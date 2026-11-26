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
