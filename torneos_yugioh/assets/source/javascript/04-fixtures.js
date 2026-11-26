/**
 * fixtures: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function fixtureRoundsFor(division) {
    const rounds = data.fixtures?.[division] || [];
    return Array.isArray(rounds) ? rounds : Object.values(rounds || {});
  }

  function allMatches(division) {
    return fixtureRoundsFor(division).flatMap((round) =>
      (round.matches || []).map((match) => ({ ...match, ronda: round.ronda, division }))
    );
  }

  function allMatchList() {
    return Object.keys(data.fixtures || {}).flatMap((division) => allMatches(division));
  }

  function individualMatchList() {
    return allMatchList().flatMap(match => {
      const event=(data.competitions || []).find(c=>String(c.id)===String(match.competition_id));
      if(!event?.teamConfig)return [match];
      const r=data.teamResults?.[match.id];if(!r || r.kind!=='team')return [];
      return r.evaluations.map((evaluation,seat)=>({...match,deck_a_id:r.lineup_a.decks[seat],deck_b_id:r.lineup_b.decks[seat],deck_c_id:null,deck_d_id:null,deck_e_id:null,deck_f_id:null,a:deckInfoById(r.lineup_a.decks[seat])?.name || '',b:deckInfoById(r.lineup_b.decks[seat])?.name || '',team_a_extra:[],team_b_extra:[],match_format:'1v1',participants_per_side:1,duels:r.sequences[seat],result:evaluation.winner,score:evaluation.score,duel_details:'[]'}));
    });
  }
  function deckInfoById(id) { return (data.allDecks || []).find(d=>String(d.id)===String(id)); }

  function activeAlertCompetitionIds() {
    return new Set((data.competitions || [])
      .filter((competition) => {
        const status = normalizeStatusKey(competition.status || '');
        return Number(competition.id || 0) > 0 && ['active', 'activo', 'en_curso'].includes(status);
      })
      .map((competition) => String(competition.id)));
  }

  function alertableMatchList() {
    const activeIds = activeAlertCompetitionIds();
    if (!activeIds.size) return [];
    return allMatchList().filter((match) =>
      match.id
      && match.competition_id
      && activeIds.has(String(match.competition_id))
      && !['FINALIZADA', 'completed', 'finalizada', 'cancelled', 'canceled', 'anulado'].includes(String(match.estado || match.status || ''))
    );
  }

  function deckInfoByName(name) {
    return (data.allDecks || []).find((deck) => normalize(deck.name) === normalize(name)) || {};
  }

  function matchSideLogoUrl(match, side) {
    const deckName = side === 'a' ? match.a : match.b;
    const deck = deckInfoByName(deckName);
    const teamLogo = side === 'a' ? match.team_a_logo : match.team_b_logo;
    return teamLogo || deck.team_logo || deck.logo_url || deck.cover_url || '';
  }

  function matchSideFallback(match, side) {
    const deckName = side === 'a' ? match.a : match.b;
    const teamName = side === 'a' ? match.team_a : match.team_b;
    const teamCode = side === 'a' ? match.team_a_code : match.team_b_code;
    const deck = deckInfoByName(deckName);
    return String(teamCode || deck.team_code || deck.team_symbol || teamName || deck.team || deckName || '?').replace(/[^a-z0-9]/gi, '').slice(0, 3).toUpperCase() || '?';
  }

  function matchLabelWithTeam(deckName) {
    const deck = deckInfoByName(deckName);
    return `${deck.team || 'Sin equipo'} / ${deckName}`;
  }

  function matchSideDecks(match, side) {
    const base = side === 'a' ? match.a : match.b;
    const extra = side === 'a' ? (match.team_a_extra || []) : (match.team_b_extra || []);
    return [base, ...extra].filter(Boolean);
  }

  function matchSideLabel(match, side) {
    return matchSideDecks(match, side).map(matchLabelWithTeam).join(' + ');
  }

  function resultTeamLogoCell(match, side) {
    const deckName = side === 'a' ? match.a : match.b;
    const teamName = side === 'a' ? match.team_a : match.team_b;
    const extraDecks = side === 'a' ? (match.team_a_extra || []) : (match.team_b_extra || []);
    const safeTeamName = teamName || 'Sin equipo';
    const logoUrl = matchSideLogoUrl(match, side);
    const fallback = matchSideFallback(match, side);
    const logo = logoUrl
      ? `<span class="result-team-logo"><img loading="lazy" decoding="async" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(safeTeamName)}"></span>`
      : `<span class="result-team-logo result-team-logo-fallback">${escapeHtml(fallback)}</span>`;
    const extra = extraDecks.length ? ` + ${extraDecks.length} deck${extraDecks.length === 1 ? '' : 's'}` : '';
    return `
      <div class="result-team-cell">
        ${logo}
        <span class="result-team-copy">
          <strong title="${escapeHtml(safeTeamName)}">${escapeHtml(safeTeamName)}</strong>
          <small title="${escapeHtml(deckName || 'Por definir')}">${escapeHtml(deckName || 'Por definir')}${escapeHtml(extra)}</small>
        </span>
      </div>`;
  }

  function matchVersusLabel(match) {
    return `${matchSideLabel(match, 'a')} vs ${matchSideLabel(match, 'b')}`;
  }

  function matchShortSideLabel(match, side) {
    return matchSideDecks(match, side).join(' + ') || 'Por definir';
  }

  function statusClassForMatch(match) {
    const status = normalizeStatusKey(match.estado || match.status || 'PENDIENTE');
    if (['finalizada', 'completed', 'finalizado'].includes(status)) return 'is-done';
    if (['activo', 'active', 'en_curso'].includes(status)) return 'is-active';
    return 'is-pending';
  }

  function renderCupBracket(matches, competition, success = '') {
    if (window.StationXCupTree && !competition?.workflow && ['single_elimination', 'bracket', 'manual'].includes(competition?.execution_model || 'manual')) {
      return success + window.StationXCupTree.render(matches, competition || {}, data.settings || {}, data.allDecks || [], escapeHtml, {logo: matchSideLogoUrl});
    }
    const sorted = matches.slice().sort((left, right) =>
      Number(left.display_order || 0) - Number(right.display_order || 0)
      || Number(left.ronda || left.round_number || 0) - Number(right.ronda || right.round_number || 0)
      || Number(left.mesa || left.table_number || 0) - Number(right.mesa || right.table_number || 0)
    );
    if (!sorted.length) {
      return '<p class="note">No hay duelos programados para mostrar la llave de esta copa.</p>';
    }
    const rounds = sorted.reduce((map, match) => {
      const key = Number(match.ronda || match.round_number || 1);
      if (!map[key]) map[key] = [];
      map[key].push(match);
      return map;
    }, {});
    const roundKeys = Object.keys(rounds).map(Number).sort((a, b) => a - b);
    const title = competition?.name || 'Copa';
    return `${success}
      <div class="cup-bracket-shell" style="--event-accent: ${escapeHtml(competition?.accent || '#ff2bd6')}; --bracket-columns: ${Math.max(1, roundKeys.length)}">
        <div class="cup-bracket-stage">
          ${roundKeys.map((roundNumber, index) => `
            <section class="cup-bracket-round">
              <header>
                <span>${escapeHtml(rounds[roundNumber][0]?.bracket_round_label || `Ronda ${roundNumber}`)}</span>
                <b>${escapeHtml(rounds[roundNumber].length)} combates</b>
              </header>
              <div class="cup-bracket-matches">
                ${rounds[roundNumber].map((match) => `
                  <article class="cup-bracket-match ${statusClassForMatch(match)}">
                    <div class="cup-bracket-meta">
                      <span>Slot ${escapeHtml(match.bracket_slot || match.mesa || match.table_number || '-')}</span>
                      <b>${escapeHtml(match.scheduled_on || 'Sin fecha')}</b>
                    </div>
                    <div class="cup-bracket-team team-a">${escapeHtml(matchShortSideLabel(match, 'a'))}</div>
                    <div class="cup-bracket-team team-b">${escapeHtml(matchShortSideLabel(match, 'b'))}</div>
                    <small>${escapeHtml(match.estado || match.status || 'PENDIENTE')}</small>
                    ${competition?.workflow ? `<small>${escapeHtml(match.bracket_round_label || '')}</small>` : ''}
                    <button class="btn btn-outline-info btn-sm" type="button" data-schedule-open-match="${escapeHtml(match.id)}"><i class="bi bi-eye"></i> Ver ficha</button>
                  </article>
                `).join('')}
              </div>
            </section>
          `).join('')}
          <section class="cup-bracket-winner">
            <i class="bi bi-trophy-fill"></i>
            <span>Ganador</span>
            <b>${escapeHtml(title)}</b>
          </section>
        </div>
      </div>`;
  }

  function ruleForMatch(match) {
    return window.StationXFixturePolicy.ruleForMatch(match, data);
  }

  function evaluateDuelByRule(values, match = null) {
    const rule = ruleForMatch(match);
    return rule ? window.YgoEventModels.evaluateSequence(values, match?.best_of || 3, rule.conditions || []) : null;
  }

  function evaluateBestOfDuel(values, configuredBestOf = 3) {
    return window.YgoEventModels.evaluateSequence(values, configuredBestOf);
  }

  function evaluateDuel(values, match = null) {
    const ruleResult = evaluateDuelByRule(values, match);
    if (ruleResult) return ruleResult;
    return evaluateBestOfDuel(values, match?.best_of);
  }

  function resultKey(match) {
    return `dml_result_${match.division}_${match.ronda}_${match.mesa}`;
  }

  function getSavedResult(match) {
    if (match && Array.isArray(match.duels) && match.duels.length) return match.duels;
    try {
      return JSON.parse(localStorage.getItem(resultKey(match)) || '[]');
    } catch {
      return [];
    }
  }

  function computeStandings(division) {
    return window.StationXFixturePolicy.computeStandings(division, data, allMatches, getSavedResult, evaluateDuel);
  }

  function hasPlayedMatches(division) {
    return computeStandings(division).some((row) => row.pj > 0);
  }

  function competitionStatus(type, division) {
    const competition = competitionFor(type, division);
    return competition ? String(competition.status || 'draft').toLowerCase() : 'draft';
  }

  const fixtureCompetition = document.querySelector('[data-fixture-competition]');
  const fixtureRound = document.querySelector('[data-fixture-round]');
  const fixtureBody = document.querySelector('[data-fixture-body]');
  const fixtureBye = document.querySelector('[data-fixture-bye]');

  function activeFixtureCompetitions() {
    const competitionIdsWithMatches = new Set(allMatchList()
      .map((match) => String(match.competition_id || ''))
      .filter(Boolean));
    return (data.competitions || [])
      .filter((competition) => {
        const status = normalizeStatusKey(competition.status || '');
        return competitionIdsWithMatches.has(String(competition.id || ''))
          && ['active', 'activo', 'en_curso'].includes(status);
      })
      .sort((left, right) => String(left.name || '').localeCompare(String(right.name || ''), 'es'));
  }

  function fixtureRoundsForCompetition(competitionId) {
    const grouped = new Map();
    allMatchList()
      .filter((match) => String(match.competition_id || '') === String(competitionId || ''))
      .forEach((match) => {
        const roundNumber = Number(match.ronda || match.round_number || 1);
        if (!grouped.has(roundNumber)) grouped.set(roundNumber, { ronda: roundNumber, matches: [] });
        grouped.get(roundNumber).matches.push(match);
      });
    return Array.from(grouped.values())
      .sort((left, right) => left.ronda - right.ronda)
      .map((round) => ({
        ...round,
        matches: round.matches.sort((left, right) => Number(left.mesa || 0) - Number(right.mesa || 0) || Number(left.id || 0) - Number(right.id || 0))
      }));
  }

  function renderFixtureCompetitionOptions() {
    if (!fixtureCompetition) return;
    const competitions = activeFixtureCompetitions();
    const requestedId = new URLSearchParams(window.location.search).get('event_id') || '';
    const currentId = fixtureCompetition.value;
    fixtureCompetition.innerHTML = competitions.length
      ? competitions.map((competition) => `<option value="${escapeHtml(competition.id)}">${escapeHtml(competition.name || 'Evento')} | ${escapeHtml(eventTypeLabel(competition.type))}</option>`).join('')
      : '<option value="">No hay eventos activos con partidas</option>';
    const preferredId = [currentId, requestedId].find((id) => competitions.some((competition) => String(competition.id) === String(id)));
    if (preferredId) fixtureCompetition.value = preferredId;
  }

  function renderFixture() {
    if (!fixtureCompetition || !fixtureRound || !fixtureBody) return;
    const competitionId = fixtureCompetition.value;
    const rounds = fixtureRoundsForCompetition(competitionId);
    const selected = Number(fixtureRound.value || rounds[0]?.ronda || 1);
    fixtureRound.innerHTML = rounds.length
      ? rounds.map((round) => `<option value="${round.ronda}" ${round.ronda === selected ? 'selected' : ''}>Ronda ${round.ronda}</option>`).join('')
      : '<option value="">Sin rondas programadas</option>';
    const round = rounds.find((item) => item.ronda === selected) || rounds[0];
    fixtureBye.textContent = round ? `${round.matches.length} partida${round.matches.length === 1 ? '' : 's'}` : 'Sin partidas';
    fixtureBody.innerHTML = round
      ? (round.matches || []).map((match) => `<tr><td>${escapeHtml(match.mesa)}</td><td>${escapeHtml(matchSideLabel(match, 'a'))}</td><td>${escapeHtml(matchSideLabel(match, 'b'))}</td><td><span class="status-chip">${escapeHtml(match.estado || 'PENDIENTE')}</span></td></tr>`).join('')
      : '<tr><td colspan="4"><div class="empty-state">No hay partidas programadas para este evento activo.</div></td></tr>';
  }

  if (fixtureCompetition) fixtureCompetition.addEventListener('change', () => { fixtureRound.value = ''; renderFixture(); });
  if (fixtureRound) fixtureRound.addEventListener('change', renderFixture);
  renderFixtureCompetitionOptions();
  renderFixture();

  const calendarGrid = document.querySelector('[data-calendar-grid]');
  const calendarTitle = document.querySelector('[data-calendar-title]');
  const calendarCount = document.querySelector('[data-calendar-count]');
  const calendarDetail = document.querySelector('[data-calendar-detail]');
  const calendarPrev = document.querySelector('[data-calendar-prev]');
  const calendarNext = document.querySelector('[data-calendar-next]');
  const calendarToday = document.querySelector('[data-calendar-today]');
  const calendarType = document.querySelector('[data-calendar-type]');
  const calendarDivision = document.querySelector('[data-calendar-division]');
  const calendarStatus = document.querySelector('[data-calendar-status]');
  const calendarSearch = document.querySelector('[data-calendar-search]');
  const calendarProfileScopeNode = document.querySelector('[data-calendar-profile-scope]');
  const calendarProfileKind = document.querySelector('[data-calendar-profile-kind]');
  const calendarProfileLabel = document.querySelector('[data-calendar-profile-label]');
  const calendarProfileClear = document.querySelector('[data-calendar-profile-clear]');
  const profileCalendarButton = document.querySelector('[data-profile-calendar]');
  const teamCalendarButton = document.querySelector('[data-team-calendar]');
  const calendarWeekdays = document.querySelector('[data-calendar-weekdays]');
  const calendarViewButtons = Array.from(document.querySelectorAll('[data-calendar-view]'));
  const fullCalendarNode = document.querySelector('.fullcalendar-shell[data-fullcalendar]');
  const fallbackNodes = [calendarWeekdays, calendarGrid].filter(Boolean);
  const calendarModalNode = document.querySelector('[data-calendar-modal]');
  const calendarModal = calendarModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(calendarModalNode) : null;
  const calendarDayModalNode = document.querySelector('[data-calendar-day-modal]');
  const calendarDayModal = calendarDayModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(calendarDayModalNode) : null;
  const deleteFriendlyModalNode = document.querySelector('[data-delete-friendly-modal]');
  const deleteFriendlyModal = deleteFriendlyModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(deleteFriendlyModalNode) : null;
  const friendlyMatchModalNode = document.querySelector('[data-friendly-match-modal]');
  const friendlyDivisionSelect = friendlyMatchModalNode ? friendlyMatchModalNode.querySelector('[data-friendly-division]') : null;
  const friendlyDivisionValue = friendlyMatchModalNode ? friendlyMatchModalNode.querySelector('[data-friendly-division-value]') : null;
  const friendlyRulesetValue = friendlyMatchModalNode ? friendlyMatchModalNode.querySelector('[data-friendly-ruleset-value]') : null;
  const friendlyDeckSelects = friendlyMatchModalNode ? Array.from(friendlyMatchModalNode.querySelectorAll('[data-friendly-deck-select]')) : [];
  let fullCalendar = null;
  let calendarCursor = new Date();
  calendarCursor.setDate(1);
  let selectedCalendarDate = serverToday;
  let calendarView = localStorage.getItem('dml_calendar_view') || 'month';
  let calendarProfileScope = null;

