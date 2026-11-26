<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class DivisionMovementPlan
{
    public static function build(array $plans, array $ranking, array $original): array
    {
        $moves = [];
        foreach ($plans as $plan) {
            $divisionRanking = array_values(
                array_filter(
                    $ranking,
                    static fn($row) => (int) ($original[(int) $row['id']] ?? 0) === $plan['id'] &&
                        (int) ($row['pj'] ?? ($row['played'] ?? 0)) > 0,
                ),
            );
            if ($plan['promotion_slots'] + $plan['relegation_slots'] > count($divisionRanking)) {
                throw new InvalidArgumentException(
                    'La división no tiene suficientes participantes con resultados para los cupos configurados.',
                );
            }
            foreach (['promotion', 'relegation'] as $kind) {
                $slots = $plan[$kind . '_slots'];
                if (!$slots) {
                    continue;
                }
                $selected =
                    $kind === 'promotion'
                        ? array_slice($divisionRanking, 0, $slots)
                        : array_slice($divisionRanking, -$slots);
                foreach ($selected as $row) {
                    $moves[] = [
                        'deck_id' => (int) $row['id'],
                        'from_division_id' => $plan['id'],
                        'to_division_id' => $plan[$kind . '_to_division_id'],
                        'kind' => $kind,
                    ];
                }
            }
        }
        return $moves;
    }
}
