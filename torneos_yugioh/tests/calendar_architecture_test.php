<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use StationX\Yugioh\Application\Calendar\ScheduleReader;
use StationX\Yugioh\Application\Calendar\ScheduleWriter;
use StationX\Yugioh\Application\Calendar\ScheduleCapacity;
use StationX\Yugioh\Application\Calendar\ScheduleEvents;
use StationX\Yugioh\Application\Calendar\CalendarTime;
use StationX\Yugioh\Application\Calendar\ReflowPendingSchedule;
use StationX\Yugioh\Application\Calendar\AssignExplicitSchedule;
use StationX\Yugioh\Domain\Calendar\PendingSchedulePlanner;
use StationX\Yugioh\Domain\Calendar\ScheduleTimePolicy;
use StationX\Yugioh\Infrastructure\Composition\CalendarModule;

$count = 0;
function calendarCheck(bool $ok, string $why): void
{
    global $count;
    if (!$ok) {
        throw new RuntimeException($why);
    }
    $count++;
}

/** One in-memory scenario implements each outgoing port and records cross-port ordering. */
final class CalendarProbe implements
    ScheduleReader,
    ScheduleWriter,
    ScheduleCapacity,
    ScheduleEvents,
    CalendarTime
{
    public array $trace = [];
    public ?array $competition = ['id' => 1, 'type' => 'cup'];
    public array $ids = [30, 20, 10];
    public array $assigned = [];
    public bool $failWrite = false;

    public function activeCompetition(int $id): ?array
    {
        $this->trace[] = 'active';
        return $this->competition;
    }
    public function pendingMatchIds(int $id): array
    {
        $this->trace[] = 'pending';
        return $this->ids;
    }
    public function occupancy(int $id): array
    {
        $this->trace[] = 'occupancy';
        return ['2026-11-16' => ['count' => 3, 'times' => []]];
    }
    public function blackouts(int $id): array
    {
        $this->trace[] = 'blackouts';
        return ['2026-11-17' => true];
    }
    public function competitionType(int $id): string
    {
        $this->trace[] = 'type';
        return 'cup';
    }
    public function clearPendingDates(int $id): void
    {
        $this->trace[] = 'clear';
    }
    public function assign(int $id, int $match, string|int $date, string $time): void
    {
        $this->trace[] = 'assign';
        if ($this->failWrite && $this->assigned) {
            throw new RuntimeException('fixture write failure');
        }
        $this->assigned[] = [$id, $match, $date, $time];
    }
    public function setEndDate(int $id, string|int $date): void
    {
        $this->trace[] = 'end';
    }
    public function limit(): int
    {
        $this->trace[] = 'capacity';
        return 3;
    }
    public function rebuild(int $id, string $type): void
    {
        $this->trace[] = 'publish';
    }
    public function parse(string $value): DateTimeImmutable
    {
        $this->trace[] = 'parse';
        return new DateTimeImmutable($value, new DateTimeZone('America/Bogota'));
    }
    public function evening(): DateTimeImmutable
    {
        $this->trace[] = 'evening';
        return new DateTimeImmutable('2026-11-18 19:00:00-05:00');
    }
}
$probe = new CalendarProbe();
$useCase = new ReflowPendingSchedule($probe, $probe, $probe, $probe, $probe);
$summary = $useCase->execute(1, '2026-11-16', 'consecutive', 1);
calendarCheck(!function_exists('ygo_reflow_pending_competition'), 'Core loaded the native facade');
calendarCheck(
    $summary === ['scheduled' => 3, 'start' => '2026-11-18', 'end' => '2026-11-20'],
    'Automatic dates/quota changed',
);
calendarCheck(array_column($probe->assigned, 1) === [30, 20, 10], 'Native match order changed');
calendarCheck(
    $probe->trace === [
        'active',
        'pending',
        'clear',
        'capacity',
        'occupancy',
        'blackouts',
        'parse',
        'evening',
        'assign',
        'assign',
        'assign',
        'end',
        'publish',
    ],
    'Cross-port write/refresh order changed',
);
calendarCheck($probe->ids === [30, 20, 10], 'Input IDs mutated');
$probe = new CalendarProbe();
$probe->competition = null;
calendarCheck(
    (new ReflowPendingSchedule($probe, $probe, $probe, $probe, $probe))->execute(
        99,
        'invalid',
        'consecutive',
        1,
    ) === ['scheduled' => 0, 'start' => null, 'end' => null],
    'Missing event must short-circuit',
);
calendarCheck($probe->trace === ['active'], 'Missing event caused queries or writes');
$probe = new CalendarProbe();
$probe->ids = [];
(new ReflowPendingSchedule($probe, $probe, $probe, $probe, $probe))->execute(
    1,
    'invalid',
    'consecutive',
    1,
);
calendarCheck(
    $probe->trace === ['active', 'pending'],
    'Empty fixture changed clock or stored dates',
);
$probe = new CalendarProbe();
$probe->failWrite = true;
try {
    (new ReflowPendingSchedule($probe, $probe, $probe, $probe, $probe))->execute(
        1,
        '2026-11-16',
        'consecutive',
        1,
    );
    throw new LogicException('Expected write rejection');
} catch (RuntimeException $e) {
    calendarCheck($e->getMessage() === 'fixture write failure', 'Write error was replaced');
}
calendarCheck(
    !in_array('end', $probe->trace, true) && !in_array('publish', $probe->trace, true),
    'Failed write published an incomplete calendar',
);
$probe = new CalendarProbe();
$probe->ids = [];
calendarCheck(
    (new AssignExplicitSchedule($probe, $probe, $probe, $probe, $probe))->execute(1, []) === [
        'scheduled' => 0,
        'end' => null,
    ],
    'Explicit empty result changed',
);
calendarCheck(
    $probe->trace === ['pending', 'clear', 'occupancy', 'capacity', 'evening', 'type', 'publish'],
    'Explicit empty call lost native clear/rebuild behavior',
);

