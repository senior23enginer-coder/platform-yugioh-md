<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class FreeForAllStatistics
{
    public const VERSION = 'STATION_X_FFA_ELO_1.0';

    public static function elo(array $ratings, int $winner, float $k = 20): array
    {
        if (
            count($ratings) < 3 ||
            count($ratings) > 4 ||
            !array_key_exists($winner, $ratings) ||
            $k <= 0
        ) {
            throw new InvalidArgumentException('Mesa Elo multijugador invalida.');
        }
        $max = max($ratings);
        $weights = [];
        foreach ($ratings as $id => $rating) {
            if (!is_numeric($rating) || !is_finite((float) $rating)) {
                throw new InvalidArgumentException('Rating invalido.');
            }
            $weights[$id] = 10 ** (((float) $rating - $max) / 600);
        }
        $sum = array_sum($weights);
        $out = [];
        foreach ($ratings as $id => $rating) {
            $expectation = $weights[$id] / $sum;
            $delta = $k * (($id === $winner ? 1 : 0) - $expectation);
            $out[$id] = [
                'before' => (float) $rating,
                'expectation' => $expectation,
                'score' => $id === $winner ? 1 : 0,
                'delta' => $delta,
                'after' => $rating + $delta,
            ];
        }
        return $out;
    }

    public static function calculate(string $cutoff, array $matches, array $policy = []): array
    {
        $policy = array_replace(
            [
                'base' => 1000,
                'k' => 20,
                'divisor' => 600,
                'minimum_matches' => 10,
                'minimum_opponents' => 3,
                'inactive_days' => 90,
                'version' => FreeForAllStatistics::VERSION,
            ],
            $policy,
        );
        usort(
            $matches,
            static fn($a, $b) => [$a['played_at'] ?? '', (int) $a['match_id']] <=> [
                $b['played_at'] ?? '',
                (int) $b['match_id'],
            ],
        );
        $rows = [];
        $ledger = [];
        $pending = [];
        $seen = [];
        foreach ($matches as $m) {
            $id = (int) $m['match_id'];
            if (($m['played_at'] ?? '') > $cutoff) {
                continue;
            }
            if (isset($seen[$id])) {
                if ($seen[$id] !== $m) {
                    throw new InvalidArgumentException('Mesa FFA duplicada contradictoria.');
                }
                continue;
            }
            $seen[$id] = $m;
            $players = $m['players'] ?? [];
            $reason = null;
            $decks = [];
            $format = $m['format_id'] ?? null;
            if (
                empty($m['confirmed']) ||
                empty($m['played_at']) ||
                !$format ||
                count($players) < 3 ||
                count($players) > 4 ||
                empty($m['competitive'])
            ) {
                $reason = 'Mesa pendiente, no competitiva o sin evidencia confirmada';
            }
            foreach ($players as $p) {
                if (
                    empty($p['deck_id']) ||
                    empty($p['team_id']) ||
                    ($p['quality'] ?? '') !== 'CONFIRMED_AT_ROSTER_FREEZE' ||
                    ($p['format_id'] ?? null) !== $format ||
                    isset($decks[$p['deck_id']])
                ) {
                    $reason = 'Identidad historica multijugador incompleta o repetida';
                }
                $decks[$p['deck_id']] = true;
            }
            if (!isset($decks[$m['winner'] ?? 0])) {
                $reason = 'Ganador de mesa inexistente';
            }
            if ($reason) {
                $pending[] = ['match_id' => $id, 'reason' => $reason];
                continue;
            }
            $ratings = [];
            foreach ($players as $p) {
                $key = $format . ':' . $p['deck_id'];
                $rows[$key] ??= [
                    'team_id' => $p['team_id'],
                    'deck_id' => $p['deck_id'],
                    'format_id' => $format,
                    'rating' => (float) $policy['base'],
                    'J' => 0,
                    'V' => 0,
                    'D' => 0,
                    'opponents' => [],
                    'last_at' => null,
                    'reactivation' => 3,
                ];
                $ratings[$p['deck_id']] = $rows[$key]['rating'];
            }
            $changes = FreeForAllStatistics::elo(
                $ratings,
                (int) $m['winner'],
                (float) $policy['k'],
            );
            $entries = [];
            foreach ($players as $p) {
                $key = $format . ':' . $p['deck_id'];
                $r = &$rows[$key];
                $change = $changes[$p['deck_id']];
                $r['rating'] = $change['after'];
                $r['J']++;
                $r[$p['deck_id'] === $m['winner'] ? 'V' : 'D']++;
                foreach ($players as $other) {
                    if ($other['deck_id'] !== $p['deck_id']) {
                        $r['opponents'][$other['deck_id']] = true;
                    }
                }
                if (
                    $r['last_at'] !== null &&
                    (new DateTimeImmutable($r['last_at']))->diff(
                        new DateTimeImmutable($m['played_at']),
                    )->days > $policy['inactive_days']
                ) {
                    $r['reactivation'] = 0;
                }
                $r['reactivation'] = min(3, $r['reactivation'] + 1);
                $r['last_at'] = $m['played_at'];
                $entries[] = array_merge($p, $change);
                unset($r);
            }
            $ledger[] = [
                'match_id' => $id,
                'event_id' => $m['event_id'],
                'season_id' => $m['season_id'],
                'format_id' => $format,
                'played_at' => $m['played_at'],
                'winner' => $m['winner'],
                'k' => $policy['k'],
                'changes' => $entries,
                'source_version' => $m['source_version'] ?? null,
            ];
        }
        foreach ($rows as &$r) {
            $inactive =
                (new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($cutoff))->days >
                $policy['inactive_days'];
            $r['state'] = $inactive
                ? 'INACTIVO'
                : ($r['reactivation'] < 3
                    ? 'REACTIVACION PENDIENTE'
                    : ($r['J'] < $policy['minimum_matches'] ||
                    count($r['opponents']) < $policy['minimum_opponents']
                        ? 'PROVISIONAL'
                        : 'CLASIFICADO'));
            $r['WR'] = (100 * $r['V']) / $r['J'];
        }
        unset($r);
        usort(
            $rows,
            static fn($a, $b) => strcmp($a['format_id'], $b['format_id']) ?:
            $b['rating'] <=> $a['rating'] ?:
            $b['V'] <=> $a['V'] ?:
            $a['deck_id'] <=> $b['deck_id'],
        );
        $position = [];
        foreach ($rows as &$r) {
            $r['position'] =
                $r['state'] === 'CLASIFICADO'
                    ? ($position[$r['format_id']] = ($position[$r['format_id']] ?? 0) + 1)
                    : null;
        }
        unset($r);
        return [
            'version' => FreeForAllStatistics::VERSION,
            'policy' => $policy,
            'ranking' => array_values($rows),
            'ledger' => $ledger,
            'pending' => $pending,
        ];
    }
}
