<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use Throwable;

/** Native delete_manual_friendly adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class DeleteManualFriendlyHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $matchId = max(0, (int) ($post['friendly_match_id'] ?? 0));
        $matchStmt = $pdo->prepare(
            "SELECT id, deck_a_id, deck_b_id, scheduled_on, starts_at, status FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL LIMIT 1",
        );
        $matchStmt->execute([$matchId]);
        $manualFriendly = $matchStmt->fetch(PDO::FETCH_ASSOC);
        if (!$manualFriendly) {
            return 'amistoso_no_eliminado';
        }
        $pdo->beginTransaction();
        try {
            foreach (
                [
                    'sx_ygo_sync_requests',
                    'sx_ygo_turn_audit',
                    'sx_ygo_live_duel_state',
                    'sx_ygo_match_snapshots',
                    'sx_ygo_elo_history',
                    'sx_ygo_match_results',
                ]
                as $table
            ) {
                $pdo->prepare("DELETE FROM {$table} WHERE match_id = ?")->execute([$matchId]);
            }
            $pdo->prepare('DELETE FROM sx_ygo_medal_unlocks WHERE match_id = ?')->execute([
                $matchId,
            ]);
            $pdo->prepare('DELETE FROM sx_ygo_checkins WHERE match_id = ?')->execute([$matchId]);
            $pdo->prepare(
                "DELETE FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL",
            )->execute([$matchId]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'match',
                $matchId,
                'delete_manual_friendly',
                json_encode($manualFriendly, JSON_UNESCAPED_UNICODE),
            ]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        return 'amistoso_eliminado';

        return null;
    }
}