// Qualifier priority and orchestration can be exercised without any native services.
final class QualifierProbe implements
    \StationX\Yugioh\Application\Calendar\QualifierScheduleReader,
    \StationX\Yugioh\Application\Calendar\QualifierScheduleWriter
{
    public int $activeReads = 0;
    public array $cleared = [];
    public ?array $configuration = [
        'id' => 4,
        'type' => 'cup',
        'starts_on' => '2026-11-16',
        'schedule_pattern' => 'consecutive',
        'qualifier_matches_per_day' => 1,
    ];
    public array $qualifiers = [
        [
            'id' => 1,
            'starts_on' => '2026-11-19',
            'schedule_pattern' => 'consecutive',
            'qualifier_matches_per_day' => 1,
        ],
        [
            'id' => 2,
            'starts_on' => '2026-10-01',
            'schedule_pattern' => 'every_other_day',
            'qualifier_matches_per_day' => 1,
        ],
    ];
    public function activeQualifiers(): array
    {
        $this->activeReads++;
        return $this->qualifiers;
    }
    public function competitionConfiguration(int $id): ?array
    {
        return $this->configuration;
    }
    public function clearPendingDatesExcept(int $id): int
    {
        $this->cleared[] = $id;
        return 2;
    }
}
$probe = new CalendarProbe();
$qualifierProbe = new QualifierProbe();
$reflow = new ReflowPendingSchedule($probe, $probe, $probe, $probe, $probe);
$qualifiers = new \StationX\Yugioh\Application\Calendar\ReflowActiveQualifiers(
    $qualifierProbe,
    $reflow,
    $probe,
);
$results = $qualifiers->execute('2026-11-18');
calendarCheck(array_keys($results) === [1, 2], 'Qualifier order changed');
calendarCheck(
    $results[1]['start'] === '2026-11-19' && $results[2]['start'] === '2026-11-18',
    'Configured and minimum qualifier starts changed',
);
$probe = new CalendarProbe();
$qualifierProbe = new QualifierProbe();
$qualifierProbe->qualifiers = [];
$reflow = new ReflowPendingSchedule($probe, $probe, $probe, $probe, $probe);
$qualifiers = new \StationX\Yugioh\Application\Calendar\ReflowActiveQualifiers(
    $qualifierProbe,
    $reflow,
    $probe,
);
$rebalance = new \StationX\Yugioh\Application\Calendar\RebalanceSchedule(
    $qualifierProbe,
    $qualifierProbe,
    $reflow,
    $qualifiers,
    $probe,
);
$rebalance->execute(4);
calendarCheck(
    $qualifierProbe->cleared === [0] && $qualifierProbe->activeReads === 1,
    'Non-qualifier priority no longer clears/reflows qualifiers',
);
$qualifierProbe->configuration['type'] = 'qualifier';
$qualifierProbe->cleared = [];
$qualifierProbe->activeReads = 0;
$rebalance->execute(4);
calendarCheck(
    $qualifierProbe->cleared === [] && $qualifierProbe->activeReads === 0,
    'Qualifier schedule displaced unrelated qualifiers',
);
$qualifierProbe->configuration = null;
$beforeTrace = $probe->trace;
$rebalance->execute(99);
calendarCheck($probe->trace === $beforeTrace, 'Missing event caused calendar writes');
$probe = new CalendarProbe();
$probe->failWrite = true;
$qualifierProbe = new QualifierProbe();
$reflow = new ReflowPendingSchedule($probe, $probe, $probe, $probe, $probe);
$qualifiers = new \StationX\Yugioh\Application\Calendar\ReflowActiveQualifiers(
    $qualifierProbe,
    $reflow,
    $probe,
);
$rebalance = new \StationX\Yugioh\Application\Calendar\RebalanceSchedule(
    $qualifierProbe,
    $qualifierProbe,
    $reflow,
    $qualifiers,
    $probe,
);
try {
    $rebalance->execute(4);
    throw new LogicException('Expected rebalance write failure');
} catch (RuntimeException $error) {
    calendarCheck(
        $error->getMessage() === 'fixture write failure',
        'Rebalance swallowed target failure',
    );
}
calendarCheck(
    $qualifierProbe->cleared === [0] && $qualifierProbe->activeReads === 0,
    'Failed target continued reflowing qualifiers',
);

