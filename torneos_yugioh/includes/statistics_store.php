<?php
declare(strict_types=1);
require_once __DIR__.'/statistics_v8.php';
require_once __DIR__.'/statistics_multiplayer_source.php';

function ygo_stats_canonical(mixed $value): string {
    $normalize=static function(mixed $v) use (&$normalize): mixed {if(!is_array($v))return $v;if(!array_is_list($v))ksort($v);foreach($v as &$item)$item=$normalize($item);unset($item);return $v;};
    return json_encode($normalize($value),JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
}

function ygo_stats_schema(PDO $p): void {
    if($p->inTransaction())throw new LogicException('La instalacion estadistica debe ejecutarse fuera de una transaccion deportiva.');
    $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_revisions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, source_hash CHAR(64) NOT NULL, algorithm_version VARCHAR(60) NOT NULL,
        payload LONGTEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_stats_source (source_hash,algorithm_version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ygo_add_column($p,'sx_ygo_statistics_revisions','previous_revision_id','BIGINT NULL');
    ygo_add_column($p,'sx_ygo_statistics_revisions','change_reason','VARCHAR(255) NULL');
    $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_publications (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(20) NOT NULL, scope_key VARCHAR(160) NOT NULL,
        cut_at DATETIME NOT NULL, source_hash CHAR(64) NOT NULL, algorithm_version VARCHAR(60) NOT NULL,
        replaces_id BIGINT NULL, reason VARCHAR(255) NOT NULL, payload LONGTEXT NOT NULL,
        published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_stats_publication (kind,scope_key,cut_at,source_hash,algorithm_version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if(!ygo_column_exists($p,'sx_ygo_statistics_publications','revision_number')){
        $p->exec('ALTER TABLE sx_ygo_statistics_publications ADD COLUMN revision_number INT NULL');
        $p->exec('ALTER TABLE sx_ygo_statistics_publications DROP INDEX ux_stats_publication, ADD UNIQUE KEY ux_stats_publication(kind,scope_key,cut_at,revision_number)');
    }
    $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_policy (
        scope_key VARCHAR(160) PRIMARY KEY, version VARCHAR(60) NOT NULL, payload LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_current (id INT PRIMARY KEY, revision_id BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_medals (event_id INT PRIMARY KEY, bonus_points INT NOT NULL, bonus_version VARCHAR(60) NOT NULL, definition_json LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Stable source facts. Fallback names, winners, tier or historical dates are forbidden. */
function ygo_stats_source(PDO $p): array {
    $events=$p->query('SELECT * FROM sx_ygo_competitions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $fixed=[];if(ygo_table_exists($p,'sx_ygo_statistics_medals'))foreach($p->query('SELECT * FROM sx_ygo_statistics_medals')->fetchAll(PDO::FETCH_ASSOC) as $f)$fixed[$f['event_id']]=$f;
    foreach($events as &$e){$e['format_id']=$e['event_ruleset']?:null;if(isset($fixed[$e['id']])){$e['fixed_bonus']=(int)$fixed[$e['id']]['bonus_points'];$e['bonus_version']=$fixed[$e['id']]['bonus_version'];}}unset($e);
    $rows=$p->query("SELECT m.*,r.result,r.score,r.d1,r.d2,r.d3,r.d4,r.d5,r.duel_details,r.dice_winner,
        r.updated_at result_updated_at,c.type event_type,c.event_ruleset,c.ranking_impact,c.season_id source_season,
        da.team_id current_team_a,db.team_id current_team_b
        FROM sx_ygo_matches m LEFT JOIN sx_ygo_match_results r ON r.match_id=m.id
        LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id
        LEFT JOIN sx_ygo_decks da ON da.id=m.deck_a_id LEFT JOIN sx_ygo_decks db ON db.id=m.deck_b_id ORDER BY m.id")->fetchAll(PDO::FETCH_ASSOC);
    $snapshots=$p->query('SELECT match_id,side,deck_id,team_id,ruleset_code,data_origin,snapshot_status FROM sx_ygo_match_snapshots ORDER BY match_id,duel_number')->fetchAll(PDO::FETCH_ASSOC);
    $identities=[];foreach($snapshots as $s)if(in_array($s['data_origin'],['captured','live','verified','retroactive_verified'],true)&&in_array($s['snapshot_status'],['exact','confirmed','verified','retroactive_verified'],true))$identities[$s['match_id']][$s['side']]=$s;
    $collective=[];foreach($p->query("SELECT season_id,setting_key,setting_value FROM sx_ygo_settings WHERE setting_key LIKE 'event_team_result_%'")->fetchAll(PDO::FETCH_ASSOC) as $record)$collective[$record['season_id'].':'.substr($record['setting_key'],18)]=json_decode($record['setting_value'],true);
    $matches=[];
    foreach($rows as $r){
        $state=strtolower(str_replace(' ','_',(string)$r['status']));$finished=in_array($state,['finalizada','finalizado','completed','finished'],true);
        $details=json_decode($r['duel_details']??'[]',true)?:[];$duels=[];$complete=true;
        foreach(range(1,5) as $i)if(in_array($r['d'.$i]??null,['A','B','E'],true)){
            $detail=array_values(array_filter($details,fn($d)=>is_array($d)&&(int)($d['duel']??0)===$i))[0]??[];
            $duels[]=array_merge($detail,['position'=>$i,'winner'=>$r['d'.$i]]);
        }
        if(!$duels||array_column($duels,'position')!==range(1,count($duels)))$complete=false;
        $winsA=count(array_filter($duels,fn($d)=>$d['winner']==='A'));$winsB=count(array_filter($duels,fn($d)=>$d['winner']==='B'));
        if(!preg_match('/^(\d+)-(\d+)$/',(string)$r['score'],$score)||$winsA!==(int)$score[1]||$winsB!==(int)$score[2])$complete=false;
        $a=$identities[$r['id']]['A']??null;$b=$identities[$r['id']]['B']??null;
        if($a && (int)$a['deck_id']!==(int)$r['deck_a_id'])$a=null;
        if($b && (int)$b['deck_id']!==(int)$r['deck_b_id'])$b=null;
        // Collective sides need a confirmed single competitive identity supplied by their owner.
        // A captain's deck is never silently credited with all teammates' results.
        if((int)($r['participants_per_side']??1)>1){$a=null;$b=null;}
        $originFormat=$a&&$b&&!empty($a['ruleset_code'])&&$a['ruleset_code']===$b['ruleset_code']?$a['ruleset_code']:null;
        $base=['match_id'=>(int)$r['id'],'event_id'=>(int)($r['competition_id']??0),'season_id'=>(int)($r['source_season']??0),
            'format_id'=>$originFormat,'ranking_impact'=>$r['ranking_impact'],'division_id'=>$r['division_id'],'phase'=>$r['phase'],'event_type'=>$r['event_type'],'judge'=>$r['judge'],'venue'=>$r['venue'],
            'confirmed'=>$finished&&in_array($r['result'],['A','B','E'],true),'result'=>$r['result'],
            'competitive'=>$r['ranking_impact']!==null&&$r['ranking_impact']!=='NONE'&&$r['event_type']!=='friendly',
            'friendly'=>$r['event_type']==='friendly','played_at'=>$r['completed_at']??(!empty($r['scheduled_on'])?$r['scheduled_on'].' '.($r['starts_at']?:'00:00:00'):null),
            'played_at_source'=>!empty($r['completed_at'])?'completed_at':'scheduled_legacy',
            'deck_a_id'=>(int)$r['deck_a_id'],'deck_b_id'=>(int)$r['deck_b_id'],
            'team_a_id'=>$a?(int)$a['team_id']:0,'team_b_id'=>$b?(int)$b['team_id']:0,
            'identity_quality'=>$a&&$b?'CONFIRMADA':((int)($r['participants_per_side']??1)>1?'PENDIENTE: resultado colectivo sin identidad equipo/deck unica confirmada':'PENDIENTE: falta snapshot historico confirmado'),
            'duels'=>$duels,'duels_complete'=>$complete,'played'=>count($duels)>0,'dice_winner'=>$r['dice_winner'],
            'source_version'=>(string)$r['result_updated_at'].':'.hash('sha256',json_encode([$r['result'],$duels]))];
        if((int)($r['participants_per_side']??1)>1)array_push($matches,...ygo_stats_team_source($r,$base,$collective[$r['source_season'].':'.$r['id']]??null));else $matches[]=$base;
    }
    $honors=$p->query('SELECT h.*,c.status event_status FROM sx_ygo_honors h LEFT JOIN sx_ygo_competitions c ON c.id=h.competition_id ORDER BY h.id')->fetchAll(PDO::FETCH_ASSOC);
    foreach($honors as &$h){$h['confirmed']=in_array(strtolower($h['event_status']??''),['completed','finalizado','finalizada'],true);$h['effective_at']=$h['created_at'];}unset($h);
    $seasons=$p->query('SELECT * FROM sx_ygo_seasons ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach($events as &$e){$s=array_values(array_filter($seasons,fn($s)=>(int)$s['id']===(int)$e['season_id']))[0]??[];$e['season_status']=$s['status']??null;}unset($e);
    return ['matches'=>$matches,'events'=>$events,'honors'=>$honors,'seasons'=>$seasons,'ffa_matches'=>ygo_stats_ffa_source($p)];
}

function ygo_stats_sync(PDO $p): int {
    if(!ygo_table_exists($p,'sx_ygo_statistics_revisions'))return 0;
    // A sporting transaction never performs DDL or waits on the statistics writer.
    // The next read refreshes the complete immutable derivative atomically.
    if($p->inTransaction())return 0;
    if((int)$p->query("SELECT GET_LOCK('station_x_statistics_v8',10)")->fetchColumn()!==1)throw new RuntimeException('Calculo estadistico ocupado; reintenta.');
    try{
    $p->beginTransaction();
    $source=ygo_stats_source($p);$policy=ygo_stats_policy();
    $q=$p->query("SELECT payload FROM sx_ygo_statistics_policy WHERE scope_key='global'");$custom=$q->fetchColumn();if($custom)$policy=array_replace($policy,json_decode($custom,true));$policy['global_cadence']='per_result';$policy['minimum_matches']=2;$policy['minimum_opponents']=1;$policy['version']=YGO_STATS_VERSION;
    foreach($source['events'] as &$e){if($e['type']==='friendly')continue;$e['fixed_bonus']??=$policy['medal_bonus'][$e['type']]??20;$e['bonus_version']??=$policy['version'];}unset($e);
    $cuts=ygo_stats_publications($p,false);$tiers=[];foreach($cuts as $cut)if($cut['kind']==='metagame')foreach($cut['data']['metagame']??[] as $t)$tiers[]=array_merge($t,['published_at'=>$cut['published_at'],'status'=>'PUBLICADO']);
    $hash=hash('sha256',ygo_stats_canonical([$source,$policy,array_column($cuts,'id'),date('Y-m-d')]));
    $q=$p->prepare('SELECT id FROM sx_ygo_statistics_revisions WHERE source_hash=? AND algorithm_version=?');$q->execute([$hash,YGO_STATS_VERSION]);$revision=$q->fetchColumn();
    if($revision){$p->prepare('INSERT INTO sx_ygo_statistics_current(id,revision_id) VALUES(1,?) ON DUPLICATE KEY UPDATE revision_id=VALUES(revision_id)')->execute([$revision]);$p->commit();return 0;}
    $data=ygo_stats_calculate($source['matches'],$source['events'],$source['honors'],$policy,null,$tiers,$source['ffa_matches']);$data['source_hash']=$hash;
    $previousPayload=$p->query('SELECT r.payload FROM sx_ygo_statistics_revisions r JOIN sx_ygo_statistics_current c ON c.revision_id=r.id WHERE c.id=1')->fetchColumn();
    if($previousPayload){$old=json_decode($previousPayload,true);$oldByKey=[];foreach($old['global']??[] as $row)$oldByKey[$row['team_id'].':'.$row['format_id']]=$row;
        foreach($data['global'] as &$row){$prior=$oldByKey[$row['team_id'].':'.$row['format_id']]??null;if(!$prior)continue;$oldAchievements=array_column($prior['achievements']??[],null,'code');
            foreach($row['achievements'] as &$achievement){$oldState=$oldAchievements[$achievement['code']]['status']??null;
                if(in_array($oldState,['DESBLOQUEADO','REVOCADO'],true)&&$achievement['status']!=='DESBLOQUEADO'){$achievement['status']='REVOCADO';$achievement['review_reason']='La evidencia confirmada actual ya no cumple la condicion.';}
            }unset($achievement);
        }unset($row);
        $currentKeys=[];foreach($data['global'] as $row)$currentKeys[$row['team_id'].':'.$row['format_id']]=true;
        foreach($oldByKey as $key=>$prior)if(!isset($currentKeys[$key])){
            $empty=ygo_stats_metric((int)$prior['team_id'],(int)$prior['deck_id'],(string)$prior['format_id']);
            $empty=array_merge($empty,['bonus_medallas'=>0,'total'=>1000.0,'state'=>'PROVISIONAL','sample_state'=>'PROVISIONAL','championships'=>0,'WR'=>null,'performance'=>null,'position'=>null]);
            $empty['achievements']=ygo_stats_achievements($empty);$oldAchievements=array_column($prior['achievements']??[],null,'code');
            foreach($empty['achievements'] as &$a)if(in_array($oldAchievements[$a['code']]['status']??'', ['DESBLOQUEADO','REVOCADO'],true)){$a['status']='REVOCADO';$a['review_reason']='La fuente confirmada ya no contiene la evidencia.';}unset($a);
            $data['global'][]=$empty;
        }
    }
    $q=$p->prepare('INSERT IGNORE INTO sx_ygo_statistics_medals(event_id,bonus_points,bonus_version,definition_json) VALUES(?,?,?,?)');foreach($data['medals'] as $m)$q->execute([$m['event_id'],$m['bonus_points'],$m['bonus_version'],json_encode($m)]);
    $previous=$p->query('SELECT revision_id FROM sx_ygo_statistics_current WHERE id=1')->fetchColumn();
    $q=$p->prepare('INSERT IGNORE INTO sx_ygo_statistics_revisions(source_hash,algorithm_version,payload,previous_revision_id,change_reason) VALUES(?,?,?,?,?)');$q->execute([$hash,YGO_STATS_VERSION,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION),$previous?:null,'Relectura del origen confirmado: comparar versiones de match y hash de fuente.']);$count=$q->rowCount();
    $p->prepare('INSERT INTO sx_ygo_statistics_current(id,revision_id) VALUES(1,?) ON DUPLICATE KEY UPDATE revision_id=VALUES(revision_id)')->execute([(int)$p->lastInsertId()]);$p->commit();return $count;
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}finally{$p->query("SELECT RELEASE_LOCK('station_x_statistics_v8')");}
}

function ygo_stats_publications(PDO $p,bool $full=true): array {
    if(!ygo_table_exists($p,'sx_ygo_statistics_publications'))return [];
    $q=$p->query('SELECT * FROM sx_ygo_statistics_publications ORDER BY cut_at,id');$cuts=[];
    while($cut=$q->fetch(PDO::FETCH_ASSOC)){
        $d=json_decode($cut['payload'],true);unset($cut['payload']);
        if(!$full){$small=['version'=>$d['version'],'cutoff'=>$d['cutoff'],'status'=>$d['status'],'summary_only'=>true,'global'=>[],'metagame'=>[],'free_for_all'=>['ranking'=>$d['free_for_all']['ranking']??[]]];
            foreach($d['global']??[] as $row)$small['global'][]=array_intersect_key($row,array_flip(['team_id','deck_id','format_id','position','rating','bonus_medallas','total','state','J','opponents','championships']));
            foreach($d['metagame']??[] as $row)$small['metagame'][]=array_intersect_key($row,array_flip(['season_id','format_id','deck_id','team_id','tier','IC','R','EL','C','RE','RD','rating_season','match_WR','J','JD','quality_status']));
            $d=$small;
        }
        $cut['data']=$d;$cuts[]=$cut;
    }return $cuts;
}

function ygo_stats_public_values(array $data): array {
    foreach($data as &$value)if(is_array($value))$value=ygo_stats_public_values($value);unset($value);
    if(($data['name']??null)==='Logro secreto' && isset($data['code']))$data['code']='secret_'.substr(hash('sha256',(string)$data['code']),0,12);
    return $data;
}

function ygo_stats_configure_global(PDO $p,string $anchor,array $mapping=[]): void {
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$anchor,new DateTimeZone('America/Bogota'));
    if(!$date||$date->format('Y-m-d')!==$anchor)throw new InvalidArgumentException('Selecciona una fecha inicial valida para los cortes globales.');
    $policy=ygo_stats_policy();$policy['global_anchor']=$anchor;
    foreach($mapping as $code=>$k){if(!in_array($code,['FULL','REDUCED','BONUS'],true)||!in_array($k,[20,25,30],true))throw new InvalidArgumentException('Mapa de importancia no admitido.');$policy['k_mapping'][$code]=$k;}
    $q=$p->query("SELECT payload FROM sx_ygo_statistics_policy WHERE scope_key='global'");$existing=$q->fetchColumn();
    if($existing && json_decode($existing,true)!==$policy)throw new InvalidArgumentException('La politica global ya esta fijada. Un cambio requiere una nueva version y revision explicita.');
    $q=$p->prepare("INSERT IGNORE INTO sx_ygo_statistics_policy(scope_key,version,payload) VALUES('global',?,?)");$q->execute([YGO_STATS_VERSION,json_encode($policy)]);
}

/** Authorized rollout defaults; never overwrite an already fixed policy. */
function ygo_stats_bootstrap_global(PDO $p): void {
    if($p->inTransaction())return;
    $q=$p->query("SELECT COUNT(*) FROM sx_ygo_statistics_policy WHERE scope_key='global'");
    if((int)$q->fetchColumn()===0)ygo_stats_configure_global($p,'2026-10-18',['FULL'=>20,'REDUCED'=>20,'BONUS'=>25]);
}

function ygo_stats_view(PDO $p): array {
    if(!ygo_table_exists($p,'sx_ygo_statistics_revisions'))return ['status'=>'PENDIENTE','reason'=>'Esquema estadistico no instalado'];
    $q=$p->query('SELECT r.payload FROM sx_ygo_statistics_revisions r JOIN sx_ygo_statistics_current c ON c.revision_id=r.id WHERE c.id=1');$payload=$q->fetchColumn();$data=$payload?json_decode($payload,true):['status'=>'PENDIENTE'];
    $data['publications']=ygo_stats_publications($p,false);$data['analytics']=ygo_stats_analytics($data);
    $data['revision_history']=$p->query('SELECT id,source_hash,algorithm_version,created_at,previous_revision_id,change_reason FROM sx_ygo_statistics_revisions ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    $data['achievement_catalog']=ygo_stats_achievements(ygo_stats_metric(0,0,'formato'));
    $source=ygo_stats_source($p);$data['available_cuts']=[];$data['can_publish']=false;
    $data['policy']['global_cadence']='per_result';$data['policy']['minimum_matches']=2;$data['policy']['minimum_opponents']=1;
    $data['source_summary']=['matches'=>count($source['matches']),'confirmed'=>count(array_filter($source['matches'],fn($m)=>$m['confirmed'])),'events'=>count($source['events']),'version'=>YGO_STATS_VERSION];
    return ygo_stats_public_values($data);
}

/** Legacy publication entry point: new cuts are retired; historical records remain readable. */
function ygo_stats_publish(PDO $p,string $kind,string $scope,string $cutAt,string $reason): int {
    throw new InvalidArgumentException('Los cortes estadísticos fueron retirados. La clasificación se actualiza con los resultados confirmados.');
}

/** Compatibility for old cron callers: never create another scheduled cut. */
function ygo_stats_publish_due(PDO $p,int $limit=8): int { return 0; }
