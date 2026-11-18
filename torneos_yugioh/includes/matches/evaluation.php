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
    $stmt=$pdo->prepare('SELECT best_of FROM sx_ygo_matches WHERE id=? LIMIT 1');
    $stmt->execute([$matchId]);
    $bestOf=max(1,min(5,(int)($stmt->fetchColumn() ?: 3)));
    $ruleId=ygo_rule_id_for_match($pdo,$matchId);
    $conditions=[];
    if($ruleId>0){
        $stmt=$pdo->prepare("SELECT * FROM sx_ygo_game_rule_conditions WHERE game_rule_id=? AND active=1 AND operator='sequence_equals' ORDER BY sort_order,id");
        $stmt->execute([$ruleId]);$conditions=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return ygo_evaluate_sequence($values,$bestOf,$conditions);
}

function ygo_competition_for_match(PDO $pdo, int $matchId): array
{
    $stmt = $pdo->prepare('
        SELECT c.*
        FROM sx_ygo_matches m
        JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
        WHERE m.id = ?
        LIMIT 1
    ');
    $stmt->execute([$matchId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function ygo_apply_event_model_to_result(PDO $pdo, int $matchId, array $evaluation, array $currentValues = []): array
{
    $competition = ygo_competition_for_match($pdo, $matchId);
    if (!$competition) {
        return $evaluation + ['model_effects' => []];
    }
    $effects = [];
    $drawPolicy = strtoupper((string) (($competition['draw_policy'] ?? '') ?: 'USE_TIEBREAKER'));
    $matchStmt = $pdo->prepare('SELECT phase FROM sx_ygo_matches WHERE id = ? LIMIT 1');
    $matchStmt->execute([$matchId]);
    $matchPhase = strtolower((string) ($matchStmt->fetchColumn() ?: ''));
    $competitionType = strtolower((string) (($competition['type'] ?? '') ?: ''));
    $executionModel = strtolower((string) (($competition['execution_model'] ?? '') ?: 'manual'));
    $workflow=ygo_workflow_load($pdo,$competition);
    $workflowTable=$workflow!==null && in_array($matchPhase,['swiss','groups','league'],true);
    $playedValues = [];
    foreach (array_slice($currentValues, 0, 5) as $value) {
        $value = (string) $value;
        if ($value === '') {
            break;
        }
        $playedValues[] = $value;
    }
    $playedCount = count($playedValues);
    $isGroupOrLeaguePhase = in_array($matchPhase, ['group', 'groups', 'league', 'round_robin', 'swiss', 'qualifier'], true)
        || in_array($executionModel, ['groups', 'round_robin', 'double_round_robin', 'league_points', 'swiss'], true)
        || in_array($competitionType, ['league', 'qualifier'], true);
    $isEliminationPhase = in_array($competitionType, ['cup', 'final'], true)
        || in_array($matchPhase, ['cup', 'final', 'top_cut', 'playoff', 'world', 'knockout', 'elimination'], true)
        || in_array($executionModel, ['single_elimination', 'double_elimination', 'top_cut', 'bracket', 'series', 'playoffs'], true);
    if (!$workflowTable && (in_array($competitionType, ['cup', 'final', 'world'], true)
        || in_array($matchPhase, ['cup', 'final', 'top_cut', 'playoff', 'world'], true)
        || in_array($executionModel, ['single_elimination', 'double_elimination', 'top_cut', 'bracket', 'series'], true))) {
        $drawPolicy = 'NOT_ALLOWED';
    }
    if($workflowTable){$isGroupOrLeaguePhase=true;$isEliminationPhase=false;}
    elseif($workflow!==null){$isGroupOrLeaguePhase=false;$isEliminationPhase=true;$drawPolicy='NOT_ALLOWED';}
    if ($isGroupOrLeaguePhase && !$isEliminationPhase && $drawPolicy === 'NOT_ALLOWED') {
        $effects[] = 'draw_policy:PHASE_ALLOWS_TABLE_DRAW';
    }
    if (($evaluation['status'] ?? '') === 'FINALIZADA'
        && in_array(($evaluation['winner'] ?? null), ['A', 'B'], true)
        && $playedCount === 4
        && in_array($drawPolicy, ['EXTRA_DUEL', 'EXTRA_MATCH', 'SUDDEN_DEATH', 'USE_TIEBREAKER'], true)
        && !$isGroupOrLeaguePhase) {
        $evaluation['status'] = 'REQUIERE D5';
        $evaluation['winner'] = null;
        $evaluation['validation'] = 'La modalidad requiere D5/desempate antes de cerrar este match.';
        $effects[] = 'd5_required:elimination_or_tiebreak';
    }
    if (($evaluation['winner'] ?? null) === 'E' && in_array($drawPolicy, ['NOT_ALLOWED', 'EXTRA_DUEL', 'EXTRA_MATCH', 'SUDDEN_DEATH', 'USE_TIEBREAKER'], true)) {
        $labels = [
            'NOT_ALLOWED' => 'Empate no permitido por la politica del evento',
            'EXTRA_DUEL' => 'Empate requiere duelo extra',
            'EXTRA_MATCH' => 'Empate requiere match extra',
            'SUDDEN_DEATH' => 'Empate requiere muerte subita',
            'USE_TIEBREAKER' => 'Empate requiere desempate',
        ];
        $evaluation['status'] = $drawPolicy === 'NOT_ALLOWED' ? 'INVALID' : 'REQUIERE DESEMPATE';
        $evaluation['winner'] = null;
        $evaluation['validation'] = $labels[$drawPolicy] ?? 'Empate requiere resolucion';
        $effects[] = 'draw_policy:' . $drawPolicy;
    }
    if (($evaluation['winner'] ?? null) === 'E' && $drawPolicy === 'ALLOWED' && ($competitionType === 'final' || $matchPhase === 'final')) {
        $evaluation['status'] = 'INVALID';
        $evaluation['winner'] = null;
        $evaluation['validation'] = 'Una final no puede cerrarse empatada.';
        $effects[] = 'final_draw_blocked';
    }
    $rankingImpact = strtoupper((string) (($competition['ranking_impact'] ?? '') ?: 'FULL'));
    $level = strtoupper((string) (($competition['level'] ?? '') ?: 'COMPETITIVE'));
    if ($rankingImpact === 'NONE' || in_array($level, ['CASUAL', 'BEGINNER', 'ACADEMY', 'PROMOTIONAL', 'EXHIBITION'], true)) {
        $effects[] = 'ranking_impact:NONE';
    }
    if (strtoupper((string) (($competition['deck_policy'] ?? '') ?: 'OPTIONAL_REGISTRATION')) === 'REQUIRED_REGISTRATION') {
        $effects[] = 'deck_policy:REQUIRED_REGISTRATION';
    }
    $evaluation['model_effects'] = $effects;
    return $evaluation;
}

function ygo_rule_id_for_match(PDO $pdo, int $matchId): int
{
    $stmt = $pdo->prepare("
        SELECT m.phase, m.game_rule_id AS match_rule_id, c.game_rule_id AS competition_rule_id
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$match) {
        return 0;
    }

    foreach (['match_rule_id', 'competition_rule_id'] as $column) {
        $ruleId = (int) ($match[$column] ?? 0);
        if ($ruleId > 0) {
            $ruleStmt = $pdo->prepare('SELECT id FROM sx_ygo_game_rules WHERE id = ? AND active = 1 LIMIT 1');
            $ruleStmt->execute([$ruleId]);
            $validRuleId = (int) ($ruleStmt->fetchColumn() ?: 0);
            if ($validRuleId > 0) {
                return $validRuleId;
            }
        }
    }

    $phase = (string) ($match['phase'] ?? '');
    $seasonId = ygo_current_season_id($pdo);
    $scopes = $phase === 'friendly'
        ? ['all_matches', 'friendly', 'match', 'general']
        : ['all_matches', 'match', 'general'];
    $placeholders = implode(',', array_fill(0, count($scopes), '?'));
    $fallbackStmt = $pdo->prepare("
        SELECT id
        FROM sx_ygo_game_rules
        WHERE season_id = ?
          AND active = 1
          AND scope IN ($placeholders)
        ORDER BY FIELD(scope, $placeholders), priority_order, id
        LIMIT 1
    ");
    $fallbackStmt->execute([$seasonId, ...$scopes, ...$scopes]);
    return (int) ($fallbackStmt->fetchColumn() ?: 0);
}

function ygo_default_game_rule_id(PDO $pdo, int $seasonId): ?int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM sx_ygo_game_rules
        WHERE season_id = ?
          AND active = 1
          AND scope IN ('event', 'all_matches', 'general')
        ORDER BY FIELD(scope, 'event', 'all_matches', 'general'), priority_order, id
        LIMIT 1
    ");
    $stmt->execute([$seasonId]);
    $ruleId = (int) ($stmt->fetchColumn() ?: 0);
    return $ruleId > 0 ? $ruleId : null;
}

function ygo_event_game_rule_id(PDO $pdo, int $seasonId, string $competitionType, string $executionModel): ?int
{
    return ygo_default_game_rule_id($pdo, $seasonId);
}

function ygo_valid_game_rule_id(PDO $pdo, int $seasonId, int $postedRuleId, ?int $fallbackRuleId = null, string $competitionType = 'tournament', string $executionModel = 'manual'): ?int
{
    if ($postedRuleId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM sx_ygo_game_rules WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$postedRuleId, $seasonId]);
        $ruleId = (int) ($stmt->fetchColumn() ?: 0);
        if ($ruleId > 0) {
            return $ruleId;
        }
    }
    return $fallbackRuleId ?: ygo_event_game_rule_id($pdo, $seasonId, $competitionType, $executionModel);
}

function ygo_valid_match_game_rule_id(PDO $pdo, int $seasonId, int $postedRuleId, string $phase): ?int
{
    if ($postedRuleId <= 0) {
        return null;
    }
    $allowedScopes = $phase === 'friendly'
        ? ['all_matches', 'friendly', 'match', 'general']
        : ['all_matches', 'match', 'general'];
    $placeholders = implode(',', array_fill(0, count($allowedScopes), '?'));
    $stmt = $pdo->prepare("
        SELECT id
        FROM sx_ygo_game_rules
        WHERE id = ?
          AND season_id = ?
          AND active = 1
          AND scope IN ($placeholders)
        LIMIT 1
    ");
    $stmt->execute([$postedRuleId, $seasonId, ...$allowedScopes]);
    $ruleId = (int) ($stmt->fetchColumn() ?: 0);
    return $ruleId > 0 ? $ruleId : null;
}
