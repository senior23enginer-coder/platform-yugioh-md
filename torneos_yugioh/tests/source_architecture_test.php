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
$manualDeckCard = $deckCardInput::single([
    'card_deck_id' => 7,
    'card_name' => ' A ',
    'card_quantity' => 4,
]);
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

// Statistics replay accepts an explicit clock, not HTTP or persistence.
$statsClock = new class implements \StationX\Yugioh\Application\Shared\Clock {
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-26T17:00:00+00:00');
    }
};
$statsReplay = new \StationX\Yugioh\Application\Statistics\CalculateStatistics($statsClock);
$emptyStats = $statsReplay->execute([]);
architectureCheck($emptyStats['cutoff'] === '2026-11-26 12:00:00', 'Statistics clock changed');
architectureCheck(
    $emptyStats['global'] === [] && $emptyStats['ledger'] === [],
    'Empty ranking invented results',
);
architectureCheck($statsReplay->freeForAll([])['ranking'] === [], 'Empty FFA invented results');
architectureCheck(
    \StationX\Yugioh\Domain\Statistics\StatisticsPolicy::defaults()['base_global'] === 1000,
    'Global rating baseline changed',
);
architectureCheck(
    \StationX\Yugioh\Domain\Statistics\AchievementPolicy::definitions()[0][0] === 'first_match_win',
    'Achievement identity changed',
);

// Revision identities, unknown evidence and empty source projections are stable.
$revisionPolicy = \StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy::class;
architectureCheck(
    $revisionPolicy::canonical(['b' => 1, 'a' => 2]) === '{"a":2,"b":1}',
    'Canonical statistics hash changed',
);
architectureCheck(
    $revisionPolicy::publicValues(['name' => 'Logro secreto', 'code' => 'hidden'])['code'] ===
        'secret_' . substr(hash('sha256', 'hidden'), 0, 12),
    'Secret achievement leaked',
);
$emptySource = \StationX\Yugioh\Domain\Statistics\StatisticsSourceProjection::build(
    [],
    [],
    [],
    [],
    [],
    [],
    [],
    [],
);
architectureCheck(
    $emptySource === [
        'matches' => [],
        'events' => [],
        'honors' => [],
        'seasons' => [],
        'ffa_matches' => [],
    ],
    'Empty source projection changed',
);

// The fixed global policy preserves its version and rejects unsupported importance values.
$globalPolicy = \StationX\Yugioh\Domain\Statistics\GlobalPolicyConfiguration::class;
$policy = $globalPolicy::build('2026-10-18', ['BONUS' => 25]);
architectureCheck($policy['global_anchor'] === '2026-10-18', 'Global anchor changed');
architectureCheck(
    $seasonOutcome(static fn() => $globalPolicy::build('2026-02-30', [])) !== null,
    'Invalid global anchor accepted',
);
architectureCheck(
    $seasonOutcome(static fn() => $globalPolicy::build('2026-10-18', ['FULL' => 99])) !== null,
    'Invalid importance accepted',
);
architectureCheck(
    $seasonOutcome(
        static fn() => $globalPolicy::assertUnchanged(
            json_encode($policy),
            $globalPolicy::build('2026-10-19', []),
        ),
    ) !== null,
    'Fixed global policy overwritten',
);

// Analysis form fields are normalized from the submitted payload, not implicit closure state.
$analysisInput = \StationX\Yugioh\Domain\Decks\DeckAnalysisInput::class;
$profileInput = $analysisInput::profile([
    'deck_analysis_strengths' => '["One, two","One, two","Three"]',
    'deck_analysis_weaknesses' => 'A; B; A',
    'deck_analysis_consistency_score' => 9,
    'deck_analysis_confidence' => 'other',
]);
architectureCheck(
    json_decode($profileInput['values'][12], true) === ['One, two', 'Three'],
    'Structured strengths lost',
);
architectureCheck(
    json_decode($profileInput['values'][13], true) === ['A', 'B'],
    'Weakness list lost',
);
architectureCheck(
    $profileInput['values'][15] === 5 && $profileInput['values'][22] === 'MEDIA',
    'Analysis score or confidence changed',
);
$matchupInput = $analysisInput::matchup([
    'strategic_score' => -9,
    'matchup_advantages' => 'A; B; A',
]);
architectureCheck(
    $matchupInput['values'][0] === -2 && $matchupInput['values'][1] === 'MUY_DESFAVORABLE',
    'Strategic classification changed',
);
architectureCheck(
    json_decode($matchupInput['values'][3], true) === ['A', 'B'],
    'Matchup advantages lost',
);

// Administrative forms preserve input guards and QR origin without database dependencies.
$dependencyInput = \StationX\Yugioh\Domain\Competitions\DependencyInput::class;
architectureCheck(
    $dependencyInput::normalize([
        'dependency_competition_id' => 2,
        'dependency_depends_on_id' => 2,
    ]) === null,
    'Self dependency accepted',
);
$topDependency = $dependencyInput::normalize([
    'dependency_competition_id' => 2,
    'dependency_depends_on_id' => 1,
    'dependency_condition' => 'TOP_N',
]);
architectureCheck(
    $topDependency['takeCount'] === 4 && $topDependency['condition'] === 'top_n',
    'Top N dependency default changed',
);
$checkinInput = \StationX\Yugioh\Domain\Competitions\ParticipantCheckinInput::class;
architectureCheck($checkinInput::normalize([]) === null, 'Empty participant registered');
$qrCheckin = $checkinInput::normalize([
    'checkin_display_name' => ' A ',
    'checkin_entity_type' => 'unknown',
    'checkin_code' => '123',
]);
architectureCheck(
    $qrCheckin['entityType'] === 'guest' &&
        $qrCheckin['source'] === 'qr' &&
        $qrCheckin['displayName'] === 'A',
    'Checkin normalization changed',
);

// Rule/administrative input policies keep existing limits and fallback behavior.
$ruleInput = \StationX\Yugioh\Domain\Matches\GameRuleInput::class;
$ruleValues = $ruleInput::ruleset(1, 'test', 'Test', [
    'ruleset_best_of' => 99,
    'ruleset_participants_per_side' => 0,
]);
architectureCheck($ruleValues[8] === 10 && $ruleValues[9] === 1, 'Ruleset limits changed');
$conditions = iterator_to_array(
    $ruleInput::conditions([
        'condition_label' => ['A', ''],
        'condition_type' => ['invalid'],
        'condition_active' => ['on'],
        'condition_result' => ['X'],
    ]),
    false,
);
architectureCheck(
    count($conditions) === 1 &&
        $conditions[0]['values'][0] === 'validation' &&
        $conditions[0]['values'][4] === '' &&
        $conditions[0]['values'][9] === 1,
    'Rule condition normalization changed',
);
$admin = \StationX\Yugioh\Domain\Settings\AdministrativeInput::class;
$values = iterator_to_array(
    $admin::settings(['valid_weekdays' => ['0', '3', '1', '9'], 'max_matches_per_day' => 100]),
);
architectureCheck(
    $values['valid_weekdays'] === '1,3,0' && $values['max_matches_per_day'] === '3',
    'Settings normalization changed',
);
architectureCheck(
    iterator_to_array(
        $admin::labels([
            'ui_label_key' => ['a!'],
            'ui_label_title' => [' Title '],
            'ui_label_active' => ['a'],
        ]),
        false,
    ) === [['a', 'Title', '', 1]],
    'UI label normalization changed',
);

// Maintenance commands retain qualifier fallback and blocked-finalization guards.
$maintenance = \StationX\Yugioh\Domain\Competitions\CompetitionMaintenancePolicy::class;
architectureCheck(
    $maintenance::qualifierStatus(['status' => 'invalid']) === 'active',
    'Qualifier fallback changed',
);
architectureCheck($maintenance::canFinalize('active', ['ok' => true]), 'Ready event blocked');
architectureCheck(
    !$maintenance::canFinalize('completed', ['ok' => true]),
    'Completed event finalized twice',
);
architectureCheck(
    !$maintenance::canFinalize('active', ['ok' => false]),
    'Incomplete event finalized',
);

// Preserve division selection, ranking intervals and manual honor identity safeguards.
$divisionInput = \StationX\Yugioh\Domain\Competitions\DivisionInput::class;
architectureCheck(
    $divisionInput::selectedDecks(['division_deck_ids' => [2, '2', 0, 4]]) === [2, 4],
    'Division selection changed',
);
$manualHonor = \StationX\Yugioh\Domain\Competitions\ManualHonorPolicy::class;
architectureCheck(
    $seasonOutcome(static fn() => $manualHonor::assertParticipants(3, 3)) !== null,
    'Duplicate champion accepted',
);
architectureCheck(
    $seasonOutcome(static fn() => $manualHonor::assertEvent(['status' => 'active'], 'evidence')) !==
        null,
    'Open event awarded honor',
);

// Offline commands reject recursion, preserve optimistic checks and deduplicate request IDs.
$offlineStore = new class implements \StationX\Yugioh\Application\Commands\OfflineFormStore {
    public ?string $stored = null;
    public string $updated = 'new';
    public array $records = [];
    public function result(string $requestId): ?string
    {
        return $this->stored;
    }
    public function updatedAt(string $action, int $id): string
    {
        return $this->updated;
    }
    public function record(string $requestId, string $hash, string $result): void
    {
        $this->records[] = [$requestId, $hash, $result];
    }
};
$offlineExecutor = new class implements \StationX\Yugioh\Application\Commands\OfflineFormExecutor {
    public array $calls = [];
    public function execute(array $payload): string
    {
        $this->calls[] = $payload;
        return 'ok';
    }
};
$offline = new \StationX\Yugioh\Application\Commands\SynchronizeOfflineForm(
    $offlineStore,
    $offlineExecutor,
);
architectureCheck(
    $offline->execute([
        'offline_request_id' => 'id',
        'offline_payload' => '{"action":"sync_offline_form"}',
    ]) === 'offline_invalido',
    'Recursive offline command accepted',
);
$offlineInput = [
    'offline_request_id' => 'id',
    'offline_payload' => '{"action":"save_deck","deck_id":1,"offline_entity_updated_at":"old"}',
];
architectureCheck(
    $offline->execute($offlineInput) === 'offline_conflicto' && !$offlineExecutor->calls,
    'Stale offline request executed',
);
$offlineStore->updated = 'old';
architectureCheck(
    $offline->execute($offlineInput) === 'ok' &&
        !isset($offlineExecutor->calls[0]['offline_entity_updated_at']) &&
        count($offlineStore->records) === 1,
    'Offline request contract changed',
);
$offlineStore->stored = 'previous';
architectureCheck(
    $offline->execute($offlineInput) === 'previous' && count($offlineExecutor->calls) === 1,
    'Offline duplicate wrote again',
);

