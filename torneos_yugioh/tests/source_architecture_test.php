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
        $seasonOutcome(
            static fn() => $seasonPolicy::validateEventDates($seasonRow, $start, $end),
        ) === $expected,
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
architectureCheck(
    $definitionPolicy::end('2024-02-29', 'annual') === '2025-02-27',
    'Leap day clamp',
);
architectureCheck(
    $definitionPolicy::end('2026-01-31', 'quarterly') === '2026-04-29',
    'Month end clamp',
);
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
$life = new \StationX\Yugioh\Application\Seasons\SeasonLifecycle(
    $lifeStore,
    $lifeOwn,
    $lifeJoin,
    $seasonClock,
);
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
architectureCheck(
    $lifeOwn->runs === 0 && $lifeJoin->runs === 2,
    'Wrong close transaction boundary',
);
$lifeStore->row = ['status' => 'active'];
$lifeStore->calls = [];
$life->closeExpired();
architectureCheck($lifeStore->calls[0] === ['expired', '2026-11-26'], 'Expired date not injected');
architectureCheck(
    $lifeStore->calls[3] === ['close', 7, 21, 'period_ended'],
    'Expired reason changed',
);
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
architectureCheck(
    $context->resolve(['season_context' => 9], []) === null,
    'Unknown season accepted',
);
architectureCheck(
    $context->resolve([], ['competition_season_id' => 3]) === 3,
    'Explicit season lost',
);
architectureCheck(
    $context->current(4) === 4 && $context->current(null) === 1,
    'Default season changed',
);
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
    $seasonOutcome(
        static fn() => $guard->execute('save_competition', 3, [
            'competition_season_id' => 3,
            'competition_division_id' => 7,
        ]),
    ) === 'Las divisiones del evento deben pertenecer a su temporada.',
    'Division season guard changed',
);
architectureCheck(
    $seasonOutcome(
        static fn() => $guard->execute('save_competition', 4, [
            'competition_season_id' => 4,
            'competition_division_id' => 7,
        ]),
    ) === null,
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

// League roster filtering keeps input order and only excludes other leagues of this season.
$rosterPolicy = \StationX\Yugioh\Domain\Competitions\LeagueRosterPolicy::class;
architectureCheck(
    $rosterPolicy::available([4, 3, 2, 1], 10, 5, [
        ['deck_id' => 4, 'season_id' => 5, 'competition_id' => 11],
        ['deck_id' => 3, 'season_id' => 6, 'competition_id' => 12],
        ['deck_id' => 2, 'season_id' => 5, 'competition_id' => 10],
    ]) === [3, 2, 1],
    'League roster exclusivity changed',
);
$leagueSchedule = \StationX\Yugioh\Domain\Competitions\LeagueSchedulePolicy::class;
architectureCheck(
    $leagueSchedule::plan([1 => [[11], [12]], 2 => [[21], [22]]], true) === [
        'groups' => [[11], [12], [21], [22]],
        'rounds' => [[[0, 1, 1, 1], [2, 3, 2, 1]], [[1, 0, 1, 1], [3, 2, 2, 1]]],
    ],
    'Division round order or offsets changed',
);
architectureCheck(
    $seasonOutcome(
        static fn() => $leagueSchedule::validateMembership([11], [1 => []], [11 => 2]),
    ) === 'Asigna cada participante a una division de esta liga antes de generar sus combates.',
    'Foreign division must be rejected',
);
architectureCheck(
    $seasonOutcome(static fn() => $leagueSchedule::requireCompleteGroups([[11]])) ===
        'Cada division con participantes necesita al menos dos lados completos para generar combates.',
    'Incomplete division accepted',
);

// Settings projection keeps overrides, defaults, null conversion and sorted UI labels.
$settingsReader = new class implements \StationX\Yugioh\Application\Settings\SettingsReader {
    public function settings(int $seasonId): array
    {
        return [
            ['setting_key' => 'a', 'setting_value' => null],
            ['setting_key' => 'c', 'setting_value' => $seasonId],
        ];
    }

    public function labels(int $seasonId): array
    {
        return [['id' => 9, 'label_key' => 'z', 'title' => null, 'active' => 0]];
    }
};
$readSettings = new \StationX\Yugioh\Application\Settings\ReadSettings($settingsReader);
architectureCheck(
    $readSettings->settings(7, ['a' => '1', 'b' => '2']) === ['a' => '', 'b' => '2', 'c' => '7'],
    'Settings override changed',
);
$projectedLabels = $readSettings->labels(7, [['z', 'Default', 'Default'], ['a', 'A', 'B']]);
architectureCheck(array_keys($projectedLabels) === ['a', 'z'], 'Label order changed');
architectureCheck(
    $projectedLabels['z'] === [
        'id' => 9,
        'key' => 'z',
        'title' => '',
        'subtitle' => '',
        'active' => 0,
    ],
    'Persisted label must take precedence',
);
architectureCheck(
    $projectedLabels['a'] === [
        'id' => 0,
        'key' => 'a',
        'title' => 'A',
        'subtitle' => 'B',
        'active' => 1,
    ],
    'Missing label fallback changed',
);
$teamStore = new class implements \StationX\Yugioh\Application\Teams\TeamStore {
    public array $calls = [];
    public int $id = 4;

    public function findId(string $name): int
    {
        $this->calls[] = ['find', $name];
        return $this->id;
    }

    public function activate(int $id): void
    {
        $this->calls[] = ['activate', $id];
    }

    public function updateProfile(int $id, array $profile): void
    {
        $this->calls[] = ['profile', $id, $profile];
    }

    public function create(string $name, ?array $profile): int
    {
        $this->calls[] = ['create', $name, $profile];
        return 17;
    }
};
$findTeam = new \StationX\Yugioh\Application\Teams\FindOrCreateTeam($teamStore);
architectureCheck($findTeam->execute('A') === 4, 'Existing identity changed');
architectureCheck(
    $teamStore->calls === [['find', 'A'], ['activate', 4]],
    'Existing activation changed',
);
$teamStore->calls = [];
$profile = ['code' => 'ABC'];
architectureCheck($findTeam->execute('A', $profile) === 4, 'Existing profile identity changed');
architectureCheck(
    $teamStore->calls === [['find', 'A'], ['activate', 4], ['profile', 4, $profile]],
    'Existing profile write order changed',
);
$teamStore->id = 0;
$teamStore->calls = [];
architectureCheck($findTeam->execute('B', null) === 17, 'New identity changed');
architectureCheck(
    $teamStore->calls === [['find', 'B'], ['create', 'B', null]],
    'Creation order changed',
);
$teamProfile = \StationX\Yugioh\Domain\Teams\TeamProfile::forDeck('ignored', '  Crystron ');
architectureCheck($teamProfile['name'] === 'Quantum Sentinels', 'Team profile mapping changed');
$deckTeamStore = new class implements \StationX\Yugioh\Application\Teams\DeckTeamStore {
    public array $calls = [];

    public function activeDecks(): array
    {
        return [
            ['id' => 1, 'team_id' => 6, 'name' => 'Keep'],
            ['id' => 2, 'team_id' => null, 'name' => 'Crystron'],
        ];
    }

    public function assign(int $deckId, int $teamId): void
    {
        $this->calls[] = ['assign', $deckId, $teamId];
    }

    public function archiveUnusedLegacyTeams(): void
    {
        $this->calls[] = ['archive'];
    }
};
$syncTeams = new \StationX\Yugioh\Application\Teams\SyncDeckTeams($deckTeamStore, $findTeam);
architectureCheck($syncTeams->execute() === 1, 'Team sync count changed');
architectureCheck(
    $deckTeamStore->calls === [['assign', 2, 17], ['archive']],
    'Team sync overwrote an existing assignment or changed archive order',
);

// Preserve the legacy league points, scores and ordering independently of SQL.
$standings = \StationX\Yugioh\Domain\Competitions\LeagueStandings::calculate(
    [
        ['id' => 1, 'name' => 'A', 'seed_rank' => 2, 'division_code' => 'X'],
        ['id' => 2, 'name' => 'B', 'seed_rank' => 1, 'division_code' => 'X'],
        ['id' => 3, 'name' => 'C', 'seed_rank' => 3, 'division_code' => 'X'],
    ],
    [
        ['deck_a_id' => 1, 'deck_b_id' => 2, 'result' => 'A', 'score' => '2-1'],
        ['deck_a_id' => 2, 'deck_b_id' => 3, 'result' => 'E', 'score' => '1-1'],
        ['deck_a_id' => 1, 'deck_b_id' => 99, 'result' => 'B', 'score' => '0-2'],
    ],
);
architectureCheck(array_column($standings, 'id') === [1, 3, 2], 'Standings ordering changed');
architectureCheck(array_column($standings, 'pts') === [3, 1, 1], 'Standings points changed');
architectureCheck(
    array_column($standings, 'dp') === [1, 0, -1],
    'Standings score difference changed',
);
architectureCheck(array_column($standings, 'pj') === [1, 1, 2], 'Unknown participant not skipped');
$rankDefinitions = [
    'first' => ['short' => 'A', 'size' => 2],
    'last' => ['short' => 'B', 'size' => 3],
];
$rankPolicy = \StationX\Yugioh\Domain\Competitions\DivisionRankPolicy::class;
architectureCheck($rankPolicy::category(2, $rankDefinitions) === 'A', 'Rank boundary changed');
architectureCheck($rankPolicy::category(3, $rankDefinitions) === 'B', 'Rank division changed');
architectureCheck($rankPolicy::category(99, $rankDefinitions) === 'B', 'Rank fallback changed');
architectureCheck(key($rankDefinitions) === 'last', 'Legacy cursor behavior changed');

// Match validation retains error precedence, position mapping and team restrictions.
$matchDeckPolicy = \StationX\Yugioh\Domain\Decks\MatchDeckPolicy::class;
architectureCheck(
    $matchDeckPolicy::selection([], [1, 1])['message'] ===
        'No hay decks permitidos para el formato del evento.',
    'Empty pool precedence changed',
);
architectureCheck(
    $matchDeckPolicy::selection([1], [1, 2])['message'] ===
        'Hay decks que no pertenecen al formato/pool del evento.',
    'Foreign deck accepted',
);
architectureCheck(
    $matchDeckPolicy::selection([1], [1, 1])['message'] ===
        'No se puede repetir un deck en el mismo match.',
    'Duplicate deck accepted',
);
architectureCheck(
    $matchDeckPolicy::selection([1], [])['message'] ===
        'Debes seleccionar decks para crear el match.',
    'Empty selection changed',
);
$matchRows = [
    ['id' => 1, 'division_id' => 7, 'team_id' => 10],
    ['id' => 2, 'division_id' => 7, 'team_id' => 20],
    ['id' => 3, 'division_id' => 7, 'team_id' => 10],
    ['id' => 4, 'division_id' => 7, 'team_id' => 20],
];
architectureCheck(
    $matchDeckPolicy::validateRows($matchRows, [1, 2, 3, 4], [1, 2, 3, 4], 7, 2) === [
        'ok' => true,
        'message' => 'OK',
    ],
    'Valid 2v2 blocked',
);
architectureCheck(
    !$matchDeckPolicy::validateRows($matchRows, [1, 2, 3, 4], [1, 3, 2, 4], 7, 2)['ok'],
    'Mixed team accepted',
);
architectureCheck(
    !$matchDeckPolicy::validateRows($matchRows, [1, 2, 3, 4], [1, 2, 3, 4], 8, 2)['ok'],
    'Foreign division accepted',
);
$rosterGrouping = \StationX\Yugioh\Domain\Decks\RosterGroupingPolicy::class;
architectureCheck(
    $rosterGrouping::normalize([0, '1', 1, 2, -1]) === [1, 2],
    'Roster normalization changed',
);
architectureCheck(
    $rosterGrouping::group([4, 1, 3, 2], 2, $matchRows) === [[4, 2], [1, 3]],
    'Team group ordering changed',
);

// Competition gate: preserve dependency short circuits and the legacy messages.
$dependencyFacts = new class implements
    \StationX\Yugioh\Application\Competitions\CompetitionDependencyFacts
{
    public array $calls = [];
    public function resultCount(int $competitionId): int
    {
        $this->calls[] = 'results';
        return 2;
    }
    public function honorCount(int $competitionId): int
    {
        $this->calls[] = 'honors';
        return 1;
    }
    public function divisionCount(int $competitionId): int
    {
        $this->calls[] = 'divisions';
        return 0;
    }
    public function standings(int $competitionId): array
    {
        $this->calls[] = 'standings';
        return [1, 2];
    }
};
$dependencyEvaluator = new \StationX\Yugioh\Application\Competitions\EvaluateCompetitionDependency(
    $dependencyFacts,
);
$dependency = ['condition_type' => 'top_n', 'depends_on_competition_id' => 2, 'take_count' => 2];
architectureCheck(
    !$dependencyEvaluator->execute($dependency, false, true) && !$dependencyFacts->calls,
    'Incomplete prerequisite must short circuit',
);
architectureCheck(
    $dependencyEvaluator->execute($dependency, true, true) &&
        $dependencyFacts->calls === ['standings'],
    'Top-N prerequisite changed',
);
$dependencyFacts->calls = [];
$dependency['condition_type'] = 'winner_to_final';
architectureCheck(
    $dependencyEvaluator->execute($dependency, true, true) &&
        $dependencyFacts->calls === ['honors'],
    'Winner honor must short circuit standings',
);
$dependency['condition_type'] = 'creates_divisions';
architectureCheck(
    !$dependencyEvaluator->execute($dependency, true, true),
    'Missing divisions accepted',
);
$competitionGatePolicy = \StationX\Yugioh\Domain\Competitions\CompetitionGatePolicy::class;
architectureCheck(
    $competitionGatePolicy::seasonOpen(
        ['status' => 'active', 'ends_on' => '2026-11-26'],
        '2026-11-26',
    ),
    'Season closing date must be inclusive',
);
architectureCheck(
    !$competitionGatePolicy::seasonOpen(['status' => 'archived'], '2026-11-26'),
    'Archived season accepted',
);
architectureCheck(
    $competitionGatePolicy::summarize([])['message'] === 'Sin prerrequisitos.',
    'Empty prerequisite response changed',
);
architectureCheck(
    $competitionGatePolicy::summarize([
        ['name' => 'A', 'ok' => false],
        ['name' => 'B', 'ok' => true],
    ])['message'] === 'Evento bloqueado: primero debe cumplirse A.',
    'Blocked prerequisite message changed',
);

// Date and venue policies preserve configured limits and legacy result states.
$schedulePolicy = \StationX\Yugioh\Domain\Calendar\MatchSchedulePolicy::class;
$dateSettings = ['allow_early_results' => '0', 'allow_overdue_results' => '1'];
architectureCheck(
    $schedulePolicy::resultDate('2026-11-27', '2026-11-26', $dateSettings)['state'] ===
        'fecha_futura',
    'Early result guard changed',
);
architectureCheck(
    $schedulePolicy::resultDate('2026-11-25', '2026-11-26', $dateSettings)['state'] === 'vencido',
    'Overdue result changed',
);
architectureCheck(
    $schedulePolicy::resultDate('2026-11-26', '2026-11-26', $dateSettings)['state'] === 'hoy',
    'Current date result changed',
);
$dateSettings['allow_overdue_results'] = '0';
architectureCheck(
    $schedulePolicy::resultDate('2026-11-25', '2026-11-26', $dateSettings)['state'] ===
        'vencido_bloqueado',
    'Blocked overdue result changed',
);
$parsedSchedule = new DateTimeImmutable('2026-11-26');
$scheduleSettings = [
    'valid_weekdays' => '4',
    'allowed_start_time' => '10:00',
    'allowed_end_time' => '22:00',
    'venues' => 'Station X',
    'max_matches_per_day' => '0',
];
architectureCheck(
    $schedulePolicy::dayAndTime($parsedSchedule, '2026-11-26', '10:00', $scheduleSettings) === null,
    'Inclusive start time changed',
);
architectureCheck(
    $schedulePolicy::dayAndTime($parsedSchedule, '2026-11-26', '09:59', $scheduleSettings)[
        'state'
    ] === 'hora_temprana',
    'Early time accepted',
);
architectureCheck(
    $schedulePolicy::dayAndTime($parsedSchedule, '2026-11-26', '22:01', $scheduleSettings)[
        'state'
    ] === 'hora_tardia',
    'Late time accepted',
);
architectureCheck(
    $schedulePolicy::venue('station x', $scheduleSettings)['ok'],
    'Case insensitive venue changed',
);
architectureCheck(
    $schedulePolicy::venue('Other', $scheduleSettings)['state'] === 'sede_no_permitida',
    'Foreign venue accepted',
);
architectureCheck(
    $schedulePolicy::maxPerDay($scheduleSettings) === 1,
    'Minimum daily capacity changed',
);

// Match model rules remain independent of SQL and preserve configured match scope.
$eventResult = \StationX\Yugioh\Domain\Matches\EventResultPolicy::class;
$matchRule = \StationX\Yugioh\Domain\Matches\MatchRulePolicy::class;
architectureCheck(
    $matchRule::bestOf(0) === 3 && $matchRule::bestOf(9) === 5,
    'Best-of fallback changed',
);
architectureCheck(
    $matchRule::scopes('friendly') === ['all_matches', 'friendly', 'match', 'general'],
    'Friendly scopes changed',
);
architectureCheck(
    $matchRule::scopes('league') === ['all_matches', 'match', 'general'],
    'Match scopes changed',
);
$draw = ['status' => 'FINALIZADA', 'winner' => 'E'];
architectureCheck(
    $eventResult::apply([], '', null, $draw) === $draw + ['model_effects' => []],
    'No event result changed',
);
architectureCheck(
    $eventResult::apply(['type' => 'cup'], 'cup', null, $draw)['status'] === 'INVALID',
    'Cup draw accepted',
);
architectureCheck(
    $eventResult::apply(['type' => 'league', 'draw_policy' => 'ALLOWED'], 'league', null, $draw)[
        'winner'
    ] === 'E',
    'League draw rejected',
);
$win = ['status' => 'FINALIZADA', 'winner' => 'A'];
architectureCheck(
    $eventResult::apply(['type' => 'special'], 'manual', null, $win, ['A', 'B', 'A', 'A'])[
        'status'
    ] === 'REQUIERE D5',
    'D5 policy changed',
);
architectureCheck(
    $eventResult::apply(['type' => 'world', 'draw_policy' => 'ALLOWED'], 'groups', [], $draw)[
        'winner'
    ] === 'E',
    'Workflow group draw rejected',
);

// Competition lifecycle preserves completion guards, status normalization and code numbering.
$lifecyclePolicy = \StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy::class;
$codePolicy = \StationX\Yugioh\Domain\Competitions\CompetitionCodePolicy::class;
architectureCheck(
    $lifecyclePolicy::normalizeStatus(' Finalizada ') === 'finalizada',
    'Status normalization changed',
);
architectureCheck(
    $lifecyclePolicy::history(1, 'Activo', ' activo ') === null,
    'Duplicate transition accepted',
);
architectureCheck(
    $lifecyclePolicy::history(1, null, 'Activo') === ['from' => null, 'to' => 'activo'],
    'Initial history changed',
);
architectureCheck(
    !$lifecyclePolicy::finishCounts(['total_matches' => 0, 'finalized_matches' => 0])['ok'],
    'Empty competition closed',
);
architectureCheck(
    $lifecyclePolicy::finishCounts(['total_matches' => 2, 'finalized_matches' => 2])['ok'],
    'Completed competition blocked',
);
architectureCheck(
    $lifecyclePolicy::unfinishedWorkflow([
        'complete' => false,
        'history' => [1, 2],
        'pending' => [3],
    ])['pending'] === 1,
    'Workflow completion count changed',
);
architectureCheck(
    $lifecyclePolicy::pendingQualifier([
        'type' => 'qualifier',
        'status' => 'pending_configuration',
        'deck_pool_count' => 4,
    ]),
    'Qualifier candidate changed',
);
architectureCheck(
    $codePolicy::nextCounter(['YU-01', 'YU-009', 'manual']) === 10,
    'Code sequence changed',
);
architectureCheck(
    iterator_to_array(
        $codePolicy::replacements([
            ['id' => 1, 'code' => 'YU-01'],
            ['id' => 2, 'code' => 'old'],
            ['id' => 3, 'code' => 'YU-9'],
        ]),
        false,
    ) === [[2, 'YU-10'], [3, 'YU-11']],
    'Legacy code migration changed',
);

// Standing and calendar projection payloads preserve position, source and stable event identity.
$snapshotPolicy = \StationX\Yugioh\Domain\Competitions\StandingSnapshotPolicy::class;
architectureCheck(
    $snapshotPolicy::scopes(['', 'league', '0', 'cup', 'league']) === [
        'current' => null,
        'phase:league' => 'league',
        'phase:cup' => 'cup',
    ],
    'Snapshot scope changed',
);
$snapshotRows = iterator_to_array(
    $snapshotPolicy::rows([['id' => 0], ['id' => 5, 'pts' => 3, 'pj' => 1]], 'current', null),
    false,
);
architectureCheck(
    count($snapshotRows) === 1 && $snapshotRows[0][0] === 5 && $snapshotRows[0][1] === 2,
    'Snapshot position or invalid deck changed',
);
architectureCheck(
    $snapshotRows[0][10] === [
        'reason' => 'current',
        'phase' => null,
        'row' => ['id' => 5, 'pts' => 3, 'pj' => 1],
    ],
    'Snapshot payload changed',
);
$calendarProjection = \StationX\Yugioh\Domain\Calendar\CompetitionCalendarProjection::class;
$journeys = iterator_to_array(
    $calendarProjection::journeys(
        7,
        'league',
        ['division_id' => null, 'name' => 'Liga'],
        [['scheduled_on' => '2026-11-27', 'starts_at' => null, 'venue' => '', 'match_count' => 2]],
    ),
    false,
);
architectureCheck(
    $journeys === [
        [
            null,
            'competition-7-league-jornada-1',
            'Liga - Jornada 1',
            'league',
            '2026-11-27',
            null,
            'Station X Tulua',
            '2 combates programados desde duelos persistidos.',
        ],
    ],
    'Calendar projection changed',
);
architectureCheck(
    $calendarProjection::startCode(7) === 'competition-7-inicio',
    'Start event code changed',
);

// Competition standings preserve scoring modes, workflow order and team projection.
$competitionStandings = \StationX\Yugioh\Domain\Competitions\CompetitionStandingsPolicy::class;
$standingDecks = [
    [
        'id' => 1,
        'name' => 'A',
        'seed_rank' => 2,
        'team' => 'A',
        'team_code' => '',
        'team_primary_color' => '',
        'team_secondary_color' => '',
    ],
    [
        'id' => 2,
        'name' => 'B',
        'seed_rank' => 1,
        'team' => 'B',
        'team_code' => '',
        'team_primary_color' => '',
        'team_secondary_color' => '',
    ],
];
$standingMatches = [['deck_a_id' => 1, 'deck_b_id' => 2, 'result' => 'A', 'score' => '2-1']];
architectureCheck(
    array_column(
        $competitionStandings::calculate($standingDecks, $standingMatches, 'FULL', []),
        'pts',
    ) === [3, 0],
    'Full points changed',
);
architectureCheck(
    array_column(
        $competitionStandings::calculate($standingDecks, $standingMatches, 'REDUCED', []),
        'pts',
    ) === [1, 0],
    'Reduced points changed',
);
architectureCheck(
    array_column(
        $competitionStandings::calculate($standingDecks, $standingMatches, 'BONUS', []),
        'pts',
    ) === [4, 0],
    'Bonus points changed',
);
architectureCheck(
    $competitionStandings::excluded(['level' => 'CASUAL'], []),
    'Casual exclusion changed',
);
$workflowRows = [
    ['id' => 2, 'points' => 6, 'buchholz' => 3],
    ['id' => 1, 'points' => 2, 'buchholz' => 1],
];
$workflowStanding = $competitionStandings::calculate(
    $standingDecks,
    $standingMatches,
    'FULL',
    $workflowRows,
);
architectureCheck(
    array_column($workflowStanding, 'id') === [2, 1] &&
        array_column($workflowStanding, 'pts') === [6, 2],
    'Workflow ordering changed',
);
architectureCheck(
    !$competitionStandings::excluded(['level' => 'CASUAL'], $workflowRows),
    'Workflow ranking unexpectedly excluded',
);
architectureCheck(
    $competitionStandings::phase(['execution_model' => 'groups_knockout'], $workflowRows, null) ===
        'groups',
    'Workflow phase changed',
);
$teamStanding = $competitionStandings::teams($workflowStanding, [
    'size' => 2,
    'lineups' => [['captain' => 2, 'name' => 'Team B', 'decks' => [2, 3]]],
]);
architectureCheck(
    count($teamStanding) === 1 &&
        $teamStanding[0]['name'] === 'Team B' &&
        $teamStanding[0]['member_deck_ids'] === [2, 3],
    'Team standings changed',
);

// Competition deletion uses a fake store: no physical data is modified.
$deletionStore = new class implements
    \StationX\Yugioh\Application\Competitions\CompetitionDeletionStore
{
    public array $calls = [];
    public array $row = ['id' => 2, 'status' => 'draft'];

    public function currentSeasonId(): int
    {
        $this->calls[] = ['season'];
        return 3;
    }

    public function competition(int $seasonId, int $competitionId): array
    {
        $this->calls[] = ['read', $seasonId, $competitionId];
        return $this->row;
    }

    public function delete(int $seasonId, int $competitionId, string $status): void
    {
        $this->calls[] = ['delete', $seasonId, $competitionId, $status];
    }
};
$deletionTransaction = new class implements \StationX\Yugioh\Application\Shared\TransactionBoundary
{
    public int $runs = 0;

    public function run(callable $operation): mixed
    {
        $this->runs++;
        return $operation();
    }
};
$deleteCompetition = new \StationX\Yugioh\Application\Competitions\DeleteCompetition(
    $deletionStore,
    $deletionTransaction,
);
architectureCheck(
    $deleteCompetition->execute(2, 'no') === 0 &&
        !$deletionStore->calls &&
        $deletionTransaction->runs === 0,
    'Unconfirmed deletion did I/O',
);
$deletionStore->row = ['status' => 'active'];
architectureCheck(
    $deleteCompetition->execute(2, 'eliminar') === 0 && $deletionTransaction->runs === 0,
    'Active competition accepted',
);
$deletionStore->calls = [];
$deletionStore->row = ['status' => 'draft'];
architectureCheck($deleteCompetition->execute(2, ' ELIMINAR ') === 2, 'Confirmed draft rejected');
architectureCheck(
    $deletionStore->calls === [['season'], ['read', 3, 2], ['delete', 3, 2, 'draft']] &&
        $deletionTransaction->runs === 1,
    'Deletion order or transaction changed',
);

// Competition configuration policy preserves roster lock, minimum sides and status transitions.
$configurationPolicy = \StationX\Yugioh\Domain\Competitions\CompetitionConfigurationPolicy::class;
architectureCheck(
    $configurationPolicy::rosterLocked(true, [], 'draft', 'league'),
    'Existing fixtures must lock roster',
);
architectureCheck(
    !$configurationPolicy::rosterLocked(false, [], 'active', 'cup'),
    'Active cup legacy rule changed',
);
architectureCheck(
    $configurationPolicy::rosterLocked(false, [], 'active', 'league'),
    'Active league roster unlocked',
);
architectureCheck(
    $configurationPolicy::readiness('qualifier', [[1], [2], [3], [4]], 4, 1, 5, null) === [
        'qualifierDuelsPerDeck' => 3,
        'canRunCompetition' => true,
    ],
    'Qualifier capacity changed',
);
architectureCheck(
    !$configurationPolicy::readiness('league', [[1], [2]], 2, 1, 3, null)['canRunCompetition'],
    'League without division allowed',
);
architectureCheck(
    $configurationPolicy::transition('cancelled', 'league', true, true, 'start', 0) === [
        'status' => 'cancelled',
        'mode' => 'save',
    ],
    'Cancelled event starts',
);
architectureCheck(
    $configurationPolicy::transition('draft', 'qualifier', true, false, 'start', 1)['status'] ===
        'pending_event',
    'Qualifier start guard changed',
);
architectureCheck(
    $configurationPolicy::transition('draft', 'cup', true, true, 'start', 0)['status'] === 'active',
    'Event start status changed',
);

// Division maintenance policies and ports: no live decks are modified.
$qualifierDivisionPolicy = \StationX\Yugioh\Domain\Competitions\QualifierDivisionPolicy::class;
architectureCheck(
    $qualifierDivisionPolicy::range([
        'source_qualifier_id' => 5,
        'rank_from' => 4,
        'rank_to' => 2,
    ]) === [5, 2, 4],
    'Rank range changed',
);
architectureCheck(
    $qualifierDivisionPolicy::range(['source_qualifier_id' => 0]) === null,
    'Missing qualifier accepted',
);
architectureCheck(
    $qualifierDivisionPolicy::completed('Finalizada') &&
        !$qualifierDivisionPolicy::completed('active'),
    'Qualifier status changed',
);
architectureCheck(
    iterator_to_array(
        $qualifierDivisionPolicy::assignments([['deck_id' => 7], ['id' => 0], ['id' => 8]]),
        false,
    ) === [[7, 1], [8, 3]],
    'Qualifier positions changed',
);
$normalizationStore = new class implements
    \StationX\Yugioh\Application\Decks\LegacyFormatNormalizationStore
{
    public bool $done = true;
    public int $division = 0;
    public array $calls = [];
    public function alreadyNormalized(): bool
    {
        $this->calls[] = 'check';
        return $this->done;
    }
    public function currentTcgDivision(): int
    {
        $this->calls[] = 'division';
        return $this->division;
    }
    public function normalize(int $divisionId): int
    {
        $this->calls[] = ['normalize', $divisionId];
        return 4;
    }
    public function record(int $divisionId, int $updated): void
    {
        $this->calls[] = ['audit', $divisionId, $updated];
    }
};
$normalizationUseCase = new \StationX\Yugioh\Application\Decks\NormalizeLegacyDeckFormat(
    $normalizationStore,
);
architectureCheck(
    $normalizationUseCase->execute() === 0 && $normalizationStore->calls === ['check'],
    'Repeated normalization wrote data',
);
$normalizationStore->done = false;
$normalizationStore->calls = [];
architectureCheck(
    $normalizationUseCase->execute() === 0 && $normalizationStore->calls === ['check', 'division'],
    'Missing division wrote data',
);
$normalizationStore->division = 9;
$normalizationStore->calls = [];
architectureCheck(
    $normalizationUseCase->execute() === 4 &&
        $normalizationStore->calls === ['check', 'division', ['normalize', 9], ['audit', 9, 4]],
    'Normalization order or audit changed',
);

// Legacy qualifier pairing and the last-slot consolidation remain deterministic.
$legacyPairs = \StationX\Yugioh\Domain\Calendar\LegacyQualifierPairings::build([1, 2, 3, 4]);
architectureCheck(count($legacyPairs) === 6, 'Four-deck qualifier changed');
$legacyCalendar = new \StationX\Yugioh\Domain\Calendar\LegacyQualifierCalendar(
    '2026-11-26',
    '19:00',
    2,
    1,
    45,
    new DateTimeImmutable('2026-11-26 19:00:00'),
);
$legacyFixtureRows = iterator_to_array(
    $legacyCalendar->fixtures(array_slice($legacyPairs, 0, 5)),
    false,
);
architectureCheck(
    $legacyFixtureRows[4]['date'] === '2026-11-27' && $legacyFixtureRows[4]['time'] === '20:30:00',
    'Last qualifier slot changed',
);
architectureCheck(
    $legacyCalendar->days(5) === ['2026-11-26' => 2, '2026-11-27' => 3],
    'Qualifier day projection changed',
);
architectureCheck(
    $legacyCalendar->endDate($legacyCalendar->days(5), 5) === '2026-11-27',
    'Qualifier end date changed',
);

// Fixture generation retains alternating cup sides, league table identities and slot dates.
$fixturePlan = \StationX\Yugioh\Domain\Calendar\FixtureGenerationPlan::class;
$cupRows = iterator_to_array(
    $fixturePlan::cupRows(
        [[1], [2], [3], [4], [5]],
        null,
        8,
        ['best_of' => 3],
        '2026-11-26',
        'consecutive',
        '1v1',
        1,
        2,
        2,
    ),
    false,
);
architectureCheck(count($cupRows) === 2, 'Odd cup roster lost bye');
architectureCheck(
    $cupRows[0]['deck_a_id'] === 5 && $cupRows[0]['deck_b_id'] === 1,
    'Cup side alternation changed',
);
architectureCheck(
    $cupRows[1]['deck_a_id'] === 2 && $cupRows[1]['deck_b_id'] === 4,
    'Second cup side changed',
);
architectureCheck($cupRows[1]['starts_at'] === '19:45:00', 'Cup slot time changed');
$leagueFixturePlan = $fixturePlan::rounds(
    [[1], [2]],
    ['groups' => [[3], [4]], 'rounds' => [[[0, 1, 9, 2]]]],
    'round_robin',
    'league',
);
$leagueFixtureRows = iterator_to_array(
    $fixturePlan::modelRows(
        $leagueFixturePlan['groups'],
        $leagueFixturePlan['rounds'],
        1,
        8,
        ['best_of' => 3],
        '2026-11-26',
        'consecutive',
        '1v1',
        1,
        2,
        1,
        $leagueFixturePlan['phase'],
        'round_robin',
    ),
    false,
);
architectureCheck(
    count($leagueFixtureRows) === 1 &&
        $leagueFixtureRows[0]['division_id'] === 9 &&
        $leagueFixtureRows[0]['table_number'] === 2,
    'League fixture division/table changed',
);
architectureCheck(
    $leagueFixtureRows[0]['deck_a_id'] === 3 && $leagueFixtureRows[0]['deck_b_id'] === 4,
    'League groups changed',
);

// Legacy event fixture plans retain the old side order, rounds, and table numbering.
$legacyEventPlan = \StationX\Yugioh\Domain\Competitions\LegacyEventFixturePlan::class;
$eventFixtureDecks = [
    ['id' => 1, 'name' => 'A', 'seed_rank' => 1],
    ['id' => 2, 'name' => 'B', 'seed_rank' => 2],
    ['id' => 3, 'name' => 'C', 'seed_rank' => 3],
    ['id' => 4, 'name' => 'D', 'seed_rank' => 4],
];
architectureCheck(
    count(iterator_to_array($legacyEventPlan::league($eventFixtureDecks), false)) === 6,
    'Legacy league round count changed',
);
architectureCheck(
    iterator_to_array($legacyEventPlan::knockout($eventFixtureDecks), false) === [
        [1, 'Ronda 1', 1, 1, 1, 4],
        [2, 'Ronda 1', 2, 2, 2, 3],
    ],
    'Legacy knockout pair order changed',
);
architectureCheck(
    count(iterator_to_array($legacyEventPlan::world($eventFixtureDecks), false)) === 6,
    'World group pair count changed',
);
$legacyFriendlyRows = iterator_to_array($legacyEventPlan::friendlies($eventFixtureDecks), false);
architectureCheck(
    count($legacyFriendlyRows) === 4 && $legacyFriendlyRows[0] === [1, 1, 1, 2],
    'Legacy friendly pair order changed',
);

// Phase advancement preserves winner validation and completion guards.
$phasePolicy = \StationX\Yugioh\Domain\Competitions\PhaseAdvancePolicy::class;
architectureCheck(
    !$phasePolicy::finished(['total' => 0, 'closed' => 0]),
    'Empty phase marked closed',
);
architectureCheck($phasePolicy::finished(['total' => 2, 'closed' => 2]), 'Complete phase blocked');
architectureCheck(
    !$phasePolicy::finished(['total' => 2, 'closed' => 1]),
    'Pending phase marked closed',
);
architectureCheck(
    $phasePolicy::winners([
        ['result' => 'A', 'deck_a_id' => 3, 'deck_b_id' => 4],
        ['result' => 'B', 'deck_a_id' => 5, 'deck_b_id' => 6],
    ]) === [3, 6],
    'Winner order changed',
);
architectureCheck(
    $phasePolicy::blockedQualifier(
        ['event_flow' => 'QUALIFIES_TO_EVENT', 'advancement_rule' => 'RANKING_THRESHOLD'],
        'qualifier',
        'swiss',
        'NONE',
    ),
    'Division qualifier gained a phase',
);

// Qualifier finalization and honor selection retain legacy allocation and precedence.
$finalizationPolicy = \StationX\Yugioh\Domain\Competitions\QualifierFinalizationPolicy::class;
architectureCheck(
    $finalizationPolicy::requiredMatches(0) === 3 && $finalizationPolicy::requiredMatches(9) === 5,
    'Qualifier minimum changed',
);
architectureCheck(
    $finalizationPolicy::roundReasons([['round_number' => 1, 'status' => 'FINALIZADA']]) === [],
    'Closed first round blocked',
);
architectureCheck(
    count(
        $finalizationPolicy::roundReasons([
            ['round_number' => 1, 'status' => 'FINALIZADA'],
            ['round_number' => 1, 'status' => 'PENDIENTE'],
        ]),
    ) === 2,
    'Partial round guard changed',
);
architectureCheck(
    !$finalizationPolicy::result([], [['pj' => 2]], 3)['ok'],
    'Insufficient match count accepted',
);
$allocation = iterator_to_array(
    $finalizationPolicy::divisions(
        [['id' => 1], ['id' => 2]],
        [['id' => 7], ['id' => 8], ['id' => 9]],
    ),
    false,
);
architectureCheck(
    array_column($allocation, 'size') === [2, 1] &&
        $allocation[1]['assignments'] === [['deckId' => 9, 'rank' => 1]],
    'Qualifier allocation changed',
);
$honorPolicy = \StationX\Yugioh\Domain\Competitions\CompetitionHonorPolicy::class;
architectureCheck(
    $honorPolicy::usesFinal(['type' => 'cup']) && !$honorPolicy::usesFinal(['type' => 'league']),
    'Honor final precedence changed',
);
architectureCheck(
    $honorPolicy::fromFinal(['result' => 'B', 'deck_a_id' => 1, 'deck_b_id' => 2]) === [2, 1],
    'Final winner changed',
);
architectureCheck($honorPolicy::fromRanking([['id' => 4]]) === [4, 0], 'Missing runner changed');

// Knockout and historical phase plans preserve ordering and bye positions.
$knockoutPlan = \StationX\Yugioh\Domain\Competitions\KnockoutRoundPlan::class;
$knockoutIds = $knockoutPlan::normalize([1, '2', 2, 3, 4, 5, 0]);
$knockoutRows = iterator_to_array(
    $knockoutPlan::rows(['id' => 9], 'cup', $knockoutIds, 2, 'Final', '2026-11-26'),
    false,
);
architectureCheck(
    array_column($knockoutRows, 'deckA') === [1, 2] &&
        array_column($knockoutRows, 'deckB') === [5, 4],
    'Knockout pairs changed',
);
architectureCheck(
    $knockoutRows[0]['displayOrder'] === 201 && $knockoutRows[0]['date'] === '2026-11-27',
    'Knockout display/date changed',
);
architectureCheck(
    $knockoutPlan::bye('cup', $knockoutIds) === 3 &&
        $knockoutPlan::bye('world', $knockoutIds) === null,
    'Bye policy changed',
);
$historicalPlan = \StationX\Yugioh\Domain\Competitions\LegacyPhasePlan::class;
architectureCheck(
    $historicalPlan::winnersByDivision([
        ['division_id' => 1, 'result' => 'A', 'deck_a_id' => 2],
        ['division_id' => 1, 'result' => 'E'],
    ]) === [1 => [2]],
    'Historical winners changed',
);
$autoScheduleRows = iterator_to_array(
    \StationX\Yugioh\Domain\Calendar\QualifierAutoSchedulePlan::rows(
        [['id' => 1], ['id' => 2], ['id' => 3]],
        new DateTimeImmutable('2026-11-26 19:00:00'),
        2,
        45,
    ),
    false,
);
architectureCheck(
    $autoScheduleRows[1]['time'] === '19:45:00' && $autoScheduleRows[2]['date'] === '2026-11-27',
    'Automatic qualifier slots changed',
);

// Verify new command boundaries without a live database.
$linksPort = new class implements \StationX\Yugioh\Application\Teams\TeamDeckLinkStore {
    public array $calls = [];
    public function active(int $teamId): bool
    {
        $this->calls[] = ['active', $teamId];
        return true;
    }
    public function add(int $teamId, array $ids): void
    {
        $this->calls[] = ['add', $teamId, $ids];
    }
    public function remove(int $teamId, array $ids): void
    {
        $this->calls[] = ['remove', $teamId, $ids];
    }
    public function audit(int $teamId, array $add, array $remove): void
    {
        $this->calls[] = ['audit', $teamId, $add, $remove];
    }
};
$linkCommand = new \StationX\Yugioh\Application\Teams\UpdateTeamDeckLinks($linksPort);
architectureCheck(
    $linkCommand->execute([
        'team_id' => 7,
        'team_add_deck_ids' => [0, 2, '2', 3],
        'team_remove_deck_ids' => [4],
    ]) === 'equipo_ficha',
    'Link response changed',
);
architectureCheck(
    $linksPort->calls === [
        ['active', 7],
        ['add', 7, [2, 3]],
        ['remove', 7, [4]],
        ['audit', 7, [2, 3], [4]],
    ],
    'Link mutation ordering changed',
);
$playerProfile = \StationX\Yugioh\Domain\Teams\PlayerProfile::fromInput([
    'player_name' => ' A ',
    'player_deck_ids' => [2, 2, -1],
]);
architectureCheck(
    $playerProfile['name'] === 'A' && $playerProfile['deckIds'] === [2],
    'Player normalization changed',
);
$matchScheduleUpdate = \StationX\Yugioh\Domain\Calendar\MatchScheduleUpdate::class;
architectureCheck(
    $matchScheduleUpdate::closed(['status' => 'finalizado']),
    'Finalized match editable',
);
architectureCheck(
    $matchScheduleUpdate::fromInput(['venue' => ' X ']) === [
        'scheduled_on' => null,
        'starts_at' => null,
        'venue' => 'X',
        'judge' => '',
        'notes' => '',
    ],
    'Schedule input normalization changed',
);

// Result command payload and correction policies remain independent of persistence.
$resultPayload = \StationX\Yugioh\Domain\Matches\MatchResultPayload::class;
architectureCheck(
    $resultPayload::values(['d1' => 'A', 'd2' => 'invalid', 'd3' => 'E']) === [
        'A',
        '',
        'E',
        '',
        '',
    ],
    'Result sequence input changed',
);
architectureCheck(
    $resultPayload::offlineRequestId(['offline_request_id' => 'req:1/2']) === 'req12',
    'Offline identity changed',
);
$payload = $resultPayload::normalize(
    ['result_dice_roll' => '{"winner":"a","choice":"first","starts":"a"}'],
    ['A', '', '', '', ''],
);
architectureCheck(
    $payload['diceWinner'] === 'A' &&
        $payload['turnDecision'] === 'primero' &&
        $payload['startsPlayer'] === 'A',
    'Dice normalization changed',
);
$resultIntegrity = \StationX\Yugioh\Domain\Matches\ResultIntegrityPolicy::class;
architectureCheck(
    $resultIntegrity::preserveStoredValues(['A', '', 'B', '', ''], ['', 'E', 'A', '', '']) === [
        'A',
        'E',
        'B',
        '',
        '',
    ],
    'Stored sequence protection changed',
);
architectureCheck(
    $seasonOutcome(
        static fn() => $resultIntegrity::assertCorrectionVersion(
            [['manual_correction_at_ms' => 5]],
            4,
        ),
    ) === 'La partida fue modificada. Recarga antes de guardar para no sobrescribir la corrección.',
    'Correction version guard changed',
);

// Complete command characterization through in-memory ports.
$resultPorts = new class implements
    \StationX\Yugioh\Application\Matches\MatchResultStore,
    \StationX\Yugioh\Application\Matches\MatchResultRules,
    \StationX\Yugioh\Application\Matches\MatchResultEffects,
    \StationX\Yugioh\Application\Matches\MatchSnapshotStore,
    \StationX\Yugioh\Application\Shared\TransactionBoundary,
    \StationX\Yugioh\Application\Shared\Clock
{
    public array $calls = [];
    public array $existing = [];
    public array $saved = [];
    public string $team = '';
    public string|false $synced = false;
    public bool $dateAllowed = true;

    public function syncedResult(string $requestId): string|false
    {
        $this->calls[] = 'synced';
        return $this->synced;
    }
    public function audit(int $matchId, string $action, array $payload): void
    {
        $this->calls[] = $action;
    }
    public function ensure(int $matchId): void
    {
        $this->calls[] = 'ensure';
    }
    public function lockExisting(int $matchId): array
    {
        $this->calls[] = 'lock';
        return $this->existing;
    }
    public function persistedTurns(int $matchId): array
    {
        $this->calls[] = 'turns';
        return [];
    }
    public function appendTurns(int $matchId, iterable $rows): void
    {
        $this->calls[] = 'append';
        foreach ($rows as $row) {
        }
    }
    public function save(array $values): void
    {
        $this->calls[] = 'save';
        $this->saved = $values;
    }
    public function competitionId(int $matchId): int
    {
        $this->calls[] = 'competition';
        return 8;
    }
    public function saveLiveState(int $matchId, array $state): void
    {
        $this->calls[] = 'live-save';
    }
    public function recordSync(string $requestId, int $matchId, string $payloadHash): void
    {
        $this->calls[] = 'record-sync';
    }
    public function teamKindForMatch(int $matchId): string
    {
        $this->calls[] = 'team';
        return $this->team;
    }
    public function verifiedStart(int $matchId, int $duel, string $starts): bool
    {
        $this->calls[] = 'verify-start';
        return false;
    }
    public function validateDate(int $matchId): array
    {
        $this->calls[] = 'date';
        return ['ok' => $this->dateAllowed];
    }
    public function assertEditable(int $matchId): void
    {
        $this->calls[] = 'editable';
    }
    public function evaluate(int $matchId, array $values): array
    {
        $this->calls[] = 'evaluate';
        return ['winner' => null, 'score' => '0-0', 'validation' => 'OK', 'status' => 'PENDIENTE'];
    }
    public function setStatus(int $matchId, string $status, string $now): void
    {
        $this->calls[] = 'status:' . $now;
    }
    public function persistStandings(int $competitionId): void
    {
        $this->calls[] = 'standings';
    }
    public function syncStatistics(int $competitionId, int $matchId): void
    {
        $this->calls[] = 'statistics';
    }
    public function liveMatch(int $matchId): array
    {
        $this->calls[] = 'live';
        return [];
    }
    public function initialLiveState(array $match): array
    {
        return [];
    }
    public function match(int $matchId): array
    {
        $this->calls[] = 'snapshot';
        return [];
    }
    public function deck(int $deckId): array
    {
        throw new RuntimeException('Unexpected deck read');
    }
    public function decklist(int $deckId): array
    {
        throw new RuntimeException('Unexpected decklist read');
    }
    public function insert(array $row): void
    {
        throw new RuntimeException('Unexpected snapshot');
    }
    public function run(callable $operation): mixed
    {
        $this->calls[] = 'begin';
        try {
            $result = $operation();
            $this->calls[] = 'commit';
            return $result;
        } catch (Throwable $error) {
            $this->calls[] = 'rollback';
            throw $error;
        }
    }
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-26T17:00:00+00:00');
    }
};
$resultCommand = new \StationX\Yugioh\Application\Matches\SaveMatchResult(
    $resultPorts,
    $resultPorts,
    $resultPorts,
    new \StationX\Yugioh\Application\Matches\PublishMatchSnapshots($resultPorts),
    $resultPorts,
    $resultPorts,
);
$resultPorts->synced = 'resultado';
architectureCheck(
    $resultCommand->execute(['match_id' => 7, 'offline_request_id' => 'a']) === 'resultado' &&
        $resultPorts->calls === ['team', 'synced'],
    'Offline duplicate performed writes',
);
$resultPorts->synced = false;
$resultPorts->calls = [];
architectureCheck(
    $resultCommand->execute(['match_id' => 7, 'd1' => 'A']) === 'resultado_bloqueado_inicio' &&
        $resultPorts->calls === ['team', 'verify-start'],
    'Missing start guard changed',
);
$resultPorts->dateAllowed = false;
$resultPorts->calls = [];
architectureCheck(
    $resultCommand->execute(['match_id' => 7]) === 'resultado_bloqueado_fecha' &&
        $resultPorts->calls === ['team', 'verify-start', 'date', 'blocked_result_by_date'],
    'Date guard wrote result',
);
$resultPorts->dateAllowed = true;
$resultPorts->calls = [];
$resultPorts->existing = ['duel_details' => '[{"manual_correction_at_ms":5}]'];
architectureCheck(
    $seasonOutcome(static fn() => $resultCommand->execute(['match_id' => 7])) !== null &&
        end($resultPorts->calls) === 'rollback',
    'Correction conflict did not roll back',
);
$resultPorts->existing = [];
$resultPorts->calls = [];
architectureCheck(
    $resultCommand->execute(['match_id' => 7, 'offline_request_id' => 'a']) === 'resultado',
    'Empty pending result response changed',
);
architectureCheck(
    $resultPorts->calls === [
        'team',
        'synced',
        'verify-start',
        'date',
        'begin',
        'editable',
        'ensure',
        'lock',
        'evaluate',
        'save',
        'snapshot',
        'status:2026-11-26 12:00:00',
        'competition',
        'standings',
        'save_result',
        'statistics',
        'live',
        'record-sync',
        'commit',
    ],
    'Result mutation order changed',
);
architectureCheck(
    $resultPorts->saved['matchId'] === 7 &&
        $resultPorts->saved['d1'] === null &&
        $resultPorts->saved['validation'] === 'OK',
    'Result payload changed',
);

