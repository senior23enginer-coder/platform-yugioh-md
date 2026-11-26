<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class StatisticsReplay
{
    public static function calculate(
        string $cutoff,
        array $matches,
        array $events = [],
        array $honors = [],
        array $policy = [],
        array $publishedTiers = [],
        array $ffaMatches = [],
    ): array {
        $policy = array_replace(StatisticsPolicy::defaults(), $policy);
        $policy['global_cadence'] = 'per_result';
        $policy['minimum_matches'] = 2;
        $policy['minimum_opponents'] = 1;
        $policy['version'] = StatisticsPolicy::VERSION;
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
                $reason = TagStatistics::reason($m);
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
                TagStatistics::apply($m, $policy, $global, $teamMetrics, $ledger);
                continue;
            }
            if (($reason = StatisticsPolicy::eligible($m)) !== null) {
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
                $global[$k] ??= MetricFactory::metric(
                    (int) $m['team_' . $suffix . '_id'],
                    (int) $m['deck_' . $suffix . '_id'],
                    $format,
                );
                $global[$k]['deck_id'] = (int) $m['deck_' . $suffix . '_id'];
                $sk = $s . ':' . $format . ':' . $m['deck_' . $suffix . '_id'];
                $skeys[] = $sk;
                $season[$sk] ??= MetricFactory::metric(
                    (int) $m['team_' . $suffix . '_id'],
                    (int) $m['deck_' . $suffix . '_id'],
                    $format,
                );
                $season[$sk]['season_id'] = $s;
            }
            [$a, $b] = $keys;
            [$sa, $sb] = $skeys;
            $change = RatingMath::elo(
                $global[$a]['rating'],
                $global[$b]['rating'],
                $score,
                (float) $importanceK,
            );
            $sc = RatingMath::elo(
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
                MetricAccumulator::accumulate($global[$key], $m, $side);
                MetricAccumulator::accumulate($season[$sk], $m, $side);
                $team = (int) $m['team_' . $sf . '_id'];
                $teamMetrics[$team] ??= MetricFactory::metric(
                    $team,
                    (int) $m['deck_' . $sf . '_id'],
                    '*',
                );
                MetricAccumulator::accumulate($teamMetrics[$team], $m, $side);
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
                $tier = HistoricalMetrics::historicalTier(
                    $publishedTiers,
                    $s,
                    $format,
                    (int) $m['deck_' . $of . '_id'],
                    $m['played_at'],
                );
                if (
                    $ss === 1 &&
                    in_array($tier, ['Tier Zero', 'Tier 1', 'Tier 2', 'Tier 3'], true)
                ) {
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
                if (
                    $ss === 1 &&
                    in_array($tier, ['Tier Zero', 'Tier 1', 'Tier 2', 'Tier 3'], true)
                ) {
                    $teamMetrics[$team]['tiers_beaten'][$tier] = true;
                    if ($tier === 'Tier Zero') {
                        $teamMetrics[$team]['tier_zero_wins']++;
                    }
                }
                if ($ss === 1 && $tier === null) {
                    $global[$key]['tier_unknown_wins']++;
                    $teamMetrics[$team]['tier_unknown_wins']++;
                }
                foreach (AchievementPolicy::achievements($teamMetrics[$team]) as $achievement) {
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
                'algorithm_version' => StatisticsPolicy::VERSION,
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
                $m['result'] === 'E'
                    ? 'E'
                    : (($score === 1) == ($lo === $m['team_a_id'])
                        ? 'V'
                        : 'D')
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
        $medals = HistoricalMetrics::championships($events, $honors, $policy, $cutoff);
        $bonuses = [];
        foreach ($medals as $medal) {
            if ($medal['status'] === 'ENTREGADA') {
                $key = $medal['team_id'] . ':' . $medal['format_id'];
                $bonuses[$key] = ($bonuses[$key] ?? 0) + $medal['bonus_points'];
                $global[$key] ??= MetricFactory::metric(
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
                (new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($cutoff))->days >
                    90;
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
            $r['WR'] = RatingMath::ratio($r['V'], $r['J']);
            $r['performance'] = RatingMath::ratio($r['V'] + 0.5 * $r['E'], $r['J']);
            $r['achievements'] = AchievementPolicy::achievements(
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
            $r = array_merge($r, MetagamePolicy::tier($r, $policy));
        }
        unset($r);
        foreach ($matchups as &$r) {
            $r['WR'] = RatingMath::ratio($r['V'], $r['J']);
            $r['performance'] = RatingMath::ratio($r['V'] + 0.5 * $r['E'], $r['J']);
        }
        unset($r);
        return [
            'version' => StatisticsPolicy::VERSION,
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
            'free_for_all' => FreeForAllStatistics::calculate($cutoff, $ffaMatches, []),
            'confirmed_ffa_matches' => array_values(
                array_filter(
                    $ffaMatches,
                    static fn($m) => !empty($m['confirmed']) && ($m['played_at'] ?? '') <= $cutoff,
                ),
            ),
        ];
    }
}
