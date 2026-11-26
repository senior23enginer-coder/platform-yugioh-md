<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\ManualFriendlyStore;

final readonly class PdoManualFriendlyStore implements ManualFriendlyStore
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, deck_a_id, deck_b_id, scheduled_on, starts_at, status FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL LIMIT 1",
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function delete(int $id): void
    {
        foreach (
            [
                'sx_ygo_sync_requests',
                'sx_ygo_turn_audit',
                'sx_ygo_live_duel_state',
                'sx_ygo_match_snapshots',
                'sx_ygo_elo_history',
                'sx_ygo_match_results',
                'sx_ygo_medal_unlocks',
                'sx_ygo_checkins',
            ]
            as $table
        ) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE match_id = ?")->execute([$id]);
        }
        $this->pdo
            ->prepare(
                "DELETE FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL",
            )
            ->execute([$id]);
    }

    public function audit(int $id, array $match): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                'match',
                $id,
                'delete_manual_friendly',
                json_encode($match, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
