<?php
declare(strict_types=1);

/** Expose actual seat subresults, never insert synthetic matches into sporting tables. */
function ygo_stats_team_source(array $db,array $base,?array $record): array {
    $fail=static fn(string $why):array=>[array_replace($base,['team_a_id'=>0,'team_b_id'=>0,'identity_quality'=>'PENDIENTE: '.$why])];
    if(!$record||($record['version']??'')!=='STATION_X_TEAMS_V1')return $fail('falta resultado colectivo verificado');
    $kind=$record['kind']??'';$size=$kind==='tag'?2:($kind==='team'?3:0);if(!$size)return $fail('modalidad colectiva desconocida');
    if(($record['aggregate']['winner']??null)!==$db['result']||($record['aggregate']['score']??null)!==$db['score'])return $fail('resultado colectivo no coincide con sus puestos');
    $left=$record['lineup_a']['decks']??[];$right=$record['lineup_b']['decks']??[];$identities=$record['identities']??[];
    if(count($left)!==$size||count($right)!==$size)return $fail('alineacion historica incompleta');
    $people=[];$format=null;$decks=[];
    foreach(['a'=>$left,'b'=>$right] as $side=>$ids)foreach($ids as $i=>$deck){
        $field=($side==='a'?['a','c','e']:['b','d','f'])[$i];$p=$identities[$deck]??null;
        if(!$p||($p['quality']??'')!=='CONFIRMED_AT_ROSTER_FREEZE'||empty($p['team_id'])||(int)$p['deck_id']!==(int)$deck||(int)$db['deck_'.$field.'_id']!==(int)$deck||isset($decks[$deck]))return $fail('identidad historica de integrante no confirmada');
        if($format!==null&&$format!==$p['format_id'])return $fail('formatos mezclados en alineaciones');$format=$p['format_id'];$people[$side][]=$p;$decks[$deck]=true;
    }
    $evaluations=$record['evaluations']??[];$sequences=$record['sequences']??[];
    if(count($evaluations)!==($kind==='tag'?1:3)||count($sequences)!==count($evaluations))return $fail('faltan resultados por puesto');
    $wins=['A'=>0,'B'=>0,'E'=>0];$duelsBySeat=[];
    foreach($evaluations as $i=>$e){
        if(($e['status']??'')!=='FINALIZADA'||!isset($wins[$e['winner']??'']))return $fail('puesto sin resultado valido');$wins[$e['winner']]++;
        $sequence=$sequences[$i];while($sequence&&in_array(end($sequence),['',null],true))array_pop($sequence);if(!$sequence||count($sequence)>5)return $fail('secuencia individual incompleta');
        $duels=[];foreach($sequence as $j=>$value){if(!in_array($value,['A','B','E'],true))return $fail('hueco en secuencia individual');$duels[]=['position'=>$j+1,'winner'=>$value,'completed_at'=>$record['game_details'][$i][$j]['completed_at']??null];}$duelsBySeat[]=$duels;
    }
    $actual=$kind==='tag'?$evaluations[0]['winner']:($wins['A']>=2?'A':($wins['B']>=2?'B':'E'));
    if($actual!==$db['result'])return $fail('ganador colectivo contradice los puestos');
    $base=array_replace($base,['format_id'=>$format,'confirmed'=>$base['confirmed'],'played'=>true,'source_version'=>hash('sha256',json_encode([$base['source_version'],$record]))]);
    if($kind==='tag')return [array_replace($base,['kind'=>'tag','source_key'=>'match:'.$db['id'].':tag','participants_a'=>$people['a'],'participants_b'=>$people['b'],'duels'=>$duelsBySeat[0],'duels_complete'=>false])];
    $out=[];foreach($evaluations as $i=>$e)$out[]=array_replace($base,['kind'=>'team_seat','source_key'=>'match:'.$db['id'].':seat:'.($i+1),'seat'=>$i+1,'result'=>$e['winner'],'deck_a_id'=>$left[$i],'deck_b_id'=>$right[$i],'team_a_id'=>$people['a'][$i]['team_id'],'team_b_id'=>$people['b'][$i]['team_id'],'duels'=>$duelsBySeat[$i],'duels_complete'=>true,'dice_winner'=>null,'identity_quality'=>'CONFIRMADA EN ALINEACION FIJADA']);
    return $out;
}

function ygo_stats_ffa_source(PDO $p): array {
    if(!ygo_table_exists($p,'sx_ygo_ffa_matches'))return [];
    $rows=$p->query("SELECT f.*,c.season_id,c.type event_type,c.event_ruleset FROM sx_ygo_ffa_matches f JOIN sx_ygo_competitions c ON c.id=f.competition_id ORDER BY f.id")->fetchAll(PDO::FETCH_ASSOC);$out=[];
    foreach($rows as $row){$s=json_decode($row['state_json'],true);$players=$s['players']??[];$format=$players[0]['format_id']??null;
        $alive=array_values(array_filter($players,static fn($p)=>empty($p['eliminated'])));
        $confirmed=$row['status']==='FINALIZADA'&&($s['status']??'')==='FINALIZADA'&&count($alive)===1&&(int)$alive[0]['deck_id']===(int)$row['winner_deck_id']&&(int)($s['winner']??0)===(int)$row['winner_deck_id'];
        $out[]=['match_id'=>(int)$row['id'],'event_id'=>(int)$row['competition_id'],'season_id'=>(int)$row['season_id'],'format_id'=>$format,'played_at'=>$s['completed_at']??($row['scheduled_on'].' '.$row['starts_at']),'played_at_source'=>!empty($s['completed_at'])?'completed_at':'scheduled_legacy','winner'=>(int)($row['winner_deck_id']??0),'players'=>$players,'confirmed'=>$confirmed,'competitive'=>$row['event_type']!=='friendly','source_version'=>$row['revision'].':'.hash('sha256',$row['state_json'])];
    }return $out;
}
