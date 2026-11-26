<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Teams;

use RuntimeException;

final class PlayerProfile
{
    public static function fromInput(array $input): array
    {
        $id = max(0, (int) ($input['player_id'] ?? 0));
        $name = trim((string) ($input['player_name'] ?? ''));
        $alias = trim((string) ($input['player_alias'] ?? ''));
        $teamId = max(0, (int) ($input['player_team_id'] ?? 0));
        $deckIds = TeamManagementPolicy::ids((array) ($input['player_deck_ids'] ?? []));
        if ($name === '') {
            throw new RuntimeException('El nombre del jugador es obligatorio.');
        }
        return [
            'id' => $id,
            'name' => $name,
            'alias' => $alias,
            'teamId' => $teamId,
            'deckIds' => $deckIds,
        ];
    }
}
