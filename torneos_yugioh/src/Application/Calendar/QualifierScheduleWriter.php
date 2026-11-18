<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

/** Native qualifier displacement, sharing the caller's transaction and event lock. */
interface QualifierScheduleWriter
{
    public function clearPendingDatesExcept(int $excludeCompetitionId): int;
}
