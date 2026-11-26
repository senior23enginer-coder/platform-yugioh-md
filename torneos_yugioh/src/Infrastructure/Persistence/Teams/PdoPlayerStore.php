<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\PlayerStore;

final readonly class PdoPlayerStore implements PlayerStore
{
    public function __construct(private PDO $pdo) {}

    public function save(array $profile): int
    {
        $id = $profile['id'];
        $values = [$profile['teamId'] ?: null, $profile['name'], $profile['alias']];
        if ($id > 0) {
            $this->pdo->prepare('UPDATE sx_ygo_players SET team_id=?, display_name=?, alias=?, active=1 WHERE id=?')
                ->execute([...$values, $id]);
            return $id;
        }
        $this->pdo->prepare('INSERT INTO sx_ygo_players (team_id, display_name, alias, active) VALUES (?, ?, ?, 1)')
            ->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function replaceDecks(int $id, array $deckIds): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_player_decks SET active=0 WHERE player_id=?')->execute([$id]);
        $link = $this->pdo->prepare('INSERT INTO sx_ygo_player_decks (player_id, deck_id, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE active=1');
        foreach ($deckIds as $deckId) {
            $link->execute([$id, $deckId]);
        }
    }

    public function audit(int $id, int $teamId, array $deckIds): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES ("player", ?, "save_player", ?)')
            ->execute([$id, json_encode(['team_id' => $teamId, 'deck_ids' => $deckIds], JSON_UNESCAPED_UNICODE)]);
    }
}
