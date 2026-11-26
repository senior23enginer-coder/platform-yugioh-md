<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;

final class QualifierAutoSchedulePlan
{
    public static function rows(
        array $matches,
        DateTimeImmutable $base,
        int $matchesPerDay,
        int $minutes,
    ): iterable {
        foreach ($matches as $index => $match) {
            $dayOffset = intdiv($index, $matchesPerDay);
            $slot = $index % $matchesPerDay;
            $dateTime = $base
                ->modify('+' . $dayOffset . ' days')
                ->modify('+' . $slot * $minutes . ' minutes');
            yield [
                'id' => (int) $match['id'],
                'date' => $dateTime->format('Y-m-d'),
                'time' => $dateTime->format('H:i:s'),
            ];
        }
    }
}
