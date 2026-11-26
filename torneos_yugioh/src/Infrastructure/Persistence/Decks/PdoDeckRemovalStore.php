<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckRemovalStore;

final readonly class PdoDeckRemovalStore implements DeckRemovalStore
{
    public function __construct(private PDO $pdo) {}

    public function archive(int $id): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_decks SET active=0 WHERE id=?')->execute([$id]);
    }

    public function auditBlocked(int $id, array $blocker): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                'deck',
                $id,
                'blocked_remove_deck_in_event',
                json_encode($blocker, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