// Live API projections do not depend on HTTP globals or invent match data.
$liveProjection = \StationX\Yugioh\Domain\Matches\LiveMatchProjection::class;
architectureCheck($liveProjection::matches([]) === [], 'Empty live list changed');
$liveRows = $liveProjection::matches([
    [['matches' => [['id' => '7', 'a' => 'Deck A', 'estado' => 'PENDIENTE']]]],
]);
architectureCheck(
    count($liveRows) === 1 &&
        $liveRows[0]['id'] === 7 &&
        $liveRows[0]['d1'] === null &&
        $liveRows[0]['a'] === 'Deck A',
    'Live match projection changed',
);

// Manual match input preserves phase sanitization and team size inferred from format.
$manualMatch = \StationX\Yugioh\Domain\Matches\ManualMatchInput::class;
architectureCheck(
    $manualMatch::initial(['manual_phase' => 'Friendly!', 'manual_round' => 0])['phase'] ===
        'friendly',
    'Manual phase changed',
);
$manualDetails = $manualMatch::details([
    'manual_match_format' => '3v3',
    'manual_participants_per_side' => 1,
    'manual_best_of' => 999,
]);
architectureCheck(
    $manualDetails['participants'] === 3 && $manualDetails['bestOf'] === 10,
    'Manual match format limits changed',
);

// Competition creation preserves selected format, league movement guards and valid series sizes.
$creationPolicy = \StationX\Yugioh\Domain\Competitions\CompetitionCreationPolicy::class;
architectureCheck(
    $creationPolicy::selection(['competition_division_ids' => ['2', 2, 0]], 'format:ocg') === [
        'divisionIds' => [2],
        'ruleset' => 'ocg',
    ],
    'Competition format selection changed',
);
$competitionInput = $creationPolicy::format(['competition_match_format' => '2v2'], [], '7', [8]);
architectureCheck(
    $competitionInput['participantsPerSide'] === 2 && $competitionInput['divisionIds'] === [7, 8],
    'Competition division order changed',
);
architectureCheck(
    $creationPolicy::movements(['competition_promotion_slots' => 3], 'league', 7, [7])[
        'leaguePromotionSlots'
    ] === 0,
    'Single division promotion allowed',
);
architectureCheck(
    $creationPolicy::parameters(['competition_parameter_series_matches' => '5']) === [
        'series_matches' => 5,
        'series_wins' => 3,
    ],
    'Series threshold changed',
);
architectureCheck(
    $seasonOutcome(
        static fn() => $creationPolicy::parameters(['competition_parameter_series_matches' => '4']),
    ) !== null,
    'Even series accepted',
);

// Read model projections preserve the virtual qualifier and empty fixture groups.
$platformDeck = \StationX\Yugioh\Domain\Decks\PlatformDeckProjection::class;
$readDecks = $platformDeck::decks($platformDeck::divisions([], []), []);
architectureCheck(
    array_keys($readDecks['divisions']) === ['CLASIFICATORIO'] && $readDecks['allDecks'] === [],
    'Virtual qualifier projection changed',
);
$platformFixtures = \StationX\Yugioh\Domain\Matches\PlatformFixtureProjection::build(
    [],
    $readDecks['divisions'],
);
architectureCheck(
    $platformFixtures === ['fixtures' => ['CLASIFICATORIO' => []], 'deckMatchCounts' => []],
    'Empty fixture projection changed',
);

// Bootstrap maintenance is independently replayable; audit is recorded only after success.
$backfillPort = new class implements \StationX\Yugioh\Application\Bootstrap\HistoricalBackfillPort {
    public array $calls = [];
    public array $records = [];
    public function completed(string $version): bool
    {
        return isset($this->records[$version]);
    }
    public function record(string $version, array $counts): void
    {
        $this->records[$version] = $counts;
    }
    public function syncMedals(): int
    {
        $this->calls[] = 'medals';
        return 3;
    }
    public function backfillSnapshots(): int
    {
        $this->calls[] = 'snapshots';
        return 4;
    }
    public function syncMeta(): int
    {
        $this->calls[] = 'meta';
        return 5;
    }
    public function syncElo(): int
    {
        $this->calls[] = 'elo';
        return 6;
    }
    public function syncRanking(): void
    {
        $this->calls[] = 'ranking';
    }
    public function syncSummaries(): void
    {
        $this->calls[] = 'summaries';
    }
};
$backfills = new \StationX\Yugioh\Application\Bootstrap\RunHistoricalBackfills($backfillPort);
$backfills->execute();
architectureCheck(
    $backfillPort->calls === [
        'medals',
        'snapshots',
        'meta',
        'elo',
        'ranking',
        'summaries',
        'medals',
    ],
    'Backfill order changed',
);
architectureCheck(
    $backfillPort->records['historical_intelligence_v2'] === ['meta_rows' => 5, 'elo_rows' => 6],
    'Historical audit changed',
);
$backfills->execute();
architectureCheck(count($backfillPort->calls) === 7, 'Completed historical work repeated');
$redirect = \StationX\Yugioh\Application\Bootstrap\PostRedirectTarget::class;
architectureCheck(
    $redirect::resolve('deck_analysis_8', []) === [
        'screen' => 'deck_ficha',
        'extraQuery' => '&deck_id=8&deck_view=analysis',
    ],
    'Deck redirect changed',
);
architectureCheck(
    $redirect::resolve('evento_avanzado_9_2', []) === [
        'screen' => 'evento_ficha',
        'extraQuery' => '&event_id=9',
    ],
    'Event redirect changed',
);
architectureCheck(
    $redirect::resolve('clasificatorio_guardado', ['qualifier_competition_id' => 11]) === [
        'screen' => 'clasificatorio',
        'extraQuery' => '&event_id=11',
    ],
    'Qualifier redirect changed',
);
architectureCheck(
    $redirect::resolve('evento_temporada_bloqueada', []) === [
        'screen' => 'torneos',
        'extraQuery' => '',
    ],
    'Season-blocked redirect changed',
);

// Bootstrap stage order and counter contracts.
$port = new class implements \StationX\Yugioh\Application\Bootstrap\PlatformInitializationPort {
    public array $calls = [];
    public function ensureCoreSchema(): void
    {
        $this->calls[] = 'ensureCoreSchema';
    }
    public function ensureMatchCompletionColumn(): void
    {
        $this->calls[] = 'ensureMatchCompletionColumn';
    }
    public function ensureStatisticsSchema(): void
    {
        $this->calls[] = 'ensureStatisticsSchema';
    }
    public function ensureFfaSchema(): void
    {
        $this->calls[] = 'ensureFfaSchema';
    }
    public function bootstrapGlobalStatistics(): void
    {
        $this->calls[] = 'bootstrapGlobalStatistics';
    }
    public function ensureCompetitionParametersColumn(): void
    {
        $this->calls[] = 'ensureCompetitionParametersColumn';
    }
    public function seedDatabase(array $divisions, array $fixtures): array
    {
        $this->calls[] = 'seedDatabase';
        return [];
    }
    public function ensureSeasonSchema(): void
    {
        $this->calls[] = 'ensureSeasonSchema';
    }
    public function initializeSeasonContext(): void
    {
        $this->calls[] = 'initializeSeasonContext';
    }
    public function closeExpiredSeasons(): void
    {
        $this->calls[] = 'closeExpiredSeasons';
    }
    public function currentSeasonId(): int
    {
        $this->calls[] = 'currentSeasonId';
        return 7;
    }
    public function ensureCatalogs(int $seasonId): void
    {
        $this->calls[] = 'ensureCatalogs';
    }
    public function loadCatalogs(int $seasonId): array
    {
        $this->calls[] = 'loadCatalogs';
        return [];
    }
};
$stage = (new \StationX\Yugioh\Application\Bootstrap\InitializePlatform($port))->execute([], []);
architectureCheck(
    $port->calls === [
        'ensureCoreSchema',
        'ensureMatchCompletionColumn',
        'ensureStatisticsSchema',
        'ensureFfaSchema',
        'bootstrapGlobalStatistics',
        'ensureCompetitionParametersColumn',
        'seedDatabase',
        'ensureSeasonSchema',
        'initializeSeasonContext',
        'closeExpiredSeasons',
        'currentSeasonId',
        'ensureCatalogs',
        'loadCatalogs',
    ],
    'InitializePlatform order changed',
);
architectureCheck(
    $stage === ['seedCounts' => [], 'seasonId' => 7, 'catalogGroups' => []],
    'InitializePlatform result changed',
);
$port = new class implements \StationX\Yugioh\Application\Bootstrap\SeasonMaintenancePort {
    public array $calls = [];
    public function ensureSettings(): void
    {
        $this->calls[] = 'ensureSettings';
    }
    public function ensureLabels(): void
    {
        $this->calls[] = 'ensureLabels';
    }
    public function removeSeededDivisions(int $seasonId, array $definitions): int
    {
        $this->calls[] = 'removeSeededDivisions';
        return 7;
    }
    public function ensureRulesets(int $seasonId, array $options): void
    {
        $this->calls[] = 'ensureRulesets';
    }
    public function ensureRulesetVersions(int $seasonId): void
    {
        $this->calls[] = 'ensureRulesetVersions';
    }
    public function ensureMatchTypes(): void
    {
        $this->calls[] = 'ensureMatchTypes';
    }
    public function ensureGameRules(int $seasonId): void
    {
        $this->calls[] = 'ensureGameRules';
    }
    public function ensureGoatStructure(int $seasonId): array
    {
        $this->calls[] = 'ensureGoatStructure';
        return [];
    }
    public function applyHistoricalSchedule(): array
    {
        $this->calls[] = 'applyHistoricalSchedule';
        return [];
    }
    public function activateDueCompetitions(): int
    {
        $this->calls[] = 'activateDueCompetitions';
        return 7;
    }
    public function ensureCompetitionCodes(int $seasonId): void
    {
        $this->calls[] = 'ensureCompetitionCodes';
    }
    public function syncDeckTeams(): int
    {
        $this->calls[] = 'syncDeckTeams';
        return 7;
    }
    public function normalizeDeckFormats(): int
    {
        $this->calls[] = 'normalizeDeckFormats';
        return 7;
    }
    public function seedDeckAnalysis(): int
    {
        $this->calls[] = 'seedDeckAnalysis';
        return 7;
    }
};
$stage = (new \StationX\Yugioh\Application\Bootstrap\MaintainSeason($port))->execute(7, [], []);
architectureCheck(
    $port->calls === [
        'ensureSettings',
        'ensureLabels',
        'removeSeededDivisions',
        'ensureRulesets',
        'ensureRulesetVersions',
        'ensureMatchTypes',
        'ensureGameRules',
        'ensureGoatStructure',
        'applyHistoricalSchedule',
        'activateDueCompetitions',
        'ensureCompetitionCodes',
        'syncDeckTeams',
        'normalizeDeckFormats',
        'seedDeckAnalysis',
    ],
    'MaintainSeason order changed',
);
architectureCheck(
    $stage === [
        'deletedSeedDivisions' => 7,
        'goatLeagueStructure' => [],
        'octoberScheduleMigration' => [],
        'activatedDueCompetitions' => 7,
        'teamSyncCount' => 7,
        'deckFormatSyncCount' => 7,
        'deckAnalysisBaselineCount' => 7,
    ],
    'MaintainSeason result changed',
);

