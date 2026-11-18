/**
 * deck-metrics: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function fillDeckSelect() {
    if (!deckSelect) return;
    deckSelect.innerHTML = data.allDecks.map((deck) => `<option value="${deck.name}">${deck.name} - ${deck.division}</option>`).join('');
    const requestedId = new URLSearchParams(window.location.search).get('deck_id') || '';
    const requestedDeck = data.allDecks.find((deck) => String(deck.id || '') === requestedId);
    const deckWithResults = data.allDecks.find((deck) => allMatchList().some((match) => {
      const participates = [match.deck_a_id, match.deck_b_id, match.deck_c_id, match.deck_d_id, match.deck_e_id, match.deck_f_id].some((id) => String(id || '') === String(deck.id || ''));
      return participates && evaluateDuel(getSavedResult(match), match).state === 'FINALIZADA';
    }));
    if (requestedDeck) deckSelect.value = requestedDeck.name;
    else if (deckWithResults) deckSelect.value = deckWithResults.name;
    deckSelect.addEventListener('change', renderProfile);
  }

  function deckProfileCompetitiveStats(deck) {
    if(data.statisticsV8&&window.YgoStatistics)return window.YgoStatistics.compatProfile(deck);
    const summary = { points: 0, matches: 0, wins: 0, draws: 0, losses: 0, duelWins: 0, duelDraws: 0, duelLosses: 0, damageDealt: 0, damageReceived: 0, lpCovered: 0, lpTotal: 0, firstStarts: 0, firstWins: 0, secondStarts: 0, secondWins: 0, brickHands: 0, brickWins: 0, turnsRecorded: 0, totalTurns: 0, fastestWinTurns: 0, durationRecorded: 0, totalDuration: 0, fastestWinSeconds: 0, surrenderWins: 0, surrenderLosses: 0, lifePointWins: 0, lifePointLosses: 0, deckOutWins: 0, timeWins: 0, effectWins: 0, flawlessWins: 0, perfectMatches: 0, dicePlayed: 0, diceWins: 0, d5Played: 0, d5Wins: 0, comebackWins: 0, reverseSweeps: 0, bestMatchStreak: 0, opponents: new Map(), formats: new Set() };
    (deck.format_codes || []).forEach((format) => summary.formats.add(String(format).replaceAll('_', ' ').toUpperCase()));
    (data.competitions || []).forEach((competition) => {
      if(competition.teamConfig)return;
      const row = (competition.ranking || []).find((item) => String(item.id || item.deck_id || '') === String(deck.id || '') || normalize(item.name || '') === normalize(deck.name));
      if (row) summary.points += Number(row.pts || row.points || 0);
    });
    individualMatchList().forEach((match) => {
      const onA = matchSideDecks(match, 'a').includes(deck.name) || [match.deck_a_id, match.deck_c_id, match.deck_e_id].some((id) => String(id || '') === String(deck.id || ''));
      const onB = matchSideDecks(match, 'b').includes(deck.name) || [match.deck_b_id, match.deck_d_id, match.deck_f_id].some((id) => String(id || '') === String(deck.id || ''));
      if (!onA && !onB) return;
      const competition = (data.competitions || []).find((item) => String(item.id || '') === String(match.competition_id || '')) || {};
      const format = match.competition_ruleset || competition.event_ruleset || '';
      if (format) summary.formats.add(String(format).replaceAll('_', ' ').toUpperCase());
      const values = getSavedResult(match).filter(Boolean);
      const result = evaluateDuel(values, match);
      if (result.state !== 'FINALIZADA') return;
      const side = onA ? 'A' : 'B';
      summary.matches += 1;
      if (result.winner === side) summary.wins += 1;
      else if (result.winner === 'E') summary.draws += 1;
      else summary.losses += 1;
      if (result.winner === side && !values.some((winner) => winner !== side)) summary.perfectMatches += 1;
      const rivalSide = side === 'A' ? 'B' : 'A';
      if (values[4]) { summary.d5Played += 1; if (values[4] === side) summary.d5Wins += 1; }
      if (result.winner === side && values[0] === rivalSide) summary.comebackWins += 1;
      if (result.winner === side && values[0] === rivalSide && values[1] === rivalSide && values.slice(2).filter(Boolean).every((winner) => winner === side)) summary.reverseSweeps += 1;
      const rivalNameForMatch = matchSideDecks(match, side === 'A' ? 'b' : 'a')[0] || 'Rival sin identificar';
      const matchRival = summary.opponents.get(rivalNameForMatch) || { name: rivalNameForMatch, matchWins: 0, matchLosses: 0, matchDraws: 0, wins: 0, losses: 0, draws: 0, duels: 0 };
      if (result.winner === side) matchRival.matchWins += 1;
      else if (result.winner === 'E') matchRival.matchDraws += 1;
      else matchRival.matchLosses += 1;
      summary.opponents.set(rivalNameForMatch, matchRival);
      const details = parseJsonValue(match.duel_details || '', []);
      values.forEach((winner, index) => {
        const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === index + 1) || {}) : {};
        const attributedId = Number(side === 'A' ? detail.deck_a_id : detail.deck_b_id);
        if (attributedId && attributedId !== Number(deck.id)) return;
        summary.lpTotal += 1;
        if (winner === side) summary.duelWins += 1;
        else if (winner === 'E') summary.duelDraws += 1;
        else summary.duelLosses += 1;
        const brick = side === 'A' ? Boolean(detail.brick_a) : Boolean(detail.brick_b);
        if (brick) summary.brickHands += 1;
        if (brick && winner === side) summary.brickWins += 1;
        const turnCount = Math.max(0, Number(detail.turn_count || 0));
        if (turnCount) {
          summary.turnsRecorded += 1;
          summary.totalTurns += turnCount;
          if (winner === side && (!summary.fastestWinTurns || turnCount < summary.fastestWinTurns)) summary.fastestWinTurns = turnCount;
        }
        const duration = Math.max(0, Number(detail.duration_seconds || 0));
        if (duration) {
          summary.durationRecorded += 1;
          summary.totalDuration += duration;
          if (winner === side && (!summary.fastestWinSeconds || duration < summary.fastestWinSeconds)) summary.fastestWinSeconds = duration;
        }
        const method = String(detail.defeat_method || '');
        if (method === 'surrender') winner === side ? summary.surrenderWins += 1 : (winner !== 'E' ? summary.surrenderLosses += 1 : 0);
        if (method === 'life_points') winner === side ? summary.lifePointWins += 1 : (winner !== 'E' ? summary.lifePointLosses += 1 : 0);
        if (method === 'deck_out' && winner === side) summary.deckOutWins += 1;
        if (method === 'time_limit' && winner === side) summary.timeWins += 1;
        if (method === 'effect_win' && winner === side) summary.effectWins += 1;
        const diceOwn = Number(side === 'A' ? detail.dice_a : detail.dice_b);
        const diceRival = Number(side === 'A' ? detail.dice_b : detail.dice_a);
        if (diceOwn > 0 && diceRival > 0) { summary.dicePlayed += 1; if (diceOwn > diceRival) summary.diceWins += 1; }
        const rivalId = Number(side === 'A' ? detail.deck_b_id : detail.deck_a_id);
        const rivalDeck = (data.allDecks || []).find((item) => Number(item.id) === rivalId);
        const rivalName = rivalDeck?.name || matchSideDecks(match, side === 'A' ? 'b' : 'a')[0] || 'Rival sin identificar';
        const rivalStat = summary.opponents.get(rivalName) || { name: rivalName, matchWins: 0, matchLosses: 0, matchDraws: 0, wins: 0, losses: 0, draws: 0, duels: 0 };
        rivalStat.duels += 1;
        if (winner === side) rivalStat.wins += 1;
        else if (winner === 'E') rivalStat.draws += 1;
        else rivalStat.losses += 1;
        summary.opponents.set(rivalName, rivalStat);
        const starts = String(detail.starts_player || (index === 0 ? match.starts_player || '' : '')).toUpperCase();
        if (starts) {
          const first = starts === side;
          if (first) { summary.firstStarts += 1; if (winner === side) summary.firstWins += 1; }
          else { summary.secondStarts += 1; if (winner === side) summary.secondWins += 1; }
        }
        const ownLpKey = side === 'A' ? 'lp_a' : 'lp_b';
        const rivalLpKey = side === 'A' ? 'lp_b' : 'lp_a';
        const ownStartKey = side === 'A' ? 'lp_start_a' : 'lp_start_b';
        const rivalStartKey = side === 'A' ? 'lp_start_b' : 'lp_start_a';
        const ownLp = Number(detail[ownLpKey]);
        const rivalLp = Number(detail[rivalLpKey]);
        const hasOwnLp = detail[ownLpKey] !== '' && detail[ownLpKey] !== null && detail[ownLpKey] !== undefined && Number.isFinite(ownLp);
        const hasRivalLp = detail[rivalLpKey] !== '' && detail[rivalLpKey] !== null && detail[rivalLpKey] !== undefined && Number.isFinite(rivalLp);
        if (hasOwnLp && hasRivalLp) summary.lpCovered += 1;
        const history = Array.isArray(detail.lp_history) ? detail.lp_history : [];
        const gross = (historySide) => history.filter((item) => item.side === historySide && item.operation !== 'reset').reduce((sum, item) => sum + Math.max(0, Number(item.before || 0) - Number(item.after || 0)), 0);
        const ownHistorySide = side === 'A' ? 'a' : 'b';
        const rivalHistorySide = side === 'A' ? 'b' : 'a';
        if (hasOwnLp) summary.damageReceived += gross(ownHistorySide) || Math.max(0, Number(detail[ownStartKey] || 8000) - ownLp);
        else if (detail.defeat_method === 'life_points' && winner !== side && winner !== 'E') summary.damageReceived += Number(detail[ownStartKey] || 8000);
        if (hasRivalLp) summary.damageDealt += gross(rivalHistorySide) || Math.max(0, Number(detail[rivalStartKey] || 8000) - rivalLp);
        else if (detail.defeat_method === 'life_points' && winner === side) summary.damageDealt += Number(detail[rivalStartKey] || 8000);
        if (winner === side && hasOwnLp && ownLp === Number(detail[ownStartKey] || 8000)) summary.flawlessWins += 1;
      });
    });
    summary.winRate = summary.matches ? Math.round((summary.wins / summary.matches) * 100) : 0;
    if(data.statisticsV8)Object.assign(summary,window.YgoStatistics?.profileMetric(deck)||{});
    const playedDuels = summary.duelWins + summary.duelDraws + summary.duelLosses;
    summary.duelWinRate = playedDuels ? Math.round((summary.duelWins / playedDuels) * 100) : 0;
    summary.firstWinRate = summary.firstStarts ? Math.round((summary.firstWins / summary.firstStarts) * 100) : 0;
    summary.secondWinRate = summary.secondStarts ? Math.round((summary.secondWins / summary.secondStarts) * 100) : 0;
    summary.brickRate = playedDuels ? Math.round((summary.brickHands / playedDuels) * 100) : 0;
    summary.averageTurns = summary.turnsRecorded ? Math.round((summary.totalTurns / summary.turnsRecorded) * 10) / 10 : 0;
    summary.averageDuration = summary.durationRecorded ? Math.round(summary.totalDuration / summary.durationRecorded) : 0;
    let currentStreak = 0;
    individualMatchList().filter((match) => {
      return matchSideDecks(match, 'a').includes(deck.name) || matchSideDecks(match, 'b').includes(deck.name)
        || [match.deck_a_id, match.deck_b_id, match.deck_c_id, match.deck_d_id, match.deck_e_id, match.deck_f_id].some((id) => String(id || '') === String(deck.id || ''));
    }).sort((a, b) => String(a.scheduled_on || '').localeCompare(String(b.scheduled_on || '')) || Number(a.id || 0) - Number(b.id || 0)).forEach((match) => {
      const result = evaluateDuel(getSavedResult(match).filter(Boolean), match);
      if (result.state !== 'FINALIZADA') return;
      const side = matchSideDecks(match, 'a').includes(deck.name) || [match.deck_a_id, match.deck_c_id, match.deck_e_id].some((id) => String(id || '') === String(deck.id || '')) ? 'A' : 'B';
      if (result.winner === side) { currentStreak += 1; summary.bestMatchStreak = Math.max(summary.bestMatchStreak, currentStreak); }
      else currentStreak = 0;
    });
    const rivals = Array.from(summary.opponents.values());
    summary.rivalsBeaten = rivals.filter((row) => row.matchWins > 0).length;
    summary.rivalsFaced = rivals.filter((row) => row.duels > 0 || row.matchWins > 0 || row.matchLosses > 0 || row.matchDraws > 0).length;
    summary.topBeaten = rivals.filter((row) => row.matchWins).sort((a, b) => b.matchWins - a.matchWins || b.wins - a.wins || a.name.localeCompare(b.name)).slice(0, 3);
    summary.topLostTo = rivals.filter((row) => row.matchLosses).sort((a, b) => b.matchLosses - a.matchLosses || b.losses - a.losses || a.name.localeCompare(b.name)).slice(0, 3);
    summary.formatLabel = Array.from(summary.formats).join(', ') || 'Sin registros';
    return summary;
  }

  function globalDeckRanking() {
    return (data.allDecks || []).map((deck) => ({ deck, stat: deckProfileCompetitiveStats(deck) }))
      .sort((left, right) =>
        right.stat.points - left.stat.points
        || right.stat.wins - left.stat.wins
        || right.stat.winRate - left.stat.winRate
        || right.stat.duelWins - left.stat.duelWins
        || (right.stat.duelWins - right.stat.duelLosses) - (left.stat.duelWins - left.stat.duelLosses)
        || String(left.deck.name || '').localeCompare(String(right.deck.name || ''))
      );
  }

  const deckProfileTabs = Array.from(document.querySelectorAll('[data-deck-profile-tab]'));
  const deckProfileViews = Array.from(document.querySelectorAll('[data-deck-profile-view]'));
  const matchupOpponent = document.querySelector('[data-matchup-opponent]');
  const matchupPeriod = document.querySelector('[data-matchup-period]');
  const matchupEvent = document.querySelector('[data-matchup-event]');
  const matchupDivision = document.querySelector('[data-matchup-division]');
  const matchupRulesetVersion = document.querySelector('[data-matchup-ruleset-version]');
  const matchupVersion = document.querySelector('[data-matchup-version]');
  const matchupOpponentVersion = document.querySelector('[data-matchup-opponent-version]');
  const matchupPosition = document.querySelector('[data-matchup-position]');
  let deckAnalysisFiltersReady = false;

  const deckCategoryOrder = ['performance', 'history', 'events', 'decklist', 'ranking', 'achievements', 'strategy', 'created-combos', 'play-calculator', 'matchups', 'edit'];
  const deckCategoryMeta = {
    performance: ['Rendimiento competitivo', 'Partidas, duelos, ritmo, puntos de vida y formas de cierre.'],
    history: ['Historial y agenda', 'Programacion, rivales, resultados y acceso a cada partida.'],
    events: ['Historial de eventos', 'Eventos, temporadas, participación y acceso a la ficha de cada evento.'],
    decklist: ['Decklist y multimedia', 'Main Deck, Extra Deck, Side Deck, cartas clave, variaciones y archivos.'],
    ranking: ['Ranking y tier', 'Posicion actual, tier, mejor registro historico y cambios persistidos.'],
    achievements: ['Logros', 'Titulos, subcampeonatos, medallas y progreso competitivo.'],
    strategy: ['Guía de juego', 'Aprende las cartas, recorre los combos y prepara tus enfrentamientos.'],
    'created-combos': ['Mis combos y estrategias', 'Gu\u00edas visuales, combos documentados y creaciones de este deck.'],
    'play-calculator': ['Calculador de jugadas', 'Reproduce el estado del duelo y consulta opciones documentadas, con cálculo local.'],
    matchups: ['Matchups', 'Comparacion entre teoria estrategica y resultados observados por rival.'],
    edit: ['Editar deck', 'Datos principales, division, arquetipo, logo y notas administrativas.']
  };
  const deckCategoryModalNode = document.querySelector('[data-deck-category-modal]');
  const deckCategoryModal = deckCategoryModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(deckCategoryModalNode) : null;
  const deckCategoryBody = document.querySelector('[data-deck-category-body]');
  const deckCategoryStaging = document.querySelector('[data-deck-category-staging]');
  const deckCategoryTitle = document.querySelector('[data-deck-category-title]');
  const deckCategorySubtitle = document.querySelector('[data-deck-category-subtitle]');
  const deckCategoryPosition = document.querySelector('[data-deck-category-position]');
  let activeDeckCategory = '';
  const deckDetailPanel = document.querySelector('.deck-profile-detail-panel');
  const deckAnalysisScreen = document.querySelector('.deck-analysis-screen');
  const deckStrategyProfile = deckAnalysisScreen?.querySelector('.deck-strategy-profile');
  const deckAnalysisHeader = deckAnalysisScreen?.querySelector('.deck-analysis-header');
  const deckCategoryNodes = {
    performance: [deckDetailPanel?.querySelector(':scope > .panel-head'), deckDetailPanel?.querySelector('.deck-record-overview'), deckDetailPanel?.querySelector('.profile-stats'), deckDetailPanel?.querySelector('[data-profile-rivals]')].filter(Boolean),
    history: [deckDetailPanel?.querySelector('.deck-history-section')].filter(Boolean),
    events: [deckDetailPanel?.querySelector('[data-profile-events]')].filter(Boolean),
    decklist: [deckDetailPanel?.querySelector('[data-card-gallery]'), deckDetailPanel?.querySelector('.deck-detail-grid')].filter(Boolean),
    ranking: [deckDetailPanel?.querySelector('.tier-history-summary')].filter(Boolean),
    achievements: [deckDetailPanel?.querySelector('.deck-achievement-layout')].filter(Boolean),
    strategy: [deckAnalysisHeader, deckStrategyProfile].filter(Boolean),
    'created-combos': [document.querySelector('.deck-created-combos-panel')].filter(Boolean),
    'play-calculator': [document.querySelector('.play-calculator-panel')].filter(Boolean),
    matchups: deckAnalysisScreen ? Array.from(deckAnalysisScreen.children).filter((node) => node !== deckAnalysisHeader && node !== deckStrategyProfile) : [],
    edit: [document.querySelector('.deck-editor-panel')].filter(Boolean)
  };

  if (deckCategoryStaging && deckCategoryBody) {
    deckCategoryOrder.flatMap((key) => deckCategoryNodes[key]).forEach((node) => deckCategoryStaging.appendChild(node));
    if (deckDetailPanel) deckDetailPanel.hidden = true;
  }

  window.addEventListener('stationx:combo-edit', () => deckCategoryModal?.hide());

