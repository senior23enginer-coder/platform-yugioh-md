<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface ScheduleCapacity
{
    /** Platform capacity clamped to one through three matches per day. */
    public function limit(): int;
}
