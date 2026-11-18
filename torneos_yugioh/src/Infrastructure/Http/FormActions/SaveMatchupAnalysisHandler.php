<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use Throwable;
use RuntimeException;

/** Native save_matchup_analysis adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveMatchupAnalysisHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $deckId = max(0, (int) ($post['matchup_deck_id'] ?? 0));
        $opponentId = max(0, (int) ($post['matchup_opponent_deck_id'] ?? 0));
        if ($deckId <= 0 || $opponentId <= 0 || $deckId === $opponentId) {
            throw new RuntimeException('Selecciona dos decks diferentes para guardar el analisis.');
        }
        $validDeck = $pdo->prepare(
            'SELECT COUNT(*) FROM sx_ygo_decks WHERE id IN (?, ?) AND active=1',
        );
        $validDeck->execute([$deckId, $opponentId]);
        if ((int) $validDeck->fetchColumn() !== 2) {
            throw new RuntimeException('Uno de los decks del matchup ya no esta activo.');
        }
        $score = max(-2, min(2, (int) ($post['strategic_score'] ?? 0)));
        $classificationByScore = [
            -2 => 'MUY_DESFAVORABLE',
            -1 => 'DESFAVORABLE',
            0 => 'EQUILIBRADO',
            1 => 'FAVORABLE',
            2 => 'MUY_FAVORABLE',
        ];
        $confidence = strtoupper(trim((string) ($post['matchup_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) {
            $confidence = 'MEDIA';
        }
        $sourceType = strtoupper(trim((string) ($post['matchup_source_type'] ?? 'EDITORIAL')));
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) {
            $sourceType = 'EDITORIAL';
        }
        $listJson = static function (string $field): string {
            $rows = preg_split('/[\r\n,;]+/', (string) ($post[$field] ?? '')) ?: [];
            $rows = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn($value): string => mb_substr(trim((string) $value), 0, 240),
                            $rows,
                        ),
                    ),
                ),
            );
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $scope = [
            mb_substr(trim((string) ($post['matchup_deck_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($post['matchup_opponent_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($post['matchup_ruleset_version'] ?? '')), 0, 180),
        ];
        $values = [
            $score,
            $classificationByScore[$score],
            trim((string) ($post['matchup_reason'] ?? '')),
            $listJson('matchup_advantages'),
            $listJson('matchup_disadvantages'),
            $listJson('matchup_choke_points'),
            trim((string) ($post['matchup_going_first_plan'] ?? '')),
            trim((string) ($post['matchup_going_second_plan'] ?? '')),
            trim((string) ($post['matchup_side_plan'] ?? '')),
            $confidence,
            $sourceType,
            trim((string) ($post['matchup_review_notes'] ?? '')),
        ];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $pdo->prepare(
                "
                INSERT INTO sx_ygo_matchup_analyses
                    (deck_id, opponent_deck_id, deck_version, opponent_version, ruleset_version, strategic_score, classification, reason, key_advantages_json, key_disadvantages_json, choke_points_json, going_first_plan, going_second_plan, side_plan, confidence, source_type, review_notes, revision, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                ON DUPLICATE KEY UPDATE strategic_score=VALUES(strategic_score), classification=VALUES(classification), reason=VALUES(reason), key_advantages_json=VALUES(key_advantages_json), key_disadvantages_json=VALUES(key_disadvantages_json), choke_points_json=VALUES(choke_points_json), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_plan=VALUES(side_plan), confidence=VALUES(confidence), source_type=VALUES(source_type), review_notes=VALUES(review_notes), revision=revision+1, active=1
            ",
            )->execute([$deckId, $opponentId, ...$scope, ...$values]);
            $analysisStmt = $pdo->prepare(
                'SELECT * FROM sx_ygo_matchup_analyses WHERE deck_id=? AND opponent_deck_id=? AND deck_version=? AND opponent_version=? AND ruleset_version=? LIMIT 1',
            );
            $analysisStmt->execute([$deckId, $opponentId, ...$scope]);
            $analysis = $analysisStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $analysisId = (int) ($analysis['id'] ?? 0);
            $revision = (int) ($analysis['revision'] ?? 1);
            $pdo->prepare(
                'INSERT INTO sx_ygo_matchup_analysis_history (analysis_id, deck_id, opponent_deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?, ?, ?)',
            )->execute([
                $analysisId,
                $deckId,
                $opponentId,
                $revision,
                json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                trim((string) ($post['matchup_change_reason'] ?? '')),
            ]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'matchup_analysis',
                $analysisId,
                'save_matchup_analysis',
                json_encode(
                    [
                        'deck_id' => $deckId,
                        'opponent_deck_id' => $opponentId,
                        'revision' => $revision,
                        'source_type' => $sourceType,
                    ],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        return 'matchup_deck_' . $deckId;

        return null;
    }
}
