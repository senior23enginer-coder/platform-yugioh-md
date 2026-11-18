<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native archive_game_rule adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ArchiveGameRuleHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $ruleId = (int) ($post['game_rule_id'] ?? 0);
        if ($ruleId > 0) {
            $pdo->prepare(
                'UPDATE sx_ygo_game_rules SET active=0 WHERE id=? AND season_id=?',
            )->execute([$ruleId, $seasonId]);
            $pdo->prepare(
                'UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?',
            )->execute([$ruleId]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute(['game_rule', $ruleId, 'archive_game_rule', '{}']);
        }
        return 'reglamento';

        return null;
    }
}
