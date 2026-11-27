<?php
declare(strict_types=1);

use StationX\Yugioh\Application\Matches\ApplyLiveDuelChange;
use StationX\Yugioh\Application\Matches\LoadLiveDuel;
use StationX\Yugioh\Domain\Matches\LiveDuelState;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoLiveDuelReadStore;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoLiveDuelWriteStore;

if (getenv('CI') !== 'true' || getenv('STATIONX_LIVE_DUEL_MYSQL_TEST') !== '1') {
    throw new RuntimeException('Only the disposable CI MySQL service is supported.');
}
require dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/includes/seasons.php';

function liveCheck(bool $ok, string $why): void
{
    if (!$ok) {
        throw new RuntimeException($why);
    }
}
function liveConnection(): PDO
{
    return new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=stationx_live_duel_test;charset=utf8mb4',
        'stationx_ci',
        (string) getenv('STATIONX_MYSQL_TEST_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
    );
}
$pdo = liveConnection();
$second = liveConnection();
liveCheck($pdo->query('SELECT DATABASE()')->fetchColumn() === 'stationx_live_duel_test', 'Wrong database');
liveCheck((int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() === 0, 'Fixture requires empty database');

$ddl = [
    'CREATE TABLE sx_ygo_seasons (id INT PRIMARY KEY, status VARCHAR(20), starts_on DATE, ends_on DATE) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_competitions (id INT PRIMARY KEY, season_id INT, name VARCHAR(80)) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_divisions (id INT PRIMARY KEY, name VARCHAR(80)) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_teams (id INT PRIMARY KEY, name VARCHAR(80), logo_url VARCHAR(255)) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_decks (id INT PRIMARY KEY, team_id INT, name VARCHAR(80)) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_matches (id INT PRIMARY KEY, competition_id INT, division_id INT, deck_a_id INT, deck_b_id INT, status VARCHAR(20), best_of INT, round_number INT, table_number INT, scheduled_on DATE, starts_at TIME) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_match_results (match_id INT PRIMARY KEY, d1 CHAR(1), d2 CHAR(1), d3 CHAR(1), d4 CHAR(1), d5 CHAR(1), duel_details TEXT) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_live_duel_state (match_id INT PRIMARY KEY, duel_number INT, revision INT, state_json TEXT, updated_by VARCHAR(20), updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB',
    'CREATE TABLE sx_ygo_audit_log (id INT AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(20), entity_id INT, action VARCHAR(80), payload TEXT) ENGINE=InnoDB',
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}
$pdo->exec("INSERT INTO sx_ygo_seasons VALUES (1,'active',NULL,NULL)");
$pdo->exec("INSERT INTO sx_ygo_competitions VALUES (1,1,'Test event')");
$pdo->exec("INSERT INTO sx_ygo_divisions VALUES (1,'Test division')");
$pdo->exec("INSERT INTO sx_ygo_teams VALUES (1,'Team A',''),(2,'Team B','')");
$pdo->exec("INSERT INTO sx_ygo_decks VALUES (101,1,'Deck A'),(102,2,'Deck B')");
$pdo->exec("INSERT INTO sx_ygo_matches VALUES (1,1,1,101,102,'EN CURSO',3,1,1,NULL,NULL)");

$now = static fn(): int => 5000;
$ms = static fn(): float => 5000.0;
$atom = static fn(): string => '2026-11-27T01:00:00-05:00';
$loader = new LoadLiveDuel(new PdoLiveDuelReadStore($pdo));
$otherLoader = new LoadLiveDuel(new PdoLiveDuelReadStore($second));
$command = new ApplyLiveDuelChange($loader, new PdoLiveDuelWriteStore($pdo));
$otherCommand = new ApplyLiveDuelChange($otherLoader, new PdoLiveDuelWriteStore($second));
$change = static fn(ApplyLiveDuelChange $command, int $revision, string $actor, string $op, array $payload = []): ?array =>
    $command->execute(1, $revision, $actor, $op, $payload, $now, $ms, $atom);

liveCheck($loader->execute(999, false, $now) === [], 'Missing match was initialized');
liveCheck($loader->execute(1, false, $now)['revision'] === 1, 'Initial state failed');
liveCheck($otherLoader->execute(1, false, $now)['revision'] === 1, 'Second reader changed revision');
liveCheck((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_live_duel_state')->fetchColumn() === 1, 'Duplicate initial row');
$initial = LiveDuelState::normalize(['duel_number'=>1, 'status'=>'running', 'starts_player'=>'A', 'active_side'=>'A'], $now);

$pdo->beginTransaction();
try {
    $result = $change($command, 1, 'judge', 'sync', $initial);
    liveCheck($result['revision'] === 2 && $pdo->inTransaction(), 'Write did not preserve caller transaction');
    // A separate connection cannot acquire the season/duel write lock before rollback.
    $second->exec('SET SESSION innodb_lock_wait_timeout=1');
    $second->beginTransaction();
    try {
        $change($otherCommand, 1, 'A', 'end_turn');
        throw new RuntimeException('Concurrent writer did not wait for lock');
    } catch (PDOException $error) {
        liveCheck((int) ($error->errorInfo[1] ?? 0) === 1205, 'Unexpected concurrent SQL error');
    } finally {
        if ($second->inTransaction()) {
            $second->rollBack();
        }
    }
    $pdo->rollBack();
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
liveCheck($otherLoader->execute(1, false, $now)['revision'] === 1, 'Rollback leaked a revision');

$pdo->beginTransaction();
$change($command, 1, 'judge', 'sync', $initial);
$pdo->commit();
$second->beginTransaction();
liveCheck($change($otherCommand, 1, 'A', 'end_turn') === null, 'Stale revision accepted');
$second->rollBack();
liveCheck($otherLoader->execute(1, false, $now)['revision'] === 2, 'Conflict changed revision');

$pdo->beginTransaction();
$changed = $change($command, 2, 'A', 'surrender');
liveCheck($changed['state']['status'] === 'completed' && $changed['revision'] === 3, 'Surrender failed');
liveCheck((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_audit_log')->fetchColumn() === 1, 'Missing surrender audit');
$pdo->rollBack();
liveCheck((int) $second->query('SELECT COUNT(*) FROM sx_ygo_audit_log')->fetchColumn() === 0, 'Audit survived rollback');
liveCheck($otherLoader->execute(1, false, $now)['revision'] === 2, 'Surrender survived rollback');

$second->beginTransaction();
$changed = $change($otherCommand, 2, 'A', 'surrender');
$second->commit();
liveCheck($loader->execute(1, false, $now)['state']['surrendered_by'] === 'A', 'Committed state invisible');
liveCheck((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_audit_log')->fetchColumn() === 1, 'Surrender audit duplicated');
liveCheck((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_match_results')->fetchColumn() === 0, 'Live state modified match result');

$pdo->exec("UPDATE sx_ygo_seasons SET status='completed' WHERE id=1");
$pdo->beginTransaction();
try {
    $change($command, 3, 'judge', 'sync', $initial);
    throw new RuntimeException('Closed season accepted');
} catch (InvalidArgumentException $error) {
    liveCheck(str_contains($error->getMessage(), 'finalizada'), 'Wrong closed-season message');
} finally {
    $pdo->rollBack();
}
liveCheck($otherLoader->execute(1, false, $now)['revision'] === 3, 'Closed season changed state');
echo "OK: MySQL live-duel initial state, concurrent lock, rollback, revision conflict, surrender audit and season guard.\n";
