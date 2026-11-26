<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\ReadModel\RulesReadModelReader;

final readonly class PdoRulesReadModelReader implements RulesReadModelReader
{
    public function __construct(private PDO $pdo) {}

    public function matchTypes(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_match_types WHERE active = 1 ORDER BY sort_order, id')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rulesets(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_rulesets WHERE active = 1 ORDER BY format_family, label')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rulesetVersions(): array
    {
        return $this->pdo
            ->query(
                'SELECT * FROM sx_ygo_ruleset_versions WHERE active = 1 ORDER BY sort_order, COALESCE(effective_on, "9999-12-31") DESC, version_label DESC',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gameRules(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_game_rules ORDER BY active DESC, priority_order, name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gameRuleConditions(): array
    {
        return $this->pdo
            ->query(
                'SELECT * FROM sx_ygo_game_rule_conditions WHERE active = 1 ORDER BY game_rule_id, sort_order, id',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
