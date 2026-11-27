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
    return (new \StationX\Yugioh\Application\Competitions\ResolveAdmissionDeckFormat(new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionSourceReader($pdo)))->execute($ids);
}

/** Largest remainders, deterministic by configured source order. No fractional seats. */
function ygo_admission_quotas(array $rows,int $capacity): array
{
    return \StationX\Yugioh\Domain\Competitions\AdmissionQuotaPolicy::quotas($rows, $capacity);
}

/** A source must actually be finished; a status label alone is not sufficient. */
function ygo_admission_source(PDO $pdo,int $id,int $division=0,bool $lock=false): array
{
    return \StationX\Yugioh\Infrastructure\Composition\AdmissionReadModule::source($pdo)->execute($id, $division, $lock);
}

function ygo_admission_resolve(PDO $pdo,array $c,array $a,bool $lock=false): array
{
    return \StationX\Yugioh\Infrastructure\Composition\AdmissionReadModule::resolve($pdo)->execute($c, $a, $lock);
}

function ygo_admission_assert_no_cycle(PDO $pdo,int $target,array $sources): void
{
    \StationX\Yugioh\Domain\Competitions\AdmissionDependencyPolicy::assertNoCycle($target, $sources, (new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionSourceReader($pdo))->edges());
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
