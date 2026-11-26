<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface MatchRuleReader
{
    public function matchRules(int $matchId): array;

    public function activeRule(int $ruleId): int;

    public function currentSeasonId(): int;

    public function fallback(int $seasonId, array $scopes): int;

    public function defaultRule(int $seasonId): ?int;

    public function postedRule(int $seasonId, int $ruleId): int;

    public function postedMatchRule(int $seasonId, int $ruleId, array $scopes): ?int;

    public function bestOf(int $matchId): mixed;

    public function conditions(int $ruleId): array;
}
