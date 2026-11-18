/**
 * live-duel-state: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function captureDuelDetails() {
    if (!resultCaptureModalNode) return [];
    return Array.from({ length: 5 }, (_, index) => {
      const n = index + 1;
      const turnChoice = parseJsonValue(resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`)?.value || '', {});
      const diceA = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${n}"]`)?.dataset.value || '';
      const diceB = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${n}"]`)?.dataset.value || '';
      const duelState = duelTimerState.get(n) || {};
      let lpState = {};
      try { lpState = JSON.parse(localStorage.getItem(resultLpStorageKey(n)) || '{}'); } catch (error) { lpState = {}; }
      return {
        duel: n,
        winner: resultCaptureModalNode.querySelector(`[data-result-capture-duel="${n}"]`)?.value || '',
        defeat_method: resultCaptureModalNode.querySelector(`[data-result-capture-method="${n}"]`)?.value || '',
        lp_a: resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${n}"]`)?.value || '',
        lp_b: resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${n}"]`)?.value || '',
        lp_start_a: Number(lpState.start_a || 8000),
        lp_start_b: Number(lpState.start_b || 8000),
        lp_history: Array.isArray(lpState.history) ? lpState.history : [],
        deck_a_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-deck-a="${n}"]`)?.value || 0),
        deck_b_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-deck-b="${n}"]`)?.value || 0),
        player_a_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-player-a="${n}"]`)?.value || 0),
        player_b_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-player-b="${n}"]`)?.value || 0),
        turn_count: Number(resultCaptureModalNode.querySelector(`[data-result-capture-turns="${n}"]`)?.value || 0),
        brick_a: Boolean(resultCaptureModalNode.querySelector(`[data-result-capture-brick-a="${n}"]`)?.checked),
        brick_b: Boolean(resultCaptureModalNode.querySelector(`[data-result-capture-brick-b="${n}"]`)?.checked),
        duration_seconds: duelTimerElapsed(Object.keys(duelState).length ? duelState : { baseElapsed: Number(resultCaptureModalNode.querySelector(`[data-result-capture-duration="${n}"]`)?.value || 0) }),
        timer_running: Boolean(duelState.running),
        timer_started_at: duelState.startedAt || null,
        timer_updated_at: duelState.updatedAt || Date.now(),
        duel_started: Boolean(duelState.duelStarted),
        duel_started_at: duelState.duelStartedAt || null,
        data_origin: resultCaptureModalNode.querySelector(`[data-result-capture-origin="${n}"]`)?.value || (duelState.duelStarted ? 'captured' : ''),
        timer_mode: duelState.timerMode || '',
        turn_history: Array.isArray(duelState.turnHistory) ? duelState.turnHistory : [],
        notes: resultCaptureModalNode.querySelector(`[data-result-capture-duel-note="${n}"]`)?.value || '',
        dice_a: diceA,
        dice_b: diceB,
        dice_winner: turnChoice.dice_winner || turnChoice.chosen_by || '',
        turn_chosen_by: turnChoice.chosen_by || '',
        turn_decision: turnChoice.choice || '',
        starts_player: turnChoice.starts || '',
        manual_correction_at_ms: Number((parseJsonValue(selectedCaptureMatch()?.duel_details||'[]',[]).find(d=>Number(d.duel)===n)||{}).manual_correction_at_ms||0),
        turn_reason: turnChoice.reason || '',
        surrendered_by: duelState.surrenderedBy || '',
        surrendered_at: duelState.surrenderedAt || ''
      };
    });
  }

  const duelTimerState = new Map();

  function currentLiveDuelNumber() {
    const match = selectedCaptureMatch();
    const persistedCount = Array.isArray(match?.duels) ? match.duels.filter((value) => ['A', 'B', 'E'].includes(String(value || ''))).length : 0;
    return Math.max(1, Math.min(5, persistedCount + 1));
  }

  function liveDuelUrl() {
    return `./?api=duel_session&match_id=${encodeURIComponent(activeResultCaptureMatchId || '')}`;
  }

  function setLiveDuelStatus(message, mode = '') {
    const status = resultCaptureModalNode?.querySelector('[data-result-live-sync]');
    if (!status) return;
    status.textContent = message;
    status.dataset.state = mode;
  }

  function judgeLiveDuelPayload() {
    const duel = currentLiveDuelNumber();
    const detail = captureDuelDetails().find((item) => Number(item.duel) === duel) || {};
    const runtime = duelTimerState.get(duel) || {};
    const starts = inferStartsPlayer(detail.starts_player, detail.turn_history);
    const turns = Math.max(0, Number(detail.turn_count || 0));
    const activeSide = starts ? (turns % 2 === 0 ? starts : oppositePlayer(starts)) : '';
    return {
      duel_number: duel,
      correction_at_ms: Math.max(0, ...parseJsonValue(selectedCaptureMatch()?.duel_details || '[]', []).map(d => Number(d.manual_correction_at_ms || 0))),
      status: runtime.duelStarted ? (runtime.timerMode === 'countdown' && !runtime.running ? 'paused' : 'running') : (starts ? 'ready' : 'setup'),
      starts_player: starts,
      active_side: activeSide,
      turn_count: turns,
      turn_history: Array.isArray(detail.turn_history) ? detail.turn_history : [],
      lp_a: detail.lp_a === '' ? 8000 : Number(detail.lp_a || 0),
      lp_b: detail.lp_b === '' ? 8000 : Number(detail.lp_b || 0),
      lp_start_a: Number(detail.lp_start_a || 8000),
      lp_start_b: Number(detail.lp_start_b || 8000),
      lp_history: Array.isArray(detail.lp_history) ? detail.lp_history : [],
      timer_mode: runtime.timerMode || '',
      timer_running: Boolean(runtime.running),
      timer_started_at: Number(runtime.startedAt || 0),
      elapsed_seconds: runtime.running ? Math.max(0, Number(runtime.baseElapsed || 0)) : duelTimerElapsed(runtime),
      dice_a: Number(detail.dice_a || 0),
      dice_b: Number(detail.dice_b || 0),
      dice_winner: detail.dice_winner || detail.turn_chosen_by || '',
      turn_decision: detail.turn_decision || '',
      surrendered_by: runtime.surrenderedBy || '',
      surrendered_at: runtime.surrenderedAt || ''
    };
  }

  function applyLiveDuelState(state) {
    if (!state || !resultCaptureModalNode) return;
    const loadedCorrection=Math.max(0,...parseJsonValue(selectedCaptureMatch()?.duel_details||'[]',[]).map(d=>Number(d.manual_correction_at_ms||0)));
    if(Number(state.correction_at_ms||0)>loadedCorrection){localStorage.setItem('dml_result_capture_match_id',String(activeResultCaptureMatchId));location.reload();return;}
    const duel = Number(state.duel_number || 0);
    if (!duel || duel !== currentLiveDuelNumber()) return;
    applyingLiveDuelState = true;
    try {
      const lpA = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${duel}"]`);
      const lpB = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${duel}"]`);
      const localTurnInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${duel}"]`);
      const localLpA = lpA?.value === '' ? 8000 : Number(lpA?.value || 0);
      const localLpB = lpB?.value === '' ? 8000 : Number(lpB?.value || 0);
      const localTurnCount = Math.max(0, Number(localTurnInput?.value || 0));
      const remoteChanged = Number(state.turn_count || 0) > localTurnCount
        || Number(state.lp_a ?? 8000) !== localLpA
        || Number(state.lp_b ?? 8000) !== localLpB;
      if (lpA) lpA.value = String(Math.max(0, Number(state.lp_a ?? 8000)));
      if (lpB) lpB.value = String(Math.max(0, Number(state.lp_b ?? 8000)));
      const runtime = duelTimerState.get(duel) || {};
      const localHistory = Array.isArray(runtime.turnHistory) ? runtime.turnHistory : [];
      const remoteHistory = Array.isArray(state.turn_history) ? state.turn_history : [];
      if (remoteHistory.length >= localHistory.length) runtime.turnHistory = remoteHistory;
      const recoveredStarts = inferStartsPlayer(state.starts_player, remoteHistory);
      const turnChoiceNode = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`);
      const turnChoice = parseJsonValue(turnChoiceNode?.value || '', {});
      if (turnChoiceNode && recoveredStarts && !turnChoice.starts) {
        turnChoiceNode.value = JSON.stringify({ ...turnChoice, starts: recoveredStarts, reason: turnChoice.reason || 'turn_history' });
      }
      runtime.turnBaseline = Math.max(Number(runtime.turnBaseline || 0), Number(state.turn_count || 0) - remoteHistory.length);
      runtime.baseElapsed = Math.max(Number(runtime.baseElapsed || 0), Number(state.elapsed_seconds || 0));
      runtime.running = Boolean(state.timer_running);
      runtime.startedAt = Number(state.timer_started_at || 0) || null;
      runtime.timerMode = state.timer_mode || runtime.timerMode || '';
      if (runtime.timerMode !== 'countdown') { runtime.running = false; runtime.startedAt = null; }
      runtime.surrenderedBy = ['A', 'B'].includes(String(state.surrendered_by || '')) ? String(state.surrendered_by) : '';
      runtime.surrenderedAt = state.surrendered_at || runtime.surrenderedAt || '';
      const remoteStarted = ['running', 'paused', 'completed'].includes(String(state.status || ''));
      const localStarts = inferStartsPlayer(turnChoice.starts, runtime.turnHistory);
      runtime.duelStarted = remoteStarted || (Boolean(runtime.duelStarted) && Boolean(localStarts));
      const origin = resultCaptureModalNode.querySelector(`[data-result-capture-origin="${duel}"]`);
      if (origin && remoteStarted) origin.value = 'captured';
      if (!runtime.duelStarted) {
        runtime.running = false;
        runtime.startedAt = null;
        runtime.timerMode = '';
        runtime.duelStartedAt = null;
      }
      duelTimerState.set(duel, runtime);
      if (runtime.surrenderedBy) {
        const winner = runtime.surrenderedBy === 'A' ? 'B' : 'A';
        const winnerControl = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${duel}"]`);
        const methodControl = resultCaptureModalNode.querySelector(`[data-result-capture-method="${duel}"]`);
        if (winnerControl) winnerControl.value = winner;
        if (methodControl) methodControl.value = 'surrender';
        runtime.running = false;
        runtime.startedAt = null;
      }
      persistDuelTimer(duel, true);
      if (localTurnInput) localTurnInput.value = String(Math.max(Number(localTurnInput.value || 0), Number(state.turn_count || 0)));
      localStorage.setItem(resultLpStorageKey(duel), JSON.stringify({
        a: Number(state.lp_a ?? 8000), b: Number(state.lp_b ?? 8000),
        start_a: Number(state.lp_start_a || 8000), start_b: Number(state.lp_start_b || 8000),
        history: Array.isArray(state.lp_history) ? state.lp_history : [], running: false
      }));
      if (remoteChanged) setResultSaveState('pending');
      syncResultCaptureSummary();
    } finally {
      applyingLiveDuelState = false;
    }
  }

  async function requestLiveDuel(operation = '', payload = {}) {
    if (!activeResultCaptureMatchId) return null;
    const requestedMatchId = String(activeResultCaptureMatchId);
    const options = { cache: 'no-store', headers: { Accept: 'application/json' } };
    if (operation) {
      const body = new FormData();
      body.set('match_id', activeResultCaptureMatchId);
      body.set('actor', 'judge');
      body.set('operation', operation);
      body.set('revision', String(liveDuelRevision || 0));
      body.set('payload', JSON.stringify(payload));
      options.method = 'POST';
      options.body = body;
    }
    const response = await fetch(liveDuelUrl(), options);
    const data = await response.json();
    if (String(activeResultCaptureMatchId) !== requestedMatchId) return null;
    if (response.status === 409 && data.state) {
      liveDuelRevision = Number(data.revision || liveDuelRevision);
      applyLiveDuelState(data.state);
      return data;
    }
    if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo sincronizar el duelo.');
    liveDuelRevision = Number(data.revision || liveDuelRevision);
    return data;
  }

  async function syncJudgeLiveDuel(force = false) {
    if (!activeResultCaptureMatchId || applyingLiveDuelState) return;
    if (liveDuelBusy) {
      if (force) window.setTimeout(() => syncJudgeLiveDuel(true), 260);
      return;
    }
    liveDuelBusy = true;
    try {
      if (force) {
        const saved = await requestLiveDuel('sync', judgeLiveDuelPayload());
        if (saved?.state) applyLiveDuelState(saved.state);
      } else {
        const current = await requestLiveDuel();
        if (current?.state) applyLiveDuelState(current.state);
      }
      setLiveDuelStatus(`Sincronizado · v${liveDuelRevision}`, 'online');
    } catch (error) {
      setLiveDuelStatus('Sin conexión con las pantallas', 'error');
    } finally {
      liveDuelBusy = false;
    }
  }

  function scheduleJudgeLiveSync() {
    if (applyingLiveDuelState || !activeResultCaptureMatchId) return;
    window.clearTimeout(liveDuelSyncTimer);
    liveDuelSyncTimer = window.setTimeout(() => syncJudgeLiveDuel(true), 180);
  }
