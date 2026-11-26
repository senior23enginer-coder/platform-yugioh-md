<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

final class DeckProfileInput
{
    public static function team(array $input, ?int $existing): int
    {
        return array_key_exists('team_id', $input)
            ? (($input['team_id'] ?? '') === '' ? 0 : (int) $input['team_id'])
            : (int) ($existing ?? 0);
    }

    public static function autoTeam(int $id, int $teamId, string $name, array $input): bool
    {
        return $id <= 0 && $teamId <= 0 && $name !== '' && array_key_exists('team_id', $input) && ($input['team_id'] ?? '') !== '';
    }

    public static function values(array $input, string $name, string $archetype, int $teamId, string $coverUrl): array
    {
        return [
            'divisionId' => ($input['division_id'] ?? '') === '' ? null : (int) $input['division_id'],
            'teamId' => $teamId > 0 ? $teamId : null,
            'rank' => max(1, (int) ($input['seed_rank'] ?? 999)),
            'name' => $name,
            'archetype' => $archetype,
            'style' => trim((string) $input['style']),
            'reason' => trim((string) $input['reason']),
            'keyCards' => trim((string) $input['key_cards']),
            'variations' => trim((string) $input['variations']),
            'notes' => trim((string) $input['notes']),
            'coverUrl' => $coverUrl,
        ];
    }
}
