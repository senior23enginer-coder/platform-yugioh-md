(function (root) {
  'use strict';
  function evaluateSequence(values, bestOf = 3, conditions = []) {
    const invalid = (validation) => ({state: 'INVALID', winner: '', score: '0-0', validation});
    if (values.length > 5) return invalid('No se permite D6.');
    const played = []; let gap = false;
    for (const v of values) {
      if (v === '' || v == null) { gap = true; continue; }
      if (gap) return invalid('No se pueden saltar duelos.');
      if (!['A', 'B', 'E'].includes(v)) return invalid('Valor invalido');
      played.push(v);
    }
    const active = conditions.filter(c => Number(c.active ?? 1) !== 0 && c.operator === 'sequence_equals' && c.target_value)
      .slice().sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0) || Number(a.id || 0) - Number(b.id || 0));
    const span = Math.max(Math.max(1, Math.min(5, Number(bestOf || 3))), ...active.map(c => Math.min(5, c.target_value.split(',').length)));
    if (!played.length) return {state: 'PENDIENTE', winner: '', score: '0-0', validation: 'OK'};
    let a = 0; let b = 0; let result; const prefix = [];
    for (let i = 0; i < played.length; i++) {
      const v = played[i]; prefix.push(v); a += Number(v === 'A'); b += Number(v === 'B');
      const exact = active.find(c => c.target_value === prefix.join(','));
      if (exact) {
        const winner = exact.result_value || '';
        const state = exact.status_value || (winner ? 'FINALIZADA' : 'REQUIERE D5');
        result = {state, winner: ['A', 'B', 'E'].includes(winner) ? winner : '', score: exact.score_value || '0-0', validation: state === 'INVALID' ? (exact.failure_message || 'Secuencia invalida por regla') : 'OK'};
      } else {
        const closed = a >= Math.floor(span / 2) + 1 || b >= Math.floor(span / 2) + 1 || prefix.length >= 5 || (span === 1 && prefix.length === 1);
        result = {state: closed ? 'FINALIZADA' : 'EN CURSO', winner: closed ? (a === b ? 'E' : (a > b ? 'A' : 'B')) : '', score: `${a}-${b}`, validation: 'OK'};
      }
      if (i < played.length - 1 && ['FINALIZADA', 'INVALID'].includes(result.state)) return {...invalid('Duelos despues de cierre.'), score: '-'};
    }
    return result;
  }
  function contractErrors(c,preset=null){
    const errors=[], model=c.execution_model||'manual',code=c.prompt_template||c.code||'',phase=c.phase_structure||'';
    if(!['FINAL_STANDINGS','SOURCE_ADMISSION','MANUAL_ENTRIES','TOP_N','GROUP_TOP_K','WINNER_ONLY'].includes(c.advancement_rule))errors.push('El criterio de avance requiere un contrato explicito; los criterios externos se configuran en Fuentes y clasificados del destino.');
    if(['swiss','round_robin','double_round_robin','league_points','groups','ladder'].includes(model)&&!['FINAL_STANDINGS','SOURCE_ADMISSION'].includes(c.advancement_rule))errors.push('Una tabla sin fase final no ejecuta un corte interno.');
    if(model==='manual'&&c.advancement_rule!=='MANUAL_ENTRIES')errors.push('El sistema manual requiere seleccion de participantes de entrada.');
    if(model==='groups_knockout'&&c.advancement_rule!=='GROUP_TOP_K')errors.push('Grupos con llave requiere cupos por grupo.');
    if(['swiss_top_cut','season_playoffs'].includes(model)&&c.advancement_rule!=='TOP_N')errors.push('El sistema compuesto requiere un corte Top N antes de la llave.');
    if(['single_elimination','bracket','double_elimination','top_cut','playoffs','series','king_of_the_hill','gauntlet','flights'].includes(model)&&c.advancement_rule!=='WINNER_ONLY')errors.push('Este sistema avanza por ganador; las plazas de entrada se configuran aparte.');
    if(preset){let allowed=preset.allowed_execution_models||[preset.execution_model];if(['round_robin','double_round_robin','league_points'].includes(preset.execution_model))allowed=['round_robin','double_round_robin','league_points'];
      if(!allowed.includes(model))errors.push('La plantilla requiere '+preset.execution_model+'. Elige otra plantilla o Personalizado para cambiar su estructura.');
      if(c.type!==preset.type)errors.push('El tipo de evento no corresponde a la plantilla elegida.');
      if(c.match_format&&['team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship','tag_duel'].includes(code)&&c.match_format!==preset.match_format)errors.push('La participacion debe coincidir con el contrato de equipos de la plantilla.');
      if(['best_of_1_event','best_of_3_event'].includes(code)&&Number(c.best_of)!==(code==='best_of_1_event'?1:3))errors.push('El mejor de no coincide con la plantilla.');
    }
    if(code==='clasificatorio_divisiones_3_duelos'&&model!=='swiss')errors.push('El clasificatorio de tres enfrentamientos usa Suizo sin Top Cut interno.');
    const phases={swiss_top_cut:['TOP_CUT','GROUPS_TOP_CUT_FINAL'],groups_knockout:['GROUPS_TOP_CUT_FINAL'],season_playoffs:['SEASON_PLAYOFF_FINAL'],groups:['GROUPS'],series:['FINAL','GRAND_FINAL']};
    if(phases[model]&&!phases[model].includes(phase))errors.push('Las fases no representan el sistema competitivo elegido.');
    if(model==='swiss'&&['TOP_CUT','GROUPS_TOP_CUT_FINAL','SEASON_PLAYOFF_FINAL'].includes(phase))errors.push('Suizo simple no genera una fase final.');
    if(['single_elimination','bracket','double_elimination','top_cut','playoffs'].includes(model)&&!['WINNER_ONLY','TOP_N'].includes(c.advancement_rule))errors.push('Una eliminatoria requiere avance por ganador.');
    if(['top_cut','playoffs'].includes(model)&&c.participant_source!=='PREVIOUS_EVENT')errors.push('Top Cut y Playoffs aislados requieren una fuente de clasificados verificable.');
    if(code==='pegasus_challenge'&&(!['swiss','single_elimination','round_robin','double_round_robin','league_points'].includes(model)||c.match_format!=='1v1'))errors.push('Pegasus requiere modalidad individual y Suizo, eliminacion directa o liga.');
    return [...new Set(errors)];
  }
  const api = {evaluateSequence,contractErrors};
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  root.YgoEventModels = api;
})(typeof window !== 'undefined' ? window : globalThis);
