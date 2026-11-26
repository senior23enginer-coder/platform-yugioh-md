<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamDeckLinkStore;

final readonly class PdoTeamDeckLinkStore implements TeamDeckLinkStore
{
    public function __construct(private PDO $pdo) {}

    public function active(int $teamId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM sx_ygo_teams WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$teamId]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    public function add(int $teamId, array $ids): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo
            ->prepare(
                "UPDATE sx_ygo_decks SET team_id=? WHERE active=1 AND team_id IS NULL AND id IN ($placeholders)",
            )
            ->execute([$teamId, ...$ids]);
    }

    public function remove(int $teamId, array $ids): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo
            ->prepare(
                "UPDATE sx_ygo_decks SET team_id=NULL WHERE active=1 AND team_id=? AND id IN ($placeholders)",
            )
            ->execute([$teamId, ...$ids]);
    }

    public function audit(int $teamId, array $add, array $remove): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                'team',
                $teamId,
                'save_team_deck_links',
                json_encode(['add' => $add, 'remove' => $remove], JSON_UNESCAPED_UNICODE),
            ]);
    }
}
