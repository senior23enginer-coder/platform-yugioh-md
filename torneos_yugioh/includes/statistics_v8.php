<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';
require_once __DIR__ . '/statistics_multiplayer.php';

/** Statistical derivatives only. Never writes competitions, matches, results or honors. */
const YGO_STATS_VERSION = 'STATION_X_STATS_8.4';

function ygo_stats_ratio(float $n, float $d): ?float
{
    return \StationX\Yugioh\Domain\Statistics\RatingMath::ratio($n, $d);
}
function ygo_stats_wilson(float $p, int $n): ?float
{
    return \StationX\Yugioh\Domain\Statistics\RatingMath::wilson($p, $n);
}
function ygo_stats_elo(float $a, float $b, float $s, float $k = 20, float $divisor = 600): array
{
    return \StationX\Yugioh\Domain\Statistics\RatingMath::elo($a, $b, $s, $k, $divisor);
}

function ygo_stats_policy(): array
{
    return [
        'version' => YGO_STATS_VERSION,
        'base_global' => 1000,
        'base_season' => 1500,
        'divisor_global' => 600,
        'divisor_season' => 400,
        'k_season' => 32,
        'k_mapping' => ['FULL' => 20, 'REDUCED' => 20, 'BONUS' => 25, 'NONE' => 0],
        'minimum_matches' => 2,
        'minimum_opponents' => 1,
        'inactive_days' => 90,
        'reactivation_matches' => 3,
        'tier_weights' => [0.4, 0.25, 0.2, 0.1, 0.05],
        'meta_minimum_matches' => 10,
        'meta_minimum_opponents' => 3,
        'tier_rules' => [
            'Tier Zero' => [
                'IC' => 90,
                'WR' => 80,
                'opponents' => 5,
                'strong_matches' => 3,
                'strong_performance' => 0.5,
            ],
            'Tier 1' => ['IC' => 75, 'WR' => 65, 'opponents' => 4],
            'Tier 2' => ['IC' => 55, 'WR' => 0, 'opponents' => 3],
        ],
        'medal_bonus' => [
            'world' => 50,
            'super_cup' => 35,
            'cup' => 30,
            'league' => 25,
            'tournament' => 20,
            'qualifier' => 10,
            'special' => 20,
        ],
        'global_anchor' => null,
        'global_cadence' => 'per_result',
        'timezone' => 'America/Bogota',
    ];
}

/** Adapters must explicitly confirm eligibility; unknown is never a positive result. */
function ygo_stats_eligible(array $m): ?string
{
    if (
        ($m['confirmed'] ?? false) !== true ||
        !in_array($m['result'] ?? null, ['A', 'B', 'E'], true)
    ) {
        return 'Resultado no confirmado';
    }
    if (($m['competitive'] ?? null) !== true) {
        return 'Elegibilidad competitiva no confirmada';
    }
    if (
        !empty($m['friendly']) ||
        !empty($m['bye']) ||
        !empty($m['walkover']) ||
        !empty($m['cancelled'])
    ) {
        return 'Partida no puntuable';
    }
    if (array_key_exists('played', $m) && $m['played'] !== true) {
        return 'Sin evidencia de juego efectivamente disputado';
    }
    foreach (['match_id', 'team_a_id', 'team_b_id', 'deck_a_id', 'deck_b_id'] as $k) {
        if ((int) ($m[$k] ?? 0) < 1) {
            return $m['identity_quality'] ?? 'Identidad incompleta';
        }
    }
    if (
        (int) $m['team_a_id'] === (int) $m['team_b_id'] ||
        (int) $m['deck_a_id'] === (int) $m['deck_b_id']
    ) {
        return 'Identidades rivales iguales';
    }
    if (empty($m['format_id']) || empty($m['played_at'])) {
        return 'Formato o fecha deportiva desconocidos';
    }
    try {
        new DateTimeImmutable($m['played_at']);
    } catch (Exception $e) {
        return 'Fecha deportiva invalida';
    }
    return null;
}

function ygo_stats_metric(int $team, int $deck, string $format): array
{
    return \StationX\Yugioh\Domain\Statistics\MetricFactory::metric($team, $deck, $format);
}

/** LP endpoints cannot prove a flawless victory; only complete evidence can. */
function ygo_stats_lp_history_known(array $d, string $side): bool
{
    return \StationX\Yugioh\Domain\Statistics\LifePointPolicy::lpHistoryKnown($d, $side);
}
function ygo_stats_flawless(array $d, string $side): bool
{
    return \StationX\Yugioh\Domain\Statistics\LifePointPolicy::flawless($d, $side);
}

