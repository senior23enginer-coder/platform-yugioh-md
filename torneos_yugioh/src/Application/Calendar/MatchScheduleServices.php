<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface MatchScheduleServices
{
    public function validate(int $matchId, ?string $scheduledOn, ?string $startsAt, string $venue): array;

    public function rebuildCalendar(int $competitionId, string $phase): void;

    public function reflowQualifiers(): void;
}
