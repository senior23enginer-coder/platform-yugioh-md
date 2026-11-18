<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native apply_qualifier_divisions adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ApplyQualifierDivisionsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $competitionId = (int) ($post['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $typeStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND type = 'qualifier' AND active = 1",
        );
        $typeStmt->execute([$competitionId, $seasonId]);
        if ((int) $typeStmt->fetchColumn() < 1) {
            return 'divisiones_aplicadas_' . $competitionId . '_0';
        }
        $divisionStmt = $pdo->prepare(
            'SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND source_qualifier_id = ? AND active = 1',
        );
        $divisionStmt->execute([$seasonId, $competitionId]);
        $moves = 0;
        foreach (array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN)) as $divisionId) {
            $moves += ygo_apply_division_from_qualifier($pdo, $divisionId);
        }
        $pdo->prepare(
            'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
        )->execute([
            'competition',
            $competitionId,
            'apply_qualifier_divisions',
            json_encode(['moved' => $moves], JSON_UNESCAPED_UNICODE),
        ]);
        return 'divisiones_aplicadas_' . $competitionId . '_' . $moves;

        return null;
    }
}
