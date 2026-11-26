<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface RulesReadModelReader
{
    public function matchTypes(): array;

    public function rulesets(): array;

    public function rulesetVersions(): array;

    public function gameRules(): array;

    public function gameRuleConditions(): array;
}
