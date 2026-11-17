<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/league_configuration.php';

function ygo_deck_world_eligible_ids(PDO $pdo): array {
    return array_values(array_unique(array_merge(ygo_format_deck_ids($pdo,'tcg_advanced'),ygo_format_deck_ids($pdo,'tcg_traditional'))));
}

function ygo_deck_attribute_catalog(): array {
    return ['dark'=>'OSCURIDAD','earth'=>'TIERRA','light'=>'LUZ','water'=>'AGUA','wind'=>'VIENTO','fire'=>'FUEGO'];
}
function ygo_deck_attributes_validate(mixed $attributes, bool $allowEmpty=false): array {
    if(!is_array($attributes)||!array_is_list($attributes)||count($attributes)>3||(!$allowEmpty&&!$attributes))throw new InvalidArgumentException('Selecciona entre uno y tres mundos.');
    foreach($attributes as $code)if(!is_string($code)||!array_key_exists($code,ygo_deck_attribute_catalog()))throw new InvalidArgumentException('Mundo no válido.');
    if(count(array_unique($attributes))!==count($attributes))throw new InvalidArgumentException('No puedes repetir mundos.');
    return $attributes;
}
function ygo_deck_attributes_schema(PDO $pdo): void {
    $engine=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    $pdo->exec('CREATE TABLE IF NOT EXISTS sx_ygo_deck_attributes (deck_id INT PRIMARY KEY, attributes_json TEXT NOT NULL, revision INT NOT NULL, updated_at VARCHAR(19) NOT NULL)'.$engine);
    $pdo->exec('CREATE TABLE IF NOT EXISTS sx_ygo_deck_attribute_revisions (deck_id INT NOT NULL, revision INT NOT NULL, attributes_json TEXT NOT NULL, changed_at VARCHAR(19) NOT NULL, PRIMARY KEY(deck_id,revision))'.$engine);
}
function ygo_deck_attributes_unpack(array $row): array {
    $attributes=ygo_deck_attributes_validate(json_decode($row['attributes_json'],true,8,JSON_THROW_ON_ERROR),true);
    return ['deck_id'=>(int)$row['deck_id'],'attributes'=>$attributes,'primary'=>$attributes[0]??null,'revision'=>(int)$row['revision'],'updated_at'=>$row['updated_at']??$row['changed_at']??null];
}
function ygo_deck_attributes_records(PDO $pdo): array {
    $eligible=ygo_deck_world_eligible_ids($pdo);
    return array_values(array_filter(array_map('ygo_deck_attributes_unpack',$pdo->query('SELECT a.* FROM sx_ygo_deck_attributes a JOIN sx_ygo_decks d ON d.id=a.deck_id ORDER BY a.deck_id')->fetchAll(PDO::FETCH_ASSOC)),static fn(array $r):bool=>in_array($r['deck_id'],$eligible,true)));
}
function ygo_deck_attributes_history(PDO $pdo,int $id): array {
    $q=$pdo->prepare('SELECT * FROM sx_ygo_deck_attribute_revisions WHERE deck_id=? ORDER BY revision DESC LIMIT 25');$q->execute([$id]);
    return array_map('ygo_deck_attributes_unpack',$q->fetchAll(PDO::FETCH_ASSOC));
}
function ygo_deck_attributes_save(PDO $pdo,array $input): array {
    $id=filter_var($input['deck_id']??null,FILTER_VALIDATE_INT);$expected=filter_var($input['expected_revision']??null,FILTER_VALIDATE_INT);
    if($id===false||$id<1||$expected===false||$expected<0)throw new InvalidArgumentException('Deck o revisión no válidos.');
    $action=$input['action']??'save';if(!in_array($action,['save','remove'],true))throw new InvalidArgumentException('Acción no válida.');
    if($action==='remove')throw new InvalidArgumentException('El deck debe conservar al menos un mundo. Añade otro antes de quitar el último.');
    $attributes=ygo_deck_attributes_validate($input['attributes']??null);
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try {
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $q=$pdo->prepare('SELECT id FROM sx_ygo_decks WHERE id=?'.$lock);$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('El deck no existe.');
        if(!in_array($id,ygo_deck_world_eligible_ids($pdo),true))throw new InvalidArgumentException('Mundos solo está disponible para decks TCG.');
        $q=$pdo->prepare('SELECT * FROM sx_ygo_deck_attributes WHERE deck_id=?'.$lock);$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);
        $current=$old?ygo_deck_attributes_unpack($old):['deck_id'=>$id,'attributes'=>[],'primary'=>null,'revision'=>0,'updated_at'=>null];
        if($expected!==$current['revision'])throw new DomainException('Otra edición cambió los mundos. Actualiza la lista antes de volver a guardar; tu selección sigue en pantalla.');
        if($current['attributes']===$attributes){if($own)$pdo->commit();return $current;}
        $revision=$expected+1;$now=(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');$json=json_encode($attributes,JSON_THROW_ON_ERROR);
        if($old)$pdo->prepare('UPDATE sx_ygo_deck_attributes SET attributes_json=?,revision=?,updated_at=? WHERE deck_id=?')->execute([$json,$revision,$now,$id]);
        else $pdo->prepare('INSERT INTO sx_ygo_deck_attributes(deck_id,attributes_json,revision,updated_at) VALUES(?,?,?,?)')->execute([$id,$json,$revision,$now]);
        $pdo->prepare('INSERT INTO sx_ygo_deck_attribute_revisions(deck_id,revision,attributes_json,changed_at) VALUES(?,?,?,?)')->execute([$id,$revision,$json,$now]);
        if($own)$pdo->commit();return ['deck_id'=>$id,'attributes'=>$attributes,'primary'=>$attributes[0]??null,'revision'=>$revision,'updated_at'=>$now];
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
