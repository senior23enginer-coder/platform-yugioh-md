<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface KnockoutRoundServices
{
    public function fixedProgram(): array;

    public function saveBye(string $key, string $value): void;

    public function scheduleAdmission(array $competition, string $start, string $pattern, int $limit, string $phase, int $round): void;

    public function scheduleFixedRound(int $competitionId, string $phase, int $round): void;

    public function rebuildCalendar(int $seasonId, int $competitionId, string $phase): void;
}
