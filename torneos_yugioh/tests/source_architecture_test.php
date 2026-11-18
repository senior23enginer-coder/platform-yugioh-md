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

function architectureCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

// Characterization snapshots were produced by the existing functions before extraction.
$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/pairings-characterization.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($cases as $case) {
    $decks = [];
    for ($i = 0; $i < $case['count']; $i++) $decks[] = [$i + 1, 'Deck ' . ($i + 1), ''];
    architectureCheck(FixtureBuilder::build($decks) === $case['fixtures'], 'Legacy fixtures changed');
    architectureCheck(RoundRobinPairings::rounds($decks) === $case['single'], 'Single round order changed');
    architectureCheck(RoundRobinPairings::rounds($decks, true) === $case['double'], 'Return-leg order changed');
    architectureCheck(KnockoutSize::forParticipants($case['count']) === $case['knockout'], 'Bracket sizing changed');
}

// The use case accepts a test double: it cannot depend on the PDO adapter or globals.
$repository = new class implements SeasonRepository {
    public int $reads = 0;
    public function newestFirst(): iterable {
        $this->reads++;
        yield new Season(3, 'Future draft', 'draft');
        yield new Season(2, '2027 B', 'active');
        yield new Season(1, '2026', 'active');
    }
};
architectureCheck((new GetCurrentSeason($repository))->execute()?->id === 2, 'Wrong active season');
architectureCheck($repository->reads === 1, 'Read port not used exactly once');
$loaded = new LoadedSeasonRepository([['id'=>2,'name'=>'  ','status'=>'active'],['id'=>1,'name'=>'2027 B','status'=>'active']]);
architectureCheck((new GetCurrentSeason($loaded))->execute()?->name === '2027 B', 'Blank active season not skipped');
architectureCheck((new GetCurrentSeason(new LoadedSeasonRepository([])))->execute() === null, 'No-season contract changed');
architectureCheck(!function_exists('ensure_ygo_tables'), 'Loading the core must not initialize legacy persistence');

// Enforce inward dependencies, including code added after this refactor.
foreach (['Domain', 'Application'] as $layer) {
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src/' . $layer)) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
        if (!is_array($token)) continue;
        architectureCheck(!($token[0] === T_VARIABLE && str_starts_with($token[1], '$_')), 'HTTP/session global in domain');
        architectureCheck(!($token[0] === T_STRING && $token[1] === 'PDO'), 'PDO in domain');
        if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            architectureCheck(!str_contains($token[1], '\\Infrastructure\\'), 'Infrastructure dependency in domain');
        }
    }
}
}

echo "OK: domain characterization, injected season port, no persistence startup, inward dependencies.\n";
