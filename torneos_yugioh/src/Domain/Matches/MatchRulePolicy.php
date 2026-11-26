<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

final class MatchRulePolicy
{
    public static function scopes(string $phase): array
    {
        return $phase === 'friendly'
            ? ['all_matches', 'friendly', 'match', 'general']
            : ['all_matches', 'match', 'general'];
    }

    public static function bestOf(mixed $value): int
    {
        return max(1, min(5, (int) ($value ?: 3)));
    }
}
