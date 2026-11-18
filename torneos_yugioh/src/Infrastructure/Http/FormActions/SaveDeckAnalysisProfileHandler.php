<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use Throwable;
use RuntimeException;

/** Native save_deck_analysis_profile adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveDeckAnalysisProfileHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $deckId = max(0, (int) ($post['deck_analysis_deck_id'] ?? 0));
        $deckStmt = $pdo->prepare('SELECT * FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
        $deckStmt->execute([$deckId]);
        $deck = $deckStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$deck) {
            throw new RuntimeException('El deck seleccionado ya no esta activo.');
        }
        $profileListJson = static function (string $field): string {
            $raw = (string) ($post[$field] ?? '');
            $structured = json_decode($raw, true);
            $isStructured =
                is_array($structured) &&
                array_is_list($structured) &&
                count(array_filter($structured, 'is_string')) === count($structured);
            $rows = $isStructured ? $structured : (preg_split('/[\r\n,;]+/', $raw) ?: []);
            $limit = $isStructured ? 2400 : 240;
            $rows = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn($value): string => mb_substr(
                                trim((string) $value),
                                0,
                                $limit,
                            ),
                            $rows,
                        ),
                    ),
                ),
            );
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $score = static fn(string $field): int => max(0, min(5, (int) ($post[$field] ?? 0)));
        $confidence = strtoupper(trim((string) ($post['deck_analysis_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) {
            $confidence = 'MEDIA';
        }
        $sourceType = strtoupper(
            trim((string) ($post['deck_analysis_source_type'] ?? 'EDITORIAL')),
        );
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) {
            $sourceType = 'EDITORIAL';
        }
        $values = [
            mb_substr(trim((string) ($post['deck_analysis_play_style'] ?? '')), 0, 120),
            mb_substr(trim((string) ($post['deck_analysis_pace'] ?? '')), 0, 30),
            mb_substr(trim((string) ($post['deck_analysis_complexity'] ?? '')), 0, 30),
            trim((string) ($post['deck_analysis_primary_game_plan'] ?? '')),
            trim((string) ($post['deck_analysis_win_condition'] ?? '')),
            trim((string) ($post['deck_analysis_resource_engine'] ?? '')),
            trim((string) ($post['deck_analysis_opening_plan'] ?? '')),
            trim((string) ($post['deck_analysis_mid_game_plan'] ?? '')),
            trim((string) ($post['deck_analysis_late_game_plan'] ?? '')),
            trim((string) ($post['deck_analysis_going_first_plan'] ?? '')),
            trim((string) ($post['deck_analysis_going_second_plan'] ?? '')),
            trim((string) ($post['deck_analysis_side_deck_strategy'] ?? '')),
            $profileListJson('deck_analysis_strengths'),
            $profileListJson('deck_analysis_weaknesses'),
            $profileListJson('deck_analysis_strategy_tags'),
            $score('deck_analysis_consistency_score'),
            $score('deck_analysis_ceiling_score'),
            $score('deck_analysis_resilience_score'),
            $score('deck_analysis_interaction_score'),
            $score('deck_analysis_recovery_score'),
            $score('deck_analysis_first_score'),
            $score('deck_analysis_second_score'),
            $confidence,
            $sourceType,
            trim((string) ($post['deck_analysis_notes'] ?? '')),
        ];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $pdo->prepare(
                "
                INSERT INTO sx_ygo_deck_analysis_profiles
                    (deck_id, play_style, pace, complexity, primary_game_plan, win_condition, resource_engine, opening_plan, mid_game_plan, late_game_plan, going_first_plan, going_second_plan, side_deck_strategy, strengths_json, weaknesses_json, strategy_tags_json, consistency_score, ceiling_score, resilience_score, interaction_score, recovery_score, first_score, second_score, confidence, source_type, analysis_notes, revision)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE play_style=VALUES(play_style), pace=VALUES(pace), complexity=VALUES(complexity), primary_game_plan=VALUES(primary_game_plan), win_condition=VALUES(win_condition), resource_engine=VALUES(resource_engine), opening_plan=VALUES(opening_plan), mid_game_plan=VALUES(mid_game_plan), late_game_plan=VALUES(late_game_plan), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_deck_strategy=VALUES(side_deck_strategy), strengths_json=VALUES(strengths_json), weaknesses_json=VALUES(weaknesses_json), strategy_tags_json=VALUES(strategy_tags_json), consistency_score=VALUES(consistency_score), ceiling_score=VALUES(ceiling_score), resilience_score=VALUES(resilience_score), interaction_score=VALUES(interaction_score), recovery_score=VALUES(recovery_score), first_score=VALUES(first_score), second_score=VALUES(second_score), confidence=VALUES(confidence), source_type=VALUES(source_type), analysis_notes=VALUES(analysis_notes), revision=revision+1
            ",
            )->execute([$deckId, ...$values]);
            $savedStmt = $pdo->prepare(
                'SELECT * FROM sx_ygo_deck_analysis_profiles WHERE deck_id=? LIMIT 1',
            );
            $savedStmt->execute([$deckId]);
            $saved = $savedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $revision = (int) ($saved['revision'] ?? 1);
            $pdo->prepare(
                'INSERT INTO sx_ygo_deck_analysis_profile_history (deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?)',
            )->execute([
                $deckId,
                $revision,
                json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                trim((string) ($post['deck_analysis_change_reason'] ?? '')),
            ]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'deck_analysis',
                $deckId,
                'save_deck_analysis_profile',
                json_encode(
                    ['revision' => $revision, 'source_type' => $sourceType],
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
        return 'deck_analysis_' . $deckId;

        return null;
    }
}
