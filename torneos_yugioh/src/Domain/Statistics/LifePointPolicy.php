<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

/** Determines known LP evidence and flawless outcomes without assuming missing captures. */
final class LifePointPolicy
{
    public static function lpHistoryKnown(array $d, string $side): bool
    {
        $s = strtolower($side);
        if (
            ($d['lp_history_complete'] ?? false) !== true ||
            !isset($d['lp_start_' . $s], $d['lp_' . $s]) ||
            !is_numeric($d['lp_start_' . $s]) ||
            !is_numeric($d['lp_' . $s]) ||
            !is_array($d['lp_history'] ?? null)
        ) {
            return false;
        }
        $last = (float) $d['lp_start_' . $s];
        foreach ($d['lp_history'] as $step) {
            if (!isset($step[$s]) || !is_numeric($step[$s])) {
                return false;
            }
            $last = (float) $step[$s];
        }
        return $last === (float) $d['lp_' . $s];
    }

    public static function flawless(array $d, string $side): bool
    {
        $s = strtolower($side);
        if (!\StationX\Yugioh\Domain\Statistics\LifePointPolicy::lpHistoryKnown($d, $side)) {
            return false;
        }
        $prev = (float) $d['lp_start_' . $s];
        foreach ($d['lp_history'] as $step) {
            if (!isset($step[$s]) || !is_numeric($step[$s])) {
                return false;
            }
            $now = (float) $step[$s];
            if ($now < $prev) {
                return false;
            }
            $prev = $now;
        }
        return $prev === (float) $d['lp_' . $s];
    }
}
