<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/autoload.php';
require_once __DIR__.'/event_qualification.php';
require_once __DIR__.'/event_catalog_rules.php';

function ygo_admission_required(array $c): bool
{
    return ygo_qualification_mode((string)($c['advancement_rule']??''))!==null || ($c['participant_source']??'')==='PREVIOUS_EVENT' || in_array($c['execution_model']??'',['top_cut','playoffs'],true);
}

function ygo_admission_load(PDO $pdo,array $c,bool $lock=false): ?array
{
    if(empty($c['id']) || empty($c['season_id']))return null;
    $q=$pdo->prepare('SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key=?'.($lock?' FOR UPDATE':''));
    $q->execute([$c['season_id'],'event_admission_'.$c['id']]);$v=$q->fetchColumn();
    return $v===false?null:json_decode((string)$v,true,512,JSON_THROW_ON_ERROR);
}

function ygo_admission_store(PDO $pdo,array $c,array $a): void
{
    $json=json_encode($a,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if(strlen($json)>65535)throw new InvalidArgumentException('La admision excede la capacidad permitida.');
    $pdo->prepare('INSERT INTO sx_ygo_settings(season_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$c['season_id'],'event_admission_'.$c['id'],$json]);
}

/** Resolve deck identity independently of memberships in the event being checked. */
function ygo_admission_deck_format(PDO $pdo,array $ids): string
{
    if(!$ids)throw new InvalidArgumentException('La fuente no tiene decks elegibles.');
    $aliases=[];foreach($pdo->query("SELECT code,label FROM sx_ygo_rulesets WHERE active=1 AND code<>'custom'")->fetchAll(PDO::FETCH_ASSOC) as $r){$aliases[strtolower($r['code'])]=$r['code'];$aliases[strtolower($r['label'])]=$r['code'];}
    $q=$pdo->prepare('SELECT d.id,d.style,c.event_ruleset FROM sx_ygo_decks d LEFT JOIN sx_ygo_divisions v ON v.id=d.division_id LEFT JOIN sx_ygo_competitions c ON c.id=v.league_competition_id WHERE d.id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$formats=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$style=$aliases[strtolower(trim((string)$r['style']))]??null;$owned=$r['event_ruleset']?:null;if($owned==='custom')$owned=null;if($style && $owned && $style!==$owned)throw new InvalidArgumentException('El formato del deck contradice el de su liga. Corrige su registro antes de clasificarlo.');$format=$owned?:$style;if(!$format)throw new InvalidArgumentException('No se puede confirmar el formato de un deck de la fuente.');$formats[$format]=true;}
    if(count($formats)!==1)throw new InvalidArgumentException('La fuente mezcla decks de distintos formatos.');
    return (string)array_key_first($formats);
}

/** Largest remainders, deterministic by configured source order. No fractional seats. */
function ygo_admission_quotas(array $rows,int $capacity): array
{
    return \StationX\Yugioh\Domain\Competitions\AdmissionQuotaPolicy::quotas($rows, $capacity);
}

/** A source must actually be finished; a status label alone is not sufficient. */
function ygo_admission_source(PDO $pdo,int $id,int $division=0,bool $lock=false): array
{
    $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1'.($lock?' FOR UPDATE':''));$q->execute([$id]);$c=$q->fetch(PDO::FETCH_ASSOC);
    if(!$c || !in_array(ygo_normalize_status_key((string)$c['status']),['completed','closed','archived'],true))throw new InvalidArgumentException('La fuente debe ser un evento finalizado.');
    if((int)($c['participants_per_side']??1)>1 || ($c['match_format']??'1v1')!=='1v1')throw new InvalidArgumentException('La admision de equipos requiere alineaciones y resultados colectivos verificados; esta admision es de decks individuales.');
    $q=$pdo->prepare('SELECT m.id,m.division_id,m.deck_a_id,m.deck_b_id,m.phase,m.round_number,m.status,r.result,r.score FROM sx_ygo_matches m LEFT JOIN sx_ygo_match_results r ON r.match_id=m.id WHERE m.competition_id=? ORDER BY m.id');$q->execute([$id]);$matches=$q->fetchAll(PDO::FETCH_ASSOC);
    if(!$matches)throw new InvalidArgumentException('La fuente no tiene combates con resultados verificables.');
    foreach($matches as $m)if($m['status']!=='FINALIZADA' || !in_array($m['result'],['A','B','E'],true))throw new InvalidArgumentException('La fuente tiene resultados pendientes o invalidos.');
    $wf=ygo_workflow_load($pdo,$c);if($wf!==null && empty($wf['complete']))throw new InvalidArgumentException('La fuente aun tiene fases pendientes.');
    $grouped=($wf['policy']['model']??'')==='groups';
    if(!$division && $c['type']==='league' && count(ygo_league_divisions($pdo,$id))>1)throw new InvalidArgumentException('Selecciona una division concreta de la liga de origen.');
    if($wf!==null)foreach($wf['history'] as $m){$r=ygo_workflow_db_result($pdo,(int)$m['match_id']);if($r['winner']!==$m['winner'] || $r['game_difference']!==$m['game_difference'])throw new InvalidArgumentException('Un resultado de la fuente difiere del cierre registrado.');}
    $ranking=ygo_competition_standings($pdo,$id);
    if($division){
        $q=$pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE id=? AND league_competition_id=? AND season_id=?');$q->execute([$division,$id,$c['season_id']]);if(!$q->fetchColumn())throw new InvalidArgumentException('La division no pertenece a la liga de origen.');
        $eligible=[];foreach($matches as $m)if((int)$m['division_id']===$division){$eligible[]=(int)$m['deck_a_id'];$eligible[]=(int)$m['deck_b_id'];}
        $ranking=array_values(array_filter($ranking,static fn($r)=>in_array((int)$r['id'],$eligible,true)));
    }
    $q=$pdo->prepare('SELECT champion_deck_id,runner_up_deck_id FROM sx_ygo_honors WHERE competition_id=? ORDER BY id');$q->execute([$id]);$honors=$q->fetchAll(PDO::FETCH_ASSOC);
    $champion=0;$runner=0;
    if($division || ($c['type']==='league' && count(ygo_league_divisions($pdo,$id))<=1))$champion=(int)($ranking[0]['id']??0);
    elseif(count($honors)===1)$champion=(int)$honors[0]['champion_deck_id'];
    if($wf!==null && !empty($wf['complete'])){$champion=(int)($wf['champion']??0);$runner=(int)($wf['runner_up']??0);}
    if($division || ($c['type']==='league' && count(ygo_league_divisions($pdo,$id))<=1))$runner=(int)($ranking[1]['id']??0);
    elseif(count($honors)===1 && !$runner)$runner=(int)$honors[0]['runner_up_deck_id'];
    if($wf===null && $champion && in_array($c['execution_model'],['single_elimination','bracket','top_cut','playoffs'],true)){
        $last=$pdo->prepare('SELECT m.deck_a_id,m.deck_b_id,r.result FROM sx_ygo_matches m JOIN sx_ygo_match_results r ON r.match_id=m.id WHERE m.competition_id=? AND m.round_number=(SELECT MAX(round_number) FROM sx_ygo_matches WHERE competition_id=?)');$last->execute([$id,$id]);$finals=$last->fetchAll(PDO::FETCH_ASSOC);
        if(count($finals)!==1 || !in_array($finals[0]['result'],['A','B'],true) || (int)$finals[0][$finals[0]['result']==='A'?'deck_a_id':'deck_b_id']!==$champion)throw new InvalidArgumentException('El titulo de la fuente no coincide con una final resuelta.');
    }
    if($runner && ($runner===$champion || !in_array($runner,array_map('intval',array_column($ranking,'id')),true)))throw new InvalidArgumentException('El subcampeon no coincide con la clasificacion de la fuente.');
    if(!empty($finals) && count($finals)===1 && $runner && (int)$finals[0][$finals[0]['result']==='A'?'deck_b_id':'deck_a_id']!==$runner)throw new InvalidArgumentException('El subcampeon no coincide con la final resuelta.');
    if($champion && !in_array($champion,array_column($ranking,'id')))throw new InvalidArgumentException('El campeon no coincide con los participantes clasificados.');
    if($champion && count($honors)===1){$runner=(int)$honors[0]['runner_up_deck_id'];$order=array_flip(array_column($ranking,'id'));$order[$champion]=-2;if($runner)$order[$runner]=-1;usort($ranking,static fn($a,$b)=>$order[$a['id']]<=>$order[$b['id']]);}
    if(in_array($c['execution_model'],['single_elimination','bracket','top_cut','playoffs','double_elimination','groups_knockout','swiss_top_cut','season_playoffs'],true)){
        $finish=[];$tie=array_flip(array_column($ranking,'id'));
        foreach($matches as $m)if(in_array($m['phase'],['cup','final','world','playoff','top_cut','double_elimination'],true) && in_array($m['result'],['A','B'],true))$finish[(int)$m[$m['result']==='A'?'deck_b_id':'deck_a_id']]=(int)$m['round_number'];
        if($champion)$finish[$champion]=PHP_INT_MAX;
        usort($ranking,static fn($a,$b)=>($finish[$b['id']]??0)<=>($finish[$a['id']]??0) ?: $tie[$a['id']]<=>$tie[$b['id']]);
    }
    if(!$ranking)throw new InvalidArgumentException('La fuente no tiene una clasificacion verificable.');
    $deckFormat=ygo_admission_deck_format($pdo,array_map('intval',array_column($ranking,'id')));
    if($c['event_ruleset']!=='custom' && $deckFormat!==$c['event_ruleset'])throw new InvalidArgumentException('El formato de los decks no coincide con el declarado por la fuente.');
    $q=$pdo->prepare('SELECT deck_id,sort_order FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 ORDER BY sort_order,id');$q->execute([$id]);$roster=$q->fetchAll(PDO::FETCH_ASSOC);
    $rankSignature=array_map(static fn($r)=>array_intersect_key($r,array_flip(['id','pts','pj','pg','pe','pp','cg','cp','dp','seed_rank','buchholz','group','group_position'])),$ranking);
    return ['id'=>$id,'division_id'=>$division,'name'=>$c['name'],'season_id'=>(int)$c['season_id'],'format'=>$c['event_ruleset'],'deck_format'=>$deckFormat,'champion_id'=>$champion,'runner_up_id'=>$runner,'grouped'=>$grouped,'ranking'=>$ranking,'signature'=>hash('sha256',json_encode([$c['event_ruleset'],$deckFormat,$matches,$honors,$roster,$rankSignature],JSON_UNESCAPED_UNICODE))];
}

function ygo_admission_resolve(PDO $pdo,array $c,array $a,bool $lock=false): array
{
    if(($a['version']??'')!=='STATION_X_ADMISSION_V1')throw new InvalidArgumentException('Version de admision desconocida.');
    $rows=$a['sources']??[];
    $expectedMode=ygo_qualification_mode((string)($c['advancement_rule']??''));
    if($expectedMode!==null)foreach($rows as $row)if(($row['mode']??'')!==$expectedMode)throw new InvalidArgumentException('Las fuentes deben usar el criterio de avance guardado en la ficha.');
    $quotas=ygo_admission_quotas($rows,(int)($a['capacity']??0));$ids=[];$origins=[];$signatures=[];$seen=[];
    $actualFormat=null;
    foreach($rows as $i=>$row){
        $sourceId=(int)($row['source_id']??0);$division=(int)($row['division_id']??0);$key=$sourceId.':'.$division;
        if(filter_var($row['source_id']??0,FILTER_VALIDATE_INT)===false || $sourceId<1 || filter_var($row['division_id']??0,FILTER_VALIDATE_INT)===false || $division<0)throw new InvalidArgumentException('Identificador de fuente o division invalido.');
        if($sourceId===(int)$c['id'] || isset($seen[$key]))throw new InvalidArgumentException('No se puede repetir una fuente ni usar el propio evento.');$seen[$key]=true;
        $source=ygo_admission_source($pdo,$sourceId,$division,$lock);
        if($source['format']!==$c['event_ruleset'])throw new InvalidArgumentException('Todas las fuentes deben tener el mismo formato que el evento de destino.');
        if($actualFormat!==null && $actualFormat!==$source['deck_format'])throw new InvalidArgumentException('Los decks de las fuentes son de formatos diferentes.');$actualFormat=$source['deck_format'];
        if(in_array(ygo_template_base_code($c['prompt_template']??''),['super_cup','grand_final'],true) && $row['mode']!=='winner')throw new InvalidArgumentException('Esta plantilla admite campeones verificados: usa Campeon en cada fuente.');
        $candidates=ygo_qualification_candidates($source,$row);
        if($row['mode']==='group_position' && count($candidates)!==$quotas[$i])throw new InvalidArgumentException('Los cupos de Posicion de grupo deben incluir esa posicion de todos los grupos, sin recortes arbitrarios.');
        $selected=array_map('intval',array_column(array_slice($candidates,0,$quotas[$i]),'id'));
        if(count($selected)!==$quotas[$i])throw new InvalidArgumentException('La fuente '.$source['name'].' no tiene suficientes participantes para sus cupos.');
        foreach($selected as $id){if(in_array($id,$ids,true))throw new InvalidArgumentException('Un deck clasifica por mas de una fuente. No se asignaran sustitutos automaticamente.');$ids[]=$id;$origins[]=['deck_id'=>$id,'source_id'=>$sourceId,'division_id'=>$division,'mode'=>$row['mode']];}
        $signatures[$key]=$source['signature'];
    }
    if(($c['execution_model']??'')==='series' && count($ids)!==2)throw new InvalidArgumentException('La serie necesita exactamente dos clasificados.');
    ygo_validate_format_roster($pdo,(string)$c['event_ruleset'],$ids);
    return ['ids'=>$ids,'origins'=>$origins,'quotas'=>$quotas,'signatures'=>$signatures];
}

function ygo_admission_assert_no_cycle(PDO $pdo,int $target,array $sources): void
{
    $edges=[];foreach($pdo->query('SELECT competition_id,depends_on_competition_id FROM sx_ygo_competition_dependencies WHERE active=1')->fetchAll(PDO::FETCH_ASSOC) as $r)$edges[(int)$r['competition_id']][]=(int)$r['depends_on_competition_id'];
    $edges[$target]=array_map(static fn($r)=>(int)$r['source_id'],$sources);$stack=$edges[$target];$visited=[];
    while($stack){$id=array_pop($stack);if($id===$target)throw new InvalidArgumentException('Las fuentes forman una dependencia circular.');if(isset($visited[$id]))continue;$visited[$id]=true;foreach($edges[$id]??[] as $next)$stack[]=$next;}
}

/** Explicit action: save sources and load exactly their qualified decks, atomically. */
function ygo_admission_save(PDO $pdo,int $target,array $rows,int $capacity): array
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_admission_save_serialized($pdo,$target,$rows,$capacity));
}

function ygo_admission_save_serialized(PDO $pdo,int $target,array $rows,int $capacity): array
{
    return (new \StationX\Yugioh\Application\Competitions\SaveEventAdmission(
        new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionWriteStore($pdo),
        new \StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary($pdo)
    ))->execute($target, $rows, $capacity);
}

/** Called before any schedule mutation; also detects corrections to a frozen source. */
function ygo_admission_require(PDO $pdo,array $c,array $ids,bool $freeze=true): array
{
    $a=ygo_admission_load($pdo,$c,true);
    if(!$a){if(ygo_admission_required($c))throw new InvalidArgumentException('Configura Fuentes y clasificados en la ficha y carga sus plazas antes de programar.');return $ids;}
    ygo_admission_assert_no_cycle($pdo,(int)$c['id'],$a['sources']);
    $resolved=ygo_admission_resolve($pdo,$c,$a,true);
    if(!empty($a['frozen']) && $a['selection']!==$resolved)throw new InvalidArgumentException('Una fuente cambio despues de programar; se conserva el evento sin recalcular sus plazas.');
    if(empty($a['frozen']) && $a['selection']!==$resolved)throw new InvalidArgumentException('Una fuente cambio desde la carga. Guarda Fuentes y clasificados para revisar y cargar nuevamente sus plazas.');
    $posted=array_map('intval',$ids);$expected=$resolved['ids'];sort($posted);sort($expected);
    if($posted!==$expected)throw new InvalidArgumentException('Los participantes deben coincidir exactamente con los clasificados de las fuentes configuradas.');
    if($freeze){$a['frozen']=true;$a['selection']=$resolved;ygo_admission_store($pdo,$c,$a);}
    return $resolved['ids'];
}

function ygo_admission_view(PDO $pdo,array $c): array
{
    $a=ygo_admission_load($pdo,$c);if(!$a)return ['config'=>null,'ok'=>!ygo_admission_required($c),'message'=>ygo_admission_required($c)?'Falta configurar fuentes y clasificados.':'Inscripcion directa.'];
    try{$selection=ygo_admission_resolve($pdo,$c,$a);if($selection!==$a['selection'])throw new InvalidArgumentException(!empty($a['frozen'])?'Cambio una fuente ya utilizada.':'La fuente cambio: revisa y guarda Fuentes y clasificados para cargar sus plazas nuevamente.');return ['config'=>$a,'ok'=>true,'message'=>count($selection['ids']).' clasificados verificados.','selection'=>$selection];}
    catch(InvalidArgumentException $e){return ['config'=>$a,'ok'=>false,'message'=>$e->getMessage()];}
}

function ygo_admission_sources(PDO $pdo): array
{
    $out=[];foreach($pdo->query("SELECT c.*,s.name season_name FROM sx_ygo_competitions c JOIN sx_ygo_seasons s ON s.id=c.season_id WHERE c.active=1 AND c.status IN ('completed','closed','archived') ORDER BY c.season_id,c.name,c.id")->fetchAll(PDO::FETCH_ASSOC) as $c){
        $divisions=$c['type']==='league'?ygo_league_divisions($pdo,(int)$c['id']):[];
        $choices=count($divisions)>1?$divisions:[['id'=>0,'name'=>'']];
        foreach($choices as $d)try{$r=ygo_admission_source($pdo,(int)$c['id'],(int)$d['id']);$r['label']=$d['id']?$d['name']:$c['name'];$r['season_name']=$c['season_name'];unset($r['signature']);$out[]=$r;}catch(InvalidArgumentException $e){/* An incomplete source is not offered as an eligible source. */}
    }return $out;
}

function ygo_admission_assert_result_editable(PDO $pdo,int $matchId): void
{
    $q=$pdo->prepare('SELECT competition_id FROM sx_ygo_matches WHERE id=?');$q->execute([$matchId]);$source=(int)$q->fetchColumn();if(!$source)return;
    $lock=$pdo->prepare('SELECT id FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$lock->execute([$source]);
    $q=$pdo->query("SELECT s.setting_value FROM sx_ygo_settings s JOIN sx_ygo_competitions c ON s.season_id=c.season_id AND s.setting_key=CONCAT('event_admission_',c.id) WHERE c.active=1 FOR UPDATE");
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $v){$a=json_decode((string)$v,true);if(!empty($a['frozen']) && in_array($source,array_map('intval',array_column($a['sources'],'source_id')),true))throw new InvalidArgumentException('Este resultado ya clasifico decks a otro evento programado. Primero se requiere revertir el evento dependiente.');}
}

/** Reschedule only the newly created stage, never any other event or earlier round. */
function ygo_admission_schedule_stage(PDO $pdo,array $c,string $start,string $pattern,int $perDay,?string $phase=null,?int $round=null): void
{
    if(ygo_admission_load($pdo,$c)===null)return;
    $q=$pdo->prepare("SELECT id FROM sx_ygo_matches WHERE competition_id=? AND status='PENDIENTE'".($phase!==null?' AND phase=? AND round_number=?':'').' ORDER BY display_order,id');$q->execute($phase!==null?[$c['id'],$phase,$round]:[$c['id']]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    if(!$ids)return;
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $pdo->prepare("UPDATE sx_ygo_matches SET scheduled_on=NULL,starts_at=NULL WHERE id IN ($marks)")->execute($ids);
    $q=$pdo->prepare('SELECT MAX(scheduled_on) FROM sx_ygo_matches WHERE competition_id=?');$q->execute([$c['id']]);$last=$q->fetchColumn();
    $base=max($start,date('Y-m-d'),$last?ygo_next_competition_match_date(new DateTimeImmutable($last),$pattern,1,1):'');
    foreach($ids as $i=>$mid){$date=ygo_next_competition_match_date(new DateTimeImmutable($base),$pattern,$i,$perDay);[$date,$time]=ygo_workflow_slot($pdo,$c,$mid,$date,$pattern,$perDay);$pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?,starts_at=? WHERE id=?')->execute([$date,$time,$mid]);}
    ygo_season_validate_competition_schedule($pdo,(int)$c['id']);
}
