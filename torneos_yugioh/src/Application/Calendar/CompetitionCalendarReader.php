<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface CompetitionCalendarReader
{
    public function competition(int $seasonId, int $competitionId): ?array;

    public function scheduledDays(int $competitionId): array;
}
