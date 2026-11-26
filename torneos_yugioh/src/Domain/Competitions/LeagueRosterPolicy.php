<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class LeagueRosterPolicy
{
    public static function normalize(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function styleAliases(string $ruleset): array
    {
        return [
            'tcg_advanced' => ['tcg avanzado', 'tcg_advanced'],
            'tcg_traditional' => ['tcg tradicional', 'tcg_traditional'],
            'goat' => ['goat', 'goat'],
            'rush_duel' => ['rush duel', 'rush_duel'],
        ][$ruleset] ?? [$ruleset, $ruleset];
    }

    public static function available(
        array $ids,
        int $competitionId,
        int $seasonId,
        array $assignments,
    ): array {
        $blocked = [];
        foreach ($assignments as $row) {
            if (
                (int) $row['season_id'] === $seasonId &&
                (int) $row['competition_id'] !== $competitionId
            ) {
                $blocked[(int) $row['deck_id']] = true;
            }
        }
        return array_values(array_filter($ids, static fn($id) => !isset($blocked[(int) $id])));
    }
}