// The domain planner does not mutate occupancy, exclusions or caller-supplied quotas.
$ids = [4, 2];
$quotas = ['2026-11-20' => 1, '2026-11-18' => 1];
$occupancy = [];
$before = serialize([$ids, $quotas, $occupancy]);
$plan = PendingSchedulePlanner::explicit(
    $ids,
    $quotas,
    3,
    $occupancy,
    new DateTimeImmutable('2026-11-01 19:00:00+00:00'),
);
$slots = iterator_to_array($plan);
calendarCheck(
    array_column($slots, 'date') === ['2026-11-20', '2026-11-18'],
    'Explicit insertion order changed',
);
calendarCheck(
    $plan->getReturn()['end'] === '2026-11-18',
    'Last assigned date was incorrectly sorted',
);
calendarCheck(serialize([$ids, $quotas, $occupancy]) === $before, 'Planner mutated inputs');
calendarCheck(
    ScheduleTimePolicy::next(
        ['19:00' => true],
        0,
        new DateTimeImmutable('2000-01-01 19:00:00+00:00'),
    ) === '19:45:00',
    'Preferred time priority changed',
);
calendarCheck(
    ScheduleTimePolicy::next(
        ['19:00' => true, '19:45' => true, '20:30' => true],
        3,
        new DateTimeImmutable('2000-01-01 19:00:00+00:00'),
    ) === '21:15:00',
    'Fallback interval changed',
);

// Native bridge doubles: no production database or real event rows are accessed.
function ygo_settings(PDO $pdo): array
{
    return ['max_matches_per_day' => $GLOBALS['calendar_capacity']];
}
function ygo_current_season_id(PDO $pdo): int
{
    return 7;
}
function ygo_rebuild_competition_calendar_events_from_matches(
    PDO $pdo,
    int $seasonId,
    int $competitionId,
    string $type,
): int {
    $GLOBALS['calendar_events'][] = [$seasonId, $competitionId, $type];
    return 1;
}
require dirname(__DIR__) . '/includes/schedule_capacity.php';
require dirname(__DIR__) . '/includes/calendar/scheduling.php';

