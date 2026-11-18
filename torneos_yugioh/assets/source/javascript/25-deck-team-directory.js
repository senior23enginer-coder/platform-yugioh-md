/**
 * deck-team-directory: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function bindListFilter(inputSelector, rowSelector) {
    const input = document.querySelector(inputSelector);
    const rows = Array.from(document.querySelectorAll(rowSelector));
    if (!input || !rows.length) return;
    input.addEventListener('input', () => {
      const query = normalize(input.value);
      rows.forEach((row) => {
        const haystack = normalize(row.dataset.search || row.textContent || '');
        row.hidden = query ? !haystack.includes(query) : false;
      });
    });
  }

  const deckListSearch = document.querySelector('[data-deck-list-search]');
  const deckListFormat = document.querySelector('[data-deck-list-format-filter]');
  const deckListDivision = document.querySelector('[data-deck-list-division-filter]');
  const deckListTeam = document.querySelector('[data-deck-list-team-filter]');
  const deckListTier = document.querySelector('[data-deck-list-tier-filter]');
  const deckListActivity = document.querySelector('[data-deck-list-activity-filter]');
  const deckListOrder = document.querySelector('[data-deck-list-order]');
  const deckListCards = document.querySelector('[data-deck-list-cards-filter]');
  const deckListClear = document.querySelector('[data-deck-list-clear]');
  const deckListVisibleCount = document.querySelector('[data-deck-list-visible-count]');
  const deckListRows = Array.from(document.querySelectorAll('[data-deck-list-row]'));

  function applyDeckListFilters() {
    const query = normalize(deckListSearch?.value || '');
    const format = normalize(deckListFormat?.value || '');
    const division = normalize(deckListDivision?.value || '');
    const team = normalize(deckListTeam?.value || '');
    const tier = normalize(deckListTier?.value || '');
    const activity = deckListActivity?.value || '';
    const order = deckListOrder?.value || 'rank';
    const cards = deckListCards?.value || '';
    document.querySelectorAll('[data-deck-loaded-filter]').forEach(button=>button.setAttribute('aria-pressed',String(cards==='with_cards')));
    let visible = 0;
    deckListRows.forEach((row) => {
      const matches = Number(row.dataset.matches || 0);
      const show = (!query || normalize(row.dataset.search || row.textContent || '').includes(query))
        && (!format || normalize(row.dataset.format || '').includes(format))
        && (!division || normalize(row.dataset.division || '').includes(division))
        && (!team || normalize(row.dataset.team || '') === team)
        && (!tier || normalize(row.dataset.tier || 'sin tier') === tier)
        && (!activity || (activity === 'with_results' ? matches > 0 : matches === 0))
        && (!cards || (cards === 'with_cards' ? row.dataset.hasCards === '1' : row.dataset.hasCards !== '1'));
      row.hidden = !show;
      if (show) visible++;
    });
    const number = (row, key) => Number(row.dataset[key] || 0);
    deckListRows.slice().sort((left, right) => {
      if (order === 'name') return String(left.dataset.name || '').localeCompare(String(right.dataset.name || ''));
      if (order === 'matches') return number(right, 'matches') - number(left, 'matches') || number(left, 'rank') - number(right, 'rank');
      if (order === 'winrate') return number(right, 'winrate') - number(left, 'winrate') || number(right, 'matches') - number(left, 'matches');
      return number(left, 'rank') - number(right, 'rank');
    }).forEach((row) => row.parentElement?.appendChild(row));
    if (deckListVisibleCount) deckListVisibleCount.textContent = `${visible} de ${deckListRows.length} decks`;
  }

  [deckListSearch, deckListFormat, deckListDivision, deckListTeam, deckListTier, deckListActivity, deckListCards, deckListOrder].filter(Boolean).forEach((control) => {
    control.addEventListener(control === deckListSearch ? 'input' : 'change', applyDeckListFilters);
  });
  document.querySelectorAll('[data-deck-loaded-filter]').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();if(!deckListCards)return;deckListCards.value=deckListCards.value==='with_cards'?'':'with_cards';applyDeckListFilters();}));
  deckListClear?.addEventListener('click', () => {
    [deckListSearch, deckListFormat, deckListDivision, deckListTeam, deckListTier, deckListActivity, deckListCards].filter(Boolean).forEach((control) => { control.value = ''; });
    if (deckListOrder) deckListOrder.value = 'rank';
    applyDeckListFilters();
  });
  [search, deckListSearch].filter(Boolean).forEach((input) => {
    if (String(input.value || '').trim().toLowerCase() === 'null') input.value = '';
    if (String(input.placeholder || '').trim().toLowerCase() === 'null') input.placeholder = input === search ? 'Buscar decks, equipos o eventos' : 'Deck, equipo o arquetipo';
  });
  const teamListSearch = document.querySelector('[data-team-list-search]');
  const teamListFormat = document.querySelector('[data-team-list-format]');
  const teamListDivision = document.querySelector('[data-team-list-division]');
  const teamListRows = Array.from(document.querySelectorAll('[data-team-list-row]'));

  function teamListCompetitiveStats(team) {
    const decks = (data.allDecks || []).filter((deck) => String(deck.team_id || '') === String(team.id || ''));
    const ids = new Set(decks.map((deck) => String(deck.id || '')));
    const names = new Set(decks.map((deck) => normalize(deck.name || '')));
    const formats = new Set(decks.flatMap((deck) => Array.isArray(deck.format_codes) ? deck.format_codes : []));
    const divisions = new Set(decks.map((deck) => String(deck.division || '')).filter(Boolean));
    const totals = { matches: 0, wins: 0, draws: 0, losses: 0, duelWins: 0, duelDraws: 0, duelLosses: 0 };
    individualMatchList().forEach((match) => {
      const sideA = [match.deck_a_id, match.deck_c_id, match.deck_e_id].some((id) => ids.has(String(id || ''))) || matchSideDecks(match, 'a').some((name) => names.has(normalize(name)));
      const sideB = [match.deck_b_id, match.deck_d_id, match.deck_f_id].some((id) => ids.has(String(id || ''))) || matchSideDecks(match, 'b').some((name) => names.has(normalize(name)));
      if (!sideA && !sideB) return;
      const competition = statsCompetition(match) || {};
      const format = String(match.competition_ruleset || competition.event_ruleset || '').trim();
      if (format) formats.add(format);
      const result = evaluateDuel(getSavedResult(match).filter(Boolean), match);
      if (result.state !== 'FINALIZADA') return;
      const side = sideA ? 'A' : 'B';
      totals.matches += 1;
      if (result.winner === side) totals.wins += 1;
      else if (result.winner === 'E') totals.draws += 1;
      else totals.losses += 1;
      getSavedResult(match).filter(Boolean).forEach((winner) => {
        if (winner === side) totals.duelWins += 1;
        else if (winner === 'E') totals.duelDraws += 1;
        else totals.duelLosses += 1;
      });
    });
    totals.winRate = totals.matches ? Math.round((totals.wins / totals.matches) * 100) : 0;
    totals.formats = Array.from(formats);
    totals.divisions = Array.from(divisions);
    return totals;
  }

  function renderTeamList() {
    const query = normalize(teamListSearch?.value || '');
    const format = String(teamListFormat?.value || '');
    const division = String(teamListDivision?.value || '');
    teamListRows.forEach((row) => {
      const team = (data.teams || []).find((item) => String(item.id || '') === String(row.dataset.teamId || ''));
      if (!team) return;
      const stats = teamListCompetitiveStats(team);
      const set = (selector, value) => { const node = row.querySelector(selector); if (node) node.textContent = String(value); };
      set('[data-team-list-formats]', stats.formats.length ? stats.formats.map((value) => value.replaceAll('_', ' ').toUpperCase()).join(', ') : 'Sin formato');
      set('[data-team-list-divisions]', stats.divisions.join(', ') || 'Sin division');
      set('[data-team-list-matches]', stats.matches);
      set('[data-team-list-match-record]', `${stats.wins} / ${stats.draws} / ${stats.losses}`);
      set('[data-team-list-duel-record]', `${stats.duelWins} / ${stats.duelDraws} / ${stats.duelLosses}`);
      set('[data-team-list-winrate]', `${stats.winRate}%`);
      const haystack = normalize(`${row.dataset.search || ''} ${stats.formats.join(' ')} ${stats.divisions.join(' ')}`);
      row.hidden = Boolean((query && !haystack.includes(query)) || (format && !stats.formats.includes(format)) || (division && !stats.divisions.includes(division)));
    });
  }

  [teamListSearch, teamListFormat, teamListDivision].forEach((control) => control?.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', renderTeamList));
  document.querySelector('[data-team-list-clear]')?.addEventListener('click', () => {
    if (teamListSearch) teamListSearch.value = '';
    if (teamListFormat) teamListFormat.value = '';
    if (teamListDivision) teamListDivision.value = '';
    renderTeamList();
  });
  renderTeamList();

  const logoHistoryNode = document.querySelector('[data-logo-history-modal]');
  const logoHistoryModal = logoHistoryNode && window.bootstrap ? new bootstrap.Modal(logoHistoryNode) : null;
  document.querySelectorAll('[data-open-logo-history]').forEach((button) => {
    button.addEventListener('click', () => {
      const entityType = button.dataset.openLogoHistory || '';
      const entityId = entityType === 'deck'
        ? document.querySelector('[data-gestion-deck-id]')?.value
        : (entityType === 'team' ? document.querySelector('[data-gestion-team-id]')?.value : activeCompetitionConfigId);
      if (!entityId) {
        window.alert('Guarda o selecciona una ficha antes de consultar su historial de logos.');
        return;
      }
      const rows = (data.logoHistory || []).filter((row) => String(row.entity_type || '') === entityType && String(row.entity_id || '') === String(entityId));
      const title = logoHistoryNode?.querySelector('[data-logo-history-title]');
      const grid = logoHistoryNode?.querySelector('[data-logo-history-grid]');
      if (title) title.textContent = `Historial de logos de ${entityType === 'deck' ? 'deck' : (entityType === 'team' ? 'equipo' : 'evento')}`;
      if (grid) grid.innerHTML = rows.length ? rows.map((row) => `
        <form method="post" class="logo-history-item">
          <input type="hidden" name="action" value="select_logo_history">
          <input type="hidden" name="logo_entity_type" value="${escapeHtml(entityType)}">
          <input type="hidden" name="logo_entity_id" value="${escapeHtml(entityId)}">
          <input type="hidden" name="logo_history_url" value="${escapeHtml(row.logo_url || '')}">
          <img src="${escapeHtml(row.logo_url || '')}" alt="Logo guardado" loading="lazy">
          <span><b>${escapeHtml(row.created_at || '')}</b><small>${escapeHtml(row.source || 'guardado')}</small></span>
          <button class="btn btn-outline-info btn-sm" type="submit"><i class="bi bi-check2-circle"></i> Usar este logo</button>
        </form>`).join('') : '<p class="note">Esta ficha todavia no tiene logos guardados.</p>';
      logoHistoryModal?.show();
    });
  });

  const adminDeckSelect = document.querySelector('[data-gestion-deck-select]');
  const adminRemoveDeckId = document.querySelector('[data-gestion-remove-deck-id]');
  if (adminDeckSelect) {
    adminDeckSelect.addEventListener('change', () => {
      const deck = (data.allDecks || []).find((item) => String(item.id) === adminDeckSelect.value);
      setValue('[data-gestion-deck-id]', deck ? deck.id : '');
      setValue('[data-gestion-deck-name]', deck ? deck.name : '');
      setValue('[data-gestion-deck-team]', deck ? deck.team_id : '');
      setValue('[data-gestion-deck-team-label]', deck ? (deck.team || 'Sin equipo') : 'Sin equipo');
      setValue('[data-gestion-deck-division]', deck ? deck.division_id : '');
      setValue('[data-gestion-deck-rank]', deck ? deck.rank : '999');
      setValue('[data-gestion-deck-style]', deck ? deck.style : '');
      setValue('[data-gestion-deck-archetype]', deck ? deck.archetype : '');
      setValue('[data-gestion-deck-reason]', deck ? deck.reason : '');
      setValue('[data-gestion-deck-cards]', deck ? deck.key_cards : '');
      setValue('[data-gestion-deck-variations]', deck ? deck.variations : '');
      setValue('[data-gestion-deck-notes]', deck ? deck.notes : '');
      setValue('[data-gestion-deck-cover]', deck ? deck.cover_url : '');
      if (adminRemoveDeckId) adminRemoveDeckId.value = deck ? deck.id : '';
    });
  }

  const adminCompetitionSelect = document.querySelector('[data-gestion-competition-select]');
  const adminArchiveCompetitionId = document.querySelector('[data-gestion-archive-competition-id]');
  const adminCompetitionType = document.querySelector('[data-gestion-competition-type]');
  const adminCompetitionTemplate = document.querySelector('[data-gestion-competition-template]');
  const adminCompetitionDivision = document.querySelector('[data-gestion-competition-division]');
  const adminCompetitionRuleset = document.querySelector('[data-gestion-competition-ruleset]');
  const competitionNameInput = document.querySelector('[data-gestion-competition-name]');
  const competitionCodePreview = document.querySelector('[data-competition-code-preview]');
  const rulesetSelect = document.querySelector('[data-ruleset-select]');
  const rulesetByCode = new Map((data.rulesets || []).map((ruleset) => [String(ruleset.code || ''), ruleset]));
