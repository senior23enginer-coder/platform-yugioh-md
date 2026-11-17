<?php
declare(strict_types=1);

/** Structural invariants for new recipes; historical fixtures are never migrated here. */
function ygo_event_contract_errors(array $c,?array $preset=null): array {
    $e=[];$model=(string)($c['execution_model']??'manual');$phase=$c['phase_structure']??'';$advance=$c['advancement_rule']??'';
    $code=$c['prompt_template']??$c['code']??'';$base=function_exists('ygo_template_base_code')?ygo_template_base_code($code):$code;
    if($model==='free_for_all'&&(($c['match_format']??'ffa')!=='ffa'||($c['best_of']??1)!=1||$advance!=='WINNER_ONLY'))$e[]='Free-for-All requiere mesa independiente, un duelo y ultimo superviviente ganador.';
    if(!in_array($advance,['FINAL_STANDINGS','SOURCE_ADMISSION','MANUAL_ENTRIES','TOP_N','GROUP_TOP_K','WINNER_ONLY'],true))$e[]='El criterio de avance es ambiguo o pertenece a Fuentes y clasificados del evento destino. Selecciona un contrato explicito para esta receta.';
    if(in_array($model,['swiss','round_robin','double_round_robin','league_points','groups','ladder'],true)&&!in_array($advance,['FINAL_STANDINGS','SOURCE_ADMISSION'],true))$e[]='Un sistema de tabla sin fase final no ejecuta un corte interno. Elige Tabla final o plazas en el evento destino.';
    if($model==='manual'&&$advance!=='MANUAL_ENTRIES')$e[]='El sistema manual requiere seleccion de participantes de entrada; no genera avances automaticos.';
    if($model==='groups_knockout'&&$advance!=='GROUP_TOP_K')$e[]='Grupos con llave requiere cupos por grupo y avance por ganador despues del corte.';
    if(in_array($model,['swiss_top_cut','season_playoffs'],true)&&$advance!=='TOP_N')$e[]='El sistema compuesto requiere un corte Top N antes de su llave.';
    if(in_array($model,['single_elimination','bracket','double_elimination','top_cut','playoffs','series','king_of_the_hill','gauntlet','flights'],true)&&$advance!=='WINNER_ONLY')$e[]='Este sistema avanza por ganador; las plazas de entrada se configuran aparte.';
    if($preset!==null){
        $expected=$preset['execution_model'];$allowed=$preset['allowed_execution_models']??[$expected];
        if(in_array($expected,['round_robin','double_round_robin','league_points'],true))$allowed=['round_robin','double_round_robin','league_points'];
        if(!in_array($model,$allowed,true))$e[]='La plantilla '.$preset['label'].' requiere '.$expected.'. Elige otra plantilla o Personalizado para cambiar su estructura.';
        if(($c['type']??null)!==($preset['type']??null))$e[]='El tipo de evento no corresponde a la plantilla elegida.';
        if(isset($c['match_format']) && in_array($base,['team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship','tag_duel'],true) && $c['match_format']!==$preset['match_format'])$e[]='La participacion debe coincidir con el contrato de equipos de la plantilla.';
        if(in_array($code,['best_of_1_event','best_of_3_event'],true) && (int)($c['best_of']??0)!==($code==='best_of_1_event'?1:3))$e[]='El mejor de no coincide con la plantilla.';
    }
    if($code==='clasificatorio_divisiones_3_duelos' && $model!=='swiss')$e[]='El clasificatorio de tres enfrentamientos usa Suizo sin Top Cut interno. Para una final elige Suizo + Top Cut.';
    $phases=['swiss_top_cut'=>['TOP_CUT','GROUPS_TOP_CUT_FINAL'],'groups_knockout'=>['GROUPS_TOP_CUT_FINAL'],
        'season_playoffs'=>['SEASON_PLAYOFF_FINAL'],'groups'=>['GROUPS'],'series'=>['FINAL','GRAND_FINAL']];
    if(isset($phases[$model]) && !in_array($phase,$phases[$model],true))$e[]='Las fases no representan el sistema competitivo elegido.';
    if($model==='swiss' && in_array($phase,['TOP_CUT','GROUPS_TOP_CUT_FINAL','SEASON_PLAYOFF_FINAL'],true))$e[]='Suizo simple no genera una fase final. Selecciona una receta compuesta.';
    if(in_array($model,['single_elimination','bracket','double_elimination','top_cut','playoffs'],true) && !in_array($advance,['WINNER_ONLY','TOP_N'],true))$e[]='Una eliminatoria requiere avance por ganador, no seleccion manual ni umbral de puntos.';
    if(in_array($model,['top_cut','playoffs'],true) && ($c['participant_source']??'')!=='PREVIOUS_EVENT')$e[]='Top Cut y Playoffs aislados requieren una fuente de clasificados verificable.';
    if($base==='pegasus_challenge'){
        if(!in_array($model,['swiss','single_elimination','round_robin','double_round_robin','league_points'],true)||($c['match_format']??'1v1')!=='1v1')$e[]='Pegasus requiere modalidad individual y Suizo, eliminacion directa o liga.';
        if(!in_array($c['event_ruleset']??'tcg_advanced',['tcg_advanced','tcg_traditional'],true))$e[]='Pegasus usa TCG Avanzado o Tradicional.';
    }
    return array_values(array_unique($e));
}
