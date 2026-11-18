<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

/** Rebuild calendar projections only after all matches and the final date have been persisted. */
interface ScheduleEvents
{
    public function rebuild(int $competitionId, string $eventType): void;
}
