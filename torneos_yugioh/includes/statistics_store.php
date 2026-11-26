<?php
declare(strict_types=1);
require_once __DIR__.'/statistics_v8.php';
require_once __DIR__.'/statistics_multiplayer_source.php';

function ygo_stats_canonical(mixed $value): string {
    return \StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy::canonical($value);
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
    return (new \StationX\Yugioh\Application\Statistics\ReadStatisticsSource(new \StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsSourceReader($p)))->execute();
}

function ygo_stats_sync(PDO $p): int {
    return \StationX\Yugioh\Infrastructure\Composition\StatisticsModule::refresh($p)->execute();
}

function ygo_stats_publications(PDO $p,bool $full=true): array {
    return (new \StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsRevisionStore($p))->publications($full);
}

function ygo_stats_public_values(array $data): array {
    return \StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy::publicValues($data);
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
