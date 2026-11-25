/**
 * event-planning: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function renderDivisionSchedule(matches, competition, success) {
    if(!matches.length)return '<p class="note">No hay duelos programados para este evento.</p>';
    const groups=new Map();
    matches.forEach(match=>{
      const entry=Object.entries(data.divisions||{}).find(([code,division])=>(match.division===code||(Number(match.division_id)>0&&Number(match.division_id)===Number(division.id)))&&(competition.type!=='league'||String(division.league_competition_id)===String(competition.id)));
      const key=entry?.[0]||'unassigned';const division=entry?.[1];
      if(!groups.has(key))groups.set(key,{key,division,matches:[]});groups.get(key).matches.push(match);
    });
    const divisions=[...groups.values()].sort((a,b)=>a.key.localeCompare(b.key,'es',{numeric:true}));
    return `${success}<div class="schedule-division-browser"><nav class="schedule-division-nav" aria-label="Divisiones del evento">${divisions.map((group,i)=>{
      let name=group.division?.name||(competition.type==='league'?'Sin división registrada':'Programación del evento');if(name.startsWith(competition.name))name=name.slice(competition.name.length).replace(/^[\s\u2014\u2013-]+/u,'');
      return `<button type="button" class="btn btn-outline-info" data-schedule-division="${i}" aria-pressed="${i===0?'true':'false'}" aria-controls="schedule-division-panel-${i}"><strong>${escapeHtml(name)}</strong><small>${group.matches.length} combates</small></button>`;
    }).join('')}</nav>${divisions.map((group,i)=>{
      const done=group.matches.filter(competitionMatchIsFinalized).length,name=group.division?.name||(competition.type==='league'?'Sin división registrada':competition.name);
      return `<section id="schedule-division-panel-${i}" class="schedule-division-panel" data-schedule-division-panel="${i}" ${i?'hidden':''}><header><div><h3>${escapeHtml(name)}</h3><p>${done} / ${group.matches.length} combates completados</p></div></header><div class="schedule-match-cards">${group.matches.map(match=>{
        const raw=String(match.estado||match.status||'PENDIENTE').toUpperCase(),done=competitionMatchIsFinalized(match),active=['EN CURSO','REQUIERE D5'].includes(raw);
        const state=done?'Finalizado':active?'En curso':raw==='PENDIENTE'?'Pendiente':raw;
        return `<div class="schedule-match-card" data-schedule-match-id="${Number(match.id)}"><div class="schedule-match-card-meta"><strong>${escapeHtml(match.scheduled_on||'Sin fecha')} ${escapeHtml(String(match.starts_at||'').slice(0,5))}</strong><span class="status-chip ${done?'state-completed':active?'ok':'state-pending-config'}">${escapeHtml(state)}</span></div><p class="schedule-match-round">Ronda ${escapeHtml(match.ronda||match.round_number||'-')} · Mesa ${escapeHtml(match.mesa||match.table_number||'-')}</p><h4>${escapeHtml(matchVersusLabel(match))}</h4>${match.score?`<p>Marcador: <strong>${escapeHtml(match.score)}</strong></p>`:''}<button class="btn btn-outline-info" type="button" data-schedule-open-match="${Number(match.id)}"><i class="bi bi-eye"></i> Ver ficha</button></div>`;
      }).join('')}</div></section>`;
    }).join('')}</div>`;
  }

  function competitionMatchesFor(competitionId) {
    return allMatchList().filter((match) => String(match.competition_id || '') === String(competitionId || ''));
  }
  function competitionMatchIsFinalized(match) {
    return ['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''));
  }
  function latestCompetitionPhase(matches) {
    const phaseOrder = { qualifier: 1, swiss: 2, groups: 3, league: 4, cup: 5, top_cut: 6, playoff: 7, final: 8 };
    return matches.slice().sort((left, right) =>
      (phaseOrder[String(right.phase || '').toLowerCase()] || 0) - (phaseOrder[String(left.phase || '').toLowerCase()] || 0)
      || Number(right.ronda || right.round_number || 0) - Number(left.ronda || left.round_number || 0)
    )[0]?.phase || '';
  }
  function renderCompetitionActionPreview(competition) {
    const node = document.querySelector('[data-competition-action-preview]');
    if (!node) return;
    if (!competition?.id) {
      node.innerHTML = '<strong>Lectura preventiva</strong><span>Selecciona un evento activo para ver si puede avanzar, aplicar divisiones o mover equipos.</span>';
      return;
    }
    const matches = competitionMatchesFor(competition.id);
    const pending = matches.filter((match) => !competitionMatchIsFinalized(match)).length;
    const completed = matches.length - pending;
    const phase = latestCompetitionPhase(matches) || competition.phase_structure || competition.type || 'evento';
    const divisions = Object.values(data.divisions || {}).filter((division) => String(division.source_qualifier_id || '') === String(competition.id || ''));
    const standings = Array.isArray(competition.ranking) ? competition.ranking : [];
    const lines = [];
    if (!matches.length) {
      lines.push('No hay combates programados todavia; primero genera calendario o guarda participantes.');
    } else if (pending > 0) {
      lines.push(`Faltan ${pending} de ${matches.length} combates por cerrar en ${phase}; no conviene avanzar fase todavia.`);
    } else {
      const normalizedPhase = String(phase).toLowerCase();
      const winners = matches.filter((match) => ['A', 'B'].includes(String(match.result || '').toUpperCase())).length;
      if (['cup', 'final', 'single_elimination', 'top_cut', 'playoff'].includes(normalizedPhase)) {
        const nextMatches = Math.floor(winners / 2);
        lines.push(nextMatches > 0
          ? `Fase actual cerrada: puede crear exactamente ${nextMatches} combate(s) de la siguiente llave.`
          : 'Fase actual cerrada: no hay suficientes ganadores para crear otra ronda.');
      } else if (competition.type === 'league' && ['season_playoffs', 'playoffs'].includes(String(competition.execution_model || ''))) {
        const nextMatches = Math.max(1, Math.floor(Math.min(standings.length || winners || completed, 8) / 2));
        lines.push(`Liga cerrada: puede crear hasta ${nextMatches} combate(s) de playoff segun ranking.`);
      } else if (competition.type === 'league') {
        lines.push('Liga de fase unica: al cerrar todos los combates se finaliza la tabla; no existe una fase siguiente.');
      } else {
        const nextMatches = Math.max(1, Math.min(8, Math.ceil((standings.length || winners || completed) / 4)));
        lines.push(`Fase actual cerrada: puede crear ${nextMatches} combate(s) segun modelo y ranking.`);
      }
    }
    if (['cup', 'tournament'].includes(String(competition.type || ''))) {
      const deckCount = Number(competition.deck_pool_count || (competition.selected_deck_ids || []).length || 0);
      if (deckCount > 0 && deckCount % 2 !== 0) {
        lines.push(`Roster impar: hay ${deckCount} decks. Quita 1 deck desde Decks participantes para poder programar la copa.`);
      }
    }
    if (competition.type === 'qualifier') {
      lines.push(divisions.length
        ? `Divisiones conectadas: ${divisions.length}. Aplicar divisiones movera decks segun rangos guardados.`
        : 'No hay divisiones conectadas a este clasificatorio; configura rangos antes de aplicar divisiones.');
    }
    if (competition.type === 'league' && Number(competition.division_count || 0) > 1) {
      const lock = String(competition.lock_division_moves || '0') === '1';
      const up = Number(competition.promotion_slots || 0);
      const down = Number(competition.relegation_slots || 0);
      lines.push(lock
        ? 'Ascensos/descensos bloqueados para esta liga: la accion no movera decks.'
        : 'Configura los cupos por division en Ascenso y descenso. Requiere liga finalizada y ranking calculado.');
    }
    node.innerHTML = `<strong>Lectura preventiva</strong>${lines.map((line) => `<span>${escapeHtml(line)}</span>`).join('')}`;
  }
  function renderEventMainChoices(competition) {
    const root = document.querySelector('[data-competition-config-modal]');
    if (!root) return;
    const optionLabel = (selector,value) => Array.from(document.querySelector(selector)?.options || []).find(o=>String(o.value)===String(value??''))?.textContent?.trim() || (value ? String(value) : 'Sin definir');
    const template = eventTemplateByCode(competition.prompt_template);
    const rule = (data.gameRules || []).find(r=>String(r.id)===String(competition.game_rule_id));
    const fields = {
      template: template?.label || (competition.prompt_template ? String(competition.prompt_template) : 'Personalizado / sin plantilla'),
      execution: optionLabel('[data-gestion-competition-execution-model]',competition.execution_model),
      division: optionLabel('[data-gestion-competition-division]',competition.division_id),
      divisions: (competition.division_ids || []).map(id=>optionLabel('[data-gestion-league-divisions-select]',id)).join(' / ') || 'Sin divisiones asociadas',
      match: optionLabel('[data-gestion-competition-match-format]',competition.match_format),
      'best-of': competition.best_of ? `${competition.best_of} (BO${competition.best_of})` : 'Sin definir',
      advancement: optionLabel('[data-gestion-competition-advancement-rule]',competition.advancement_rule),
      draw: optionLabel('[data-gestion-competition-draw-policy]',competition.draw_policy),
      rule: rule?.name || (competition.game_rule_id ? `Regla registrada ${competition.game_rule_id}` : 'Regla compatible por defecto'),
      parameters: (()=>{let params=competition.execution_parameters||{};if(typeof params==='string'){try{params=JSON.parse(params);}catch{return 'Parametros no legibles: requiere revision';}}const labels={swiss_rounds:'Rondas suizas',top_n:'Clasificados al corte',group_max_size:'Maximo por grupo',group_qualifiers:'Clasificados por grupo',series_matches:'Matches de la serie',series_wins:'Victorias para ganar la serie'};return Object.entries(params).map(([key,value])=>(labels[key]||key)+': '+value).join(' | ')||'Politica interna Station X 2026 v1, sin ajustes manuales';})()
    };
    for (const [key,value] of Object.entries(fields)) {
      const input=root.querySelector(`[data-event-main-choice="${key}"]`);
      if(input){input.value=value;input.title=value;}
    }
    const divisions=root.querySelector('[data-event-main-field="divisions"]');
    if(divisions)divisions.hidden=competition.type!=='league';
  }
  function fillCompetitionConfig(competitionId) {
    const competitions = data.competitions || [];
    let competition = competitions.find((item) => String(item.id || '') === String(competitionId || ''));
    if (!competitionConfigModal || !competition) return false;
    const notice = document.querySelector('[data-event-open-error]');
    if (notice) notice.hidden = true;
    activeCompetitionConfigId = String(competition.id || '');
    localStorage.setItem('dml_event_ficha_id', activeCompetitionConfigId);
    setEventFichaUrl(activeCompetitionConfigId);
    const selectedIds = new Set((competition.selected_deck_ids || []).map((id) => String(id)));
    competitionSelectedDeckIds = selectedIds;
    const title = competitionConfigModal.querySelector('[data-competition-config-title]');
    const meta = competitionConfigModal.querySelector('[data-competition-config-meta]');
    const idInput = competitionConfigModal.querySelector('[data-competition-config-id]');
    const codeInput = competitionConfigModal.querySelector('[data-competition-config-code]');
    const nameInput = competitionConfigModal.querySelector('[data-competition-config-name]');
    const typeSelect = competitionConfigModal.querySelector('[data-competition-config-type]');
    const statusSelect = competitionConfigModal.querySelector('[data-competition-config-status]');
    const divisionSelect = competitionConfigModal.querySelector('[data-competition-config-division]');
    const divisionsSelect = competitionConfigModal.querySelector('[data-competition-config-divisions]');
    const formatLabel = competitionConfigModal.querySelector('[data-competition-config-format-label]');
    const deckPoolSelect = competitionConfigModal.querySelector('[data-competition-config-deck-pool]');
    const rulesetSelect = competitionConfigModal.querySelector('[data-competition-config-ruleset]');
    const rulesetVersionSelect = competitionConfigModal.querySelector('[data-competition-config-ruleset-version]');
    const logoWrap = competitionConfigModal.querySelector('[data-competition-config-logo-wrap]');
    const logoImg = competitionConfigModal.querySelector('[data-competition-config-logo]');
    const logoInput = competitionConfigModal.querySelector('[data-competition-config-logo-input]');
    const logoGallery = competitionConfigModal.querySelector('[data-event-logo-gallery]');
    const accentInput = competitionConfigModal.querySelector('[data-competition-config-accent]');
    const rulesInput = competitionConfigModal.querySelector('[data-competition-config-rules]');
    const conditionSelect = competitionConfigModal.querySelector('[data-competition-config-condition]');
    const deleteButton = competitionConfigModal.querySelector('[data-competition-delete-open]');
    const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
    const matchSelect = competitionConfigModal.querySelector('[data-competition-config-match]');
    const patternSelect = competitionConfigModal.querySelector('[data-competition-config-pattern]');
    const matchesPerDaySelect = competitionConfigModal.querySelector('[data-competition-config-matches-per-day]');
    const ruleSelect = competitionConfigModal.querySelector('[data-competition-config-rule]');
    const qualifierDuelsSelect = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]');
    const finishButton = competitionConfigModal.querySelector('[data-competition-finalize-submit]');
    const isLocked = competition.configuration_locked === 1 || competition.configuration_locked === '1';
    const isCancelled = competitionIsCancelled(competition.status);
    competitionConfigModal.dataset.eventCancelled = isCancelled ? '1' : '0';
    const rosterLocked = !competitionCanEditRoster(competition);
    competitionConfigModal.dataset.deckRosterLocked = rosterLocked ? '1' : '0';
    const normalizedStatus = normalizeCompetitionStatus(competition.status);
    const divisionLabel = competition.division || competition.division_code || 'General';
    const startLabel = competition.starts_on || 'Sin fecha';
    const patternLabel = schedulePatternLabel(competition.schedule_pattern) || competition.schedule_pattern || 'Sin programar';
    renderCompetitionActionPreview(competition);

    renderEventMainChoices(competition);
    if (title) title.textContent = competition.name || 'Evento';
    if (meta) meta.textContent = `${eventTypeLabel(competition.type)} | ${divisionLabel} | ${rulesetLabel(competition.event_ruleset)} | ${competitionStatusLabel(normalizedStatus)}`;
    const eventExport = competitionConfigModal.querySelector('[data-event-export-pdf]');
    if (eventExport) eventExport.href = './?export=entity_report&entity_type=event&entity_id=' + encodeURIComponent(competition.id || '') + '&format=pdf';
    const shareEvent = competitionConfigModal.querySelector('[data-share-event-profile]');
    if (shareEvent) shareEvent.onclick = () => {
      const summary = (data.eventSummaries || []).find((row) => String(row.competition_id) === String(competition.id));
      shareCanvas('Perfil de evento', [competition.name || 'Evento', eventTypeLabel(competition.type), rulesetLabel(competition.event_ruleset), competitionStatusLabel(normalizedStatus), summary?.champion_name ? 'Campeon: ' + summary.champion_name : 'En progreso', startLabel], 'evento-' + normalize(competition.name || 'evento').replaceAll(' ', '-') + '.png');
    };
    const statusChip = competitionConfigModal.querySelector('[data-competition-config-status-chip]');
    if (statusChip) {
      statusChip.textContent = competitionStatusLabel(normalizedStatus);
      statusChip.className = `tag ${isCancelled ? 'state-cancelled' : ''}`.trim();
    }
    const setSummary = (selector, value) => {
      const node = competitionConfigModal.querySelector(selector);
      if (node) node.textContent = value;
    };
    setSummary('[data-competition-config-summary-type]', eventTypeLabel(competition.type));
    setSummary('[data-competition-config-summary-division]', divisionLabel);
    setSummary('[data-competition-config-summary-match]', String(competition.match_format || '1v1').toUpperCase());
    setSummary('[data-competition-config-summary-start]', startLabel);
    setSummary('[data-competition-config-summary-pattern]', patternLabel);
    setSummary('[data-competition-config-summary-decks]', `${selectedIds.size || Number(competition.deck_pool_count || 0)} decks`);
    if (idInput) idInput.value = competition.id || '';
    if (codeInput) codeInput.value = competition.code || '';
    if (nameInput) nameInput.value = competition.name || '';
    const removeLogoInput = competitionConfigModal.querySelector('[data-competition-config-remove-logo]');
    if (logoInput) logoInput.value = '';
    if (removeLogoInput) removeLogoInput.checked = false;
    if (logoImg && logoWrap) {
      const logoUrl = String(competition.logo_url || '').trim();
      logoImg.src = logoUrl || '';
      logoWrap.hidden = !logoUrl;
    }
    if (typeSelect) typeSelect.value = competition.type || 'special';
    if (formatLabel) formatLabel.value = competition.event_ruleset || 'custom';
    setValue('[data-competition-season]', competition.season_id);
    document.querySelector('[data-competition-season]')?.dispatchEvent(new Event('change'));
    renderEventLogoGallery(logoGallery, competition.type || 'special', competition.logo_url || '');
    if (statusSelect) {
      syncCompetitionStatusOptions(statusSelect, competition.status);
      statusSelect.value = normalizeCompetitionStatus(competition.status);
    }
    if (deleteButton) {
      const deleteAllowed = !['active', 'completed', 'cancelled'].includes(normalizedStatus);
      deleteButton.disabled = !deleteAllowed;
      deleteButton.dataset.competitionId = String(competition.id || '');
      deleteButton.dataset.competitionName = competition.name || 'Evento';
      deleteButton.title = deleteAllowed ? 'Eliminar evento' : 'Solo se eliminan eventos pendientes o en configuracion';
    }
    if (finishButton) {
      const competitionMatches = allMatchList().filter((match) => String(match.competition_id || '') === String(competition.id || ''));
      const pendingMatches = competitionMatches.filter((match) => !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')));
      const canFinish = normalizeCompetitionStatus(competition.status) === 'active' && competitionMatches.length > 0 && pendingMatches.length === 0;
      finishButton.hidden = !canFinish;
      finishButton.disabled = !canFinish;
      finishButton.title = canFinish ? 'Todos los duelos estan registrados. Puedes finalizar el evento.' : 'Disponible cuando todos los duelos esten finalizados.';
    }
    if (divisionSelect) {
      const isLeague = competition.type === 'league';
      divisionSelect.value = competition.division_id || '';
      divisionSelect.required = isLeague;
      divisionSelect.dataset.lockedAlways = isLeague ? '0' : '1';
      divisionSelect.dataset.lockedByStart = isLocked ? '1' : '0';
      divisionSelect.disabled = !isLeague || isLocked || isCancelled;
      divisionSelect.title = isLeague ? 'Toda liga debe estar asociada a una division.' : 'La division solo aplica a eventos de tipo liga.';
    }
    if (divisionsSelect) {
      const divisionIds = new Set((competition.division_ids || []).map((id) => String(id)));
      Array.from(divisionsSelect.options).forEach((option) => { option.selected = divisionIds.has(option.value); });
      divisionsSelect.required = competition.type === 'league';
      divisionsSelect.dataset.lockedByStart = isLocked ? '1' : '0';
      divisionsSelect.disabled = competition.type !== 'league' || isLocked || isCancelled;
    }
    if (deckPoolSelect) deckPoolSelect.value = competition.deck_pool || 'all_active';
    if (rulesetSelect) rulesetSelect.value = competition.event_ruleset || 'custom';
    if (rulesetVersionSelect) {
      rulesetVersionSelect.querySelectorAll('[data-saved-version]').forEach(option=>option.remove());
      const savedVersion=String(competition.ruleset_version || '');
      if(savedVersion && !Array.from(rulesetVersionSelect.options).some(option=>option.value===savedVersion)){
        const option=new Option(savedVersion,savedVersion);option.dataset.savedVersion='1';option.dataset.ruleset=competition.event_ruleset || '';rulesetVersionSelect.append(option);
      }
      rulesetVersionSelect.value=savedVersion;
    }
    if (accentInput) accentInput.value = competition.accent || '#36d8ff';
    if (rulesInput) rulesInput.value = competition.rules || '';
    if (conditionSelect) {
      const dependency = Array.isArray(competition.dependencies) ? competition.dependencies[0] : null;
      populateConditionQualifierSelect(conditionSelect, dependency ? String(dependency.depends_on_competition_id || '') : '');
      conditionSelect.value = dependency ? String(dependency.depends_on_competition_id || '') : '';
    }
    if (startInput) {
      startInput.value = competition.starts_on || data.serverToday || '';
      startInput.dataset.savedValue = competition.starts_on || '';
    }
    if (matchSelect) matchSelect.value = competition.match_format || '1v1';
    if (patternSelect) patternSelect.value = competition.schedule_pattern || 'consecutive';
    if (matchesPerDaySelect) matchesPerDaySelect.value = String(Math.max(1, Math.min(5, Number(competition.qualifier_matches_per_day || 1))));
    if (ruleSelect) ruleSelect.value = competition.game_rule_id || '';
    renderCompetitionRuleSummary(competition);
    if (qualifierDuelsSelect) qualifierDuelsSelect.value = String(competition.qualifier_duels_per_deck || 3);
    setScopedValue(competitionConfigModal, '[data-competition-config-promotion-slots]', competition.promotion_slots || '0');
    setScopedValue(competitionConfigModal, '[data-competition-config-relegation-slots]', competition.relegation_slots || '0');
    setScopedValue(competitionConfigModal, '[data-competition-config-promotion-to]', competition.promotion_to_division_id || '');
    setScopedValue(competitionConfigModal, '[data-competition-config-relegation-to]', competition.relegation_to_division_id || '');
    syncMovementTargetOptions(competition.division_id || '', '[data-competition-config-promotion-to]', '[data-competition-config-relegation-to]');
    setScopedChecked(competitionConfigModal, '[data-competition-config-lock-moves]', competition.lock_division_moves === 1 || competition.lock_division_moves === '1');
    if (startInput) {
      startInput.dataset.lockedByStart = isLocked ? '1' : '0';
      startInput.disabled = isLocked || isCancelled;
    }
    if (patternSelect) {
      patternSelect.dataset.lockedByStart = isLocked ? '1' : '0';
      patternSelect.disabled = isLocked || isCancelled;
    }
    if (matchesPerDaySelect) {
      matchesPerDaySelect.dataset.lockedByStart = isLocked ? '1' : '0';
      matchesPerDaySelect.disabled = isLocked || isCancelled;
    }
    renderLeagueMovementSheet(competition);
    syncCompetitionDeckInputs();
    syncCompetitionDeckPoolNote();
    renderCompetitionSelectedDecks();
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
    const rankingModalTitle = eventRankingModalNode ? eventRankingModalNode.querySelector('[data-event-ranking-modal-title]') : null;
    const rankingModalMeta = eventRankingModalNode ? eventRankingModalNode.querySelector('[data-event-ranking-modal-meta]') : null;
    if (rankingModalTitle) rankingModalTitle.textContent = competition.name || 'Tabla del evento';
    const rankingTag=eventRankingModalNode?.querySelector('[data-event-ranking-label]');if(rankingTag)rankingTag.textContent=competition.type==='league'?'Clasificación por divisiones':'Tabla general';
    const rankingOpen=competitionConfigModal.querySelector('[data-open-event-ranking-modal]');if(rankingOpen)rankingOpen.innerHTML='<i class="bi bi-table"></i> '+(competition.type==='league'?'Ver tablas por división':'Ver tabla general');
    if (rankingModalMeta) rankingModalMeta.textContent = `${eventTypeLabel(competition.type)} | ${rulesetLabel(competition.event_ruleset)} | ${competitionStatusLabel(competition.status)}`;
    const rankingNode = document.querySelector('[data-competition-config-ranking]');
    if (rankingNode) {
      const tabsNode = document.querySelector('[data-event-ranking-phase-tabs]');
      const byDivision = competition.type === 'league';
      const divisions = byDivision ? leagueMovementDivisions(competition) : [];
      const divisionKey = division => 'division:' + division.id;
      const snapshots = (data.standingSnapshots || []).filter((row) => String(row.competition_id || '') === String(competition.id || ''));
      const snapshotKeys = Array.from(new Set(snapshots.map((row) => row.snapshot_key || 'current')));
      const preferredKeys = ['current', 'phase:qualifier', 'phase:swiss', 'phase:groups', 'phase:league', 'phase:top_cut', 'phase:playoff', 'phase:cup', 'phase:final'];
      const orderedKeys = byDivision ? divisions.map(divisionKey) : [...preferredKeys.filter((key) => snapshotKeys.includes(key)), ...snapshotKeys.filter((key) => !preferredKeys.includes(key))];
      const activeKey = orderedKeys.includes(rankingNode.dataset.activeRankingKey || '') ? rankingNode.dataset.activeRankingKey : (orderedKeys[0] || 'current');
      const labelForSnapshot = (key) => { if(byDivision){let name=divisions.find(d=>divisionKey(d)===key)?.name||'Division';if(name.startsWith(competition.name))name=name.slice(competition.name.length).replace(/^[\s\u2014\u2013-]+/u,'');return name;}return key === 'current' ? 'General' : key.replace('phase:', '').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); };
      if (tabsNode) {
        tabsNode.innerHTML = orderedKeys.length ? orderedKeys.map((key) => `<button class="${key === activeKey ? 'active' : ''}" type="button" data-ranking-phase-key="${escapeHtml(key)}" aria-pressed="${key === activeKey}">${escapeHtml(labelForSnapshot(key))}</button>`).join('') : '';
        tabsNode.querySelectorAll('[data-ranking-phase-key]').forEach((button) => {
          button.addEventListener('click', () => {
            rankingNode.dataset.activeRankingKey = button.dataset.rankingPhaseKey || 'current';
            fillCompetitionConfig(competition.id);
          });
        });
      }
      const sourceKey = byDivision ? (snapshotKeys.includes('current') ? 'current' : 'phase:league') : activeKey;
      const snapshotRows = snapshots
        .filter((row) => (row.snapshot_key || 'current') === sourceKey)
        .sort((a, b) => Number(a.position || 0) - Number(b.position || 0));
      let ranking = snapshotRows.length ? snapshotRows.map((row) => {
        const deck = (data.allDecks || []).find((item) => String(item.id || '') === String(row.deck_id || '')) || {};
        const workflowRow = parseJsonValue(row.payload, {})?.row || {};
        return {
          id: Number(row.deck_id),
          division_id: Number(deck.division_id || 0),
          name: deck.name || `Deck #${row.deck_id}`,
          team: deck.team || 'Sin equipo',
          logo: deck.team_logo || deck.logo_url || deck.cover_url || '',
          pts: row.points,
          pj: row.played,
          pg: row.wins,
          pe: row.draws,
          pp: row.losses,
          dp: row.difference,
          cg: row.duels_won,
          cp: row.duels_lost,
          position: Number(row.position || 0),
          group: workflowRow.group,
          group_position: workflowRow.group_position,
          buchholz: workflowRow.buchholz
        };
      }) : ((byDivision || activeKey === 'current') && Array.isArray(competition.ranking) ? competition.ranking : []);
      const activeDivision = byDivision ? divisions.find(d=>divisionKey(d)===activeKey) : null;
      if(byDivision)ranking=ranking.filter(row=>{const deck=(data.allDecks||[]).find(d=>String(d.id)===String(row.id||row.deck_id||''));return activeDivision&&String(row.division_id||deck?.division_id||'')===String(activeDivision.id);}).map((row,index)=>({...row,position:index+1}));
      rankingNode.innerHTML = ranking.length ? ranking.map((row, index) => {
        const teamName = row.team || 'Sin equipo';
        const deckName = row.name || 'Deck';
        const logoUrl = row.logo || row.team_logo || row.logo_url || row.cover_url || '';
        const initials = teamName.split(/\s+/).filter(Boolean).slice(0, 3).map((part) => part[0]).join('').toUpperCase();
        const emblem = logoUrl
          ? `<img loading="lazy" decoding="async" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(teamName)}">`
          : `<span>${escapeHtml(initials || 'EQ')}</span>`;
        return `
        <article class="event-ranking-row" data-ranking-deck-id="${Number(row.id||row.deck_id||0)}">
          <b class="event-ranking-position">#${row.position || index + 1}</b>
          <span class="event-ranking-emblem">${emblem}</span>
          <span class="event-ranking-identity"><strong>${escapeHtml(teamName)}</strong><small>${escapeHtml(deckName)}</small>${row.group ? `<small>Grupo ${Number(row.group)} · puesto ${Number(row.group_position)}</small>` : ''}</span>
          <strong class="event-ranking-points">${row.pts || 0}<small>pts</small></strong>
          <span class="event-ranking-stats">
            <small><b>${row.pj || 0}</b>PJ</small><small><b>${row.pg || row.wins || 0}</b>PG</small><small><b>${row.pp || row.losses || 0}</b>PP</small><small><b>${row.cg || 0}</b>CG</small><small><b>${row.cp || 0}</b>CP</small><small><b>${row.pe || row.draws || 0}</b>E</small><small><b>${row.dp || 0}</b>Dif</small>
            ${row.buchholz !== undefined ? `<small><b>${Number(row.buchholz)}</b>Buchholz</small>` : ''}
          </span>
        </article>
      `;
      }).join('') : '<p class="note">Todavia no hay resultados persistidos para esta fase.</p>';
      if(byDivision){rankingNode.insertAdjacentHTML('afterbegin',`<div class="event-ranking-division-title"><h3>${escapeHtml(activeDivision?.name||'Sin divisiones registradas')}</h3><p>${ranking.length} equipos · Posiciones de esta división</p></div>`);}
    }
    const scheduleModalTitle = eventScheduleModalNode ? eventScheduleModalNode.querySelector('[data-event-schedule-modal-title]') : null;
    const scheduleModalMeta = eventScheduleModalNode ? eventScheduleModalNode.querySelector('[data-event-schedule-modal-meta]') : null;
    if (scheduleModalTitle) scheduleModalTitle.textContent = ['cup', 'final'].includes(String(competition.type || '')) ? `Llave de ${competition.name || 'copa'}` : `Duelos de ${competition.name || 'evento'}`;
    if (scheduleModalMeta) scheduleModalMeta.textContent = `${competition.code || ''} | ${competition.starts_on || 'Sin fecha'} | ${schedulePatternLabel(competition.schedule_pattern) || 'Sin programar'}`;
    const pendingNode = document.querySelector('[data-competition-config-pending-matches]');
    if (pendingNode) {
      const competitionMatches = allMatchList().filter((match) => String(match.competition_id || '') === String(competition.id || ''));
      const pending = competitionMatches.filter((match) => !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')));
      const params = new URLSearchParams(window.location.search);
      const saved = params.get('saved') || '';
      const savedMatch = saved.match(/^evento_configurado_(\d+)$/);
      const isCurrentSavedEvent = savedMatch && String(savedMatch[1] || '') === String(competition.id || '');
      const sortedPending = pending.slice().sort((left, right) =>
        String(left.scheduled_on || '9999-12-31').localeCompare(String(right.scheduled_on || '9999-12-31'))
        || Number(left.ronda || 0) - Number(right.ronda || 0)
        || Number(left.mesa || 0) - Number(right.mesa || 0)
      );
      const sortedMatches = competitionMatches.slice().sort((left, right) =>
        String(left.scheduled_on || '9999-12-31').localeCompare(String(right.scheduled_on || '9999-12-31'))
        || Number(left.ronda || 0) - Number(right.ronda || 0)
        || Number(left.mesa || 0) - Number(right.mesa || 0)
      );
      const success = isCurrentSavedEvent && competitionMatches.length > 0
        ? `<div class="schedule-programmed-alert"><i class="bi bi-check-circle"></i><div><strong>Programacion guardada: ${competitionMatches.length} combates.</strong><span>Estos cruces quedaron persistidos en base de datos y se muestran como fechas tentativas hasta comenzar el evento.</span></div></div>`
        : '';
      if (['cup', 'final'].includes(String(competition.type || ''))) {
        pendingNode.innerHTML = renderCupBracket(competitionMatches, competition, success);
        window.StationXCupTree?.mount(pendingNode);
      } else {
        pendingNode.innerHTML = renderDivisionSchedule(sortedMatches, competition, success);
      }
      pendingNode.querySelectorAll('[data-schedule-division]').forEach(button=>button.addEventListener('click',()=>{
        pendingNode.querySelectorAll('[data-schedule-division]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));
        pendingNode.querySelectorAll('[data-schedule-division-panel]').forEach(panel=>panel.hidden=panel.dataset.scheduleDivisionPanel!==button.dataset.scheduleDivision);
        const scroll=eventScheduleModalNode?.querySelector('.modal-body'); if(scroll)scroll.scrollTop=0;
      }));
      pendingNode.querySelectorAll('[data-schedule-open-match]:not(.cup-tree-node)').forEach((button) => {
        button.addEventListener('click', () => {
          if (eventScheduleModal) eventScheduleModal.hide();
          window.setTimeout(() => openMatchSheet(button.dataset.scheduleOpenMatch), 180);
        });
      });
    }
    const dependenciesNode = competitionConfigModal.querySelector('[data-competition-config-dependencies]');
    if (dependenciesNode) {
      const dependencies = Array.isArray(competition.dependencies) ? competition.dependencies : [];
      dependenciesNode.innerHTML = dependencies.length ? dependencies.map((dependency) => `
        <article class="${dependency.ok ? 'ready' : 'blocked'}">
          <span>${dependency.name || dependency.depends_on_name || 'Evento previo'}</span>
          <b>${dependency.ok ? 'OK' : 'Pendiente'}</b>
          <small>${dependency.condition_type || 'completed_with_results'} | ${dependency.status || dependency.depends_on_status || 'pending_configuration'}</small>
        </article>
      `).join('') : '<p class="note">Sin prerequisitos activos.</p>';
    }
    updateCompetitionConfigVisibility();
    updateCompetitionConfigCount();
    updateCompetitionDeckConstraints();
    updateCompetitionCancelledLock(competition.status);
    return true;
  }
