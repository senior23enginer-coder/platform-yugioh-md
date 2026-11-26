<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class StatisticsPolicy
{
    public const VERSION = 'STATION_X_STATS_8.4';

    public static function defaults(): array
    {
        return [
            'version' => StatisticsPolicy::VERSION,
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

    public static function eligible(array $m): ?string
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
}
