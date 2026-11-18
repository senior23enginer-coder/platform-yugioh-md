<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;
use Generator;
use RuntimeException;

/**
 * Deterministic native-order scheduling without persistence or ambient time.
 * Slots are incremental so the caller retains its original transaction and failure behavior.
 */
final class PendingSchedulePlanner
{
    /** @return Generator<int, array{match_id:int,date:string,time:string}, void, array> */
    public static function automatic(
        array $matchIds,
        DateTimeImmutable $start,
        string $pattern,
        int $globalLimit,
        int $eventDailyLimit,
        array $occupancy,
        array $blackouts,
        DateTimeImmutable $evening,
    ): Generator {
        $eventDailyLimit = max(1, min($globalLimit, $eventDailyLimit));
        $cursor = 0;
        $dayIndex = 0;
        $firstDate = null;
        $lastDate = null;
        while ($cursor < count($matchIds) && $dayIndex < 730) {
            $date = MatchDatePattern::date($start, $pattern, $dayIndex, 1);
            $dayIndex++;
            if (isset($blackouts[$date])) {
                continue;
            }
            $existing = (int) ($occupancy[$date]['count'] ?? 0);
            $quota = ScheduleQuota::availableQuota(
                $globalLimit,
                $existing,
                $eventDailyLimit,
                count($matchIds) - $cursor,
            );
            if ($quota < 1) {
                continue;
            }
            $usedTimes = $occupancy[$date]['times'] ?? [];
            for ($slot = 0; $slot < $quota; $slot++) {
                $time = ScheduleTimePolicy::next($usedTimes, $existing + $slot, $evening);
                $usedTimes[substr($time, 0, 5)] = true;
                yield ['match_id' => $matchIds[$cursor], 'date' => $date, 'time' => $time];
                $cursor++;
            }
            $occupancy[$date] = ['count' => $existing + $quota, 'times' => $usedTimes];
            $firstDate ??= $date;
            $lastDate = $date;
        }
        if ($cursor !== count($matchIds)) {
            throw new RuntimeException(
                'No fue posible distribuir todos los combates dentro de la capacidad global.',
            );
        }
        return ['scheduled' => $cursor, 'start' => $firstDate, 'end' => $lastDate];
    }

    /** Quotas retain supplied date order. Nonpositive quotas and partial failures remain observable. */
    public static function explicit(
        array $matchIds,
        array $dateQuotas,
        int $limit,
        array $occupancy,
        DateTimeImmutable $evening,
    ): Generator {
        $cursor = 0;
        $lastDate = null;
        foreach ($dateQuotas as $date => $requested) {
            $existing = (int) ($occupancy[$date]['count'] ?? 0);
            $quota = ScheduleQuota::availableQuota(
                $limit,
                $existing,
                (int) $requested,
                count($matchIds) - $cursor,
            );
            $usedTimes = $occupancy[$date]['times'] ?? [];
            for ($slot = 0; $slot < $quota; $slot++) {
                $time = ScheduleTimePolicy::next($usedTimes, $existing + $slot, $evening);
                $usedTimes[substr($time, 0, 5)] = true;
                yield ['match_id' => $matchIds[$cursor], 'date' => $date, 'time' => $time];
                $cursor++;
            }
            if ($quota > 0) {
                $lastDate = $date;
            }
        }
        if ($cursor !== count($matchIds)) {
            throw new RuntimeException(
                'La programacion explicita no contiene cupos para todos los combates.',
            );
        }
        return ['scheduled' => $cursor, 'end' => $lastDate];
    }
}
