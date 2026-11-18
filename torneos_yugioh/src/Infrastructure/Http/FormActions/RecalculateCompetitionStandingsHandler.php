<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native recalculate_competition_standings adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class RecalculateCompetitionStandingsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $competitionId = (int) ($post['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $exists = 0;
        if ($competitionId > 0) {
            $stmt = $pdo->prepare(
                "SELECT id FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 AND type NOT IN ('cup', 'final') LIMIT 1",
            );
            $stmt->execute([$competitionId, $seasonId]);
            $exists = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($exists > 0) {
            ygo_persist_competition_standings(
                $pdo,
                $seasonId,
                $competitionId,
                'manual_recalculate',
            );
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'competition',
                $competitionId,
                'recalculate_standings',
                json_encode(['source' => 'manual_button'], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return 'ranking_recalculado_' . $competitionId . '_' . ($exists > 0 ? 1 : 0);

        return null;
    }
}
