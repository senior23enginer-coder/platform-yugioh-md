<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\GameRuleWriteStore;

final readonly class PdoGameRuleWriteStore implements GameRuleWriteStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function slug(string $name): string
    {
        return \ygo_slugify($name);
    }

    public function saveRuleset(array $values): void
    {
        $this->pdo
            ->prepare(
                "
            INSERT INTO sx_ygo_rulesets
                (season_id, code, label, format_family, banlist_name, banlist_version, banlist_effective_on, match_format, best_of, participants_per_side, schedule_strategy, deck_pool, victory_condition, defeat_condition, draw_condition, notes, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE label=VALUES(label), format_family=VALUES(format_family), banlist_name=VALUES(banlist_name), banlist_version=VALUES(banlist_version), banlist_effective_on=VALUES(banlist_effective_on), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), schedule_strategy=VALUES(schedule_strategy), deck_pool=VALUES(deck_pool), victory_condition=VALUES(victory_condition), defeat_condition=VALUES(defeat_condition), draw_condition=VALUES(draw_condition), notes=VALUES(notes), active=1
        ",
            )
            ->execute($values);
    }

    public function saveRule(int $ruleId, int $seasonId, string $ruleCode, array $values): int
    {
        if ($ruleId > 0) {
            $this->pdo
                ->prepare(
                    'UPDATE sx_ygo_game_rules SET code=?, name=?, rule_type=?, scope=?, trigger_event=?, effect=?, points_value=?, priority_order=?, active=?, notes=? WHERE id=? AND season_id=?',
                )
                ->execute(array_merge(array_slice($values, 1), [$ruleId, $seasonId]));
        } else {
            $this->pdo
                ->prepare(
                    '
                INSERT INTO sx_ygo_game_rules
                    (season_id, code, name, rule_type, scope, trigger_event, effect, points_value, priority_order, active, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name=VALUES(name), rule_type=VALUES(rule_type), scope=VALUES(scope), trigger_event=VALUES(trigger_event), effect=VALUES(effect), points_value=VALUES(points_value), priority_order=VALUES(priority_order), active=VALUES(active), notes=VALUES(notes)
            ',
                )
                ->execute($values);
            $ruleLookup = $this->pdo->prepare(
                'SELECT id FROM sx_ygo_game_rules WHERE season_id = ? AND code = ? LIMIT 1',
            );
            $ruleLookup->execute([$seasonId, $ruleCode]);
            $ruleId = (int) $ruleLookup->fetchColumn();
        }
        return $ruleId;
    }

    public function replaceConditions(int $ruleId, iterable $rows): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?')
            ->execute([$ruleId]);
        $conditionUpsert = $this->pdo->prepare('
            INSERT INTO sx_ygo_game_rule_conditions
                (game_rule_id, condition_type, condition_label, operator, target_value, result_value, status_value, score_value, failure_message, sort_order, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $conditionUpdate = $this->pdo->prepare('
            UPDATE sx_ygo_game_rule_conditions
            SET condition_type=?, condition_label=?, operator=?, target_value=?, result_value=?, status_value=?, score_value=?, failure_message=?, sort_order=?, active=?
            WHERE id=? AND game_rule_id=?
        ');
        foreach ($rows as $row) {
            if ($row['id'] > 0) {
                $conditionUpdate->execute([...$row['values'], $row['id'], $ruleId]);
            } else {
                $conditionUpsert->execute([$ruleId, ...$row['values']]);
            }
        }
    }

    public function archive(int $ruleId, int $seasonId): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_game_rules SET active=0 WHERE id=? AND season_id=?')
            ->execute([$ruleId, $seasonId]);
        $this->pdo
            ->prepare('UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?')
            ->execute([$ruleId]);
    }

    public function audit(string $entity, ?int $id, string $action, array $payload): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                $entity,
                $id,
                $action,
                json_encode((object) $payload, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
