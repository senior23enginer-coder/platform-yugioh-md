<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface CompetitionCalendarWriter
{
    public function deactivateType(int $seasonId, int $competitionId, string $eventType): void;

    public function upsertJourneys(int $seasonId, int $competitionId, iterable $rows): int;

    public function deactivateCode(int $seasonId, int $competitionId, string $code): void;
}
