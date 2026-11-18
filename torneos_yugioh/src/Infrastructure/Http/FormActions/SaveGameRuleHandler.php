<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_game_rule adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveGameRuleHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $ruleId = (int) ($post['game_rule_id'] ?? 0);
        $ruleName = trim((string) ($post['game_rule_name'] ?? ''));
        $ruleCode = trim((string) ($post['game_rule_code'] ?? ''));
        if ($ruleCode === '' && $ruleName !== '') {
            $ruleCode = strtoupper(str_replace('-', '_', ygo_slugify($ruleName)));
        }
        $values = [
            $seasonId,
            $ruleCode,
            $ruleName,
            trim((string) ($post['game_rule_type'] ?? 'custom')),
            trim((string) ($post['game_rule_scope'] ?? 'general')),
            trim((string) ($post['game_rule_trigger'] ?? '')),
            trim((string) ($post['game_rule_effect'] ?? '')),
            (int) ($post['game_rule_points'] ?? 0),
            max(1, (int) ($post['game_rule_priority'] ?? 100)),
            isset($post['game_rule_active']) ? 1 : 0,
            trim((string) ($post['game_rule_notes'] ?? '')),
        ];
        if ($ruleCode !== '' && $ruleName !== '') {
            if ($ruleId > 0) {
                $pdo->prepare(
                    'UPDATE sx_ygo_game_rules SET code=?, name=?, rule_type=?, scope=?, trigger_event=?, effect=?, points_value=?, priority_order=?, active=?, notes=? WHERE id=? AND season_id=?',
                )->execute(array_merge(array_slice($values, 1), [$ruleId, $seasonId]));
            } else {
                $pdo->prepare(
                    '
                    INSERT INTO sx_ygo_game_rules
                        (season_id, code, name, rule_type, scope, trigger_event, effect, points_value, priority_order, active, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), rule_type=VALUES(rule_type), scope=VALUES(scope), trigger_event=VALUES(trigger_event), effect=VALUES(effect), points_value=VALUES(points_value), priority_order=VALUES(priority_order), active=VALUES(active), notes=VALUES(notes)
                ',
                )->execute($values);
                $ruleLookup = $pdo->prepare(
                    'SELECT id FROM sx_ygo_game_rules WHERE season_id = ? AND code = ? LIMIT 1',
                );
                $ruleLookup->execute([$seasonId, $ruleCode]);
                $ruleId = (int) $ruleLookup->fetchColumn();
            }
            if ($ruleId > 0) {
                $conditionIds = $post['condition_id'] ?? [];
                $conditionTypes = $post['condition_type'] ?? [];
                $conditionLabels = $post['condition_label'] ?? [];
                $conditionOperators = $post['condition_operator'] ?? [];
                $conditionTargets = $post['condition_target'] ?? [];
                $conditionResults = $post['condition_result'] ?? [];
                $conditionStatuses = $post['condition_status'] ?? [];
                $conditionScores = $post['condition_score'] ?? [];
                $conditionMessages = $post['condition_failure_message'] ?? [];
                $conditionActive = $post['condition_active'] ?? [];
                $pdo->prepare(
                    'UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?',
                )->execute([$ruleId]);
                $conditionUpsert = $pdo->prepare('
                    INSERT INTO sx_ygo_game_rule_conditions
                        (game_rule_id, condition_type, condition_label, operator, target_value, result_value, status_value, score_value, failure_message, sort_order, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $conditionUpdate = $pdo->prepare('
                    UPDATE sx_ygo_game_rule_conditions
                    SET condition_type=?, condition_label=?, operator=?, target_value=?, result_value=?, status_value=?, score_value=?, failure_message=?, sort_order=?, active=?
                    WHERE id=? AND game_rule_id=?
                ');
                $allowedTypes = [
                    'result_case',
                    'victory',
                    'defeat',
                    'draw',
                    'validation',
                    'score',
                    'availability',
                    'custom',
                ];
                $allowedOperators = [
                    'sequence_equals',
                    'defined',
                    'winner_in',
                    'winner_equals',
                    'score_min',
                    'score_max',
                    'points_value',
                    'requires_previous',
                    'blocks_when',
                    'custom',
                ];
                $allowedStatuses = [
                    'FINALIZADA',
                    'REQUIERE D5',
                    'EN CURSO',
                    'PENDIENTE',
                    'INVALID',
                ];
                $rows = max(count((array) $conditionLabels), count((array) $conditionTypes));
                for ($i = 0; $i < $rows; $i++) {
                    $label = trim((string) ($conditionLabels[$i] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $type = trim((string) ($conditionTypes[$i] ?? 'validation'));
                    $operator = trim((string) ($conditionOperators[$i] ?? 'defined'));
                    $type = in_array($type, $allowedTypes, true) ? $type : 'validation';
                    $operator = in_array($operator, $allowedOperators, true)
                        ? $operator
                        : 'defined';
                    $rowActive = in_array(
                        (string) ($conditionActive[$i] ?? '0'),
                        ['1', 'on', 'true'],
                        true,
                    )
                        ? 1
                        : 0;
                    $target = trim((string) ($conditionTargets[$i] ?? ''));
                    $result = trim((string) ($conditionResults[$i] ?? ''));
                    $result = in_array($result, ['A', 'B', 'E'], true) ? $result : '';
                    $statusValue = trim((string) ($conditionStatuses[$i] ?? 'FINALIZADA'));
                    $statusValue = in_array($statusValue, $allowedStatuses, true)
                        ? $statusValue
                        : 'FINALIZADA';
                    $scoreValue = trim((string) ($conditionScores[$i] ?? ''));
                    $message = trim((string) ($conditionMessages[$i] ?? ''));
                    $sort = ($i + 1) * 10;
                    $conditionId = (int) ($conditionIds[$i] ?? 0);
                    if ($conditionId > 0) {
                        $conditionUpdate->execute([
                            $type,
                            $label,
                            $operator,
                            $target,
                            $result,
                            $statusValue,
                            $scoreValue,
                            $message,
                            $sort,
                            $rowActive,
                            $conditionId,
                            $ruleId,
                        ]);
                    } else {
                        $conditionUpsert->execute([
                            $ruleId,
                            $type,
                            $label,
                            $operator,
                            $target,
                            $result,
                            $statusValue,
                            $scoreValue,
                            $message,
                            $sort,
                            $rowActive,
                        ]);
                    }
                }
            }
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'game_rule',
                $ruleId ?: null,
                'save_game_rule',
                json_encode(['code' => $ruleCode, 'name' => $ruleName], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return 'reglamento';

        return null;
    }
}
