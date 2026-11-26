<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use StationX\Yugioh\Domain\Matches\GameRuleInput;

final readonly class ManageGameRules
{
    public function __construct(private GameRuleWriteStore $store) {}

    public function ruleset(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $code = trim((string) ($post['ruleset_code'] ?? ''));
        $label = trim((string) ($post['ruleset_label'] ?? ''));
        if ($code === '' && $label !== '') {
            $code = $this->store->slug($label);
        }
        $values = GameRuleInput::ruleset($seasonId, $code, $label, $post);
        if ($code !== '' && $label !== '') {
            $this->store->saveRuleset($values);
            $this->store->audit('ruleset', null, 'save_ruleset', [
                'code' => $code,
                'label' => $label,
            ]);
        }
        return 'reglamento';
    }

    public function rule(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $ruleId = (int) ($post['game_rule_id'] ?? 0);
        $ruleName = trim((string) ($post['game_rule_name'] ?? ''));
        $ruleCode = trim((string) ($post['game_rule_code'] ?? ''));
        if ($ruleCode === '' && $ruleName !== '') {
            $ruleCode = strtoupper(str_replace('-', '_', $this->store->slug($ruleName)));
        }
        $values = GameRuleInput::rule($seasonId, $ruleCode, $ruleName, $post);
        if ($ruleCode !== '' && $ruleName !== '') {
            $ruleId = $this->store->saveRule($ruleId, $seasonId, $ruleCode, $values);
            if ($ruleId > 0) {
                $this->store->replaceConditions($ruleId, GameRuleInput::conditions($post));
            }
            $this->store->audit('game_rule', $ruleId ?: null, 'save_game_rule', [
                'code' => $ruleCode,
                'name' => $ruleName,
            ]);
        }
        return 'reglamento';
    }

    public function archive(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $ruleId = (int) ($post['game_rule_id'] ?? 0);
        if ($ruleId > 0) {
            $this->store->archive($ruleId, $seasonId);
            $this->store->audit('game_rule', $ruleId, 'archive_game_rule', []);
        }
        return 'reglamento';
    }
}
