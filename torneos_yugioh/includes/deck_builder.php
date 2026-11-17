<?php
declare(strict_types=1);

function ygo_card_cache_dir(): string {
    $dir=dirname(__DIR__).'/assets/cache/ygoprodeck';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el catálogo de cartas.');
    return $dir;
}

/** Fixed upstream endpoints only. Cached responses and a shared throttle protect the provider. */
function ygo_card_download(string $url,int $max=4000000): string {
    $dir=ygo_card_cache_dir();$lock=fopen($dir.'/upstream.lock','c+');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('El catálogo está ocupado. Inténtalo de nuevo.');
    try{
        $last=(float)stream_get_contents($lock);$delay=0.25-(microtime(true)-$last);if($delay>0)usleep((int)($delay*1000000));
        $body='';$ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'StationX-DeckBuilder/1.0',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_WRITEFUNCTION=>static function($c,$chunk)use(&$body,$max){if(strlen($body)+strlen($chunk)>$max)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        rewind($lock);ftruncate($lock,0);fwrite($lock,(string)microtime(true));
        if($ok===false||!in_array($status,[200,400],true))throw new RuntimeException('YGOPRODeck no está disponible en este momento. Inténtalo de nuevo.');
        return $body;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function ygo_card_cache_write(string $file,string $body): void {
    $tmp=$file.'.'.bin2hex(random_bytes(6)).'.tmp';
    if(file_put_contents($tmp,$body,LOCK_EX)===false||!rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('No se pudo guardar el catálogo local.');}
}

function ygo_card_lookup(int $id): array {
    if($id<1||$id>999999999)throw new InvalidArgumentException('Código de carta inválido.');
    $file=ygo_card_cache_dir().'/card-'.$id.'.json';
    if(is_file($file))return json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
    $answer=json_decode(ygo_card_download('https://db.ygoprodeck.com/api/v7/cardinfo.php?id='.$id),true,32,JSON_THROW_ON_ERROR);
    $card=$answer['data'][0]??null;if(!$card||(int)$card['id']!==$id)throw new InvalidArgumentException('No se encontró esta carta en YGOPRODeck.');
    $card=ygo_card_summary($card);ygo_card_cache_write($file,json_encode($card,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));return $card;
}

function ygo_card_summary(array $card): array {
    return ['id'=>(int)$card['id'],'name'=>(string)$card['name'],'type'=>(string)($card['type']??''),'description'=>(string)($card['desc']??''),'race'=>(string)($card['race']??''),'attribute'=>(string)($card['attribute']??''),'atk'=>$card['atk']??null,'def'=>$card['def']??null,'level'=>$card['level']??null,'image'=>'./?api=deck_builder&op=image&id='.(int)$card['id']];
}

/** Inspector reads exact catalog text, never a generated strategic summary. */
function ygo_card_inspector_cached(int $id,string $dir): ?array {
    if($id<1||$id>999999999)throw new InvalidArgumentException('Código de carta inválido.');
    $file=$dir.'/card-'.$id.'.json';
    if(!is_file($file))return null;
    $card=json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($card)||(int)($card['id']??0)!==$id)throw new RuntimeException('La identidad del catálogo no coincide.');
    $card['image']=is_file($dir.'/image-full-'.$id.'.jpg')?'./?api=deck_builder&op=image&size=full&local=1&id='.$id:null;
    $card['text_source']='YGOPRODeck';$card['text_language']='en';
    return $card;
}

function ygo_card_search(string $query,string $format,int $page): array {
    $query=trim($query);if(mb_strlen($query)<2||mb_strlen($query)>100)throw new InvalidArgumentException('Escribe entre 2 y 100 caracteres para buscar.');
    if(!in_array($format,['all','tcg','goat','rush duel'],true)||$page<0||$page>1000)throw new InvalidArgumentException('Filtro de búsqueda inválido.');
    $params=['num'=>20,'offset'=>$page*20,'sort'=>'name'];if(ctype_digit($query))$params['id']=$query;else $params['fname']=$query;
    if($format!=='all')$params['format']=$format;
    $file=ygo_card_cache_dir().'/search-'.hash('sha256','pagination-v2:'.json_encode($params)).'.json';
    $lock=fopen($file.'.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo abrir el catálogo.');
    try{
        if(is_file($file)&&filemtime($file)>time()-172800)return json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
        try{$remote=json_decode(ygo_card_download('https://db.ygoprodeck.com/api/v7/cardinfo.php?'.http_build_query($params)),true,32,JSON_THROW_ON_ERROR);}
        catch(Throwable $e){if(is_file($file)){ $cached=json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);$cached['stale']=true;return $cached;}throw $e;}
        if(!isset($remote['data'])&&!str_contains(strtolower((string)($remote['error']??'')),'no card matching'))throw new RuntimeException('No se pudo completar esta búsqueda.');
        $rows=array_map('ygo_card_summary',$remote['data']??[]);
        foreach($rows as $card)ygo_card_cache_write(ygo_card_cache_dir().'/card-'.$card['id'].'.json',json_encode($card,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        // total_pages upstream counts page offsets, not the number of display pages.
        $total=(int)($remote['meta']['total_rows']??count($rows));
        $result=['ok'=>true,'cards'=>$rows,'page'=>$page,'page_size'=>20,'total_rows'=>$total,'total_pages'=>(int)ceil($total/20),'has_more'=>($page+1)*20<$total,'search_language'=>'en','spanish_supported'=>false];
        ygo_card_cache_write($file,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));return $result;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function ygo_card_catalog_api(): never {
    try{
        if($_SERVER['REQUEST_METHOD']!=='GET')throw new InvalidArgumentException('Operación no permitida.');
        if(($_GET['op']??'')==='detail'){
            $id=filter_var($_GET['id']??'',FILTER_VALIDATE_INT);if(!$id||$id<1||$id>999999999)throw new InvalidArgumentException('Código inválido.');
            $localOnly=($_GET['local']??'1')==='1';
            if($localOnly)$card=ygo_card_inspector_cached($id,ygo_card_cache_dir());
            else{$card=ygo_card_lookup($id);$card['image']='./?api=deck_builder&op=image&size=full&id='.$id;$card['text_source']='YGOPRODeck';$card['text_language']='en';}
            header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
            echo json_encode(['ok'=>true,'card'=>$card],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
        }
        if(($_GET['op']??'')==='image'){
            $id=filter_var($_GET['id']??'',FILTER_VALIDATE_INT);if(!$id||$id<1||$id>999999999)throw new InvalidArgumentException('Código inválido.');
            $size=(string)($_GET['size']??'small');if(!in_array($size,['small','full'],true))throw new InvalidArgumentException('Tamaño de imagen inválido.');
            $dir=ygo_card_cache_dir();$file=$dir.'/image-'.($size==='full'?'full-':'').$id.'.jpg';
            if(!is_file($file)){
                if(($_GET['local']??'')==='1')throw new InvalidArgumentException('La imagen original no está guardada localmente.');
                // Only download images for catalogued cards; never accept a client URL.
                if(!is_file($dir.'/card-'.$id.'.json'))throw new InvalidArgumentException('Carta no catalogada.');
                $lock=fopen($file.'.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Imagen ocupada.');
                try{if(!is_file($file)){$image=ygo_card_download('https://images.ygoprodeck.com/images/'.($size==='full'?'cards':'cards_small').'/'.$id.'.jpg',1500000);if(@getimagesizefromstring($image)['mime']!=='image/jpeg')throw new RuntimeException('Imagen no disponible.');ygo_card_cache_write($file,$image);}}finally{flock($lock,LOCK_UN);fclose($lock);}
            }
            header('Content-Type: image/jpeg');header('Cache-Control: public, max-age=2592000, immutable');header('X-Content-Type-Options: nosniff');readfile($file);exit;
        }
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        echo json_encode(ygo_card_search((string)($_GET['q']??''),(string)($_GET['format']??'all'),(int)($_GET['page']??0)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){http_response_code($e instanceof InvalidArgumentException?422:503);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    exit;
}

function ygo_deck_builder_snapshot(PDO $pdo,int $id,bool $lock=false): array {
    $q=$pdo->prepare('SELECT id,name FROM sx_ygo_decks WHERE id=? AND active=1'.($lock?' FOR UPDATE':''));$q->execute([$id]);$deck=$q->fetch(PDO::FETCH_ASSOC);if(!$deck)throw new InvalidArgumentException('El deck no existe.');
    $q=$pdo->prepare('SELECT * FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY id'.($lock?' FOR UPDATE':''));$q->execute([$id]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    return ['deck'=>$deck,'cards'=>$rows,'version'=>hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))];
}

/** Preserve annotations when a draft stack is dropped onto the same card. */
function ygo_card_merge_annotations(array $old,array $source,int $cardId,mixed $merged): array {
    if(!is_array($merged)||!array_is_list($merged)||count($merged)>300)throw new InvalidArgumentException('Referencias de cartas inv?lidas.');
    foreach(array_unique($merged) as $mergedId){
        $mergedId=filter_var($mergedId,FILTER_VALIDATE_INT);
        if(!$mergedId||!isset($old[$mergedId])||(int)($old[$mergedId]['ygoprodeck_id']??0)!==$cardId||!$cardId)throw new InvalidArgumentException('No se pueden combinar estas cartas.');
        $notes=trim((string)($old[$mergedId]['notes']??''));$role=trim((string)($old[$mergedId]['role']??''));
        if(empty($source['role']))$source['role']=$role?:null;
        elseif($role!==''&&$source['role']!==$role)$notes=trim($notes."\n\nFunci?n de la carta movida: ".$role);
        if($notes!==''&&!in_array($notes,explode("\n\n",(string)($source['notes']??'')),true))$source['notes']=trim((string)($source['notes']??'')).(empty($source['notes'])?'':"\n\n").$notes;
    }
    return $source;
}

function ygo_deck_builder_save(PDO $pdo,int $id,array $input): array {
    return ygo_with_event_write_lock($pdo,static function()use($pdo,$id,$input){
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try{
            $before=ygo_deck_builder_snapshot($pdo,$id,true);
            if(!hash_equals($before['version'],(string)($input['version']??'')))throw new InvalidArgumentException('La lista cambió desde que la abriste. Recárgala antes de guardar.');
            $rows=$input['cards']??null;if(!is_array($rows)||!array_is_list($rows)||count($rows)>300)throw new InvalidArgumentException('Lista de cartas inválida.');
            $old=array_column($before['cards'],null,'id');$normalized=[];$seen=[];
            foreach($rows as $row){
                if(!is_array($row))throw new InvalidArgumentException('Carta inválida.');
                $section=(string)($row['section']??'');$quantity=filter_var($row['quantity']??null,FILTER_VALIDATE_INT);$existing=(int)($row['existing_id']??0);$cardId=(int)($row['card_id']??0);
                if(!in_array($section,['main','extra','side','reserve'],true)||!$quantity||$quantity<1||$quantity>99)throw new InvalidArgumentException('Revisa la sección y la cantidad de cada carta (1 a 99).');
                if($existing&&!isset($old[$existing]))throw new InvalidArgumentException('La carta no pertenece a esta lista.');
                $source=$existing?$old[$existing]:[];
                $source=ygo_card_merge_annotations($old,$source,$cardId,$row['merged_ids']??[]);
                if(($input['documented_import']??false)===true){
                    // Owner-approved documentary inventories may contain cards absent from the image catalog.
                    // Konami CIDs are provenance, never substituted for YGOPRODeck image IDs.
                    $name=trim((string)($row['card_name']??''));
                    if($name===''||mb_strlen($name)>160)throw new InvalidArgumentException('Nombre documental inválido.');
                    if($cardId)ygo_card_lookup($cardId);
                    $key=$section.':documented:'.mb_strtolower($name);
                    $source=['role'=>mb_substr(trim((string)($row['role']??'')),0,80)?:null,'notes'=>trim((string)($row['notes']??''))?:null];
                    if(strlen((string)$source['notes'])>4000)throw new InvalidArgumentException('La anotación documental supera el tamaño permitido.');
                }
                elseif($cardId){$card=ygo_card_lookup($cardId);$name=$card['name'];$key=$section.':card:'.$cardId;}
                else{if(!$existing||!empty($source['ygoprodeck_id']))throw new InvalidArgumentException('Selecciona una carta del buscador.');$name=$source['card_name'];$key='legacy:'.$existing;}
                if(isset($seen[$key]))throw new InvalidArgumentException('La misma carta está repetida en una sección. Ajusta su cantidad.');$seen[$key]=true;
                $normalized[]=['card_name'=>$name,'role'=>$source['role']??null,'quantity'=>$quantity,'section'=>$section,'notes'=>$source['notes']??null,'ygoprodeck_id'=>$cardId?:null];
            }
            $pdo->prepare('DELETE FROM sx_ygo_deck_cards WHERE deck_id=?')->execute([$id]);
            $insert=$pdo->prepare('INSERT INTO sx_ygo_deck_cards(deck_id,card_name,role,quantity,section,notes,ygoprodeck_id) VALUES(?,?,?,?,?,?,?)');
            foreach($normalized as $r)$insert->execute([$id,$r['card_name'],$r['role'],$r['quantity'],$r['section'],$r['notes'],$r['ygoprodeck_id']]);
            $after=ygo_deck_builder_snapshot($pdo,$id);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['deck',$id,'save_decklist',json_encode(['before'=>$before['cards'],'after'=>$after['cards']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            if($owns)$pdo->commit();return ['ok'=>true]+$after;
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    });
}

function ygo_deck_builder_api(PDO $pdo): never {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try{
        $id=(int)($_GET['deck_id']??0);$_SESSION['deck_builder_token']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(!ygo_can_edit())throw new InvalidArgumentException('No tienes permiso para editar el deck.');
            $raw=file_get_contents('php://input');if(strlen($raw)>100000)throw new InvalidArgumentException('La lista supera el tamaño permitido.');$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($input)||!hash_equals($_SESSION['deck_builder_token'],(string)($input['token']??'')))throw new InvalidArgumentException('La sesión cambió. Vuelve a abrir el editor.');
            $answer=ygo_deck_builder_save($pdo,$id,$input);
        }elseif($_SERVER['REQUEST_METHOD']==='GET')$answer=['ok'=>true,'token'=>$_SESSION['deck_builder_token'],'can_edit'=>ygo_can_edit()]+ygo_deck_builder_snapshot($pdo,$id);
        else throw new InvalidArgumentException('Operación no permitida.');
        echo json_encode($answer,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){http_response_code($e instanceof InvalidArgumentException?422:503);echo json_encode(['ok'=>false,'message'=>$e instanceof JsonException?'Los datos de la lista no son válidos.':($e instanceof PDOException?'No se pudo guardar la lista. Inténtalo de nuevo.':$e->getMessage())],JSON_UNESCAPED_UNICODE);}
    exit;
}
