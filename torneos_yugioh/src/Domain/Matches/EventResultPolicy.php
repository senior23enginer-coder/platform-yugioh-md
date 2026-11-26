<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

final class EventResultPolicy
{
    public static function apply(
        array $competition,
        string $matchPhase,
        ?array $workflow,
        array $evaluation,
        array $currentValues = [],
    ): array {
        if (!$competition) {
            return $evaluation + ['model_effects' => []];
        }
        $effects = [];
        $drawPolicy = strtoupper((string) (($competition['draw_policy'] ?? '') ?: 'USE_TIEBREAKER'));
        $matchPhase = strtolower($matchPhase);
        $competitionType = strtolower((string) (($competition['type'] ?? '') ?: ''));
        $executionModel = strtolower((string) (($competition['execution_model'] ?? '') ?: 'manual'));
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
}
