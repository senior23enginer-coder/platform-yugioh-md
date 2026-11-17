<?php
declare(strict_types=1);

/** Capture sporting identity when the roster is fixed, never infer it on a later read. */
function ygo_participant_identities(PDO $p,array $ids,string $format): array {
    if(!$ids)return [];
    $q=$p->prepare('SELECT id,team_id FROM sx_ygo_decks WHERE active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$out=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $d)$out[(int)$d['id']]=['deck_id'=>(int)$d['id'],'team_id'=>(int)($d['team_id']??0),'format_id'=>$format,'quality'=>'CONFIRMED_AT_ROSTER_FREEZE'];
    if(count($out)!==count($ids))throw new InvalidArgumentException('No se pudo fijar la identidad de todos los participantes.');
    return $out;
}