// Team event configuration preserves exact roster membership, storage limits and fixed slots.
$teamEventPolicy = \StationX\Yugioh\Domain\Competitions\TeamConfigurationPolicy::class;
$teamState = [
    'version' => 'STATION_X_TEAMS_V1',
    'size' => 2,
    'lineups' => [['captain' => 5, 'decks' => [5, 6]], ['captain' => 7, 'decks' => [7, 8]]],
];
architectureCheck(
    $teamEventPolicy::decodeState($teamEventPolicy::encodeState($teamState)) === $teamState,
    'Team configuration JSON changed',
);
architectureCheck(
    $teamEventPolicy::assertRoster($teamState, [8, '7', 6, 5]) === [5, 6, 7, 8],
    'Team roster identity changed',
);
architectureCheck(
    $teamEventPolicy::binding($teamState, 5, 7) === ['2v2', 2, 6, 8, null, null],
    'Tag binding changed',
);
architectureCheck(
    $seasonOutcome(static fn() => $teamEventPolicy::assertRoster($teamState, [5, 5, 7, 8])) ===
        'Los participantes deben coincidir exactamente con las alineaciones guardadas.',
    'Duplicate team roster accepted',
);
architectureCheck(
    $seasonOutcome(
        static fn() => $teamEventPolicy::assertMode(['match_format' => '1v1'], 'tag'),
    ) !== null,
    'Invalid Tag mode accepted',
);

