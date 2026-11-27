<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface AdmissionResultGuardStore
{
    public function competitionForMatch(int $matchId): int;

    public function lockCompetition(int $source): void;

    public function lockedConfigurations(): array;
}
