<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class MetricAccumulator
{
    public static function accumulate(array &$r, array $m, string $side): void
    {
        $other = $side === 'A' ? 'B' : 'A';
        $s = strtolower($side);
        $o = strtolower($other);
        $won = $m['result'] === $side;
        $draw = $m['result'] === 'E';
        $score = $won ? 1 : ($draw ? 0.5 : 0);
        $r['J']++;
        $r[$won ? 'V' : ($draw ? 'E' : 'D')]++;
        $r['streak'] = $won ? $r['streak'] + 1 : 0;
        $r['best_streak'] = max($r['best_streak'], $r['streak']);
        $rival = (int) $m['team_' . $o . '_id'];
        $r['opponents'][!empty($r['_season_scope']) ? (int) $m['deck_' . $o . '_id'] : $rival] = true;
        if (
            $r['last_at'] !== null &&
            (new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($m['played_at']))->days >
                90
        ) {
            $r['reactivation'] = 0;
        }
        $r['reactivation'] = min(3, $r['reactivation'] + 1);
        $r['last_at'] = $m['played_at'];
        $r['recent'][] = $score;
        $r['evidence'][] = $m['match_id'];
        if ($won) {
            $r['rivals_beaten'][$rival] = true;
            $r['events_won_match'][$m['event_id'] ?? 0] = true;
        }
        $ds = $m['duels'] ?? [];
        $complete = ($m['duels_complete'] ?? false) === true && count($ds) > 0;
        $r['duels_complete'] = $r['duels_complete'] && $complete;
        $d1 = null;
        $d2 = null;
        $lost = 0;
        foreach ($ds as $d) {
            $dw = $d['winner'] ?? null;
            $position = (int) ($d['position'] ?? ($d['duel'] ?? 0));
            if (!in_array($dw, ['A', 'B', 'E'], true) || $position < 1) {
                $r['duels_complete'] = false;
                continue;
            }
            $r['JD']++;
            $r[$dw === $side ? 'VG' : ($dw === 'E' ? 'EG' : 'DG')]++;
            $r['positions'][$position] ??= ['J' => 0, 'V' => 0, 'E' => 0, 'D' => 0];
            $r['positions'][$position]['J']++;
            $r['positions'][$position][$dw === $side ? 'V' : ($dw === 'E' ? 'E' : 'D')]++;
            if ($dw === $other) {
                $lost++;
            }
            if ($position === 1) {
                $d1 = $dw;
            }
            if ($position === 2) {
                $d2 = $dw;
            }
            if ($position === 5) {
                $r['d5_n']++;
                if ($won && $dw === $side) {
                    $r['d5_wins']++;
                }
            }
            $start = strtoupper((string) ($d['starts_player'] ?? ''));
            if (in_array($start, ['A', 'B'], true)) {
                $key = $start === $side ? 'first' : 'second';
                $r[$key . '_n']++;
                if ($dw === $side) {
                    $r[$key . '_w']++;
                }
            }
            $brick = $d['brick_' . $s] ?? null;
            if (in_array($brick, [true, false, 0, 1, '0', '1'], true)) {
                $r['brick_n']++;
                if ($dw === $side) {
                    $r['brick_win_known_n']++;
                }
                if (in_array($brick, [true, 1, '1'], true)) {
                    $r['brick']++;
                    if ($dw === $side) {
                        $r['brick_w']++;
                    }
                }
            }
            $duration = $d['duration_seconds'] ?? null;
            $turns = $d['turn_count'] ?? null;
            if (is_numeric($duration) && $duration > 0) {
                $r['duration_n']++;
                $r['duration_sum'] += $duration;
                if ($dw === $side) {
                    $r['duration_win_n']++;
                    $r['fastest'] =
                        $r['fastest'] === null
                            ? (float) $duration
                            : min($r['fastest'], (float) $duration);
                    if ($duration < 600) {
                        $r['under_10']++;
                    }
                    if ($duration < 300) {
                        $r['under_5']++;
                    }
                }
            }
            if (is_numeric($turns) && $turns > 0) {
                $r['turn_n']++;
                $r['turn_sum'] += $turns;
                if ($dw === $side) {
                    $r['turn_win_n']++;
                    $r['fewest_turns'] =
                        $r['fewest_turns'] === null
                            ? (int) $turns
                            : min($r['fewest_turns'], (int) $turns);
                    if ($turns <= 5) {
                        $r['under_5_turns']++;
                    }
                    if ($turns <= 3) {
                        $r['under_3_turns']++;
                    }
                }
            }
            if (
                isset($d['lp_start_' . $o], $d['lp_' . $o]) &&
                is_numeric($d['lp_start_' . $o]) &&
                is_numeric($d['lp_' . $o])
            ) {
                $net = max(0, $d['lp_start_' . $o] - $d['lp_' . $o]);
                $r['lp_n']++;
                $r['lp_net'] += $net;
                if (is_numeric($turns) && $turns > 0) {
                    $r['lp_turn_n']++;
                    $r['lp_per_turn_net'] += $net;
                    $r['lp_per_turn_turns'] += $turns;
                }
            }
            if (isset($d['lp_start_' . $s], $d['lp_' . $s])) {
                $r['lp_received'] += max(0, $d['lp_start_' . $s] - $d['lp_' . $s]);
            }
            if (isset($d['lp_' . $s], $d['lp_' . $o])) {
                $r['lp_diff_n']++;
                $r['lp_diff_sum'] += $d['lp_' . $s] - $d['lp_' . $o];
            }
            if ($dw === $side && LifePointPolicy::flawless($d, $side)) {
                $r['flawless']++;
            }
            if ($dw === $side && LifePointPolicy::lpHistoryKnown($d, $side)) {
                $r['lp_history_win_known_n']++;
            }
            $method = $d['defeat_method'] ?? '';
            if (
                $dw === $side &&
                in_array(
                    $method,
                    ['life_points', 'surrender', 'time_limit', 'deck_out', 'effect_win'],
                    true,
                )
            ) {
                $r['closure_win_known_n']++;
            }
            if ($method !== '') {
                $r['closures'][$method][$dw === $side ? 'won' : ($dw === 'E' ? 'draw' : 'lost')] =
                    ($r['closures'][$method][$dw === $side ? 'won' : ($dw === 'E' ? 'draw' : 'lost')] ??
                        0) +
                    1;
            }
            if ($dw === $side) {
                foreach (
                    [
                        'surrender' => 'surrender_w',
                        'time_limit' => 'time_w',
                        'deck_out' => 'deck_out_w',
                        'effect_win' => 'effect_w',
                    ]
                    as $code => $field
                ) {
                    if ($method === $code) {
                        $r[$field]++;
                    }
                }
            }
            if ($dw === $other && $method === 'surrender') {
                $r['surrender_l']++;
            }
        }
        if ($won && $complete && $lost === 0) {
            $r['perfect_matches']++;
        }
        if ($won && $d1 === $other) {
            $r['comebacks']++;
        }
        if ($won && $d1 === $other && $d2 === $other) {
            $r['reverse_sweeps']++;
        }
        $dice = $m['dice_winner'] ?? null;
        if (in_array($dice, ['A', 'B'], true)) {
            $r['dice_n']++;
            if ($dice === $side) {
                $r['dice_w']++;
                if ($won) {
                    $r['dice_match_w']++;
                }
            }
        }
        if (array_key_exists('official_points_' . $s, $m) && is_numeric($m['official_points_' . $s])) {
            $r['official_points'] = ($r['official_points'] ?? 0) + $m['official_points_' . $s];
        }
    }
}