// Collective results do not duplicate individual deck outcomes.
$teamResultPolicy = \StationX\Yugioh\Domain\Competitions\TeamResultPolicy::class;
architectureCheck(
    $teamResultPolicy::collectiveDuels('team', [['A'], ['B'], ['A']]) === array_fill(0, 5, null),
    'Seat games leaked into collective result',
);
architectureCheck(
    $teamResultPolicy::collectiveDuels('tag', [['A', 'A']]) === ['A', 'A', null, null, null],
    'Shared Tag series changed',
);
architectureCheck(
    $seasonOutcome(static fn() => $teamResultPolicy::assertRevision(['revision' => 2], 1)) !== null,
    'Stale team result revision accepted',
);
$projection = \StationX\Yugioh\Domain\Statistics\TeamIndividualProjection::class;
architectureCheck(
    $projection::expand([['id' => 1], ['id' => 2]], [2 => true], [2 => ['kind' => 'tag']], []) === [
        ['id' => 1],
    ],
    'Collective Tag match counted as individual',
);

// The package boundary rejects traversal and accepts only the exact deck root.
$packageFiles = \StationX\Yugioh\Domain\Decks\Packages\PackageFiles::class;
architectureCheck(
    $packageFiles::path('DECK_1/guide.md') === 'DECK_1/guide.md',
    'Package path changed',
);
architectureCheck(
    $seasonOutcome(static fn() => $packageFiles::path('../outside.md')) !== null,
    'Archive traversal accepted',
);
$packageFolder = \StationX\Yugioh\Domain\Decks\Packages\PackageFolder::class;
architectureCheck(
    $packageFolder::normalizeFiles(['decks/DECK_1/guide.md' => 'source']) === [
        'DECK_1/guide.md' => 'source',
    ],
    'Deck root normalization changed',
);
architectureCheck(
    $packageFiles::sourceImage(['a/photo.png' => 'a', 'b/photo.png' => 'b'], 'photo.png') === null,
    'Ambiguous image accepted',
);

