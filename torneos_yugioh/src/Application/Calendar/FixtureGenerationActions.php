<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface FixtureGenerationActions
{
    public function workflow(array $competition, array $ids, string $startDate, string $pattern, int $matchesPerDay): int;

    public function cup(array $competition, array $ids, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay): int;

    public function qualifier(array $competition, array $ids, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $duels, int $matchesPerDay, string $phase): int;

    public function saveBye(string $key, string $value): void;

    public function admissionStage(array $competition, string $startDate, string $pattern, int $matchesPerDay): void;

    public function rebuildCalendar(int $seasonId, int $competitionId, string $phase): int;
}
