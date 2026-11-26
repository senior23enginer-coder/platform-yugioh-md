<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class TagStatistics
{
    public static function reason(array $m): ?string
    {
        if (
            ($m['confirmed'] ?? false) !== true ||
            !in_array($m['result'] ?? '', ['A', 'B', 'E'], true)
        ) {
            return 'Resultado Tag no confirmado';
        }
        if (empty($m['competitive']) || !empty($m['friendly'])) {
            return 'Tag no puntuable';
        }
        if (
            empty($m['played_at']) ||
            empty($m['format_id']) ||
            empty($m['match_id']) ||
            empty($m['duels'])
        ) {
            return 'Tag sin evidencia de juego';
        }
        $teams = [];
        $decks = [];
        foreach (['participants_a', 'participants_b'] as $side) {
            if (count($m[$side] ?? []) !== 2) {
                return 'Alineacion Tag incompleta';
            }
            foreach ($m[$side] as $p) {
                if (
                    ($p['quality'] ?? '') !== 'CONFIRMED_AT_ROSTER_FREEZE' ||
                    empty($p['team_id']) ||
                    empty($p['deck_id']) ||
                    ($p['format_id'] ?? '') !== $m['format_id']
                ) {
                    return 'Identidad historica Tag no confirmada';
                }
                if (isset($teams[$p['team_id']]) || isset($decks[$p['deck_id']])) {
                    return 'Identidad competitiva repetida en la mesa Tag';
                }
                $teams[$p['team_id']] = true;
                $decks[$p['deck_id']] = true;
            }
        }
        return null;
    }

    public static function accumulate(
        array &$r,
        array $m,
        array $person,
        array $opponents,
        bool $won,
        bool $draw,
    ): void {
        $score = $won ? 1.0 : ($draw ? 0.5 : 0.0);
        $r['J']++;
        $r[$won ? 'V' : ($draw ? 'E' : 'D')]++;
        $r['streak'] = $won ? $r['streak'] + 1 : 0;
        $r['best_streak'] = max($r['best_streak'], $r['streak']);
        foreach ($opponents as $o) {
            $r['opponents'][$o['team_id']] = true;
            if ($won) {
                $r['rivals_beaten'][$o['team_id']] = true;
            }
        }
        if (
            $r['last_at'] !== null &&
            (new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($m['played_at']))
                ->days > 90
        ) {
            $r['reactivation'] = 0;
        }
        $r['reactivation'] = min(3, $r['reactivation'] + 1);
        $r['last_at'] = $m['played_at'];
        $r['recent'][] = $score;
        $r['evidence'][] = $m['source_key'];
        $r['tag_matches'] = ($r['tag_matches'] ?? 0) + 1;
        $r['shared_duels'] = ($r['shared_duels'] ?? 0) + count($m['duels']);
        // Shared duel wins are not individual duel wins, LP damage or flawless evidence.
        if ($won) {
            $r['events_won_match'][$m['event_id'] ?? 0] = true;
            $r['formats'][$m['format_id']] = true;
            $r['format_moments'][$m['format_id']] ??= [
                'sports_effective_at' => $m['played_at'],
                'match_id' => $m['match_id'],
            ];
        }
    }

    public static function apply(
        array $m,
        array $policy,
        array &$global,
        array &$profiles,
        array &$ledger,
    ): void {
        $format = $m['format_id'];
        $left = $m['participants_a'];
        $right = $m['participants_b'];
        $all = array_merge($left, $right);
        $before = [];
        foreach ($all as $p) {
            $key = $p['team_id'] . ':' . $format;
            $global[$key] ??= MetricFactory::metric($p['team_id'], $p['deck_id'], $format);
            $global[$key]['deck_id'] = $p['deck_id'];
            $before[$p['team_id']] = $global[$key]['rating'];
        }
        $k = (float) $policy['k_mapping'][$m['ranking_impact']];
        $score = $m['result'] === 'A' ? 1.0 : ($m['result'] === 'E' ? 0.5 : 0.0);
        $change = RatingMath::tagElo(
            array_map(fn($p) => $before[$p['team_id']], $left),
            array_map(fn($p) => $before[$p['team_id']], $right),
            $score,
            $k,
        );
        $changes = [];
        foreach (
            [
                ['A', $left, $right, $change['delta_a'], $change['before_b']],
                ['B', $right, $left, $change['delta_b'], $change['before_a']],
            ]
            as [$side, $members, $opponents, $delta, $rivalMean]
        ) {
            foreach ($members as $p) {
                $key = $p['team_id'] . ':' . $format;
                $profile = $p['team_id'];
                $profiles[$profile] ??= MetricFactory::metric($profile, $p['deck_id'], '*');
                $won = $m['result'] === $side;
                $draw = $m['result'] === 'E';
                TagStatistics::accumulate($global[$key], $m, $p, $opponents, $won, $draw);
                TagStatistics::accumulate($profiles[$profile], $m, $p, $opponents, $won, $draw);
                $global[$key]['rival_pre_sum'] += $rivalMean;
                $global[$key]['rating'] = $before[$profile] + $delta;
                foreach (AchievementPolicy::achievements($profiles[$profile]) as $a) {
                    if ($a['status'] === 'DESBLOQUEADO') {
                        $profiles[$profile]['achievement_moments'][$a['code']] ??= [
                            'sports_effective_at' => $m['played_at'],
                            'match_id' => $m['match_id'],
                        ];
                    }
                }
                $changes[] = [
                    'team_id' => $profile,
                    'deck_id' => $p['deck_id'],
                    'side' => $side,
                    'before' => $before[$profile],
                    'delta' => $delta,
                    'after' => $before[$profile] + $delta,
                    'rival_mean' => $rivalMean,
                ];
            }
        }
        $ledger[] = array_merge($change, [
            'kind' => 'tag',
            'source_key' => $m['source_key'],
            'match_id' => $m['match_id'],
            'format_id' => $format,
            'season_id' => $m['season_id'],
            'event_id' => $m['event_id'],
            'played_at' => $m['played_at'],
            'result' => $m['result'],
            'team_a_id' => null,
            'team_b_id' => null,
            'deck_a_id' => null,
            'deck_b_id' => null,
            'participants' => $changes,
            'source_version' => $m['source_version'],
            'algorithm_version' => StatisticsPolicy::VERSION,
        ]);
    }
}
