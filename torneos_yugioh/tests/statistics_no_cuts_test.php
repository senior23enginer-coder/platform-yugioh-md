<?php
require_once __DIR__.'/../includes/statistics_v8.php';
require_once __DIR__.'/../includes/statistics_store.php';
function requireLive(bool $ok): void { if(!$ok)throw new RuntimeException('Live statistics contract failed'); }
$pdo=new PDO('sqlite::memory:');
requireLive(ygo_stats_policy()['global_cadence']==='per_result');
requireLive(ygo_stats_publish_due($pdo)===0);
try { ygo_stats_publish($pdo,'global','tcg_advanced','2026-10-31 23:59:59','legacy'); throw new RuntimeException('Unexpected publication'); }
catch(InvalidArgumentException $e) { requireLive(str_contains($e->getMessage(),'retirados')); }
$data=ygo_stats_calculate([],[],[],['global_cadence'=>'monthly'],'2026-11-13 12:00:00');
requireLive($data['policy']['global_cadence']==='per_result');
requireLive(count($pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll())===0);
echo "OK: live cadence overrides legacy cadence; manual and automatic cuts perform zero SQL writes.\n";

// Extend the existing statistics acceptance program only on the disposable MySQL CI service.
if (getenv('STATIONX_MYSQL_TEST') === '1') {
    // Deliberately isolated integration fixture: never load application credentials.
    if (getenv('STATIONX_MYSQL_TEST') !== '1' || getenv('CI') !== 'true') {
        throw new RuntimeException('Run only against the disposable CI MySQL service.');
    }
    require_once dirname(__DIR__) . '/src/autoload.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/schema.php';
    require_once dirname(__DIR__) . '/includes/statistics_store.php';

    function verify(bool $ok, string $why): void
    {
        if (!$ok) {
            throw new RuntimeException($why);
        }
    }
    function connection(): PDO
    {
        return new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=stationx_statistics_test;charset=utf8mb4',
            'stationx_ci',
            (string) getenv('STATIONX_MYSQL_TEST_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }

    $pdo = connection();
    verify($pdo->query('SELECT DATABASE()')->fetchColumn() === 'stationx_statistics_test', 'Wrong database');
    verify((int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() === 0, 'Fixture requires empty database');
    verify(ygo_stats_sync($pdo) === 0, 'Missing schema must not write');
    verify(ygo_stats_view($pdo)['status'] === 'PENDIENTE', 'Missing schema must be pending');
    $pdo->beginTransaction();
    try {
        ygo_stats_schema($pdo);
        throw new RuntimeException('DDL was allowed in a sporting transaction');
    } catch (LogicException) {
        verify($pdo->inTransaction(), 'Schema guard committed a borrowed transaction');
    }
    $pdo->rollBack();
    ygo_stats_schema($pdo);
    ygo_stats_schema($pdo);
    ygo_stats_bootstrap_global($pdo);
    ygo_stats_bootstrap_global($pdo);
    verify((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_statistics_policy')->fetchColumn() === 1, 'Bootstrap must be idempotent');

    $ddl = [
        'CREATE TABLE sx_ygo_competitions (id INT PRIMARY KEY, season_id INT, type VARCHAR(20), event_ruleset VARCHAR(30), ranking_impact VARCHAR(20), status VARCHAR(20), name VARCHAR(80)) ENGINE=InnoDB',
        'CREATE TABLE sx_ygo_seasons (id INT PRIMARY KEY, status VARCHAR(20)) ENGINE=InnoDB',
        'CREATE TABLE sx_ygo_decks (id INT PRIMARY KEY, team_id INT) ENGINE=InnoDB',
        'CREATE TABLE sx_ygo_matches (id INT PRIMARY KEY, competition_id INT, division_id INT, deck_a_id INT, deck_b_id INT, participants_per_side INT, phase VARCHAR(30), status VARCHAR(20), judge VARCHAR(80), venue VARCHAR(80), completed_at DATETIME, scheduled_on DATE, starts_at TIME) ENGINE=InnoDB',
        'CREATE TABLE sx_ygo_match_results (match_id INT PRIMARY KEY, result CHAR(1), score VARCHAR(10), d1 CHAR(1), d2 CHAR(1), d3 CHAR(1), d4 CHAR(1), d5 CHAR(1), duel_details TEXT, dice_winner CHAR(1), updated_at DATETIME) ENGINE=InnoDB',
        'CREATE TABLE sx_ygo_match_snapshots (match_id INT, duel_number INT, side CHAR(1), deck_id INT, team_id INT, ruleset_code VARCHAR(30), data_origin VARCHAR(30), snapshot_status VARCHAR(30)) ENGINE=InnoDB',
        'CREATE TABLE sx_ygo_settings (season_id INT, setting_key VARCHAR(100), setting_value TEXT) ENGINE=InnoDB',
        'CREATE TABLE sx_ygo_honors (id INT PRIMARY KEY, competition_id INT, champion_team_id INT, champion_deck_id INT, created_at DATETIME) ENGINE=InnoDB',
    ];
    foreach ($ddl as $statement) {
        $pdo->exec($statement);
    }
    $pdo->exec("INSERT INTO sx_ygo_seasons VALUES (1,'active')");
    $pdo->exec("INSERT INTO sx_ygo_competitions VALUES (1,1,'league','tcg_advanced','FULL','active','Test league')");
    $pdo->exec('INSERT INTO sx_ygo_decks VALUES (101,1),(102,2)');
    $pdo->exec("INSERT INTO sx_ygo_matches VALUES
        (1,1,1,101,102,1,'league','FINALIZADA','','',DATE_SUB(NOW(), INTERVAL 4 DAY),DATE(DATE_SUB(NOW(), INTERVAL 4 DAY)),'19:00:00'),
        (2,1,1,101,102,1,'league','FINALIZADA','','',DATE_SUB(NOW(), INTERVAL 3 DAY),DATE(DATE_SUB(NOW(), INTERVAL 3 DAY)),'19:00:00')");
    $pdo->exec("INSERT INTO sx_ygo_match_results VALUES
        (1,'A','2-0','A','A',NULL,NULL,NULL,'[]',NULL,DATE_SUB(NOW(), INTERVAL 4 DAY)),
        (2,'A','2-0','A','A',NULL,NULL,NULL,'[]',NULL,DATE_SUB(NOW(), INTERVAL 3 DAY))");
    $pdo->exec("INSERT INTO sx_ygo_match_snapshots VALUES
        (1,1,'A',101,1,'tcg_advanced','captured','exact'),(1,1,'B',102,2,'tcg_advanced','captured','exact'),
        (2,1,'A',101,1,'tcg_advanced','captured','exact'),(2,1,'B',102,2,'tcg_advanced','captured','exact')");
    $source = ygo_stats_source($pdo);
    verify(count($source['matches']) === 2 && $source['matches'][0]['identity_quality'] === 'CONFIRMADA', 'Real SQL source projection failed');

    $pdo->beginTransaction();
    verify(ygo_stats_sync($pdo) === 0 && $pdo->inTransaction(), 'Sporting transaction must not refresh');
    verify((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_statistics_revisions')->fetchColumn() === 0, 'Sporting transaction wrote revisions');
    $pdo->rollBack();

    verify(ygo_stats_sync($pdo) === 1, 'First revision was not created');
    $revision = (int) $pdo->query('SELECT revision_id FROM sx_ygo_statistics_current WHERE id=1')->fetchColumn();
    verify($revision > 0, 'Current pointer invalid');
    verify(ygo_stats_sync($pdo) === 0, 'Unchanged input created duplicate revision');
    verify((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_statistics_revisions')->fetchColumn() === 1, 'Duplicate source revision');
    $data = ygo_stats_view($pdo);
    $teams = array_column($data['global'], null, 'team_id');
    verify($teams[1]['V'] === 2 && $teams[1]['position'] === 1, 'Ranking from SQL source differs');
    verify(abs($teams[1]['rating'] + $teams[2]['rating'] - 2000) < 1e-8, 'Elo is not zero sum');
    verify(!$data['can_publish'] && $data['policy']['global_cadence'] === 'per_result', 'Retired cuts resurfaced');

    $pdo->exec("UPDATE sx_ygo_match_results SET result='B',score='0-2',d1='B',d2='B',updated_at=DATE_SUB(NOW(), INTERVAL 2 DAY) WHERE match_id=2");
    verify(ygo_stats_sync($pdo) === 1, 'Correction did not create new revision');
    verify((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_statistics_revisions')->fetchColumn() === 2, 'Revision history was overwritten');
    $after = ygo_stats_view($pdo);
    $teams = array_column($after['global'], null, 'team_id');
    verify($teams[1]['V'] === 1 && $teams[2]['V'] === 1, 'Correction did not replay');
    verify((int) $pdo->query('SELECT previous_revision_id FROM sx_ygo_statistics_revisions ORDER BY id DESC LIMIT 1')->fetchColumn() === $revision, 'Revision link missing');
    $second = connection();
    verify((int) $second->query("SELECT GET_LOCK('station_x_statistics_v8',0)")->fetchColumn() === 1, 'Statistics lock leaked');
    $second->query("SELECT RELEASE_LOCK('station_x_statistics_v8')");
    verify((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_matches')->fetchColumn() === 2, 'Statistics mutated sporting rows');
    echo "OK: MySQL statistics schema, source identity, revision idempotence, corrections, borrowed transaction, lock release and preserved matches.\n";
}
