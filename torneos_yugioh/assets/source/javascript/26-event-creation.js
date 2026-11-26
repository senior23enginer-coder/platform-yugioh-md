/**
 * event-creation: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function nextYuCompetitionCode() {
    const max = (data.competitions || []).reduce((current, competition) => {
      const match = String(competition.code || '').match(/^YU-(\d+)$/i);
      return match ? Math.max(current, Number(match[1] || 0)) : current;
    }, 0);
    return `YU-${String(max + 1).padStart(2, '0')}`;
  }
  function updateCompetitionCodePreview() {
    if (!competitionCodePreview) return;
    const existingCode = document.querySelector('[data-gestion-competition-code]')?.value || '';
    if (existingCode) {
      competitionCodePreview.value = existingCode;
      return;
    }
    competitionCodePreview.value = nextYuCompetitionCode();
  }
  function syncParticipantsFromMatchFormat() {
    const select = document.querySelector('[data-gestion-competition-match-format]');
    const matchFormat = select?.value || '1v1';
    const participants = document.querySelector('[data-gestion-competition-participants]');
    const configured = select?.selectedOptions?.[0]?.dataset?.participants || '';
    if (participants) participants.value = configured || (matchFormat.startsWith('3') ? '3' : (matchFormat.startsWith('2') ? '2' : '1'));
    setValue('[data-gestion-competition-participation]', matchFormat === 'ffa'?'FREE_FOR_ALL':matchFormat === '3v3' ? 'TEAM_3V3' : (matchFormat === '2v2' ? 'TAG_2V2' : 'INDIVIDUAL_1V1'));
  }
  function selectCompetitionRulesetVersion(value) {
    const select = document.querySelector('[data-gestion-competition-ruleset-version]');
    if (!select) return;
    const wanted = String(value || '').trim();
    if (!wanted) {
      select.selectedIndex = 0;
      return;
    }
    const normalized = wanted.toLowerCase();
    const option = Array.from(select.options).find((item) => {
      const text = `${item.value} ${item.textContent || ''}`.toLowerCase();
      return item.value === wanted || text.includes(normalized) || normalized.includes(item.value.toLowerCase());
    });
    if (option) select.value = option.value;
  }
  function inferRulesetCodeFromVersionOption(option) {
    const raw = `${option?.dataset?.ruleset || ''} ${option?.textContent || ''} ${option?.value || ''}`.toLowerCase();
    if (raw.includes('master duel')) return 'master_duel';
    if (raw.includes('speed duel')) return 'speed_duel';
    if (raw.includes('edison')) return 'edison';
    if (raw.includes('goat')) return 'goat';
    if (raw.includes('genesys') || raw.includes('genesis')) return 'genesis';
    if (raw.includes('ocg') && !raw.includes('tcg')) return 'ocg';
    if (raw.includes('tcg')) return 'tcg_advanced';
    return '';
  }
  function syncRulesetFromVersion(versionSelect, rulesetSelect) {
    if (!versionSelect || !rulesetSelect) return;
    const inferred = inferRulesetCodeFromVersionOption(versionSelect.selectedOptions?.[0]);
    if (inferred && Array.from(rulesetSelect.options).some((option) => option.value === inferred)) {
      rulesetSelect.value = inferred;
    }
  }
  function syncRulesetFromDivision(divisionSelect, rulesetSelect) {
    if (!divisionSelect || !rulesetSelect) return;
    const option = divisionSelect.selectedOptions?.[0];
    const direct = String(option?.dataset?.ruleset || '').trim();
    const raw = `${option?.textContent || ''} ${divisionSelect.value || ''}`.toLowerCase();
    const inferred = direct || (raw.includes('rush') ? 'rush_duel'
      : raw.includes('duel link') || raw.includes('duel_links') ? 'duel_links'
        : raw.includes('speed') ? 'speed_duel'
          : raw.includes('edison') ? 'edison'
            : raw.includes('goat') ? 'goat'
              : raw.includes('genesis') ? 'genesis'
                : raw.includes('ocg') ? 'ocg'
                  : raw.includes('tcg') || raw.includes('avanzado') ? 'tcg_advanced'
                    : '');
    if (inferred && Array.from(rulesetSelect.options).some((option) => option.value === inferred)) {
      rulesetSelect.value = inferred;
    }
  }
  function applyRulesetToCompetition(force = false) {
    const ruleset = rulesetByCode.get(String(adminCompetitionRuleset?.value || '')) || {};
    const fill = (selector, value) => {
      const node = document.querySelector(selector);
      if (node && (force || !String(node.value || '').trim())) node.value = value == null ? '' : value;
    };
    fill('[data-gestion-competition-best-of]', eventTemplateByCode(adminCompetitionTemplate?.value || '')?.best_of || ruleset.best_of || '3');
    fill('[data-gestion-competition-schedule]', ruleset.schedule_strategy || 'manual');
    fill('[data-gestion-competition-deck-pool]', ['division', 'all_active'].includes(String(ruleset.deck_pool || '')) ? ruleset.deck_pool : 'all_active');
    const setText = (selector, value) => {
      const node = document.querySelector(selector);
      if (node && 'value' in node) node.value = value || 'Por definir';
      else if (node) node.textContent = value || 'Por definir';
    };
    setText('[data-ruleset-version]', ruleset.banlist_version || 'Por definir');
    if (force) selectCompetitionRulesetVersion(ruleset.banlist_version || ruleset.banlist_name || '');
    updateCompetitionCodePreview();
  }
  const competitionTypePresets = {
    qualifier: { format: 'suizo clasificatorio', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'auto_swiss', execution: 'swiss_top_cut', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'QUALIFIES_TO_EVENT', source: 'MANUAL', access: 'QUALIFIER', phases: 'TOP_CUT', advancement: 'TOP_N', drawPolicy: 'USE_TIEBREAKER', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Dos victorias consecutivas o ventaja al cierre D4/D5', defeat: 'Menor marcador al cierre del match', draw: 'D5 empatado o dos empates consecutivos' },
    tournament: { format: 'torneo competitivo', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'manual', execution: 'manual', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'STANDALONE', source: 'MANUAL', access: 'OPEN', phases: 'NONE', advancement: 'MANUAL_SELECTION', drawPolicy: 'USE_TIEBREAKER', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Victoria por match segun reglamento', defeat: 'Derrota por match segun reglamento', draw: 'Empate segun reglamento' },
    league: { format: 'liga todos contra todos', ruleset: 'tcg_advanced', deckPool: 'division', schedule: 'auto_round_robin', execution: 'round_robin', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'SEASON', source: 'DIVISION', access: 'OPEN', phases: 'SEASON_PLAYOFF_FINAL', advancement: 'RANKING_THRESHOLD', drawPolicy: 'ALLOWED', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Victoria por match segun D1-D5', defeat: 'Derrota por match segun D1-D5', draw: 'Empate valido solo cuando el reglamento lo permita' },
    cup: { format: 'eliminacion directa', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'auto_knockout', execution: 'single_elimination', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'STANDALONE', source: 'MANUAL', access: 'OPEN', phases: 'FINAL', advancement: 'WINNER_ONLY', drawPolicy: 'NOT_ALLOWED', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Ganar el match para avanzar de ronda', defeat: 'Perder el match elimina o envia a llave secundaria', draw: 'Empate genera desempate completo' },
    world: { format: 'grupos y eliminacion directa', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'manual', execution: 'groups_knockout', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'WORLD', flow: 'CHAMPIONSHIP_PATH', source: 'PREVIOUS_EVENT', access: 'INVITATIONAL', phases: 'GROUPS_TOP_CUT_FINAL', advancement: 'TOP_N', drawPolicy: 'NOT_ALLOWED', rankingImpact: 'BONUS', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Victoria suma en grupos o avanza en llave', defeat: 'Derrota suma en grupos o elimina en llave', draw: 'Empate permitido en grupos; desempate en eliminatoria' },
    final: { format: 'final unica', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'manual', execution: 'series', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'CHAMPIONSHIP', flow: 'RECEIVES_FROM_EVENT', source: 'PREVIOUS_EVENT', access: 'INVITATIONAL', phases: 'FINAL', advancement: 'WINNER_ONLY', drawPolicy: 'NOT_ALLOWED', rankingImpact: 'BONUS', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Ganar la serie final', defeat: 'Perder la serie final', draw: 'Empate fuerza desempate completo' },
    friendly: { format: 'amistoso rotativo', ruleset: 'custom', deckPool: 'all_active', schedule: 'friendly_rotation', execution: 'manual', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'CASUAL', flow: 'STANDALONE', source: 'MANUAL', access: 'OPEN', phases: 'NONE', advancement: 'MANUAL_SELECTION', drawPolicy: 'ALLOWED', rankingImpact: 'NONE', deckPolicy: 'NO_REGISTRATION', victory: 'Resultado amistoso registrado en historial', defeat: 'Resultado amistoso registrado en historial', draw: 'Empate amistoso permitido' },
    special: { format: 'evento especial editable', ruleset: 'custom', deckPool: 'all_active', schedule: 'manual', execution: 'custom', participation: 'INDIVIDUAL_1V1', construction: 'CUSTOM', environment: 'PRESENCIAL', level: 'SPECIAL', flow: 'CUSTOM', source: 'CUSTOM', access: 'CUSTOM', phases: 'CUSTOM', advancement: 'CUSTOM', drawPolicy: 'CUSTOM', rankingImpact: 'CUSTOM', deckPolicy: 'CUSTOM', victory: 'Condicion definida por el evento', defeat: 'Condicion definida por el evento', draw: 'Condicion definida por el evento' },
  };
  const competitionTypeDescriptions = {
    qualifier: 'Clasificatorio: ordena participantes, habilita ranking de entrada y suele alimentar ligas, copas o fases posteriores.',
    tournament: 'Torneo competitivo: evento flexible para rondas manuales, suizo o estructuras configurables sin ascensos ni descensos propios de liga.',
    league: 'Liga: usa tablas, calendario por jornadas, pool por formato/division y puede manejar ascensos, descensos o bloqueo de movimientos.',
    cup: 'Copa: orientada a eliminacion, grupos con llave final o rondas programadas donde el ganador avanza.',
    world: 'Mundial: evento global o cruzado, normalmente con grupos, eliminacion y reglas mas amplias que una copa normal.',
    final: 'Final: fase decisiva o cierre de evento. Pensada para series, top cut, playoffs o combate unico por titulo.',
    friendly: 'Amistoso: combate/evento de practica que registra historial sin afectar necesariamente tablas principales.',
    special: 'Especial: evento editable para reglas propias, exhibiciones, formatos alternativos o casos que no encajan en los tipos deportivos base.'
  };
  const executionByCompetitionType = data.executionModelsByType || {
    qualifier: ['manual', 'swiss', 'swiss_top_cut', 'top_cut', 'flights', 'ladder', 'custom'],
    tournament: ['manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'groups', 'groups_knockout', 'top_cut', 'bracket', 'flights', 'custom'],
    league: ['manual', 'round_robin', 'double_round_robin', 'league_points', 'season_playoffs', 'playoffs', 'custom'],
    cup: ['manual', 'single_elimination', 'double_elimination', 'groups_knockout', 'top_cut', 'bracket', 'playoffs', 'custom'],
    world: ['manual', 'groups', 'groups_knockout', 'swiss_top_cut', 'top_cut', 'bracket', 'single_elimination', 'custom'],
    final: ['manual', 'series', 'single_elimination', 'top_cut', 'playoffs', 'custom'],
    friendly: ['manual', 'swiss', 'round_robin', 'single_elimination', 'king_of_the_hill', 'gauntlet', 'custom'],
    special: ['manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'round_robin', 'double_round_robin', 'groups', 'groups_knockout', 'league_points', 'playoffs', 'top_cut', 'bracket', 'flights', 'ladder', 'king_of_the_hill', 'gauntlet', 'series', 'season_playoffs', 'custom']
  };
  const executionDescriptions = {
    manual: 'Manual: el organizador controla emparejamientos, calendario y avance sin generacion automatica estricta.',
    swiss: 'Suizo: rondas por rendimiento, sin eliminacion inmediata. Sirve para clasificar por puntos o desempates.',
    swiss_top_cut: 'Suizo + corte: primero se juegan rondas suizas y luego los mejores pasan a eliminacion directa.',
    single_elimination: 'Eliminacion directa: perder elimina o deja fuera de la llave principal.',
    double_elimination: 'Doble eliminacion: el participante necesita dos derrotas para quedar eliminado.',
    round_robin: 'Round robin: todos contra todos, ideal para liga, grupos o tabla por puntos.',
    double_round_robin: 'Ida y vuelta: todos se enfrentan dos veces, alternando localia/lado o segunda vuelta.',
    groups: 'Grupos: separa participantes en grupos antes de ordenar o clasificar.',
    groups_knockout: 'Grupos + eliminacion: fase de grupos y luego llave final con clasificados.',
    season_playoffs: 'Temporada + playoffs: tabla regular y fase final para definir campeon.',
    ladder: 'Ladder interno: se programan dos rondas por inscrito. Se alternan retos entre posiciones vecinas; si gana el de abajo, intercambian posicion. No se admiten empates; la primera posicion al cierre gana.',
    king_of_the_hill: 'King of the Hill: el primer inscrito defiende. Los demas retan una vez en orden de inscripcion; el ganador permanece y el ultimo defensor gana el evento.',
    gauntlet: 'Gauntlet: el primer inscrito debe vencer a todos los demas, uno a uno y en orden. La primera derrota termina el intento sin campeon global; si supera todos los retos, gana el evento.',
    flights: 'Flights: grupos de hasta cuatro por orden inicial, eliminacion directa dentro de cada grupo y llave final entre sus ganadores. Los descansos no crean combates ni XP.',
    series: 'Serie: enfrentamiento unico, best of X o conjunto corto definido por el organizador.'
  };
  function eventCreateGuideText(selector, value) {
    const node = document.querySelector(selector);
    if (node) node.textContent = value || '-';
  }
  function optionValueOrDefault(selector, value, fallback) {
    const node = document.querySelector(selector);
    if (!node) return value || fallback || '';
    const raw = String(value || '').trim();
    if (raw && Array.from(node.options || []).some((option) => option.value === raw)) return raw;
    const normalized = normalize(raw).replace(/\s+/g, '_').toUpperCase();
    if (normalized && Array.from(node.options || []).some((option) => option.value === normalized)) return normalized;
    return fallback;
  }
  function eventModelWarnings(config) {
    const cap = (data.executionCapabilities || {})[config.execution_model || 'manual'];
    const warnings = cap?.reason ? [cap.reason] : [];
    const template = eventTemplateByCode(config.prompt_template || '');
    if (template) {
      const originalEngineReason = (data.executionCapabilities || {})[template.execution_model]?.reason;
      warnings.push(...(template.activation_restrictions || []).filter(message => message !== originalEngineReason && !(config.teamConfig && message.startsWith('Configura Equipos y alineaciones'))));
    }
    warnings.push(...(window.YgoEventModels?.contractErrors(config,template)||[]));
    return [...new Set(warnings)];
  }
  function filterCompetitionVersionOptions() {
    const select = document.querySelector('[data-gestion-competition-ruleset-version]');
    if (!select) return;
    const family = adminCompetitionRuleset?.value || 'custom';
    const words = {tcg_advanced: 'tcg', ocg: 'ocg', goat: 'goat', edison: 'edison', genesis: 'genesys', speed_duel: 'speed duel', master_duel: 'master duel', rush_duel: 'rush', duel_links: 'duel links'};
    for (const option of select.options) {
      const compatible = !option.value || family === 'custom' || String(option.dataset.system || '').toLowerCase().includes(words[family] || family);
      option.hidden = !compatible; option.disabled = !compatible;
    }
    if (select.selectedOptions[0]?.disabled) select.value = '';
  }
  function workflowPolicyDescription(model, state = null) {
    const base = 'Reglamento interno Station X 2026 v1. Orden inicial: orden de inscripcion, conservado al programar. ';
    const descriptions = {
      swiss: 'Rondas: ceil(log2 N), maximo N-1. Victoria 3, empate 1, derrota 0. BYE: 3 puntos sin match ni XP; al ultimo elegible sin BYE anterior. Sin repetir rival; se bloquea si no hay ronda completa. Desempates: puntos, Buchholz, diferencia de juegos y orden inicial.',
      swiss_top_cut: 'Suizo progresivo; despues, Top N de eliminacion directa. N es la mayor potencia de 2 hasta la mitad de participantes y maximo 8 (minimo 2). Sin sustituciones ni duplicados. Desempates: puntos, Buchholz, diferencia de juegos y orden inicial.',
      groups: 'Grupos de hasta 4 por siembra en serpiente. Cada grupo juega todos contra todos a una vuelta. Tabla y ganador independientes por grupo. Puntos 3/1/0; desempates por Buchholz, diferencia de juegos y orden inicial.',
      groups_knockout: 'Grupos de hasta 4 por siembra en serpiente; una vuelta. Clasifican los dos primeros de cada grupo. Cruces iniciales: primero contra segundo de otro grupo; siguientes rondas por eliminacion directa. Los BYE de llave no cuentan como combates ni XP.',
      series: 'Exactamente 2 decks. Gana la serie el primero en ganar 2 matches (maximo 3). El mejor de de cada match se configura por separado. Un empate se resuelve en el mismo match antes de avanzar.',
      double_elimination: 'Cuadros superior e inferior por orden inicial; dos derrotas eliminan. BYE sin derrota, combate ni XP. Final entre invicto y ganador del inferior; si el invicto pierde, se juega una final de reset.',
      season_playoffs: 'Temporada regular a una vuelta y despues playoffs. Top N: mayor potencia de 2 hasta la mitad de participantes y maximo 8 (minimo 2). Puntos 3/1/0; desempates: Buchholz, diferencia de juegos y orden inicial.'
    };
    if (!descriptions[model]) return '';
    const actual = state ? ` Participantes fijados: ${state.roster.length}. Rondas suizas: ${state.policy.swiss_rounds}. Corte: ${state.policy.top_n}. Etapa actual: ${state.round}.` : '';
    const impact=document.querySelector('[data-gestion-competition-ranking-impact]')?.value||'FULL';
    const points=state?.policy?.points||{win:impact==='BONUS'?4:impact==='REDUCED'?1:3,draw:impact==='REDUCED'?0:1,loss:0,bye:3};
    const overrides=[...document.querySelectorAll('[data-parameter]')].filter(input=>!input.disabled&&input.value!=='').map(input=>input.closest('label').firstChild.textContent.trim()+': '+input.value).join('; ');
    return base + 'Valores base cuando no hay ajustes manuales: '+descriptions[model]+actual+' Escala aplicada a la tabla: victoria '+points.win+', empate '+points.draw+', derrota '+points.loss+'; BYE '+points.bye+' cuando corresponde.'+(overrides?' Ajustes elegidos: '+overrides+'.':'')+' Las fases se crean al completar los resultados de la anterior. Estas reglas no certifican un torneo oficial.';
  }
  function renderEventModelDiagnostic() {
    const box = document.querySelector('[data-event-model-diagnostic]');
    if (!box) return;
    const config={prompt_template:adminCompetitionTemplate?.value};
    for(const field of ['type','execution_model','phase_structure','advancement_rule','best_of','participant_source','match_format'])config[field]=document.querySelector('[data-gestion-competition-'+field.replaceAll('_','-')+']')?.value;
    const warnings = eventModelWarnings(config);
    const structural=window.YgoEventModels?.contractErrors(config,eventTemplateByCode(config.prompt_template))||[];
    document.querySelector('[data-gestion-competition-execution-model]')?.setCustomValidity(structural.join(' '));
    box.hidden = warnings.length === 0;
    box.textContent = warnings.length ? 'Revisa la configuracion antes de guardar o programar: ' + warnings.join(' ') : '';
  }
  document.querySelectorAll('[data-parameter]').forEach(input=>input.addEventListener('input',()=>updateEventCreateGuide()));
  function updateEventCreateGuide() {
    const execution=document.querySelector('[data-gestion-competition-execution-model]')?.value;
    const parameterFields={swiss:['swiss_rounds'],swiss_top_cut:['swiss_rounds','top_n'],groups:['group_max_size'],groups_knockout:['group_max_size','group_qualifiers'],season_playoffs:['top_n'],series:['series_matches'],flights:['group_max_size']}[execution]||[];
    document.querySelectorAll('[data-parameter]').forEach(input=>{const applies=parameterFields.includes(input.dataset.parameter);input.disabled=!applies;input.closest('label').hidden=!applies;});
    filterCompetitionVersionOptions();
    renderEventModelDiagnostic();
    const oldPolicy = document.querySelector('[data-event-workflow-policy]');
    if (oldPolicy) { oldPolicy.hidden = true; oldPolicy.textContent = ''; }
    renderEventModelImpact();
  }
  function selectedOptionText(selector) {
    const node = document.querySelector(selector);
    return node?.selectedOptions?.[0]?.textContent?.trim() || node?.value || '';
  }
  function renderEventModelImpact(saved = null) {
    const guide = document.querySelector(saved ? '[data-event-saved-reading]' : '[data-event-guide-content]');
    const sourceForm = document.querySelector('.event-create-form');
    if (!sourceForm) return;
    const form = saved ? sourceForm.cloneNode(true) : sourceForm;
    if (saved) {
      for (const node of form.querySelectorAll('input[name], select[name], textarea[name]')) {
        if (node.type === 'file') continue;
        const key = node.name.replace(/^competition_/, '').replace(/\[\]$/, '');
        const stored = saved[key];
        if (node.multiple) {
          const ids = (saved.division_ids || []).map(String);
          for (const option of node.options) option.selected = ids.includes(option.value);
        } else if (node.type === 'checkbox') node.checked = Number(stored) === 1;
        else {
          const raw = stored == null ? '' : String(stored);
          if (node.tagName === 'SELECT' && raw && !Array.from(node.options).some(o => o.value === raw)) {
            const option = new Option(raw, raw); node.append(option);
          }
          node.value = raw;
        }
      }
      const season = form.querySelector('[data-event-season-name]');
      if (season) season.value = saved.season_name || (data.seasons || []).find(s => String(s.id) === String(saved.season_id))?.name || 'Sin temporada registrada';
    }
    if (!guide || !form) return;
    const field = key => form.querySelector(`[data-gestion-competition-${key}]`);
    const value = key => field(key)?.value || '';
    const label = key => {
      const node = field(key);
      return node?.multiple ? Array.from(node.selectedOptions).map(o => o.textContent.trim()).join(', ')
        : node?.selectedOptions?.[0]?.textContent?.trim() || node?.value || 'Sin seleccionar';
    };
    const cards = [];
    const add = (title, chosen, explanation) => cards.push(`<article class="event-guide-card"><span>${escapeHtml(title)}</span><strong>${escapeHtml(chosen || 'Sin seleccionar')}</strong><p>${escapeHtml(explanation)}</p></article>`);
    const explanations = {
      advancement: {
        FINAL_STANDINGS: 'El evento termina con su tabla. No se crea una eliminatoria ni otra fase interna.',
        SOURCE_ADMISSION: 'La tabla sirve de fuente para otro evento. Las plazas y los criterios se guardan en Fuentes y clasificados del destino; no se adjudican cupos implicitos.',
        MANUAL_ENTRIES: 'La seleccion manual fija los participantes de entrada. No genera emparejamientos ni avances automaticos; los titulos manuales requieren adjudicacion expresa en Palmares.',
        GROUP_TOP_K: 'Clasifican los cupos configurados de cada grupo; despues avanza el ganador de cada cruce de la llave.',
        MANUAL_SELECTION: 'El organizador elige los clasificados. Esta opcion no calcula por si sola un corte automatico.',
        TOP_N: 'Clasifican los primeros puestos hasta completar el cupo. La cantidad depende del sistema competitivo y de sus parametros.',
        TOP_PERCENTAGE: 'En Fuentes y clasificados, distribuye el total de plazas entre fuentes mediante porcentajes que sumen 100. Los cupos se llenan con los primeros puestos; no calcula un porcentaje de todos los inscritos de cada fuente.',
        MINIMUM_POINTS: 'En Fuentes y clasificados, define puntos minimos y plazas exactas. Solo se admiten puestos con al menos esos puntos; si no hay suficientes, se rechaza la carga sin sustitutos.',
        GROUP_POSITION: 'La clasificacion depende del puesto dentro de cada grupo; no de una tabla que mezcle todos los grupos.',
        WINNER_ONLY: 'El ganador es quien avanza. En una eliminatoria, el resultado debe quedar resuelto antes de crear el siguiente cruce.',
        RUNNER_UP: 'El criterio selecciona al segundo puesto del evento de origen, en lugar del campeon.',
        RANKING_THRESHOLD: 'La clasificacion se decide por una posicion de corte en la tabla. Debes indicar hasta que puesto se acepta.',
        BEST_RECORD: 'En Fuentes y clasificados, se ordena por proporcion de victorias sobre encuentros disputados; despues por menor numero de derrotas y por el orden verificado de la fuente. Se carga el numero exacto de plazas configurado.',
        CUSTOM: 'El avance requiere una regla propia y parametros definidos; elegir Personalizado no crea esa regla automaticamente.'
      },
      draw: {
        ALLOWED: 'La politica admite un resultado empatado. Las rondas de eliminacion necesitan un ganador aunque la fase de tabla admita empates.',
        NOT_ALLOWED: 'No se acepta cerrar el enfrentamiento con empate; debe registrarse un ganador valido.',
        EXTRA_DUEL: 'Un empate queda pendiente de un duelo de desempate antes de cerrar el resultado.',
        EXTRA_MATCH: 'Un empate queda pendiente de desempate. Esta seleccion no crea automaticamente otro enfrentamiento en el calendario.',
        SUDDEN_DEATH: 'Un empate requiere resolverse con muerte subita segun el reglamento; no se sortea un ganador.',
        USE_TIEBREAKER: 'Si el resultado termina igualado, se debe completar el desempate antes de cerrarlo. El orden de una tabla usa los criterios del sistema competitivo.',
        MANUAL_DECISION: 'El organizador revisa el empate. Las restricciones de una fase eliminatoria siguen exigiendo un ganador.',
        CUSTOM: 'La politica de empate requiere una definicion propia. Las reglas de resultado y de la fase siguen vigentes.'
      }
    };
    const type = value('type'), model = value('execution-model');
    const template = eventTemplateByCode(value('template'));
    if(template?.purpose)add('Finalidad de la plantilla',template.label,template.purpose);
    if(template?.difference&&!saved)add('Qué distingue este modelo',template.label,template.difference);
    add('Tipo de evento', label('type'), competitionTypeDescriptions[type] || 'Determina la categoria con la que se organiza y consulta el evento.');
    add('Modelo preconfigurado', template?.label || 'Personalizado / sin plantilla', template
      ? (saved ? 'Es la plantilla con la que se configuro el evento. Los valores de esta ficha corresponden a lo guardado, aunque difieran de los valores iniciales de la plantilla.' : 'Este modelo rellena valores iniciales. La lectura de abajo muestra la configuracion actual, incluidos los cambios que hagas despues de elegirlo.')
      : 'No hay una plantilla registrada. Se utiliza la configuracion particular del evento.');
    let systemText = executionDescriptions[model] || {
      league_points: 'Los resultados alimentan una tabla por puntos. Esta opcion no significa ida y vuelta: eso se elige con el sistema Ida y vuelta.',
      top_cut: 'Los clasificados disputan un corte final. Deben quedar definidos los cupos y la forma de resolver sus cruces.',
      bracket: 'Los enfrentamientos se organizan en una llave. Deben quedar definidos el acceso y el avance de los participantes.',
      playoffs: 'Fase decisiva entre clasificados de una etapa anterior. Deben definirse los cupos y los cruces.',
      custom: 'Requiere definir como se empareja, se puntua y se avanza. No activa automaticamente un sistema nuevo.'
    }[model] || 'Consulta los requisitos de este sistema antes de programar el evento.';
    const practical = {
      swiss: ' Cada ronda se crea al completar la anterior. Con 8 inscritos se calculan 3 rondas; con 10, 4 rondas. Se empareja por rendimiento sin repetir rival y se bloquea la siguiente ronda si no hay cruces validos para completarla. Victoria: 3 puntos; empate: 1; derrota: 0. Si alguien descansa por numero impar, recibe 3 puntos sin combate ni XP. A igualdad de puntos se compara la puntuacion de sus rivales, despues la diferencia de juegos y el orden inicial.',
      swiss_top_cut: ' Se completa primero el suizo; despues juegan los mejores en eliminacion directa. El corte admite 2, 4 u 8 participantes segun el total inscrito.',
      groups: ' Se forman grupos de hasta 4; dentro de cada grupo todos se enfrentan una vez. Cada grupo tiene su propia tabla.',
      groups_knockout: ' Se forman grupos de hasta 4. Pasan los dos primeros de cada grupo y se cruzan primeros contra segundos de otro grupo.',
      series: ' Se inscriben dos participantes. Gana la serie quien gana dos enfrentamientos, con un maximo de tres. El formato de partida de abajo se aplica a cada enfrentamiento.',
      double_elimination: ' Hay una llave principal y otra de recuperacion. Si el finalista invicto pierde la primera final, se disputa una segunda final.',
      season_playoffs: ' Primero se juega todos contra todos a una vuelta. Despues pasan 2, 4 u 8 a la fase final, segun el total inscrito.'
    };
    systemText += practical[model] || '';
    add('Sistema competitivo', label('execution-model'), systemText);
    add('Participacion del enfrentamiento', label('match-format'), template?.code === 'tag_duel'
      ? 'Dos decks por lado comparten el resultado de Tag Duel. No se registran como dos victorias individuales.'
      : value('match-format') === '3v3' ? 'Tres decks por lado. En los modelos por equipos se guarda una alineacion fija y tres resultados individuales; el resultado conjunto decide el ganador.'
      : value('match-format') === '2v2' ? 'Dos decks por lado. El modelo y la regla elegidos determinan como se registra el resultado conjunto.'
      : 'Un deck se enfrenta a otro. El resultado corresponde a esos dos participantes.');
    add('Formato o division principal', label('division'), value('division').startsWith('format:')
      ? 'Selecciona la familia de decks del evento. No permite mezclar decks de formatos incompatibles.'
      : value('division') ? 'Es la division de referencia. El modo de participantes indica si se limita la seleccion a esta division.'
      : 'No se ha elegido una division. Revisa el formato base y el modo de participantes antes de inscribir decks.');
    const linked = form.querySelector('[data-gestion-league-divisions-select]');
    if (type === 'league' && linked) add('Divisiones de la liga', Array.from(linked.selectedOptions).map(o=>o.textContent.trim()).join(', ') || 'Sin divisiones seleccionadas', 'Los ascensos y descensos solo tienen sentido entre divisiones de esta misma liga. Una liga de una division no tiene movimientos.');
    add('Regla de avance', label('advancement-rule'), explanations.advancement[value('advancement-rule')] || 'Debes definir el criterio de clasificacion.');
    add('Politica de empate', label('draw-policy'), explanations.draw[value('draw-policy')] || 'Revisa como se resuelve un resultado igualado.');
    const best = Number(value('best-of'));
    add('Formato de partida', best > 0 ? `Mejor de ${best} (BO${best})` : 'Sin definir', best === 1
      ? 'Se disputa un solo juego por enfrentamiento. La regla de resultado seleccionada determina como se registra.'
      : `BO${best} indica el formato de cada enfrentamiento; no es el numero de rondas del evento. La regla de resultado elegida determina las condiciones de cierre y desempate.`);
    const rule = (data.gameRules || []).find(r=>String(r.id) === value('game-rule'));
    add('Regla del evento', label('game-rule'), rule
      ? `Esta regla evalua el resultado y sus condiciones de victoria, derrota o empate.${rule.description ? ' '+rule.description : ''} No sustituye al sistema que organiza las rondas.`
      : 'Se buscara una regla compatible al guardar. Aun no has elegido una regla concreta en este selector.');
    add('Formato base de los decks', label('ruleset'), 'Define la familia de reglas y los decks compatibles, por ejemplo TCG Avanzado o GOAT. Los participantes no pueden mezclar formatos incompatibles.');
    add('Reglamento y lista de cartas', label('ruleset-version'), value('ruleset-version')
      ? 'Es la version de reglamento o lista de cartas asociada. Debe corresponder al formato base; no cambia el sistema de rondas.'
      : 'Todavia no hay una version seleccionada. El formato base por si solo no identifica una lista concreta de cartas permitidas.');
    add('Modo de participantes', label('deck-pool'), value('deck-pool') === 'division'
      ? 'La seleccion toma los decks de la division elegida. Se mantienen las restricciones de formato y de pertenencia a una sola liga.'
      : 'Permite seleccionar decks activos sin limitarse a una division. Se mantienen las restricciones de formato y, en ligas, no se admiten decks inscritos en otra liga.');
    const season = form.querySelector('[data-event-season-name]');
    if (season) add('Temporada', season.value || 'Sin temporada', 'El evento pertenece a esta temporada. Al finalizarla se cierran los eventos asociados y se conserva su historial.');
    for (const [key,title,description] of [
      ['promotion-slots','Cupos de ascenso','Cantidad de decks que subirian a la division superior de esta liga. Debe coincidir con los descensos de esa division.'],
      ['promotion-to','Destino del ascenso','Division superior que recibira los decks que asciendan.'],
      ['relegation-slots','Cupos de descenso','Cantidad de decks que bajarian a la division inferior. Debe coincidir con los ascensos de esa division.'],
      ['relegation-to','Destino del descenso','Division inferior que recibira los decks que desciendan.']
    ]) if (field(key)?.getClientRects().length || (saved && type === 'league' && (saved.division_ids || []).length > 1)) add(title,label(key),description);
    const lock = form.querySelector('[data-gestion-competition-lock-moves]');
    if (lock?.getClientRects().length || (saved && type === 'league')) add('Movimientos entre divisiones', lock.checked ? 'Bloqueados' : 'Sin bloqueo manual', 'Este control bloquea los movimientos; no crea ascensos o descensos cuando solo hay una division.');
    const name = value('name');
    const notes = value('rules');
    if (name) add('Nombre del evento', name, 'Nombre con el que aparecera en la lista de eventos y en su ficha.');
    if (notes) add('Notas del organizador', notes, 'Estas notas documentan aclaraciones; no crean validaciones automaticas.');
    if (saved) {
      add('Inicio y cierre registrados', `${saved.starts_on || 'Inicio sin definir'} / ${saved.ends_on || 'Cierre sin definir'}`, 'Fechas guardadas del evento. La programacion de cada combate se consulta en su calendario.');
      const diagnostic = eventModelWarnings({...saved, teamConfig:saved.teamConfig});
      if (diagnostic.length) add('Requisitos pendientes', 'Configuracion con restricciones', diagnostic.join(' '));
      for (const [key,title,description] of [
        ['phase-structure','Fases registradas','Estructura declarada del evento. El sistema competitivo implementado determina las fases que se generan realmente.'],
        ['participant-source','Origen de participantes','Procedencia registrada. Cuando procede de otros eventos, las fuentes y los cupos se configuran y verifican en Fuentes y clasificados.'],
        ['event-flow','Relacion con otros eventos','Indica si es independiente o forma parte de un recorrido de clasificacion. Declararlo no crea automaticamente eventos de destino.'],
        ['ranking-impact','Impacto en la tabla','Politica guardada para la puntuacion. Las fases por etapas aplican su propio reglamento de puntos y desempates.'],
        ['deck-policy','Registro de decks','Politica registrada para los decks. Las restricciones de formato, inscripcion y alineaciones siguen siendo obligatorias.'],
        ['construction','Construccion de los decks','Modalidad declarada del evento. La plataforma organiza participantes, combates y resultados; no verifica el contenido de los decks.']
      ]) if (value(key)) add(title,label(key),description);
    }
    guide.innerHTML = cards.join('');
  }
  function eventTemplateByCode(code) {
    return (data.eventPromptTemplates || []).find((template) => String(template.code || '') === String(code || '')) || null;
  }
  function syncEventRuleSelection(force = false) {
    const select = document.querySelector('[data-gestion-competition-game-rule]');
    if (!select) return;
    const currentRule = (data.gameRules || []).find((item) => String(item.id || '') === String(select.value || ''));
    if (!force && String(select.value || '').trim() && currentRule) return;
    const template = eventTemplateByCode(adminCompetitionTemplate?.value || '');
    const code = template?.game_rule_code || '';
    if (!code) return;
    const rule = (data.gameRules || []).find((item) => String(item.code || '') === code && String(item.active ?? '1') !== '0');
    if (rule && Array.from(select.options || []).some((option) => String(option.value) === String(rule.id))) {
      select.value = String(rule.id);
    }
  }
  function enforceEventModelConstraints() {
    const type=adminCompetitionType?.value||'tournament';
    const execution=document.querySelector('[data-gestion-competition-execution-model]');
    if(execution)execution.setCustomValidity((executionByCompetitionType[type]||[]).includes(execution.value)?'':'El sistema no es compatible con este tipo. Selecciona uno compatible.');
    const advance=document.querySelector('[data-gestion-competition-advancement-rule]');
    if(advance)for(const option of advance.options)option.disabled=!['FINAL_STANDINGS','SOURCE_ADMISSION','MANUAL_ENTRIES','TOP_N','GROUP_TOP_K','WINNER_ONLY'].includes(option.value);
  }
  function syncCompetitionTypeCompatibility(resetInvalid = false) {
    const typeCode = adminCompetitionType?.value || 'tournament';
    const allowedExecutions = executionByCompetitionType[typeCode] || executionByCompetitionType.special;
    const executionSelect = document.querySelector('[data-gestion-competition-execution-model]');
    if (adminCompetitionDivision) {
      Array.from(adminCompetitionDivision.options || []).forEach((option) => {
        const ruleset = String(option.dataset.ruleset || '').toLowerCase();
        const text = String(option.textContent || '').toLowerCase();
        const isFormatOnly = String(option.value || '').startsWith('format:');
        const isGoat = ruleset === 'goat' || text.includes('goat');
        const compatible = !(typeCode === 'league' && (!option.value || isFormatOnly)) && !(typeCode === 'qualifier' && isGoat);
        option.hidden = !compatible;
        option.disabled = !compatible;
      });
      adminCompetitionDivision.required = typeCode === 'league';
      if (adminCompetitionDivision.selectedOptions[0]?.disabled) adminCompetitionDivision.value = '';
    }
    if (adminCompetitionRuleset) {
      Array.from(adminCompetitionRuleset.options || []).forEach((option) => {
        const compatible = !(typeCode === 'qualifier' && String(option.value || '').toLowerCase() === 'goat');
        option.hidden = !compatible;
        option.disabled = !compatible;
      });
      if (adminCompetitionRuleset.selectedOptions[0]?.disabled) adminCompetitionRuleset.value = 'tcg_advanced';
    }
    if (adminCompetitionTemplate) {
      Array.from(adminCompetitionTemplate.options || []).forEach((option) => {
        if (!option.value) {
          option.hidden = false;
          option.disabled = false;
          return;
        }
        const template = eventTemplateByCode(option.value);
        const templateType = option.dataset.templateType || template?.type || '';
        const compatible = !templateType || templateType === typeCode || typeCode === 'special';
        option.hidden = !compatible;
        option.disabled = !compatible;
      });
      const selectedTemplate = eventTemplateByCode(adminCompetitionTemplate.value || '');
      if (selectedTemplate && selectedTemplate.type && selectedTemplate.type !== typeCode && typeCode !== 'special') {
        adminCompetitionTemplate.value = '';
        if (resetInvalid) applyEventPromptTemplate('', false);
      }
    }
    if (executionSelect) {
      Array.from(executionSelect.options || []).forEach((option) => {
        const compatible = allowedExecutions.includes(option.value);
        option.hidden = !compatible;
        option.disabled = !compatible && !option.selected;
      });
      executionSelect.setCustomValidity(allowedExecutions.includes(executionSelect.value) ? '' : 'El sistema no es compatible con este tipo.');
    }
    enforceEventModelConstraints();
    syncEventRuleSelection(false);
  }
  function applyEventPromptTemplate(code, forceName = false) {
    document.querySelectorAll('[data-parameter]').forEach(input=>{input.value='';});
    const template = eventTemplateByCode(code);
    if (!template) {
      setValue('[data-gestion-competition-event-system]', '');
      setValue('[data-gestion-competition-event-system-preview]', 'Selecciona tipo');
      setValue('[data-gestion-competition-execution-model]', 'manual');
      setValue('[data-gestion-competition-execution-preview]', 'Manual');
      setValue('[data-gestion-competition-participation]', 'INDIVIDUAL_1V1');
      setValue('[data-gestion-competition-construction]', 'CONSTRUCTED');
      setValue('[data-gestion-competition-environment]', 'PRESENCIAL');
      setValue('[data-gestion-competition-level]', 'COMPETITIVE');
      setValue('[data-gestion-competition-event-flow]', 'STANDALONE');
      setValue('[data-gestion-competition-participant-source]', 'MANUAL');
      setValue('[data-gestion-competition-access-type]', 'OPEN');
      setValue('[data-gestion-competition-phase-structure]', 'NONE');
      setValue('[data-gestion-competition-advancement-rule]', 'MANUAL_SELECTION');
      setValue('[data-gestion-competition-draw-policy]', 'USE_TIEBREAKER');
      setValue('[data-gestion-competition-ranking-impact]', 'FULL');
    setValue('[data-gestion-competition-deck-policy]', 'OPTIONAL_REGISTRATION');
      syncEventRuleSelection(true);
      updateEventCreateGuide();
      return;
    }
    setValue('[data-gestion-competition-format]', template.format || template.system || 'editable');
    setValue('[data-gestion-competition-match-format]', template.match_format ?? '1v1');
    setValue('[data-gestion-competition-participants]', template.participants_per_side || '1');
    setValue('[data-gestion-competition-best-of]', template.best_of || '3');
    setValue('[data-gestion-competition-qualifier-duels]', template.qualifier_duels_per_deck || '3');
    setValue('[data-gestion-competition-matches-per-day]', template.qualifier_matches_per_day || '1');
    setValue('[data-competition-config-qualifier-duels]', template.qualifier_duels_per_deck || '3');
    setValue('[data-gestion-competition-schedule]', template.schedule || 'manual');
    setValue('[data-gestion-competition-ruleset]', template.ruleset || 'custom');
    setValue('[data-gestion-competition-deck-pool]', template.deck_pool || 'all_active');
    setValue('[data-gestion-competition-event-system]', template.system || '');
    setValue('[data-gestion-competition-event-system-preview]', template.system || '');
    setValue('[data-gestion-competition-execution-model]', template.execution_model || 'manual');
    setValue('[data-gestion-competition-execution-preview]', template.execution_model || 'manual');
    setValue('[data-gestion-competition-participation]', template.participation_code || 'INDIVIDUAL_1V1');
    setValue('[data-gestion-competition-construction]', template.construction_code || 'CONSTRUCTED');
    setValue('[data-gestion-competition-environment]', template.environment_code || 'PRESENCIAL');
    setValue('[data-gestion-competition-level]', template.level_code || 'COMPETITIVE');
    setValue('[data-gestion-competition-event-flow]', template.event_flow || 'STANDALONE');
    setValue('[data-gestion-competition-participant-source]', template.participant_source || 'MANUAL');
    setValue('[data-gestion-competition-access-type]', template.access_type || 'OPEN');
    setValue('[data-gestion-competition-phase-structure]', template.phase_structure || 'NONE');
    setValue('[data-gestion-competition-advancement-rule]', template.advancement_rule || 'MANUAL_SELECTION');
    setValue('[data-gestion-competition-draw-policy]', template.draw_policy || 'USE_TIEBREAKER');
    setValue('[data-gestion-competition-ranking-impact]', template.ranking_impact || 'FULL');
    setValue('[data-gestion-competition-deck-policy]', template.deck_policy || 'OPTIONAL_REGISTRATION');
    syncEventRuleSelection(true);
    const nameNode = document.querySelector('[data-gestion-competition-name]');
    if (nameNode && (forceName || !String(nameNode.value || '').trim() || nameNode.value === nameNode.dataset.templateName)) { nameNode.value = template.label || ''; nameNode.dataset.templateName = nameNode.value; }
    const notesNode = document.querySelector('[data-gestion-competition-rules]');
    if (notesNode && (!String(notesNode.value || '').trim() || notesNode.value === notesNode.dataset.templateNotes)) { notesNode.value = template.notes || ''; notesNode.dataset.templateNotes = notesNode.value; }
    if (Number(template.best_of || 3) === 1) setValue('[data-gestion-competition-game-rule]', '');
    syncParticipantsFromMatchFormat();
    applyRulesetToCompetition(false);
    syncGestionLeagueMovement();
    renderGestionLogoGallery('');
    updateCompetitionCodePreview();
    updateEventCreateGuide();
  }
  function renderEventLogoGallery(container, type, currentLogo = '') {
    if (!container) return;
    const grid = container.querySelector('[data-event-logo-gallery-grid]');
    const input = container.querySelector('[data-event-logo-gallery-input]');
    const count = container.querySelector('[data-event-logo-gallery-count]');
    if (!grid || !input) return;
    const logos = (data.eventLogoGallery || {})[type] || [];
    const selected = String(input.value || currentLogo || '');
    if (count) count.textContent = logos.length ? `${logos.length} disponibles` : 'Sin logos para este tipo';
    if (!logos.length) {
      input.value = '';
      grid.innerHTML = '<p class="note">No hay logos disponibles para este tipo.</p>';
      return;
    }
    grid.innerHTML = logos.map((logo) => {
      const url = String(logo.url || '');
      const active = selected === url;
      return `
        <button class="event-logo-choice${active ? ' active' : ''}" type="button" data-event-logo-choice="${escapeHtml(url)}" aria-pressed="${active ? 'true' : 'false'}">
          <img loading="lazy" decoding="async" src="${escapeHtml(url)}" alt="${escapeHtml(logo.label || 'Logo')}">
          <span>${escapeHtml(logo.label || 'Logo')}</span>
        </button>
      `;
    }).join('');
    if (selected && logos.some((logo) => String(logo.url || '') === selected)) {
      input.value = selected;
    } else if (!logos.some((logo) => String(logo.url || '') === input.value)) {
      input.value = '';
    }
    grid.querySelectorAll('[data-event-logo-choice]').forEach((button) => {
      button.addEventListener('click', () => {
        const value = button.dataset.eventLogoChoice || '';
        input.value = input.value === value ? '' : value;
        renderEventLogoGallery(container, type, input.value);
      });
    });
  }
  function renderGestionLogoGallery(currentLogo = '') {
    const form = document.querySelector('.event-create-form');
    if (!form) return;
    renderEventLogoGallery(form.querySelector('[data-event-logo-gallery]'), adminCompetitionType?.value || 'tournament', currentLogo);
  }
  function syncGestionLeagueMovement() {
    const box = document.querySelector('[data-gestion-league-movement]');
    if (box) {
      const form = box.closest('form');
      const type = form?.querySelector('[data-gestion-competition-type]')?.value || adminCompetitionType?.value || '';
      const divisionCount = form?.querySelector('[data-gestion-league-divisions-select]')?.selectedOptions?.length || 0;
      box.hidden = type !== 'league' || divisionCount < 2;
    }
    document.querySelectorAll('[data-gestion-league-divisions]').forEach((field) => {
      const form = field.closest('form');
      const type = form?.querySelector('[data-gestion-competition-type]')?.value || '';
      field.hidden = type !== 'league';
      const select = field.querySelector('[data-gestion-league-divisions-select]');
      if (select) select.required = type === 'league';
    });
  }
  function syncMovementTargetOptions(divisionValue, promotionSelector, relegationSelector) {
    const current = String(divisionValue || '');
    document.querySelectorAll(`${promotionSelector} option, ${relegationSelector} option`).forEach((option) => {
      option.hidden = !!current && option.value === current;
      if (option.hidden && option.selected) option.selected = false;
    });
  }
  function applyCompetitionPreset(force = false) {
    if (!adminCompetitionType) return;
    const preset = competitionTypePresets[adminCompetitionType.value] || competitionTypePresets.special;
    const fill = (selector, value) => {
      const node = document.querySelector(selector);
      if (node && (force || !String(node.value || '').trim())) node.value = value;
    };
    fill('[data-gestion-competition-format]', preset.format);
    fill('[data-gestion-competition-ruleset]', preset.ruleset);
    fill('[data-gestion-competition-deck-pool]', preset.deckPool);
    fill('[data-gestion-competition-schedule]', preset.schedule);
    fill('[data-gestion-competition-execution-model]', preset.execution);
    fill('[data-gestion-competition-execution-preview]', preset.execution);
    fill('[data-gestion-competition-participation]', preset.participation);
    fill('[data-gestion-competition-construction]', preset.construction);
    fill('[data-gestion-competition-environment]', preset.environment);
    fill('[data-gestion-competition-level]', preset.level);
    fill('[data-gestion-competition-event-flow]', preset.flow);
    fill('[data-gestion-competition-participant-source]', preset.source);
    fill('[data-gestion-competition-access-type]', preset.access);
    fill('[data-gestion-competition-phase-structure]', preset.phases);
    fill('[data-gestion-competition-advancement-rule]', preset.advancement);
    fill('[data-gestion-competition-draw-policy]', preset.drawPolicy);
    fill('[data-gestion-competition-ranking-impact]', preset.rankingImpact);
    fill('[data-gestion-competition-deck-policy]', preset.deckPolicy);
    syncEventRuleSelection(force);
    applyRulesetToCompetition(force);
    syncParticipantsFromMatchFormat();
    const conditionField = document.querySelector('[data-competition-condition-field]');
    if (conditionField) conditionField.hidden = !['league', 'tournament', 'world'].includes(adminCompetitionType.value || '');
    syncGestionLeagueMovement();
    syncCompetitionTypeCompatibility(false);
  }
  function pendingEventQualifiers() {
    return (data.competitions || []).filter((competition) =>
      competition.type === 'qualifier'
      && (
        ['pending_event', 'pendiente_evento'].includes(normalizeStatusKey(competition.status))
        || (normalizeStatusKey(competition.status) === 'pending_configuration' && Math.max(Number(competition.deck_pool_count || 0), (competition.selected_deck_ids || []).length) >= 4)
      )
      && normalize(competition.active ?? '1') !== '0'
    );
  }
  function populateConditionQualifierSelect(select, selectedValue = '') {
    if (!select) return;
    const qualifiers = pendingEventQualifiers();
    const current = selectedValue || select.value || '';
    select.innerHTML = `<option value="">Sin condicionante</option>${qualifiers.map((qualifier) => (
      `<option value="${escapeHtml(qualifier.id)}">${escapeHtml(qualifier.name || 'Clasificatorio')} - debe finalizar</option>`
    )).join('')}`;
    if (current && Array.from(select.options).some((option) => option.value === String(current))) {
      select.value = String(current);
    }
  }
  populateConditionQualifierSelect(document.querySelector('[data-competition-condition-select]'));
  const eventReadingForm = document.querySelector('.event-create-form');
  ['input', 'change'].forEach(eventName => eventReadingForm?.addEventListener(eventName, () => {
    queueMicrotask(updateEventCreateGuide);
  }));
  if (adminCompetitionType) adminCompetitionType.addEventListener('change', () => {
    applyCompetitionPreset(true);
    syncCompetitionTypeCompatibility(true);
    renderGestionLogoGallery('');
    updateEventCreateGuide();
  });
  if (adminCompetitionTemplate) adminCompetitionTemplate.addEventListener('change', () => {
    syncCompetitionTypeCompatibility(true);
    applyEventPromptTemplate(adminCompetitionTemplate.value, false);
  });
  if (adminCompetitionDivision) adminCompetitionDivision.addEventListener('change', () => {
    syncRulesetFromDivision(adminCompetitionDivision, adminCompetitionRuleset);
    syncMovementTargetOptions(adminCompetitionDivision.value, '[data-gestion-competition-promotion-to]', '[data-gestion-competition-relegation-to]');
    const form = adminCompetitionDivision.closest('form');
    const linkedSelect = form?.querySelector('[data-gestion-league-divisions-select]');
    if (linkedSelect && adminCompetitionDivision.value && !adminCompetitionDivision.value.startsWith('format:')) {
      const primaryOption = Array.from(linkedSelect.options).find((option) => option.value === adminCompetitionDivision.value);
      if (primaryOption) primaryOption.selected = true;
    }
    syncGestionLeagueMovement();
    updateCompetitionCodePreview();
  });
  document.querySelectorAll('[data-gestion-league-divisions-select]').forEach((select) => {
    select.addEventListener('change', () => {
      const form = select.closest('form');
      const primary = form?.querySelector('[data-gestion-competition-division]');
      const selectedIds = Array.from(select.selectedOptions).map((option) => option.value);
      if (primary && primary.value && !primary.value.startsWith('format:') && !selectedIds.includes(primary.value)) {
        const primaryOption = Array.from(select.options).find((option) => option.value === primary.value);
        if (primaryOption) primaryOption.selected = true;
      }
      if (primary && (!primary.value || primary.value.startsWith('format:')) && selectedIds.length) primary.value = selectedIds[0];
      syncGestionLeagueMovement();
    });
  });
  if (adminCompetitionRuleset) adminCompetitionRuleset.addEventListener('change', () => applyRulesetToCompetition(true));
  document.querySelectorAll('[data-gestion-competition-execution-model], [data-gestion-competition-participation], [data-gestion-competition-construction], [data-gestion-competition-environment], [data-gestion-competition-level], [data-gestion-competition-event-flow], [data-gestion-competition-participant-source], [data-gestion-competition-access-type], [data-gestion-competition-phase-structure], [data-gestion-competition-advancement-rule], [data-gestion-competition-draw-policy], [data-gestion-competition-ranking-impact], [data-gestion-competition-deck-policy], [data-gestion-competition-best-of], [data-gestion-competition-game-rule]').forEach((node) => {
    node.addEventListener('change', () => {
      enforceEventModelConstraints();
      if (node.matches('[data-gestion-competition-execution-model]')) syncEventRuleSelection(false);
      if(node.matches('[data-gestion-competition-execution-model]')&&adminCompetitionTemplate?.value==='pegasus_challenge'){const ko=node.value==='single_elimination';setValue('[data-gestion-competition-advancement-rule]',ko?'WINNER_ONLY':'FINAL_STANDINGS');setValue('[data-gestion-competition-phase-structure]','NONE');}
      updateEventCreateGuide();
    });
  });
  const adminCompetitionRulesetVersion = document.querySelector('[data-gestion-competition-ruleset-version]');
  if (adminCompetitionRulesetVersion) adminCompetitionRulesetVersion.addEventListener('change', () => {
    syncRulesetFromVersion(adminCompetitionRulesetVersion, adminCompetitionRuleset);
    updateCompetitionCodePreview();
  });
  document.querySelectorAll('[data-gestion-competition-match-format]').forEach((node) => {
    node.addEventListener('change', () => {
      syncParticipantsFromMatchFormat();
      updateCompetitionCodePreview();
      updateEventCreateGuide();
    });
  });
  if (competitionNameInput) competitionNameInput.addEventListener('input', updateCompetitionCodePreview);
  if (adminCompetitionSelect) {
    adminCompetitionSelect.addEventListener('change', () => {
      const competition = (data.competitions || []).find((item) => String(item.id) === adminCompetitionSelect.value);
      setValue('[data-gestion-competition-id]', competition ? competition.id : '');
      setValue('[data-gestion-competition-code]', competition ? competition.code : '');
      setValue('[data-gestion-competition-name]', competition ? competition.name : '');
      setValue('[data-gestion-competition-template]', competition ? competition.prompt_template : '');
      setValue('[data-gestion-competition-type]', competition ? competition.type : 'tournament');
      setValue('[data-gestion-competition-scope]', competition ? competition.scope : 'division');
      setValue('[data-gestion-competition-division]', competition ? (competition.division_id || (competition.event_ruleset ? `format:${competition.event_ruleset}` : '')) : '');
      document.querySelectorAll('[data-gestion-league-divisions-select]').forEach((select) => {
        const selectedIds = new Set((competition?.division_ids || []).map((id) => String(id)));
        Array.from(select.options).forEach((option) => { option.selected = selectedIds.has(option.value); });
      });
      setValue('[data-gestion-competition-format]', competition ? competition.format : '');
      setValue('[data-gestion-competition-match-format]', competition ? competition.match_format : '1v1');
      setValue('[data-gestion-competition-best-of]', competition ? competition.best_of : '5');
      setValue('[data-gestion-competition-participants]', competition ? competition.participants_per_side : '1');
      setValue('[data-gestion-competition-schedule]', competition ? competition.schedule_strategy : 'manual');
      setValue('[data-gestion-competition-ruleset]', competition ? competition.event_ruleset : 'tcg_advanced');
      setValue('[data-gestion-competition-season]', competition ? competition.season_id : data.currentSeasonId);
      document.querySelector('[data-gestion-competition-season]')?.dispatchEvent(new Event('change'));
      selectCompetitionRulesetVersion(competition ? (competition.ruleset_version || competition.banlist_version || '') : '');
      setValue('[data-gestion-competition-game-rule]', competition ? (competition.game_rule_id || '') : '');
      setValue('[data-gestion-competition-deck-pool]', competition ? competition.deck_pool : 'all_active');
      setValue('[data-gestion-competition-promotion-slots]', competition ? competition.promotion_slots : '0');
      setValue('[data-gestion-competition-relegation-slots]', competition ? competition.relegation_slots : '0');
      setValue('[data-gestion-competition-promotion-to]', competition ? competition.promotion_to_division_id : '');
      setValue('[data-gestion-competition-relegation-to]', competition ? competition.relegation_to_division_id : '');
      syncMovementTargetOptions(adminCompetitionDivision?.value || '', '[data-gestion-competition-promotion-to]', '[data-gestion-competition-relegation-to]');
      setScopedChecked(document, '[data-gestion-competition-lock-moves]', competition && (competition.lock_division_moves === 1 || competition.lock_division_moves === '1'));
      setValue('[data-gestion-competition-status]', competition ? competition.status : 'pending_configuration');
      setValue('[data-gestion-competition-start]', competition ? competition.starts_on : '');
      setValue('[data-gestion-competition-end]', competition ? competition.ends_on : '');
      setValue('[data-gestion-competition-accent]', competition ? competition.accent : '#36d8ff');
      setValue('[data-gestion-competition-rules]', competition ? competition.rules : '');
      setValue('[data-gestion-competition-event-system]', competition ? competition.event_system : '');
      setValue('[data-gestion-competition-event-system-preview]', competition ? competition.event_system : '');
      const selectedTypePreset = competitionTypePresets[competition?.type || 'tournament'] || competitionTypePresets.tournament;
      const executionModel = optionValueOrDefault('[data-gestion-competition-execution-model]', competition ? competition.execution_model : '', selectedTypePreset.execution || 'manual');
      setValue('[data-gestion-competition-execution-model]', executionModel);
      setValue('[data-gestion-competition-execution-preview]', executionModel);
      setValue('[data-gestion-competition-participation]', optionValueOrDefault('[data-gestion-competition-participation]', competition ? competition.participation : '', selectedTypePreset.participation || 'INDIVIDUAL_1V1'));
      setValue('[data-gestion-competition-construction]', optionValueOrDefault('[data-gestion-competition-construction]', competition ? competition.construction : '', selectedTypePreset.construction || 'CONSTRUCTED'));
      setValue('[data-gestion-competition-environment]', optionValueOrDefault('[data-gestion-competition-environment]', competition ? competition.environment : '', selectedTypePreset.environment || 'PRESENCIAL'));
      setValue('[data-gestion-competition-level]', optionValueOrDefault('[data-gestion-competition-level]', competition ? competition.level : '', selectedTypePreset.level || 'COMPETITIVE'));
      setValue('[data-gestion-competition-event-flow]', optionValueOrDefault('[data-gestion-competition-event-flow]', competition ? competition.event_flow : '', selectedTypePreset.flow || 'STANDALONE'));
      setValue('[data-gestion-competition-participant-source]', optionValueOrDefault('[data-gestion-competition-participant-source]', competition ? competition.participant_source : '', selectedTypePreset.source || 'MANUAL'));
      setValue('[data-gestion-competition-access-type]', optionValueOrDefault('[data-gestion-competition-access-type]', competition ? competition.access_type : '', selectedTypePreset.access || 'OPEN'));
      setValue('[data-gestion-competition-phase-structure]', optionValueOrDefault('[data-gestion-competition-phase-structure]', competition ? competition.phase_structure : '', selectedTypePreset.phases || 'NONE'));
      setValue('[data-gestion-competition-advancement-rule]', optionValueOrDefault('[data-gestion-competition-advancement-rule]', competition ? competition.advancement_rule : '', selectedTypePreset.advancement || 'MANUAL_SELECTION'));
      setValue('[data-gestion-competition-draw-policy]', optionValueOrDefault('[data-gestion-competition-draw-policy]', competition ? competition.draw_policy : '', selectedTypePreset.drawPolicy || 'USE_TIEBREAKER'));
      setValue('[data-gestion-competition-ranking-impact]', optionValueOrDefault('[data-gestion-competition-ranking-impact]', competition ? competition.ranking_impact : '', selectedTypePreset.rankingImpact || 'FULL'));
      setValue('[data-gestion-competition-deck-policy]', optionValueOrDefault('[data-gestion-competition-deck-policy]', competition ? competition.deck_policy : '', selectedTypePreset.deckPolicy || 'OPTIONAL_REGISTRATION'));
      renderGestionLogoGallery(competition ? (competition.logo_url || '') : '');
      if (adminArchiveCompetitionId) adminArchiveCompetitionId.value = competition ? competition.id : '';
      if (!competition) applyCompetitionPreset(true);
      applyRulesetToCompetition(false);
      syncParticipantsFromMatchFormat();
      syncGestionLeagueMovement();
      syncCompetitionTypeCompatibility(true);
      updateCompetitionCodePreview();
      updateEventCreateGuide();
    });
  }
  syncGestionLeagueMovement();
  syncCompetitionTypeCompatibility(true);
  renderGestionLogoGallery('');
  updateEventCreateGuide();
  document.querySelectorAll('[data-event-logo-upload]').forEach((input) => {
    input.addEventListener('change', () => {
      if (!input.files || !input.files.length) return;
      const form = input.closest('form');
      const galleryInput = form ? form.querySelector('[data-event-logo-gallery-input]') : null;
      if (galleryInput) galleryInput.value = '';
      const gallery = form ? form.querySelector('[data-event-logo-gallery]') : null;
      const typeSelect = form ? (form.querySelector('[data-gestion-competition-type]') || form.querySelector('[data-competition-config-type]')) : null;
      renderEventLogoGallery(gallery, typeSelect?.value || 'special', '');
    });
  });
  syncMovementTargetOptions(adminCompetitionDivision?.value || '', '[data-gestion-competition-promotion-to]', '[data-gestion-competition-relegation-to]');
  if (rulesetSelect) {
    rulesetSelect.addEventListener('change', () => {
      const ruleset = rulesetByCode.get(String(rulesetSelect.value || '')) || {};
      setValue('[data-ruleset-code]', ruleset.code || '');
      setValue('[data-ruleset-label]', ruleset.label || '');
      setValue('[data-ruleset-family]', ruleset.format_family || '');
      setValue('[data-ruleset-banlist-name]', ruleset.banlist_name || '');
      setValue('[data-ruleset-banlist-version]', ruleset.banlist_version || '');
      setValue('[data-ruleset-banlist-date]', ruleset.banlist_effective_on || '');
      setValue('[data-ruleset-match-format]', ruleset.match_format || '1v1');
      setValue('[data-ruleset-best-of]', ruleset.best_of || '3');
      setValue('[data-ruleset-participants]', ruleset.participants_per_side || '1');
      setValue('[data-ruleset-schedule]', ruleset.schedule_strategy || 'manual');
      setValue('[data-ruleset-deck-pool]', ruleset.deck_pool || 'all_active');
      setValue('[data-ruleset-victory-condition]', ruleset.victory_condition || '');
      setValue('[data-ruleset-defeat-condition]', ruleset.defeat_condition || '');
      setValue('[data-ruleset-draw-condition]', ruleset.draw_condition || '');
      setValue('[data-ruleset-notes]', ruleset.notes || '');
    });
  }
  const gameRuleSelect = document.querySelector('[data-game-rule-select]');
  const gameRuleArchiveId = document.querySelector('[data-game-rule-archive-id]');
  const gameRulesById = new Map((data.gameRules || []).map((rule) => [String(rule.id || ''), rule]));
  const ruleDetailScreen = document.querySelector('[data-screen="regla_ficha"]');
  const ruleConditionList = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-list]') : null;
  const ruleConditionTemplate = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-template]') : null;
  const ruleSearchInput = document.querySelector('[data-rule-search]');
  const ruleTypeFilter = document.querySelector('[data-rule-type-filter]');
  const ruleStatusFilter = document.querySelector('[data-rule-status-filter]');
  const ruleVisibleCount = document.querySelector('[data-rule-visible-count]');
  const ruleDetailForm = document.querySelector('[data-game-rule-form]');
  const ruleArchiveForm = document.querySelector('[data-game-rule-archive-form]');
  const conditionFieldNames = {
    id: 'condition_id',
    type: 'condition_type',
    label: 'condition_label',
    operator: 'condition_operator',
    target: 'condition_target',
    message: 'condition_failure_message',
    active: 'condition_active'
  };
  const ruleConditionModalNode = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-modal]') : null;
  const ruleConditionModal = ruleConditionModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(ruleConditionModalNode) : null;
  const conditionModalFields = {
    row: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-row]') : null,
    title: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-title]') : null,
    type: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-type]') : null,
    label: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-label]') : null,
    operator: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-operator]') : null,
    target: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-target]') : null,
    result: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-result]') : null,
    status: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-status]') : null,
    score: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-score]') : null,
    message: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-message]') : null,
    active: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-active]') : null,
    save: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-save]') : null
  };

