<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface LegacyPhaseStore
{
    public function firstDivisionId(): int;

    public function finishedResults(string $phase): array;

    public function nextRound(int $divisionId, string $phase): int;

    public function insert(int $divisionId, string $phase, int $round, iterable $rows): int;
}
