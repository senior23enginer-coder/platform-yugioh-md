<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use StationX\Yugioh\Domain\Matches\MatchRulePolicy;

final readonly class ResolveMatchRule
{
    public function __construct(private MatchRuleReader $reader) {}

    public function execute(int $matchId): int
    {
        $match = $this->reader->matchRules($matchId);
        if (!$match) {
            return 0;
        }
        foreach (['match_rule_id', 'competition_rule_id'] as $column) {
            $ruleId = (int) ($match[$column] ?? 0);
            if ($ruleId > 0) {
                $validRuleId = $this->reader->activeRule($ruleId);
                if ($validRuleId > 0) {
                    return $validRuleId;
                }
            }
        }
        $phase = (string) ($match['phase'] ?? '');
        $seasonId = $this->reader->currentSeasonId();
        return $this->reader->fallback($seasonId, MatchRulePolicy::scopes($phase));
    }

    public function validateEventRule(int $seasonId, int $postedRuleId, ?int $fallbackRuleId): ?int
    {
        if ($postedRuleId > 0) {
            $ruleId = $this->reader->postedRule($seasonId, $postedRuleId);
            if ($ruleId > 0) {
                return $ruleId;
            }
        }
        return $fallbackRuleId ?: $this->reader->defaultRule($seasonId);
    }

    public function validateMatchRule(int $seasonId, int $postedRuleId, string $phase): ?int
    {
        if ($postedRuleId <= 0) {
            return null;
        }
        return $this->reader->postedMatchRule(
            $seasonId,
            $postedRuleId,
            MatchRulePolicy::scopes($phase),
        );
    }
}
