<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

final class GameRuleInput
{
    public static function ruleset(int $seasonId, string $code, string $label, array $post): array
    {
        $values = [
            $seasonId,
            $code,
            $label,
            trim((string) ($post['ruleset_family'] ?? 'custom')),
            trim((string) ($post['ruleset_banlist_name'] ?? '')),
            trim((string) ($post['ruleset_banlist_version'] ?? '')),
            $post['ruleset_banlist_effective_on'] ?? '' ?: null,
            trim((string) ($post['ruleset_match_format'] ?? '1v1')),
            max(1, min(10, (int) ($post['ruleset_best_of'] ?? 3))),
            max(1, min(3, (int) ($post['ruleset_participants_per_side'] ?? 1))),
            trim((string) ($post['ruleset_schedule_strategy'] ?? 'manual')),
            trim((string) ($post['ruleset_deck_pool'] ?? 'all_active')),
            trim((string) ($post['ruleset_victory_condition'] ?? '')),
            trim((string) ($post['ruleset_defeat_condition'] ?? '')),
            trim((string) ($post['ruleset_draw_condition'] ?? '')),
            trim((string) ($post['ruleset_notes'] ?? '')),
        ];
        return $values;
    }

    public static function rule(
        int $seasonId,
        string $ruleCode,
        string $ruleName,
        array $post,
    ): array {
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
        return $values;
    }

    public static function conditions(array $post): iterable
    {
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
        $allowedStatuses = ['FINALIZADA', 'REQUIERE D5', 'EN CURSO', 'PENDIENTE', 'INVALID'];
        $rows = max(count((array) $conditionLabels), count((array) $conditionTypes));
        for ($i = 0; $i < $rows; $i++) {
            $label = trim((string) ($conditionLabels[$i] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = trim((string) ($conditionTypes[$i] ?? 'validation'));
            $operator = trim((string) ($conditionOperators[$i] ?? 'defined'));
            $type = in_array($type, $allowedTypes, true) ? $type : 'validation';
            $operator = in_array($operator, $allowedOperators, true) ? $operator : 'defined';
            $rowActive = in_array((string) ($conditionActive[$i] ?? '0'), ['1', 'on', 'true'], true)
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
            yield [
                'id' => $conditionId,
                'values' => [
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
                ],
            ];
        }
    }
}
