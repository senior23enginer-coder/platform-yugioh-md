<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class EventProgressPolicy
{
    public static function calculate(array $rounds, bool $perRound): array
    {
        usort($rounds, static fn($a, $b) => (int) $a['round_number'] <=> (int) $b['round_number']);
        $done = 0;
        $total = 0;
        $round = null;
        if ($perRound && $rounds) {
            $current = end($rounds);
            foreach ($rounds as $row) {
                if ((int) $row['done'] < (int) $row['total']) {
                    $current = $row;
                    break;
                }
            }
            $done = (int) $current['done'];
            $total = (int) $current['total'];
            $round = (int) $current['round_number'];
        } else {
            foreach ($rounds as $row) {
                $done += (int) $row['done'];
                $total += (int) $row['total'];
            }
        }
        return ['done' => $done, 'total' => $total, 'round' => $round, 'ratio' => $total > 0 ? $done / $total : 0];
    }

    public static function group(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[(int) $row['competition_id']][] = $row;
        }
        return $groups;
    }
}
