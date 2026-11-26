<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface StandingSnapshotReader
{
    public function phases(int $competitionId): array;

    public function standings(int $competitionId, ?string $phase): array;

    public function maxRound(int $competitionId, ?string $phase): int;

    public function missingCompetitionIds(int $seasonId): array;
}
