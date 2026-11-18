<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

/** Computes free daily capacity and expected qualifier size from explicit inputs. */
final class ScheduleQuota
{
    public static function availableQuota(
        int $globalLimit,
        int $occupied,
        int $eventLimit,
        int $remaining,
    ): int {
        return max(0, min($eventLimit, $globalLimit - $occupied, $remaining));
    }

    public static function expectedQualifierMatchCount(
        int $selectedDeckCount,
        int $participantsPerSide,
        int $duelsPerDeck,
    ): int {
        $participantsPerSide = max(1, min(3, $participantsPerSide));
        $groupCount =
            $participantsPerSide > 0 ? intdiv($selectedDeckCount, $participantsPerSide) : 0;
        if ($groupCount < 2 || $duelsPerDeck < 2 || $duelsPerDeck > $groupCount - 1) {
            return 0;
        }
        $roundLimit = min(max(2, min(5, $duelsPerDeck)), $groupCount - 1);
        return intdiv($groupCount, 2) * $roundLimit;
    }
}
