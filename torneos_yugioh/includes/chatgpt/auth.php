<?php
declare(strict_types=1);

final class YgoMcpAuth
{
    public const SCOPE = 'stationx:read';
    public function __construct(public readonly array $config)
    {
        if (empty($config['resource']) || empty($config['owner_password_hash']) || empty($config['state_dir'])) throw new RuntimeException('Connector is not configured.');
        $url=parse_url($config['resource']);
        if (($url['scheme']??'')!=='https' && !(($url['scheme']??'')==='http' && in_array($url['host']??'',['127.0.0.1','localhost'],true))) throw new RuntimeException('HTTPS required.');
        if (isset($url['query']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass']) || !str_ends_with($config['resource'],'/')) throw new RuntimeException('Invalid resource.');
        $root=realpath($_SERVER['DOCUMENT_ROOT']??'')?:dirname(__DIR__,2);
        $dir=rtrim($config['state_dir'],'/\\');
        if (!preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~',$dir) || preg_match('~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)~',$dir)) throw new RuntimeException('OAuth storage requires an absolute private path.');
        $resolved=realpath($dir)?:$dir;
        if (str_starts_with(str_replace('\\','/',$resolved).'/',str_replace('\\','/',$root).'/')) throw new RuntimeException('OAuth state must stay outside the web root.');
    }
    public static function load(): self
    {
        $app=dirname(__DIR__,2);
        $path=getenv('STATION_X_CHATGPT_CONFIG')?:dirname($app,2).'/yugioh-chatgpt-config.php';
        if (!is_file($path)) throw new RuntimeException('Connector is not configured.');
        return new self(require $path);
    }
    public function resource(): string { return $this->config['resource']; }
    public function issuer(): string { return $this->resource().'oauth'; }
    public function metadata(): array
    {
        return ['issuer'=>$this->issuer(),'authorization_endpoint'=>$this->issuer().'/authorize','token_endpoint'=>$this->issuer().'/token','registration_endpoint'=>$this->issuer().'/register','revocation_endpoint'=>$this->issuer().'/revoke','response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['none'],'scopes_supported'=>[self::SCOPE],'authorization_response_iss_parameter_supported'=>true,'client_id_metadata_document_supported'=>true];
    }
    public function resourceMetadata(): array
    {
        return ['resource'=>$this->resource(),'authorization_servers'=>[$this->issuer()],'scopes_supported'=>[self::SCOPE],'bearer_methods_supported'=>['header'],'resource_name'=>'Station X · Yu-Gi-Oh (lectura)','resource_documentation'=>$this->resource().'connect'];
    }
    public function challenge(): string
    {
        return 'Bearer resource_metadata="'.$this->resource().'.well-known/oauth-protected-resource", scope="'.self::SCOPE.'"';
    }
    private static function random(): string { return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'='); }
    private static function key(string $value): string { return hash('sha256',$value); }
    private function state(callable $operation): mixed
    {
        $dir=$this->config['state_dir'];
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Could not initialize OAuth storage.');
        $lock=fopen($dir.'/oauth.lock','c');if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('OAuth storage is busy.');
        try {
            $file=$dir.'/oauth.json';
            if (is_file($file)) {
                if (filesize($file)>3000000) throw new RuntimeException('OAuth storage limit.');
                $db=json_decode(file_get_contents($file),true,128,JSON_THROW_ON_ERROR);
            } else $db=['clients'=>[],'pending'=>[],'codes'=>[],'tokens'=>[],'limits'=>[]];
            $now=time();foreach(['pending','codes','tokens','limits'] as $group)foreach($db[$group] as $id=>$record)if(($record['expires']??0)<=$now)unset($db[$group][$id]);
            $result=$operation($db);
            $json=json_encode($db,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            if (strlen($json)>3000000) throw new RuntimeException('OAuth storage limit.');
            $temp=$dir.'/oauth-'.bin2hex(random_bytes(8)).'.tmp';
            if (file_put_contents($temp,$json)===false) throw new RuntimeException('Could not save OAuth state.');
            chmod($temp,0600);if(!rename($temp,$file)){@unlink($temp);throw new RuntimeException('Could not save OAuth state.');}
            return $result;
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
    private function rate(array &$db,string $action,int $max,int $seconds): bool
    {
        $key=hash_hmac('sha256',$action.':'.($_SERVER['REMOTE_ADDR']??'local'),$this->config['owner_password_hash']);
        $entry=$db['limits'][$key]??['count'=>0,'expires'=>time()+$seconds];$entry['count']++;$db['limits'][$key]=$entry;
        return $entry['count']<=$max;
    }
    public function allowedRedirect(string $uri): bool
    {
        if (in_array($uri,$this->config['allowed_redirect_uris']??[],true)) return true;
        $p=parse_url($uri);
        return ($p['scheme']??'')==='https' && ($p['host']??'')==='chatgpt.com' && !isset($p['port']) && !isset($p['user']) && !isset($p['pass']) && !isset($p['query']) && !isset($p['fragment']) && (bool)preg_match('~^/(?:connector_platform_oauth_redirect|connector/oauth/[A-Za-z0-9_-]{8,160})$~D',$p['path']??'');
    }
    public function register(array $input): array
    {
        $redirects=$input['redirect_uris']??[];
        if (!is_array($redirects) || !array_is_list($redirects) || count($redirects)<1 || count($redirects)>5) throw new InvalidArgumentException('invalid_redirect_uri');
        foreach($redirects as $uri)if(!is_string($uri)||!$this->allowedRedirect($uri))throw new InvalidArgumentException('invalid_redirect_uri');
        if (($input['token_endpoint_auth_method']??'none')!=='none') throw new InvalidArgumentException('invalid_client_metadata');
        $result=$this->state(function(array &$db)use($redirects){
            if (!$this->rate($db,'register',30,3600) || count($db['clients'])>=200) return null;
            $id='sx-'.self::random();$row=['client_id'=>$id,'client_id_issued_at'=>time(),'client_name'=>'ChatGPT · Station X','redirect_uris'=>array_values(array_unique($redirects)),'token_endpoint_auth_method'=>'none','grant_types'=>['authorization_code','refresh_token'],'response_types'=>['code']];$db['clients'][$id]=$row;return $row;
        });
        if (!$result) throw new OverflowException('registration_limit');return $result;
    }
    private function client(string $id): array
    {
        $cached=$this->state(static fn(array &$db)=>$db['clients'][$id]??null);
        if ($cached && (!str_starts_with($id,'https://') || ($cached['expires']??0)>time())) return $cached;
        if (!preg_match('~^https://chatgpt\.com/oauth/(?:[A-Za-z0-9_-]{8,160}/)?client\.json$~D',$id)) throw new InvalidArgumentException('invalid_client');
        $context=stream_context_create(['http'=>['timeout'=>8,'follow_location'=>0,'header'=>"Accept: application/json\r\n"]]);
        $body=@file_get_contents($id,false,$context,0,65537);
        if ($body===false || strlen($body)>65536) throw new InvalidArgumentException('invalid_client_metadata');
        $metadata=json_decode($body,true,64,JSON_THROW_ON_ERROR);
        if (($metadata['client_id']??$id)!==$id || empty($metadata['redirect_uris']) || !is_array($metadata['redirect_uris']) || count($metadata['redirect_uris'])>5) throw new InvalidArgumentException('invalid_client_metadata');
        foreach($metadata['redirect_uris'] as $uri)if(!is_string($uri)||!$this->allowedRedirect($uri))throw new InvalidArgumentException('invalid_redirect_uri');
        $row=['client_id'=>$id,'client_name'=>'ChatGPT · Station X','redirect_uris'=>$metadata['redirect_uris'],'token_endpoint_auth_method'=>'none','expires'=>time()+86400];
        $this->state(function(array &$db)use($id,$row){if(count($db['clients'])>=200&&!isset($db['clients'][$id]))throw new OverflowException('registration_limit');$db['clients'][$id]=$row;return null;});return $row;
    }
    public function startAuthorization(array $params,string $sessionNonce): string
    {
        $client=$this->client((string)($params['client_id']??''));$redirect=(string)($params['redirect_uri']??'');
        if (!in_array($redirect,$client['redirect_uris'],true)) throw new InvalidArgumentException('invalid_redirect_uri');
        if (($params['response_type']??'')!=='code' || ($params['code_challenge_method']??'')!=='S256' || !preg_match('/^[A-Za-z0-9_-]{43}$/D',(string)($params['code_challenge']??''))) throw new InvalidArgumentException('invalid_request');
        if (($params['resource']??$this->resource())!==$this->resource() || ($params['scope']??self::SCOPE)!==self::SCOPE) throw new InvalidArgumentException('invalid_scope_or_resource');
        $state=(string)($params['state']??'');if(strlen($state)>2048)throw new InvalidArgumentException('invalid_state');
        $id=$this->state(function(array &$db)use($client,$redirect,$params,$state,$sessionNonce){
            if (!$this->rate($db,'authorize',60,900) || count($db['pending'])>=200) return null;
            $id=self::random();$db['pending'][$id]=['client_id'=>$client['client_id'],'redirect_uri'=>$redirect,'challenge'=>$params['code_challenge'],'state'=>$state,'nonce'=>self::key($sessionNonce),'expires'=>time()+600];return $id;
        });if(!$id)throw new OverflowException('authorization_limit');return $id;
    }
    public function approve(string $pending,string $sessionNonce,string $password,bool $consent): array
    {
        $result=$this->state(function(array &$db)use($pending,$sessionNonce,$password,$consent){
            if (!$this->rate($db,'owner_login',10,900)) return ['error'=>'login_limit'];
            $request=$db['pending'][$pending]??null;
            if (!$request || !hash_equals($request['nonce'],self::key($sessionNonce))) return ['error'=>'expired_request'];
            if (!$consent) { unset($db['pending'][$pending]);return ['redirect'=>$request['redirect_uri'],'error_response'=>'access_denied','state'=>$request['state'],'iss'=>$this->issuer()]; }
            if (!password_verify($password,$this->config['owner_password_hash'])) return ['error'=>'invalid_owner_code'];
            unset($db['pending'][$pending]);$code=self::random();$db['codes'][self::key($code)]=['client_id'=>$request['client_id'],'redirect_uri'=>$request['redirect_uri'],'challenge'=>$request['challenge'],'resource'=>$this->resource(),'expires'=>time()+90];
            return ['redirect'=>$request['redirect_uri'],'code'=>$code,'state'=>$request['state'],'iss'=>$this->issuer()];
        });
        if (isset($result['error'])) throw new InvalidArgumentException($result['error']);return $result;
    }
    private function issue(array &$db,string $client,string $family): array
    {
        if(count($db['tokens'])>1500)throw new OverflowException('token_limit');
        $access='sxat_'.self::random();$refresh='sxrt_'.self::random();
        $base=['client_id'=>$client,'scope'=>self::SCOPE,'resource'=>$this->resource(),'family'=>$family];
        $db['tokens'][self::key($access)]=$base+['kind'=>'access','expires'=>time()+3600];
        $db['tokens'][self::key($refresh)]=$base+['kind'=>'refresh','expires'=>time()+30*86400];
        return ['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>3600,'refresh_token'=>$refresh,'scope'=>self::SCOPE];
    }
    public function token(array $input): array
    {
        if (($input['resource']??$this->resource())!==$this->resource()) throw new InvalidArgumentException('invalid_target');
        $client=(string)($input['client_id']??'');if($client==='')throw new InvalidArgumentException('invalid_client');
        $result=$this->state(function(array &$db)use($input,$client){
            if (!$this->rate($db,'exchange',120,900)) return ['error'=>'token_limit'];
            if (($input['grant_type']??'')==='authorization_code') {
                $key=self::key((string)($input['code']??''));$code=$db['codes'][$key]??null;$verifier=(string)($input['code_verifier']??'');
                if (!$code || $code['client_id']!==$client || $code['redirect_uri']!==($input['redirect_uri']??'') || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$verifier) || !hash_equals($code['challenge'],rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='))) return ['error'=>'invalid_grant'];
                unset($db['codes'][$key]);return $this->issue($db,$client,self::random());
            }
            if (($input['grant_type']??'')==='refresh_token') {
                $key=self::key((string)($input['refresh_token']??''));$token=$db['tokens'][$key]??null;
                if (!$token || $token['kind']!=='refresh' || $token['client_id']!==$client) return ['error'=>'invalid_grant'];
                unset($db['tokens'][$key]);return $this->issue($db,$client,$token['family']);
            }
            return ['error'=>'unsupported_grant_type'];
        });if(isset($result['error']))throw new InvalidArgumentException($result['error']);return $result;
    }
    public function authenticate(string $bearer): ?array
    {
        if (!preg_match('/^sxat_[A-Za-z0-9_-]{43}$/D',$bearer)) return null;
        return $this->state(function(array &$db)use($bearer){$token=$db['tokens'][self::key($bearer)]??null;return $token && $token['kind']==='access' && $token['scope']===self::SCOPE && $token['resource']===$this->resource() ? $token : null;});
    }
    public function revoke(string $value,string $client): void
    {
        $this->state(function(array &$db)use($value,$client){$row=$db['tokens'][self::key($value)]??null;if($row && $row['client_id']===$client)foreach($db['tokens'] as $key=>$token)if($token['family']===$row['family'])unset($db['tokens'][$key]);return null;});
    }
}
