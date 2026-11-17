<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/seasons.php';
date_default_timezone_set('America/Bogota');
$dsn = getenv('YGO_TEST_DSN') ?: 'mysql:host=127.0.0.1;port=33317;dbname=ygo_season_test;charset=utf8mb4';
if (!str_contains($dsn, 'dbname=ygo_season_test')) throw new RuntimeException('This test only runs against the isolated ygo_season_test database.');
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$checks = 0;
function check(bool $value, string $message): void { global $checks; if (!$value) throw new RuntimeException($message); ++$checks; }
function rejected(callable $call, string $message): void { try { $call(); } catch (InvalidArgumentException $error) { check(true, $message); return; } throw new RuntimeException($message); }
function seasonInput(string $name, string $period='annual', string $start='2030-01-01'): array { return ['season_name'=>$name,'season_period'=>$period,'season_starts_on'=>$start,'season_status'=>'draft']; }
function fixtureEvent(PDO $pdo, int $seasonId, string $status='pending_configuration'): int {
    $pdo->prepare("INSERT INTO sx_ygo_competitions(season_id,code,name,type,status,event_ruleset,active) VALUES(?,?,?,'league',?,'tcg_advanced',1)")->execute([$seasonId,'test-'.bin2hex(random_bytes(5)),'Season lifecycle fixture',$status]);
    return (int)$pdo->lastInsertId();
}
$original = $pdo->query('SELECT id,season_id,status,name FROM sx_ygo_competitions WHERE season_id=1 ORDER BY id')->fetchAll();
check(count($original)===7, 'Expected the seven preserved competitions in the isolated copy.');
check(ygo_season_end('2030-01-01','annual')==='2030-12-31','Annual cycle');
check(ygo_season_end('2030-01-01','semiannual')==='2030-06-30','Semester cycle');
check(ygo_season_end('2030-07-01','quarterly')==='2030-09-30','Quarter cycle');
check(ygo_season_end('2028-01-01','quarterly')==='2028-03-31','Leap year quarter');
rejected(fn()=>ygo_season_end('2030-02-30','annual'),'Invalid calendar date must fail');
rejected(fn()=>ygo_season_end('2030-01-01','monthly'),'Unsupported cycle must fail');
$empty = ygo_season_save($pdo, seasonInput('QA Empty season'));
check(ygo_season_find($pdo,$empty)['ends_on']==='2030-12-31','Created season persisted');
ygo_season_save($pdo, array_merge(seasonInput('QA Updated season','semiannual'),['season_id'=>$empty]));
check(ygo_season_find($pdo,$empty)['name']==='QA Updated season','Season edit persisted');
ygo_season_delete($pdo,$empty);
check(ygo_season_find($pdo,$empty)===null,'Empty season deleted');
$id = ygo_season_save($pdo, seasonInput('QA Lifecycle season'));
$pending=fixtureEvent($pdo,$id);$completed=fixtureEvent($pdo,$id,'completed');$cancelled=fixtureEvent($pdo,$id,'cancelled');
$GLOBALS['ygo_request_season_id']=$id;
$_POST=['action'=>'save_competition','competition_season_id'=>$id,'starts_on'=>'2030-04-01','ends_on'=>'2030-04-30'];
check(ygo_season_guard_post($pdo,'save_competition')===null,'Open season accepts event dates');
unset($_POST['competition_season_id']);
check(ygo_season_guard_post($pdo,'save_competition')==='evento_temporada_bloqueada','Season is mandatory');
$_POST=['competition_season_id'=>$id,'starts_on'=>'2031-01-01'];
check(ygo_season_guard_post($pdo,'save_competition')==='evento_temporada_bloqueada','Out-of-period event rejected');
$_POST=['action'=>'save_competition','competition_id'=>$pending,'competition_season_id'=>1];
ygo_season_initialize_context($pdo);
check(ygo_season_guard_post($pdo,'save_competition')==='evento_temporada_bloqueada','Cannot move event to another season by changing its form');
$pdo->prepare("UPDATE sx_ygo_competitions SET starts_on='2030-09-01',ends_on='2030-09-30' WHERE id=?")->execute([$pending]);
rejected(fn()=>ygo_season_save($pdo,array_merge(seasonInput('QA Invalid resize','semiannual'),['season_id'=>$id])),'Cannot shrink a season over its events');
rejected(fn()=>ygo_season_delete($pdo,$id),'Cannot delete season with events');
$beforeStats=(int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_match_results')->fetchColumn();
check(ygo_season_close($pdo,$id)===1,'Close unfinished event only');
check(ygo_season_find($pdo,$id)['status']==='completed','Season closed');
check(ygo_season_find($pdo,$id)['closed_at']!==null,'Closure time persisted');
check($pdo->query('SELECT status FROM sx_ygo_competitions WHERE id='.$pending)->fetchColumn()==='closed','Unfinished event closed without inventing sporting completion');
check($pdo->query('SELECT status FROM sx_ygo_competitions WHERE id='.$completed)->fetchColumn()==='completed','Completed event preserved');
check($pdo->query('SELECT status FROM sx_ygo_competitions WHERE id='.$cancelled)->fetchColumn()==='cancelled','Cancelled event preserved');
check((int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_match_results')->fetchColumn()===$beforeStats,'Closure preserves results');
check(ygo_season_close($pdo,$id)===0,'Closure is idempotent');
rejected(fn()=>ygo_season_save($pdo,array_merge(seasonInput('QA Cannot reopen'),['season_id'=>$id])),'Closed season cannot be reopened');
foreach(['save_match_result','save_match_schedule','configure_competition','advance_competition','save_competition','delete_competition','save_division'] as $action) {
    $_POST=['competition_id'=>$pending,'competition_season_id'=>$id];
    check(ygo_season_guard_post($pdo,$action)==='evento_temporada_bloqueada','Closed season rejects '.$action);
}
$expired=ygo_season_save($pdo,seasonInput('QA Expired season','quarterly','2020-01-01'));
$expiredEvent=fixtureEvent($pdo,$expired);
ygo_season_close_expired($pdo);
check(ygo_season_find($pdo,$expired)['status']==='completed','Expired season closes automatically on access');
check($pdo->query('SELECT status FROM sx_ygo_competitions WHERE id='.$expiredEvent)->fetchColumn()==='closed','Expired season event closes');
check($pdo->query('SELECT id,season_id,status,name FROM sx_ygo_competitions WHERE season_id=1 ORDER BY id')->fetchAll()===$original,'All seven real competitions stay untouched');
try { fixtureEvent($pdo, 2147483000); throw new RuntimeException('Missing season accepted by DB'); } catch (PDOException $error) { check($error->getCode()==='23000','Database enforces season foreign key'); }
// Leave a closed fixture for HTTP/live-state checks; this database is disposable.
$pdo->prepare("INSERT INTO sx_ygo_matches(competition_id,phase,round_number,table_number,deck_a_id,deck_b_id,status) VALUES(?,'league',1,1,646,647,'PENDIENTE')")->execute([$pending]);
$fixture=['closed_season_id'=>$id,'closed_event_id'=>$pending,'closed_match_id'=>(int)$pdo->lastInsertId()];
$pdo->prepare("UPDATE sx_ygo_matches SET scheduled_on='2031-01-01' WHERE id=?")->execute([$fixture['closed_match_id']]);
rejected(fn()=>ygo_season_validate_competition_schedule($pdo,$pending),'Generated schedules cannot exceed the season');
$pdo->prepare("UPDATE sx_ygo_matches SET scheduled_on='2030-06-01' WHERE id=?")->execute([$fixture['closed_match_id']]);
ygo_season_validate_competition_schedule($pdo,$pending);
check(true,'In-period schedule accepted');
file_put_contents(dirname(__DIR__,2).'/outputs/ygo-seasons-20261015/test-fixtures.json',json_encode($fixture));
echo json_encode(['passed'=>true,'checks'=>$checks,'fixture'=>$fixture],JSON_PRETTY_PRINT),PHP_EOL;
