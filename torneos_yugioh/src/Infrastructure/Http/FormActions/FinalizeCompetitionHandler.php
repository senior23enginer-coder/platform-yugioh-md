<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native finalize_competition adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class FinalizeCompetitionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $competitionId = (int) ($post['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, status FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1',
        );
        $stmt->execute([$competitionId, $seasonId]);
        $competition = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($competition) {
            $currentStatus = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
            $ready = ygo_competition_ready_to_finish($pdo, $competitionId);
            if (
                $ready['ok'] &&
                in_array(
                    $currentStatus,
                    ['active', 'activo', 'pending_event', 'pendiente_evento'],
                    true,
                )
            ) {
                $pdo->prepare(
                    "UPDATE sx_ygo_competitions SET status='completed' WHERE id=? AND season_id=?",
                )->execute([$competitionId, $seasonId]);
                ygo_persist_competition_standings(
                    $pdo,
                    $seasonId,
                    $competitionId,
                    'finalize_competition',
                );
                $divisionStmt = $pdo->prepare(
                    'SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND source_qualifier_id = ? AND active = 1',
                );
                $divisionStmt->execute([$seasonId, $competitionId]);
                $divisionMoves = 0;
                foreach (
                    array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN))
                    as $divisionId
                ) {
                    $divisionMoves += ygo_apply_division_from_qualifier($pdo, $divisionId);
                }
                $leagueMoves = ygo_apply_league_division_movements($pdo, $competitionId);
                $honors = ygo_record_competition_honor($pdo, $competitionId);
                ygo_record_competition_status_history(
                    $pdo,
                    $seasonId,
                    $competitionId,
                    (string) ($competition['status'] ?? ''),
                    'completed',
                    'finalize_competition',
                    $ready,
                );
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'competition',
                    $competitionId,
                    'finalize_competition',
                    json_encode(
                        $ready + [
                            'division_moves' => $divisionMoves,
                            'league_moves' => $leagueMoves,
                            'honors' => $honors,
                        ],
                        JSON_UNESCAPED_UNICODE,
                    ),
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'competition',
                    $competitionId,
                    'blocked_finalize_competition',
                    json_encode($ready + ['status' => $currentStatus], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
        return 'evento_configurado_' . $competitionId;

        return null;
    }
}
