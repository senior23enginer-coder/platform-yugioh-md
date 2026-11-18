<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native apply_league_movements adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ApplyLeagueMovementsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $competitionId = (int) ($post['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $typeStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND type = 'league' AND active = 1",
        );
        $typeStmt->execute([$competitionId, $seasonId]);
        if ((int) $typeStmt->fetchColumn() < 1) {
            return 'movimientos_liga_' . $competitionId . '_0';
        }
        return 'movimientos_liga_' .
            $competitionId .
            '_' .
            ygo_apply_league_division_movements($pdo, $competitionId);

        return null;
    }
}