// Entity report projection preserves the native export rows without HTTP or database state.
$reportClass = \StationX\Yugioh\Application\Reports\EntityReportProjection::class;
$report = $reportClass::build(
    'entity_report',
    ['entity_type' => 'deck', 'entity_id' => '3'],
    [['id' => 3, 'name' => 'Memento', 'team_id' => 4, 'team' => 'Team A']],
    [],
    [],
    [
        [
            'rounds' => [
                'matches' => [
                    [
                        'id' => 1,
                        'deck_a_id' => 3,
                        'deck_b_id' => 8,
                        'a' => 'Memento',
                        'b' => 'Crystron',
                        'estado' => 'FINALIZADA',
                        'result' => 'A',
                        'score' => '2-1',
                        'd1' => 'A',
                        'd2' => 'B',
                        'd3' => 'A',
                    ],
                ],
            ],
        ],
    ],
    [],
    [],
    [],
    '2026-11-26 12:00:00',
);
architectureCheck($report['title'] === 'Informe completo Deck #3', 'Entity report title changed');
architectureCheck(
    in_array(['Partidas G/E/P', '1/0/0'], $report['rows'], true),
    'Entity report match count changed',
);
architectureCheck(
    in_array(['Duelos G/E/P', '2/0/1'], $report['rows'], true),
    'Entity report duel count changed',
);
architectureCheck(
    in_array(['Winrate partidas', '100%'], $report['rows'], true),
    'Entity report win rate changed',
);
$invalid = $reportClass::build(
    'entity_report',
    ['entity_type' => 'unknown', 'entity_id' => 3],
    [],
    [],
    [],
    [],
    [],
    [],
    [],
    '2026-11-26 12:00:00',
);
architectureCheck(
    in_array(['Error', 'Entidad o identificador invalido'], $invalid['rows'], true),
    'Invalid report accepted',
);

// Bootstrap flash messages retain the original priority, including the season fallback.
$flashPolicy = \StationX\Yugioh\Application\Bootstrap\SavedStatusMessage::class;
$flashCases = [
    ['', null, 'Existing warning', 'alert-warning', 'Existing warning', 'alert-warning', false],
    [
        'temporada_error',
        'Season error',
        null,
        'alert-success',
        'Cambio guardado: temporada_error',
        'alert-success',
        true,
    ],
    [
        'evento_temporada_bloqueada',
        null,
        null,
        'alert-success',
        'Cambio guardado: evento_temporada_bloqueada',
        'alert-success',
        true,
    ],
    [
        'temporada_guardada',
        null,
        null,
        'alert-success',
        'Cambio guardado: temporada_guardada',
        'alert-success',
        false,
    ],
    [
        'evento_avanzado_7_2',
        null,
        null,
        'alert-success',
        'Siguiente fase creada: 2 combates nuevos generados.',
        'alert-success',
        false,
    ],
    [
        'evento_avanzado_7_0',
        null,
        null,
        'alert-success',
        'No se creo una nueva fase: verifica que la fase anterior este finalizada y tenga ganadores.',
        'alert-warning',
        false,
    ],
    [
        'movimientos_liga_3_2',
        null,
        null,
        'alert-success',
        'Ascensos/descensos aplicados: 2 movimientos registrados.',
        'alert-success',
        false,
    ],
    ['unknown', null, null, 'alert-warning', 'Cambio guardado: unknown', 'alert-success', false],
];
foreach (
    $flashCases
    as [$status, $seasonError, $previous, $previousClass, $message, $class, $consume]
) {
    architectureCheck(
        $flashPolicy::resolve($status, $seasonError, $previous, $previousClass) === [
            'message' => $message,
            'class' => $class,
            'consumeSeasonError' => $consume,
        ],
        'Saved status response changed: ' . $status,
    );
}

// The bootstrap enrichment preserves native call order and competition projections.
$enrichmentPort = new class implements \StationX\Yugioh\Application\Bootstrap\BootstrapDataPort {
    public array $calls = [];
    public function statistics(): array
    {
        $this->calls[] = 'statistics';
        return ['version' => 8];
    }
    public function executionCapabilities(): array
    {
        $this->calls[] = 'capabilities';
        return [];
    }
    public function executionModels(array $types): array
    {
        $this->calls[] = 'models';
        return array_fill_keys($types, []);
    }
    public function freeForAllMatches(): array
    {
        $this->calls[] = 'ffa';
        return [];
    }
    public function canEdit(): bool
    {
        $this->calls[] = 'edit';
        return true;
    }
    public function teamResults(): array
    {
        $this->calls[] = 'results';
        return [1 => ['score' => 2]];
    }
    public function teamConfiguration(array $competition): mixed
    {
        $this->calls[] = 'team' . $competition['id'];
        return ['id' => $competition['id']];
    }
    public function workflow(array $competition): mixed
    {
        $this->calls[] = 'workflow' . $competition['id'];
        return $competition['id'] === 1 ? ['phase' => 2] : null;
    }
    public function recoveryToken(mixed $workflow): mixed
    {
        $this->calls[] = 'token';
        return 'token';
    }
    public function admissionSources(): array
    {
        $this->calls[] = 'sources';
        return [];
    }
    public function admission(array $competition): mixed
    {
        $this->calls[] = 'admission' . $competition['id'];
        return ['id' => $competition['id']];
    }
    public function eventCatalog(): array
    {
        $this->calls[] = 'catalog';
        return [];
    }
    public function eventDescriptors(): mixed
    {
        $this->calls[] = 'descriptors';
        return null;
    }
    public function eventTemplates(): array
    {
        $this->calls[] = 'templates';
        return [];
    }
};
$enriched = (new \StationX\Yugioh\Application\Bootstrap\EnrichPlatformData(
    $enrichmentPort,
))->execute(
    ['competitions' => [['id' => 1], ['id' => 2]], 'unchanged' => true],
    ['ruleset' => []],
    ['league' => 'League'],
);
architectureCheck(
    $enrichmentPort->calls === [
        'statistics',
        'capabilities',
        'models',
        'ffa',
        'edit',
        'results',
        'team1',
        'workflow1',
        'token',
        'team2',
        'workflow2',
        'sources',
        'admission1',
        'admission2',
        'catalog',
        'descriptors',
        'templates',
    ],
    'Bootstrap enrichment call order changed',
);
architectureCheck($enriched['unchanged'] === true, 'Original data lost');
architectureCheck($enriched['statisticsV8'] === ['version' => 8], 'Statistics changed');
architectureCheck($enriched['teamResults'] === [1 => ['score' => 2]], 'Team results changed');
architectureCheck($enriched['competitions'][0]['recoveryToken'] === 'token', 'Token lost');
architectureCheck($enriched['competitions'][1]['recoveryToken'] === null, 'Null workflow changed');
architectureCheck(
    $enriched['competitions'][1]['admission'] === ['id' => 2],
    'Admission projection changed',
);

// Both pre-connection and failure payloads preserve the original ordered keys.
$initialMetadata = [
    'eventExtendedCatalog' => ['catalog'],
    'executionCapabilities' => ['capability'],
    'executionModelsByType' => ['league' => []],
    'eventPromptTemplates' => ['template'],
    'competitionTypeOptions' => ['league' => 'League'],
    'settings' => ['limit' => 3],
    'uiLabels' => ['title' => []],
    'serverToday' => '2026-11-26',
];
$initialPolicy = \StationX\Yugioh\Application\Bootstrap\InitialPlatformData::class;
$initialPayload = $initialPolicy::build($initialMetadata, [1 => []], [1 => []], ['deck']);
$failurePayload = $initialPolicy::build($initialMetadata, [], [], [], true);
architectureCheck(
    array_keys($initialPayload) === [
        'eventExtendedCatalog',
        'executionCapabilities',
        'executionModelsByType',
        'divisions',
        'fixtures',
        'allDecks',
        'teams',
        'competitions',
        'competitionDependencies',
        'statusHistory',
        'standingSnapshots',
        'matchSnapshots',
        'matchupAnalyses',
        'matchupAnalysisHistory',
        'deckAnalysisProfiles',
        'deckAnalysisProfileHistory',
        'medalUnlocks',
        'medalProgress',
        'rankingHistory',
        'eventSummaries',
        'checkins',
        'metaHistory',
        'eloHistory',
        'seasons',
        'players',
        'playerDecks',
        'cards',
        'media',
        'logoHistory',
        'honors',
        'audit',
        'events',
        'rulesets',
        'rulesetVersions',
        'matchTypes',
        'gameRules',
        'gameRuleConditions',
        'eventPromptTemplates',
        'competitionTypeOptions',
        'settings',
        'uiLabels',
        'serverToday',
    ],
    'Initial bootstrap payload keys changed',
);
architectureCheck(
    array_keys($failurePayload) === [
        'eventExtendedCatalog',
        'executionCapabilities',
        'executionModelsByType',
        'divisions',
        'fixtures',
        'allDecks',
        'teams',
        'competitions',
        'competitionDependencies',
        'statusHistory',
        'standingSnapshots',
        'cards',
        'media',
        'logoHistory',
        'honors',
        'audit',
        'events',
        'rulesets',
        'rulesetVersions',
        'matchTypes',
        'gameRules',
        'gameRuleConditions',
        'eventPromptTemplates',
        'competitionTypeOptions',
        'settings',
        'uiLabels',
        'serverToday',
    ],
    'Failure bootstrap payload keys changed',
);
architectureCheck($initialPayload['allDecks'] === ['deck'], 'Initial decks changed');
architectureCheck($failurePayload['allDecks'] === [], 'Failure decks changed');
foreach ($initialMetadata as $key => $value) {
    architectureCheck($initialPayload[$key] === $value, 'Initial metadata changed: ' . $key);
    architectureCheck($failurePayload[$key] === $value, 'Failure metadata changed: ' . $key);
}

