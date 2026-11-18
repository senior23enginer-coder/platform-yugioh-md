<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native set_qualifier_status adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SetQualifierStatusHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $competitionId = ygo_selected_qualifier_competition_id($pdo);
        $status = (string) ($post['status'] ?? 'active');
        if (!in_array($status, ['draft', 'active', 'completed'], true)) {
            $status = 'active';
        }
        if ($status === 'completed') {
            if (!ygo_finalize_qualifier($pdo, $competitionId)) {
                return 'clasificatorio_incompleto';
            }
        }
        if ($competitionId) {
            $currentStmt = $pdo->prepare(
                "SELECT status FROM sx_ygo_competitions WHERE id=? AND season_id=? AND type='qualifier' AND active=1 LIMIT 1",
            );
            $currentStmt->execute([$competitionId, $seasonId]);
            $fromStatus = (string) ($currentStmt->fetchColumn() ?: '');
            $pdo->prepare(
                "UPDATE sx_ygo_competitions SET status=? WHERE id=? AND season_id=? AND type='qualifier' AND active=1",
            )->execute([$status, $competitionId, $seasonId]);
            ygo_record_competition_status_history(
                $pdo,
                $seasonId,
                $competitionId,
                $fromStatus,
                $status,
                'set_qualifier_status',
            );
        }
        return 'clasificatorio_' . $status;

        return null;
    }
}
