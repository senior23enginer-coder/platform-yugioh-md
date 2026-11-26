<?php
declare(strict_types=1);

/**
 * match-evaluation: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function evaluate_duel_server(array $values, int $bestOf = 3): array
{
    return ygo_evaluate_sequence($values, $bestOf);
}

function ygo_evaluate_duel_with_rule(PDO $pdo, int $matchId, array $values): array
{
    return \StationX\Yugioh\Infrastructure\Composition\MatchEvaluationModule::evaluation($pdo)->evaluate($matchId, $values);
}

function ygo_competition_for_match(PDO $pdo, int $matchId): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Matches\PdoMatchEventReader($pdo))->competition($matchId);
}

function ygo_apply_event_model_to_result(PDO $pdo, int $matchId, array $evaluation, array $currentValues = []): array
{
    return \StationX\Yugioh\Infrastructure\Composition\MatchEvaluationModule::evaluation($pdo)->applyEvent($matchId, $evaluation, $currentValues);
}

function ygo_rule_id_for_match(PDO $pdo, int $matchId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\MatchEvaluationModule::rules($pdo)->execute($matchId);
}

function ygo_default_game_rule_id(PDO $pdo, int $seasonId): ?int
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Matches\PdoMatchRuleReader($pdo))->defaultRule($seasonId);
}

function ygo_event_game_rule_id(PDO $pdo, int $seasonId, string $competitionType, string $executionModel): ?int
{
    return ygo_default_game_rule_id($pdo, $seasonId);
}

function ygo_valid_game_rule_id(PDO $pdo, int $seasonId, int $postedRuleId, ?int $fallbackRuleId = null, string $competitionType = 'tournament', string $executionModel = 'manual'): ?int
{
    return \StationX\Yugioh\Infrastructure\Composition\MatchEvaluationModule::rules($pdo)->validateEventRule($seasonId, $postedRuleId, $fallbackRuleId);
}

function ygo_valid_match_game_rule_id(PDO $pdo, int $seasonId, int $postedRuleId, string $phase): ?int
{
    return \StationX\Yugioh\Infrastructure\Composition\MatchEvaluationModule::rules($pdo)->validateMatchRule($seasonId, $postedRuleId, $phase);
}
