<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Matches\EvaluateMatchResult;
use StationX\Yugioh\Application\Matches\ResolveMatchRule;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoMatchEventReader;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoMatchRuleReader;

final class MatchEvaluationModule
{
    public static function rules(PDO $pdo): ResolveMatchRule
    {
        return new ResolveMatchRule(new PdoMatchRuleReader($pdo));
    }

    public static function evaluation(PDO $pdo): EvaluateMatchResult
    {
        $rules = new PdoMatchRuleReader($pdo);
        return new EvaluateMatchResult(
            $rules,
            new PdoMatchEventReader($pdo),
            new ResolveMatchRule($rules),
        );
    }
}
