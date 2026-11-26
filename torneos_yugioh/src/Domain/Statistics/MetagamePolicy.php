<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class MetagamePolicy
{
    public static function tier(array $r, array $policy = []): array
    {
        $policy = array_replace(StatisticsPolicy::defaults(), $policy);
        $weights = $policy['tier_weights'];
        if (count($weights) !== 5 || abs(array_sum($weights) - 1) > 1e-10 || min($weights) < 0) {
            throw new InvalidArgumentException('Pesos de metagame invalidos.');
        }
        $J = $r['J'];
        $R = RatingMath::ratio($r['V'] + 0.5 * $r['E'], $J);
        $RD = RatingMath::ratio($r['VG'] + 0.5 * $r['EG'], $r['JD']);
        $EL = max(0, min(100, 50 + ($r['rating_season'] - 1500) / 8));
        $C = $J ? 100 * RatingMath::wilson(($r['V'] + 0.5 * $r['E']) / $J, $J) : null;
        $recent = array_slice($r['recent'], -5);
        $RE = $recent ? (100 * array_sum($recent)) / count($recent) : null;
        $wr = RatingMath::ratio($r['V'], $J);
        $distinct = count($r['opponents']);
        $valid =
            $J >= $policy['meta_minimum_matches'] &&
            $distinct >= $policy['meta_minimum_opponents'] &&
            $r['duels_complete'] &&
            $RD !== null;
        $IC =
            $r['duels_complete'] && $RD !== null && $R !== null
                ? array_sum(
                    array_map(static fn($w, $value) => $w * $value, $weights, [
                        $R,
                        $EL,
                        $C,
                        $RE,
                        $RD,
                    ]),
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
            'duel_WR' => RatingMath::ratio($r['VG'], $r['JD']),
            'distinct_opponents' => $distinct,
            'quality_status' => $valid
                ? 'SUFICIENTE'
                : (!$r['duels_complete']
                    ? 'DUELOS INCOMPLETOS'
                    : 'MUESTRA INSUFICIENTE'),
        ];
    }
}
