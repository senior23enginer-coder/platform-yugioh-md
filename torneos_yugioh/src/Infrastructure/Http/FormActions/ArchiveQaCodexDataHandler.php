<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use Throwable;

/** Native archive_qa_codex_data adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ArchiveQaCodexDataHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $counts = ['competitions' => 0, 'divisions' => 0, 'decks' => 0, 'teams' => 0];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE season_id=? AND name LIKE 'QA Codex %' AND name <> 'Clasificatorio inicial MD'",
            );
            $stmt->execute([$seasonId]);
            $counts['competitions'] = $stmt->rowCount();
            $stmt = $pdo->prepare(
                "UPDATE sx_ygo_divisions SET active=0 WHERE season_id=? AND name LIKE 'QA Codex %'",
            );
            $stmt->execute([$seasonId]);
            $counts['divisions'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_decks SET active=0 WHERE name LIKE 'QA Codex %'");
            $stmt->execute();
            $counts['decks'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_teams SET active=0 WHERE name LIKE 'QA Codex %'");
            $stmt->execute();
            $counts['teams'] = $stmt->rowCount();
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'qa',
                $seasonId,
                'archive_qa_codex_data',
                json_encode($counts, JSON_UNESCAPED_UNICODE),
            ]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return 'qa_codex_archivado_' . array_sum($counts);

        return null;
    }
}
