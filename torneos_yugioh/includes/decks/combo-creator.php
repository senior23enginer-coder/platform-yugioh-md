<?php
declare(strict_types=1);
require_once __DIR__.'/combo-creator-details.php';
require_once __DIR__.'/combo-source.php';

/** User-authored guides. No decklist, result or editorial-source writes. */
function ygo_combo_schema(PDO $pdo): void
{
    $text = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'TEXT' : 'LONGTEXT';
    $engine = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS sx_ygo_created_combos (
        id VARCHAR(64) PRIMARY KEY, deck_id INT NOT NULL, title VARCHAR(160) NOT NULL,
        revision INT NOT NULL, inventory_sha256 VARCHAR(64) NOT NULL,
        document_json $text NOT NULL, inventory_json $text NOT NULL, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL
    )".$engine);
    $pdo->exec("CREATE TABLE IF NOT EXISTS sx_ygo_created_combo_revisions (
        combo_id VARCHAR(64) NOT NULL, revision INT NOT NULL, snapshot_json $text NOT NULL,
        created_at VARCHAR(19) NOT NULL, PRIMARY KEY(combo_id, revision)
    )".$engine);
}

function ygo_combo_text(mixed $value, int $limit, bool $required = false): string
{
    if (!is_string($value) || mb_strlen($value) > $limit) throw new InvalidArgumentException('Texto no válido o demasiado largo.');
    $value = trim($value);
    if ($required && $value === '') throw new InvalidArgumentException('Completa el título del combo y de cada paso.');
    return $value;
}

function ygo_combo_inventory(PDO $pdo, int $deckId, bool $lock = false, bool $requireMain = true): array
{
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $q = $pdo->prepare('SELECT id,name FROM sx_ygo_decks WHERE id=?'.$suffix);
    $q->execute([$deckId]);
    $deck = $q->fetch(PDO::FETCH_ASSOC);
    if (!$deck) throw new InvalidArgumentException('El deck no existe.');
    $q = $pdo->prepare('SELECT id,deck_id,card_name,quantity,section,ygoprodeck_id FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY id'.$suffix);
    $q->execute([$deckId]); $cards = $q->fetchAll(PDO::FETCH_ASSOC);
    $loaded=(bool)array_filter($cards, static fn($c) => $c['section'] === 'main' && (int)$c['quantity'] > 0);
    if ($requireMain && !$loaded) throw new InvalidArgumentException('Carga primero el Main Deck.');
    foreach ($cards as &$c) { $c['id']=(int)$c['id']; $c['deck_id']=(int)$c['deck_id']; $c['quantity']=(int)$c['quantity']; $c['ygoprodeck_id']=$c['ygoprodeck_id'] ? (int)$c['ygoprodeck_id'] : null; } unset($c);
    return ['deck'=>$deck, 'cards'=>$cards, 'cards_loaded'=>$loaded, 'inventory_sha256'=>hash('sha256', json_encode($cards, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))]+ygo_combo_context($deckId);
}

