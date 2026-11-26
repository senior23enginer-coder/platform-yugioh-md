<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface PhaseAdvanceReader
{
    public function lock(int $competitionId): ?array;

    public function activeCompetition(int $competitionId): array;

    public function registeredDecks(int $competitionId): array;

    public function matchCount(int $competitionId, string $phase): int;

    public function phaseCounts(int $competitionId, string $phase): array;

    public function roundResults(int $competitionId, string $phase, int $round): array;

    public function latestRound(int $competitionId, string $phase): int;

    public function pendingCount(int $competitionId, string $phase, int $round): int;

    public function standings(int $competitionId): array;

    public function cupByes(int $competitionId, int $round): array;
}
