(() => {
  'use strict';

  const root = document.documentElement;
  const side = String(root.dataset.duelPlayerSide || '').toUpperCase();
  const matchId = Number(root.dataset.duelPlayerMatch || 0);
  const opposite = side === 'A' ? 'B' : 'A';
  let revision = 0;
  let state = null;
  let entry = '';
  let standard = { display: '0', accumulator: null, operator: '', replace: true };
  let busy = false;
  let pollTimer = null;

  const node = (selector) => document.querySelector(selector);
  const formatLp = (value) => new Intl.NumberFormat('es-CO').format(Math.max(0, Number(value) || 0));
  const standardStep = (currentState, key) => {
    const current = Number(currentState.display || 0);
    const apply = (left, operator, right) => {
      if (operator === 'add') return left + right;
      if (operator === 'subtract') return left - right;
      if (operator === 'multiply') return left * right;
      if (operator === 'divide') return right === 0 ? null : left / right;
      return right;
    };
    if (key === 'clear') return { display: '0', accumulator: null, operator: '', replace: true };
    if (/^\d$/.test(key)) {
      const display = currentState.replace || currentState.display === '0' ? key : `${currentState.display}${key}`.slice(0, 12);
      return { ...currentState, display, replace: false };
    }
    if (['add', 'subtract', 'multiply', 'divide'].includes(key)) {
      const computed = currentState.operator && currentState.accumulator !== null ? apply(Number(currentState.accumulator), currentState.operator, current) : current;
      if (computed === null || !Number.isFinite(computed)) return { display: 'Error', accumulator: null, operator: '', replace: true };
      return { display: String(Number(computed.toFixed(6))), accumulator: computed, operator: key, replace: true };
    }
    if (key === 'equals' && currentState.operator && currentState.accumulator !== null) {
      const computed = apply(Number(currentState.accumulator), currentState.operator, current);
      if (computed === null || !Number.isFinite(computed)) return { display: 'Error', accumulator: null, operator: '', replace: true };
      return { display: String(Number(computed.toFixed(6))), accumulator: null, operator: '', replace: true };
    }
    return currentState;
  };
  const showToast = (message, error = false) => {
    const toast = node('[data-player-toast]');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('is-error', error);
    toast.hidden = false;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => { toast.hidden = true; }, 2600);
  };
  const setSync = (label, mode = '') => {
    const sync = node('[data-player-sync]');
    if (!sync) return;
    sync.className = `player-sync ${mode}`.trim();
    sync.innerHTML = `<i></i> ${label}`;
  };
  const elapsed = () => {
    if (!state) return 0;
    const base = Math.max(0, Number(state.elapsed_seconds || 0));
    if (!state.timer_running || !state.timer_started_at) return Math.min(1200, base);
    return Math.min(1200, Math.floor(base + (Date.now() - Number(state.timer_started_at)) / 1000));
  };
  const renderClock = () => {
    const clock = node('[data-player-clock]');
    if (!clock || !state) return;
    if (state.timer_mode !== 'countdown') {
      clock.textContent = state.status === 'setup' ? 'Sin definir' : 'Sin reloj';
      return;
    }
    const remaining = Math.max(0, 1200 - elapsed());
    clock.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    clock.classList.toggle('is-warning', remaining <= 300 && remaining > 0);
    clock.classList.toggle('is-expired', remaining === 0);
  };
  const render = () => {
    if (!state) return;
    const ownLp = node('[data-player-lp-own]');
    const rivalLp = node('[data-player-lp-rival]');
    const turnLabel = node('[data-player-turn-label]');
    const turnDetail = node('[data-player-turn-detail]');
    const endTurn = node('[data-player-end-turn]');
    const surrender = node('[data-player-surrender]');
    if (ownLp) ownLp.textContent = formatLp(state[`lp_${side.toLowerCase()}`]);
    if (rivalLp) rivalLp.textContent = formatLp(state[`lp_${opposite.toLowerCase()}`]);
    if (node('[data-player-entry]')) node('[data-player-entry]').textContent = formatLp(entry || 0);
    if (node('[data-player-standard-display]')) node('[data-player-standard-display]').textContent = standard.display;
    if (node('[data-player-duel-number]')) node('[data-player-duel-number]').textContent = `D${state.duel_number || 1}`;
    if (node('[data-player-turn-count]')) node('[data-player-turn-count]').textContent = String(state.turn_count || 0);
    const myTurn = state.active_side === side && ['running', 'paused'].includes(state.status);
    if (turnLabel) turnLabel.textContent = state.surrendered_by
      ? `Rendición del lado ${state.surrendered_by}`
      : (state.status === 'setup' || state.status === 'ready' ? 'Esperando al juez' : (myTurn ? 'Es tu turno' : `Turno del lado ${state.active_side || '-'}`));
    if (turnDetail) turnDetail.textContent = state.surrendered_by
      ? 'El duelo terminó y está pendiente de confirmación por el juez.'
      : (state.status === 'setup' ? 'Faltan dados, elección o confirmación de inicio.' : (myTurn ? `Al terminar, registra el turno ${Number(state.turn_count || 0) + 1}.` : 'La pantalla se actualizará automáticamente.'));
    if (endTurn) {
      endTurn.disabled = busy || !myTurn;
      endTurn.classList.toggle('is-ready', myTurn);
    }
    if (surrender) surrender.disabled = busy || !myTurn || Boolean(state.surrendered_by);
    const duelRunning = ['running', 'paused'].includes(state.status) && !state.surrendered_by;
    document.querySelectorAll('[data-player-key], [data-player-lp-op], [data-player-standard-use]').forEach((control) => {
      control.disabled = busy || !duelRunning;
    });
    const dice = node('[data-player-dice]');
    if (dice) dice.textContent = state.dice_a && state.dice_b
      ? `A ${state.dice_a} · B ${state.dice_b}${state.starts_player ? ` · Empieza ${state.starts_player}` : ' · Falta elegir'}`
      : 'Esperando lanzamiento del juez';
    renderClock();
  };

  async function request(operation = '', payload = {}) {
    const url = `./?api=duel_session&match_id=${encodeURIComponent(matchId)}`;
    const options = { cache: 'no-store', headers: { Accept: 'application/json' } };
    if (operation) {
      const body = new FormData();
      body.set('match_id', String(matchId));
      body.set('actor', side);
      body.set('operation', operation);
      body.set('revision', String(revision));
      body.set('payload', JSON.stringify(payload));
      options.method = 'POST';
      options.body = body;
    }
    const response = await fetch(url, options);
    const data = await response.json();
    if (response.status === 409 && data.state) {
      revision = Number(data.revision || revision);
      state = data.state;
      render();
      throw new Error('La pantalla cambió en otro dispositivo. Intenta de nuevo.');
    }
    if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo sincronizar.');
    revision = Number(data.revision || revision);
    state = data.state;
    render();
    return data;
  }

  async function poll() {
    if (busy || document.hidden) return;
    try {
      const data = await request();
      setSync(`En línea · v${data.revision || revision}`, 'is-online');
    } catch (error) {
      setSync('Reconectando', 'is-error');
    }
  }

  async function mutate(operation, payload = {}, success = '') {
    if (busy) return;
    busy = true;
    render();
    setSync('Guardando', 'is-waiting');
    try {
      await request(operation, payload);
      setSync(`En línea · v${revision}`, 'is-online');
      if (success) showToast(success);
    } catch (error) {
      setSync('Revisa la conexión', 'is-error');
      showToast(error.message, true);
    } finally {
      busy = false;
      render();
    }
  }

  node('[data-player-end-turn]')?.addEventListener('click', () => mutate('end_turn', {}, 'Turno registrado.'));
  node('[data-player-surrender]')?.addEventListener('click', () => {
    if (!state || state.active_side !== side || !['running', 'paused'].includes(state.status)) {
      showToast('Solo puedes rendirte durante tu turno.', true);
      return;
    }
    if (!window.confirm('¿Confirmas que deseas rendirte y terminar este duelo?')) return;
    mutate('surrender', {}, 'Rendicion registrada. El juez confirmara el resultado.');
  });
  document.querySelectorAll('[data-player-key]').forEach((button) => button.addEventListener('click', () => {
    entry = `${entry}${button.dataset.playerKey || ''}`.replace(/^0+(?=\d)/, '').slice(0, 6);
    render();
  }));
  document.querySelectorAll('[data-player-lp-op]').forEach((button) => button.addEventListener('click', () => {
    const kind = button.dataset.playerLpOp || '';
    if (kind === 'clear') { entry = ''; render(); return; }
    if (['subtract', 'add'].includes(kind) && !Number(entry || 0)) { showToast('Escribe primero un valor.', true); return; }
    const amount = Number(entry || 0);
    entry = '';
    mutate('lp_change', { side, kind, amount }, 'LP actualizados.');
  }));
  document.querySelectorAll('[data-player-standard-key]').forEach((button) => button.addEventListener('click', () => {
    standard = standardStep(standard, button.dataset.playerStandardKey || '');
    render();
  }));
  node('[data-player-standard-use]')?.addEventListener('click', () => {
    const value = Number(standard.display);
    if (!Number.isFinite(value)) {
      showToast('La operacion no tiene un resultado valido.', true);
      return;
    }
    entry = String(Math.max(0, Math.min(99999, Math.round(Math.abs(value)))));
    render();
    showToast('Resultado preparado. Ahora elige sumar o restar.');
  });

  poll();
  pollTimer = window.setInterval(poll, 1500);
  window.setInterval(renderClock, 1000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  window.addEventListener('beforeunload', () => window.clearInterval(pollTimer));
})();