function ygo_combo_document(array $input, array $inventory): array
{
    $id = (string)($input['id'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $id)) throw new InvalidArgumentException('Identificador de combo no válido.');
    $kind = (string)($input['kind'] ?? 'combo');
    if (!in_array($kind, ['combo','strategy'], true)) throw new InvalidArgumentException('Tipo de guía no válido.');
    $turn = (string)($input['turn_order'] ?? 'any');
    if (!in_array($turn, ['any','first','second'], true)) throw new InvalidArgumentException('Orden de turno no válido.');
    $format=$input['board_format']??'unspecified';$status=$input['editorial_status']??'draft';
    if(!in_array($format,['unspecified','tcg','rush','goat'],true)||!in_array($status,['draft','conditional','pending','not_executable'],true))throw new InvalidArgumentException('Formato o estado documental no válido.');
    if($format!=='unspecified'&&($inventory['format']??'unspecified')!=='unspecified'&&$format!==$inventory['format'])throw new InvalidArgumentException('El tablero no corresponde al formato documentado del deck.');
    $nodes = $input['nodes'] ?? null; $edges = $input['edges'] ?? null;
    if (!is_array($nodes) || !array_is_list($nodes) || count($nodes)<1 || count($nodes)>60 || !is_array($edges) || !array_is_list($edges) || count($edges)>120) throw new InvalidArgumentException('La guía admite de 1 a 60 pasos y hasta 120 conexiones.');
    $allowed = array_column($inventory['cards'], null, 'id'); $seen=[]; $clean=[];
    foreach ($nodes as $n) {
        if (!is_array($n)) throw new InvalidArgumentException('Paso no válido.');
        $nid=(string)($n['id']??'');
        if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/D',$nid) || isset($seen[$nid])) throw new InvalidArgumentException('Identificador de paso repetido o no válido.');
        $seen[$nid]=true; $type=(string)($n['kind']??'step');
        if (!in_array($type,['opening','step','decision','result'],true)) throw new InvalidArgumentException('Tipo de paso no válido.');
        foreach (['x','y'] as $axis) if (!isset($n[$axis]) || !is_numeric($n[$axis]) || !is_finite((float)$n[$axis]) || (float)$n[$axis]<0 || (float)$n[$axis]>5000) throw new InvalidArgumentException('Posición fuera del lienzo.');
        $refs=$n['cards']??[];
        if (!is_array($refs) || !array_is_list($refs) || count($refs)>8) throw new InvalidArgumentException('Cada paso admite hasta ocho cartas.');
        $cards=[]; $copies=[];
        foreach ($refs as $ref) {
            $cid=filter_var($ref,FILTER_VALIDATE_INT);
            if (!$cid || !isset($allowed[$cid])) throw new InvalidArgumentException('La carta no pertenece al deck seleccionado.');
            $copies[$cid]=($copies[$cid]??0)+1;
            if ($copies[$cid]>(int)$allowed[$cid]['quantity']) throw new InvalidArgumentException('El paso usa más copias de una carta que la lista cargada.');
            if ($type==='opening' && $allowed[$cid]['section']!=='main') throw new InvalidArgumentException('La mano inicial solo admite cartas del Main Deck.');
            $cards[]=$cid;
        }
        $clean[]=['id'=>$nid,'kind'=>$type,'title'=>ygo_combo_text($n['title']??'',120,true),'action'=>ygo_combo_text($n['action']??'',80),'explanation'=>ygo_combo_text($n['explanation']??'',4000),'x'=>round((float)$n['x'],1),'y'=>round((float)$n['y'],1),'cards'=>$cards]+ygo_combo_details($n,$cards,$format);
    }
    $links=[]; $pairs=[]; $degree=array_fill_keys(array_keys($seen),0); $out=[];
    foreach ($edges as $edge) {
        if (!is_array($edge)) throw new InvalidArgumentException('Conexión no válida.');
        $from=(string)($edge['from']??'');$to=(string)($edge['to']??'');$key=$from.':'.$to;
        if (!isset($seen[$from],$seen[$to]) || $from===$to || isset($pairs[$key])) throw new InvalidArgumentException('Conexión repetida, circular o sin un paso válido.');
        $pairs[$key]=true; $degree[$to]++; $out[$from][]=$to;
        $links[]=['from'=>$from,'to'=>$to,'label'=>ygo_combo_text($edge['label']??'',160)];
    }
    $queue=array_keys(array_filter($degree,static fn($n)=>$n===0));$visited=0;
    while ($queue) { $from=array_shift($queue);$visited++;foreach ($out[$from]??[] as $to) if (--$degree[$to]===0) $queue[]=$to; }
    if ($visited!==count($clean)) throw new InvalidArgumentException('La conexión crea un ciclo. Usa un paso nuevo para explicar una continuación.');
    $optional=[];
    if(isset($input['board_format']))$optional['board_format']=$format;
    if(isset($input['editorial_status']))$optional['editorial_status']=$status;
    if(!empty($input['skill_id'])){
        $skill=null;foreach($inventory['skills']??[] as $item)if($item['id']===$input['skill_id'])$skill=$item;
        if(!$skill||$format!=='rush')throw new InvalidArgumentException('La Skill no pertenece al deck o al formato Rush.');
        $optional['skill_id']=$skill['id'];$optional['skill_snapshot']=$skill;
    }
    return ['schema_version'=>1,'id'=>$id,'deck_id'=>(int)$inventory['deck']['id'],'kind'=>$kind,'title'=>ygo_combo_text($input['title']??'',160,true),'objective'=>ygo_combo_text($input['objective']??'',4000),'conditions'=>ygo_combo_text($input['conditions']??'',6000),'turn_order'=>$turn,'nodes'=>$clean,'edges'=>$links]+$optional+ygo_combo_source_reference($input,(int)$inventory['deck']['id']);
}

