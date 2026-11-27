<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

final class GameRuleReadModelProjection
{
    public static function attachConditions(array $gameRules, array $gameRuleConditions): array
    {
        $conditionsByRule = [];
        foreach ($gameRuleConditions as $condition) {
            $conditionsByRule[(int) ($condition['game_rule_id'] ?? 0)][] = $condition;
        }
        foreach ($gameRules as &$gameRule) {
            $gameRule['conditions'] = $conditionsByRule[(int) ($gameRule['id'] ?? 0)] ?? [];
            $gameRule['condition_count'] = count(
                array_filter(
                    $gameRule['conditions'],
                    static fn($condition) => !empty($condition['active']),
                ),
            );
        }
        unset($gameRule);
        return $gameRules;
    }
}
