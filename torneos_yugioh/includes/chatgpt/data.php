<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/src/autoload.php';

/** Narrow read model. Never bootstrap index.php or initialize platform tables. */
final class YgoMcpData
{
    public const LIBRARY_SECTIONS = ['documents','card_profiles','card_checks','route_checks','source_index','verification','coverage','official_card_texts','historical_records'];
    public const SECTIONS = ['profile','cards','card_metadata','combos','interruptions','matchups','topics','skills','hands','probability','original_record',...self::LIBRARY_SECTIONS];

    public function __construct(private PDO $pdo, private string $app, private string $site)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($pdo->inTransaction()) throw new RuntimeException('A dedicated read connection is required.');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $pdo->exec('SET TRANSACTION READ ONLY');
        else $pdo->exec('PRAGMA query_only=ON');
        $pdo->beginTransaction();
    }

    public function __destruct() { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }

    private function rows(string $sql, array $params=[]): array
    {
        $q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetchAll();
    }

    private function optional(string $sql, array $params=[]): array
    {
        try { return $this->rows($sql,$params); }
        catch(PDOException $e) {
            // Only a genuinely absent optional table is an empty feature.
            if (($e->errorInfo[1]??0)===1146 || str_contains($e->getMessage(),'no such table:')) return [];
            throw $e;
        }
    }

    private function jsonFile(string $relative): array
    {
        $path=realpath($this->app.'/'.$relative);$root=realpath($this->app.'/assets/editorial');
        if (!$path || !$root || !str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$root).'/') || !str_ends_with($path,'.json') || filesize($path)>16000000) throw new RuntimeException('Published guide is unavailable.');
        return json_decode(file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
    }

    private function manifest(): array { return $this->jsonFile('assets/editorial/deck-deliveries.json'); }
    private function url(int $deck, string $section=''): string
    {
        return $this->site.'?deck_id='.$deck.($section!==''?'&deck_section='.$section:'').'#deck_ficha';
    }

    public function leagues(?int $season, int $offset): array
    {
        return (new \StationX\Yugioh\Infrastructure\Persistence\McpScheduleReader($this->pdo))->leagues($season,$offset);
    }

    public function matches(?int $league, ?int $division, ?int $deck, ?int $season, int $offset): array
    {
        return (new \StationX\Yugioh\Infrastructure\Persistence\McpScheduleReader($this->pdo))->matches($league,$division,$deck,$season,$offset);
    }

    public function deckSchedule(int $deck, ?int $season, int $offset): array
    {
        return (new \StationX\Yugioh\Infrastructure\Persistence\McpScheduleReader($this->pdo))->deckSchedule($deck,$season,$offset);
    }

    public function decks(string $query='', int $offset=0, int $limit=30): array
    {
        $all=$this->rows('SELECT d.id,d.name,d.active,d.style,(SELECT COALESCE(SUM(c.quantity),0) FROM sx_ygo_deck_cards c WHERE c.deck_id=d.id AND c.section=\'main\' AND c.quantity>0) AS main_copies FROM sx_ygo_decks d ORDER BY d.name,d.id');
        $all=array_values(array_filter($all,static fn($d)=>$query==='' || mb_stripos($d['name'],$query)!==false));
        foreach($all as &$d) { $d['id']=(int)$d['id'];$d['main_copies']=(int)$d['main_copies'];$d['cards_loaded']=$d['main_copies']>0;$d['url']=$this->url($d['id']); } unset($d);
        return ['decks'=>array_slice($all,$offset,$limit),'total'=>count($all),'next_offset'=>$offset+$limit<count($all)?$offset+$limit:null];
    }

    public function deck(int $id): array
    {
        $deck=$this->rows('SELECT id,name,archetype,style,notes,active FROM sx_ygo_decks WHERE id=?',[$id])[0]??null;
        if (!$deck) throw new InvalidArgumentException('El deck no existe.');
        $cards=$this->rows('SELECT id,deck_id,card_name,quantity,section,ygoprodeck_id FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY id',[$id]);
        $loaded=false;
        foreach($cards as &$c) { $c['id']=(int)$c['id'];$c['deck_id']=(int)$c['deck_id'];$c['quantity']=(int)$c['quantity'];$c['ygoprodeck_id']=$c['ygoprodeck_id']?(int)$c['ygoprodeck_id']:null;if($c['section']==='main'&&$c['quantity']>0)$loaded=true; } unset($c);
        $profile=$this->optional('SELECT play_style,pace,complexity,primary_game_plan,win_condition,resource_engine,opening_plan,mid_game_plan,late_game_plan,going_first_plan,going_second_plan,side_deck_strategy,strengths_json,weaknesses_json,strategy_tags_json,consistency_score,ceiling_score,resilience_score,interaction_score,recovery_score,first_score,second_score,confidence,source_type,analysis_notes,revision,updated_at FROM sx_ygo_deck_analysis_profiles WHERE deck_id=?',[$id])[0]??null;
        return ['deck'=>$deck,'cards_loaded'=>$loaded,'cards'=>$cards,'inventory_sha256'=>hash('sha256',json_encode($cards,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),'profile'=>$profile,'url'=>$this->url($id),'retrieved_at'=>gmdate(DATE_ATOM),'note'=>$loaded?'Inventario real. Main, Extra, Side y reserva permanecen separados. No certifica legalidad ni ejecución de combos.':'Sin Main cargado: no generar jugadas ni asumir cartas.'];
    }

    public function guide(int $id,string $section,int $offset=0): array
    {
        if (!in_array($section,self::SECTIONS,true)) throw new InvalidArgumentException('Sección desconocida.');
        if (!$this->deck($id)['cards_loaded']) throw new InvalidArgumentException('Carga primero el Main Deck; no hay una lista para contrastar esta guía.');
        $m=$this->manifest();$entry=$m['decks'][(string)$id]??null;
        if (!$entry) throw new InvalidArgumentException('Este deck todavía no tiene una entrega editorial publicada.');
        $relative=parse_url($entry['url'],PHP_URL_PATH);
        if (!is_string($relative) || !str_starts_with($relative,'./assets/editorial/')) throw new RuntimeException('Invalid published guide reference.');
        $record=$this->jsonFile(substr($relative,2));
        if ((int)($record['deckId']??0)!==$id) throw new RuntimeException('Guide ownership mismatch.');
        $source=$record;
        if(in_array($section,self::LIBRARY_SECTIONS,true)){
            $source=$this->jsonFile('assets/editorial/deck-library/deck-'.$id.'.json');
            if((int)($source['deck_id']??0)!==$id || empty($record['source_sha256']) || !hash_equals((string)$record['source_sha256'],(string)($source['source_sha256']??'')))throw new InvalidArgumentException('La biblioteca detallada no corresponde a la revisión publicada de este deck. No se mezclan entregas.');
        }
        $text=json_encode($source[$section]??null,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $size=16000;$length=mb_strlen($text);if($offset>$length)throw new InvalidArgumentException('Offset fuera del documento.');
        return ['deck_id'=>$id,'section'=>$section,'text'=>mb_substr($text,$offset,$size),'offset'=>$offset,'total_characters'=>$length,'next_offset'=>$offset+$size<$length?$offset+$size:null,'published_version'=>$m['version']??null,'source_sha256'=>$record['source_sha256']??null,'publication_status'=>$record['publication_status']??null,'url'=>$this->url($id,'strategy'),'note'=>'Contenido documental; comprobar que las cartas y cantidades del inventario actual coinciden con la construcción estudiada. Seguir next_offset hasta null para leer todo.'];
    }

    public function combos(int $id,int $offset=0): array
    {
        $this->deck($id);
        $all=$this->optional('SELECT id,deck_id,title,revision,updated_at FROM sx_ygo_created_combos WHERE deck_id=? ORDER BY updated_at DESC,id',[$id]);
        return ['combos'=>array_slice($all,$offset,30),'total'=>count($all),'next_offset'=>$offset+30<count($all)?$offset+30:null,'url'=>$this->url($id,'created-combos')];
    }

    public function combo(string $id): array
    {
        $row=$this->optional('SELECT id,deck_id,title,revision,inventory_sha256,document_json,inventory_json,created_at,updated_at FROM sx_ygo_created_combos WHERE id=?',[$id])[0]??null;
        if(!$row)throw new InvalidArgumentException('El combo no existe.');
        $deck=$this->deck((int)$row['deck_id']);
        $row['document']=json_decode($row['document_json'],true,128,JSON_THROW_ON_ERROR);$row['cards_snapshot']=json_decode($row['inventory_json'],true,128,JSON_THROW_ON_ERROR);unset($row['document_json'],$row['inventory_json']);
        $row['inventory_changed']=!hash_equals($row['inventory_sha256'],$deck['inventory_sha256']);$row['cards_loaded']=$deck['cards_loaded'];$row['url']=$this->url((int)$row['deck_id'],'created-combos');
        return $row;
    }

    public function search(string $query): array
    {
        $results=[];
        foreach($this->decks($query,0,50)['decks'] as $d)$results[]=['id'=>'deck:'.$d['id'],'title'=>$d['name'].($d['cards_loaded']?' · lista cargada':' · sin cartas'),'url'=>$d['url']];
        $combos=$this->optional('SELECT id,deck_id,title FROM sx_ygo_created_combos ORDER BY updated_at DESC,id');
        foreach($combos as $c)if(mb_stripos($c['title'],$query)!==false&&count($results)<50)$results[]=['id'=>'combo:'.$c['id'],'title'=>$c['title'],'url'=>$this->url((int)$c['deck_id'],'created-combos')];
        return ['results'=>$results,'note'=>'Busca nombres de decks o títulos de combos propios. Para estudiar contenido utiliza get_guide por deck y sección; no es búsqueda de texto completo.'];
    }

    public function fetch(string $id): array
    {
        if(preg_match('/^deck:([1-9][0-9]*)$/D',$id,$m)){ $data=$this->deck((int)$m[1]);$title=$data['deck']['name']; }
        elseif(preg_match('/^guide:([1-9][0-9]*):([a-z_]+):([0-9]+)$/D',$id,$m)){ $data=$this->guide((int)$m[1],$m[2],(int)$m[3]);$title='Guía '.$m[1].' · '.$m[2]; }
        elseif(preg_match('/^combo:([A-Za-z0-9-]{16,64})$/D',$id,$m)){ $data=$this->combo($m[1]);$title=$data['title']; }
        else throw new InvalidArgumentException('ID no válido. Usa deck:ID, guide:ID:seccion:offset o combo:ID.');
        return ['id'=>$id,'title'=>$title,'text'=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'url'=>$data['url'],'metadata'=>['next_id'=>isset($data['next_offset'])?'guide:'.$data['deck_id'].':'.$data['section'].':'.$data['next_offset']:null]];
    }
}
