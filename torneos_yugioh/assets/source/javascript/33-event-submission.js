/**
 * event-submission: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function submitCompetitionConfig(mode) {
    if (!competitionConfigModal) return;
    const model = (data.competitions || []).find(c => String(c.id) === String(activeCompetitionConfigId));
    if (model && ['start','schedule_qualifier','schedule_cup','save_schedule'].includes(mode) && competitionPersistedMatchCount(model.id) === 0) {
      const warnings = eventModelWarnings(model);
      if (warnings.length) { window.alert('Modelo pendiente de implementacion/configuracion: ' + warnings.join(' ')); return; }
    }
    const invalidExchange = competitionConfigModal.querySelector('[data-division-exchange]:invalid');
    if (invalidExchange) {
      bootstrap.Modal.getOrCreateInstance(document.querySelector('[data-league-movement-modal]')).show();
      invalidExchange.reportValidity();
      return;
    }
    const occupiedDeck = selectedCompetitionDecks().find(deck => participantOtherLeague(deck));
    if (occupiedDeck) { window.alert(participantAddRestriction(occupiedDeck)); return; }
    if (!compatibleParticipantRoster(selectedCompetitionDecks())) {
      window.alert('No se pueden guardar participantes de otro formato ni mezclar formatos. Retira los decks incompatibles.');
      return;
    }
    const form = competitionConfigModal.querySelector('form');
    const modeInput = competitionConfigModal.querySelector('[data-competition-config-mode]');
    if (!form || !modeInput) return;
    const normalizedMode = mode === 'status' ? 'save' : mode;
    const type = competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '';
    const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
    modeInput.value = normalizedMode;
    syncCompetitionDeckInputs();
    updateCompetitionDeckConstraints();
    if (['schedule_qualifier', 'schedule_cup'].includes(normalizedMode) && startInput && startInput.value && startInput.value < serverToday) {
      startInput.reportValidity();
      window.alert('La fecha de inicio no puede ser menor a la fecha actual.');
      return;
    }
    if (normalizedMode === 'start' && startInput && startInput.value && startInput.value > serverToday) {
      window.alert(`Este evento se podra comenzar el ${startInput.value}.`);
      return;
    }
    if (!['save', 'save_schedule', 'start'].includes(normalizedMode) && !updateCompetitionDeckConstraints()) {
      window.alert(type === 'qualifier'
        ? 'Configuracion invalida: el clasificatorio necesita minimo 4 decks y combinaciones coherentes entre match y duelos a clasificar.'
        : 'Configuracion invalida: agrega decks suficientes para organizar los combates del evento.');
      return;
    }
    const participantsPerSide = competitionParticipantsPerSide(competitionConfigModal.querySelector('[data-competition-config-match]'));
    const eligibleGroups = currentEligibleGroupCount(participantsPerSide);
    const expectedMatches = expectedQualifierMatchCount(eligibleGroups * participantsPerSide, participantsPerSide, Number(competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]')?.value || 0));
    if (normalizedMode === 'start' && type === 'qualifier' && competitionScheduledMatchCount() < expectedMatches) {
      window.alert('Primero debes programar todos los duelos y fechas tentativas del clasificatorio.');
      return;
    }
    if (normalizedMode === 'schedule_qualifier' && type === 'qualifier' && confirmScheduleQualifierModal) {
      const competitionId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
      confirmScheduleQualifierModalNode.dataset.pendingScheduleMode = 'schedule_qualifier';
      const existingMatches = allMatchList().filter((match) =>
        String(match.competition_id || '') === String(competitionId)
        && !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''))
      );
      const message = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-message]');
      const meta = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-meta]');
      if (meta) meta.textContent = existingMatches.length
        ? `Este evento ya tiene ${existingMatches.length} duelos programados.`
        : 'Este evento aun no tiene duelos programados.';
      if (message) message.textContent = existingMatches.length
        ? 'Ya se genero un calendario de duelos y fechas para este evento. Si continuas, se reemplazara el calendario anterior no finalizado y se actualizara en base de datos.'
        : 'Si continuas, se generaran los cruces y fechas tentativas del clasificatorio y quedaran guardados en base de datos.';
      confirmScheduleQualifierModal.show();
      return;
    }
    if (normalizedMode === 'schedule_cup' && ['cup', 'final'].includes(type) && confirmScheduleQualifierModal) {
      const competitionId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
      confirmScheduleQualifierModalNode.dataset.pendingScheduleMode = 'schedule_cup';
      const existingMatches = allMatchList().filter((match) =>
        String(match.competition_id || '') === String(competitionId)
        && !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''))
      );
      if (existingMatches.length) {
        window.alert('Esta copa ya fue programada. La llave existente no se puede volver a generar.');
        return;
      }
      const message = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-message]');
      const meta = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-meta]');
      if (meta) meta.textContent = 'Esta copa aun no tiene llave programada.';
      if (message) message.textContent = 'Si continuas, se generara una sola vez la llave inicial de copa, sus cruces y fechas tentativas en base de datos.';
      confirmScheduleQualifierModal.show();
      return;
    }
    if (normalizedMode === 'start' && confirmStartQualifierModal) {
      const scheduledMatches = competitionScheduledMatchCount();
      const title = confirmStartQualifierModalNode.querySelector('[data-confirm-start-title]');
      const subtitle = confirmStartQualifierModalNode.querySelector('[data-confirm-start-subtitle]');
      const message = confirmStartQualifierModalNode.querySelector('[data-confirm-start-message]');
      if (title) title.textContent = `Comenzar ${eventTypeLabel(type).toLowerCase()}`;
      if (subtitle) subtitle.textContent = 'El evento cambiara a estado activo y su configuracion quedara bloqueada.';
      if (message) message.textContent = type === 'league' && startInput?.value > serverToday && scheduledMatches > 0
        ? `La liga esta programada para el ${startInput.value}. Si confirmas, quedara activa desde ahora sin cambiar las fechas de sus combates. Esta seguro de continuar?`
        : (['cup', 'final'].includes(type) && scheduledMatches === 0
        ? 'Esta copa aun no ha sido programada. Al confirmar, el sistema generara automaticamente la llave y los combates antes de comenzar. Esta seguro de continuar?'
        : 'El evento ya tiene su programacion preparada. Esta seguro de comenzar?');
      confirmStartQualifierModal.show();
      return;
    }
    form.requestSubmit();
  }
  document.querySelectorAll('[data-competition-config-submit]').forEach((button) => {
    button.addEventListener('click', () => submitCompetitionConfig(button.dataset.competitionConfigSubmit || 'save'));
  });
  const finalizeCompetitionButton = document.querySelector('[data-competition-finalize-submit]');
  if (finalizeCompetitionButton) {
    finalizeCompetitionButton.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const actionInput = form ? form.querySelector('input[name="action"]') : null;
      if (!form || !actionInput || finalizeCompetitionButton.disabled) return;
      actionInput.value = 'finalize_competition';
      syncCompetitionDeckInputs();
      form.requestSubmit();
    });
  }
  document.querySelectorAll('[data-competition-action-submit]').forEach((button) => {
    button.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const actionInput = form ? form.querySelector('input[name="action"]') : null;
      const competitionIdInput = form ? form.querySelector('[data-competition-config-id]') : null;
      if (!form || !actionInput || !competitionIdInput?.value || button.disabled) return;
      const previewText = document.querySelector('[data-competition-action-preview]')?.innerText?.replace(/\s+/g, ' ').trim() || '';
      const actionLabel = button.textContent.trim() || 'ejecutar accion';
      if (!window.confirm(`${actionLabel}\n\n${previewText}\n\nEsta accion se guardara en base de datos. Deseas continuar?`)) return;
      actionInput.value = button.dataset.competitionActionSubmit || 'configure_competition';
      syncCompetitionDeckInputs();
      form.requestSubmit();
    });
  });
  const confirmStartQualifierSubmit = document.querySelector('[data-confirm-start-qualifier-submit]');
  if (confirmStartQualifierSubmit) {
    confirmStartQualifierSubmit.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const modeInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-mode]') : null;
      if (!form || !modeInput) return;
      const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
      modeInput.value = 'start';
      syncCompetitionDeckInputs();
      updateCompetitionDeckConstraints();
      const type = competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '';
      if (startInput && startInput.value && startInput.value > serverToday) {
        window.alert(`Este evento se podra comenzar el ${startInput.value}.`);
        return;
      }
      updateCompetitionDeckConstraints();
      const participantsPerSide = competitionParticipantsPerSide(competitionConfigModal.querySelector('[data-competition-config-match]'));
      const eligibleGroups = currentEligibleGroupCount(participantsPerSide);
      const expectedMatches = expectedQualifierMatchCount(eligibleGroups * participantsPerSide, participantsPerSide, Number(competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]')?.value || 0));
      if (type === 'qualifier' && competitionScheduledMatchCount() < expectedMatches) {
        window.alert('Primero debes programar todos los duelos y fechas tentativas del clasificatorio.');
        return;
      }
      form.requestSubmit();
    });
  }
  const confirmScheduleQualifierSubmit = document.querySelector('[data-confirm-schedule-qualifier-submit]');
  if (confirmScheduleQualifierSubmit) {
    confirmScheduleQualifierSubmit.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const modeInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-mode]') : null;
      if (!form || !modeInput) return;
      modeInput.value = confirmScheduleQualifierModalNode?.dataset?.pendingScheduleMode || 'schedule_qualifier';
      syncCompetitionDeckInputs();
      if (!updateCompetitionDeckConstraints()) {
        const currentType = competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '';
        window.alert(currentType === 'qualifier'
          ? 'Configuracion invalida: el clasificatorio necesita minimo 4 decks y combinaciones coherentes entre match y duelos a clasificar.'
          : 'Configuracion invalida: agrega decks suficientes para organizar los combates del evento.');
        return;
      }
      form.requestSubmit();
    });
  }
  function renderEventDetail(competitionId) {
    if (!eventDetail) return;
    const competition = (data.competitions || []).find((item) => String(item.id) === String(competitionId));
    if (!competition) return;
    const ranking = Array.isArray(competition.ranking) ? competition.ranking : [];
    const topCut = Array.isArray(competition.top_cut) ? competition.top_cut : ranking.slice(0, competition.type === 'world' ? 24 : 16);
    setEventDetailText('[data-event-detail-title]', competition.name || 'Ficha del evento');
    setEventDetailText('[data-event-detail-meta]', `${eventTypeLabel(competition.type)} | ${rulesetLabel(competition.event_ruleset)} | ${competition.format || 'Sistema editable'} | ${String(competition.match_format || '1v1').toUpperCase()} BO${competition.best_of || 5}`);
    setEventDetailText('[data-event-detail-pool]', poolLabel(competition.deck_pool));
    setEventDetailText('[data-event-detail-pool-count]', `${competition.deck_pool_count || 0} decks habilitados`);
    setEventDetailText('[data-event-detail-ranking-state]', competition.ranking_ready ? `${ranking.length} con resultados` : 'Pendiente');
    setEventDetailText('[data-event-detail-gate]', competition.can_execute === false || competition.can_execute === '0' ? 'Bloqueado' : 'Habilitado');
    setEventDetailText('[data-event-detail-gate-note]', competition.blocked_reason || 'Sin bloqueo activo');
    setEventDetailText('[data-event-detail-dates]', `${competition.starts_on || 'Sin inicio'} -> ${competition.ends_on || 'Sin fin'}`);
    setEventDetailText('[data-event-detail-schedule]', schedulePatternLabel(competition.schedule_pattern) || competition.schedule_strategy || 'manual');
    setEventDetailText('[data-event-detail-rule]', gameRuleLabel(competition.game_rule_id));
    setEventDetailText('[data-event-detail-lock]', competition.configuration_locked === 1 || competition.configuration_locked === '1' ? 'Fecha y frecuencia bloqueadas' : 'Pendiente comenzar');
    setEventDetailText('[data-event-detail-victory]', competition.victory_condition || 'Configurable');
    setEventDetailText('[data-event-detail-defeat]', competition.defeat_condition || 'Configurable');
    setEventDetailText('[data-event-detail-draw]', competition.draw_condition || 'Configurable');
    const rankingNode = eventDetail.querySelector('[data-event-detail-ranking]');
    if (rankingNode) {
      const rows = topCut.length ? topCut : [];
      rankingNode.innerHTML = rows.length ? rows.slice(0, 12).map((row, index) => `
        <article>
          <b>#${index + 1}</b>
          <span>${row.team || 'Sin equipo'} / ${row.name}</span>
          <small>${row.pts || 0} pts | DP ${row.dp || 0} | CG ${row.cg || 0}</small>
        </article>
      `).join('') : '<p class="note">Todavia no hay ranking deportivo para este evento. Se usara el pool configurado como preparacion.</p>';
    }
    const dependenciesNode = eventDetail.querySelector('[data-event-detail-dependencies]');
    if (dependenciesNode) {
      const dependencies = Array.isArray(competition.dependencies) ? competition.dependencies : [];
      dependenciesNode.innerHTML = dependencies.length ? dependencies.map((dependency) => `
        <article class="${dependency.ok ? 'ready' : 'blocked'}">
          <span>${dependency.name}</span>
          <b>${dependency.ok ? 'OK' : 'Pendiente'}</b>
          <small>${dependency.condition_type || 'completed_with_results'} | ${dependency.status || 'draft'} | ${dependency.result_count || 0} resultados</small>
        </article>
      `).join('') : '<p class="note">Sin prerequisitos activos.</p>';
    }
    const pendingNode = eventDetail.querySelector('[data-event-detail-pending-matches]');
    if (pendingNode) {
      const competitionMatches = allMatchList().filter((match) => String(match.competition_id || '') === String(competition.id || ''));
      const pending = competitionMatches.filter((match) => !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')));
      if (['cup', 'final'].includes(String(competition.type || ''))) {
        pendingNode.innerHTML = renderCupBracket(competitionMatches, competition);
        window.StationXCupTree?.mount(pendingNode);
        return;
      }
      const byDate = pending.reduce((map, match) => {
        const key = match.scheduled_on || 'Sin fecha';
        map[key] = map[key] || [];
        map[key].push(match);
        return map;
      }, {});
      const days = Object.keys(byDate).sort();
      pendingNode.innerHTML = days.length ? days.map((day) => `
        <article>
          <span>${day}</span>
          <b>${byDate[day].length} pendientes</b>
          <small>${byDate[day].slice(0, 3).map((match) => `Mesa ${match.mesa}: ${matchVersusLabel(match)}`).join(' | ')}</small>
        </article>
      `).join('') : '<p class="note">No hay duelos pendientes programados para este evento.</p>';
    }
    eventDetail.hidden = false;
    eventDetail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  document.querySelectorAll('[data-event-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.eventOpen;
      if (!(data.competitions || []).some((event) => String(event.id) === String(id))) return;
      setEventFichaUrl(id);
      if (fillCompetitionConfig(id)) routeTo('evento_ficha');
    });
  });
  const openEventRankingModal = document.querySelector('[data-open-event-ranking-modal]');
  if (openEventRankingModal) {
    openEventRankingModal.addEventListener('click', () => {
      if (!activeCompetitionConfigId && (data.competitions || []).length) fillCompetitionConfig(data.competitions[0].id);
      if (eventRankingModal) eventRankingModal.show();
    });
  }
  const savedReadingModalNode = document.getElementById('eventSavedReadingModal');
  const savedReadingModal = savedReadingModalNode && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(savedReadingModalNode) : null;
  document.querySelectorAll('[data-open-event-saved-reading]').forEach(button => button.addEventListener('click', () => {
    const competition = currentCompetitionConfig();
    if (!competition || !savedReadingModal) return;
    renderEventModelImpact(competition);
    savedReadingModalNode.querySelector('[data-event-saved-reading-name]').textContent = competition.name || '';
    savedReadingModal.show();
  }));
  document.querySelectorAll('[data-open-event-rule-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!activeCompetitionConfigId && (data.competitions || []).length) fillCompetitionConfig(data.competitions[0].id);
      renderCompetitionRuleSummary(currentCompetitionConfig());
      if (eventRuleModal) eventRuleModal.show();
    });
  });
  const competitionConfigRuleSelect = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-rule]') : null;
  if (competitionConfigRuleSelect) {
    competitionConfigRuleSelect.addEventListener('change', () => renderCompetitionRuleSummary(currentCompetitionConfig()));
  }
  const openEventScheduleModal = document.querySelector('[data-open-event-schedule-modal]');
  if (openEventScheduleModal) {
    openEventScheduleModal.addEventListener('click', () => {
      if (!activeCompetitionConfigId && (data.competitions || []).length) fillCompetitionConfig(data.competitions[0].id);
      if (eventScheduleModal) eventScheduleModal.show();
    });
  }
  const eventProgrammingOpen = document.querySelector('[data-event-programming-open]');
  if (eventProgrammingOpen) {
    eventProgrammingOpen.addEventListener('click', () => {
      if (eventProgrammingModal) eventProgrammingModal.show();
    });
  }
  window.addEventListener('dml:routechange', (event) => {
    if (event.detail?.target !== 'evento_ficha' || !competitionConfigModal) return;
    const currentId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
    const requestedId = requestedEventFichaId();
    if (requestedId) {
      if (!fillCompetitionConfig(requestedId)) {
        routeTo('torneos');
        const notice = document.querySelector('[data-event-open-error]');
        if (notice) { notice.hidden = false; notice.textContent = 'El evento solicitado no esta disponible. Selecciona Ver en el evento que deseas consultar.'; }
      }
    } else if (!currentId) {
      routeTo('torneos');
    } else {
      renderCompetitionDeckPicker();
    }
  });
  const eventDetailClose = document.querySelector('[data-event-detail-close]');
  if (eventDetailClose && eventDetail) eventDetailClose.addEventListener('click', () => { eventDetail.hidden = true; });

  const manualCompetitionSelect = document.querySelector('[data-manual-competition-select]');
  const manualDivisionSelect = document.querySelector('select[name="manual_division_id"]');
  const manualPhaseSelect = document.querySelector('select[name="manual_phase"]');
  const manualMatchFormatSelect = document.querySelector('select[name="manual_match_format"]');
  const manualBestOfInput = document.querySelector('input[name="manual_best_of"]');
  const manualParticipantsInput = document.querySelector('input[name="manual_participants_per_side"]');
  const manualDeckSelects = Array.from(document.querySelectorAll('[data-manual-deck-select]'));
  const manualDeckPoolNote = document.querySelector('[data-manual-deck-pool-note]');
