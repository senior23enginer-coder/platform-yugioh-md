/**
 * dashboard: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function applyDashboardFormatFilter(format = '') {
    const target = normalize(format);
    dashboardFormatButtons.forEach((button) => {
      button.classList.toggle('active', normalize(button.dataset.dashboardFormatFilter || '') === target);
    });
    dashboardEventCards.forEach((card) => {
      const cardFormat = normalize(card.dataset.dashboardFormat || '');
      card.hidden = Boolean(target && cardFormat !== target);
    });
  }
  dashboardFormatButtons.forEach((button) => {
    button.addEventListener('click', () => applyDashboardFormatFilter(button.dataset.dashboardFormatFilter || ''));
  });
  applyDashboardFormatFilter('');

  const rankingScopes = window.YgoDashboardRankingScopes;
  const dashboardEventScopes = rankingScopes.options(data);
  const dashboardRankingEvent = document.querySelector('[data-dashboard-ranking-event]');
  const dashboardRankingMetric = document.querySelector('[data-dashboard-ranking-metric]');
  const dashboardRankingSource = document.querySelector('[data-dashboard-ranking-source]');
  const dashboardRankingTitle = document.querySelector('[data-dashboard-ranking-title]');
  const dashboardRankingList = document.querySelector('[data-dashboard-ranking-list]');
  const dashboardLpCoverage = document.querySelector('[data-dashboard-lp-coverage]');

  function dashboardRankingLogo(url, fallback, className) {
    const cleanUrl = String(url || '').trim();
    return cleanUrl
      ? `<span class="${className} has-media"><img loading="lazy" decoding="async" src="${escapeHtml(cleanUrl)}" alt="${escapeHtml(fallback)}"></span>`
      : `<span class="${className}">${escapeHtml(String(fallback || 'EQ').replace(/[^a-z0-9]/gi, '').slice(0, 3).toUpperCase() || 'EQ')}</span>`;
  }

  function renderDashboardEventRanking(competitionId) {
    if (!dashboardRankingList) return;
    const scope = dashboardEventScopes.find(item => item.value === String(competitionId || ''));
    const competition = (data.competitions || []).find(item => String(item.id) === String(scope?.competitionId));
    if (!competition) {
      dashboardRankingList.innerHTML = '<p class="note">No hay un evento activo para clasificar.</p>';
      return;
    }

    if (dashboardRankingSource) dashboardRankingSource.textContent = scope.label;
    const rows = rankingScopes.ranking(scope, Array.isArray(competition.ranking) ? competition.ranking : []);
    const deckRows = rows.map((row) => {
      const deck = (data.allDecks || []).find((item) => String(item.id || '') === String(row.id || row.deck_id || ''))
        || (data.allDecks || []).find((item) => normalize(item.name) === normalize(row.name))
        || {};
      return {
        ...row,
        name: row.name || deck.name || 'Deck',
        team: row.team || deck.team || 'Sin equipo',
        logo: deck.cover_url || deck.logo_url || deck.team_logo || ''
      };
    });

    const teamMap = new Map();
    deckRows.forEach((row) => {
      const teamName = row.team || 'Sin equipo';
      const current = teamMap.get(teamName) || { name: teamName, pts: 0, pj: 0, pg: 0, pp: 0, cg: 0, cp: 0, dp: 0, logo: '' };
      const team = (data.teams || []).find((item) => normalize(item.name) === normalize(teamName)) || {};
      current.pts += Number(row.pts || 0);
      current.pj += Number(row.pj || 0);
      current.pg += Number(row.pg || row.wins || 0);
      current.pp += Number(row.pp || row.losses || 0);
      current.cg += Number(row.cg || row.duels_won || 0);
      current.cp += Number(row.cp || row.duels_lost || 0);
      current.dp += Number(row.dp || row.difference || 0);
      current.logo = current.logo || team.logo_url || row.team_logo || '';
      teamMap.set(teamName, current);
    });

    const teams = Array.from(teamMap.values()).sort((left, right) =>
      right.pts - left.pts || right.pg - left.pg || right.dp - left.dp || right.cg - left.cg || left.name.localeCompare(right.name)
    );
    deckRows.sort((left, right) =>
      Number(right.cg || right.duels_won || 0) - Number(left.cg || left.duels_won || 0)
      || Number(right.pts || 0) - Number(left.pts || 0)
      || Number(right.pj || 0) - Number(left.pj || 0)
      || String(left.name || '').localeCompare(String(right.name || ''))
    );
    const metric = dashboardRankingMetric?.value || 'points';
    const eventMatches = rankingScopes.matches(scope, allMatchList());
    const teamMetrics = new Map();
    const ensureTeamMetric = (name, logo = '') => {
      const key = normalize(name || 'Sin equipo');
      if (!teamMetrics.has(key)) teamMetrics.set(key, { name: name || 'Sin equipo', logo, damage_dealt: 0, damage_received: 0, damage_dealt_missing: 0, damage_received_missing: 0, surrenders: 0, lp_losses: 0 });
      const row = teamMetrics.get(key);
      row.logo = row.logo || logo;
      return row;
    };
    teams.forEach((team) => ensureTeamMetric(team.name, team.logo));
    let lpCoveredDuels = 0;
    let lpTotalDuels = 0;
    let playedMatches = 0;
    let drawnMatches = 0;
    let firstStartDuels = 0;
    let firstStartWins = 0;
    let secondStartDuels = 0;
    let secondStartWins = 0;
    eventMatches.forEach((match) => {
      const sideA = ensureTeamMetric(match.team_a || 'Sin equipo', match.team_a_logo || '');
      const sideB = ensureTeamMetric(match.team_b || 'Sin equipo', match.team_b_logo || '');
      const savedDuels = getSavedResult(match).filter(Boolean);
      const matchResult = evaluateDuel(savedDuels, match);
      if (matchResult.state === 'FINALIZADA') {
        playedMatches += 1;
        if (matchResult.winner === 'E') drawnMatches += 1;
      }
      const persistedDetails = parseJsonValue(match.duel_details || '', []);
      const details = savedDuels.map((winner, index) => {
        const persisted = Array.isArray(persistedDetails) ? persistedDetails.find((detail) => Number(detail.duel) === index + 1) : null;
        return persisted || { duel: index + 1, winner };
      });
      details.forEach((detail, detailIndex) => {
        lpTotalDuels += 1;
        const winner = String(detail.winner || '').toUpperCase();
        const starts = String(detail.starts_player || (detailIndex === 0 ? match.starts_player || '' : '')).toUpperCase();
        if (starts === 'A' || starts === 'B') {
          firstStartDuels += 1;
          secondStartDuels += 1;
          if (winner === starts) firstStartWins += 1;
          if ((winner === 'A' || winner === 'B') && winner !== starts) secondStartWins += 1;
        }
        const lpA = Number(detail.lp_a);
        const lpB = Number(detail.lp_b);
        const hasLpA = detail.lp_a !== '' && detail.lp_a !== null && detail.lp_a !== undefined && Number.isFinite(lpA);
        const hasLpB = detail.lp_b !== '' && detail.lp_b !== null && detail.lp_b !== undefined && Number.isFinite(lpB);
        if (hasLpA && hasLpB) lpCoveredDuels += 1;
        const history = Array.isArray(detail.lp_history) ? detail.lp_history : [];
        const grossLoss = (side) => history.filter((item) => item.side === side && item.operation !== 'reset').reduce((sum, item) => sum + Math.max(0, Number(item.before || 0) - Number(item.after || 0)), 0);
        const grossA = grossLoss('a');
        const grossB = grossLoss('b');
        const startA = Math.max(1, Number(detail.lp_start_a || 8000));
        const startB = Math.max(1, Number(detail.lp_start_b || 8000));
        if (hasLpA) {
          const damageToA = grossA || Math.max(0, startA - lpA);
          sideB.damage_dealt += damageToA;
          sideA.damage_received += damageToA;
        } else if (detail.defeat_method === 'life_points' && winner === 'B') {
          sideB.damage_dealt += startA;
          sideA.damage_received += startA;
        } else {
          sideB.damage_dealt_missing += 1;
          sideA.damage_received_missing += 1;
        }
        if (hasLpB) {
          const damageToB = grossB || Math.max(0, startB - lpB);
          sideA.damage_dealt += damageToB;
          sideB.damage_received += damageToB;
        } else if (detail.defeat_method === 'life_points' && winner === 'A') {
          sideA.damage_dealt += startB;
          sideB.damage_received += startB;
        } else {
          sideA.damage_dealt_missing += 1;
          sideB.damage_received_missing += 1;
        }
        if (detail.defeat_method === 'surrender') {
          if (winner === 'A') sideB.surrenders += 1;
          if (winner === 'B') sideA.surrenders += 1;
        }
        if (detail.defeat_method === 'life_points') {
          if (winner === 'A') sideB.lp_losses += 1;
          if (winner === 'B') sideA.lp_losses += 1;
        }
      });
    });

    const totalMatches = eventMatches.length;
    const pendingMatches = Math.max(0, totalMatches - playedMatches);
    const percentage = (wins, total) => total > 0 ? Math.round((wins / total) * 100) : 0;
    const generalStats = [
      { name: 'Partidas jugadas', value: playedMatches, label: `${playedMatches}` },
      { name: 'Partidas pendientes', value: pendingMatches, label: `${pendingMatches}` },
      { name: 'Total de partidas', value: totalMatches, label: `${totalMatches}` },
      { name: 'Victorias empezando primero', value: percentage(firstStartWins, firstStartDuels), label: `${percentage(firstStartWins, firstStartDuels)}% (${firstStartWins}/${firstStartDuels})` },
      { name: 'Victorias empezando segundo', value: percentage(secondStartWins, secondStartDuels), label: `${percentage(secondStartWins, secondStartDuels)}% (${secondStartWins}/${secondStartDuels})` },
      { name: 'Empates', value: drawnMatches, label: `${drawnMatches}` }
    ];

    const configurations = {
      general_stats: { title: 'Estadisticas generales del evento', rows: generalStats, value: (row) => row.value, label: (value, row) => row.label, type: 'summary' },
      points: { title: 'Equipos con mas puntos', rows: teams, value: (row) => Number(row.pts || 0), label: (value) => `${value} pts`, type: 'team' },
      duels: { title: 'Decks con mas duelos ganados', rows: deckRows, value: (row) => Number(row.cg || row.duels_won || 0), label: (value) => `${value} ${value === 1 ? 'duelo ganado' : 'duelos ganados'}`, type: 'deck' },
      damage_dealt: { title: 'LP quitados a oponentes en el evento', rows: Array.from(teamMetrics.values()), value: (row) => row.damage_dealt, label: (value, row) => `${value.toLocaleString('es-CO')} LP${row.damage_dealt_missing ? ` | ${row.damage_dealt_missing} sin LP` : ''}`, type: 'team' },
      damage_received: { title: 'LP perdidos por el equipo en el evento', rows: Array.from(teamMetrics.values()), value: (row) => row.damage_received, label: (value, row) => `${value.toLocaleString('es-CO')} LP${row.damage_received_missing ? ` | ${row.damage_received_missing} sin LP` : ''}`, type: 'team' },
      surrenders: { title: 'Equipos con mas rendiciones', rows: Array.from(teamMetrics.values()), value: (row) => row.surrenders, label: (value) => `${value} ${value === 1 ? 'rendicion' : 'rendiciones'}`, type: 'team' },
      lp_losses: { title: 'Equipos con mas derrotas por LP', rows: Array.from(teamMetrics.values()), value: (row) => row.lp_losses, label: (value) => `${value} ${value === 1 ? 'derrota' : 'derrotas'}`, type: 'team' }
    };
    const configuration = configurations[metric] || configurations.points;
    if (dashboardLpCoverage) {
      dashboardLpCoverage.hidden = !['damage_dealt', 'damage_received'].includes(metric);
      dashboardLpCoverage.textContent = `Cobertura LP: ${lpCoveredDuels}/${lpTotalDuels} duelos con valores completos. Los faltantes se identifican sin inventar montos.`;
    }
    const rankingRows = configuration.type === 'summary'
      ? configuration.rows.slice()
      : configuration.rows.slice().sort((left, right) =>
        configuration.value(right) - configuration.value(left) || String(left.name || '').localeCompare(String(right.name || ''))
      ).slice(0, 10);
    if (dashboardRankingTitle) dashboardRankingTitle.textContent = configuration.title;
    dashboardRankingList.classList.toggle('deck-ranks', configuration.type === 'deck');
    dashboardRankingList.classList.toggle('general-stats', configuration.type === 'summary');
    dashboardRankingList.innerHTML = rankingRows.length ? rankingRows.map((row, index) => configuration.type === 'summary' ? `
      <article class="dashboard-general-stat">
        <span class="dashboard-general-stat-icon"><i class="bi ${['bi-check2-circle', 'bi-hourglass-split', 'bi-calendar2-check', 'bi-arrow-up-circle', 'bi-arrow-down-circle', 'bi-dash-circle'][index] || 'bi-bar-chart'}"></i></span>
        <strong>${escapeHtml(row.name)}</strong>
        <em>${escapeHtml(configuration.label(configuration.value(row), row))}</em>
      </article>
    ` : `
      <article>
        <b>${index + 1}</b>
        ${dashboardRankingLogo(row.logo, row.name, configuration.type === 'deck' ? 'mini-card-art' : 'rank-avatar media-avatar')}
        <strong title="${escapeHtml(row.name)}">${escapeHtml(row.name)}</strong>
        <em>${configuration.label(configuration.value(row), row)}</em>
      </article>
    `).join('') : '<p class="note">Este evento aun no tiene datos para esta clasificacion.</p>';
  }

  if (dashboardRankingEvent) {
    dashboardRankingEvent.innerHTML = dashboardEventScopes.length
      ? dashboardEventScopes.map(scope => `<option value="${escapeHtml(scope.value)}">${escapeHtml(scope.label)}</option>`).join('')
      : '<option value="">Sin eventos activos</option>';
    const rankedScope = dashboardEventScopes.find(scope => {
      const competition = (data.competitions || []).find(c => String(c.id) === String(scope.competitionId));
      return rankingScopes.ranking(scope, competition?.ranking || []).some(row => Number(row.pj || 0) > 0);
    });
    dashboardRankingEvent.value = (rankedScope || dashboardEventScopes[0])?.value || '';
    dashboardRankingEvent.addEventListener('change', () => renderDashboardEventRanking(dashboardRankingEvent.value));
    if (dashboardRankingMetric) dashboardRankingMetric.addEventListener('change', () => renderDashboardEventRanking(dashboardRankingEvent.value));
    renderDashboardEventRanking(dashboardRankingEvent.value);
  }

  const auditSearch = document.querySelector('[data-audit-search]');
  const auditKind = document.querySelector('[data-audit-kind]');
  const auditEntity = document.querySelector('[data-audit-entity]');
  const auditAction = document.querySelector('[data-audit-action]');
  const auditRows = Array.from(document.querySelectorAll('[data-audit-row]'));

