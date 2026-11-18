/**
 * results-dashboard: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function renderResult() {
    const match = selectedMatch();
    const values = duelSelects.map((select) => select.value);
    if (match && !match.id) localStorage.setItem(resultKey(match), JSON.stringify(values));
    const result = evaluateDuel(values, match);
    const timing = matchTimingStatus(match);
    if (resultSaveButton) {
      resultSaveButton.disabled = !timing.canCapture;
      resultSaveButton.title = timing.canCapture ? '' : `Bloqueado: ${timing.label}`;
    }
    const timingText = timing.canCapture ? timing.label : `${timing.label} | no ejecutable`;
    resultSummary.textContent = `${timingText} | ${result.state} | Marcador ${result.score}${result.winner ? ` | Resultado ${result.winner}` : ''} | Validacion ${result.validation}`;
    renderStandings();
    renderStats();
    renderProfile();
    renderResultDashboard(filteredResultMatches());
  }

  if (resultDivision) resultDivision.addEventListener('change', () => { syncResultRoundOptions(); loadMatchOptions(); });
  if (resultMatch) resultMatch.addEventListener('change', loadResult);
  [resultPhase, resultEventFilter, resultLeagueDivisionFilter].forEach((control) => {
    if (control) control.addEventListener('change', () => { syncResultRoundOptions(); loadMatchOptions(); });
  });
  resultPeriod?.addEventListener('change', () => {
    if (resultPeriod.value === 'day' && !resultDate.value) resultDate.value = serverToday;
    if (resultPeriod.value === 'month' && !resultMonth.value) resultMonth.value = serverToday.slice(0, 7);
    syncResultPeriodFields();
    loadMatchOptions();
  });
  [resultStatus, resultScheduleFilter, resultRoundFilter, resultSort, resultDate, resultMonth, resultDateFrom, resultDateTo, resultSearch].forEach((control) => {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', () => { syncResultPeriodFields(); loadMatchOptions(); });
  });
  resultClearFilters?.addEventListener('click', () => {
    if (resultPhase) resultPhase.value = '';
    if (resultEventFilter) resultEventFilter.value = '';
    if (resultLeagueDivisionFilter) resultLeagueDivisionFilter.value = '';
    if (resultStatus) resultStatus.value = '';
    if (resultScheduleFilter) resultScheduleFilter.value = 'all';
    if (resultRoundFilter) resultRoundFilter.value = '';
    if (resultSort) resultSort.value = 'desc';
    if (resultDate) resultDate.value = '';
    if (resultPeriod) resultPeriod.value = 'all';
    [resultMonth, resultDateFrom, resultDateTo].forEach((control) => { if (control) control.value = ''; });
    syncResultPeriodFields();
    if (resultSearch) resultSearch.value = '';
    syncResultRoundOptions();
    loadMatchOptions();
  });
  duelSelects.forEach((select) => select.addEventListener('change', renderResult));
  resultCaptureModalNode?.addEventListener('click', (event) => {
    const reroll = event.target.closest('[data-result-duel-reroll]');
    if (reroll) {
      event.preventDefault();
      const duel = Number(reroll.dataset.resultDuelReroll || 0);
      if (duel <= 1) return;
      const details = captureDuelDetails();
      if (details[duel - 2]?.winner !== 'E') return;
      const roll = rollOpeningDice();
      const diceA = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${duel}"]`);
      const diceB = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${duel}"]`);
      const hidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`);
      if (diceA) { diceA.textContent = String(roll.a); diceA.dataset.value = String(roll.a); }
      if (diceB) { diceB.textContent = String(roll.b); diceB.dataset.value = String(roll.b); }
      if (hidden) hidden.value = JSON.stringify({ chosen_by: roll.winner, dice_winner: roll.winner, choice: '', starts: '', reason: 'draw_reroll', rolled_at: new Date().toISOString() });
      const runtime = duelTimerState.get(duel) || {};
      runtime.duelStarted = false;
      runtime.running = false;
      runtime.startedAt = null;
      runtime.timerMode = '';
      duelTimerState.set(duel, runtime);
      persistDuelTimer(duel);
      setResultSaveState('pending');
      syncResultCaptureSummary();
      scheduleJudgeLiveSync();
      return;
    }
    const begin = event.target.closest('[data-result-duel-begin]');
    if (begin) {
      event.preventDefault();
      const duel = Number(begin.dataset.resultDuelBegin || 0);
      if (duel) askDuelTimer(duel);
      return;
    }
    const endTurn = event.target.closest('[data-result-turn-end]');
    if (endTurn) {
      event.preventDefault();
      const duel = Number(endTurn.dataset.resultTurnEnd || 0);
      const state = duelTimerState.get(duel);
      const turnChoice = parseJsonValue(resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`)?.value || '', {});
      const recoveredStarts = inferStartsPlayer(turnChoice.starts, state?.turnHistory);
      if (!state?.duelStarted || !recoveredStarts) return;
      if (!turnChoice.starts) {
        turnChoice.starts = recoveredStarts;
        turnChoice.reason = turnChoice.reason || 'turn_history';
        const turnChoiceNode = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`);
        if (turnChoiceNode) turnChoiceNode.value = JSON.stringify(turnChoice);
      }
      state.turnHistory = Array.isArray(state.turnHistory) ? state.turnHistory : [];
      const turnInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${duel}"]`);
      const persistedCount = Math.max(0, Number(turnInput?.value || 0));
      const completed = Math.max(persistedCount, Math.max(0, Number(state.turnBaseline || 0)) + state.turnHistory.length);
      const side = completed % 2 === 0 ? recoveredStarts : oppositePlayer(recoveredStarts);
      state.turnHistory.push({ turn: completed + 1, side, ended_at: new Date().toISOString(), elapsed_seconds: duelTimerElapsed(state) });
      if (turnInput) turnInput.value = String(completed + 1);
      duelTimerState.set(duel, state);
      persistDuelTimer(duel);
      setResultSaveState('pending');
      syncResultCaptureSummary();
      scheduleJudgeLiveSync();
      return;
    }
    const toggle = event.target.closest('[data-result-timer-toggle]');
    if (toggle) {
      event.preventDefault();
      const duel = Number(toggle.dataset.resultTimerToggle || 0);
      const state = duelTimerState.get(duel) || { baseElapsed: 0, running: false, warned: false, startedAt: null };
      if (state.running) {
        state.baseElapsed = duelTimerElapsed(state);
        state.running = false;
        state.startedAt = null;
        duelTimerState.set(duel, state);
        persistDuelTimer(duel);
        renderDuelTimer(duel);
      } else {
        startDuelTimer(duel);
      }
      setResultSaveState('pending');
      scheduleJudgeLiveSync();
    }
  });
  const clearResult = document.querySelector('[data-clear-result]');
  if (clearResult) clearResult.addEventListener('click', () => {
    duelSelects.forEach((select) => { select.value = ''; });
    syncSequentialDuelControls(duelSelects, []);
    renderResult();
  });

  function syncResultRoundOptions() {
    syncResultEventOptions();
    syncResultLeagueDivisionOptions();
    if (!resultRoundFilter) return;
    const current = resultRoundFilter.value;
    const phase = resultPhase ? resultPhase.value : '';
    const rounds = Array.from(new Set(resultBaseMatches()
      .filter(resultLeagueDivisionMatches)
      .filter(match => !resultEventFilter?.value || String(match.competition_id) === resultEventFilter.value)
      .filter((match) => {
        if (!phase) return true;
        const matchPhase = String(match.phase || '').toLowerCase();
        if (phase === 'events') return ['event', 'events', 'special', 'eternal', 'tiebreaker', 'final'].includes(matchPhase);
        return matchPhase === phase;
      })
      .map((match) => Number(match.ronda || 0))
      .filter(Boolean))).sort((a, b) => a - b);
    resultRoundFilter.innerHTML = '<option value="">Todas las rondas</option>' + rounds.map((round) => `<option value="${round}">Ronda ${round}</option>`).join('');
    if (rounds.some((round) => String(round) === String(current))) {
      resultRoundFilter.value = current;
    }
  }

  function renderResultDashboard(matches) {
    if (resultKpiTotal) resultKpiTotal.textContent = String(matches.length);
    if (resultKpiPending) resultKpiPending.textContent = String(matches.filter((match) => match.estado === 'PENDIENTE').length);
    if (resultKpiProgress) resultKpiProgress.textContent = String(matches.filter((match) => ['EN CURSO', 'REQUIERE D5'].includes(match.estado)).length);
    if (resultKpiDone) resultKpiDone.textContent = String(matches.filter((match) => match.estado === 'FINALIZADA').length);
    if (resultKpiOverdue) resultKpiOverdue.textContent = String(matches.filter((match) => matchTimingStatus(match).key === 'overdue').length);
    if (resultKpiToday) resultKpiToday.textContent = String(matches.filter((match) => matchTimingStatus(match).key === 'today').length);
    if (resultKpiFuture) resultKpiFuture.textContent = String(matches.filter((match) => matchTimingStatus(match).key === 'future').length);
    if (resultFilterCount) resultFilterCount.textContent = `${matches.length} visibles`;
    renderOperationalAlerts();
    if (!resultQueue) return;
    if (!matches.length) {
      resultQueue.innerHTML = '<tr><td colspan="5"><div class="empty-state">No hay partidas con los filtros actuales.</div></td></tr>';
      return;
    }
    resultQueue.innerHTML = matches.map((match) => {
      const timing = matchTimingStatus(match);
      const state = String(match.estado || 'PENDIENTE').toUpperCase();
      const actionLabel = state === 'FINALIZADA' ? 'Revisar' : (state.includes('CURSO') ? 'Continuar' : 'Capturar');
      const actionIcon = state === 'FINALIZADA' ? 'bi-eye' : (state.includes('CURSO') ? 'bi-play-circle' : 'bi-pencil-square');
      return `
      <tr class="${String(resultMatch?.value || '') === String(match.id) ? 'selected' : ''} timing-${timing.key}">
        <td data-label="Programacion"><div class="result-schedule-cell"><strong>${match.scheduled_on || 'Sin fecha'}<span>${match.starts_at ? match.starts_at.slice(0, 5) : 'Sin hora'}</span></strong><div class="result-event-context">${resultMatchEventContext(match)}<small>Ronda ${match.ronda} · Mesa ${match.mesa} | ${String(match.match_format || '1v1').toUpperCase()} | BO${match.best_of || 5}</small></div><span class="status-chip ${timing.className}">${timing.label}</span></div></td>
        <td data-label="Enfrentamiento"><div class="result-versus-cell"><div>${resultTeamLogoCell(match, 'a')}</div><b>VS</b><div>${resultTeamLogoCell(match, 'b')}</div></div></td>
        <td data-label="Estado"><span class="status-chip">${match.estado || 'PENDIENTE'}</span></td>
        <td data-label="Marcador"><strong class="result-score-value">${match.score || '0-0'}</strong></td>
        <td data-label="Accion" class="result-queue-action"><button class="btn btn-outline-info btn-sm" type="button" data-result-pick="${match.id}"><i class="bi ${actionIcon}"></i> ${actionLabel}</button></td>
      </tr>`;
    }).join('');
    Array.from(resultQueue.querySelectorAll('[data-result-pick]')).forEach((button) => {
      button.addEventListener('click', () => {
        if (resultMatch) resultMatch.value = button.dataset.resultPick;
        loadResult();
        openResultCapture(button.dataset.resultPick);
      });
    });
  }

