<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

/** Preserves server-owned completion dates; a timestamp is supplied explicitly by the caller. */
final class CompletionPolicy
{
    public static function completionGames(
        array $incoming,
        array $stored,
        array $before,
        array $after,
        string $now,
        bool $allowNew = true,
    ): array {
        $old = [];
        $rows = [];
        foreach ($stored as $row) {
            if (is_array($row)) {
                $old[(int) ($row['duel'] ?? 0)] = $row;
            }
        }
        foreach ($incoming as $row) {
            if (is_array($row)) {
                $rows[(int) ($row['duel'] ?? 0)] = $row;
            }
        }
        foreach ($after as $i => $winner) {
            $n = $i + 1;
            $row = array_replace($old[$n] ?? [], $rows[$n] ?? ['duel' => $n]);
            unset($row['completed_at']);
            if (!empty($old[$n]['completed_at'])) {
                $row['completed_at'] = $old[$n]['completed_at'];
            } elseif (
                $allowNew &&
                !in_array($before[$i] ?? '', ['A', 'B', 'E'], true) &&
                in_array($winner, ['A', 'B', 'E'], true)
            ) {
                $row['completed_at'] = $now;
            }
            $rows[$n] = $row;
        }
        ksort($rows);
        return array_values($rows);
    }
}