function ygo_combo_unpack(array $row, string $hash): array
{
    return ['id'=>$row['id'],'deck_id'=>(int)$row['deck_id'],'title'=>$row['title'],'revision'=>(int)$row['revision'],'inventory_sha256'=>$row['inventory_sha256'],'stale'=>$row['inventory_sha256']!==$hash,'cards_snapshot'=>json_decode($row['inventory_json'],true,128,JSON_THROW_ON_ERROR),'document'=>json_decode($row['document_json'],true,128,JSON_THROW_ON_ERROR),'updated_at'=>$row['updated_at']];
}

function ygo_combo_save(PDO $pdo, array $input): array
{
    if (!isset($input['deck_id'],$input['revision'],$input['document']) || !is_int($input['deck_id']) || $input['deck_id']<1 || !is_int($input['revision']) || $input['revision']<0 || !is_array($input['document'])) throw new InvalidArgumentException('Deck, documento o revisión no válidos.');
    $pdo->beginTransaction();
    try {
        $inventory=ygo_combo_inventory($pdo,(int)($input['deck_id']??0),true);
        if (!hash_equals($inventory['inventory_sha256'],(string)($input['inventory_sha256']??''))) throw new DomainException('La lista de cartas cambió. Recarga las cartas antes de guardar; tu trabajo sigue en el editor.');
        $document=ygo_combo_document($input['document']??[],$inventory);
        $json=json_encode($document,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $q=$pdo->prepare('SELECT * FROM sx_ygo_created_combos WHERE id=?');$q->execute([$document['id']]);$old=$q->fetch(PDO::FETCH_ASSOC);
        $revision=(int)($input['revision']??0);
        if ($old && (int)$old['deck_id']!==(int)$document['deck_id']) throw new InvalidArgumentException('No puedes trasladar un combo a otro deck.');
        if ($old && (int)$old['revision']!==$revision) {
            if ($old['document_json']===$json && $old['inventory_sha256']===$inventory['inventory_sha256']) { $pdo->commit(); return ygo_combo_unpack($old,$inventory['inventory_sha256']); }
            throw new DomainException('Otra edición guardó cambios. Abre esa versión en otra pestaña antes de reemplazar tu trabajo.');
        }
        if (!$old && $revision!==0) throw new DomainException('La versión del combo ya no está disponible.');
        $now=gmdate('Y-m-d H:i:s'); $next=$revision+1; $inventoryJson=json_encode($inventory['cards'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if ($old) {
            $q=$pdo->prepare('UPDATE sx_ygo_created_combos SET title=?,revision=?,inventory_sha256=?,document_json=?,inventory_json=?,updated_at=? WHERE id=? AND revision=?');
            $q->execute([$document['title'],$next,$inventory['inventory_sha256'],$json,$inventoryJson,$now,$document['id'],$revision]);
            if ($q->rowCount()!==1) throw new DomainException('El combo cambió durante el guardado. Tu edición se conserva.');
        } else {
            $q=$pdo->prepare('INSERT INTO sx_ygo_created_combos(id,deck_id,title,revision,inventory_sha256,document_json,inventory_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
            $q->execute([$document['id'],$document['deck_id'],$document['title'],$next,$inventory['inventory_sha256'],$json,$inventoryJson,$now,$now]);
        }
        $row=['id'=>$document['id'],'deck_id'=>$document['deck_id'],'title'=>$document['title'],'revision'=>$next,'inventory_sha256'=>$inventory['inventory_sha256'],'document_json'=>$json,'inventory_json'=>$inventoryJson,'updated_at'=>$now];
        if(isset($document['source_route_id']))$row['source_prompt']=ygo_combo_source_markdown(ygo_combo_unpack($row,$inventory['inventory_sha256']));
        $q=$pdo->prepare('INSERT INTO sx_ygo_created_combo_revisions(combo_id,revision,snapshot_json,created_at) VALUES(?,?,?,?)');
        $q->execute([$document['id'],$next,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$now]);
        $pdo->commit();return ygo_combo_unpack($row,$inventory['inventory_sha256']);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
}