// Bootstrap seed and presentation directory projections preserve the native output.
$seedProjection = \StationX\Yugioh\Application\Bootstrap\SeedDataProjection::class;
$seedDecks = [[1, 'A', 'first'], [2, 'B', 'second']];
$seedProjectionResult = $seedProjection::fixturesAndDecks(['APEX' => ['decks' => $seedDecks]]);
architectureCheck(
    $seedProjectionResult['fixtures']['APEX'] === FixtureBuilder::build($seedDecks),
    'Bootstrap fixture generation changed',
);
architectureCheck(
    $seedProjectionResult['allDecks'] === [
        ['rank' => 1, 'name' => 'A', 'division' => 'APEX', 'reason' => 'first'],
        ['rank' => 2, 'name' => 'B', 'division' => 'APEX', 'reason' => 'second'],
    ],
    'Bootstrap seed identity changed',
);
$seedLabels = $seedProjection::labels([['home', ' Home ', 'Subtitle']]);
architectureCheck(
    $seedProjection::labelText($seedLabels, 'home', 'title', 'Fallback') === 'Home',
    'Active label changed',
);
$seedLabels['home']['active'] = 0;
architectureCheck(
    $seedProjection::labelText($seedLabels, 'home', 'title', 'Fallback') === 'Fallback',
    'Inactive label fallback changed',
);
$statusPolicy = \StationX\Yugioh\Application\Bootstrap\ConnectionStatus::class;
architectureCheck(
    $statusPolicy::connected(['old' => 1, 'same' => 2], ['same' => 3])['counts'] === [
        'old' => 1,
        'same' => 3,
    ],
    'Connection maintenance count precedence changed',
);
architectureCheck(!$statusPolicy::initial()['connected'], 'Initial connection state changed');
architectureCheck(!$statusPolicy::failed()['connected'], 'Failure connection state changed');
$directory = \StationX\Yugioh\Application\Presentation\DirectoryProjection::class;
$visibleDivisions = $directory::divisions([
    'a' => ['id' => 1, 'code' => 'A'],
    'b' => ['id' => 2, 'code' => 'A'],
    'c' => ['id' => 3, 'active' => 0],
    'd' => ['id' => 4, 'short' => 'D'],
    'e' => ['id' => 0],
]);
architectureCheck(array_keys($visibleDivisions) === ['A', 'D'], 'Division identity changed');
architectureCheck($visibleDivisions['A']['id'] === 1, 'First division precedence changed');
$teamNames = $directory::deckTeams([
    ['name' => 'A', 'team' => 'Old'],
    ['name' => 'A', 'team' => 'New'],
    ['name' => 'B', 'team' => ''],
]);
architectureCheck($directory::matchLabel($teamNames, 'A') === 'New / A', 'Team label changed');
architectureCheck(
    $directory::matchLabel($teamNames, 'B') === 'Sin equipo / B',
    'Empty team changed',
);
architectureCheck(
    $directory::matchLabel($teamNames, 'C') === 'Sin equipo / C',
    'Missing team changed',
);

// Shared presentation aggregation keeps cup sorting and world insertion order distinct.
$competitionMatches = \StationX\Yugioh\Application\Presentation\CompetitionMatchProjection::class;
$projectionFixtures = [
    'X' => [
        [
            'ronda' => 2,
            'matches' => [
                ['id' => 1, 'competition_id' => 8, 'phase' => 'cup', 'display_order' => 2],
                [
                    'id' => 2,
                    'competition_id' => 8,
                    'phase' => 'final',
                    'display_order' => 1,
                    'score' => '2-0',
                    'scheduled_on' => '2026-11-26',
                ],
                ['id' => 3, 'competition_id' => 9, 'phase' => 'world', 'display_order' => 2],
                ['id' => 4, 'competition_id' => 9, 'phase' => 'world', 'display_order' => 1],
                ['id' => 5, 'competition_id' => 0, 'phase' => 'cup'],
            ],
        ],
    ],
];
$cupProjection = $competitionMatches::cups($projectionFixtures);
$worldProjection = $competitionMatches::worlds($projectionFixtures);
architectureCheck(array_column($cupProjection['matches'][8], 'id') === [2, 1], 'Cup order changed');
architectureCheck(
    array_column($worldProjection['matches'][9], 'id') === [3, 4],
    'World order changed',
);
architectureCheck(
    $cupProjection['stats'][8] === [
        'total' => 2,
        'scheduled' => 1,
        'finalized' => 1,
        'pending' => 1,
        'dates' => ['2026-11-26' => true],
    ],
    'Cup totals changed',
);
architectureCheck(!isset($worldProjection['stats'][9]['dates']), 'World shape changed');
architectureCheck(!isset($cupProjection['stats'][0]), 'Invalid competition included');
$competitionDirectory = \StationX\Yugioh\Application\Presentation\CompetitionDirectoryProjection::build(
    [
        ['id' => 1, 'type' => 'cup', 'name' => 'Cup', 'dependencies' => [['ok' => true]]],
        ['id' => 2, 'type' => 'cup'],
        ['id' => 3],
    ],
);
architectureCheck(
    $competitionDirectory['byType'] === ['cup' => 2, 'special' => 1],
    'Type totals changed',
);
architectureCheck($competitionDirectory['byRuleset'] === ['custom' => 3], 'Ruleset totals changed');
architectureCheck($competitionDirectory['dependencies'][0]['ok'], 'Dependency state changed');

// Qualifier match projection preserves filtering, date ordering and completion criteria.
$qualifierProjection = \StationX\Yugioh\Application\Presentation\QualifierMatchProjection::class;
$qualifierFixtures = [
    'CLASIFICATORIO' => [
        [
            'ronda' => 2,
            'matches' => [
                ['id' => 1, 'competition_id' => 8, 'scheduled_on' => '2026-11-27'],
                [
                    'id' => 2,
                    'competition_id' => 8,
                    'scheduled_on' => '2026-11-26',
                    'score' => '2-0',
                ],
                ['id' => 3, 'competition_id' => 9, 'estado' => 'FINALIZADA'],
                ['id' => 4, 'competition_id' => 8],
            ],
        ],
    ],
];
$qualifierSummary = $qualifierProjection::statistics($qualifierFixtures, [8 => true]);
architectureCheck(
    $qualifierSummary === [8 => ['matches' => 3, 'finished' => 1]],
    'Qualifier totals changed',
);
$qualifierDisplay = $qualifierProjection::display($qualifierFixtures, 8);
architectureCheck(
    array_column($qualifierDisplay['matches'], 'id') === [2, 1, 4],
    'Qualifier order changed',
);
architectureCheck($qualifierDisplay['roundCount'] === 1, 'Qualifier round count changed');
architectureCheck($qualifierDisplay['scheduledCount'] === 2, 'Qualifier scheduled count changed');
architectureCheck($qualifierDisplay['finishedCount'] === 1, 'Qualifier finished count changed');

// Dashboard and qualifier projections preserve the original visible order and totals.
$dashboardProjection = \StationX\Yugioh\Application\Presentation\DashboardProjection::class;
$dashboardEvents = $dashboardProjection::eventsByFormat(
    [
        ['id' => 1, 'status' => 'active', 'division_code' => 'A'],
        ['id' => 2, 'status' => 'draft', 'division_code' => 'A'],
        ['id' => 3, 'status' => 'ACTIVO', 'event_ruleset' => 'tcg'],
    ],
    ['tcg' => 'TCG'],
);
architectureCheck(
    array_keys($dashboardEvents) === ['A', 'TCG'],
    'Dashboard format grouping changed',
);
$dashboardFixture = [
    'APEX' => [
        [
            'ronda' => 1,
            'matches' => [
                [
                    'id' => 1,
                    'a' => 'A',
                    'b' => 'B',
                    'score' => '2-0',
                    'scheduled_on' => '2026-11-26',
                    'competition_id' => 1,
                ],
                ['id' => 2, 'a' => 'A', 'b' => 'C', 'estado' => 'PENDIENTE'],
            ],
        ],
    ],
];
$labeler = static fn(
    array $match,
    string $code,
    int|string $round,
    array $labels,
): string => $code . '-' . $round;
[$events, $active, $total, $finished] = $dashboardProjection::fixtureCounts(
    $dashboardEvents,
    $dashboardFixture,
);
architectureCheck($active === 2 && $total === 2 && $finished === 1, 'Dashboard counts changed');
$recent = $dashboardProjection::recentResults($dashboardFixture, '2026-11-26', [], $labeler);
architectureCheck(count($recent) === 1 && $recent[0]['score'] === '2-0', 'Recent result changed');
[$matches, $byCompetition, $byPhase, $usage, $today] = $dashboardProjection::matches(
    $dashboardFixture,
    [],
    static fn(string $deck): string => $deck,
    $labeler,
    '2026-11-26',
);
architectureCheck(array_column($matches, 'id') === [1, 2], 'Dashboard ordering changed');
architectureCheck(
    $byCompetition[1]['finalized'] === 1 && $byPhase['apex']['pending'] === 1,
    'Dashboard group totals changed',
);
architectureCheck(
    $usage === ['A' => 2, 'B' => 1, 'C' => 1] && count($today) === 1,
    'Dashboard usage changed',
);
$qualifierProgressClass =
    \StationX\Yugioh\Application\Presentation\QualifierProgressProjection::class;
