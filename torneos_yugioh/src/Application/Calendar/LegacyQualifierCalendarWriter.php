<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface LegacyQualifierCalendarWriter
{
    public function updateDailyLimit(int $competitionId, int $matchesPerDay): void;

    public function clearPending(int $seasonId, ?int $competitionId): void;

    public function insertFixtures(int $divisionId, ?int $competitionId, iterable $rows): int;

    public function insertJourneys(
        int $seasonId,
        int $divisionId,
        ?int $competitionId,
        array $days,
        string $startTime,
    ): void;

    public function activate(int $competitionId, string $startDate, string $endDate): void;

    public function audit(?int $competitionId, array $payload): void;
}
