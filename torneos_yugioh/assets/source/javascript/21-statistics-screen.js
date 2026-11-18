/**
 * statistics-screen: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function renderStatsV2() {
    if (!statsBody) return;
    populateStatsFilters();
    const model = buildStatsModel();
    renderDataQuality(model);
    const context = model.context;
    const matches = model.matches;
    const decks = model.decks;
    const teams = model.teams;
    const finalized = matches.filter((match) => evaluateDuel(getSavedResult(match), match).state === 'FINALIZADA');
    const playedDuels = finalized.reduce((total, match) => total + getSavedResult(match).filter(Boolean).length, 0);
    const setText = (selector, value) => { const node = document.querySelector(selector); if (node) node.textContent = String(value); };
    setText('[data-stats-kpi="events"]', new Set(matches.map((match) => String(match.competition_id || 'general'))).size);
    setText('[data-stats-kpi="matches"]', matches.length);
    setText('[data-stats-kpi="finalized"]', finalized.length);
    setText('[data-stats-kpi="duels"]', playedDuels);
    setText('[data-stats-kpi="decks"]', decks.length);
    setText('[data-stats-kpi="teams"]', teams.length);
    const lpCoverage = finalized.reduce((coverage, match) => {
      const details = parseJsonValue(match.duel_details || '', []);
      getSavedResult(match).filter(Boolean).forEach((winner, index) => {
        coverage.total += 1;
        const detail = Array.isArray(details) ? details.find((item) => Number(item.duel) === index + 1) : null;
        const hasA = detail && detail.lp_a !== '' && detail.lp_a !== null && detail.lp_a !== undefined;
        const hasB = detail && detail.lp_b !== '' && detail.lp_b !== null && detail.lp_b !== undefined;
        if (hasA && hasB) coverage.covered += 1;
      });
      return coverage;
    }, { covered: 0, total: 0 });
    setText('[data-stats-kpi="lpCoverage"]', `${lpCoverage.covered}/${lpCoverage.total}`);
    const scope = [];
    if (context.event) scope.push(((data.competitions || []).find((item) => String(item.id) === context.event) || {}).name || 'Evento');
    if (context.ruleset) scope.push(context.ruleset.replaceAll('_', ' ').toUpperCase());
    if (context.type) scope.push(context.type.replaceAll('_', ' ').toUpperCase());
    if (context.season) scope.push(((data.seasons || []).find((season) => String(season.id) === context.season) || {}).name || ('Temporada ' + context.season));
    if (context.deck) scope.push(context.deck);
    if (context.team) scope.push(context.team);
    if (context.player) scope.push(((data.players || []).find((player) => String(player.id) === context.player) || {}).alias || 'Jugador');
    if (context.from || context.to) scope.push((context.from || 'Inicio') + ' a ' + (context.to || 'hoy'));
    setText('[data-stats-scope]', scope.join(' | ') || 'Todos los eventos competitivos');
    setText('[data-stats-sample-note]', 'Minimo ' + context.sample + ' matches finalizados.');

    const rankingKey = (analyticsRanking && analyticsRanking.value) || 'points';
    const sorters = {
      points: (a, b) => b.points - a.points || b.matchWins - a.matchWins || b.winRate - a.winRate || b.duelWins - a.duelWins || b.duelDiff - a.duelDiff || a.name.localeCompare(b.name),
      matchWins: (a, b) => b.matchWins - a.matchWins || b.winRate - a.winRate,
      winRate: (a, b) => b.winRate - a.winRate || b.matches - a.matches,
      duelWins: (a, b) => b.duelWins - a.duelWins || b.duelDiff - a.duelDiff,
      duelDiff: (a, b) => b.duelDiff - a.duelDiff || b.duelWins - a.duelWins,
      firstWinRate: (a, b) => b.firstWinRate - a.firstWinRate || b.firstStarts - a.firstStarts,
      secondWinRate: (a, b) => b.secondWinRate - a.secondWinRate || b.secondStarts - a.secondStarts,
      fastestTurns: (a, b) => (a.fastestTurns || Number.MAX_SAFE_INTEGER) - (b.fastestTurns || Number.MAX_SAFE_INTEGER) || b.duelWins - a.duelWins,
      fastestSeconds: (a, b) => (a.fastestSeconds || Number.MAX_SAFE_INTEGER) - (b.fastestSeconds || Number.MAX_SAFE_INTEGER) || b.duelWins - a.duelWins,
      sweeps: (a, b) => b.sweeps - a.sweeps || b.matchWins - a.matchWins,
      d5Wins: (a, b) => b.d5Wins - a.d5Wins || b.d5Played - a.d5Played
      ,brickHands: (a, b) => b.brickHands - a.brickHands || b.brickRate - a.brickRate
      ,averageTurns: (a, b) => (a.averageTurns || Number.MAX_SAFE_INTEGER) - (b.averageTurns || Number.MAX_SAFE_INTEGER)
      ,elo: (a, b) => b.elo - a.elo || b.matches - a.matches
      ,power5: (a, b) => b.power5 - a.power5 || b.matches - a.matches
      ,power10: (a, b) => b.power10 - a.power10 || b.matches - a.matches
      ,consistency: (a, b) => b.consistency - a.consistency || b.matches - a.matches
      ,strength: (a, b) => b.strength - a.strength || b.matches - a.matches
    };
    const sampleRankings = ['winRate', 'consistency', 'power5', 'power10', 'strength'];
    const ranked = (sampleRankings.includes(rankingKey) ? decks.filter((deck) => deck.matches >= context.sample) : decks).slice().sort(sorters[rankingKey] || sorters.points);
    const rankingValue = (row) => {
      if (rankingKey === 'elo' || rankingKey === 'strength') return Number(row[rankingKey] || 0);
      if (['power5', 'power10', 'consistency', 'winRate', 'firstWinRate', 'secondWinRate'].includes(rankingKey)) return Number(row[rankingKey] || 0) + '%';
      if (rankingKey === 'fastestSeconds') return row.fastestSeconds ? Math.floor(row.fastestSeconds / 60) + ':' + String(row.fastestSeconds % 60).padStart(2, '0') : 'N/D';
      if (rankingKey === 'fastestTurns' || rankingKey === 'averageTurns') return Number(row[rankingKey] || 0) || 'N/D';
      return Number(row[rankingKey] ?? row.points ?? 0);
    };
    const globalRanked = decks.slice().sort(sorters.points);
    if (statsGlobalPreview) {
      statsGlobalPreview.innerHTML = globalRanked.length ? globalRanked.slice(0, 10).map((row, index) => {
        const logo = row.logo ? '<img src="' + escapeHtml(row.logo) + '" alt="">' : '<span><i class="bi bi-collection"></i></span>';
        return '<tr><td data-label="#"><b>' + (index + 1) + '</b></td><td data-label="Deck / equipo"><div class="analytics-deck-cell">' + logo + '<div><b>' + escapeHtml(row.name) + '</b><small>' + escapeHtml(row.team) + '</small></div></div></td>'
          + '<td data-label="Formato">' + escapeHtml(row.ruleset) + '</td><td data-label="PTS"><b>' + row.points + '</b></td><td data-label="Partidas G/E/P">' + row.matchWins + ' / ' + row.matchDraws + ' / ' + row.matchLosses + '</td><td data-label="WR">' + row.winRate + '%</td>'
          + '<td data-label="Duelos G/E/P">' + row.duelWins + ' / ' + row.duelDraws + ' / ' + row.duelLosses + '</td><td data-label="DIF">' + (row.duelDiff > 0 ? '+' : '') + row.duelDiff + '</td></tr>';
      }).join('') : statsEmpty('No hay decks dentro del alcance seleccionado.', 8);
    }
    statsBody.innerHTML = ranked.length ? ranked.map((row, index) => {
      const logo = row.logo ? '<img src="' + escapeHtml(row.logo) + '" alt="">' : '<span><i class="bi bi-collection"></i></span>';
      const winRate = row.matches >= context.sample ? row.winRate + '%' : '<span title="Requiere ' + context.sample + ' matches">N/M</span>';
      return '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Equipo / Deck"><div class="analytics-deck-cell">' + logo + '<div><b>' + escapeHtml(row.name) + '</b><small>' + escapeHtml(row.team) + '</small></div></div></td>'
        + '<td data-label="Formato">' + escapeHtml(row.ruleset) + '</td><td data-label="PTS">' + row.points + '</td><td data-label="Valor ranking"><b>' + rankingValue(row) + '</b></td><td data-label="MJ">' + row.matches + '</td><td data-label="MG">' + row.matchWins + '</td><td data-label="ME">' + row.matchDraws + '</td><td data-label="MP">' + row.matchLosses + '</td>'
        + '<td data-label="WR">' + winRate + '</td><td data-label="WR 1ro">' + (row.firstStarts ? row.firstWinRate + '% (' + row.firstWins + '/' + row.firstStarts + ')' : 'N/D') + '</td><td data-label="WR 2do">' + (row.secondStarts ? row.secondWinRate + '% (' + row.secondWins + '/' + row.secondStarts + ')' : 'N/D') + '</td><td data-label="DJ">' + (row.duelWins + row.duelLosses + row.duelDraws) + '</td><td data-label="DG">' + row.duelWins + '</td><td data-label="DP">' + row.duelLosses + '</td><td data-label="DIF">' + row.duelDiff + '</td><td data-label="Brick">' + row.brickHands + ' (' + row.brickRate + '%)</td><td data-label="Prom. turnos">' + (row.averageTurns || 'N/D') + '</td><td data-label="2-0">' + row.sweeps + '</td><td data-label="D5">' + row.d5Wins + '</td><td data-label="Racha">' + (row.currentStreak ? 'W' + row.currentStreak : row.lastResult) + '</td></tr>';
    }).join('') : statsEmpty(sampleRankings.includes(rankingKey) ? 'No hay decks con la muestra minima de ' + context.sample + ' partidas.' : 'No hay resultados para el alcance seleccionado.', 21);

    const teamRankingBody = document.querySelector('[data-stats-team-ranking]');
    if (teamRankingBody) {
      const teamRanked = (sampleRankings.includes(rankingKey) ? teams.filter((team) => team.matches >= context.sample) : teams).slice().sort(sorters[rankingKey] || sorters.points);
      teamRankingBody.innerHTML = teamRanked.length ? teamRanked.map((team, index) => '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Equipo"><b>' + escapeHtml(team.name) + '</b></td><td data-label="PTS">' + team.points + '</td><td data-label="MJ">' + team.matches + '</td><td data-label="G/E/P">' + team.matchWins + ' / ' + team.matchDraws + ' / ' + team.matchLosses + '</td><td data-label="WR">' + team.winRate + '%</td><td data-label="Duelos G/E/P">' + team.duelWins + ' / ' + team.duelDraws + ' / ' + team.duelLosses + '</td><td data-label="Elo">' + team.elo + '</td><td data-label="Power 5">' + team.power5 + '%</td><td data-label="Consistencia">' + team.consistency + '%</td></tr>').join('') : statsEmpty('No hay equipos con la muestra requerida.', 10);
    }
    const playerRankingBody = document.querySelector('[data-stats-player-ranking]');
    if (playerRankingBody) {
      const playerRanked = model.playerStats.slice().sort((a, b) => b.points - a.points || b.wins - a.wins || b.matches - a.matches);
      playerRankingBody.innerHTML = playerRanked.length ? playerRanked.map((row, index) => {
        const linked = Array.from(row.deckIds).map((id) => (data.allDecks || []).find((deck) => Number(deck.id) === Number(id))).filter(Boolean);
        const rate = row.matches ? Math.round((row.wins / row.matches) * 100) : 0;
        const medals = (data.medalUnlocks || []).filter((unlock) => unlock.entity_type === 'player' && String(unlock.entity_id) === String(row.player.id)).length;
        return '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Jugador"><b>' + escapeHtml(row.player.alias || row.player.display_name) + '</b><small class="d-block">' + medals + ' medallas</small></td><td data-label="Equipo">' + escapeHtml(row.player.team_name || 'Sin equipo') + '</td><td data-label="Decks">' + (linked.map((deck) => escapeHtml(deck.name)).join(', ') || 'Sin deck') + '</td><td data-label="MJ">' + row.matches + '</td><td data-label="G/E/P">' + row.wins + ' / ' + row.draws + ' / ' + row.losses + '</td><td data-label="WR">' + rate + '%</td><td data-label="PTS">' + row.points + '</td></tr>';
      }).join('') : statsEmpty('No hay jugadores atribuidos directamente a los duelos de este alcance.', 8);
    }

    const byRate = decks.filter((deck) => deck.matches >= context.sample).sort((a, b) => b.winRate - a.winRate || b.matches - a.matches);
    const bySweep = decks.slice().sort((a, b) => b.sweeps - a.sweeps || b.matchWins - a.matchWins);
    const byD5 = decks.slice().sort((a, b) => b.d5Wins - a.d5Wins || b.d5Played - a.d5Played);
    const teamRows = teams.slice().sort((a, b) => b.points - a.points || b.duelDiff - a.duelDiff);
    if (statsBestEfficiency) statsBestEfficiency.textContent = byRate[0] ? byRate[0].team + ' / ' + byRate[0].name + ' (' + byRate[0].winRate + '%)' : 'Muestra insuficiente';
    if (statsBestSweep) statsBestSweep.textContent = bySweep[0] && bySweep[0].sweeps ? bySweep[0].team + ' / ' + bySweep[0].name + ' (' + bySweep[0].sweeps + ')' : 'Sin cierres perfectos';
    if (statsD5Pressure) statsD5Pressure.textContent = byD5[0] && byD5[0].d5Wins ? byD5[0].team + ' / ' + byD5[0].name + ' (' + byD5[0].d5Wins + ')' : 'Sin D5 finalizados';
    if (statsBestTeam) statsBestTeam.textContent = teamRows[0] ? teamRows[0].name + ' (' + teamRows[0].points + ' pts)' : 'Sin datos';
    renderCompactBars(statsTopBars, decks.slice().sort((a, b) => b.points - a.points).slice(0, 8).map((row) => ({ ...row, pts: row.points, division: row.ruleset })), 'pts');
    renderCompactBars(statsTeamBars, teamRows.slice(0, 8).map((row) => ({ ...row, pts: row.points, division: 'Equipo' })), 'pts');
    renderCompactBars(statsOffenseBars, decks.slice().sort((a, b) => b.duelWins - a.duelWins).slice(0, 8).map((row) => ({ ...row, cg: row.duelWins, division: row.ruleset })), 'cg');
    renderCompactBars(statsDefenseBars, decks.filter((row) => row.matches).sort((a, b) => a.duelLosses - b.duelLosses || b.matches - a.matches).slice(0, 8).map((row) => ({ ...row, cp: row.duelLosses, division: row.ruleset })), 'cp');
    if (statsTopSummary) statsTopSummary.textContent = Math.max(0, ...decks.map((row) => row.points)) + ' pts';
    if (statsMatchSummary) statsMatchSummary.textContent = String(matches.length);
    const formatCounts = matches.reduce((map, match) => {
      const competition = statsCompetition(match);
      const key = String(match.competition_ruleset || (competition && competition.event_ruleset) || 'general');
      map.set(key, (map.get(key) || 0) + 1);
      return map;
    }, new Map());
    const formatRows = Array.from(formatCounts.entries()).map((entry) => ({ name: entry[0].replaceAll('_', ' ').toUpperCase(), value: entry[1], division: 'Matches' })).sort((a, b) => b.value - a.value);
    renderCompactBars(statsDivisionBars, formatRows, 'value');
    if (statsDivisionSummary) statsDivisionSummary.textContent = formatRows.length + ' formatos';
    renderStatsComparator(decks);

    const statuses = matches.reduce((map, match) => {
      const state = evaluateDuel(getSavedResult(match), match).state;
      const key = state === 'FINALIZADA' ? 'FINALIZADA' : (state === 'PENDIENTE' ? 'PENDIENTE' : 'EN CURSO');
      map[key] = (map[key] || 0) + 1;
      return map;
    }, {});
    const total = Math.max(1, matches.length);
    const finalPct = Math.round(((statuses.FINALIZADA || 0) / total) * 100);
    const progressPct = Math.round(((statuses['EN CURSO'] || 0) / total) * 100);
    if (statsDonut) statsDonut.style.background = 'radial-gradient(circle, #07101f 0 48%, transparent 49%), conic-gradient(#35d27f 0 ' + finalPct + '%, #ffd25f ' + finalPct + '% ' + (finalPct + progressPct) + '%, #4f86ff ' + (finalPct + progressPct) + '% 100%)';
    if (statsDonutLegend) statsDonutLegend.innerHTML = '<span><i style="--accent:#35d27f"></i>Finalizados ' + (statuses.FINALIZADA || 0) + '</span><span><i style="--accent:#ffd25f"></i>En curso ' + (statuses['EN CURSO'] || 0) + '</span><span><i style="--accent:#4f86ff"></i>Pendientes ' + (statuses.PENDIENTE || 0) + '</span>';

    const matchupsBody = document.querySelector('[data-stats-matchups]');
    if (matchupsBody) matchupsBody.innerHTML = model.matchups.length ? model.matchups.sort((a, b) => b.matches - a.matches).map((row) => '<tr><td data-label="Deck A"><b>' + escapeHtml(row.a) + '</b></td><td data-label="Deck B"><b>' + escapeHtml(row.b) + '</b></td><td data-label="MJ">' + row.matches + '</td><td data-label="A gana">' + row.aWins + '</td><td data-label="B gana">' + row.bWins + '</td><td data-label="Empates">' + row.draws + '</td><td data-label="Duelos A-B">' + row.aDuels + '-' + row.bDuels + '</td><td data-label="WR A">' + Math.round((row.aWins / row.matches) * 100) + '%</td></tr>').join('') : statsEmpty('No hay enfrentamientos finalizados para estos filtros.', 8);
    const matchupMatrix = document.querySelector('[data-stats-matchup-matrix]');
    if (matchupMatrix) {
      const matrixDecks = model.decks.filter((deck) => deck.matches).sort((a, b) => a.name.localeCompare(b.name));
      matchupMatrix.innerHTML = matrixDecks.length ? '<table class="matchup-matrix"><thead><tr><th>Deck</th>' + matrixDecks.map((deck) => '<th title="' + escapeHtml(deck.name) + '">' + escapeHtml(deck.name) + '</th>').join('') + '</tr></thead><tbody>' + matrixDecks.map((rowDeck) => '<tr><th>' + escapeHtml(rowDeck.name) + '</th>' + matrixDecks.map((columnDeck) => {
        if (rowDeck.name === columnDeck.name) return '<td class="is-self">-</td>';
        const matchup = model.matchups.find((item) => [item.a, item.b].includes(rowDeck.name) && [item.a, item.b].includes(columnDeck.name));
        if (!matchup) return '<td class="is-empty">N/D</td>';
        const wins = matchup.a === rowDeck.name ? matchup.aWins : matchup.bWins;
        const rate = Math.round(((wins + matchup.draws * .5) / matchup.matches) * 100);
        const heat = rate >= 67 ? 'is-good' : rate <= 33 ? 'is-bad' : 'is-even';
        return '<td class="' + heat + '" title="' + escapeHtml(rowDeck.name + ' vs ' + columnDeck.name) + '"><b>' + rate + '%</b><small>' + matchup.matches + ' PJ</small></td>';
      }).join('') + '</tr>').join('') + '</tbody></table>' : '<div class="empty-state">Sin enfrentamientos suficientes para construir la matriz.</div>';
    }
    const duelBreakdown = document.querySelector('[data-stats-duel-breakdown]');
    if (duelBreakdown) duelBreakdown.innerHTML = model.duelPositions.map((slot) => '<article><span>D' + slot.position + '</span><b>' + slot.total + '</b><small>A ' + slot.a + ' | B ' + slot.b + (slot.draws ? ' | E ' + slot.draws : '') + '</small><div><i style="width:' + (slot.total ? Math.round((slot.a / slot.total) * 100) : 0) + '%"></i></div></article>').join('');
    const rivalRecords = document.querySelector('[data-stats-rival-records]');
    if (rivalRecords) {
      rivalRecords.innerHTML = decks.length ? decks.map((deck) => {
        const list = (rows, key, empty) => rows.length ? rows.map((row, index) => '<li><b>#' + (index + 1) + ' ' + escapeHtml(row.name) + '</b><span>' + row[key] + ' duelos</span></li>').join('') : '<li class="note">' + empty + '</li>';
        return '<article><h3>' + escapeHtml(deck.name) + '</h3><div><section><h4>Mas vencidos</h4><ol>' + list(deck.topBeaten, 'wins', 'Sin victorias') + '</ol></section><section><h4>Mas derrotas recibidas</h4><ol>' + list(deck.topLostTo, 'losses', 'Sin derrotas') + '</ol></section></div></article>';
      }).join('') : '<div class="empty-state">No hay duelos finalizados para calcular rivales frecuentes.</div>';
    }

    const availableMetaTypes = new Map();
    model.matches.forEach((match) => {
      const competition = statsCompetition(match);
      const code = String(match.competition_type || competition?.type || match.phase || 'general');
      availableMetaTypes.set(code, code.replaceAll('_', ' ').toUpperCase());
    });
    if (analyticsMetaEventType) {
      const previousType = analyticsMetaEventType.value;
      const typeCatalog = data.competitionTypeOptions || {};
      const configuredTypes = Object.entries(typeCatalog).map(([code, meta]) => [code, String(meta.label || code)]);
      availableMetaTypes.forEach((label, code) => {
        if (!configuredTypes.some((entry) => entry[0] === code)) configuredTypes.push([code, label]);
      });
      const withResults = configuredTypes.filter(([code]) => availableMetaTypes.has(code));
      const withoutResults = configuredTypes.filter(([code]) => !availableMetaTypes.has(code));
      analyticsMetaEventType.innerHTML = '<option value="">Todos los tipos con resultados</option>'
        + (withResults.length ? '<optgroup label="Con resultados guardados">' + withResults.map(([code, label]) => '<option value="' + escapeHtml(code) + '">' + escapeHtml(label) + '</option>').join('') + '</optgroup>' : '')
        + (withoutResults.length ? '<optgroup label="Sin resultados en este alcance">' + withoutResults.map(([code, label]) => '<option value="' + escapeHtml(code) + '" disabled>' + escapeHtml(label) + ' - sin datos</option>').join('') + '</optgroup>' : '');
      analyticsMetaEventType.value = availableMetaTypes.has(previousType) ? previousType : '';
    }
    const metaEventType = analyticsMetaEventType?.value || '';
    const metaSourceMatches = metaEventType ? model.matches.filter((match) => {
      const competition = statsCompetition(match);
      return String(match.competition_type || competition?.type || match.phase || 'general') === metaEventType;
    }) : model.matches;
    const metaGroup = analyticsMetaGroup?.value || 'deck';
    const meta = buildMetaPerformance(metaSourceMatches, metaGroup);
    const metaSample = Math.max(1, Math.min(context.sample, meta.maxMatches || 1));
    const tieredRows = meta.rows.map((row) => {
      let tier = 'Tier 3';
      if (row.matches >= metaSample && row.share >= Number(settings.tier_zero_presence || 35) && row.winRate >= Number(settings.tier_zero_winrate || 65)) tier = 'Tier Zero';
      else if (row.matches >= metaSample && row.metaScore >= Number(settings.tier_one_score || 70)) tier = 'Tier 1';
      else if (row.metaScore >= Number(settings.tier_two_score || 45)) tier = 'Tier 2';
      return { ...row, tier };
    }).sort((a, b) => b.metaScore - a.metaScore || b.appearances - a.appearances || b.winRate - a.winRate || a.name.localeCompare(b.name));
    const leader = tieredRows[0];
    setText('[data-stats-meta-kpi="matches"]', meta.finalizedMatches.length);
    setText('[data-stats-meta-kpi="entries"]', meta.totalAppearances);
    setText('[data-stats-meta-kpi="decks"]', tieredRows.length);
    setText('[data-stats-meta-kpi="leader"]', leader ? leader.name : '-');
    setText('[data-stats-meta-kpi="share"]', leader ? leader.tier + ' | ' + leader.share + '% presencia | indice ' + leader.metaScore : 'Sin resultados finalizados');
    renderMetaEvolution(metaSourceMatches, metaGroup, analyticsMetaPeriod?.value || 'week', context);
    renderAdvancedAnalytics(model, tieredRows);
    renderOperations(model, tieredRows);
    renderCompactBars(document.querySelector('[data-stats-meta-share]'), tieredRows.slice().sort((a, b) => b.appearances - a.appearances || b.metaScore - a.metaScore).slice(0, 15).map((row) => ({ name: row.name, team: row.team, value: row.appearances, division: row.share + '% | ' + row.matches + ' partidas' })), 'value');
    renderCompactBars(document.querySelector('[data-stats-meta-performance]'), tieredRows.slice().sort((a, b) => b.winRate - a.winRate || b.matches - a.matches).slice(0, 10).map((row) => ({ name: row.name, team: row.team, value: row.winRate, division: row.wins + '/' + row.matches + ' partidas | ' + row.duelWinRate + '% duelos' })), 'value', '%');
    const tierBoard = document.querySelector('[data-stats-meta-tiers]');
    const presenceWeight = Math.max(0, Math.min(100, Number(settings.meta_presence_weight || 50)));
    const metaFormula = document.querySelector('[data-meta-formula]');
    if (metaFormula) metaFormula.textContent = `${presenceWeight}% presencia relativa + ${100 - presenceWeight}% winrate de partidas.`;
    const tierRules = document.querySelector('[data-meta-tier-rules]');
    if (tierRules) tierRules.innerHTML = `<li><b>Tier Zero:</b> minimo ${Number(settings.tier_zero_presence || 35)}% de presencia y ${Number(settings.tier_zero_winrate || 65)}% de victorias.</li><li><b>Tier 1:</b> indice minimo ${Number(settings.tier_one_score || 70)}.</li><li><b>Tier 2:</b> indice minimo ${Number(settings.tier_two_score || 45)}.</li><li><b>Tier 3:</b> evidencia inicial o indice inferior.</li>`;
    if (tierBoard) {
      const tierDescriptions = { 'Tier Zero': 'Dominio excepcional', 'Tier 1': 'Alta presencia y rendimiento', 'Tier 2': 'Competitivo y relevante', 'Tier 3': 'Emergente o muestra inicial' };
      tierBoard.innerHTML = ['Tier Zero', 'Tier 1', 'Tier 2', 'Tier 3'].map((tier) => {
        const rows = tieredRows.filter((row) => row.tier === tier);
        return '<article class="panel" data-meta-tier="' + escapeHtml(tier.toLowerCase().replace(' ', '-')) + '"><header><span>' + escapeHtml(tier) + '</span><b>' + rows.length + '</b><small>' + tierDescriptions[tier] + '</small></header><ol>' + (rows.length ? rows.map((row) => '<li><strong>' + escapeHtml(row.name) + '</strong><span>Indice ' + row.metaScore + '</span><small>' + row.share + '% presencia | ' + row.winRate + '% WR | ' + row.matches + ' PJ</small></li>').join('') : '<li class="empty">Sin decks en este nivel</li>') + '</ol></article>';
      }).join('');
      tierBoard.insertAdjacentHTML('beforeend', '<p class="analytics-tier-sample">Muestra aplicada: ' + metaSample + ' ' + (metaSample === 1 ? 'partida finalizada' : 'partidas finalizadas') + ' por deck, ajustada a los datos disponibles.</p>');
    }
    const metaHistoryBody = document.querySelector('[data-stats-meta-history]');
    if (metaHistoryBody) {
      const historyRows = (data.metaHistory || []).filter((row) => String(row.group_type || 'deck') === metaGroup)
        .filter((row) => !context.season || String(row.season_id || '') === context.season)
        .filter((row) => !context.ruleset || String(row.format_code || '') === context.ruleset)
        .sort((a, b) => String(b.snapshot_on || '').localeCompare(String(a.snapshot_on || '')) || Number(b.meta_score || 0) - Number(a.meta_score || 0)).slice(0, 120);
      metaHistoryBody.innerHTML = historyRows.length ? historyRows.map((row) => `<tr><td data-label="Fecha">${escapeHtml(row.snapshot_on || '')}</td><td data-label="Deck / grupo"><b>${escapeHtml(row.group_key || '')}</b></td><td data-label="Formato">${escapeHtml(String(row.format_code || 'general').replaceAll('_', ' ').toUpperCase())}</td><td data-label="Tier"><span class="status-chip">${escapeHtml(row.tier || 'Tier 3')}</span></td><td data-label="Indice">${Number(row.meta_score || 0).toFixed(1)}</td><td data-label="Presencia">${Number(row.presence_share || 0).toFixed(1)}%</td><td data-label="WR">${Number(row.win_rate || 0).toFixed(1)}%</td><td data-label="Muestra">${Number(row.matches || 0)} PJ</td></tr>`).join('') : statsEmpty('Todavia no hay cortes historicos para este alcance.', 8);
    }
    renderCompactBars(statsEventBars, model.events.filter((event) => event.finalized).sort((a, b) => b.finalized - a.finalized).map((event) => ({ name: event.name, value: event.finalized, division: event.duels + ' duelos guardados' })), 'value');
    const eventRankingBody = document.querySelector('[data-stats-meta-event-ranking]');
    if (eventRankingBody) {
      const typeLabel = metaEventType ? (availableMetaTypes.get(metaEventType) || metaEventType) : 'TODOS';
      eventRankingBody.innerHTML = tieredRows.length ? tieredRows.slice().sort((a, b) => b.points - a.points || b.wins - a.wins || b.winRate - a.winRate || b.duelDiff - a.duelDiff).map((row, index) => '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Deck / equipo"><div class="analytics-deck-cell">' + (row.logo ? '<img src="' + escapeHtml(row.logo) + '" alt="">' : '<span><i class="bi bi-collection"></i></span>') + '<div><b>' + escapeHtml(row.name) + '</b><small>' + escapeHtml(row.team) + '</small></div></div></td><td data-label="Tipo">' + escapeHtml(typeLabel) + '</td><td data-label="PTS oficiales">' + row.points + '</td><td data-label="Partidas G/E/P">' + row.wins + ' / ' + row.draws + ' / ' + row.losses + '</td><td data-label="WR">' + row.winRate + '%</td><td data-label="Duelos G/E/P">' + row.duelWins + ' / ' + row.duelDraws + ' / ' + row.duelLosses + '</td><td data-label="DIF">' + (row.duelDiff > 0 ? '+' : '') + row.duelDiff + '</td></tr>').join('') : statsEmpty('No hay resultados finalizados para este tipo de evento.', 8);
    }
    const timeline = document.querySelector('[data-stats-timeline]');
    if (timeline) timeline.innerHTML = model.chronology.length ? model.chronology.slice(-12).reverse().map((row) => '<div><time>' + escapeHtml(row.date || 'Sin fecha') + '</time><span>' + escapeHtml(row.event) + '</span><b>' + escapeHtml(row.a) + ' ' + (row.winner === 'A' ? 'gano a' : row.winner === 'B' ? 'perdio con' : 'empato con') + ' ' + escapeHtml(row.b) + '</b></div>').join('') : '<div class="empty-state">Sin resultados historicos para estos filtros.</div>';

    const recordRows = [
      ['bi-fire', 'Mayor racha', decks.slice().sort((a, b) => b.bestStreak - a.bestStreak)[0], 'bestStreak', ' victorias'],
      ['bi-controller', 'Mas matches ganados', decks.slice().sort((a, b) => b.matchWins - a.matchWins)[0], 'matchWins', ' ganados'],
      ['bi-grid-3x3-gap', 'Mas duelos ganados', decks.slice().sort((a, b) => b.duelWins - a.duelWins)[0], 'duelWins', ' duelos'],
      ['bi-shield-check', 'Mas victorias perfectas', bySweep[0], 'sweeps', ' cierres'],
      ['bi-5-circle', 'Mas victorias D5', byD5[0], 'd5Wins', ' victorias'],
      ['bi-arrow-repeat', 'Mas remontadas', decks.slice().sort((a, b) => b.comebacks - a.comebacks)[0], 'comebacks', ' remontadas']
      ,['bi-lightning-charge', 'Victoria en menos turnos', decks.filter((deck) => deck.fastestTurns).sort((a, b) => a.fastestTurns - b.fastestTurns)[0], 'fastestTurns', ' turnos']
      ,['bi-stopwatch', 'Victoria en menor tiempo', decks.filter((deck) => deck.fastestSeconds).sort((a, b) => a.fastestSeconds - b.fastestSeconds)[0], 'fastestSeconds', ' segundos']
      ,['bi-exclamation-diamond', 'Mas manos brick', decks.slice().sort((a, b) => b.brickHands - a.brickHands)[0], 'brickHands', ' manos']
    ];
    const records = document.querySelector('[data-stats-records]');
    if (records) records.innerHTML = recordRows.map((record) => {
      const hasRecord = record[2] && Number(record[2][record[3]]);
      return '<article class="panel"><i class="bi ' + record[0] + '"></i><span>' + record[1] + '</span><b>' + (hasRecord ? escapeHtml(record[2].name) : 'Sin registro') + '</b><small>' + (hasRecord ? record[2][record[3]] + record[4] : 'Se mostrara al existir resultados') + '</small></article>';
    }).join('');
    renderCompactBars(document.querySelector('[data-stats-top-cut]'), decks.filter((deck) => deck.topCutMatches).sort((a, b) => b.topCutWins - a.topCutWins || b.topCutMatches - a.topCutMatches).map((deck) => ({ name: deck.name, value: deck.topCutWins, division: deck.topCutMatches + ' matches Top Cut' })), 'value');
    const honors = document.querySelector('[data-stats-honors]');
    const visibleHonors = (data.honors || []).filter((honor) => !context.event || String(honor.competition_id || '') === context.event).filter((honor) => !context.season || String(honor.season_id || '') === context.season);
    if (honors) honors.innerHTML = visibleHonors.length ? visibleHonors.map((honor) => '<div><time><i class="bi bi-trophy"></i></time><span>' + escapeHtml(honor.title || honor.competition_name || 'Titulo') + '</span><b>' + escapeHtml((honor.champion_team_name || '') + (honor.champion_name ? ' / ' + honor.champion_name : '')) + '<small> Subcampeon: ' + escapeHtml((honor.runner_team_name || '') + (honor.runner_name ? ' / ' + honor.runner_name : '')) + '</small></b></div>').join('') : '<div class="empty-state">No hay titulos registrados en este alcance.</div>';
    const movements = document.querySelector('[data-stats-movements]');
    const movementRows = (data.audit || []).filter((row) => ['division_promotion', 'division_relegation'].includes(String(row.action || '')));
    if (movements) movements.innerHTML = movementRows.length ? movementRows.slice(0, 30).map((row) => '<div><time>' + escapeHtml(String(row.created_at || '').slice(0, 10)) + '</time><span>' + (row.action === 'division_promotion' ? 'Ascenso' : 'Descenso') + '</span><b>Deck #' + escapeHtml(row.entity_id || '') + '</b></div>').join('') : '<div class="empty-state">No hay movimientos de division auditados.</div>';
    const eloHistoryBody = document.querySelector('[data-stats-elo-history]');
    if (eloHistoryBody) {
      const eloRows = (data.eloHistory || []).filter((row) => !context.deck || String(row.deck_name || '') === context.deck).slice(0, 150);
      eloHistoryBody.innerHTML = eloRows.length ? eloRows.map((row) => {
        const delta = Number(row.rating_delta || 0);
        const result = delta > 0 ? 'Victoria' : delta < 0 ? 'Derrota' : 'Empate';
        return `<tr><td data-label="Fecha">${escapeHtml(row.played_on || '')}</td><td data-label="Deck"><b>${escapeHtml(row.deck_name || `Deck #${row.deck_id}`)}</b></td><td data-label="Rival">${escapeHtml(row.opponent_name || `Deck #${row.opponent_deck_id}`)}</td><td data-label="Resultado">${result}</td><td data-label="Antes">${Math.round(Number(row.rating_before || 1500))}</td><td data-label="Cambio"><b class="${delta >= 0 ? 'text-success' : 'text-danger'}">${delta > 0 ? '+' : ''}${delta.toFixed(1)}</b></td><td data-label="Despues">${Math.round(Number(row.rating_after || 1500))}</td></tr>`;
      }).join('') : statsEmpty('No hay movimientos Elo persistidos para este alcance.', 7);
    }
    const rankingHistoryBody = document.querySelector('[data-stats-ranking-history]');
    if (rankingHistoryBody) {
      const historyRows = (data.rankingHistory || []).filter((row) => String(row.scope_key || 'global') === (context.season ? 'season' : 'global')).filter((row) => !context.season || String(row.season_id || '') === context.season).filter((row) => !context.deck || String(row.deck_name || '') === context.deck).slice(0, 250);
      rankingHistoryBody.innerHTML = historyRows.length ? historyRows.map((row) => {
        const delta = Number(row.position_delta || 0); const movement = delta > 0 ? `+${delta}` : String(delta);
        return `<tr><td data-label="Fecha">${escapeHtml(row.snapshot_on || '')}</td><td data-label="Deck / equipo"><b>${escapeHtml(row.deck_name || `Deck #${row.deck_id}`)}</b><small>${escapeHtml(row.team_name || 'Sin equipo')}</small></td><td data-label="Posicion"><b>#${Number(row.position || 0)}</b></td><td data-label="Anterior">${row.previous_position ? '#' + Number(row.previous_position) : 'Debut'}</td><td data-label="Movimiento"><b class="${delta > 0 ? 'text-success' : delta < 0 ? 'text-danger' : ''}">${movement}</b></td><td data-label="PTS">${Number(row.points || 0)}</td><td data-label="Partidas G/E/P">${Number(row.wins || 0)} / ${Number(row.draws || 0)} / ${Number(row.losses || 0)}</td></tr>`;
      }).join('') : statsEmpty('Todavia no hay cortes historicos de posiciones para este alcance.', 7);
    }
    const playerBody = document.querySelector('[data-stats-players]');
    if (playerBody) {
      const playerRows = (model.playerStats || []).sort((a, b) => b.points - a.points || b.wins - a.wins || b.duels - a.duels);
      playerBody.innerHTML = playerRows.length ? playerRows.map((row, index) => {
        const linked = Array.from(row.deckIds).map((id) => (data.allDecks || []).find((deck) => Number(deck.id) === Number(id))).filter(Boolean);
        const medals = (data.medalUnlocks || []).filter((unlock) => unlock.entity_type === 'player' && String(unlock.entity_id) === String(row.player.id)).length;
        return '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Jugador"><b>' + escapeHtml(row.player.alias || row.player.display_name) + '</b><small class="d-block">' + medals + ' medallas</small></td><td data-label="Equipo">' + escapeHtml(row.player.team_name || 'Sin equipo') + '</td><td data-label="Decks">' + (linked.map((deck) => escapeHtml(deck.name)).join(', ') || 'Sin deck atribuido') + '</td><td data-label="MJ">' + row.matches + '</td><td data-label="MG">' + row.wins + '</td><td data-label="DG">' + row.duels + '</td><td data-label="PTS">' + row.points + '</td></tr>';
      }).join('') : statsEmpty('No hay jugadores asignados directamente a duelos dentro de este alcance.', 8);
    }
    const history = document.querySelector('[data-stats-history]');
    if (history) history.innerHTML = model.events.length ? model.events.sort((a, b) => String(b.startsOn).localeCompare(String(a.startsOn))).map((event) => {
      const leader = Array.from(event.leaders.entries()).sort((a, b) => b[1] - a[1])[0];
      return '<tr><td data-label="Evento"><b>' + escapeHtml(event.name) + '</b></td><td data-label="Formato">' + escapeHtml(String(event.ruleset).replaceAll('_', ' ').toUpperCase()) + '</td><td data-label="Inicio">' + escapeHtml(event.startsOn || 'Sin fecha') + '</td><td data-label="Matches">' + event.matches + '</td><td data-label="Finalizados">' + event.finalized + '</td><td data-label="Duelos">' + event.duels + '</td><td data-label="Lider">' + (leader ? escapeHtml(leader[0]) + ' (' + leader[1] + ' pts)' : 'Sin resultados') + '</td></tr>';
    }).join('') : statsEmpty('No hay eventos dentro del alcance seleccionado.', 7);
  }

  function setStatsCategoryUrl(category) {
    const url = new URL(window.location.href);
    if (category) url.searchParams.set('stats_section', category);
    else url.searchParams.delete('stats_section');
    history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function openStatsTab(tabName, showModal = true, updateUrl = true) {
    const target = analyticsViews.some((view) => view.dataset.statsView === tabName) ? tabName : 'summary';
    analyticsTabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.statsTab === target));
    analyticsViews.forEach((view) => { view.hidden = true; });
    localStorage.setItem('dml_stats_tab', target);
    activeStatsCategory = target;
    if (!showModal || !statsCategoryModal || !statsCategoryBody) return;
    Array.from(statsCategoryBody.children).forEach((node) => statsCategoryStaging?.appendChild(node));
    const view = analyticsViews.find((item) => item.dataset.statsView === target);
    if (!view) return;
    view.hidden = false;
    statsCategoryBody.appendChild(view);
    const meta = statsCategoryMeta[target];
    const title = document.querySelector('[data-stats-category-title]');
    const subtitle = document.querySelector('[data-stats-category-subtitle]');
    const position = document.querySelector('[data-stats-category-position]');
    if (title) title.textContent = meta[0];
    if (subtitle) subtitle.textContent = meta[1];
    if (position) position.textContent = `${statsCategoryOrder.indexOf(target) + 1} de ${statsCategoryOrder.length}`;
    if (updateUrl) setStatsCategoryUrl(target);
    statsCategoryModal.show();
  }

  renderStats = renderStatsV2;
  populateStatsFilters();
  try {
    const savedFilters = JSON.parse(localStorage.getItem('dml_stats_filters') || '{}');
    analyticsFilters.forEach((control) => {
      const key = control.dataset.statsFilter;
      if (!(key in savedFilters)) return;
      if (control.type === 'checkbox') control.checked = Boolean(savedFilters[key]);
      else if (Array.from(control.options || []).some((option) => option.value === String(savedFilters[key]))) control.value = String(savedFilters[key]);
      else if (!control.options) control.value = String(savedFilters[key]);
    });
  } catch {}
  const publicSeason = new URLSearchParams(window.location.search).get('public_season') || new URLSearchParams(window.location.search).get('season_id') || '';
  const publicSeasonControl = document.querySelector('[data-stats-filter="season"]');
  if (publicSeason && publicSeasonControl && Array.from(publicSeasonControl.options).some((option) => option.value === publicSeason)) {
    publicSeasonControl.value = publicSeason;
  }
  analyticsFilters.forEach((control) => control.addEventListener('change', () => {
    const state = {};
    analyticsFilters.forEach((item) => { state[item.dataset.statsFilter] = item.type === 'checkbox' ? item.checked : item.value; });
    localStorage.setItem('dml_stats_filters', JSON.stringify(state));
    renderStats();
  }));
  analyticsTabs.forEach((tab) => tab.addEventListener('click', () => openStatsTab(tab.dataset.statsTab)));
  document.querySelector('[data-stats-category-prev]')?.addEventListener('click', () => {
    const index = statsCategoryOrder.indexOf(activeStatsCategory);
    openStatsTab(statsCategoryOrder[(index - 1 + statsCategoryOrder.length) % statsCategoryOrder.length]);
  });
  document.querySelector('[data-stats-category-next]')?.addEventListener('click', () => {
    const index = statsCategoryOrder.indexOf(activeStatsCategory);
    openStatsTab(statsCategoryOrder[(index + 1) % statsCategoryOrder.length]);
  });
  statsCategoryModalNode?.addEventListener('hidden.bs.modal', () => {
    Array.from(statsCategoryBody?.children || []).forEach((node) => {
      node.hidden = true;
      statsCategoryStaging?.appendChild(node);
    });
    activeStatsCategory = '';
    setStatsCategoryUrl('');
  });
  document.querySelector('[data-stats-open-filters]')?.addEventListener('click', () => {
    if (!statsFilterModal || !statsFilterBody) return;
    statsFilterNodes.forEach((node) => statsFilterBody.appendChild(node));
    statsFilterModal.show();
  });
  statsFilterModalNode?.addEventListener('hidden.bs.modal', () => {
    statsFilterNodes.forEach((node) => statsFilterStaging?.appendChild(node));
  });
  const statsGoRanking = document.querySelector('[data-stats-go-ranking]');
  if (statsGoRanking) statsGoRanking.addEventListener('click', () => {
    openStatsTab('rankings');
  });
  const statsClear = document.querySelector('[data-stats-clear]');
  if (statsClear) statsClear.addEventListener('click', () => {
    analyticsFilters.forEach((control) => {
      if (control.type === 'checkbox') control.checked = false;
      else control.value = control.dataset.statsFilter === 'sample' ? String(settings.stats_minimum_sample || 3) : '';
    });
    localStorage.removeItem('dml_stats_filters');
    renderStats();
  });
  if (analyticsRanking) analyticsRanking.addEventListener('change', renderStats);
  if (analyticsMetaEventType) analyticsMetaEventType.addEventListener('change', renderStats);
  if (analyticsMetaGroup) analyticsMetaGroup.addEventListener('change', renderStats);
  if (analyticsMetaPeriod) analyticsMetaPeriod.addEventListener('change', renderStats);
  if (analyticsAdvancedTop) analyticsAdvancedTop.addEventListener('change', renderStats);
  analyticsRankingEntities.forEach((button) => button.addEventListener('click', () => {
    analyticsRankingEntities.forEach((item) => item.classList.toggle('active', item === button));
    analyticsRankingPanels.forEach((panel) => { panel.hidden = panel.dataset.statsRankingPanel !== button.dataset.statsRankingEntity; });
  }));
  document.querySelectorAll('[data-quality-filter]').forEach((control) => control.addEventListener('change', renderStats));
  const statsExport = document.querySelector('[data-stats-export]');
  if (statsExport) statsExport.addEventListener('click', () => {
    const model = buildStatsModel();
    const rows = [['Deck', 'Equipo', 'Formato', 'PTS', 'MJ', 'MG', 'ME', 'MP', 'WR', 'WR primero', 'Muestra primero', 'WR segundo', 'Muestra segundo', 'DJ', 'DG', 'DP', 'DIF', 'Perfectas', 'D5', 'Top Cut', 'Top Cut ganados']];
    model.decks.forEach((deck) => rows.push([deck.name, deck.team, deck.ruleset, deck.points, deck.matches, deck.matchWins, deck.matchDraws, deck.matchLosses, deck.winRate + '%', deck.firstWinRate + '%', deck.firstStarts, deck.secondWinRate + '%', deck.secondStarts, deck.duelWins + deck.duelLosses + deck.duelDraws, deck.duelWins, deck.duelLosses, deck.duelDiff, deck.sweeps, deck.d5Wins, deck.topCutMatches, deck.topCutWins]));
    const csv = rows.map((row) => row.map((value) => '"' + String(value ?? '').replaceAll('"', '""') + '"').join(',')).join('\r\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'estadisticas-filtradas-' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
  });
  openStatsTab(localStorage.getItem('dml_stats_tab') || 'summary', false, false);
  renderStats();
  [compareA, compareB].forEach((select) => {
    if (select) select.addEventListener('change', renderStats);
  });

  const dashboardFormatButtons = Array.from(document.querySelectorAll('[data-dashboard-format-filter]'));
  const dashboardEventCards = Array.from(document.querySelectorAll('[data-dashboard-event-card]'));