// Publication policies preserve the expected revision and immutable metadata.
$publicationPolicy = \StationX\Yugioh\Domain\Decks\Packages\PackagePublicationPolicy::class;
$publicationRecord = $publicationPolicy::record(
    ['image' => 'old.png', 'folder_delivery' => ['reader' => 'old.html']],
    ['old.png' => './assets/editorial/version/new.png'],
    '2026-11-26T12:00:00+00:00',
);
architectureCheck(
    $publicationRecord['image'] === './assets/editorial/version/new.png' &&
        !isset($publicationRecord['folder_delivery']['reader']),
    'Publication image map or private reader changed',
);
$publicationPolicy::assertUnchanged(null, hash('sha256', 'null'));
try {
    $publicationPolicy::assertUnchanged(['path' => 'new'], hash('sha256', 'null'));
    throw new RuntimeException('Stale package revision accepted');
} catch (DomainException) {
    // Conflict is expected before creating a publication directory.
}

// Package ticket identity and expiry are explicit and independently testable.
$packagePreview = \StationX\Yugioh\Domain\Decks\Packages\PackagePreviewPolicy::class;
$packagePending = $packagePreview::pending(
    9,
    '/tmp/example',
    'ticket',
    100,
    null,
    ['inventory_sha256' => 'inv'],
    [],
);
$packagePreview::assertTicket($packagePending, 9, 'ticket', 1900);
architectureCheck(
    $seasonOutcome(
        static fn() => $packagePreview::assertTicket($packagePending, 9, 'ticket', 1901),
    ) !== null,
    'Expired preview ticket accepted',
);
architectureCheck(
    $seasonOutcome(
        static fn() => $packagePreview::assertTicket($packagePending, 8, 'ticket', 100),
    ) !== null,
    'Preview ticket reused for another deck',
);
$packagePreview::assertInventory($packagePending, ['inventory_sha256' => 'inv'], []);

// Deck inventory commands preserve manual quantities and CSV zones.
$deckCardInput = \StationX\Yugioh\Domain\Decks\DeckCardInput::class;
$manualDeckCard = $deckCardInput::single(['card_deck_id' => 7, 'card_name' => ' A ', 'card_quantity' => 4]);
architectureCheck(
    $manualDeckCard['quantity'] === 4 && $manualDeckCard['section'] === 'main',
    'Legacy manual card quantity changed',
);
$parsedDeckCards = $deckCardInput::parse(7, "main;4;A\nextra;1;B\ninvalid;1;C\nside;0;D");
architectureCheck(
    array_column($parsedDeckCards, 'quantity') === [3, 1, 1] &&
        array_column($parsedDeckCards, 'section') === ['main', 'extra', 'side'],
    'Legacy inventory parser changed',
);
$deckProfilePolicy = \StationX\Yugioh\Domain\Decks\DeckProfileInput::class;
architectureCheck($deckProfilePolicy::team([], 7) === 7, 'Existing team was lost');
architectureCheck($deckProfilePolicy::team(['team_id' => ''], 7) === 0, 'Explicit unlink lost');

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