$qualifierDecks = [
    ['id' => 1, 'name' => 'A', 'rank' => 1, 'division' => 'APEX'],
    ['id' => 2, 'name' => 'B', 'rank' => 2, 'division' => 'APEX'],
];
[$pairings, $progress, $ranking, $rankingMap, $ready] = $qualifierProgressClass::build(
    $qualifierDecks,
    2,
    null,
    [],
    0,
    static fn(string $url): string => $url,
);
architectureCheck($pairings[0]['rivals'] === ['B'], 'Qualifier pairing changed');
architectureCheck($progress[0]['remaining'] === 2 && $ready === 0, 'Qualifier progress changed');
architectureCheck(
    \StationX\Yugioh\Application\Presentation\AuditSummaryProjection::build([
        ['action' => 'a'],
        ['action' => 'b'],
        ['action' => 'b'],
    ]) === ['b' => 2, 'a' => 1],
    'Audit aggregation changed',
);

// Preserve selection priority, deck-format mapping and dashboard counters.
$selection = \StationX\Yugioh\Application\Presentation\QualifierSelectionProjection::class;
$normalizeStatus = static fn(string $status): string => strtolower($status);
$qualifierCandidates = [
    ['id' => 1, 'type' => 'qualifier', 'status' => 'active', 'can_execute' => false],
    ['id' => 2, 'type' => 'qualifier', 'status' => 'ACTIVE', 'selected_deck_ids' => [2]],
    [
        'id' => 3,
        'type' => 'qualifier',
        'status' => 'pending_event',
        'qualifier_duels_per_deck' => 9,
    ],
];
[
    $first,
    $qualifiers,
    $pending,
    $active,
    $requested,
    $requestedCompetition,
    $detail,
    $ids,
] = $selection::select(
    $qualifierCandidates,
    3,
    static fn(array $event): bool => $event['status'] === 'pending_event',
    $normalizeStatus,
);
architectureCheck(
    $first['id'] === 1 && $active['id'] === 2 && $requestedCompetition['id'] === 3,
    'Qualifier selection changed',
);
architectureCheck(
    $detail && count($pending) === 1 && count($ids) === 3,
    'Qualifier directory changed',
);
[
    $display,
    $displayId,
    $selectedIds,
    $selectedMap,
    $selectedDecks,
    $required,
    $perDay,
] = $selection::display($requestedCompetition, $active, $first, []);
architectureCheck(
    $displayId === 3 && $required === 5 && $perDay === 1,
    'Qualifier display constraints changed',
);
architectureCheck(
    $selection::status($display, $normalizeStatus)[2] === 'Pendiente evento',
    'Qualifier status label changed',
);
$formats = \StationX\Yugioh\Application\Presentation\DeckFormatProjection::class;
[$rulesetOptions, $formatOptions] = $formats::options(
    [],
    ['custom' => 'Custom', 'tcg' => 'TCG', 'goat' => 'GOAT'],
);
architectureCheck(array_keys($formatOptions) === ['tcg', 'goat'], 'Format options changed');
[$formatIds, $formattedDecks] = $formats::assign(
    [['id' => 1], ['id' => 2]],
    $formatOptions,
    static fn(string $code): array => $code === 'tcg' ? [1, 1] : [1, 2],
);
architectureCheck(
    $formattedDecks[0]['format_codes'] === ['tcg', 'goat'],
    'Format deduplication changed',
);
architectureCheck($formattedDecks[1]['format_codes'] === ['goat'], 'Format assignment changed');
architectureCheck(
    $formats::assign([['id' => 1]], $formatOptions, null)[1][0]['format_codes'] === [],
    'Disconnected projection changed',
);
$dashboardSummary = \StationX\Yugioh\Application\Presentation\DashboardSummaryProjection::build(
    [['status' => 'pending_configuration'], ['status' => 'completed', 'starts_on' => '2026-11-26']],
    [['active' => 1]],
    [['team_id' => 2], ['cover_url' => 'a']],
    $dashboardFixture,
    [],
    $normalizeStatus,
);
architectureCheck(
    array_slice($dashboardSummary, 0, 10) === [1, 0, 1, 0, 1, 1, 1, 1, 1, 1],
    'Dashboard summary changed',
);

// Competition read-model projection uses only its narrow service port, with native order and fallbacks.
$competitionReadPort = new class implements
    \StationX\Yugioh\Application\ReadModel\CompetitionReadModelServices
{
    public array $calls = [];
    public function dependencies(): array
    {
        $this->calls[] = 'dependencies';
        return [['id' => 1]];
    }
    public function progressGroups(): array
    {
        $this->calls[] = 'groups';
        return [7 => ['rows']];
    }
    public function progress(array $rows, bool $knockout): array
    {
        $this->calls[] = 'progress';
        return ['rows' => $rows, 'knockout' => $knockout];
    }
    public function gate(int $id): array
    {
        $this->calls[] = 'gate-' . $id;
        return ['dependencies' => [], 'can_execute' => $id === 7, 'message' => 'gate'];
    }
    public function allowedDecks(int $id, int $divisionId): array
    {
        $this->calls[] = 'allowed-' . $id . '-' . $divisionId;
        if ($id === 8) {
            throw new RuntimeException('unavailable');
        }
        return [1, 2];
    }
    public function standings(int $id): array
    {
        $this->calls[] = 'standings-' . $id;
        return [['id' => 1, 'pts' => 3]];
    }
};
$competitionModel = (new \StationX\Yugioh\Application\ReadModel\ProjectCompetitionReadModel(
    $competitionReadPort,
))->execute(
    [['id' => 2, 'league_competition_id' => 7], ['id' => 2, 'league_competition_id' => 7]],
    [['competition_id' => 7, 'deck_id' => 1], ['competition_id' => 7, 'deck_id' => 2]],
    [['id' => 1, 'team_id' => 5], ['id' => 2, 'team_id' => 5]],
    [['id' => 7, 'division_id' => 3, 'type' => 'cup'], ['id' => 8, 'type' => 'world']],
    [
        'A' => [
            [
                'matches' => [
                    ['competition_id' => 7],
                    ['competition_id' => 8],
                    ['competition_id' => 9],
                    ['competition_id' => 0],
                ],
            ],
        ],
    ],
);
architectureCheck(
    $competitionReadPort->calls === [
        'dependencies',
        'groups',
        'progress',
        'gate-7',
        'allowed-7-3',
        'standings-7',
        'progress',
        'gate-8',
        'allowed-8-0',
        'gate-9',
    ],
    'Competition read service order changed',
);
architectureCheck(
    $competitionModel['competitions'][0]['division_ids'] === [3, 2],
    'Division priority changed',
);
architectureCheck(
    $competitionModel['competitions'][0]['participant_team_count'] === 1,
    'Participant team count changed',
);
architectureCheck(
    $competitionModel['competitions'][0]['ranking_ready'],
    'Ranking readiness changed',
);
architectureCheck(
    !$competitionModel['competitions'][1]['ranking_ready'],
    'Failed standings fallback changed',
);
architectureCheck(
    $competitionModel['fixtures']['A'][0]['matches'][0]['competition_can_execute'],
    'Fixture gate changed',
);
architectureCheck(
    !$competitionModel['fixtures']['A'][0]['matches'][2]['competition_can_execute'],
    'Unknown competition gate changed',
);
architectureCheck(
    $competitionModel['fixtures']['A'][0]['matches'][3]['competition_can_execute'],
    'Unassigned fixture gate changed',
);
$readRules = \StationX\Yugioh\Application\ReadModel\GameRuleReadModelProjection::attachConditions(
    [['id' => 1], ['id' => 2]],
    [['game_rule_id' => 1, 'active' => 1], ['game_rule_id' => 1, 'active' => 0]],
);
architectureCheck(
    $readRules[0]['condition_count'] === 1 && count($readRules[0]['conditions']) === 2,
    'Rule conditions changed',
);
architectureCheck($readRules[1]['conditions'] === [], 'Empty rule conditions changed');

