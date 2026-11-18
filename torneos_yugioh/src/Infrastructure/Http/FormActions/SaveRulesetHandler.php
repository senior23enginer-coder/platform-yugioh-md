<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_ruleset adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveRulesetHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $code = trim((string) ($post['ruleset_code'] ?? ''));
        $label = trim((string) ($post['ruleset_label'] ?? ''));
        if ($code === '' && $label !== '') {
            $code = ygo_slugify($label);
        }
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
        if ($values[1] !== '' && $values[2] !== '') {
            $pdo->prepare(
                "
                INSERT INTO sx_ygo_rulesets
                    (season_id, code, label, format_family, banlist_name, banlist_version, banlist_effective_on, match_format, best_of, participants_per_side, schedule_strategy, deck_pool, victory_condition, defeat_condition, draw_condition, notes, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE label=VALUES(label), format_family=VALUES(format_family), banlist_name=VALUES(banlist_name), banlist_version=VALUES(banlist_version), banlist_effective_on=VALUES(banlist_effective_on), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), schedule_strategy=VALUES(schedule_strategy), deck_pool=VALUES(deck_pool), victory_condition=VALUES(victory_condition), defeat_condition=VALUES(defeat_condition), draw_condition=VALUES(draw_condition), notes=VALUES(notes), active=1
            ",
            )->execute($values);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'ruleset',
                null,
                'save_ruleset',
                json_encode(['code' => $code, 'label' => $label], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return 'reglamento';

        return null;
    }
}
