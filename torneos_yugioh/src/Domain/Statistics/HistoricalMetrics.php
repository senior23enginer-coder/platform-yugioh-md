<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use InvalidArgumentException;

/** Reads supplied historical tiers and championships without publishing or creating new awards. */
final class HistoricalMetrics
{
    public static function historicalTier(
        array $cuts,
        int $season,
        string $format,
        int $deck,
        string $at,
    ): ?string {
        $found = null;
        $latest = '';
        foreach ($cuts as $c) {
            if (
                in_array($c['status'] ?? '', ['PUBLICADO', 'FINAL'], true) &&
                ($c['season_id'] ?? 0) === $season &&
                ($c['format_id'] ?? '') === $format &&
                ($c['deck_id'] ?? 0) === $deck &&
                ($c['published_at'] ?? '') < $at &&
                ($c['published_at'] ?? '') > $latest
            ) {
                $found = $c['tier'];
                $latest = $c['published_at'];
            }
        }
        return $found;
    }

    public static function championships(
        array $events,
        array $honors,
        array $policy,
        string $cutoff,
    ): array {
        $out = [];
        foreach ($events as $e) {
            if (strtolower((string) ($e['type'] ?? '')) === 'friendly') {
                continue;
            }
            $id = (int) $e['id'];
            if ($id < 1) {
                throw new InvalidArgumentException('La medalla requiere un evento identificado.');
            }
            $h = array_values(
                array_filter(
                    $honors,
                    fn($h) => (int) ($h['competition_id'] ?? 0) === $id &&
                        !empty($h['confirmed']) &&
                        ($h['effective_at'] ?? '') <= $cutoff,
                ),
            );
            $winner = count($h) === 1 ? $h[0] : null;
            $valid =
                $winner !== null &&
                !empty($winner['champion_team_id']) &&
                !empty($winner['champion_deck_id']) &&
                !empty($e['format_id']);
            $out[] = [
                'event_id' => $id,
                'name' => 'Campeón de ' . ($e['name'] ?? $id),
                'type' => $e['type'],
                'season_id' => $e['season_id'] ?? null,
                'format_id' => $e['format_id'] ?? null,
                'visual_asset_reference' => $e['logo_url'] ?? null,
                'bonus_points' => $e['fixed_bonus'] ?? ($policy['medal_bonus'][$e['type']] ?? 20),
                'bonus_version' => $e['bonus_version'] ?? $policy['version'],
                'status' => $valid ? 'ENTREGADA' : 'PENDIENTE DE ENTREGA',
                'team_id' => $valid ? (int) $winner['champion_team_id'] : null,
                'deck_id' => $valid ? (int) $winner['champion_deck_id'] : null,
                'source' => $winner,
            ];
        }
        return $out;
    }
}
