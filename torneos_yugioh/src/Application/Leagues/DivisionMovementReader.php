<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface DivisionMovementReader
{
    public function divisions(int $competitionId): array;

    public function lockCompetition(int $competitionId): ?array;

    public function alreadyApplied(int $competitionId): bool;

    public function hasPendingMatches(int $competitionId): bool;

    public function standings(int $competitionId): array;

    public function originalDivisions(): array;
}
