<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/autoload.php';

use StationX\Yugioh\Application\Seasons\GetCurrentSeason;
use StationX\Yugioh\Application\Seasons\SeasonRepository;
use StationX\Yugioh\Domain\Competitions\FixtureBuilder;
use StationX\Yugioh\Domain\Competitions\KnockoutSize;
use StationX\Yugioh\Domain\Competitions\RoundRobinPairings;
use StationX\Yugioh\Domain\Seasons\Season;
use StationX\Yugioh\Infrastructure\Persistence\LoadedSeasonRepository;

function architectureCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

// Characterization snapshots were produced by the existing functions before extraction.
$cases = json_decode(
    file_get_contents(__DIR__ . '/fixtures/pairings-characterization.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
foreach ($cases as $case) {
    $decks = [];
    for ($i = 0; $i < $case['count']; $i++) {
        $decks[] = [$i + 1, 'Deck ' . ($i + 1), ''];
    }
    architectureCheck(
        FixtureBuilder::build($decks) === $case['fixtures'],
        'Legacy fixtures changed',
    );
    architectureCheck(
        RoundRobinPairings::rounds($decks) === $case['single'],
        'Single round order changed',
    );
    architectureCheck(
        RoundRobinPairings::rounds($decks, true) === $case['double'],
        'Return-leg order changed',
    );
    architectureCheck(
        KnockoutSize::forParticipants($case['count']) === $case['knockout'],
        'Bracket sizing changed',
    );
}

// The use case accepts a test double: it cannot depend on the PDO adapter or globals.
$repository = new class implements SeasonRepository {
    public int $reads = 0;
    public function newestFirst(): iterable
    {
        $this->reads++;
        yield new Season(3, 'Future draft', 'draft');
        yield new Season(2, '2027 B', 'active');
        yield new Season(1, '2026', 'active');
    }
};
architectureCheck((new GetCurrentSeason($repository))->execute()?->id === 2, 'Wrong active season');
architectureCheck($repository->reads === 1, 'Read port not used exactly once');
$loaded = new LoadedSeasonRepository([
    ['id' => 2, 'name' => '  ', 'status' => 'active'],
    ['id' => 1, 'name' => '2027 B', 'status' => 'active'],
]);
architectureCheck(
    (new GetCurrentSeason($loaded))->execute()?->name === '2027 B',
    'Blank active season not skipped',
);
architectureCheck(
    (new GetCurrentSeason(new LoadedSeasonRepository([])))->execute() === null,
    'No-season contract changed',
);
architectureCheck(
    !function_exists('ensure_ygo_tables'),
    'Loading the core must not initialize legacy persistence',
);

// Characterize season guards: missing season, closed status, inclusive boundaries,
// future play versus scheduling, invalid calendar dates and reversed ranges.
$seasonPolicy = \StationX\Yugioh\Domain\Seasons\SeasonSchedulePolicy::class;
$seasonRow = ['status' => 'active', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'];
$seasonOutcome = static function (callable $operation): ?string {
    try {
        $operation();
        return null;
    } catch (InvalidArgumentException $error) {
        return $error->getMessage();
    }
};
$closedError = 'La temporada está finalizada. Sus eventos están cerrados y solo permiten consulta.';
$missingError = 'El evento debe estar vinculado a una temporada existente.';
$dateError = 'Las fechas del evento deben estar dentro del período de su temporada.';
$openCases = [
    [null, false, '2026-01-01', $missingError],
    [$seasonRow, true, '2026-01-01', null],
    [$seasonRow, true, '2026-12-31', null],
    [$seasonRow, false, '2025-12-31', null],
    [$seasonRow, true, '2025-12-31', 'La temporada todavía no ha comenzado.'],
    [$seasonRow, false, '2027-01-01', $closedError],
    [array_replace($seasonRow, ['status' => 'completed']), false, '2026-01-01', $closedError],
    [array_replace($seasonRow, ['status' => 'archived']), false, '2026-01-01', $closedError],
];
foreach ($openCases as [$row, $playing, $today, $expected]) {
    architectureCheck(
        $seasonOutcome(static fn() => $seasonPolicy::requireOpen($row, $playing, $today)) ===
            $expected,
        'Season open guard changed',
    );
}
$dateCases = [
    [null, null, null],
    ['', '', null],
    ['2026-01-01', '2026-12-31', null],
    ['2026-02-29', null, $dateError],
    ['2025-12-31', null, $dateError],
    [null, '2027-01-01', $dateError],
    ['2026-12-31', '2026-01-01', 'El fin del evento no puede ser anterior a su inicio.'],
];
foreach ($dateCases as [$start, $end, $expected]) {
    architectureCheck(
        $seasonOutcome(static fn() => $seasonPolicy::validateEventDates($seasonRow, $start, $end)) ===
            $expected,
        'Season date validation changed',
    );
}
$seasonReader = new class implements \StationX\Yugioh\Application\Seasons\SeasonScheduleReader {
    public array $calls = [];
    public ?array $season = [
        'status' => 'active',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
    ];

    public function find(int $id, bool $lock = false): ?array
    {
        $this->calls[] = ['find', $id, $lock];
        return $this->season;
    }

    public function forCompetition(int $competitionId): ?array
    {
        $this->calls[] = ['competition', $competitionId];
        return $this->season;
    }

    public function matchDateRange(int $competitionId): array
    {
        $this->calls[] = ['range', $competitionId];
        return ['first_date' => '2026-01-01', 'last_date' => '2026-12-31'];
    }
};
$seasonClock = new class implements \StationX\Yugioh\Application\Shared\Clock {
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-26T12:00:00-05:00');
    }
};
$seasonUseCase = new \StationX\Yugioh\Application\Seasons\ValidateSeasonSchedule(
    $seasonReader,
    $seasonClock,
);
architectureCheck($seasonUseCase->requireOpen(7, true, true) === $seasonRow, 'Season row changed');
architectureCheck($seasonReader->calls === [['find', 7, true]], 'Season lock flag lost');
$seasonUseCase->validateCompetition(9);
architectureCheck(
    $seasonReader->calls === [['find', 7, true], ['competition', 9], ['range', 9]],
    'Season schedule query order changed',
);
$seasonReader->season = null;
$seasonReader->calls = [];
architectureCheck(
    $seasonOutcome(static fn() => $seasonUseCase->validateCompetition(9)) ===
        'El evento debe pertenecer a una temporada existente.',
    'Missing competition season error changed',
);
architectureCheck($seasonReader->calls === [['competition', 9]], 'Unexpected date range query');

// Season lifecycle uses ports and preserves owned/borrowed transaction selection.
$definitionPolicy = \StationX\Yugioh\Domain\Seasons\SeasonDefinition::class;
architectureCheck($definitionPolicy::end('2024-02-29', 'annual') === '2025-02-27', 'Leap day clamp');
architectureCheck($definitionPolicy::end('2026-01-31', 'quarterly') === '2026-04-29', 'Month end clamp');
$lifeStore = new class implements \StationX\Yugioh\Application\Seasons\SeasonLifecycleStore {
    public array $calls = [];
    public ?array $row = ['status' => 'active'];
    public bool $dependencies = false;

    public function find(int $id, bool $lock = false): ?array
    {
        $this->calls[] = ['find', $id, $lock];
        return $this->row;
    }
    public function hasOutsideDates(int $id, string $start, string $end): bool
    {
        return false;
    }
    public function insert(array $definition): int
    {
        $this->calls[] = ['insert'];
        return 7;
    }
    public function update(int $id, array $definition): void
    {
        $this->calls[] = ['update', $id];
    }
    public function openCompetitions(int $id): array
    {
        $this->calls[] = ['competitions', $id];
        return [['id' => 21, 'status' => 'active']];
    }
    public function closeCompetition(int $seasonId, array $competition, string $reason): void
    {
        $this->calls[] = ['close', $seasonId, $competition['id'], $reason];
    }
    public function completeSeason(int $id): void
    {
        $this->calls[] = ['complete', $id];
        $this->row = ['status' => 'completed'];
    }
    public function expiredIds(string $today): array
    {
        $this->calls[] = ['expired', $today];
        return [7];
    }
    public function hasDependencies(int $id): bool
    {
        return $this->dependencies;
    }
    public function deleteSeason(int $id): void
    {
        $this->calls[] = ['delete', $id];
    }
    public function audit(int $id, string $action, array $payload): void
    {
        $this->calls[] = ['audit', $id, $action, $payload];
    }
};
$lifeOwn = new class implements \StationX\Yugioh\Application\Shared\TransactionBoundary {
    public int $runs = 0;
    public function run(callable $operation): mixed
    {
        $this->runs++;
        return $operation();
    }
};
$lifeJoin = clone $lifeOwn;
$life = new \StationX\Yugioh\Application\Seasons\SeasonLifecycle($lifeStore, $lifeOwn, $lifeJoin, $seasonClock);
architectureCheck($life->close(7) === 1, 'Close count changed');
architectureCheck(
    $lifeStore->calls === [
        ['find', 7, true],
        ['competitions', 7],
        ['close', 7, 21, 'manual'],
        ['complete', 7],
        ['audit', 7, 'close_season', ['reason' => 'manual', 'closed_events' => 1]],
    ],
    'Close ordering or audit changed',
);
architectureCheck($life->close(7) === 0, 'Repeated close must be idempotent');
architectureCheck($lifeOwn->runs === 0 && $lifeJoin->runs === 2, 'Wrong close transaction boundary');
$lifeStore->row = ['status' => 'active'];
$lifeStore->calls = [];
$life->closeExpired();
architectureCheck($lifeStore->calls[0] === ['expired', '2026-11-26'], 'Expired date not injected');
architectureCheck($lifeStore->calls[3] === ['close', 7, 21, 'period_ended'], 'Expired reason changed');
$lifeStore->dependencies = true;
$lifeStore->calls = [];
architectureCheck(
    $seasonOutcome(static fn() => $life->delete(7)) ===
        'La temporada tiene eventos o divisiones asociados. Puedes finalizarla y conservar su historial.',
    'Delete dependency guard changed',
);
architectureCheck($lifeStore->calls === [['find', 7, true]], 'Blocked delete wrote data');
$lifeStore->dependencies = false;
$lifeStore->calls = [];
$life->delete(7);
architectureCheck(
    $lifeStore->calls === [['find', 7, true], ['delete', 7], ['audit', 7, 'delete_season', []]],
    'Delete ordering or audit changed',
);
$lifeStore->calls = [];
$lifeStore->row = ['status' => 'active'];
$saveInput = [
    'season_name' => 'Temporada',
    'season_period' => 'annual',
    'season_starts_on' => '2026-01-01',
    'season_finalize' => true,
];
architectureCheck($life->save($saveInput) === 7, 'Save identity changed');
architectureCheck(
    $lifeStore->calls[0] === ['insert'] &&
        $lifeStore->calls[1][2] === 'save_season' &&
        $lifeStore->calls[2] === ['find', 7, true],
    'Save and finalize ordering changed',
);

// Resolve season context with the same dependency, match, competition and division precedence.
$contextReader = new class implements \StationX\Yugioh\Application\Seasons\SeasonContextReader {
    public array $calls = [];
    public function competitionForDependency(int $id): int
    {
        $this->calls[] = ['dependency', $id];
        return 10;
    }
    public function competitionForMatch(int $id): int
    {
        $this->calls[] = ['match', $id];
        return 20;
    }
    public function seasonForCompetition(int $id): int
    {
        $this->calls[] = ['competition', $id];
        return 3;
    }
    public function seasonForDivision(int $id): int
    {
        $this->calls[] = ['division', $id];
        return 4;
    }
    public function exists(int $id): bool
    {
        $this->calls[] = ['exists', $id];
        return $id < 9;
    }
    public function defaultSeasonId(): int
    {
        $this->calls[] = ['default'];
        return 1;
    }
};
$context = new \StationX\Yugioh\Application\Seasons\ResolveSeasonContext($contextReader);
architectureCheck(
    $context->resolve(
        ['season_context' => 2],
        ['dependency_id' => 5, 'match_id' => 6, 'action' => 'save_division', 'division_id' => 7],
    ) === 4,
    'Season context precedence changed',
);
architectureCheck(
    $contextReader->calls === [
        ['dependency', 5],
        ['match', 6],
        ['competition', 20],
        ['division', 7],
        ['exists', 4],
    ],
    'Season context read order changed',
);
architectureCheck($context->resolve(['season_context' => 9], []) === null, 'Unknown season accepted');
architectureCheck($context->resolve([], ['competition_season_id' => 3]) === 3, 'Explicit season lost');
architectureCheck($context->current(4) === 4 && $context->current(null) === 1, 'Default season changed');
$seasonReader->season = $seasonRow;
$guard = new \StationX\Yugioh\Application\Seasons\GuardSeasonAction($seasonUseCase, $contextReader);
architectureCheck(
    $seasonOutcome(static fn() => $guard->execute('save_competition', 3, [])) ===
        'Selecciona la temporada del evento. La temporada de un evento existente se conserva.',
    'Competition season guard changed',
);
architectureCheck(
    $seasonOutcome(static fn() => $guard->execute('create_match', 3, [])) ===
        'Selecciona un evento con temporada para crear la partida.',
    'Manual match season guard changed',
);
architectureCheck(
    $seasonOutcome(static fn() => $guard->execute('save_competition', 3, [
        'competition_season_id' => 3,
        'competition_division_id' => 7,
    ])) === 'Las divisiones del evento deben pertenecer a su temporada.',
    'Division season guard changed',
);
architectureCheck(
    $seasonOutcome(static fn() => $guard->execute('save_competition', 4, [
        'competition_season_id' => 4,
        'competition_division_id' => 7,
    ])) === null,
    'Valid division blocked',
);

// Configured movement planning uses original divisions and only ranked decks with results.
$movementPlan = \StationX\Yugioh\Domain\Competitions\DivisionMovementPlan::class;
$movementPlans = [
    ['id' => 1, 'promotion_slots' => 0, 'relegation_slots' => 1, 'relegation_to_division_id' => 2],
    ['id' => 2, 'promotion_slots' => 1, 'promotion_to_division_id' => 1, 'relegation_slots' => 0],
];
$movementRanking = [
    ['id' => 10, 'pj' => 2],
    ['id' => 20, 'pj' => 2],
    ['id' => 11, 'played' => 1],
    ['id' => 21, 'pj' => 0],
];
$movementOriginal = [10 => 1, 11 => 1, 20 => 2, 21 => 2];
architectureCheck(
    $movementPlan::build($movementPlans, $movementRanking, $movementOriginal) === [
        ['deck_id' => 11, 'from_division_id' => 1, 'to_division_id' => 2, 'kind' => 'relegation'],
        ['deck_id' => 20, 'from_division_id' => 2, 'to_division_id' => 1, 'kind' => 'promotion'],
    ],
    'Configured division movement plan changed',
);
architectureCheck(
    $seasonOutcome(static fn() => $movementPlan::build($movementPlans, [], $movementOriginal)) ===
        'La división no tiene suficientes participantes con resultados para los cupos configurados.',
    'Insufficient ranked participants must block moves',
);

// Enforce inward dependencies, including code added after this refactor.
foreach (['Domain', 'Application'] as $layer) {
    foreach (
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__) . '/src/' . $layer),
        )
        as $file
    ) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
            if (!is_array($token)) {
                continue;
            }
            architectureCheck(
                !($token[0] === T_VARIABLE && str_starts_with($token[1], '$_')),
                'HTTP/session global in domain',
            );
            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = strtolower(ltrim($token[1], '\\'));
                architectureCheck($name !== 'pdo', 'PDO dependency in ' . $file->getPathname());
                architectureCheck(
                    !str_starts_with($name, 'ygo_'),
                    'Native helper dependency in ' . $file->getPathname(),
                );
                architectureCheck(
                    !in_array(
                        $name,
                        [
                            'file_get_contents',
                            'file_put_contents',
                            'fopen',
                            'readfile',
                            'file_exists',
                            'is_file',
                            'is_dir',
                            'realpath',
                            'scandir',
                            'glob',
                            'mkdir',
                            'unlink',
                            'getenv',
                            'session_start',
                            'header',
                            'curl_init',
                            'curl_exec',
                        ],
                        true,
                    ),
                    'Direct I/O in ' . $file->getPathname(),
                );
                architectureCheck(
                    !str_contains($name, '\\infrastructure\\'),
                    'Infrastructure dependency in ' . $file->getPathname(),
                );
                architectureCheck(
                    $layer !== 'Domain' || !str_contains($name, '\\application\\'),
                    'Application dependency in domain: ' . $file->getPathname(),
                );
            }
        }
    }
}

echo "OK: domain characterization, injected season port, no persistence startup, inward dependencies.\n";