function calendarDatabase(array $case): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(
        'CREATE TABLE sx_ygo_competitions(id INT PRIMARY KEY,type TEXT,active INT,ends_on TEXT,status TEXT); CREATE TABLE sx_ygo_matches(id INT PRIMARY KEY,competition_id INT,status TEXT,scheduled_on TEXT,starts_at TEXT,display_order INT,round_number INT,table_number INT); CREATE TABLE sx_ygo_schedule_blackouts(competition_id INT,blackout_date TEXT,active INT)',
    );
    $q = $pdo->prepare('INSERT INTO sx_ygo_competitions VALUES(?,?,?,?,?)');
    foreach ($case['competitions'] as $row) {
        $q->execute([...$row, $row[1] === 'completed' ? 'completed' : 'active']);
    }
    $q = $pdo->prepare('INSERT INTO sx_ygo_matches VALUES(?,?,?,?,?,?,?,?)');
    foreach ($case['matches'] as $row) {
        $q->execute($row);
    }
    $q = $pdo->prepare('INSERT INTO sx_ygo_schedule_blackouts VALUES(?,?,?)');
    foreach ($case['blackouts'] as $row) {
        $q->execute($row);
    }
    return $pdo;
}
function calendarState(PDO $pdo): array
{
    return [
        'matches' => $pdo
            ->query('SELECT * FROM sx_ygo_matches ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC),
        'competitions' => $pdo
            ->query('SELECT * FROM sx_ygo_competitions ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC),
    ];
}
$empty = new PDO('sqlite::memory:');
CalendarModule::create($empty);
calendarCheck(
    (int) $empty->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn() ===
        0,
    'Composition initialized schema',
);
$cases = json_decode(
    file_get_contents(__DIR__ . '/fixtures/calendar-characterization.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
foreach ($cases as $case) {
    $pdo = calendarDatabase($case);
    $GLOBALS['calendar_capacity'] = $case['capacity'];
    $GLOBALS['calendar_events'] = [];
    $result = null;
    $error = null;
    try {
        $result =
            $case['mode'] === 'automatic'
                ? ygo_reflow_pending_competition(
                    $pdo,
                    $case['id'],
                    $case['start'],
                    $case['pattern'],
                    $case['daily'],
                )
                : ygo_assign_explicit_competition_schedule($pdo, $case['id'], $case['quotas']);
    } catch (Throwable $e) {
        $error = [
            'class' =>
                $e instanceof \DateMalformedStringException ? Exception::class : get_class($e),
            'message' => $e->getMessage(),
        ];
    }
    $actual = ['result' => $result, 'error' => $error] +
        calendarState($pdo) + [
            'events' => $GLOBALS['calendar_events'],
            'in_transaction' => $pdo->inTransaction(),
        ];
    calendarCheck(
        $actual === $case['expected'],
        'Original schedule behavior changed: ' . $case['name'],
    );
}

// The qualifier reader uses the selected season, active flag, status exclusions and native ID order.
$pdo = calendarDatabase($cases[0]);
$pdo->exec(
    'ALTER TABLE sx_ygo_competitions ADD COLUMN season_id INT; ALTER TABLE sx_ygo_competitions ADD COLUMN starts_on TEXT; ALTER TABLE sx_ygo_competitions ADD COLUMN schedule_pattern TEXT; ALTER TABLE sx_ygo_competitions ADD COLUMN qualifier_matches_per_day INT',
);
$pdo->exec(
    "UPDATE sx_ygo_competitions SET season_id=7,type='qualifier',starts_on='2026-11-18',schedule_pattern='consecutive',qualifier_matches_per_day=1",
);
$reader = new \StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoQualifierSchedules($pdo);
calendarCheck(
    array_column($reader->activeQualifiers(), 'id') === [1, 2],
    'Qualifier SQL filters changed',
);
$pdo->exec('UPDATE sx_ygo_competitions SET season_id=8 WHERE id=2');
calendarCheck(
    array_column($reader->activeQualifiers(), 'id') === [1],
    'Qualifier query crossed seasons',
);
calendarCheck(
    $reader->competitionConfiguration(99) === null,
    'Missing competition gained configuration',
);

// Success and failure must leave a caller-owned transaction open, for its caller to commit or undo.
$case = $cases[0];
$GLOBALS['calendar_capacity'] = $case['capacity'];
$GLOBALS['calendar_events'] = [];
$pdo = calendarDatabase($case);
$before = calendarState($pdo);
$pdo->beginTransaction();
ygo_reflow_pending_competition($pdo, 1, $case['start'], $case['pattern'], $case['daily']);
calendarCheck($pdo->inTransaction(), 'Successful reflow committed caller transaction');
$pdo->rollBack();
calendarCheck(
    calendarState($pdo) === $before,
    'Caller rollback failed to restore matches and competition',
);
$pdo->beginTransaction();
try {
    ygo_assign_explicit_competition_schedule($pdo, 1, ['2026-11-16' => 1]);
    throw new LogicException('Expected quota rejection');
} catch (RuntimeException $e) {
    calendarCheck(
        $e->getMessage() === 'La programacion explicita no contiene cupos para todos los combates.',
        'Quota failure changed',
    );
}
calendarCheck($pdo->inTransaction(), 'Failure rolled back caller transaction');
$pdo->rollBack();
calendarCheck(calendarState($pdo) === $before, 'Partial assignments escaped caller rollback');
$pdo->exec(
    "CREATE TRIGGER reject_calendar_assignment BEFORE UPDATE ON sx_ygo_matches WHEN NEW.status <> 'FINALIZADA' AND NEW.scheduled_on IS NOT NULL BEGIN SELECT RAISE(ABORT,'calendar fixture failure'); END",
);
$pdo->beginTransaction();
$GLOBALS['calendar_events'] = [];
try {
    ygo_reflow_pending_competition($pdo, 1, $case['start'], $case['pattern'], $case['daily']);
    throw new LogicException('Expected storage rejection');
} catch (PDOException) {
    $count++;
}
calendarCheck(
    $pdo->inTransaction() && $GLOBALS['calendar_events'] === [],
    'Storage failure changed transaction ownership or published events',
);
$pdo->rollBack();
calendarCheck(calendarState($pdo) === $before, 'Storage error escaped caller rollback');
echo "PASS $count checks: calendar ports, 13 original scenarios, stable ordering and caller-owned transactions.\n";