function ygo_stats_accumulate(array &$r, array $m, string $side): void
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
        if ($dw === $side && ygo_stats_flawless($d, $side)) {
            $r['flawless']++;
        }
        if ($dw === $side && ygo_stats_lp_history_known($d, $side)) {
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

function ygo_stats_tier(array $r, array $policy = []): array
{
    $policy = array_replace(ygo_stats_policy(), $policy);
    $weights = $policy['tier_weights'];
    if (count($weights) !== 5 || abs(array_sum($weights) - 1) > 1e-10 || min($weights) < 0) {
        throw new InvalidArgumentException('Pesos de metagame invalidos.');
    }
    $J = $r['J'];
    $R = ygo_stats_ratio($r['V'] + 0.5 * $r['E'], $J);
    $RD = ygo_stats_ratio($r['VG'] + 0.5 * $r['EG'], $r['JD']);
    $EL = max(0, min(100, 50 + ($r['rating_season'] - 1500) / 8));
    $C = $J ? 100 * ygo_stats_wilson(($r['V'] + 0.5 * $r['E']) / $J, $J) : null;
    $recent = array_slice($r['recent'], -5);
    $RE = $recent ? (100 * array_sum($recent)) / count($recent) : null;
    $wr = ygo_stats_ratio($r['V'], $J);
    $distinct = count($r['opponents']);
    $valid =
        $J >= $policy['meta_minimum_matches'] &&
        $distinct >= $policy['meta_minimum_opponents'] &&
        $r['duels_complete'] &&
        $RD !== null;
    $IC =
        $r['duels_complete'] && $RD !== null && $R !== null
            ? array_sum(
                array_map(static fn($w, $value) => $w * $value, $weights, [$R, $EL, $C, $RE, $RD]),
            )
            : null;
    $tier = 'SIN CLASIFICAR';
    if ($valid) {
        $tier = 'Tier 3';
        foreach (['Tier Zero', 'Tier 1', 'Tier 2'] as $candidate) {
            $rule = $policy['tier_rules'][$candidate];
            if ($IC < $rule['IC'] || $wr < $rule['WR'] || $distinct < $rule['opponents']) {
                continue;
            }
            if (
                $candidate === 'Tier Zero' &&
                ($r['strong_n'] < $rule['strong_matches'] ||
                    $r['strong_score'] / $r['strong_n'] <= $rule['strong_performance'])
            ) {
                continue;
            }
            $tier = $candidate;
            break;
        }
    }
    return [
        'tier' => $tier,
        'IC' => $IC,
        'R' => $R,
        'EL' => $EL,
        'C' => $C,
        'RE' => $RE,
        'RD' => $RD,
        'match_WR' => $wr,
        'duel_WR' => ygo_stats_ratio($r['VG'], $r['JD']),
        'distinct_opponents' => $distinct,
        'quality_status' => $valid
            ? 'SUFICIENTE'
            : (!$r['duels_complete']
                ? 'DUELOS INCOMPLETOS'
                : 'MUESTRA INSUFICIENTE'),
    ];
}

/** Complete chronological replay, used for first calculation and corrections alike. */
function ygo_stats_calculate(
    array $matches,
    array $events = [],
    array $honors = [],
    array $policy = [],
    ?string $cutoff = null,
    array $publishedTiers = [],
    array $ffaMatches = [],
): array {
    $policy = array_replace(ygo_stats_policy(), $policy);
    $policy['global_cadence'] = 'per_result';
    $policy['minimum_matches'] = 2;
    $policy['minimum_opponents'] = 1;
    $policy['version'] = YGO_STATS_VERSION;
    $cutoff ??= (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format(
        'Y-m-d H:i:s',
    );
    usort(
        $matches,
        static fn($a, $b) => [
            $a['played_at'] ?? '',
            (int) ($a['match_id'] ?? 0),
            (int) ($a['seat'] ?? 0),
        ] <=> [$b['played_at'] ?? '', (int) ($b['match_id'] ?? 0), (int) ($b['seat'] ?? 0)],
    );
    $global = [];
    $season = [];
    $ledger = [];
    $pending = [];
    $seen = [];
    $matchups = [];
    $teamMetrics = [];
    $players = [];
    foreach ($matches as $m) {
        if (($m['played_at'] ?? '') > $cutoff) {
            continue;
        }
        if (($m['kind'] ?? '') === 'tag') {
            $reason = ygo_stats_group_reason($m);
            $k = $policy['k_mapping'][$m['ranking_impact'] ?? ''] ?? 0;
            if ($reason !== null || $k <= 0) {
                $pending[] = [
                    'match_id' => $m['match_id'],
                    'reason' => $reason ?? 'Importancia no asignada',
                ];
                continue;
            }
            $unique = $m['format_id'] . ':' . $m['source_key'];
            if (isset($seen[$unique])) {
                if ($seen[$unique] !== $m) {
                    throw new InvalidArgumentException('Duplicado Tag contradictorio');
                }
                continue;
            }
            $seen[$unique] = $m;
            ygo_stats_apply_tag($m, $policy, $global, $teamMetrics, $ledger);
            continue;
        }
        if (($reason = ygo_stats_eligible($m)) !== null) {
            $pending[] = ['match_id' => $m['match_id'] ?? null, 'reason' => $reason];
            continue;
        }
        $format = (string) $m['format_id'];
        $unique = $format . ':' . ($m['source_key'] ?? $m['match_id']);
        if (isset($seen[$unique])) {
            if ($seen[$unique] !== $m) {
                throw new InvalidArgumentException('Duplicado contradictorio: ' . $unique);
            }
            continue;
        }
        $seen[$unique] = $m;
        $importanceK = $policy['k_mapping'][$m['ranking_impact'] ?? ''] ?? null;
        if ($importanceK === null || $importanceK <= 0) {
            $pending[] = ['match_id' => $m['match_id'], 'reason' => 'Importancia no asignada'];
            continue;
        }
        $keys = [];
        $skeys = [];
        $s = (int) ($m['season_id'] ?? 0);
        $score = $m['result'] === 'A' ? 1 : ($m['result'] === 'E' ? 0.5 : 0);
        foreach (['a', 'b'] as $suffix) {
            $k = $m['team_' . $suffix . '_id'] . ':' . $format;
            $keys[] = $k;
            $global[$k] ??= ygo_stats_metric(
                (int) $m['team_' . $suffix . '_id'],
                (int) $m['deck_' . $suffix . '_id'],
                $format,
            );
            $global[$k]['deck_id'] = (int) $m['deck_' . $suffix . '_id'];
            $sk = $s . ':' . $format . ':' . $m['deck_' . $suffix . '_id'];
            $skeys[] = $sk;
            $season[$sk] ??= ygo_stats_metric(
                (int) $m['team_' . $suffix . '_id'],
                (int) $m['deck_' . $suffix . '_id'],
                $format,
            );
            $season[$sk]['season_id'] = $s;
        }
        [$a, $b] = $keys;
        [$sa, $sb] = $skeys;
        $change = ygo_stats_elo(
            $global[$a]['rating'],
            $global[$b]['rating'],
            $score,
            (float) $importanceK,
        );
        $sc = ygo_stats_elo(
            $season[$sa]['rating_season'],
            $season[$sb]['rating_season'],
            $score,
            32,
            400,
        );
        foreach (
            [['A', $a, $sa, $b, 'a', 'b', $score], ['B', $b, $sb, $a, 'b', 'a', 1 - $score]]
            as [$side, $key, $sk, $opp, $sf, $of, $ss]
        ) {
            $global[$key]['rival_pre_sum'] += $change['before_' . $of];
            $season[$sk]['_season_scope'] = true;
            ygo_stats_accumulate($global[$key], $m, $side);
            ygo_stats_accumulate($season[$sk], $m, $side);
            $team = (int) $m['team_' . $sf . '_id'];
            $teamMetrics[$team] ??= ygo_stats_metric($team, (int) $m['deck_' . $sf . '_id'], '*');
            ygo_stats_accumulate($teamMetrics[$team], $m, $side);
            if ($ss === 1) {
                $teamMetrics[$team]['formats'][$format] = true;
                $teamMetrics[$team]['format_moments'][$format] ??= [
                    'sports_effective_at' => $m['played_at'],
                    'match_id' => $m['match_id'],
                ];
            }
            if ($sc['before_' . $of] >= 1500) {
                $season[$sk]['strong_n']++;
                $season[$sk]['strong_score'] += $ss;
            }
            $tier = ygo_stats_historical_tier(
                $publishedTiers,
                $s,
                $format,
                (int) $m['deck_' . $of . '_id'],
                $m['played_at'],
            );
            if ($ss === 1 && in_array($tier, ['Tier Zero', 'Tier 1', 'Tier 2', 'Tier 3'], true)) {
                $global[$key]['tiers_beaten'][$tier] = true;
                if ($tier === 'Tier Zero') {
                    $global[$key]['tier_zero_wins']++;
                }
            }
            if ($tier !== null) {
                $global[$key]['versus_tiers'][$tier] ??= ['J' => 0, 'score' => 0.0];
                $global[$key]['versus_tiers'][$tier]['J']++;
                $global[$key]['versus_tiers'][$tier]['score'] += $ss;
            }
            if ($ss === 1 && in_array($tier, ['Tier Zero', 'Tier 1', 'Tier 2', 'Tier 3'], true)) {
                $teamMetrics[$team]['tiers_beaten'][$tier] = true;
                if ($tier === 'Tier Zero') {
                    $teamMetrics[$team]['tier_zero_wins']++;
                }
            }
            if ($ss === 1 && $tier === null) {
                $global[$key]['tier_unknown_wins']++;
                $teamMetrics[$team]['tier_unknown_wins']++;
            }
            foreach (ygo_stats_achievements($teamMetrics[$team]) as $achievement) {
                if ($achievement['status'] === 'DESBLOQUEADO') {
                    $teamMetrics[$team]['achievement_moments'][$achievement['code']] ??= [
                        'sports_effective_at' => $m['played_at'],
                        'match_id' => $m['match_id'],
                    ];
                }
            }
        }
        $global[$a]['rating'] = $change['after_a'];
        $global[$b]['rating'] = $change['after_b'];
        $season[$sa]['rating_season'] = $sc['after_a'];
        $season[$sb]['rating_season'] = $sc['after_b'];
        $ledger[] = array_merge($change, [
            'kind' => $m['kind'] ?? 'individual',
            'source_key' => $m['source_key'] ?? 'match:' . $m['match_id'],
            'seat' => $m['seat'] ?? null,
            'event_id' => $m['event_id'] ?? null,
            'match_id' => $m['match_id'],
            'format_id' => $format,
            'team_a_id' => $m['team_a_id'],
            'team_b_id' => $m['team_b_id'],
            'deck_a_id' => $m['deck_a_id'],
            'deck_b_id' => $m['deck_b_id'],
            'season_id' => $s,
            'result' => $m['result'],
            'played_at' => $m['played_at'],
            'source_version' => $m['source_version'] ?? null,
            'algorithm_version' => YGO_STATS_VERSION,
        ]);
        $lo = min($m['team_a_id'], $m['team_b_id']);
        $hi = max($m['team_a_id'], $m['team_b_id']);
        $mk = $format . ':' . $lo . ':' . $hi;
        $matchups[$mk] ??= [
            'format_id' => $format,
            'a' => $lo,
            'b' => $hi,
            'J' => 0,
            'V' => 0,
            'E' => 0,
            'D' => 0,
        ];
        $matchups[$mk]['J']++;
        $matchups[$mk][
            $m['result'] === 'E' ? 'E' : (($score === 1) == ($lo === $m['team_a_id']) ? 'V' : 'D')
        ]++;
        foreach ($m['duels'] ?? [] as $d) {
            foreach (['A' => 'a', 'B' => 'b'] as $side => $suffix) {
                $id = (int) ($d['player_' . $suffix . '_id'] ?? 0);
                if ($id < 1 || !in_array($d['winner'] ?? null, ['A', 'B', 'E'], true)) {
                    continue;
                }
                $pk = $format . ':' . $id;
                $players[$pk] ??= [
                    'player_id' => $id,
                    'format_id' => $format,
                    'JD' => 0,
                    'VG' => 0,
                    'EG' => 0,
                    'DG' => 0,
                    'evidence' => [],
                ];
                $players[$pk]['JD']++;
                $players[$pk][
                    $d['winner'] === $side ? 'VG' : ($d['winner'] === 'E' ? 'EG' : 'DG')
                ]++;
                $players[$pk]['evidence'][] = [
                    'match_id' => $m['match_id'],
                    'duel' => $d['position'] ?? ($d['duel'] ?? null),
                ];
            }
        }
    }
    $medals = ygo_stats_championships($events, $honors, $policy, $cutoff);
    $bonuses = [];
    foreach ($medals as $medal) {
        if ($medal['status'] === 'ENTREGADA') {
            $key = $medal['team_id'] . ':' . $medal['format_id'];
            $bonuses[$key] = ($bonuses[$key] ?? 0) + $medal['bonus_points'];
            $global[$key] ??= ygo_stats_metric(
                $medal['team_id'],
                $medal['deck_id'],
                $medal['format_id'],
            );
        }
    }
    foreach ($global as $key => &$r) {
        $r['bonus_medallas'] = $bonuses[$key] ?? 0;
        $r['total'] = $r['rating'] + $r['bonus_medallas'];
        $r['championships'] = count(
            array_filter(
                $medals,
                fn($m) => $m['status'] === 'ENTREGADA' &&
                    $m['team_id'] === $r['team_id'] &&
                    $m['format_id'] === $r['format_id'],
            ),
        );
        $inactive =
            $r['last_at'] === null ||
            (new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($cutoff))->days > 90;
        $r['sample_state'] =
            $r['J'] < $policy['minimum_matches'] ||
            count($r['opponents']) < $policy['minimum_opponents']
                ? 'PROVISIONAL'
                : 'SUFICIENTE';
        $r['state'] =
            $inactive && $r['last_at'] !== null
                ? 'INACTIVO'
                : ($r['reactivation'] < 3
                    ? 'REACTIVACION PENDIENTE'
                    : ($r['sample_state'] === 'PROVISIONAL'
                        ? 'PROVISIONAL'
                        : 'CLASIFICADO'));
        $r['WR'] = ygo_stats_ratio($r['V'], $r['J']);
        $r['performance'] = ygo_stats_ratio($r['V'] + 0.5 * $r['E'], $r['J']);
        $r['achievements'] = ygo_stats_achievements(
            $teamMetrics[$r['team_id']] ?? $r,
            $events,
            $honors,
            array_values($seen),
        );
        $r['achievements'][38]['name'] = 'Primera victoria en ' . $r['format_id'];
        $r['achievements'][38]['progress'] = $r['V'];
        $r['achievements'][38]['status'] = $r['V'] > 0 ? 'DESBLOQUEADO' : 'BLOQUEADO';
        $r['achievements'][38]['earned_moment'] =
            $teamMetrics[$r['team_id']]['format_moments'][$r['format_id']] ?? null;
    }
    unset($r);
    usort(
        $global,
        static fn($a, $b) => strcmp($a['format_id'], $b['format_id']) ?:
        [$b['total'], $b['rating'], $b['championships'], $a['team_id']] <=> [
                $a['total'],
                $a['rating'],
                $a['championships'],
                $b['team_id'],
            ],
    );
    $positions = [];
    foreach ($global as &$r) {
        $r['position'] =
            $r['state'] === 'CLASIFICADO'
                ? ($positions[$r['format_id']] = ($positions[$r['format_id']] ?? 0) + 1)
                : null;
    }
    unset($r);
    $season = array_filter($season, static fn($r) => $r['season_id'] > 0);
    foreach ($season as &$r) {
        $r = array_merge($r, ygo_stats_tier($r, $policy));
    }
    unset($r);
    foreach ($matchups as &$r) {
        $r['WR'] = ygo_stats_ratio($r['V'], $r['J']);
        $r['performance'] = ygo_stats_ratio($r['V'] + 0.5 * $r['E'], $r['J']);
    }
    unset($r);
    return [
        'version' => YGO_STATS_VERSION,
        'cutoff' => $cutoff,
        'status' => 'PROVISIONAL',
        'policy' => $policy,
        'global' => array_values($global),
        'ledger' => $ledger,
        'metagame' => array_values($season),
        'medals' => $medals,
        'pending' => $pending,
        'matchups' => array_values($matchups),
        'players' => array_values($players),
        'profiles' => array_values($teamMetrics),
        'confirmed_matches' => array_values($seen),
        'free_for_all' => ygo_stats_calculate_ffa($ffaMatches, [], $cutoff),
        'confirmed_ffa_matches' => array_values(
            array_filter(
                $ffaMatches,
                static fn($m) => !empty($m['confirmed']) && ($m['played_at'] ?? '') <= $cutoff,
            ),
        ),
    ];
}

function ygo_stats_historical_tier(
    array $cuts,
    int $season,
    string $format,
    int $deck,
    string $at,
): ?string {
    return \StationX\Yugioh\Domain\Statistics\HistoricalMetrics::historicalTier(
        $cuts,
        $season,
        $format,
        $deck,
        $at,
    );
}

function ygo_stats_championships(array $events, array $honors, array $policy, string $cutoff): array
{
    return \StationX\Yugioh\Domain\Statistics\HistoricalMetrics::championships(
        $events,
        $honors,
        $policy,
        $cutoff,
    );
}

/** Individual scopes and evidence; secret names remain hidden until unlocked. */
function ygo_stats_achievements(
    array $r,
    array $events = [],
    array $honors = [],
    array $matches = [],
): array {
    $counts = [
        'match_wins' => $r['V'],
        'duel_wins' => $r['VG'],
        'rivals_beaten' => count($r['rivals_beaten']),
        'perfect_matches' => $r['perfect_matches'],
        'flawless_wins' => $r['flawless'],
        'best_streak' => $r['best_streak'],
        'd5_wins' => $r['d5_wins'],
        'brick_wins' => $r['brick_w'],
        'comebacks' => $r['comebacks'],
        'reverse_sweeps' => $r['reverse_sweeps'],
        'second_wins' => $r['second_w'],
        'surrender_wins' => $r['surrender_w'],
        'time_wins' => $r['time_w'],
        'under_10' => $r['under_10'],
        'under_5' => $r['under_5'],
        'under_5_turns' => $r['under_5_turns'],
        'under_3_turns' => $r['under_3_turns'],
        'deck_out_wins' => $r['deck_out_w'],
        'effect_wins' => $r['effect_w'],
        'dice_wins' => $r['dice_w'],
        'damage_dealt' => $r['lp_net'],
        'events_won' => count(array_filter(array_keys($r['events_won_match']))),
        'formats_won' => count($r['formats'] ?? []),
        'no_surrender_season' => null,
        'undefeated_champion' => null,
        'tier_zero_wins' => $r['tier_zero_wins'],
        'tiers_beaten' => count($r['tiers_beaten']),
    ];
    foreach ($honors as $h) {
        if (!empty($h['confirmed']) && (int) ($h['champion_team_id'] ?? 0) === $r['team_id']) {
            $own = array_values(
                array_filter(
                    $matches,
                    fn($m) => ygo_stats_eligible($m) === null &&
                        ($m['event_id'] ?? 0) === $h['competition_id'] &&
                        in_array($r['team_id'], [$m['team_a_id'], $m['team_b_id']], true),
                ),
            );
            if (
                $own &&
                !array_filter(
                    $own,
                    fn($m) => $m['result'] !== 'E' &&
                        $m['result'] !== ($m['team_a_id'] === $r['team_id'] ? 'A' : 'B'),
                )
            ) {
                $counts['undefeated_champion'] = 1;
            }
        }
    }
    $closed = [];
    foreach ($events as $e) {
        if (
            in_array(
                $e['season_status'] ?? '',
                ['closed', 'completed', 'finalizada', 'finalizado'],
                true,
            )
        ) {
            $closed[(int) $e['season_id']] = true;
        }
    }
    foreach (array_keys($closed) as $seasonId) {
        $won = 0;
        $surrender = false;
        $complete = true;
        $played = 0;
        foreach ($matches as $m) {
            if ((int) ($m['season_id'] ?? 0) !== $seasonId || ygo_stats_eligible($m) !== null) {
                continue;
            }
            $side =
                $m['team_a_id'] === $r['team_id']
                    ? 'A'
                    : ($m['team_b_id'] === $r['team_id']
                        ? 'B'
                        : null);
            if ($side === null) {
                continue;
            }
            $played++;
            foreach ($m['duels'] ?? [] as $d) {
                if (empty($d['defeat_method'])) {
                    $complete = false;
                }
                if (($d['winner'] ?? '') === $side) {
                    $won++;
                } elseif (
                    ($d['winner'] ?? '') !== 'E' &&
                    ($d['defeat_method'] ?? '') === 'surrender'
                ) {
                    $surrender = true;
                }
            }
            if (empty($m['duels_complete'])) {
                $complete = false;
            }
        }
        if ($played && $complete) {
            $counts['no_surrender_season'] =
                $won >= 5 && !$surrender ? 1 : $counts['no_surrender_season'] ?? 0;
        }
    }
    $out = [];
    foreach (ygo_medal_definitions() as $index => $d) {
        [$code, $name, $rarity, $field, $target] = $d;
        if ($code === 'three_decks') {
            $code = 'three_events';
            $name = 'Victoria en tres eventos';
            $field = 'events_won';
        }
        $v = $counts[$field] ?? null;
        $unlocked = $v !== null && $v >= $target;
        $secret = !empty($d[5]);
        if ($secret && !$unlocked && !empty($r['tier_unknown_wins'])) {
            $v = null;
        }
        $requiredCoverage = match ($field) {
            'flawless_wins' => ['lp_history_win_known_n', 'VG'],
            'brick_wins' => ['brick_win_known_n', 'VG'],
            'second_wins' => null,
            'under_10', 'under_5' => ['duration_win_n', 'VG'],
            'under_5_turns', 'under_3_turns' => ['turn_win_n', 'VG'],
            'surrender_wins', 'time_wins', 'deck_out_wins', 'effect_wins' => [
                'closure_win_known_n',
                'VG',
            ],
            'damage_dealt' => ['lp_n', 'JD'],
            default => null,
        };
        if (
            !$unlocked &&
            $requiredCoverage &&
            ($r[$requiredCoverage[0]] ?? 0) < $r[$requiredCoverage[1]]
        ) {
            $v = null;
        }
        if (!$unlocked && $field === 'second_wins' && $r['first_n'] + $r['second_n'] < $r['JD']) {
            $v = null;
        }
        $out[] = [
            'code' => $code,
            'name' => $secret && !$unlocked ? 'Logro secreto' : $name,
            'rarity' => $rarity,
            'category' => ['Trayectoria', 'Dominio', 'Resiliencia', 'Proezas', 'Adicionales'][
                intdiv($index, 8)
            ],
            'entity_type' => 'team',
            'scope' => 'identidad equipo; histórico elegible',
            'unit' => $secret && !$unlocked ? null : $field,
            'target' => $secret && !$unlocked ? null : $target,
            'progress' => $secret && !$unlocked ? null : $v,
            'earned_moment' => $unlocked ? $r['achievement_moments'][$code] ?? null : null,
            'status' => $unlocked
                ? 'DESBLOQUEADO'
                : ($v === null
                    ? 'REVISION PENDIENTE'
                    : 'BLOQUEADO'),
            'evidence' => $unlocked ? $r['evidence'] : [],
            'version' => YGO_STATS_VERSION,
        ];
    }
    $out[] = [
        'code' => 'format_victory',
        'name' => 'Primera victoria en ' . $r['format_id'],
        'rarity' => 'silver',
        'scope' => 'equipo/formato',
        'unit' => 'match_wins',
        'target' => 1,
        'progress' => $r['V'],
        'status' => $r['V'] > 0 ? 'DESBLOQUEADO' : 'BLOQUEADO',
        'evidence' => $r['V'] > 0 ? $r['evidence'] : [],
        'version' => YGO_STATS_VERSION,
    ];
    return $out;
}

/** Official global positions close once per calendar month, in Colombia time. */
function ygo_stats_global_cuts(string $start, string $now): array
{
    $tz = new DateTimeZone('America/Bogota');
    $first = new DateTimeImmutable(substr($start, 0, 10) . ' 00:00:00', $tz);
    $limit = new DateTimeImmutable($now, $tz);
    $cuts = [];
    for (
        $month = $first->modify('first day of this month');
        ;
        $month = $month->modify('first day of next month')
    ) {
        $cut = $month->modify('last day of this month')->setTime(23, 59, 59);
        if ($cut > $limit) {
            break;
        }
        $cuts[] = ['at' => $cut->format('Y-m-d H:i:s'), 'final' => false];
    }
    return $cuts;
}

function ygo_stats_cuts(string $start, string $end, string $now): array
{
    $tz = new DateTimeZone('America/Bogota');
    $s = new DateTimeImmutable(substr($start, 0, 10) . ' 00:00:00', $tz);
    $e = new DateTimeImmutable(substr($end, 0, 10) . ' 23:59:59', $tz);
    $n = new DateTimeImmutable($now, $tz);
    $cuts = [];
    for ($d = $s->modify('+14 days'); $d < $e && $d <= $n; $d = $d->modify('+14 days')) {
        $cuts[] = ['at' => $d->format('Y-m-d H:i:s'), 'final' => false];
    }
    if ($e <= $n) {
        $cuts[] = ['at' => $e->format('Y-m-d H:i:s'), 'final' => true];
    }
    return $cuts;
}

function ygo_stats_analytics(array $data, int $minimum = 3): array
{
    return \StationX\Yugioh\Domain\Statistics\Analytics::analytics($data, $minimum);
}

/** Export projections are shared by CSV and PDF; all values come from the API snapshot. */
function ygo_stats_export_rows(array $data, string $collection = 'global'): array
{
    if ($collection === 'ffa_ranking') {
        $data['ffa_ranking'] = $data['free_for_all']['ranking'] ?? [];
    }
    if ($collection === 'ffa_ledger') {
        $data['ffa_ledger'] = [];
        foreach ($data['free_for_all']['ledger'] ?? [] as $entry) {
            foreach ($entry['changes'] as $change) {
                $data['ffa_ledger'][] = array_merge($entry, $change);
            }
        }
    }
    if ($collection === 'profile') {
        $rows = [['Equipo/deck: historico confirmado; ratings separados por formato']];
        foreach (['global', 'profiles', 'medals', 'ffa_ranking'] as $section) {
            $rows[] = [strtoupper($section)];
            $rows = array_merge($rows, ygo_stats_export_rows($data, $section));
        }
        $rows[] = ['LOGROS: no conceden puntos de Ranking Global'];
        $seen = [];
        foreach ($data['global'] ?? [] as $g) {
            foreach ($g['achievements'] ?? [] as $a) {
                $key =
                    $g['team_id'] .
                    ':' .
                    $a['code'] .
                    ($a['code'] === 'format_victory' ? ':' . $g['format_id'] : '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows[] = [
                    $g['team_id'],
                    $a['name'],
                    $a['status'],
                    $a['progress'] ?? 'N/D',
                    $a['target'] ?? 'N/D',
                    $a['scope'] ?? '',
                    $data['version'] ?? YGO_STATS_VERSION,
                ];
            }
        }
        return $rows;
    }
    if (in_array($collection, ['analytics', 'tops'], true)) {
        $rows = [
            ['indicador', 'formato', 'equipo_id', 'valor', 'muestra', 'unidad', 'corte', 'version'],
        ];
        foreach (
            $data['analytics'][$collection === 'tops' ? 'tops' : 'rankings'] ?? []
            as $metric
        ) {
            foreach ($metric['rows'] as $entry) {
                $rows[] = [
                    $metric['name'],
                    $entry['format_id'],
                    $entry['team_id'] ?? ($entry['a'] ?? null),
                    $entry['value'],
                    $entry['sample'],
                    $metric['unit'] ?? 'ver indicador',
                    $data['cutoff'] ?? null,
                    $data['version'] ?? YGO_STATS_VERSION,
                ];
            }
        }
        return $rows;
    }
    $fields = [
        'global' => [
            'format_id',
            'team_id',
            'deck_id',
            'rating',
            'bonus_medallas',
            'total',
            'state',
            'J',
            'position',
        ],
        'metagame' => [
            'season_id',
            'format_id',
            'deck_id',
            'tier',
            'IC',
            'R',
            'EL',
            'C',
            'RE',
            'RD',
            'match_WR',
            'J',
            'JD',
            'quality_status',
        ],
        'medals' => [
            'event_id',
            'name',
            'format_id',
            'team_id',
            'deck_id',
            'bonus_points',
            'bonus_version',
            'status',
        ],
        'ledger' => [
            'match_id',
            'source_key',
            'kind',
            'seat',
            'format_id',
            'team_a_id',
            'team_b_id',
            'deck_a_id',
            'deck_b_id',
            'played_at',
            'before_a',
            'before_b',
            'expectation_a',
            'k',
            'delta_a',
            'delta_b',
            'after_a',
            'after_b',
            'participants',
        ],
        'ffa_ranking' => [
            'format_id',
            'team_id',
            'deck_id',
            'rating',
            'state',
            'J',
            'V',
            'D',
            'WR',
            'position',
        ],
        'ffa_ledger' => [
            'match_id',
            'event_id',
            'season_id',
            'format_id',
            'played_at',
            'winner',
            'deck_id',
            'team_id',
            'before',
            'expectation',
            'score',
            'k',
            'delta',
            'after',
        ],
        'matchups' => ['format_id', 'a', 'b', 'J', 'V', 'E', 'D', 'WR', 'performance'],
        'players' => ['player_id', 'format_id', 'JD', 'VG', 'EG', 'DG'],
        'profiles' => [
            'team_id',
            'J',
            'V',
            'E',
            'D',
            'JD',
            'VG',
            'EG',
            'DG',
            'first_n',
            'first_w',
            'second_n',
            'second_w',
            'brick_n',
            'brick',
            'brick_w',
            'lp_n',
            'lp_net',
            'lp_received',
            'turn_n',
            'turn_sum',
            'duration_n',
            'duration_sum',
            'fastest',
            'fewest_turns',
        ],
        'pending' => ['match_id', 'reason'],
    ];
    if (!isset($fields[$collection])) {
        throw new InvalidArgumentException('Coleccion estadistica no exportable.');
    }
    $rows = [array_merge($fields[$collection], ['corte', 'version', 'estado_corte'])];
    foreach ($data[$collection] ?? [] as $entry) {
        $row = [];
        foreach ($fields[$collection] as $field) {
            $value = $entry[$field] ?? 'N/D';
            $row[] = is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
                : $value;
        }
        $rows[] = array_merge($row, [
            $data['cutoff'] ?? 'N/D',
            $data['version'] ?? YGO_STATS_VERSION,
            $data['status'] ?? 'PENDIENTE',
        ]);
    }
    return $rows;
}

function ygo_stats_filter(array $matches, array $filters): array
{
    $allowed = [
        'event_id',
        'season_id',
        'division_id',
        'phase',
        'event_type',
        'team_id',
        'deck_id',
        'player_id',
        'judge',
        'venue',
        'from',
        'to',
        'format_id',
    ];
    foreach (array_keys($filters) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new InvalidArgumentException('Filtro no soportado: ' . $key);
        }
    }
    foreach (['from', 'to'] as $key) {
        if (!empty($filters[$key])) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
            if (!$d || $d->format('Y-m-d') !== $filters[$key]) {
                throw new InvalidArgumentException('Fecha de filtro invalida.');
            }
        }
    }
    return array_values(
        array_filter($matches, static function ($m) use ($filters) {
            foreach ($filters as $key => $value) {
                if ($value === '') {
                    continue;
                }
                if ($key === 'from' && substr($m['played_at'], 0, 10) < $value) {
                    return false;
                }
                if ($key === 'to' && substr($m['played_at'], 0, 10) > $value) {
                    return false;
                }
                if (in_array($key, ['from', 'to'], true)) {
                    continue;
                }
                if (in_array($key, ['team_id', 'deck_id'], true)) {
                    $ids = [
                        (int) ($m[str_replace('_id', '_a_id', $key)] ?? 0),
                        (int) ($m[str_replace('_id', '_b_id', $key)] ?? 0),
                    ];
                    foreach (
                        array_merge(
                            $m['participants_a'] ?? [],
                            $m['participants_b'] ?? [],
                            $m['players'] ?? [],
                        )
                        as $p
                    ) {
                        $ids[] = (int) $p[$key];
                    }
                    if (!in_array((int) $value, $ids, true)) {
                        return false;
                    }
                    continue;
                }
                if ($key === 'player_id') {
                    $found = false;
                    foreach ($m['duels'] ?? [] as $d) {
                        if (
                            in_array(
                                (int) $value,
                                [(int) ($d['player_a_id'] ?? 0), (int) ($d['player_b_id'] ?? 0)],
                                true,
                            )
                        ) {
                            $found = true;
                        }
                    }
                    if (!$found) {
                        return false;
                    }
                    continue;
                }
                if (!array_key_exists($key, $m) || (string) $m[$key] !== $value) {
                    return false;
                }
            }
            return true;
        }),
    );
}
