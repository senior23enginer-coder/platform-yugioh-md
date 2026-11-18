/**
 * results-directory: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function syncResultPeriodFields() {
    const period = resultPeriod?.value || 'all';
    document.querySelectorAll('[data-result-period-field]').forEach((field) => { field.hidden = field.dataset.resultPeriodField !== period; });
    const invalid = period === 'range' && resultDateFrom?.value && resultDateTo?.value && resultDateFrom.value > resultDateTo.value;
    resultDateTo?.setCustomValidity(invalid ? 'La fecha hasta debe ser igual o posterior a la fecha desde.' : '');
    const error = document.querySelector('[data-result-period-error]');
    if (error) error.hidden = !invalid;
  }
  function resultScheduleMatches(match) {
    const selection = resultScheduleFilter?.value || 'all';
    const date = String(match.scheduled_on || '').slice(0, 10);
    if (selection === 'unscheduled') return !date;
    if (selection === 'today') return date === serverToday;
    if (selection === 'future') return Boolean(date) && date > serverToday;
    if (selection === 'overdue') {
      const status = String(match.estado || match.status || '').trim().toUpperCase();
      const closed = ['FINALIZADA', 'FINALIZADO', 'COMPLETED', 'FINISHED', 'CLOSED', 'CANCELLED', 'CANCELED', 'CANCELADA', 'CANCELADO'].includes(status);
      return Boolean(date) && date < serverToday && !closed;
    }
    return true;
  }
  function resultPeriodMatches(match) {
    const date = String(match.scheduled_on || '').slice(0, 10);
    const period = resultPeriod?.value || 'all';
    if (period === 'today') return date === serverToday;
    if (period === 'day') return !resultDate?.value || date === resultDate.value;
    if (period === 'month') return !resultMonth?.value || date.startsWith(resultMonth.value + '-');
    if (period === 'range') {
      const from = resultDateFrom?.value || '', to = resultDateTo?.value || '';
      return (!from && !to) || (Boolean(date) && (!from || date >= from) && (!to || date <= to));
    }
    return true;
  }
  syncResultPeriodFields();
  const resultSearch = document.querySelector('[data-result-search]');
  const resultQueue = document.querySelector('[data-result-queue]');
  const resultFilterCount = document.querySelector('[data-result-filter-count]');
  const resultClearFilters = document.querySelector('[data-result-clear-filters]');
  const resultKpiTotal = document.querySelector('[data-result-kpi-total]');
  const resultKpiPending = document.querySelector('[data-result-kpi-pending]');
  const resultKpiProgress = document.querySelector('[data-result-kpi-progress]');
  const resultKpiDone = document.querySelector('[data-result-kpi-done]');
  const resultKpiOverdue = document.querySelector('[data-result-kpi-overdue]');
  const resultKpiToday = document.querySelector('[data-result-kpi-today]');
  const resultKpiFuture = document.querySelector('[data-result-kpi-future]');
  const resultSaveButton = document.querySelector('[data-result-save-button]');
  const resultCaptureModalNode = document.querySelector('[data-result-capture-modal]');
  const resultCaptureModal = resultCaptureModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(resultCaptureModalNode) : null;
  const resultDuelReadModal = resultCaptureModalNode?.querySelector('[data-result-duel-read-modal]');
  const duelTimerConfirmNode = document.querySelector('[data-duel-timer-confirm-modal]');
  let pendingTimerDuel = 0;
  let activeResultCaptureMatchId = '';
  let resultLpState = null;
  let resultLpTimerTick = null;
  let resultStandardState = { display: '0', accumulator: null, operator: '', replace: true };
  let liveDuelRevision = 0;
  let liveDuelPollTimer = null;
  let liveDuelSyncTimer = null;
  let liveDuelBusy = false;
  let applyingLiveDuelState = false;

  function setResultSaveState(state) {
    const node = resultCaptureModalNode?.querySelector('[data-result-save-state]');
    if (!node) return;
    const states = {
      pending: ['bi-pencil-square', 'Cambios pendientes'],
      saving: ['bi-arrow-repeat', 'Guardando...'],
      saved: ['bi-cloud-check', 'Turnos guardados']
    };
    const [icon, label] = states[state] || states.saved;
    node.dataset.state = state;
    node.innerHTML = `<i class="bi ${icon}"></i> ${label}`;
  }

  function closeResultDuelRead() {
    if (resultDuelReadModal) resultDuelReadModal.hidden = true;
  }

  function completionLabel(value) {
    if (!value) return 'Sin fecha de cierre registrada';
    const date = new Date(String(value).replace(' ', 'T') + (/Z$|[+-]\d\d:\d\d$/.test(value) ? '' : '-05:00'));
    return Number.isNaN(date.getTime()) ? 'Sin fecha de cierre registrada' : date.toLocaleString('es-CO', { timeZone: 'America/Bogota', dateStyle: 'short', timeStyle: 'medium' });
  }

  function openResultDuelRead(duelNumber) {
    if (!resultDuelReadModal) return;
    const match = allMatchList().find((item) => String(item.id || '') === String(activeResultCaptureMatchId || ''));
    if (!match) return;
    const saved = getSavedResult(match);
    const details = parseJsonValue(match.duel_details || '', []);
    const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === Number(duelNumber)) || {}) : {};
    const winner = String(detail.winner || saved[Number(duelNumber) - 1] || '').toUpperCase();
    if (!winner) return;
    const dice = parseJsonValue(match.dice_roll || '', {});
    const value = (raw, fallback = 'No registrado') => raw === '' || raw === null || raw === undefined ? fallback : String(raw);
    const yesNo = (key) => Object.prototype.hasOwnProperty.call(detail, key) ? (detail[key] ? 'Si' : 'No') : 'No registrado';
    const deckName = (id, fallback) => ((data.allDecks || []).find((deck) => Number(deck.id) === Number(id)) || {}).name || fallback || 'No registrado';
    const playerName = (id) => {
      const player = (data.players || []).find((item) => Number(item.id) === Number(id));
      return player ? (player.alias || player.display_name || player.name) : 'Sin asignar';
    };
    const methodLabels = { life_points: 'Derrota por puntos de vida', surrender: 'Rendicion del oponente', time_limit: 'Tiempo finalizado', deck_out: 'Deck sin cartas', effect_win: 'Condicion de victoria por efecto' };
    const sideADeck = deckName(detail.deck_a_id, match.a);
    const sideBDeck = deckName(detail.deck_b_id, match.b);
    const winnerText = winner === 'A' ? `${match.team_a || 'Equipo A'} / ${sideADeck}` : winner === 'B' ? `${match.team_b || 'Equipo B'} / ${sideBDeck}` : 'Empate';
    const starts = value(detail.starts_player || (Number(duelNumber) === 1 ? match.starts_player || dice.starts : ''), 'No registrado').toUpperCase();
    const firstText = starts === 'A' ? `${match.team_a || 'Equipo A'} / ${sideADeck}` : starts === 'B' ? `${match.team_b || 'Equipo B'} / ${sideBDeck}` : 'No registrado';
    const secondText = starts === 'A' ? `${match.team_b || 'Equipo B'} / ${sideBDeck}` : starts === 'B' ? `${match.team_a || 'Equipo A'} / ${sideADeck}` : 'No registrado';
    const diceA = value(detail.dice_a || (Number(duelNumber) === 1 ? dice.a : ''), '-');
    const diceB = value(detail.dice_b || (Number(duelNumber) === 1 ? dice.b : ''), '-');
    const startA = Number(detail.lp_start_a || 8000);
    const startB = Number(detail.lp_start_b || 8000);
    const hasLpA = detail.lp_a !== '' && detail.lp_a !== null && detail.lp_a !== undefined;
    const hasLpB = detail.lp_b !== '' && detail.lp_b !== null && detail.lp_b !== undefined;
    const history = Array.isArray(detail.lp_history) ? detail.lp_history : [];
    const turnHistory = Array.isArray(detail.turn_history) ? detail.turn_history : [];
    const title = resultDuelReadModal.querySelector('[data-result-duel-read-title]');
    const subtitle = resultDuelReadModal.querySelector('[data-result-duel-read-subtitle]');
    const body = resultDuelReadModal.querySelector('[data-result-duel-read-body]');
    if (title) title.textContent = `D${duelNumber} | ${winnerText}`;
    if (subtitle) subtitle.textContent = `R${match.ronda || '-'} M${match.mesa || '-'} | ${match.competition_name || match.division || 'Combate'} | Solo lectura`;
    const metric = (label, metricValue, accent = '') => `<div class="result-duel-read-metric${accent ? ` ${accent}` : ''}"><span>${escapeHtml(label)}</span><strong>${escapeHtml(String(metricValue))}</strong></div>`;
    const historyHtml = history.length ? history.map((item) => {
      const side = String(item.side || '').toUpperCase();
      const owner = item.name || (side === 'A' ? match.team_a : match.team_b) || `Equipo ${side}`;
      return `<tr><td data-label="Equipo">${escapeHtml(owner)}</td><td data-label="Operacion">${escapeHtml(item.action || item.operation || 'Cambio')}</td><td data-label="Antes">${formatResultLp(item.before)} → ${formatResultLp(item.after)} LP</td></tr>`;
    }).join('') : '<tr><td colspan="4"><div class="empty-state">Este duelo no tiene movimientos individuales de LP registrados.</div></td></tr>';
    const turnHistoryHtml = turnHistory.length ? turnHistory.map((item) => {
      const side = String(item.side || '').toUpperCase();
      const owner = side === 'A' ? match.team_a || 'Equipo A' : match.team_b || 'Equipo B';
      const endedAt = item.ended_at ? new Date(item.ended_at) : null;
      const recorded = endedAt && !Number.isNaN(endedAt.getTime())
        ? endedAt.toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'medium' })
        : 'Sin fecha';
      return `<tr><td data-label="Turno">${escapeHtml(item.turn || '-')}</td><td data-label="Equipo">${escapeHtml(owner)} (${escapeHtml(side)})</td><td data-label="Tiempo">${formatResultLpTimer(item.elapsed_seconds || 0)}</td><td data-label="Registrado">${escapeHtml(recorded)}</td></tr>`;
    }).join('') : '<tr><td colspan="4"><div class="empty-state">Este duelo no tiene cierres de turno registrados.</div></td></tr>';
    if (body) body.innerHTML = `
      <section class="result-duel-read-result"><span>Resultado final</span><strong>${escapeHtml(winnerText)}</strong><small>${escapeHtml(methodLabels[detail.defeat_method] || value(detail.defeat_method, 'Metodo no registrado'))}</small></section>
      <section class="result-duel-read-grid">
        ${metric('Finalizacion de esta partida', completionLabel(detail.completed_at), 'wide')}
        ${metric('Dado Equipo A', diceA)}${metric('Dado Equipo B', diceB)}
        ${metric('Eligio el turno', value(detail.turn_chosen_by || (Number(duelNumber) === 1 ? match.turn_chosen_by || dice.chosen_by || dice.winner : '')))}
        ${metric('Decision', value(detail.turn_decision || (Number(duelNumber) === 1 ? match.turn_decision || dice.choice : '')))}
        ${metric('Empieza primero', firstText, 'wide')}${metric('Empieza segundo', secondText, 'wide')}
        ${metric('Numero de turnos', detail.turn_count ? detail.turn_count : 'No registrado')}
        ${metric('Duracion', detail.duration_seconds ? formatResultLpTimer(detail.duration_seconds) : 'No registrada')}
      </section>
      <section class="result-duel-read-sides">
        <article><header><span>Equipo A</span><strong>${escapeHtml(match.team_a || 'Equipo A')}</strong></header><dl><div><dt>Deck participante</dt><dd>${escapeHtml(sideADeck)}</dd></div><div><dt>Jugador</dt><dd>${escapeHtml(playerName(detail.player_a_id))}</dd></div><div><dt>LP inicial</dt><dd>${formatResultLp(startA)}</dd></div><div><dt>LP final</dt><dd>${hasLpA ? formatResultLp(detail.lp_a) : 'No registrado'}</dd></div><div><dt>Mano brick</dt><dd>${yesNo('brick_a')}</dd></div></dl></article>
        <article><header><span>Equipo B</span><strong>${escapeHtml(match.team_b || 'Equipo B')}</strong></header><dl><div><dt>Deck participante</dt><dd>${escapeHtml(sideBDeck)}</dd></div><div><dt>Jugador</dt><dd>${escapeHtml(playerName(detail.player_b_id))}</dd></div><div><dt>LP inicial</dt><dd>${formatResultLp(startB)}</dd></div><div><dt>LP final</dt><dd>${hasLpB ? formatResultLp(detail.lp_b) : 'No registrado'}</dd></div><div><dt>Mano brick</dt><dd>${yesNo('brick_b')}</dd></div></dl></article>
      </section>
      <section class="result-duel-read-notes"><span>Notas del duelo</span><p>${escapeHtml(value(detail.notes, 'Sin notas registradas.'))}</p></section>
      <section class="result-duel-read-history"><div><h3>Historial de turnos</h3><span>${turnHistory.length} turnos registrados</span></div><div class="table-responsive"><table class="table app-table compact mobile-card-table"><thead><tr><th>Turno</th><th>Equipo</th><th>Tiempo acumulado</th><th>Registrado</th></tr></thead><tbody>${turnHistoryHtml}</tbody></table></div></section>
      <section class="result-duel-read-history"><div><h3>Historial de puntos de vida</h3><span>${history.length} movimientos registrados</span></div><div class="table-responsive"><table class="table app-table compact mobile-card-table"><thead><tr><th>Equipo</th><th>Operacion</th><th>Antes</th><th>Despues</th></tr></thead><tbody>${historyHtml}</tbody></table></div></section>`;
    resultDuelReadModal.hidden = false;
    resultDuelReadModal.querySelector('.result-duel-read-dialog')?.scrollTo({ top: 0 });
  }

  resultDuelReadModal?.querySelectorAll('[data-result-duel-read-close]').forEach((button) => button.addEventListener('click', closeResultDuelRead));

  function resultBaseMatches() {
    const division = resultDivision ? resultDivision.value : '';
    const matches = division ? allMatches(division) : allMatchList();
    return matches.filter((match) => match.id);
  }

  function syncResultEventOptions() {
    if (!resultEventFilter) return;
    const current = resultEventFilter.value;
    const phase = resultPhase?.value || '';
    const phaseMatches = (value) => phase === 'events'
      ? ['event', 'events', 'special', 'eternal', 'tiebreaker', 'final'].includes(String(value || '').toLowerCase())
      : String(value || '').toLowerCase() === phase;
    const matches = resultBaseMatches();
    const events = (data.competitions || []).filter(event =>
      ['active', 'activo', 'en_curso', 'in_progress'].includes(normalizeStatusKey(event.status || ''))
      && (!phase || phaseMatches(event.type) || matches.some(match => String(match.competition_id) === String(event.id) && phaseMatches(match.phase)))
    ).sort((a, b) => String(a.name).localeCompare(String(b.name), 'es'));
    const allLabel = ({league:'Todas las ligas',cup:'Todas las copas',qualifier:'Todos los clasificatorios',world:'Todos los mundiales',friendly:'Todos los amistosos'})[phase] || 'Todos los eventos';
    resultEventFilter.replaceChildren(new Option(allLabel, ''), ...events.map(event => new Option(window.StationXLabels?.text(event.name) || String(event.name).replace(/^(.+) League$/, 'Liga $1'), String(event.id))));
    resultEventFilter.value = events.some(event => String(event.id) === current) ? current : '';
    resultEventFilter.title = events.length ? 'Eventos activos de la fase seleccionada' : 'No hay eventos activos para esta fase. La opción Todos permite consultar el historial.';
  }

  function resultLeagueDivisionLabel(division, event, includeLeague = false) {
    const originalName = event?.name || '';
    let name = String(division?.name || 'Sin división registrada');
    if (originalName && name.startsWith(originalName)) name = name.slice(originalName.length).replace(/^[\s\u2014\u2013-]+/u, '');
    const leagueName = window.StationXLabels?.text(originalName) || originalName.replace(/^(.+) League$/, 'Liga $1');
    return includeLeague && leagueName ? `${leagueName} — ${name}` : name;
  }

  function syncResultLeagueDivisionOptions() {
    if (!resultLeagueDivisionFilter) return;
    const current = resultLeagueDivisionFilter.value;
    const phase = resultPhase?.value || '';
    const eventId = resultEventFilter?.value || '';
    const selectedEvent = (data.competitions || []).find(e => String(e.id) === eventId);
    const applicable = (!phase || phase === 'league') && (!selectedEvent || selectedEvent.type === 'league');
    const leagues = (data.competitions || []).filter(e => e.type === 'league' && ['active', 'activo', 'en_curso', 'in_progress'].includes(normalizeStatusKey(e.status || '')) && (!eventId || String(e.id) === eventId));
    const leagueById = new Map(leagues.map(e => [String(e.id), e]));
    const divisions = applicable ? Object.entries(data.divisions || {}).filter(([,d]) => Number(d.id) > 0 && d.active !== false && String(d.active) !== '0' && leagueById.has(String(d.league_competition_id))).sort((a,b) => Number(a[1].tier_order || 0) - Number(b[1].tier_order || 0) || String(a[0]).localeCompare(String(b[0]))) : [];
    resultLeagueDivisionFilter.replaceChildren(new Option(applicable ? 'Todas las divisiones' : 'No aplica a esta fase', ''), ...divisions.map(([key,d]) => new Option(resultLeagueDivisionLabel(d, leagueById.get(String(d.league_competition_id)), !eventId), key)));
    resultLeagueDivisionFilter.value = divisions.some(([key]) => key === current) ? current : '';
    resultLeagueDivisionFilter.disabled = !applicable;
  }

  function resultLeagueDivisionMatches(match) {
    const key = resultLeagueDivisionFilter?.value || '';
    if (!key) return true;
    const division = data.divisions?.[key];
    return Boolean(division) && String(match.competition_id) === String(division.league_competition_id)
      && (match.division === key || (Number(match.division_id) > 0 && Number(match.division_id) === Number(division.id)));
  }

  function resultMatchEventContext(match) {
    const event = (data.competitions || []).find(e => String(e.id) === String(match.competition_id));
    const league = event?.type === 'league' || match.phase === 'league';
    const name = event?.name || match.competition_name || ({league:'Liga',cup:'Copa',qualifier:'Clasificatorio',world:'Mundial',friendly:'Amistoso',events:'Evento'})[match.phase] || 'Evento';
    const label = window.StationXLabels?.text(name) || name.replace(/^(.+) League$/, 'Liga $1');
    const division = data.divisions?.[match.division] || Object.values(data.divisions || {}).find(d => Number(match.division_id) > 0 && Number(d.id) === Number(match.division_id));
    const ownDivision = league && division && String(division.league_competition_id) === String(match.competition_id);
    return `<span class="tag">${escapeHtml(label)}</span>${league ? `<span class="result-division-name">División: ${escapeHtml(ownDivision ? resultLeagueDivisionLabel(division, event) : 'Sin división registrada')}</span>` : ''}`;
  }

  function filteredResultMatches() {
    const phase = resultPhase ? resultPhase.value : '';
    const status = resultStatus ? resultStatus.value : '';
    const round = resultRoundFilter ? resultRoundFilter.value : '';
    const query = normalize(resultSearch ? resultSearch.value : '');
    const phaseMatches = (match) => {
      if (!phase) return true;
      const matchPhase = String(match.phase || '').toLowerCase();
      if (phase === 'events') {
        return ['event', 'events', 'special', 'eternal', 'tiebreaker', 'final'].includes(matchPhase);
      }
      return matchPhase === phase;
    };
    const statusMatches = (match) => {
      if (!status) return true;
      const matchStatus = String(match.estado || match.status || 'PENDIENTE').trim().toUpperCase();
      if (status === 'EN CURSO') return ['EN CURSO', 'REQUIERE D5'].includes(matchStatus);
      return matchStatus === status;
    };
    const filtered = resultBaseMatches().filter((match) => {
      const haystack = normalize(`${match.a} ${match.b} ${match.division} ${match.phase} ${match.competition_name} ${match.competition_status} ronda ${match.ronda} mesa ${match.mesa} ${match.scheduled_on} ${match.starts_at} ${match.estado} ${match.score} ${matchLabelWithTeam(match.a)} ${matchLabelWithTeam(match.b)}`);
      return phaseMatches(match)
        && statusMatches(match)
        && resultLeagueDivisionMatches(match)
        && (!resultEventFilter?.value || String(match.competition_id) === resultEventFilter.value)
        && (!round || String(match.ronda) === String(round))
        && resultPeriodMatches(match)
        && resultScheduleMatches(match)
        && (!query || haystack.includes(query));
    });
    const direction = resultSort?.value === 'asc' ? 1 : -1;
    return filtered.sort((a, b) => {
      const aHasDate = Boolean(a.scheduled_on);
      const bHasDate = Boolean(b.scheduled_on);
      if (aHasDate !== bHasDate) return aHasDate ? -1 : 1;
      const aDate = `${a.scheduled_on || ''}T${a.starts_at || '00:00:00'}`;
      const bDate = `${b.scheduled_on || ''}T${b.starts_at || '00:00:00'}`;
      return direction * aDate.localeCompare(bDate)
        || direction * (Number(a.ronda || 0) - Number(b.ronda || 0))
        || direction * (Number(a.mesa || 0) - Number(b.mesa || 0))
        || direction * (Number(a.id || 0) - Number(b.id || 0));
    });
  }

  function selectedMatch() {
    const matches = resultBaseMatches();
    const selectedId = resultMatch ? resultMatch.value : '';
    return matches.find((match) => String(match.id) === String(selectedId)) || filteredResultMatches()[0] || matches[0];
  }

  function syncSequentialDuelControls(selects, savedValues) {
    const values = Array.from({ length: 5 }, (_, index) => savedValues[index] || '');
    let firstOpen = values.findIndex((value) => !value);
    if (firstOpen < 0) firstOpen = values.length;
    selects.forEach((select, index) => {
      const enabled = index <= firstOpen;
      select.disabled = !enabled;
      select.title = enabled ? '' : 'Guarda el duelo anterior para habilitar este.';
      if (!enabled) select.value = '';
    });
  }

  function loadMatchOptions() {
    const matches = filteredResultMatches();
    if (resultMatch) {
      resultMatch.innerHTML = matches.map((match) => `<option value="${match.id}">${(match.phase || '').toUpperCase()} R${match.ronda} M${match.mesa}: ${matchVersusLabel(match)}</option>`).join('');
    }
    renderResultDashboard(matches);
    loadResult();
  }

  function loadResult() {
    const match = selectedMatch();
    if (!match) {
      duelSelects.forEach((select) => { select.value = ''; });
      syncSequentialDuelControls(duelSelects, []);
      renderResultDashboard([]);
      return;
    }
    if (resultMatchId) resultMatchId.value = match.id || '';
    resultA.textContent = matchLabelWithTeam(match.a);
    resultB.textContent = matchLabelWithTeam(match.b);
    const saved = getSavedResult(match);
    duelSelects.forEach((select, index) => { select.value = saved[index] || ''; });
    syncSequentialDuelControls(duelSelects, saved);
    renderResult();
  }

