/**
 * life-point-tool: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function initLifePointTool() {
    const tool = document.querySelector('[data-screen="herramienta_lp"]');
    if (!tool) return;
    const storageKey = 'dml_lp_tool_state';
    const defaultState = {
      a: 8000,
      b: 8000,
      startA: 8000,
      startB: 8000,
      nameA: 'Duelista 1',
      nameB: 'Duelista 2',
      timer: 0,
      running: false,
      turn: 1,
      coin: '',
      dice: '',
      history: []
    };
    let state = { ...defaultState };
    let timerStartedAt = 0;
    let timerTick = null;

    try {
      const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
      state = { ...state, ...saved };
      state.a = Number.isFinite(Number(state.a)) ? Math.max(0, Number(state.a)) : 8000;
      state.b = Number.isFinite(Number(state.b)) ? Math.max(0, Number(state.b)) : 8000;
      state.startA = Number.isFinite(Number(state.startA)) ? Math.max(0, Number(state.startA)) : 8000;
      state.startB = Number.isFinite(Number(state.startB)) ? Math.max(0, Number(state.startB)) : 8000;
      state.timer = Number.isFinite(Number(state.timer)) ? Math.max(0, Number(state.timer)) : 0;
      state.turn = Number.isFinite(Number(state.turn)) ? Math.max(1, Number(state.turn)) : 1;
      state.coin = String(state.coin || '');
      state.dice = String(state.dice || '');
      state.history = Array.isArray(state.history) ? state.history.slice(0, 12) : [];
      state.running = false;
    } catch (error) {
      state = { ...defaultState };
    }

    const formatLp = (value) => new Intl.NumberFormat('es-CO').format(Math.max(0, Math.round(Number(value) || 0)));
    const formatTimer = (seconds) => {
      const value = Math.max(0, Math.floor(seconds || 0));
      const h = String(Math.floor(value / 3600)).padStart(2, '0');
      const m = String(Math.floor((value % 3600) / 60)).padStart(2, '0');
      const s = String(value % 60).padStart(2, '0');
      return `${h}:${m}:${s}`;
    };
    const save = () => {
      localStorage.setItem(storageKey, JSON.stringify({ ...state, running: false }));
    };
    const setStatus = (text) => {
      const node = tool.querySelector('[data-lp-status]');
      if (node) node.textContent = text || 'Listo';
    };
    const renderHistory = () => {
      const list = tool.querySelector('[data-lp-history]');
      if (!list) return;
      list.innerHTML = state.history.length
        ? state.history.map((item) => `<article><b>${escapeHtml(item.player)}</b><span>${escapeHtml(item.text)}</span><small>${escapeHtml(item.score)}</small></article>`).join('')
        : '<p class="note">Sin cambios registrados todavia.</p>';
    };
    const render = () => {
      ['a', 'b'].forEach((player) => {
        const value = Math.max(0, Math.round(Number(state[player]) || 0));
        const display = tool.querySelector(`[data-lp-display="${player}"]`);
        const bar = tool.querySelector(`[data-lp-bar="${player}"]`);
        if (display) display.textContent = formatLp(value);
        const startValue = player === 'a' ? state.startA : state.startB;
        const base = Math.max(1, Number(startValue) || 8000);
        if (bar) bar.style.width = `${Math.min(100, (value / base) * 100)}%`;
      });
      const nameA = tool.querySelector('[data-lp-name="a"]');
      const nameB = tool.querySelector('[data-lp-name="b"]');
      if (nameA && nameA.value !== state.nameA) nameA.value = state.nameA || 'Duelista 1';
      if (nameB && nameB.value !== state.nameB) nameB.value = state.nameB || 'Duelista 2';
      const startA = tool.querySelector('[data-lp-start="a"]');
      const startB = tool.querySelector('[data-lp-start="b"]');
      if (startA && startA.value !== String(state.startA)) startA.value = String(state.startA);
      if (startB && startB.value !== String(state.startB)) startB.value = String(state.startB);
      const timer = tool.querySelector('[data-lp-timer-display]');
      if (timer) timer.textContent = formatTimer(state.timer);
      const timerButton = tool.querySelector('[data-lp-timer-toggle]');
      if (timerButton) timerButton.innerHTML = state.running ? '<i class="bi bi-pause-fill"></i> Pausar' : '<i class="bi bi-play-fill"></i> Iniciar';
      const resetLabel = tool.querySelector('[data-lp-action="reset"] span');
      if (resetLabel) resetLabel.textContent = state.startA === state.startB ? formatLp(state.startA) : 'Reset';
      const turnNode = tool.querySelector('[data-lp-turn]');
      if (turnNode) turnNode.textContent = String(state.turn || 1);
      const coinNode = tool.querySelector('[data-lp-coin]');
      if (coinNode) coinNode.textContent = state.coin || 'Moneda';
      const diceNode = tool.querySelector('[data-lp-dice]');
      if (diceNode) diceNode.textContent = state.dice || '-';
      renderHistory();
      save();
    };
    const pushHistory = (player, text, previous) => {
      const label = player === 'a' ? (state.nameA || 'Duelista 1') : (state.nameB || 'Duelista 2');
      state.history.unshift({
        player: label,
        text,
        score: `${formatLp(previous)} > ${formatLp(state[player])}`,
        prevA: player === 'a' ? previous : state.a,
        prevB: player === 'b' ? previous : state.b
      });
      state.history = state.history.slice(0, 12);
    };
    const changeLp = (player, delta, label) => {
      if (!['a', 'b'].includes(player)) return;
      const previous = Math.max(0, Math.round(Number(state[player]) || 0));
      state[player] = Math.max(0, previous + delta);
      pushHistory(player, label || `${delta > 0 ? '+' : ''}${delta}`, previous);
      setStatus(`${player === 'a' ? state.nameA : state.nameB}: ${label || delta}`);
      render();
    };
    const setLp = (player, value, label) => {
      if (!['a', 'b'].includes(player)) return;
      const previous = Math.max(0, Math.round(Number(state[player]) || 0));
      state[player] = Math.max(0, Math.round(Number(value) || 0));
      pushHistory(player, label || `LP fijado en ${formatLp(state[player])}`, previous);
      setStatus(`${player === 'a' ? state.nameA : state.nameB}: ${formatLp(state[player])} LP`);
      render();
    };
    const halfLp = (player) => {
      if (!['a', 'b'].includes(player)) return;
      const previous = Math.max(0, Math.round(Number(state[player]) || 0));
      state[player] = Math.floor(previous / 2);
      pushHistory(player, 'Mitad de LP', previous);
      setStatus('LP dividido a la mitad');
      render();
    };
    const resetDuel = () => {
      state.a = Math.max(0, Math.round(Number(state.startA) || 8000));
      state.b = Math.max(0, Math.round(Number(state.startB) || 8000));
      state.timer = 0;
      state.running = false;
      timerStartedAt = 0;
      state.turn = 1;
      state.coin = '';
      state.dice = '';
      state.history = [];
      setStatus('Duelo reiniciado');
      render();
    };
    const undo = () => {
      const last = state.history.shift();
      if (!last) return;
      state.a = Number(last.prevA || state.a);
      state.b = Number(last.prevB || state.b);
      setStatus('Ultimo cambio revertido');
      render();
    };
    const stopTimerInterval = () => {
      if (timerTick) window.clearInterval(timerTick);
      timerTick = null;
    };
    const syncTimer = () => {
      if (!state.running) return;
      const now = Date.now();
      if (timerStartedAt) {
        state.timer += Math.floor((now - timerStartedAt) / 1000);
      }
      timerStartedAt = now;
      render();
    };
    const startTimer = () => {
      if (state.running) return;
      state.running = true;
      timerStartedAt = Date.now();
      stopTimerInterval();
      timerTick = window.setInterval(syncTimer, 1000);
      render();
    };
    const pauseTimer = () => {
      syncTimer();
      state.running = false;
      timerStartedAt = 0;
      stopTimerInterval();
      render();
    };

    tool.querySelectorAll('[data-lp-delta]').forEach((button) => {
      button.addEventListener('click', () => changeLp(button.dataset.lpTarget, Number(button.dataset.lpDelta || 0), button.textContent.trim()));
    });
    tool.querySelectorAll('[data-lp-custom-apply]').forEach((button) => {
      button.addEventListener('click', () => {
        const player = button.dataset.lpTarget;
        const input = tool.querySelector(`[data-lp-custom="${player}"]`);
        const amount = Math.max(0, Number(input?.value || 0));
        if (!amount) return;
        if (button.dataset.lpCustomApply === 'set') {
          setLp(player, amount, `LP fijado en ${formatLp(amount)}`);
        } else {
          const sign = button.dataset.lpCustomApply === 'add' ? 1 : -1;
          changeLp(player, amount * sign, `${sign > 0 ? '+' : '-'}${amount}`);
        }
        if (input) input.value = '';
      });
    });
    tool.querySelectorAll('[data-lp-action="half"]').forEach((button) => {
      button.addEventListener('click', () => halfLp(button.dataset.lpTarget));
    });
    const resetButton = tool.querySelector('[data-lp-action="reset"]');
    if (resetButton) resetButton.addEventListener('click', resetDuel);
    const fullResetButton = tool.querySelector('[data-lp-full-reset]');
    if (fullResetButton) fullResetButton.addEventListener('click', resetDuel);
    const undoButton = tool.querySelector('[data-lp-action="undo"]');
    if (undoButton) undoButton.addEventListener('click', undo);
    const swapButton = tool.querySelector('[data-lp-action="swap"]');
    if (swapButton) {
      swapButton.addEventListener('click', () => {
        [state.a, state.b] = [state.b, state.a];
        [state.startA, state.startB] = [state.startB, state.startA];
        [state.nameA, state.nameB] = [state.nameB, state.nameA];
        setStatus('Duelistas intercambiados');
        render();
      });
    }
    tool.querySelectorAll('[data-lp-action="zero"]').forEach((button) => {
      button.addEventListener('click', () => setLp(button.dataset.lpTarget, 0, 'LP en 0'));
    });
    tool.querySelectorAll('[data-lp-start-preset]').forEach((button) => {
      button.addEventListener('click', () => {
        const value = Math.max(0, Number(button.dataset.lpStartPreset || 8000));
        state.startA = value;
        state.startB = value;
        state.a = value;
        state.b = value;
        state.history = [];
        setStatus(`LP inicial ${formatLp(value)}`);
        render();
      });
    });
    const applyStartButton = tool.querySelector('[data-lp-apply-start]');
    if (applyStartButton) {
      applyStartButton.addEventListener('click', () => {
        const startA = Math.max(0, Number(tool.querySelector('[data-lp-start="a"]')?.value || 8000));
        const startB = Math.max(0, Number(tool.querySelector('[data-lp-start="b"]')?.value || 8000));
        state.startA = Math.round(startA);
        state.startB = Math.round(startB);
        state.a = state.startA;
        state.b = state.startB;
        state.history = [];
        setStatus('LP inicial aplicado');
        render();
      });
    }
    const turnButton = tool.querySelector('[data-lp-action="turn"]');
    if (turnButton) {
      turnButton.addEventListener('click', () => {
        state.turn = Math.max(1, Number(state.turn || 1) + 1);
        setStatus(`Turno ${state.turn}`);
        render();
      });
    }
    const coinButton = tool.querySelector('[data-lp-action="coin"]');
    if (coinButton) {
      coinButton.addEventListener('click', () => {
        state.coin = Math.random() >= 0.5 ? 'Cara' : 'Sello';
        setStatus(`Moneda: ${state.coin}`);
        render();
      });
    }
    const diceButton = tool.querySelector('[data-lp-action="dice"]');
    if (diceButton) {
      diceButton.addEventListener('click', () => {
        state.dice = String(Math.floor(Math.random() * 6) + 1);
        setStatus(`Dado: ${state.dice}`);
        render();
      });
    }
    tool.querySelectorAll('[data-lp-name]').forEach((input) => {
      input.addEventListener('input', () => {
        if (input.dataset.lpName === 'a') state.nameA = input.value || 'Duelista 1';
        if (input.dataset.lpName === 'b') state.nameB = input.value || 'Duelista 2';
        save();
      });
    });
    const timerToggle = tool.querySelector('[data-lp-timer-toggle]');
    if (timerToggle) timerToggle.addEventListener('click', () => (state.running ? pauseTimer() : startTimer()));
    const timerReset = tool.querySelector('[data-lp-timer-reset]');
    if (timerReset) {
      timerReset.addEventListener('click', () => {
        state.running = false;
        state.timer = 0;
        timerStartedAt = 0;
        stopTimerInterval();
        render();
      });
    }
    window.addEventListener('beforeunload', () => {
      if (state.running) pauseTimer();
    });
    render();
  }

  const savedParam = new URLSearchParams(window.location.search).get('saved') || '';
  const entityParams = new URLSearchParams(window.location.search);
  const requestedDeckId = entityParams.get('deck_id') || '';
  const requestedTeamId = entityParams.get('team_id') || '';
  if (requestedDeckId && adminDeckSelect) {
    adminDeckSelect.value = requestedDeckId;
    adminDeckSelect.dispatchEvent(new Event('change'));
    const requestedDeck = (data.allDecks || []).find((item) => String(item.id || '') === requestedDeckId);
    if (requestedDeck && deckSelect) deckSelect.value = requestedDeck.name || '';
  }
  if (requestedTeamId && adminTeamSelect) {
    adminTeamSelect.value = requestedTeamId;
    adminTeamSelect.dispatchEvent(new Event('change'));
  }
  const initialGestionModule = savedParam.startsWith('division') ? 'divisiones' : (localStorage.getItem('dml_gestion_module') || 'resumen');
  openGestionModule(initialGestionModule, { noRoute: true });

  let deferredInstallPrompt = null;
  let offlineFlushRunning = false;
  const updateOfflineQueueCount = () => {
    const count = readOfflineQueue().length;
    document.querySelectorAll('[data-offline-queue-count]').forEach((node) => { node.textContent = `${count} ${count === 1 ? 'cambio pendiente' : 'cambios pendientes'}`; });
  };
  const updateConnectivity = () => {
    const online = navigator.onLine;
    document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = online ? 'En linea. Los cambios pueden guardarse en servidor.' : 'Sin conexion. La captura quedara pendiente en este dispositivo.'; });
    document.querySelectorAll('[data-pwa-icon]').forEach((node) => { node.className = 'bi ' + (online ? 'bi-wifi' : 'bi-wifi-off'); });
    updateOfflineQueueCount();
  };
  window.addEventListener('beforeinstallprompt', (event) => { event.preventDefault(); deferredInstallPrompt = event; });
  document.querySelectorAll('[data-install-pwa]').forEach((button) => {
    button.addEventListener('click', async () => {
      if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
      } else {
        window.alert('La aplicacion ya puede estar instalada o el navegador mostrara la opcion Instalar en su menu.');
      }
    });
  });
  const offlineFormActions = new Set(['save_deck', 'save_deck_analysis_profile', 'save_matchup_analysis', 'save_team', 'save_competition', 'configure_competition', 'save_match_schedule', 'save_division', 'save_settings']);
  const offlineEntityBaseline = (action, payload) => {
    const source = action === 'save_deck' ? (data.allDecks || []).find((row) => String(row.id) === String(payload.deck_id || ''))
      : action === 'save_team' ? (data.teams || []).find((row) => String(row.id) === String(payload.team_id || ''))
      : ['save_competition', 'configure_competition'].includes(action) ? (data.competitions || []).find((row) => String(row.id) === String(payload.competition_id || ''))
      : null;
    return source?.updated_at || '';
  };
  document.addEventListener('submit', (event) => {
    if (navigator.onLine || !(event.target instanceof HTMLFormElement)) return;
    const form = event.target; const formData = new FormData(form); const action = String(formData.get('action') || '');
    if (!offlineFormActions.has(action)) return;
    const hasFile = Array.from(formData.values()).some((value) => value instanceof File && value.size > 0);
    event.preventDefault();
    if (hasFile) { window.alert('Este formulario contiene un archivo. Los datos no se perderan en pantalla, pero la imagen debe enviarse cuando vuelva la conexion.'); return; }
    const payload = {};
    formData.forEach((value, key) => {
      const text = String(value);
      if (Object.prototype.hasOwnProperty.call(payload, key)) payload[key] = Array.isArray(payload[key]) ? [...payload[key], text] : [payload[key], text];
      else payload[key] = text;
    });
    payload.offline_entity_updated_at = offlineEntityBaseline(action, payload);
    const requestId = makeRequestId(); const queue = readOfflineQueue();
    queue.push({ id: requestId, kind: 'form', action, payload, queuedAt: new Date().toISOString(), attempts: 0 });
    writeOfflineQueue(queue); updateOfflineQueueCount();
    window.alert(`Sin conexion. ${action.replaceAll('_', ' ')} quedo en la cola de este dispositivo.`);
  }, true);
  const flushOfflineResult = async () => {
    updateConnectivity();
    if (!navigator.onLine || offlineFlushRunning) return;
    const queue = readOfflineQueue();
    if (!queue.length) return;
    offlineFlushRunning = true;
    try {
      while (queue.length && navigator.onLine) {
        const queued = queue[0];
        const body = new URLSearchParams();
        if (queued.kind === 'form') {
          body.set('action', 'sync_offline_form');
          body.set('offline_request_id', queued.id || makeRequestId());
          body.set('offline_payload', JSON.stringify(queued.payload || {}));
        } else {
          (queued.payload || []).forEach(([key, value]) => body.append(key, value));
          if (!body.has('offline_request_id')) body.set('offline_request_id', queued.id || makeRequestId());
        }
        const response = await fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body.toString(), credentials: 'same-origin', redirect: 'follow' });
        const responseUrl = String(response.url || '');
        if (responseUrl.includes('saved=offline_conflicto')) {
          document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = 'Conflicto detectado: el registro cambio en otro dispositivo. Revisa antes de reemplazarlo.'; });
          throw new Error('Conflicto de sincronizacion.');
        }
        if (!response.ok || !responseUrl.includes('saved=')) throw new Error('El servidor no confirmo el guardado.');
        queue.shift();
        writeOfflineQueue(queue);
        updateOfflineQueueCount();
      }
      if (!queue.length) {
        localStorage.removeItem(OFFLINE_QUEUE_KEY);
        document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = 'Todos los cambios pendientes fueron sincronizados.'; });
      }
    } catch (error) {
      if (queue[0]) queue[0].attempts = Number(queue[0].attempts || 0) + 1;
      writeOfflineQueue(queue);
      document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = `Hay ${queue.length} cambios pendientes por sincronizar.`; });
    } finally {
      offlineFlushRunning = false;
      updateOfflineQueueCount();
    }
  };
  window.addEventListener('online', flushOfflineResult);
  window.addEventListener('offline', updateConnectivity);
  updateConnectivity();
  flushOfflineResult();
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('./service-worker.js').catch(() => {});

  const showAppNotice = (message, tone = 'info') => {
    let host = document.querySelector('[data-app-notices]');
    if (!host) {
      host = document.createElement('div'); host.dataset.appNotices = ''; host.className = 'app-notices'; document.body.appendChild(host);
    }
    const notice = document.createElement('div'); notice.className = `app-notice is-${tone}`; notice.innerHTML = `<i class="bi ${tone === 'success' ? 'bi-check2-circle' : 'bi-bell'}"></i><span>${escapeHtml(message)}</span><button type="button" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>`;
    notice.querySelector('button')?.addEventListener('click', () => notice.remove()); host.appendChild(notice);
    window.setTimeout(() => notice.remove(), 8000);
  };
  const notifyUpcomingMatches = () => {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const threshold = Math.max(1, Number(settings.notification_minutes_before || 30));
    const now = Date.now();
    allMatchList().filter((match) => !['FINALIZADA', 'CANCELADA'].includes(String(match.estado || '').toUpperCase()) && match.scheduled_on && match.starts_at).forEach((match) => {
      const starts = new Date(`${match.scheduled_on}T${String(match.starts_at).slice(0, 5)}:00`).getTime();
      const minutes = Math.ceil((starts - now) / 60000);
      const key = `dml-notified-${match.id}-${match.scheduled_on}-${match.starts_at}`;
      if (minutes >= 0 && minutes <= threshold && !localStorage.getItem(key)) {
        const label = `Mesa ${match.mesa || '-'}: ${match.a || 'Deck A'} vs ${match.b || 'Deck B'}`;
        new Notification('Proxima partida', { body: `${label}. Comienza en ${minutes} min.`, icon: './assets/logo.png' });
        showAppNotice(`${label}. Comienza en ${minutes} min.`);
        localStorage.setItem(key, new Date().toISOString());
      }
    });
  };
  document.querySelectorAll('[data-enable-notifications]').forEach((button) => button.addEventListener('click', async () => {
    if (!('Notification' in window)) return showAppNotice('Este navegador no admite notificaciones.', 'warning');
    const permission = await Notification.requestPermission();
    button.innerHTML = `<i class="bi bi-bell${permission === 'granted' ? '-fill' : '-slash'}"></i> ${permission === 'granted' ? 'Avisos activos' : 'Avisos bloqueados'}`;
    if (permission === 'granted') notifyUpcomingMatches();
  }));
  notifyUpcomingMatches();
  window.setInterval(notifyUpcomingMatches, 60000);

  let liveRevision = Math.max(0, ...(data.audit || []).map((row) => Number(row.id || 0)));
  let livePolling = false;
  const pollLiveCenter = async () => {
    if (!navigator.onLine || livePolling || readOfflineQueue().length) return;
    livePolling = true;
    try {
      const response = await fetch('./?api=live', { cache: 'no-store', credentials: 'same-origin' });
      if (!response.ok) throw new Error('live');
      const payload = await response.json();
      if (Number(payload.revision || 0) <= liveRevision) return;
      liveRevision = Number(payload.revision || 0);
      const captureOpen = resultCaptureModalNode && !resultCaptureModalNode.hidden && resultCaptureModalNode.classList.contains('show');
      if (captureOpen) {
        document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = 'Hay una actualizacion en servidor. Se aplicara al cerrar la captura actual.'; });
        return;
      }
      const byId = new Map((payload.matches || []).map((row) => [String(row.id), row]));
      allMatchList().forEach((match) => { const fresh = byId.get(String(match.id)); if (fresh) Object.assign(match, fresh); });
      const knownUnlockIds = new Set((data.medalUnlocks || []).map((row) => String(row.id)));
      const newUnlocks = (payload.medal_unlocks || []).filter((row) => !knownUnlockIds.has(String(row.id)));
      data.medalUnlocks = payload.medal_unlocks || data.medalUnlocks;
      newUnlocks.forEach((unlock) => showAppNotice(`Medalla desbloqueada: ${unlock.medal_name}`, 'success'));
      renderStats(); renderProfile();
      showAppNotice('Resultados y centro en vivo actualizados.', 'success');
    } catch {}
    finally { livePolling = false; }
  };
  window.setInterval(pollLiveCenter, Math.max(5, Number(settings.live_refresh_seconds || 15)) * 1000);

  initLifePointTool();
  fillDeckSelect();
  renderProfile();
  const requestedDeckCategory = entityParams.get('deck_section') || (entityParams.get('deck_view') === 'analysis' ? 'strategy' : '');
  const requestedDivision = entityParams.get('division') || '';
  const requestedDivisionCategory = entityParams.get('division_section') || '';
  const requestedStatsCategory = entityParams.get('stats_section') || '';
  openDeckProfileView('summary');
  renderStats();
  if (resultDivision) loadMatchOptions();
  routeTo(publicSeason ? 'estadisticas' : ((location.hash || '').replace('#', '') || localStorage.getItem('dml_screen') || 'dashboard'), 'replace');
  if (deckCategoryOrder.includes(requestedDeckCategory) && (location.hash || '').replace('#', '') === 'deck_ficha') {
    window.setTimeout(() => openDeckCategory(requestedDeckCategory, false), 0);
  }
  if (requestedDivision && divisionDetailPanels.some((panel) => panel.dataset.divisionDetail === requestedDivision)) {
    openDivisionDetail(requestedDivision);
  }
  if (divisionCategoryOrder.includes(requestedDivisionCategory) && (location.hash || '').replace('#', '') === 'divisiones') {
    window.setTimeout(() => openDivisionCategory(requestedDivision, requestedDivisionCategory, false), 0);
  }
  if (statsCategoryOrder.includes(requestedStatsCategory) && (location.hash || '').replace('#', '') === 'estadisticas') {
    window.setTimeout(() => openStatsTab(requestedStatsCategory, true, false), 0);
  }
  if (publicSeason) openStatsTab('summary');
  if (entityParams.get('checkin_match')) {
    routeTo('estadisticas');
    openStatsTab('operations');
    const checkinSelect = document.querySelector('[data-checkin-match]');
    if (checkinSelect && Array.from(checkinSelect.options).some((option) => option.value === entityParams.get('checkin_match'))) {
      checkinSelect.value = entityParams.get('checkin_match');
      checkinSelect.dispatchEvent(new Event('change'));
    }
    const checkinCode = document.querySelector('[data-checkin-code]');
    if (checkinCode && entityParams.get('checkin_code')) checkinCode.value = entityParams.get('checkin_code');
  }
  const persistedResultMatchId = localStorage.getItem('dml_result_capture_match_id') || '';
  if (persistedResultMatchId && allMatchList().some((match) => String(match.id || '') === String(persistedResultMatchId))) {
    const persistedRoute = localStorage.getItem('dml_result_capture_route') || 'calendario';
    if (screens.some((screen) => screen.dataset.screen === persistedRoute)) routeTo(persistedRoute);
    window.setTimeout(() => openResultCapture(persistedResultMatchId), 250);
  }
  window.YgoCupBracketNavigation?.init(openMatchSheet);
  if (!publicSeason) window.YgoDailyDuels?.init(data, allMatchList, openMatchSheet);
}());
