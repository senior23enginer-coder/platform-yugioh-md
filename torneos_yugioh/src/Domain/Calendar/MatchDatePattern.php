<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;

/** Native event cadence calculated from an explicit start date, never the server clock. */
final class MatchDatePattern
{
    public static function date(
        DateTimeImmutable $start,
        string $pattern,
        int $index,
        int $matchesPerDay = 1,
    ): string {
        $dayIndex = intdiv(max(0, $index), max(1, min(5, $matchesPerDay)));
        if ($pattern === 'weekend') {
            $date = $start;
            $weekendIndex = 0;
            while ((int) $date->format('N') < 6) {
                $date = $date->modify('+1 day');
            }
            while ($weekendIndex < $dayIndex) {
                $date = $date->modify('+1 day');
                if ((int) $date->format('N') >= 6) {
                    $weekendIndex++;
                }
            }
            return $date->format('Y-m-d');
        }
        $interval =
            ['consecutive' => 1, 'every_other_day' => 2, 'every_2_days' => 3, 'every_3_days' => 4][
                $pattern
            ] ?? 1;
        return $start->modify('+' . $dayIndex * $interval . ' days')->format('Y-m-d');
    }
}
