<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

/** No independent begin/commit/rollback: the native caller owns the transaction. */
interface ScheduleWriter
{
    public function clearPendingDates(int $competitionId): void;
    public function assign(int $competitionId, int $matchId, string|int $date, string $time): void;
    public function setEndDate(int $competitionId, string|int $date): void;
}
