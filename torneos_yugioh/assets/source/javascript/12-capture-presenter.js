/**
 * capture-presenter: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function syncResultCaptureSummary() {
    if (!resultCaptureModalNode) return;
    const match = selectedCaptureMatch();
    window.StationXParticipants?.set(resultCaptureModalNode, match);
    const details = captureDuelDetails();
    const values = details.map((item) => item.winner);
    const result = evaluateDuel(values, match);
    let closingIndex = -1;
    for (let i = 0; i < values.length; i += 1) {
      const partial = values.slice(0, i + 1);
      if (evaluateDuel(partial, match).state === 'FINALIZADA') {
        closingIndex = i;
        break;
      }
    }
    const persistedFinalized = ['FINALIZADA', 'completed', 'finalizada'].includes(String(match?.estado || match?.status || ''));
    const matchClosed = result.state === 'FINALIZADA' || persistedFinalized;
    resultCaptureModalNode.classList.toggle('match-closed', matchClosed);
    const currentDuelLabel = resultCaptureModalNode.querySelector('[data-result-current-duel]');
    if (currentDuelLabel) currentDuelLabel.textContent = matchClosed ? 'Completado' : 'D1';
    const summary = resultCaptureModalNode.querySelector('[data-result-capture-summary]');
    if (summary) summary.textContent = `${result.state} | Marcador ${result.score}${result.winner ? ` | Resultado ${result.winner}` : ''} | Validacion ${result.validation}`;
    const scorePill = resultCaptureModalNode.querySelector('[data-result-capture-score-pill]');
    if (scorePill) scorePill.textContent = String(result.score || '0-0').replace('-', ' - ');
    const [scoreA = '0', scoreB = '0'] = String(result.score || '0-0').split('-');
    setResultCaptureText('[data-result-capture-score-a]', scoreA);
    setResultCaptureText('[data-result-capture-score-b]', scoreB);
    const validationTitle = resultCaptureModalNode.querySelector('[data-result-capture-validation-title]');
    if (validationTitle) validationTitle.textContent = result.validation === 'OK' ? 'Validacion OK' : 'Revisar validacion';
    const validationPill = resultCaptureModalNode.querySelector('[data-result-validation-pill]');
    if (validationPill) validationPill.classList.toggle('has-warning', result.validation !== 'OK');
    const dice = parseJsonValue(resultCaptureModalNode.querySelector('[data-result-capture-dice-json]')?.value || '', null);
    const openingTurnResolved = Boolean((dice?.a && dice?.b && dice?.winner && dice?.chosen_by && dice?.choice && dice?.starts)||(details[0]?.manual_correction_at_ms&&details[0]?.starts_player));
    resultCaptureModalNode.classList.toggle('opening-resolved', openingTurnResolved);
    const persistedDuelCount = Array.isArray(match?.duels)
      ? match.duels.filter((value) => ['A', 'B', 'E'].includes(String(value || ''))).length
      : 0;
    let completed = persistedDuelCount;
    details.forEach((detail, index) => {
      const n = index + 1;
      const card = resultCaptureModalNode.querySelector(`[data-result-duel-card="${n}"]`);
      const status = resultCaptureModalNode.querySelector(`[data-result-duel-status="${n}"]`);
      const cardSummary = resultCaptureModalNode.querySelector(`[data-result-duel-card-summary="${n}"]`);
      const lpCalculatorButton = resultCaptureModalNode.querySelector(`[data-result-lp-open="${n}"]`);
      const outcomeButton = resultCaptureModalNode.querySelector(`[data-result-outcome-open="${n}"]`);
      const ledger = resultCaptureModalNode.querySelector(`[data-result-duel-turn-ledger="${n}"]`);
      const ledgerOwner = resultCaptureModalNode.querySelector(`[data-result-duel-turn-owner="${n}"]`);
      const launch = resultCaptureModalNode.querySelector(`[data-result-duel-launch="${n}"]`);
      const beginButton = resultCaptureModalNode.querySelector(`[data-result-duel-begin="${n}"]`);
      const livePanel = resultCaptureModalNode.querySelector(`[data-result-duel-live="${n}"]`);
      const setupPanel = card?.querySelector('.result-duel-setup');
      const turnHidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`);
      const diceANode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${n}"]`);
      const diceBNode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${n}"]`);
      const rerollButton = resultCaptureModalNode.querySelector(`[data-result-duel-reroll="${n}"]`);
      const firstNode = resultCaptureModalNode.querySelector(`[data-result-duel-first="${n}"]`);
      const secondNode = resultCaptureModalNode.querySelector(`[data-result-duel-second="${n}"]`);
      const hasWinner = Boolean(detail.winner);
      const isPersistedDuel = index < persistedDuelCount;
      const isAfterClose = closingIndex >= 0 && index > closingIndex;
      const isNext = index === persistedDuelCount && !isAfterClose && !persistedFinalized;
      const canPrepareDuel = isNext;
      const winnerLabel = detail.winner === 'A' ? 'Victoria de A' : detail.winner === 'B' ? 'Victoria de B' : detail.winner === 'E' ? 'Empate' : 'Pendiente de registrar';
      const methodLabel = detail.defeat_method === 'life_points' ? 'Derrota por LP' : detail.defeat_method === 'surrender' ? 'Rendicion' : '';
      const previousWinner = n > 1 ? details[n - 2]?.winner || '' : '';
      const drawDiceReady = previousWinner === 'E' && detail.dice_a && detail.dice_b && detail.turn_reason === 'draw_reroll';
      const chooser = n === 1 ? (dice?.chosen_by || dice?.winner || '') : (previousWinner === 'A' ? 'B' : previousWinner === 'B' ? 'A' : drawDiceReady ? (detail.dice_winner || detail.turn_chosen_by || '') : '');
      const reason = n === 1 ? 'dice' : (previousWinner === 'E' ? 'draw_reroll' : 'previous_duel_loss');
      let selectedTurn = parseJsonValue(turnHidden?.value || '', {});
      const manualStart=Boolean(detail.manual_correction_at_ms&&detail.starts_player);
      if (n === 1 && dice?.chosen_by && dice?.choice && dice?.starts && !manualStart) {
        selectedTurn = { chosen_by: dice.chosen_by, choice: dice.choice, starts: dice.starts, reason };
        if (turnHidden) turnHidden.value = JSON.stringify(selectedTurn);
      }
      const invalidTurnOwner = selectedTurn.chosen_by && chooser && selectedTurn.chosen_by !== chooser;
      if ((invalidTurnOwner || (!chooser && n > 1)) && turnHidden && !manualStart) {
        turnHidden.value = '';
        selectedTurn = {};
      }
      const starts = inferStartsPlayer(
        selectedTurn.starts || computeStartsPlayer(selectedTurn.chosen_by || chooser, selectedTurn.choice || ''),
        detail.turn_history
      );
      const choiceText = manualStart?'Inicio corregido: empieza '+starts:(selectedTurn.choice && starts ? turnText(selectedTurn.chosen_by || chooser, selectedTurn.choice, starts) : '');
      const runtimeState = duelTimerState.get(n) || {};
      const requiresDrawReroll = previousWinner === 'E' && !drawDiceReady;
      const hasRecordedTurns = Array.isArray(detail.turn_history) && detail.turn_history.length > 0;
      let duelStarted = Boolean(runtimeState.duelStarted || hasWinner) && !requiresDrawReroll;
      if (duelStarted && !hasWinner && !starts && !hasRecordedTurns) {
        runtimeState.duelStarted = false;
        runtimeState.duelStartedAt = null;
        runtimeState.running = false;
        runtimeState.startedAt = null;
        runtimeState.timerMode = '';
        duelTimerState.set(n, runtimeState);
        persistDuelTimer(n);
        duelStarted = false;
      }
      const canEditDuel = canPrepareDuel && Boolean(choiceText || (starts && hasRecordedTurns)) && duelStarted;
      const methodControl = resultCaptureModalNode.querySelector(`[data-result-capture-method="${n}"]`);
      const surrenderOption = methodControl?.querySelector('option[value="surrender"]');
      const completedTurns = Math.max(Number(detail.turn_count || 0), Array.isArray(detail.turn_history) ? detail.turn_history.length : 0);
      const activeSide = starts ? (completedTurns % 2 === 0 ? starts : oppositePlayer(starts)) : '';
      const brickFieldset = resultCaptureModalNode.querySelector(`[data-result-brick-fieldset="${n}"]`);
      const brickSummary = resultCaptureModalNode.querySelector(`[data-result-brick-summary="${n}"]`);
      const brickSummaryText = resultCaptureModalNode.querySelector(`[data-result-brick-summary-text="${n}"]`);
      const brickAInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-a="${n}"]`);
      const brickBInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-b="${n}"]`);
      const brickOptionVisible = canEditDuel && !hasWinner && completedTurns < 2 && ['A', 'B'].includes(activeSide);
      const visitedBrickSides = [];
      if (duelStarted && starts) visitedBrickSides.push(starts);
      if (completedTurns >= 1 && starts) visitedBrickSides.push(oppositePlayer(starts));
      const brickSummarySides = [...new Set(visitedBrickSides)].filter((side) => hasWinner || side !== activeSide || completedTurns >= 2);
      if (brickFieldset) {
        brickFieldset.hidden = !brickOptionVisible;
        brickFieldset.querySelectorAll('[data-result-brick-side]').forEach((label) => {
          label.hidden = label.dataset.resultBrickSide !== activeSide;
        });
      }
      if (brickAInput) brickAInput.disabled = !brickOptionVisible || activeSide !== 'A';
      if (brickBInput) brickBInput.disabled = !brickOptionVisible || activeSide !== 'B';
      if (brickSummary) brickSummary.hidden = brickSummarySides.length === 0;
      if (brickSummaryText) {
        brickSummaryText.textContent = brickSummarySides.map((side) => {
          const checked = side === 'A' ? Boolean(brickAInput?.checked) : Boolean(brickBInput?.checked);
          return `${side}: ${checked ? 'Si' : 'No'}`;
        }).join(' | ');
      }
      const surrenderingSide = detail.winner === 'A' ? 'B' : detail.winner === 'B' ? 'A' : '';
      if (surrenderOption) {
        surrenderOption.disabled = !canEditDuel || !surrenderingSide || surrenderingSide !== activeSide;
        surrenderOption.textContent = surrenderOption.disabled
          ? 'Rendicion (solo en el turno del jugador)'
          : `Rendicion del lado ${surrenderingSide}`;
      }
      const detailDiceA = detail.dice_a || (n === 1 ? dice?.a || '' : '');
      const detailDiceB = detail.dice_b || (n === 1 ? dice?.b || '' : '');
      if (diceANode) {
        diceANode.textContent = detailDiceA || '-';
        diceANode.dataset.value = detailDiceA || '';
      }
      if (diceBNode) {
        diceBNode.textContent = detailDiceB || '-';
        diceBNode.dataset.value = detailDiceB || '';
      }
      if (firstNode) firstNode.textContent = starts ? `${starts === 'A' ? matchLabelWithTeam(match?.a || 'A') : matchLabelWithTeam(match?.b || 'B')} (${starts})` : 'Sin definir';
      if (secondNode) {
        const second = starts ? oppositePlayer(starts) : '';
        secondNode.textContent = second ? `${second === 'A' ? matchLabelWithTeam(match?.a || 'A') : matchLabelWithTeam(match?.b || 'B')} (${second})` : 'Sin definir';
      }
      if (card) {
        card.classList.toggle('is-complete', isPersistedDuel && hasWinner && !isAfterClose);
        card.classList.toggle('is-active', isNext);
        card.classList.toggle('is-pending', !hasWinner && !isNext && !isAfterClose && !persistedFinalized);
        card.classList.toggle('is-locked', !canPrepareDuel);
        card.dataset.duelResult = detail.winner || '';
        if (isNext) {
          const currentDuelLabel = resultCaptureModalNode.querySelector('[data-result-current-duel]');
          if (currentDuelLabel) currentDuelLabel.textContent = `D${n}`;
        }
      }
      if (status) {
        status.textContent = hasWinner
          ? (isNext ? 'Listo para guardar' : 'Completado')
          : (isAfterClose ? 'No necesario' : (persistedFinalized ? 'Cerrado' : (isNext ? (duelStarted ? 'En curso' : (choiceText ? 'Listo para comenzar' : 'Define el inicio')) : 'Pendiente')));
      }
      if (cardSummary) {
        const lpText = detail.lp_a || detail.lp_b ? ` | LP ${detail.lp_a || '0'}-${detail.lp_b || '0'}` : '';
        cardSummary.textContent = isAfterClose ? 'Match ya finalizado; duelo no necesario' : `${winnerLabel}${methodLabel ? ` | ${methodLabel}` : ''}${lpText}${choiceText ? ` | ${choiceText}` : ''}`;
      }
      if (ledger) {
        const canChoose = canPrepareDuel && Boolean(chooser) && !duelStarted && (n === 1 || Boolean(previousWinner));
        ledger.hidden = !canPrepareDuel || duelStarted || !chooser;
        ledger.classList.toggle('is-complete', Boolean(choiceText));
        ledger.classList.toggle('is-pending', canChoose && !choiceText);
        ledger.querySelectorAll('[data-result-duel-turn-select]').forEach((button) => {
          button.disabled = !canChoose;
          button.classList.toggle('active', normalizeTurnChoice(button.dataset.choice || '') === normalizeTurnChoice(selectedTurn.choice || ''));
        });
      }
      if (rerollButton) {
        rerollButton.hidden = !(canPrepareDuel && previousWinner === 'E' && !drawDiceReady);
        rerollButton.disabled = !canPrepareDuel || duelStarted;
      }
      if (launch) launch.hidden = !canPrepareDuel || !choiceText || duelStarted;
      if (beginButton) beginButton.disabled = !canPrepareDuel || !choiceText || duelStarted;
      if (livePanel) livePanel.hidden = !duelStarted;
      if (setupPanel) setupPanel.hidden = duelStarted;
      renderDuelTurnTracker(n, starts, canEditDuel);
      if (ledgerOwner) {
        if (choiceText) {
          ledgerOwner.textContent = `${n === 1 || previousWinner === 'E' ? 'Ganador del dado' : 'Perdedor del duelo anterior'}: ${choiceText}`;
        } else if (chooser) {
          ledgerOwner.textContent = `${n === 1 || previousWinner === 'E' ? 'Ganador del dado' : 'Perdedor del duelo anterior'} ${chooser}: elige si empieza o va segundo.`;
        } else {
          ledgerOwner.textContent = n === 1 ? 'Lanza el dado para definir quien elige.' : (previousWinner === 'E' ? 'El duelo anterior terminó empatado. Deben lanzar dados nuevamente.' : 'Se habilita cuando el duelo anterior tenga ganador.');
        }
      }
      ['duel', 'method', 'lp-a', 'lp-b', 'deck-a', 'deck-b', 'player-a', 'player-b', 'turns', 'brick-a', 'brick-b', 'note'].forEach((kind) => {
        const selector = kind === 'duel'
          ? `[data-result-capture-duel="${n}"]`
          : kind === 'method'
            ? `[data-result-capture-method="${n}"]`
            : kind === 'lp-a'
              ? `[data-result-capture-lp-a="${n}"]`
              : kind === 'lp-b'
                ? `[data-result-capture-lp-b="${n}"]`
                : kind === 'deck-a'
                  ? `[data-result-capture-deck-a="${n}"]`
                  : kind === 'deck-b'
                    ? `[data-result-capture-deck-b="${n}"]`
                    : kind === 'player-a'
                      ? `[data-result-capture-player-a="${n}"]`
                    : kind === 'player-b'
                      ? `[data-result-capture-player-b="${n}"]`
                      : kind === 'turns'
                        ? `[data-result-capture-turns="${n}"]`
                        : kind === 'brick-a'
                          ? `[data-result-capture-brick-a="${n}"]`
                          : kind === 'brick-b'
                            ? `[data-result-capture-brick-b="${n}"]`
                        : `[data-result-capture-duel-note="${n}"]`;
        const control = resultCaptureModalNode.querySelector(selector);
        if (control) control.disabled = !canEditDuel;
      });
      if (brickAInput) brickAInput.disabled = !brickOptionVisible || activeSide !== 'A';
      if (brickBInput) brickBInput.disabled = !brickOptionVisible || activeSide !== 'B';
      if (lpCalculatorButton) {
        lpCalculatorButton.disabled = !canEditDuel;
        lpCalculatorButton.title = canEditDuel ? `Abrir calculadora para D${n}` : (hasWinner ? 'Este duelo ya fue completado.' : 'Este duelo aun no esta habilitado.');
      }
      if (outcomeButton) {
        outcomeButton.disabled = !canEditDuel;
        outcomeButton.title = canEditDuel ? `Registrar resultado de D${n}` : 'El duelo debe estar en curso.';
      }
      resultCaptureModalNode.querySelectorAll(`[data-result-timer-toggle="${n}"]`).forEach((button) => { button.disabled = !canEditDuel || runtimeState.timerMode !== 'countdown'; });
      renderDuelTimer(n);
    });
    const rollButton = resultCaptureModalNode.querySelector('[data-result-roll-dice]');
    if (rollButton) {
      rollButton.disabled = persistedFinalized || matchClosed || openingTurnResolved;
      rollButton.title = openingTurnResolved
        ? 'La tirada inicial ya esta registrada con eleccion de turno.'
        : (persistedFinalized || matchClosed ? 'El match ya esta cerrado.' : '');
      rollButton.innerHTML = openingTurnResolved
        ? '<i class="bi bi-lock-fill"></i> Dado registrado'
        : '<i class="bi bi-dice-5"></i> Lanzar dado';
    }
    const progressText = resultCaptureModalNode.querySelector('[data-result-duel-progress-text]');
    const configuredBestOf = [1, 3, 5].includes(Number(match?.best_of)) ? Number(match.best_of) : 3;
    // Draws can extend a series under its saved rule. Never show fewer slots
    // than duels already played, and consider a closed series complete.
    const progressTotal = Math.max(configuredBestOf, completed);
    if (progressText) {
      progressText.textContent = `${completed} / ${progressTotal}`;
      progressText.title = `Duelos jugados | Mejor de ${configuredBestOf}${matchClosed ? ' | Combate finalizado' : ''}`;
    }
    const progressBar = resultCaptureModalNode.querySelector('[data-result-duel-progress-bar]');
    if (progressBar) progressBar.style.width = `${matchClosed ? 100 : Math.min(100, (completed / progressTotal) * 100)}%`;
    const notesInput = resultCaptureModalNode.querySelector('[data-result-capture-notes]');
    const notesCount = resultCaptureModalNode.querySelector('[data-result-notes-count]');
    if (notesCount) notesCount.textContent = String((notesInput?.value || '').length);
    const detailsInput = resultCaptureModalNode.querySelector('[data-result-capture-details-json]');
    if (detailsInput) detailsInput.value = JSON.stringify(closingIndex >= 0 ? details.slice(0, closingIndex + 1) : details);
    const miniList = resultCaptureModalNode.querySelector('[data-result-capture-duel-list]');
    if (miniList) {
      const firstPendingIndex = details.findIndex((detail, index) => !detail.winner && !(closingIndex >= 0 && index > closingIndex));
      miniList.innerHTML = details.map((detail, index) => {
        const n = index + 1;
        const value = detail.winner || '';
        const isPersistedDuel = index < persistedDuelCount;
        const isCurrentDraft = index === persistedDuelCount && Boolean(value) && !persistedFinalized;
        const state = isCurrentDraft ? 'Listo para guardar' : (value ? (value === 'E' ? 'Empate' : 'Finalizado') : (closingIndex >= 0 && index > closingIndex ? 'No necesario' : 'Pendiente'));
        const scoreText = value === 'A' ? 'A gana' : value === 'B' ? 'B gana' : value === 'E' ? 'Empate' : '-';
        const active = index === persistedDuelCount && !persistedFinalized;
        const locked = closingIndex >= 0 && index > closingIndex;
        const className = [isPersistedDuel && value ? 'is-complete' : '', active ? 'is-active' : '', locked ? 'is-locked' : ''].filter(Boolean).join(' ');
        return `<span class="${className}"><b>D${n}</b><i>${state}</i><strong>${scoreText}</strong>${value ? `<button class="btn btn-outline-info btn-sm" type="button" data-result-duel-read="${n}" title="Ver informacion completa de D${n}"><i class="bi bi-eye"></i> Ver</button>` : ''}</span>`;
      }).join('');
      miniList.querySelectorAll('[data-result-duel-read]').forEach((button) => button.addEventListener('click', () => openResultDuelRead(Number(button.dataset.resultDuelRead))));
    }
    const saveButton = resultCaptureModalNode.querySelector('[data-result-capture-save]');
    if (saveButton) {
      saveButton.disabled = persistedFinalized || result.validation !== 'OK';
      saveButton.innerHTML = persistedFinalized
        ? '<i class="bi bi-lock-fill"></i> Resultado cerrado'
        : '<i class="bi bi-database-check"></i> Guardar cambios';
    }
  }

  function openResultCapture(matchId) {
    if (!resultCaptureModalNode || !resultCaptureModal) return;
    const match = allMatchList().find((item) => String(item.id || '') === String(matchId || ''));
    if (!match) return;
    closeResultDuelRead();
    if(window.StationXTeams?.isTeamMatch(match)){window.StationXTeams.openResult(match);return;}
    window.clearTimeout(liveDuelSyncTimer);
    duelTimerState.clear();
    liveDuelRevision = 0;
    activeResultCaptureMatchId = String(match.id || '');
    localStorage.setItem('dml_result_capture_match_id', activeResultCaptureMatchId);
    localStorage.setItem('dml_result_capture_route', (location.hash || '').replace('#', '') || localStorage.getItem('dml_screen') || 'calendario');
    setResultSaveState('saved');
    ['A', 'B'].forEach((side) => {
      const url = new URL('./', window.location.href);
      url.searchParams.set('duel_player', side);
      url.searchParams.set('match_id', activeResultCaptureMatchId);
      url.hash = '';
      const link = resultCaptureModalNode.querySelector(`[data-result-player-link="${side}"]`);
      const copy = resultCaptureModalNode.querySelector(`[data-result-player-copy="${side}"]`);
      if (link) link.href = url.toString();
      if (copy) copy.dataset.url = url.toString();
    });
    const saved = getSavedResult(match);
    const details = parseJsonValue(match.duel_details || '', []);
    const correctionInput=resultCaptureModalNode.querySelector('[data-result-correction-version]');if(correctionInput)correctionInput.value=String(Math.max(0,...details.map(d=>Number(d.manual_correction_at_ms||0))));
    const dice = parseJsonValue(match.dice_roll || '', null);
    const matchIdInput = resultCaptureModalNode.querySelector('[data-result-capture-match-id]');
    const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
    const turnInput = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
    const notesInput = resultCaptureModalNode.querySelector('[data-result-capture-notes]');
    if (matchIdInput) matchIdInput.value = match.id || '';
    if (diceInput) diceInput.value = match.dice_roll || '';
    if (turnInput) turnInput.value = match.turn_choice || '';
    if (notesInput) notesInput.value = match.result_notes || '';
    setResultCaptureText('[data-result-capture-phase]', match.phase || 'Combate');
    setResultCaptureText('[data-result-capture-title]', `R${match.ronda || '-'} M${match.mesa || '-'} | ${match.competition_name || match.division || ''}`);
    setResultCaptureText('[data-result-capture-meta]', `${match.scheduled_on || 'Sin fecha'} ${match.starts_at ? match.starts_at.slice(0, 5) : ''} | ${String(match.match_format || '1v1').toUpperCase()} | BO${match.best_of || 5}`);
    const captureIdentity=resultCaptureModalNode.querySelector('[data-result-capture-identity]');
    if(captureIdentity) captureIdentity.innerHTML=calendarEventIdentity({competitionId:match.competition_id,matches:[match],division:match.division,type:match.phase},false);
    setResultCaptureText('[data-result-capture-bestof]', `Mejor de ${match.best_of || 5}`);
    const teamA = match.team_a || 'Equipo A';
    const teamB = match.team_b || 'Equipo B';
    renderMatchLeaguePositions(match, 'result-capture');
    setResultCaptureText('[data-result-capture-team-a]', teamA);
    setResultCaptureText('[data-result-capture-team-b]', teamB);
    const logoA = matchSideLogoUrl(match, 'a');
    const logoB = matchSideLogoUrl(match, 'b');
    setResultCaptureLogo('[data-result-capture-logo-a]', logoA, matchSideFallback(match, 'a') || 'A', teamA);
    setResultCaptureLogo('[data-result-capture-logo-b]', logoB, matchSideFallback(match, 'b') || 'B', teamB);
    setResultArenaArt('[data-result-capture-side-a]', logoA);
    setResultArenaArt('[data-result-capture-side-b]', logoB);
    setResultCaptureText('[data-result-capture-a]', matchLabelWithTeam(match.a));
    setResultCaptureText('[data-result-capture-b]', matchLabelWithTeam(match.b));
    const shareMatch = resultCaptureModalNode.querySelector('[data-share-match-result]');
    if (shareMatch) shareMatch.onclick = () => {
      const result = evaluateDuel(getSavedResult(match).filter(Boolean), match);
      shareCanvas('Resultado de partida', [match.competition_name || match.division || 'Evento', `R${match.ronda || '-'} | Mesa ${match.mesa || '-'}`, match.a || 'Deck A', result.score || '0-0', match.b || 'Deck B', result.state || 'Pendiente', String(match.scheduled_on || '')], 'resultado-partida-' + String(match.id || '') + '.png');
    };
    Array.from({ length: 5 }, (_, index) => index + 1).forEach((n) => {
      const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === n) || {}) : {};
      const duel = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${n}"]`);
      const method = resultCaptureModalNode.querySelector(`[data-result-capture-method="${n}"]`);
      const lpA = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${n}"]`);
      const lpB = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${n}"]`);
      const note = resultCaptureModalNode.querySelector(`[data-result-capture-duel-note="${n}"]`);
      const deckASelect = resultCaptureModalNode.querySelector(`[data-result-capture-deck-a="${n}"]`);
      const deckBSelect = resultCaptureModalNode.querySelector(`[data-result-capture-deck-b="${n}"]`);
      const playerASelect = resultCaptureModalNode.querySelector(`[data-result-capture-player-a="${n}"]`);
      const playerBSelect = resultCaptureModalNode.querySelector(`[data-result-capture-player-b="${n}"]`);
      const turnsInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${n}"]`);
      const brickAInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-a="${n}"]`);
      const brickBInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-b="${n}"]`);
      const durationInput = resultCaptureModalNode.querySelector(`[data-result-capture-duration="${n}"]`);
      const originSelect = resultCaptureModalNode.querySelector(`[data-result-capture-origin="${n}"]`);
      if (detail.lp_start_a || detail.lp_start_b || Array.isArray(detail.lp_history)) {
        let storedLp = {};
        try { storedLp = JSON.parse(localStorage.getItem(resultLpStorageKey(n)) || '{}'); } catch (error) { storedLp = {}; }
        localStorage.setItem(resultLpStorageKey(n), JSON.stringify({
          ...storedLp,
          a: detail.lp_a !== '' && detail.lp_a !== undefined ? Number(detail.lp_a) : Number(detail.lp_start_a || storedLp.a || 8000),
          b: detail.lp_b !== '' && detail.lp_b !== undefined ? Number(detail.lp_b) : Number(detail.lp_start_b || storedLp.b || 8000),
          start_a: Number(detail.lp_start_a || storedLp.start_a || 8000),
          start_b: Number(detail.lp_start_b || storedLp.start_b || 8000),
          history: Array.isArray(detail.lp_history) ? detail.lp_history : (storedLp.history || []),
          running: false
        }));
      }
      const turnHidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`);
      const diceANode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${n}"]`);
      const diceBNode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${n}"]`);
      if (duel) duel.value = detail.winner || saved[n - 1] || '';
      if (method) method.value = detail.defeat_method || '';
      if (lpA) lpA.value = detail.lp_a ?? '';
      if (lpB) lpB.value = detail.lp_b ?? '';
      if (note) note.value = detail.notes || '';
      if (turnsInput) turnsInput.value = detail.turn_count || '';
      if (brickAInput) brickAInput.checked = Boolean(detail.brick_a);
      if (brickBInput) brickBInput.checked = Boolean(detail.brick_b);
      if (originSelect) {
        const origin = String(detail.data_origin || '').toLowerCase();
        originSelect.value = ['captured', 'added', 'inferred'].includes(origin)
          ? origin
          : (detail.duel_started_at || detail.turn_history?.length ? 'captured' : 'added');
      }
      let localTimer = null;
      try { localTimer = JSON.parse(localStorage.getItem(duelTimerStorageKey(n)) || 'null'); } catch (error) { localTimer = null; }
      const serverTimer = {
        baseElapsed: Number(detail.duration_seconds || 0),
        running: Boolean(detail.timer_running),
        startedAt: Number(detail.timer_started_at || 0) || null,
        updatedAt: Number(detail.timer_updated_at || 0),
        correctionVersion: Number(detail.manual_correction_at_ms || 0),
        warned: Number(detail.duration_seconds || 0) >= 900,
        duelStarted: Boolean(detail.duel_started || detail.winner || saved[n - 1]),
        duelStartedAt: Number(detail.duel_started_at || 0) || null,
        timerMode: ['none', 'countdown'].includes(detail.timer_mode) ? detail.timer_mode : (detail.duel_started || detail.winner || saved[n - 1] ? 'none' : ''),
        turnHistory: Array.isArray(detail.turn_history) ? detail.turn_history : [],
        turnBaseline: Math.max(0, Number(detail.turn_count || 0) - (Array.isArray(detail.turn_history) ? detail.turn_history.length : 0))
      };
      const hasLocalChanges = localTimer && !localTimer.liveSynced && Number(localTimer.correctionVersion || 0) === serverTimer.correctionVersion && Number(localTimer.updatedAt || 0) > serverTimer.updatedAt;
      const timerState = hasLocalChanges ? localTimer : serverTimer;
      timerState.duelStarted = Boolean(timerState.duelStarted || timerState.running || Number(timerState.baseElapsed || 0) > 0 || detail.winner || saved[n - 1]);
      timerState.duelStartedAt = Number(timerState.duelStartedAt || detail.duel_started_at || 0) || null;
      timerState.timerMode = timerState.timerMode || (timerState.duelStarted ? 'none' : '');
      if (detail.duel_started && detail.timer_mode === 'none') timerState.timerMode = 'none';
      if (timerState.timerMode !== 'countdown') { timerState.running = false; timerState.startedAt = null; }
      timerState.turnHistory = Array.isArray(timerState.turnHistory) ? timerState.turnHistory : (Array.isArray(detail.turn_history) ? detail.turn_history : []);
      timerState.turnBaseline = Number.isFinite(Number(timerState.turnBaseline)) ? Math.max(0, Number(timerState.turnBaseline)) : Math.max(0, Number(detail.turn_count || 0) - timerState.turnHistory.length);
      if (detail.winner) {
        timerState.baseElapsed = duelTimerElapsed(timerState);
        timerState.running = false;
        timerState.startedAt = null;
      }
      timerState.warned = Boolean(timerState.warned || duelTimerElapsed(timerState) >= 900);
      duelTimerState.set(n, timerState);
      if (durationInput) durationInput.value = String(duelTimerElapsed(timerState));
      localStorage.setItem(duelTimerStorageKey(n), JSON.stringify(timerState));
      if (hasLocalChanges) setResultSaveState('pending');
      renderDuelTimer(n);
      const sideADecks = matchSideDecks(match, 'a').map(deckInfoByName).filter((deck) => deck.id);
      const sideBDecks = matchSideDecks(match, 'b').map(deckInfoByName).filter((deck) => deck.id);
      const fillDeckSelect = (select, decks, selectedId) => {
        if (!select) return;
        select.innerHTML = decks.map((deck) => `<option value="${escapeHtml(deck.id)}">${escapeHtml(deck.name)}</option>`).join('');
        select.value = String(selectedId || decks[0]?.id || '');
        const label = select.closest('label');
        if (label) {
          label.hidden = decks.length <= 1;
          label.style.display = label.hidden ? 'none' : '';
        }
      };
      const fillPlayerSelect = (select, deckId, selectedId) => {
        if (!select) return;
        const playerIds = new Set((data.playerDecks || []).filter((link) => Number(link.deck_id) === Number(deckId)).map((link) => Number(link.player_id)));
        const players = (data.players || []).filter((player) => playerIds.has(Number(player.id)));
        select.innerHTML = '<option value="">Sin asignar</option>' + players.map((player) => `<option value="${escapeHtml(player.id)}">${escapeHtml(player.alias || player.display_name)}</option>`).join('');
        select.value = String(selectedId || '');
        const label = select.closest('label');
        if (label) label.hidden = players.length === 0;
      };
      fillDeckSelect(deckASelect, sideADecks, detail.deck_a_id);
      fillDeckSelect(deckBSelect, sideBDecks, detail.deck_b_id);
      fillPlayerSelect(playerASelect, deckASelect?.value, detail.player_a_id);
      fillPlayerSelect(playerBSelect, deckBSelect?.value, detail.player_b_id);
      const participantGrid = resultCaptureModalNode.querySelector(`[data-result-participant-grid="${n}"]`);
      if (participantGrid) {
        participantGrid.style.display = Array.from(participantGrid.querySelectorAll('label')).some((label) => !label.hidden) ? '' : 'none';
      }
      deckASelect?.addEventListener('change', () => fillPlayerSelect(playerASelect, deckASelect.value, ''));
      deckBSelect?.addEventListener('change', () => fillPlayerSelect(playerBSelect, deckBSelect.value, ''));
      if (diceANode) {
        const value = detail.dice_a || (n === 1 ? dice?.a || '' : '');
        diceANode.textContent = value || '-';
        diceANode.dataset.value = value || '';
      }
      if (diceBNode) {
        const value = detail.dice_b || (n === 1 ? dice?.b || '' : '');
        diceBNode.textContent = value || '-';
        diceBNode.dataset.value = value || '';
      }
      if (turnHidden) {
        const turnPayload = {
          chosen_by: detail.turn_chosen_by || (n === 1 ? (match.turn_chosen_by || dice?.chosen_by || dice?.winner || '') : ''),
          choice: detail.turn_decision || (n === 1 ? (match.turn_decision || dice?.choice || '') : ''),
          starts: detail.starts_player || (n === 1 ? (match.starts_player || dice?.starts || '') : ''),
          reason: detail.turn_reason || (n === 1 ? 'dice' : 'previous_duel_loss')
        };
        turnHidden.value = (turnPayload.chosen_by && turnPayload.choice && turnPayload.starts)||(detail.manual_correction_at_ms&&turnPayload.starts) ? JSON.stringify(turnPayload) : '';
      }
    });
    const diceSummary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
    if (diceSummary) {
      const persistedStarts = match.starts_player || dice?.starts || '';
      const persistedDecision = match.turn_decision || dice?.choice || '';
      const persistedChosenBy = match.turn_chosen_by || dice?.chosen_by || dice?.winner || '';
      diceSummary.textContent = dice && dice.winner
        ? `Dado ${dice.a}-${dice.b}. Gano ${dice.winner}. ${match.turn_choice || (persistedChosenBy && persistedDecision && persistedStarts ? `${persistedChosenBy} eligio ${persistedDecision}. Empieza ${persistedStarts}.` : '')}`
        : 'Sin lanzamiento registrado.';
    }
    updateResultDie('[data-result-die-a]', dice?.a || 1, false);
    updateResultDie('[data-result-die-b]', dice?.b || 1, false);
    const choicePanel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
    if (choicePanel) choicePanel.hidden = Boolean((dice?.chosen_by && dice?.choice && dice?.starts) || match.turn_choice || !dice?.winner);
    syncResultCaptureSummary();
    resultCaptureModal.show();
    liveDuelRevision = 0;
    window.clearInterval(liveDuelPollTimer);
    syncJudgeLiveDuel(false);
    liveDuelPollTimer = window.setInterval(() => syncJudgeLiveDuel(false), 1800);
    window.setTimeout(() => {
      const body = resultCaptureModalNode.querySelector('.result-console-body');
      if (body) body.scrollTop = 0;
    }, 80);
  }

