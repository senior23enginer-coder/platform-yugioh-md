/**
 * duel-timer: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function duelTimerStorageKey(duel) {
    return window.StationXDuelTimerPolicy.storageKey(activeResultCaptureMatchId, duel);
  }
  function duelTimerElapsed(state) {
    return window.StationXDuelTimerPolicy.elapsed(state, () => Date.now());
  }
  function persistDuelTimer(duel, liveSynced = false) {
    const state = duelTimerState.get(duel);
    window.StationXDuelTimerPolicy.persist(
      state, () => duelTimerStorageKey(duel), liveSynced,
      () => Date.now(), (key, value) => localStorage.setItem(key, value)
    );
  }
  function closeDuelTimerConfirm() {
    if (!duelTimerConfirmNode) return;
    duelTimerConfirmNode.classList.remove('show');
    duelTimerConfirmNode.style.display = 'none';
    duelTimerConfirmNode.setAttribute('aria-hidden', 'true');
    pendingTimerDuel = 0;
  }
  function askDuelTimer(duel) {
    if (!duelTimerConfirmNode || !duel) return;
    const state = duelTimerState.get(duel);
    if (state?.duelStarted) return;
    pendingTimerDuel = duel;
    const title = duelTimerConfirmNode.querySelector('[data-duel-start-confirm-title]');
    const copy = duelTimerConfirmNode.querySelector('[data-duel-start-confirm-copy]');
    if (title) title.textContent = `¿Comenzar D${duel}?`;
    if (copy) copy.textContent = 'Al confirmar comenzara el registro de turnos. Elige ahora si usaras el limite de 20 minutos; esta decision no podra cambiarse durante el duelo.';
    duelTimerConfirmNode.classList.add('show');
    duelTimerConfirmNode.style.display = 'block';
    duelTimerConfirmNode.removeAttribute('aria-hidden');
  }
  function startDuelTimer(duel) {
    const state = duelTimerState.get(duel) || { baseElapsed: 0, running: false, warned: false, startedAt: null };
    if (!state.duelStarted || state.timerMode !== 'countdown') return;
    state.duelStarted = true;
    state.timerMode = 'countdown';
    state.duelStartedAt = state.duelStartedAt || Date.now();
    state.turnHistory = Array.isArray(state.turnHistory) ? state.turnHistory : [];
    state.baseElapsed = duelTimerElapsed(state);
    state.running = state.baseElapsed < 1200;
    state.startedAt = state.running ? Date.now() : null;
    duelTimerState.set(duel, state);
    persistDuelTimer(duel);
    renderDuelTimer(duel);
  }
  function beginDuel(duel, timerMode) {
    const state = duelTimerState.get(duel) || { baseElapsed: 0, running: false, warned: false, startedAt: null };
    if (state.duelStarted) return;
    const turnChoice = parseJsonValue(resultCaptureModalNode?.querySelector(`[data-result-duel-turn-choice="${duel}"]`)?.value || '', {});
    const starts = inferStartsPlayer(turnChoice.starts, state.turnHistory);
    if (!['A', 'B'].includes(String(turnChoice.chosen_by || '').toUpperCase())
      || !['primero', 'segundo'].includes(normalizeTurnChoice(turnChoice.choice || ''))
      || !['A', 'B'].includes(starts)) {
      state.duelStarted = false;
      state.running = false;
      state.startedAt = null;
      state.timerMode = '';
      duelTimerState.set(duel, state);
      persistDuelTimer(duel);
      syncResultCaptureSummary();
      return;
    }
    state.duelStarted = true;
    state.duelStartedAt = Date.now();
    state.timerMode = timerMode === 'countdown' ? 'countdown' : 'none';
    state.turnHistory = [];
    state.turnBaseline = 0;
    const turnCountInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${duel}"]`);
    if (turnCountInput) turnCountInput.value = '0';
    state.baseElapsed = 0;
    state.running = state.timerMode === 'countdown';
    state.startedAt = state.running ? Date.now() : null;
    duelTimerState.set(duel, state);
    persistDuelTimer(duel);
    const origin = resultCaptureModalNode?.querySelector(`[data-result-capture-origin="${duel}"]`);
    if (origin) origin.value = 'captured';
    setResultSaveState('pending');
    syncResultCaptureSummary();
    scheduleJudgeLiveSync();
  }
  function soundDuelAlarm(repeats = 1) {
    try {
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      const context = new AudioContextClass();
      for (let index = 0; index < repeats; index += 1) {
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.frequency.value = index % 2 ? 880 : 660;
        gain.gain.value = 0.12;
        oscillator.connect(gain); gain.connect(context.destination);
        oscillator.start(context.currentTime + index * 0.22);
        oscillator.stop(context.currentTime + index * 0.22 + 0.15);
      }
    } catch (error) {}
  }
  function renderDuelTimer(duel) {
    const duration = resultCaptureModalNode?.querySelector(`[data-result-capture-duration="${duel}"]`);
    const state = duelTimerState.get(duel);
    const elapsed = state ? duelTimerElapsed(state) : Math.max(0, Number(duration?.value || 0));
    if (duration) duration.value = String(elapsed);
    const remaining = Math.max(0, 1200 - elapsed);
    const clock = resultCaptureModalNode?.querySelector(`[data-result-duel-clock="${duel}"]`);
    const alarm = resultCaptureModalNode?.querySelector(`[data-result-duel-alarm="${duel}"]`);
    const toggle = resultCaptureModalNode?.querySelector(`[data-result-timer-toggle="${duel}"]`);
    const timerMode = state?.timerMode || '';
    const timerPanel = resultCaptureModalNode?.querySelector(`[data-result-duel-timer="${duel}"]`);
    if (timerPanel) timerPanel.classList.toggle('is-disabled', timerMode === 'none');
    if (timerMode === 'none') {
      if (clock) clock.textContent = 'Sin reloj';
      if (alarm) alarm.textContent = 'El duelo comenzo sin temporizador.';
      if (toggle) toggle.hidden = true;
      return;
    }
    if (toggle) toggle.hidden = false;
    if (clock) clock.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    if (clock) clock.classList.toggle('is-warning', remaining <= 300 && remaining > 0);
    if (clock) clock.classList.toggle('is-expired', remaining === 0);
    if (alarm && remaining === 0) alarm.textContent = 'Tiempo finalizado. Si ambos conservan LP, el duelo termina en empate.';
    if (toggle) toggle.innerHTML = state?.running ? '<i class="bi bi-pause-fill"></i> Pausar reloj' : '<i class="bi bi-play-fill"></i> Reanudar reloj';
  }

  function renderDuelTurnTracker(duel, startsPlayer, editable) {
    const state = duelTimerState.get(duel) || {};
    const history = Array.isArray(state.turnHistory) ? state.turnHistory : [];
    const legacyCount = Math.max(0, Number(resultCaptureModalNode?.querySelector(`[data-result-capture-turns="${duel}"]`)?.value || 0));
    const baseline = Number.isFinite(Number(state.turnBaseline)) ? Math.max(0, Number(state.turnBaseline)) : Math.max(0, legacyCount - history.length);
    const completed = Math.max(legacyCount, baseline + history.length);
    const recoveredStarts = inferStartsPlayer(startsPlayer, history);
    const currentSide = recoveredStarts ? (completed % 2 === 0 ? recoveredStarts : oppositePlayer(recoveredStarts)) : '';
    const match = selectedCaptureMatch();
    const current = resultCaptureModalNode?.querySelector(`[data-result-turn-current="${duel}"]`);
    const player = resultCaptureModalNode?.querySelector(`[data-result-turn-player="${duel}"]`);
    const total = resultCaptureModalNode?.querySelector(`[data-result-turn-total="${duel}"]`);
    const input = resultCaptureModalNode?.querySelector(`[data-result-capture-turns="${duel}"]`);
    const endButton = resultCaptureModalNode?.querySelector(`[data-result-turn-end="${duel}"]`);
    const historyNode = resultCaptureModalNode?.querySelector(`[data-result-turn-history="${duel}"]`);
    if (current) current.textContent = `Turno ${completed + 1}`;
    if (player) player.textContent = currentSide ? `${currentSide === 'A' ? matchLabelWithTeam(match?.a || 'Equipo A') : matchLabelWithTeam(match?.b || 'Equipo B')} (${currentSide})` : 'Esperando inicio';
    if (total) total.textContent = `${completed} ${completed === 1 ? 'turno completado' : 'turnos completados'}`;
    if (input) input.value = String(completed);
    if (endButton) {
      endButton.disabled = !editable || !state.duelStarted || !currentSide;
      endButton.innerHTML = `<i class="bi bi-check2-circle"></i> Terminar turno de ${currentSide || '-'}`;
    }
    if (historyNode) {
      historyNode.innerHTML = history.length
        ? history.slice(-6).map((entry) => `<span>T${escapeHtml(entry.turn)} · ${escapeHtml(entry.side)}</span>`).join('')
        : '<small>Aun no hay turnos completados.</small>';
    }
  }
  function finishDuelByTime(duel, automatic = false) {
    const state = duelTimerState.get(duel) || {};
    if (automatic && (!state.duelStarted || state.timerMode !== 'countdown')) return;
    state.baseElapsed = duelTimerElapsed(state);
    state.running = false;
    state.startedAt = null;
    duelTimerState.set(duel, state);
    persistDuelTimer(duel);
    // Reaching the time limit never decides a winner or a draw.
    const method = resultCaptureModalNode?.querySelector(`[data-result-capture-method="${duel}"]`);
    if (method && !automatic) method.value = 'time_limit';
    const alarm = resultCaptureModalNode?.querySelector(`[data-result-duel-alarm="${duel}"]`);
    if (alarm) alarm.textContent = 'Tiempo finalizado. Confirma el resultado en Registrar resultado; no se asigna empate automaticamente.';
    if (automatic) soundDuelAlarm(3);
    syncResultCaptureSummary(); renderDuelTimer(duel);
  }
  window.setInterval(() => {
    duelTimerState.forEach((state, duel) => {
      if (!state.duelStarted || state.timerMode !== 'countdown' || !state.running) return;
      const duration = resultCaptureModalNode?.querySelector(`[data-result-capture-duration="${duel}"]`);
      if (!duration) return;
      const elapsed = duelTimerElapsed(state);
      duration.value = String(elapsed);
      const remaining = 1200 - elapsed;
      if (remaining <= 300 && remaining > 0 && !state.warned) { state.warned = true; persistDuelTimer(duel); soundDuelAlarm(2); const alarm = resultCaptureModalNode.querySelector(`[data-result-duel-alarm="${duel}"]`); if (alarm) alarm.textContent = 'Alerta: faltan 5 minutos.'; }
      if (remaining <= 0) finishDuelByTime(duel, true);
      renderDuelTimer(duel);
    });
  }, 1000);

  function validateSurrenderTurn(duel, showMessage = false) {
    const method = resultCaptureModalNode?.querySelector(`[data-result-capture-method="${duel}"]`);
    if (!method || method.value !== 'surrender') return true;
    const winner = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${duel}"]`)?.value || '';
    const detail = captureDuelDetails().find((item) => Number(item.duel) === Number(duel)) || {};
    const starts = inferStartsPlayer(detail.starts_player, detail.turn_history);
    const completedTurns = Math.max(Number(detail.turn_count || 0), Array.isArray(detail.turn_history) ? detail.turn_history.length : 0);
    const activeSide = starts ? (completedTurns % 2 === 0 ? starts : oppositePlayer(starts)) : '';
    const surrenderingSide = winner === 'A' ? 'B' : winner === 'B' ? 'A' : '';
    const valid = Boolean(detail.duel_started && activeSide && surrenderingSide && activeSide === surrenderingSide);
    if (!valid && showMessage) {
      window.alert('Solo puede rendirse el lado que tiene el turno. Selecciona al ganador correcto o espera el turno del jugador que se rinde.');
    }
    return valid;
  }

