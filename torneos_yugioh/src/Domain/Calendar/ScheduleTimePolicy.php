<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;

/** Preferred slots first; the fallback anchor is an explicit input instead of ambient time. */
final class ScheduleTimePolicy
{
    public static function next(
        array $usedTimes,
        int $fallbackIndex,
        DateTimeImmutable $evening,
    ): string {
        foreach (['19:00', '19:45', '20:30'] as $time) {
            if (empty($usedTimes[$time])) {
                return $time . ':00';
            }
        }
        return $evening->modify('+' . $fallbackIndex * 45 . ' minutes')->format('H:i:s');
    }
}