// Segregated read-model adapters retain the exact native query method tokens.
$readModelOriginals = json_decode(
    file_get_contents(__DIR__ . '/fixtures/platform-read-model-query-originals.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$readModelTokens = static function (string $source): array {
    return array_values(
        array_filter(
            token_get_all('<?php ' . $source),
            static fn($token): bool => !is_array($token) ||
                !in_array($token[0], [T_WHITESPACE, T_OPEN_TAG], true),
        ),
    );
};
foreach ($readModelOriginals as $method => $original) {
    $readerClass =
        'StationX\\Yugioh\\Infrastructure\\Persistence\\ReadModel\\' . $original['adapter'];
    $reflection = new ReflectionMethod($readerClass, $method);
    $readerLines = file($reflection->getFileName());
    $actualSource = implode(
        '',
        array_slice(
            $readerLines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ),
    );
    // Drop token line numbers while retaining token kinds and literal values.
    $withoutLine = static fn(array $tokens): array => array_map(
        static fn($token) => is_array($token) ? [$token[0], $token[1]] : $token,
        $tokens,
    );
    architectureCheck(
        $withoutLine($readModelTokens($actualSource)) ===
            $withoutLine($readModelTokens($original['source'])),
        'Read-model query changed: ' . $method,
    );
}
$aggregateReader = new ReflectionClass(
    \StationX\Yugioh\Application\ReadModel\PlatformReadModelReader::class,
);
architectureCheck(
    count($aggregateReader->getMethods()) === count($readModelOriginals),
    'Read port coverage changed',
);
architectureCheck(count($aggregateReader->getInterfaceNames()) === 4, 'Read ports not segregated');

// The live-duel state policy has deterministic time and no HTTP or database dependency.
$livePolicy = \StationX\Yugioh\Domain\Matches\LiveDuelState::class;
$liveNow = static fn(): int => 5000;
$liveMilliseconds = static fn(): float => 5000.0;
$liveAtom = static fn(): string => '2026-11-26T19:00:00-05:00';
$liveMatch = ['status' => 'EN CURSO'];
$initialDuel = $livePolicy::initial($liveMatch, $liveNow);
architectureCheck(
    $initialDuel['duel_number'] === 1 && $initialDuel['lp_a'] === 8000,
    'Initial duel changed',
);
$drawDuel = $livePolicy::initial(
    [
        'd1' => 'E',
        'duel_details' => json_encode([
            ['duel' => 2, 'duel_started' => true, 'starts_player' => 'B', 'timer_running' => true],
        ]),
    ],
    $liveNow,
);
architectureCheck(
    $drawDuel['duel_number'] === 2 &&
        $drawDuel['status'] === 'setup' &&
        !$drawDuel['timer_running'],
    'Draw reroll guard changed',
);
$liveState = $livePolicy::normalize(
    [
        'status' => 'running',
        'starts_player' => 'A',
        'active_side' => 'A',
        'timer_running' => true,
        'timer_started_at' => 1000,
        'elapsed_seconds' => 10,
        'lp_a' => 8000,
        'lp_b' => 8000,
    ],
    $liveNow,
);
$liveTransition = static fn(
    array $state,
    string $actor,
    string $op,
    array $payload = [],
): ?array => $livePolicy::transition(
    $state,
    $liveMatch,
    $actor,
    $op,
    $payload,
    $liveNow,
    $liveMilliseconds,
    $liveAtom,
);
$endedTurn = $liveTransition($liveState, 'A', 'end_turn');
architectureCheck(
    $endedTurn['active_side'] === 'B' && $endedTurn['turn_count'] === 1,
    'Turn progression changed',
);
architectureCheck(
    $endedTurn['turn_history'][0]['elapsed_seconds'] === 14,
    'Turn elapsed calculation changed',
);
architectureCheck(
    $endedTurn['turn_history'][0]['ended_at'] === $liveAtom(),
    'Turn timestamp changed',
);
$halfLp = $liveTransition($liveState, 'A', 'lp_change', ['kind' => 'half']);
architectureCheck(
    $halfLp['lp_a'] === 4000 && $halfLp['lp_history'][0]['before'] === 8000,
    'LP operation changed',
);
$resetLp = $liveTransition($halfLp, 'judge', 'lp_change', ['side' => 'A', 'kind' => 'reset']);
architectureCheck(
    $resetLp['lp_a'] === 8000 && count($resetLp['lp_history']) === 2,
    'LP reset/history changed',
);
$surrendered = $liveTransition($liveState, 'A', 'surrender');
architectureCheck(
    $surrendered['status'] === 'completed' && !$surrendered['timer_running'],
    'Surrender changed',
);
architectureCheck($surrendered['surrendered_at'] === $liveAtom(), 'Surrender timestamp changed');
$synced = $liveTransition($surrendered, 'judge', 'sync', $liveState);
architectureCheck(
    $synced['surrendered_by'] === 'A' && $synced['status'] === 'completed',
    'Surrender overwritten',
);
architectureCheck(
    $liveTransition($liveState, 'judge', 'sync', ['correction_at_ms' => 1]) === null,
    'Correction conflict changed',
);
foreach (
    [['B', 'end_turn'], ['B', 'surrender'], ['judge', 'surrender']]
    as [$invalidActor, $invalidOp]
) {
    try {
        $liveTransition($liveState, $invalidActor, $invalidOp);
        architectureCheck(false, 'Invalid actor accepted');
    } catch (RuntimeException $error) {
        architectureCheck(
            $error->getMessage() !== 'Invalid actor accepted',
            'Invalid actor accepted',
        );
    }
}
$boundedDuel = $livePolicy::normalize(
    ['duel_number' => 9, 'lp_a' => -1, 'lp_b' => 999999, 'status' => 'running'],
    $liveNow,
);
architectureCheck(
    $boundedDuel['duel_number'] === 5 &&
        $boundedDuel['lp_a'] === 0 &&
        $boundedDuel['lp_b'] === 99999,
    'State bounds changed',
);
architectureCheck(
    $boundedDuel['status'] === 'setup' && $boundedDuel['updated_at_ms'] === 5000,
    'State normalization changed',
);

// The live-duel loader preserves lazy creation, borrowed lock selection and authoritative duel recovery.
$liveStore = new class implements \StationX\Yugioh\Application\Matches\LiveDuelReadStore {
    public array $calls = [];
    public array $matchRow = [];
    public array $stateRow = [];
    public function match(int $matchId): array
    {
        $this->calls[] = 'match-' . $matchId;
        return $this->matchRow;
    }
    public function state(int $matchId, bool $forUpdate): array
    {
        $this->calls[] = $forUpdate ? 'locked-state' : 'state';
        return $this->stateRow;
    }
    public function insertInitial(int $matchId, array $state): void
    {
        $this->calls[] = 'insert';
        $this->stateRow = [
            'revision' => 1,
            'state_json' => json_encode($state),
            'updated_by' => 'judge',
        ];
    }
};
$liveLoader = new \StationX\Yugioh\Application\Matches\LoadLiveDuel($liveStore);
architectureCheck($liveLoader->execute(9, true, $liveNow) === [], 'Missing match changed');
architectureCheck($liveStore->calls === ['match-9'], 'Missing match should not query state');
$liveStore->calls = [];
$liveStore->matchRow = ['status' => 'EN CURSO', 'd1' => 'A'];
$loaded = $liveLoader->execute(9, true, $liveNow);
architectureCheck(
    $liveStore->calls === ['match-9', 'locked-state', 'insert', 'locked-state'],
    'State creation order or lock changed',
);
architectureCheck(
    $loaded['state']['duel_number'] === 2 && $loaded['revision'] === 1,
    'Initial persisted state changed',
);
$liveStore->calls = [];
$liveStore->stateRow = [
    'revision' => 4,
    'state_json' => json_encode(['duel_number' => 1]),
    'updated_by' => 'A',
];
$loaded = $liveLoader->execute(9, false, $liveNow);
architectureCheck($liveStore->calls === ['match-9', 'state'], 'Existing state was written');
architectureCheck(
    $loaded['state']['duel_number'] === 2 && $loaded['revision'] === 4,
    'Authoritative duel recovery changed',
);
$liveStore->matchRow['status'] = 'FINALIZADA';
architectureCheck(
    $liveLoader->execute(9, false, $liveNow)['state']['duel_number'] === 1,
    'Finalized match state changed',
);
$liveStore->matchRow = ['status' => 'EN CURSO', 'd1' => 'E'];
$liveStore->stateRow['state_json'] = json_encode([
    'duel_number' => 2,
    'status' => 'running',
    'starts_player' => 'A',
    'timer_running' => true,
]);
$loaded = $liveLoader->execute(9, false, $liveNow);
architectureCheck(
    $loaded['state']['status'] === 'setup' && !$loaded['state']['timer_running'],
    'Stored draw reroll guard changed',
);

// Write use case relies on narrow read/write ports and preserves lock, update and audit order.
$liveChangeStore = new class implements
    \StationX\Yugioh\Application\Matches\LiveDuelReadStore,
    \StationX\Yugioh\Application\Matches\LiveDuelWriteStore
{
    public array $calls = [];
    public array $row = [];
    public bool $failUpdate = false;
    public function match(int $matchId): array
    {
        $this->calls[] = 'match';
        return ['status' => 'EN CURSO'];
    }
    public function state(int $matchId, bool $forUpdate): array
    {
        $this->calls[] = $forUpdate ? 'locked-state' : 'state';
        return $this->row;
    }
    public function insertInitial(int $matchId, array $state): void
    {
        throw new RuntimeException('Unexpected initialization');
    }
    public function requireOpen(int $matchId, bool $forUpdate): void
    {
        $this->calls[] = $forUpdate ? 'locked-season' : 'season';
    }
    public function update(int $matchId, array $state, int $revision, string $actor): void
    {
        $this->calls[] = 'update';
        if ($this->failUpdate) {
            throw new RuntimeException('write failure');
        }
        $this->row = [
            'revision' => $revision,
            'state_json' => json_encode($state),
            'updated_by' => $actor,
        ];
    }
    public function recordSurrender(int $matchId, array $state, string $actor): void
    {
        $this->calls[] = 'audit';
    }
};
$liveChange = new \StationX\Yugioh\Application\Matches\ApplyLiveDuelChange(
    new \StationX\Yugioh\Application\Matches\LoadLiveDuel($liveChangeStore),
    $liveChangeStore,
);
$liveChangeStore->row = ['revision' => 3, 'state_json' => json_encode($liveState)];
$change = static fn(
    int $revision,
    string $actor,
    string $operation,
    array $payload = [],
): ?array => $liveChange->execute(
    9,
    $revision,
    $actor,
    $operation,
    $payload,
    $liveNow,
    $liveMilliseconds,
    $liveAtom,
);
architectureCheck($change(2, 'A', 'surrender') === null, 'Stale revision accepted');
architectureCheck(
    $liveChangeStore->calls === ['locked-season', 'match', 'locked-state'],
    'Conflict wrote state',
);
$liveChangeStore->calls = [];
architectureCheck(
    $change(3, 'judge', 'sync', ['correction_at_ms' => 1]) === null,
    'Correction conflict accepted',
);
architectureCheck(
    $liveChangeStore->calls === ['locked-season', 'match', 'locked-state'],
    'Correction conflict wrote state',
);
$liveChangeStore->calls = [];
$changedDuel = $change(3, 'A', 'surrender');
architectureCheck(
    $changedDuel['revision'] === 4 && $changedDuel['state']['status'] === 'completed',
    'Write response changed',
);
architectureCheck(
    $liveChangeStore->calls === ['locked-season', 'match', 'locked-state', 'update', 'audit'],
    'Surrender write/audit order changed',
);
$liveChangeStore->row = ['revision' => 3, 'state_json' => json_encode($liveState)];
$liveChangeStore->calls = [];
$liveChangeStore->failUpdate = true;
try {
    $change(3, 'A', 'surrender');
    architectureCheck(false, 'Write failure swallowed');
} catch (RuntimeException $error) {
    architectureCheck($error->getMessage() === 'write failure', 'Write failure changed');
}
architectureCheck(
    $liveChangeStore->calls === ['locked-season', 'match', 'locked-state', 'update'],
    'Audit occurred after failed write',
);

// TXT import: compare the preserved parser and keep inventory creation atomic.
$templateOriginal = json_decode(
    file_get_contents(__DIR__ . '/fixtures/deck-template-original.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
eval(
    str_replace(
        'function ygo_parse_deck_template(',
        'function architectureOriginalDeckTemplate(',
        $templateOriginal['parser'],
    )
);
$templateValid =
    "# STATIONX-DECK-TEMPLATE/1\n[DECK]\nnombre=Test\narquetipo=Machine\n[MAIN]\n2;Card A\n[EXTRA]\n1;Card B\n[SIDE]\n1;Card C\n[RESERVA]\n3;Card D";
foreach (
    [
        $templateValid,
        "\xEF\xBB\xBF" . $templateValid,
        str_replace("\n", "\r\n", $templateValid),
        '',
        'invalid',
        str_replace('2;Card A', '4;Card A', $templateValid),
        str_replace('[RESERVA]', '[UNKNOWN]', $templateValid),
    ]
    as $templateCase
) {
    $evaluateTemplate = static function (callable $parse) use ($templateCase): array {
        try {
            return ['ok' => $parse($templateCase)];
        } catch (InvalidArgumentException $error) {
            return ['error' => $error->getMessage()];
        }
    };
    architectureCheck(
        $evaluateTemplate('architectureOriginalDeckTemplate') ===
            $evaluateTemplate([\StationX\Yugioh\Domain\Decks\DeckTemplate::class, 'parse']),
        'Template parser changed',
    );
}
$templateWriter = new class implements \StationX\Yugioh\Application\Decks\DeckTemplateWriter {
    public array $calls = [];
    public bool $exists = false;
    public bool $fail = false;
    public function activeNameExists(string $name): bool
    {
        $this->calls[] = ['exists', $name];
        return $this->exists;
    }
    public function create(array $data): int
    {
        $this->calls[] = ['create', $data['name']];
        return 42;
    }
    public function appendCards(int $id, array $cards): void
    {
        $this->calls[] = ['cards', $id, array_column($cards, 'section')];
        if ($this->fail) {
            throw new RuntimeException('card write failed');
        }
    }
};
$templateTx = new class implements \StationX\Yugioh\Application\Shared\TransactionBoundary {
    public array $calls = [];
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
};
$templateUsecase = new \StationX\Yugioh\Application\Decks\ImportDeckTemplate(
    $templateWriter,
    $templateTx,
);
architectureCheck(
    $templateUsecase->execute($templateValid) === 'deck_42',
    'Template response changed',
);
architectureCheck(
    $templateWriter->calls === [
        ['exists', 'Test'],
        ['create', 'Test'],
        ['cards', 42, ['main', 'extra', 'side', 'tech']],
    ],
    'Template write order/sections changed',
);
architectureCheck($templateTx->calls === ['begin', 'commit'], 'Template transaction changed');
$templateWriter->exists = true;
$templateWriter->calls = [];
$templateTx->calls = [];
try {
    $templateUsecase->execute($templateValid);
    architectureCheck(false, 'Duplicate deck accepted');
} catch (InvalidArgumentException $error) {
    architectureCheck(
        $error->getMessage() === 'Ya existe un deck activo con ese nombre.',
        'Duplicate deck message changed',
    );
}
architectureCheck(
    $templateWriter->calls === [['exists', 'Test']] && $templateTx->calls === [],
    'Duplicate deck started write',
);
$templateWriter->exists = false;
$templateWriter->fail = true;
try {
    $templateUsecase->execute($templateValid);
    architectureCheck(false, 'Card failure swallowed');
} catch (RuntimeException $error) {
    architectureCheck($error->getMessage() === 'card write failed', 'Card error changed');
}
architectureCheck(
    $templateTx->calls === ['begin', 'rollback'],
    'Failed template did not roll back',
);

// Admission quotas retain exact deterministic remainders; write coordination uses the same transaction boundary.
$admissionOriginal = json_decode(
    file_get_contents(__DIR__ . '/fixtures/admission-write-originals.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
eval(
    str_replace(
        'function ygo_admission_quotas(',
        'function architectureOriginalAdmissionQuotas(',
        $admissionOriginal['quotas'],
    )
);
$quotaRows = [['mode' => 'percentage', 'value' => 50], ['mode' => 'percentage', 'value' => 50]];
foreach (range(2, 64) as $quotaCapacity) {
    architectureCheck(
        \StationX\Yugioh\Domain\Competitions\AdmissionQuotaPolicy::quotas(
            $quotaRows,
            $quotaCapacity,
        ) === architectureOriginalAdmissionQuotas($quotaRows, $quotaCapacity),
        'Admission quota changed',
    );
}
$admissionInput = \StationX\Yugioh\Domain\Competitions\AdmissionFormInput::parse([
    'competition_id' => 7,
    'admission_sources_json' => '[{"source_id":3}]',
    'admission_capacity' => '2',
]);
architectureCheck(
    $admissionInput === [7, [['source_id' => 3]], 2],
    'Admission form contract changed',
);
$admissionStore = new class implements \StationX\Yugioh\Application\Competitions\AdmissionWriteStore
{
    public array $calls = [];
    public array $competition = ['season_id' => 1, 'status' => 'active'];
    public bool $fail = false;
    public function lockedCompetition(int $target): array
    {
        $this->calls[] = 'lock';
        return $this->competition;
    }
    public function requireOpen(int $seasonId): void
    {
        $this->calls[] = 'season';
    }
    public function assertMutable(int $target): void
    {
        $this->calls[] = 'mutable';
    }
    public function normalizedStatus(string $status): string
    {
        $this->calls[] = 'status';
        return $status;
    }
    public function assertNoCycle(int $target, array $sources): void
    {
        $this->calls[] = 'cycle';
    }
    public function resolve(array $competition, array $admission): array
    {
        $this->calls[] = 'resolve';
        return ['ids' => [4, 5], 'quotas' => [1, 1]];
    }
    public function validateRoster(int $target, array $ids): void
    {
        $this->calls[] = 'validate';
        if ($this->fail) {
            throw new RuntimeException('roster failure');
        }
    }
    public function replaceDecks(int $target, array $ids): void
    {
        $this->calls[] = ['decks', $ids];
    }
    public function clearDependencies(int $target): void
    {
        $this->calls[] = 'clear';
    }
    public function writeDependencies(array $competition, int $target, array $dependencies): void
    {
        $this->calls[] = ['dependencies', $dependencies];
    }
    public function setParticipantSource(int $target): void
    {
        $this->calls[] = 'source';
    }
    public function store(array $competition, array $admission): void
    {
        $this->calls[] = 'store';
    }
    public function refreshGate(int $target): void
    {
        $this->calls[] = 'gate';
    }
    public function audit(int $target, array $admission): void
    {
        $this->calls[] = 'audit';
    }
};
$admissionUsecase = new \StationX\Yugioh\Application\Competitions\SaveEventAdmission(
    $admissionStore,
    $templateTx,
);
$templateTx->calls = [];
$admissionRows = [['source_id' => 3], ['source_id' => 3]];
$admissionResult = $admissionUsecase->execute(7, $admissionRows, 2);
architectureCheck($admissionResult['selection']['ids'] === [4, 5], 'Admission selection changed');
architectureCheck(
    $admissionStore->calls === [
        'lock',
        'season',
        'mutable',
        'status',
        'cycle',
        'resolve',
        'validate',
        ['decks', [4, 5]],
        'clear',
        ['dependencies', [3 => 2]],
        'source',
        'store',
        'gate',
        'audit',
    ],
    'Admission order changed',
);
architectureCheck($templateTx->calls === ['begin', 'commit'], 'Admission transaction changed');
$admissionStore->calls = [];
$templateTx->calls = [];
$admissionStore->fail = true;
try {
    $admissionUsecase->execute(7, $admissionRows, 2);
    architectureCheck(false, 'Invalid roster accepted');
} catch (RuntimeException $error) {
    architectureCheck($error->getMessage() === 'roster failure', 'Admission error changed');
}
architectureCheck(
    $admissionStore->calls === [
        'lock',
        'season',
        'mutable',
        'status',
        'cycle',
        'resolve',
        'validate',
    ],
    'Invalid roster modified data',
);
architectureCheck($templateTx->calls === ['begin', 'rollback'], 'Admission rollback changed');

// Source verification and selection: compare native ordering, signatures, guards and dependencies.
$sourceReader = new class implements \StationX\Yugioh\Application\Competitions\AdmissionSourceReader
{
    public array $calls = [];
    public string $status = 'completed';
    public string $format = 'tcg_advanced';
    public function competition(int $id, bool $lock): array
    {
        $this->calls[] = ['competition', $id, $lock];
        return [
            'id' => $id,
            'status' => $this->status,
            'type' => 'cup',
            'name' => 'Source ' . $id,
            'season_id' => 1,
            'execution_model' => 'single_elimination',
            'event_ruleset' => $this->format,
        ];
    }
    public function matches(int $id): array
    {
        $this->calls[] = 'matches';
        return [
            [
                'id' => 1,
                'division_id' => 1,
                'deck_a_id' => 101,
                'deck_b_id' => 102,
                'phase' => 'cup',
                'round_number' => 1,
                'status' => 'FINALIZADA',
                'result' => 'A',
                'score' => '2-0',
            ],
        ];
    }
    public function normalizedStatus(string $status): string
    {
        $this->calls[] = 'status';
        return $status;
    }
    public function workflow(array $competition): ?array
    {
        $this->calls[] = 'workflow';
        return null;
    }
    public function divisions(int $id): array
    {
        $this->calls[] = 'divisions';
        return [];
    }
    public function workflowResult(int $matchId): array
    {
        $this->calls[] = 'workflow-result';
        return [];
    }
    public function standings(int $id): array
    {
        $this->calls[] = 'standings';
        return [['id' => 101, 'pts' => 3], ['id' => 102, 'pts' => 0]];
    }
    public function divisionBelongs(int $division, int $id, int $seasonId): bool
    {
        $this->calls[] = 'division';
        return true;
    }
    public function honors(int $id): array
    {
        $this->calls[] = 'honors';
        return [['champion_deck_id' => 101, 'runner_up_deck_id' => 102]];
    }
    public function finals(int $id): array
    {
        $this->calls[] = 'finals';
        return [['deck_a_id' => 101, 'deck_b_id' => 102, 'result' => 'A']];
    }
    public function roster(int $id): array
    {
        $this->calls[] = 'roster';
        return [['deck_id' => 101, 'sort_order' => 1], ['deck_id' => 102, 'sort_order' => 2]];
    }
    public function rulesets(): array
    {
        $this->calls[] = 'rulesets';
        return [['code' => 'tcg_advanced', 'label' => 'TCG']];
    }
    public function deckFormats(array $ids): array
    {
        $this->calls[] = ['formats', $ids];
        return array_map(
            static fn($id) => ['id' => $id, 'style' => 'TCG', 'event_ruleset' => 'tcg_advanced'],
            $ids,
        );
    }
    public function edges(): array
    {
        return [];
    }
    public function baseTemplate(string $code): string
    {
        $this->calls[] = 'template';
        return $code;
    }
    public function validateFormatRoster(string $format, array $ids): void
    {
        $this->calls[] = ['validate', $format, $ids];
    }
};
$formatUsecase = new \StationX\Yugioh\Application\Competitions\ResolveAdmissionDeckFormat(
    $sourceReader,
);
$readSource = new \StationX\Yugioh\Application\Competitions\ReadAdmissionSource(
    $sourceReader,
    $formatUsecase,
);
$sourceResult = $readSource->execute(3, 0, true);
architectureCheck(
    $sourceResult['champion_id'] === 101 && $sourceResult['runner_up_id'] === 102,
    'Admission title changed',
);
architectureCheck(
    array_column($sourceResult['ranking'], 'id') === [101, 102],
    'Admission ranking changed',
);
architectureCheck(
    $sourceReader->calls === [
        ['competition', 3, true],
        'status',
        'matches',
        'workflow',
        'standings',
        'honors',
        'finals',
        'rulesets',
        ['formats', [101, 102]],
        'roster',
    ],
    'Admission source read order changed',
);
$sourceReader->calls = [];
$resolveAdmission = new \StationX\Yugioh\Application\Competitions\ResolveEventAdmission(
    $sourceReader,
    $readSource,
);
$resolvedAdmission = $resolveAdmission->execute(
    ['id' => 7, 'event_ruleset' => 'tcg_advanced'],
    [
        'version' => 'STATION_X_ADMISSION_V1',
        'capacity' => 2,
        'sources' => [['source_id' => 3, 'mode' => 'top_n', 'value' => 2]],
    ],
    true,
);
architectureCheck(
    $resolvedAdmission['ids'] === [101, 102] && $resolvedAdmission['quotas'] === [2],
    'Admission selection changed',
);
architectureCheck(
    $resolvedAdmission['signatures']['3:0'] === $sourceResult['signature'],
    'Admission signature changed',
);
architectureCheck(
    end($sourceReader->calls) === ['validate', 'tcg_advanced', [101, 102]],
    'Admission roster validation changed',
);
$sourceReader->status = 'active';
$sourceReader->calls = [];
try {
    $readSource->execute(3);
    architectureCheck(false, 'Unfinished source accepted');
} catch (InvalidArgumentException $error) {
    architectureCheck(
        $error->getMessage() === 'La fuente debe ser un evento finalizado.',
        'Source guard changed',
    );
}
architectureCheck(
    $sourceReader->calls === [['competition', 3, false], 'status'],
    'Unfinished source read results',
);
\StationX\Yugioh\Domain\Competitions\AdmissionDependencyPolicy::assertNoCycle(
    7,
    [['source_id' => 3]],
    [['competition_id' => 3, 'depends_on_competition_id' => 2]],
);
try {
    \StationX\Yugioh\Domain\Competitions\AdmissionDependencyPolicy::assertNoCycle(
        7,
        [['source_id' => 3]],
        [['competition_id' => 3, 'depends_on_competition_id' => 7]],
    );
    architectureCheck(false, 'Cycle accepted');
} catch (InvalidArgumentException $error) {
    architectureCheck(
        $error->getMessage() === 'Las fuentes forman una dependencia circular.',
        'Cycle error changed',
    );
}

// Admission configuration retains required, freeze, view, and result protection contracts.
$sourceReader->status = 'completed';
$admissionConfig = new class implements
    \StationX\Yugioh\Application\Competitions\AdmissionConfigurationStore
{
    public ?array $value = null;
    public array $calls = [];
    public function load(array $competition, bool $lock = false): ?array
    {
        $this->calls[] = ['load', $lock];
        return $this->value;
    }
    public function store(array $competition, array $admission): void
    {
        $this->calls[] = 'store';
        $this->value = $admission;
    }
};
$configCase = new \StationX\Yugioh\Application\Competitions\AdmissionConfiguration(
    $admissionConfig,
    $sourceReader,
    $resolveAdmission,
);
$targetCompetition = ['id' => 7, 'event_ruleset' => 'tcg_advanced'];
architectureCheck(
    $configCase->requireParticipants($targetCompetition, [5, 4]) === [5, 4],
    'Direct admission changed',
);
architectureCheck(
    $configCase->view($targetCompetition)['message'] === 'Inscripcion directa.',
    'Direct admission label changed',
);
try {
    $configCase->requireParticipants(
        $targetCompetition + ['participant_source' => 'PREVIOUS_EVENT'],
        [],
    );
    architectureCheck(false, 'Required admission ignored');
} catch (InvalidArgumentException $error) {
    architectureCheck(
        str_contains($error->getMessage(), 'Configura Fuentes'),
        'Required admission error changed',
    );
}
$admissionConfig->value = [
    'version' => 'STATION_X_ADMISSION_V1',
    'capacity' => 2,
    'sources' => [['source_id' => 3, 'mode' => 'top_n', 'value' => 2]],
    'selection' => $resolvedAdmission,
    'frozen' => false,
];
architectureCheck($configCase->view($targetCompetition)['ok'], 'Valid admission view changed');
architectureCheck(
    $configCase->requireParticipants($targetCompetition, [102, 101]) === [101, 102],
    'Verified participants changed',
);
architectureCheck(
    $admissionConfig->value['frozen'] && end($admissionConfig->calls) === 'store',
    'Freeze not persisted',
);
$admissionConfig->value['selection']['ids'] = [999];
architectureCheck(!$configCase->view($targetCompetition)['ok'], 'Stale source accepted');
try {
    $configCase->requireParticipants($targetCompetition, [101, 102]);
    architectureCheck(false, 'Frozen source changed');
} catch (InvalidArgumentException $error) {
    architectureCheck(
        str_contains($error->getMessage(), 'despues de programar'),
        'Frozen source error changed',
    );
}
$guardStore = new class implements
    \StationX\Yugioh\Application\Competitions\AdmissionResultGuardStore
{
    public int $source = 3;
    public array $calls = [];
    public function competitionForMatch(int $matchId): int
    {
        $this->calls[] = 'match';
        return $this->source;
    }
    public function lockCompetition(int $source): void
    {
        $this->calls[] = 'lock';
    }
    public function lockedConfigurations(): array
    {
        $this->calls[] = 'configs';
        return ['{"frozen":true,"sources":[{"source_id":3}]}'];
    }
};
$resultGuard = new \StationX\Yugioh\Application\Competitions\GuardAdmissionResult($guardStore);
try {
    $resultGuard->execute(4);
    architectureCheck(false, 'Protected result editable');
} catch (InvalidArgumentException $error) {
    architectureCheck(
        str_contains($error->getMessage(), 'ya clasifico'),
        'Result protection changed',
    );
}
architectureCheck($guardStore->calls === ['match', 'lock', 'configs'], 'Result lock order changed');
$guardStore->source = 0;
$guardStore->calls = [];
$resultGuard->execute(4);
architectureCheck($guardStore->calls === ['match'], 'Unassigned match locked a source');
$stageStore = new class implements \StationX\Yugioh\Application\Competitions\AdmissionStageStore {
    public array $calls = [];
    public function pendingIds(array $competition, ?string $phase, ?int $round): array
    {
        $this->calls[] = ['pending', $phase, $round];
        return [3, 4];
    }
    public function clearDates(array $ids): void
    {
        $this->calls[] = ['clear', $ids];
    }
    public function lastDate(array $competition): string|false|null
    {
        $this->calls[] = 'last';
        return '2026-11-28';
    }
    public function nextDate(string $base, string $pattern, int $index, int $perDay): string
    {
        $this->calls[] = ['date', $base, $index, $perDay];
        return $base;
    }
    public function slot(
        array $competition,
        int $matchId,
        string $date,
        string $pattern,
        int $perDay,
    ): array {
        $this->calls[] = ['slot', $matchId];
        return [$date, '19:00:00'];
    }
    public function updateSlot(int $matchId, string $date, string $time): void
    {
        $this->calls[] = ['update', $matchId, $date, $time];
    }
    public function validateSeason(int $competitionId): void
    {
        $this->calls[] = ['validate', $competitionId];
    }
};
$stageClock = new class implements \StationX\Yugioh\Application\Shared\Clock {
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-27T12:00:00-05:00');
    }
};
$stageCase = new \StationX\Yugioh\Application\Competitions\ScheduleAdmissionStage(
    $admissionConfig,
    $stageStore,
    $stageClock,
);
$stageCase->execute($targetCompetition, '2026-11-25', 'manual', 2, 'cup', 2);
architectureCheck(
    $stageStore->calls === [
        ['pending', 'cup', 2],
        ['clear', [3, 4]],
        'last',
        ['date', '2026-11-28', 1, 1],
        ['date', '2026-11-28', 0, 2],
        ['slot', 3],
        ['update', 3, '2026-11-28', '19:00:00'],
        ['date', '2026-11-28', 1, 2],
        ['slot', 4],
        ['update', 4, '2026-11-28', '19:00:00'],
        ['validate', 7],
    ],
    'Stage scheduling order or scope changed',
);
$admissionConfig->value = null;
$stageStore->calls = [];
$stageCase->execute($targetCompetition, '2026-11-25', 'manual', 2);
architectureCheck($stageStore->calls === [], 'Direct event was rescheduled by admission');

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
