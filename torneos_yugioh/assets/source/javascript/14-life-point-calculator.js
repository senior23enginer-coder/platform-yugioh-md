/**
 * life-point-calculator: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function formatResultLp(value) {
    return new Intl.NumberFormat('es-CO').format(Math.max(0, Math.round(Number(value) || 0)));
  }

  function calculateStandardStep(state, key) {
    return window.StationXLpWorkspace.step(state, key);
  }

  function renderResultStandardCalculator() {
    const display = resultCaptureModalNode?.querySelector('[data-result-standard-display]');
    if (display) display.textContent = resultStandardState.display;
    const expression = resultCaptureModalNode?.querySelector('[data-result-standard-expression]');
    if (expression) expression.textContent = resultStandardState.expression || '0';
  }

  function formatResultLpTimer(seconds) {
    const value = Math.max(0, Math.floor(Number(seconds) || 0));
    return [Math.floor(value / 3600), Math.floor((value % 3600) / 60), value % 60].map((part) => String(part).padStart(2, '0')).join(':');
  }

  function resultLpStorageKey(duel) {
    return `dml_result_lp_${activeResultCaptureMatchId || 'match'}_${duel || 1}`;
  }

  function stopResultLpTimer() {
    if (resultLpTimerTick) window.clearInterval(resultLpTimerTick);
    resultLpTimerTick = null;
  }

  function saveResultLpState() {
    if (!resultLpState) return;
    localStorage.setItem(resultLpStorageKey(resultLpState.duel), JSON.stringify({ ...resultLpState, running: false }));
  }

  function applyResultLpStateToDuel(syncLive = true) {
    if (!resultLpState || !resultCaptureModalNode) return;
    const lpA = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${resultLpState.duel}"]`);
    const lpB = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${resultLpState.duel}"]`);
    const beforeA = Number(lpA?.value), beforeB = Number(lpB?.value);
    if (lpA) lpA.value = String(Math.max(0, Math.round(resultLpState.a)));
    if (lpB) lpB.value = String(Math.max(0, Math.round(resultLpState.b)));
    void window.StationXLpSound?.change(beforeA, beforeB, Number(lpA?.value), Number(lpB?.value));
    saveResultLpState();
    setResultSaveState('pending');
    syncResultCaptureSummary();
    if (syncLive) scheduleJudgeLiveSync();
  }

  function renderResultLpCalculator() {
    const calculator = resultCaptureModalNode?.querySelector('[data-result-lp-calculator]');
    if (!calculator || !resultLpState) return;
    ['a', 'b'].forEach((side) => {
      const value = calculator.querySelector(`[data-result-lp-value="${side}"]`);
      const sideButton = calculator.querySelector(`[data-result-lp-side="${side}"]`);
      if (value) value.textContent = formatResultLp(resultLpState[side]);
      if (sideButton) sideButton.classList.toggle('active', resultLpState.side === side);
    });
    const entry = calculator.querySelector('[data-result-lp-entry]');
    if (entry) entry.textContent = formatResultLp(Number(resultLpState.entry || 0));
    const timer = calculator.querySelector('[data-result-lp-timer]');
    if (timer) timer.textContent = formatResultLpTimer(resultLpState.timer);
    const timerButton = calculator.querySelector('[data-result-lp-timer-toggle]');
    if (timerButton) timerButton.innerHTML = resultLpState.running ? '<i class="bi bi-pause-fill"></i>' : '<i class="bi bi-play-fill"></i>';
    ['a', 'b'].forEach((side) => {
      const start = calculator.querySelector(`[data-result-lp-start="${side}"]`);
      if (start && document.activeElement !== start) start.value = String(resultLpState[`start_${side}`] || 8000);
    });
    const history = calculator.querySelector('[data-result-lp-history]');
    if (history) {
      history.innerHTML = resultLpState.history.length
        ? resultLpState.history.slice(0, 6).map((item) => `<span><b>${escapeHtml(item.name)}</b> ${escapeHtml(item.action)} <strong>${formatResultLp(item.before)} â†’ ${formatResultLp(item.after)}</strong></span>`).join('')
        : 'Sin movimientos registrados.';
    }
    saveResultLpState();
  }

  function openResultLpCalculator(duel) {
    const calculator = resultCaptureModalNode?.querySelector('[data-result-lp-calculator]');
    const match = selectedCaptureMatch();
    const openButton = resultCaptureModalNode?.querySelector(`[data-result-lp-open="${duel}"]`);
    if (!calculator || !match || openButton?.disabled) return;
    const lpAInput = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${duel}"]`);
    const lpBInput = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${duel}"]`);
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(resultLpStorageKey(duel)) || '{}'); } catch (error) { saved = {}; }
    resultLpState = {
      duel,
      a: lpAInput?.value !== '' ? Math.max(0, Number(lpAInput.value)) : (Number.isFinite(Number(saved.a)) ? Math.max(0, Number(saved.a)) : 8000),
      b: lpBInput?.value !== '' ? Math.max(0, Number(lpBInput.value)) : (Number.isFinite(Number(saved.b)) ? Math.max(0, Number(saved.b)) : 8000),
      side: ['a', 'b'].includes(saved.side) ? saved.side : 'a',
      entry: '',
      timer: Math.max(0, Number(saved.timer || 0)),
      running: false,
      history: Array.isArray(saved.history) ? saved.history.slice(0, 100) : []
      ,start_a: Math.max(0, Number(saved.start_a || 8000))
      ,start_b: Math.max(0, Number(saved.start_b || 8000))
    };
    resultStandardState = { display: '0', accumulator: null, operator: '', replace: true };
    const duelLabel = calculator.querySelector('[data-result-lp-duel]');
    const nameA = calculator.querySelector('[data-result-lp-name="a"]');
    const nameB = calculator.querySelector('[data-result-lp-name="b"]');
    if (duelLabel) duelLabel.textContent = `D${duel}`;
    if (nameA) nameA.textContent = match.team_a || match.a || 'Equipo A';
    if (nameB) nameB.textContent = match.team_b || match.b || 'Equipo B';
    calculator.hidden = false;
    calculator.scrollTop = 0;
    renderResultLpCalculator();
    renderResultStandardCalculator();
  }

  function closeResultLpCalculator() {
    window.StationXLpWorkspace?.close();
    const calculator = resultCaptureModalNode?.querySelector('[data-result-lp-calculator]');
    if (resultLpState?.running) resultLpState.running = false;
    stopResultLpTimer();
    saveResultLpState();
    if (calculator) calculator.hidden = true;
  }

  async function offerResultLpVictory() {
    const match = selectedCaptureMatch();
    if (!match || !resultLpState) return;
    const duel = resultLpState.duel, matchId = String(match.id);
    const lp = side => Number(resultCaptureModalNode.querySelector(`[data-result-capture-lp-${side}="${duel}"]`)?.value);
    const winner = lp('a') === 0 && lp('b') > 0 ? 'B' : lp('b') === 0 && lp('a') > 0 ? 'A' : '';
    const control = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${duel}"]`);
    const runtime = duelTimerState.get(duel);
    if (!winner || !control || control.disabled || control.value || !runtime?.duelStarted) return;
    const name = window.StationXParticipants?.participant(match, winner) || matchSideLabel(match, winner.toLowerCase());
    const loser = winner === 'A' ? 'B' : 'A';
    const loserName = window.StationXParticipants?.participant(match, loser) || matchSideLabel(match, loser.toLowerCase());
    const accepted = await window.StationXLpWorkspace.confirmVictory({text: `${loserName} quedó en 0 LP. ¿Deseas otorgar D${duel} a ${name} y guardar esta partida por puntos de vida?`});
    if (!accepted || String(activeResultCaptureMatchId) !== matchId || resultLpState?.duel !== duel || control.disabled || control.value) return;
    const stillWinner = lp('a') === 0 && lp('b') > 0 ? 'B' : lp('b') === 0 && lp('a') > 0 ? 'A' : '';
    if (stillWinner !== winner) return;
    runtime.baseElapsed = duelTimerElapsed(runtime); runtime.running = false; runtime.startedAt = null;
    duelTimerState.set(duel, runtime); persistDuelTimer(duel);
    control.value = winner;
    resultCaptureModalNode.querySelector(`[data-result-capture-method="${duel}"]`).value = 'life_points';
    setResultSaveState('pending'); syncResultCaptureSummary(); closeResultLpCalculator();
    const form = resultCaptureModalNode.querySelector('[data-result-capture-form]');
    const submit = form?.querySelector('[data-result-capture-save]');
    if (form && submit && !submit.disabled) form.requestSubmit(submit);
  }

  function changeResultLp(operation) {
    if (!resultLpState) return;
    const side = resultLpState.side;
    const before = Math.max(0, Number(resultLpState[side]) || 0);
    const amount = Math.max(0, Number(resultLpState.entry || 0));
    let after = before;
    let action = '';
    if (operation === 'subtract' && amount) { after = Math.max(0, before - amount); action = `-${formatResultLp(amount)}`; }
    if (operation === 'add' && amount) { after = before + amount; action = `+${formatResultLp(amount)}`; }
    if (operation === 'half') { after = Math.floor(before / 2); action = 'Mitad'; }
    if (operation === 'reset') { after = Number(resultLpState[`start_${side}`] || 8000); action = 'Reiniciar'; }
    if (!action) return;
    const match = selectedCaptureMatch();
    resultLpState[side] = after;
    resultLpState.history.unshift({ side, name: side === 'a' ? (match?.team_a || 'Equipo A') : (match?.team_b || 'Equipo B'), operation, amount, action, before, after });
    resultLpState.history = resultLpState.history.slice(0, 100);
    resultLpState.entry = '';
    renderResultLpCalculator();
    applyResultLpStateToDuel();
    if (before > 0 && after === 0 && ['subtract', 'half'].includes(operation)) void offerResultLpVictory();
  }

  if (resultCaptureModalNode) {
    resultCaptureModalNode.querySelector('[data-modify-captured-match]')?.addEventListener('click', () => {
      window.clearTimeout(liveDuelSyncTimer);
      window.dispatchEvent(new CustomEvent('dml:modify-match', {detail:{matchId:activeResultCaptureMatchId,mode:'full',parentId:'resultCaptureModal',draft:{duels:captureDuelDetails(),values:Array.from({length:5},(_,i)=>resultCaptureModalNode.querySelector(`[data-result-capture-duel="${i+1}"]`)?.value||''),notes:resultCaptureModalNode.querySelector('[data-result-capture-notes]')?.value||''}}}));
    });
    window.addEventListener('dml:resume-result', () => {
      resultCaptureModal.show();
      localStorage.setItem('dml_result_capture_match_id',String(activeResultCaptureMatchId));
      window.clearInterval(liveDuelPollTimer);syncJudgeLiveDuel(false);liveDuelPollTimer=window.setInterval(()=>syncJudgeLiveDuel(false),1800);
    });
    resultCaptureModalNode.addEventListener('shown.bs.modal', () => {
      resultCaptureModalNode.scrollTop = 0;
      const dialog = resultCaptureModalNode.querySelector('.result-capture-dialog');
      const body = resultCaptureModalNode.querySelector('.result-console-body');
      if (dialog) dialog.scrollTop = 0;
      if (body) body.scrollTop = 0;
    });
    resultCaptureModalNode.addEventListener('hidden.bs.modal', () => {
      closeResultLpCalculator();
      const terminals = resultCaptureModalNode.querySelector('[data-result-terminals-sheet]');
      if (terminals) {
        terminals.classList.remove('is-open');
        terminals.setAttribute('aria-hidden', 'true');
      }
      resultCaptureModalNode.querySelectorAll('[data-result-outcome-sheet]').forEach((sheet) => {
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
      });
      const historySheet = resultCaptureModalNode.querySelector('[data-result-history-sheet]');
      if (historySheet) {
        historySheet.classList.remove('is-open');
        historySheet.setAttribute('aria-hidden', 'true');
      }
      window.clearInterval(liveDuelPollTimer);
      window.clearTimeout(liveDuelSyncTimer);
      liveDuelPollTimer = null;
      localStorage.removeItem('dml_result_capture_match_id');
      localStorage.removeItem('dml_result_capture_route');
    });
    resultCaptureModalNode.querySelectorAll('[data-result-player-copy]').forEach((button) => button.addEventListener('click', async () => {
      const url = button.dataset.url || '';
      if (!url) return;
      try {
        await navigator.clipboard.writeText(url);
        setLiveDuelStatus(`Enlace del lado ${button.dataset.resultPlayerCopy} copiado`, 'online');
      } catch (error) {
        window.prompt('Copia este enlace:', url);
      }
    }));
    const terminalsSheet = resultCaptureModalNode.querySelector('[data-result-terminals-sheet]');
    const setTerminalsOpen = (open) => {
      if (!terminalsSheet) return;
      terminalsSheet.classList.toggle('is-open', open);
      terminalsSheet.setAttribute('aria-hidden', open ? 'false' : 'true');
    };
    resultCaptureModalNode.querySelector('[data-result-terminals-open]')?.addEventListener('click', () => setTerminalsOpen(true));
    resultCaptureModalNode.querySelectorAll('[data-result-terminals-close]').forEach((button) => button.addEventListener('click', () => setTerminalsOpen(false)));
    const setOutcomeOpen = (duel, open) => {
      resultCaptureModalNode.querySelectorAll('[data-result-outcome-sheet]').forEach((sheet) => {
        const selected = open && Number(sheet.dataset.resultOutcomeSheet || 0) === Number(duel);
        sheet.classList.toggle('is-open', selected);
        sheet.setAttribute('aria-hidden', selected ? 'false' : 'true');
      });
    };
    resultCaptureModalNode.querySelectorAll('[data-result-outcome-open]').forEach((button) => button.addEventListener('click', () => {
      if (!button.disabled) setOutcomeOpen(Number(button.dataset.resultOutcomeOpen || 0), true);
    }));
    resultCaptureModalNode.querySelectorAll('[data-result-outcome-close]').forEach((button) => button.addEventListener('click', () => setOutcomeOpen(Number(button.dataset.resultOutcomeClose || 0), false)));
    const historySheet = resultCaptureModalNode.querySelector('[data-result-history-sheet]');
    const setHistoryOpen = (open) => {
      if (!historySheet) return;
      historySheet.classList.toggle('is-open', open);
      historySheet.setAttribute('aria-hidden', open ? 'false' : 'true');
    };
    resultCaptureModalNode.querySelector('[data-result-history-open]')?.addEventListener('click', () => setHistoryOpen(true));
    resultCaptureModalNode.querySelectorAll('[data-result-history-close]').forEach((button) => button.addEventListener('click', () => setHistoryOpen(false)));
    resultCaptureModalNode.querySelectorAll('[data-result-capture-duel], [data-result-capture-method], [data-result-capture-lp-a], [data-result-capture-lp-b], [data-result-capture-deck-a], [data-result-capture-deck-b], [data-result-capture-player-a], [data-result-capture-player-b], [data-result-capture-turns], [data-result-capture-brick-a], [data-result-capture-brick-b], [data-result-capture-origin], [data-result-capture-duel-note], [data-result-capture-notes]').forEach((control) => {
      const changed = () => {
        setResultSaveState('pending');
        syncResultCaptureSummary();
        scheduleJudgeLiveSync();
      };
      control.addEventListener('input', changed);
      control.addEventListener('change', changed);
    });
    resultCaptureModalNode.querySelectorAll('[data-result-capture-method]').forEach((control) => control.addEventListener('change', () => {
      const duel = Number(control.dataset.resultCaptureMethod || 0);
      if (control.value === 'surrender' && !validateSurrenderTurn(duel, true)) {
        control.value = '';
        syncResultCaptureSummary();
        return;
      }
      if (control.value !== 'time_limit') return;
      finishDuelByTime(duel);
    }));
    resultCaptureModalNode.querySelectorAll('[data-result-lp-open]').forEach((button) => {
      button.addEventListener('click', () => openResultLpCalculator(Number(button.dataset.resultLpOpen || 1)));
    });
    resultCaptureModalNode.querySelectorAll('[data-result-lp-close]').forEach((button) => button.addEventListener('click', closeResultLpCalculator));
    resultCaptureModalNode.querySelectorAll('[data-result-lp-side]').forEach((button) => {
      button.addEventListener('click', () => {
        if (!resultLpState) return;
        resultLpState.side = button.dataset.resultLpSide || 'a';
        resultLpState.entry = '';
        renderResultLpCalculator();
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-lp-key]').forEach((button) => {
      button.addEventListener('click', () => {
        if (!resultLpState) return;
        resultLpState.entry = `${resultLpState.entry || ''}${button.dataset.resultLpKey || ''}`.replace(/^0+(?=\d)/, '').slice(0, 6);
        renderResultLpCalculator();
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-lp-op]').forEach((button) => {
      button.addEventListener('click', () => {
        const operation = button.dataset.resultLpOp || '';
        if (!resultLpState) return;
        if (operation === 'clear') {
          resultLpState.entry = '';
          renderResultLpCalculator();
          return;
        }
        if (operation === 'undo') {
          const previous = resultLpState.history.shift();
          if (previous && ['a', 'b'].includes(previous.side)) resultLpState[previous.side] = previous.before;
          renderResultLpCalculator();
          applyResultLpStateToDuel();
          return;
        }
        changeResultLp(operation);
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-standard-key]').forEach((button) => {
      button.addEventListener('click', () => {
        resultStandardState = calculateStandardStep(resultStandardState, button.dataset.resultStandardKey || '');
        renderResultStandardCalculator();
      });
    });
    const resultStandardUse = resultCaptureModalNode.querySelector('[data-result-standard-use]');
    if (resultStandardUse) resultStandardUse.addEventListener('click', () => {
      if (!resultLpState) return;
      const value = Number(resultStandardState.display);
      if (!Number.isFinite(value)) {
        window.alert('La operacion no tiene un resultado valido.');
        return;
      }
      resultLpState.entry = String(Math.max(0, Math.min(99999, Math.round(Math.abs(value)))));
      renderResultLpCalculator();
      window.StationXLpWorkspace.close();
    });
    const resultLpApply = resultCaptureModalNode.querySelector('[data-result-lp-apply]');
    if (resultLpApply) resultLpApply.addEventListener('click', () => {
      if (!resultLpState) return;
      applyResultLpStateToDuel();
      closeResultLpCalculator();
    });
    const resultLpStartApply = resultCaptureModalNode.querySelector('[data-result-lp-start-apply]');
    if (resultLpStartApply) resultLpStartApply.addEventListener('click', () => {
      if (!resultLpState || resultLpState.history.length) return;
      ['a', 'b'].forEach((side) => {
        const input = resultCaptureModalNode.querySelector(`[data-result-lp-start="${side}"]`);
        const start = Math.max(1, Number(input?.value || 8000));
        resultLpState[`start_${side}`] = start;
        resultLpState[side] = start;
      });
      renderResultLpCalculator();
    });
    const resultLpTimerToggle = resultCaptureModalNode.querySelector('[data-result-lp-timer-toggle]');
    if (resultLpTimerToggle) resultLpTimerToggle.addEventListener('click', () => {
      if (!resultLpState) return;
      resultLpState.running = !resultLpState.running;
      stopResultLpTimer();
      if (resultLpState.running) {
        resultLpTimerTick = window.setInterval(() => {
          if (!resultLpState?.running) return;
          resultLpState.timer += 1;
          renderResultLpCalculator();
        }, 1000);
      }
      renderResultLpCalculator();
    });
    const resultLpTimerReset = resultCaptureModalNode.querySelector('[data-result-lp-timer-reset]');
    if (resultLpTimerReset) resultLpTimerReset.addEventListener('click', () => {
      if (!resultLpState) return;
      resultLpState.timer = 0;
      resultLpState.running = false;
      stopResultLpTimer();
      renderResultLpCalculator();
    });
    const rollButton = resultCaptureModalNode.querySelector('[data-result-roll-dice]');
    if (rollButton) rollButton.addEventListener('click', () => {
      setResultSaveState('pending');
      const openingRoll = rollOpeningDice();
      const { a, b, winner, attempts } = openingRoll;
      const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
      const diceSummary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
      const panel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
      const question = resultCaptureModalNode.querySelector('[data-result-turn-choice-question]');
      const roll = { a, b, winner, attempts, rerolls: Math.max(0, attempts.length - 1), rolled_at: new Date().toISOString() };
      const turnInput = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
      if (turnInput) turnInput.value = '';
      if (diceInput) diceInput.value = JSON.stringify(roll);
      if (diceSummary) diceSummary.textContent = 'Lanzando dados...';
      if (panel) panel.hidden = true;
      const d1DiceA = resultCaptureModalNode.querySelector('[data-result-duel-dice-a="1"]');
      const d1DiceB = resultCaptureModalNode.querySelector('[data-result-duel-dice-b="1"]');
      if (d1DiceA) {
        d1DiceA.textContent = '-';
        d1DiceA.dataset.value = '';
      }
      if (d1DiceB) {
        d1DiceB.textContent = '-';
        d1DiceB.dataset.value = '';
      }
      updateResultDie('[data-result-die-a]', 1, true);
      updateResultDie('[data-result-die-b]', 1, true);
      window.setTimeout(() => {
        updateResultDie('[data-result-die-a]', a, false);
        updateResultDie('[data-result-die-b]', b, false);
        if (d1DiceA) {
          d1DiceA.textContent = String(a);
          d1DiceA.dataset.value = String(a);
        }
        if (d1DiceB) {
          d1DiceB.textContent = String(b);
          d1DiceB.dataset.value = String(b);
        }
        if (diceSummary) diceSummary.textContent = `A saco ${a}, B saco ${b}. Gano ${winner}. Falta elegir primero o segundo.`;
        if (question) question.textContent = `${winner} gano el dado. Que elige?`;
        if (panel) panel.hidden = false;
        scheduleJudgeLiveSync();
      }, 1250);
    });
    resultCaptureModalNode.querySelectorAll('[data-result-turn-select]').forEach((button) => {
      button.addEventListener('click', () => {
        const dice = parseJsonValue(resultCaptureModalNode.querySelector('[data-result-capture-dice-json]')?.value || '', null);
        if (!dice || !dice.winner) return;
        const choice = button.dataset.resultTurnSelect === 'second' ? 'segundo' : 'primero';
        const starts = choice === 'primero' ? dice.winner : (dice.winner === 'A' ? 'B' : 'A');
        const text = `${dice.winner} eligio ${choice}. Empieza ${starts}.`;
        const input = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
        const summary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
        const panel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
        const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
        const updatedDice = { ...dice, choice, starts, chosen_by: dice.winner, choice_recorded_at: new Date().toISOString() };
        if (input) input.value = text;
        if (diceInput) diceInput.value = JSON.stringify(updatedDice);
        const firstTurnInput = resultCaptureModalNode.querySelector('[data-result-duel-turn-choice="1"]');
        if (firstTurnInput) firstTurnInput.value = JSON.stringify({ chosen_by: dice.winner, choice, starts, reason: 'dice' });
        if (summary) summary.textContent = `A saco ${dice.a}, B saco ${dice.b}. ${text}`;
        if (panel) panel.hidden = true;
        setResultSaveState('pending');
        syncResultCaptureSummary();
        scheduleJudgeLiveSync();
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-duel-turn-select]').forEach((button) => {
      button.addEventListener('click', () => {
        const n = Number(button.dataset.resultDuelTurnSelect || 0);
        if (!n) return;
        const details = captureDuelDetails();
        const previousWinner = n > 1 ? details[n - 2]?.winner || '' : '';
        const dice = parseJsonValue(resultCaptureModalNode.querySelector('[data-result-capture-dice-json]')?.value || '', null);
        const chosenBy = n === 1
          ? (dice?.chosen_by || dice?.winner || '')
          : (previousWinner === 'A' ? 'B' : previousWinner === 'B' ? 'A' : previousWinner === 'E' ? (details[n - 1]?.dice_winner || details[n - 1]?.turn_chosen_by || '') : '');
        if (!chosenBy) return;
        const choice = normalizeTurnChoice(button.dataset.choice || '');
        const starts = computeStartsPlayer(chosenBy, choice);
        const hidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`);
        if (hidden) hidden.value = JSON.stringify({ chosen_by: chosenBy, dice_winner: previousWinner === 'E' ? chosenBy : '', choice, starts, reason: n === 1 ? 'dice' : (previousWinner === 'E' ? 'draw_reroll' : 'previous_duel_loss'), recorded_at: new Date().toISOString() });
        setResultSaveState('pending');
        if (n === 1) {
          const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
          const turnInput = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
          const summary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
          const panel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
          const updatedDice = { ...(dice || {}), chosen_by: chosenBy, choice, starts, choice_recorded_at: new Date().toISOString() };
          if (diceInput) diceInput.value = JSON.stringify(updatedDice);
          if (turnInput) turnInput.value = turnText(chosenBy, choice, starts);
          if (summary && dice) summary.textContent = `A saco ${dice.a}, B saco ${dice.b}. ${turnText(chosenBy, choice, starts)}`;
          if (panel) panel.hidden = true;
        }
        syncResultCaptureSummary();
        scheduleJudgeLiveSync();
      });
    });
    duelTimerConfirmNode?.querySelector('[data-duel-start-without-timer]')?.addEventListener('click', () => {
      const duel = pendingTimerDuel;
      closeDuelTimerConfirm();
      if (duel) beginDuel(duel, 'none');
    });
    duelTimerConfirmNode?.querySelector('[data-duel-start-with-timer]')?.addEventListener('click', () => {
      const duel = pendingTimerDuel;
      closeDuelTimerConfirm();
      if (duel) beginDuel(duel, 'countdown');
    });
    duelTimerConfirmNode?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => button.addEventListener('click', closeDuelTimerConfirm));
    const form = resultCaptureModalNode.querySelector('[data-result-capture-form]');
    if (form) form.addEventListener('submit', (event) => {
      const invalidSurrender = Array.from({ length: 5 }, (_, index) => index + 1).some((duel) => !validateSurrenderTurn(duel, false));
      if (invalidSurrender) {
        event.preventDefault();
        window.alert('No se guardo: una rendicion fue registrada fuera del turno del jugador que se rinde.');
        setResultSaveState('pending');
        return;
      }
      syncResultCaptureSummary();
      setResultSaveState('saving');
      // Los resultados cerrados permanecen bloqueados en pantalla, pero deben viajar en el POST.
      form.querySelectorAll('[data-result-capture-duel]:disabled').forEach((control) => {
        control.disabled = false;
      });
      if (!navigator.onLine) {
        event.preventDefault();
        const requestId = makeRequestId();
        const formData = new FormData(form);
        formData.set('offline_request_id', requestId);
        const payload = Array.from(formData.entries()).map(([key, value]) => [key, String(value)]);
        const queue = readOfflineQueue();
        queue.push({ id: requestId, payload, queuedAt: new Date().toISOString(), attempts: 0 });
        writeOfflineQueue(queue);
        setResultSaveState('pending');
        window.alert(`Sin conexion. El cambio quedo guardado en este dispositivo. Hay ${queue.length} ${queue.length === 1 ? 'envio pendiente' : 'envios pendientes'}.`);
      }
    });
  }

  syncResultRoundOptions();

  const qualifierMatchSearch = document.querySelector('[data-qualifier-match-search]');
  const qualifierMatchStatus = document.querySelector('[data-qualifier-match-status]');
  const qualifierMatchRound = document.querySelector('[data-qualifier-match-round]');
  const qualifierMatchOrder = document.querySelector('[data-qualifier-match-order]');
  const qualifierMatchList = document.querySelector('[data-qualifier-match-list]');
  const qualifierClearFilters = document.querySelector('[data-qualifier-clear-filters]');
  const qualifierRankingOrder = document.querySelector('[data-qualifier-ranking-order]');
  const qualifierRankingBody = document.querySelector('[data-qualifier-ranking-body]');
  const qualifierScreen = document.querySelector('[data-screen="clasificatorio"]');
  const selectedQualifierEventId = String(qualifierScreen?.dataset.qualifierEventId || '');
  const matchSheetModalNode = document.querySelector('[data-match-sheet-modal]');
  const matchSheetModal = matchSheetModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(matchSheetModalNode) : null;
  const qualifierDeckSheetModalNode = document.querySelector('[data-qualifier-deck-sheet-modal]');
  const qualifierDeckSheetModal = qualifierDeckSheetModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(qualifierDeckSheetModalNode) : null;
  let activeSheetMatchId = '';
  let activeQualifierDeckName = '';

