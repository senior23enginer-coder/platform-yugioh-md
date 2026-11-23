/**
 * deck-profile: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function renderProfile(preferredDeckId = '') {
    if (!deckSelect) return;
    // After a successful ZIP import, restore exactly the deck that initiated it.
    const importedDeckId = sessionStorage.getItem('stationx:deck-data-return') || '';
    if (importedDeckId) sessionStorage.removeItem('stationx:deck-data-return');
    const requestedId = preferredDeckId || importedDeckId;
    const deck = data.allDecks.find((item) => String(item.id) === String(requestedId)) || data.allDecks.find((item) => item.name === deckSelect.value) || data.allDecks[0];
    if (!deck) return;
    window.YgoDeckAttributes?.renderDeck(deck);
    const stat = deckProfileCompetitiveStats(deck);
    if (profileCalendarButton) profileCalendarButton.dataset.deckId = deck.id || '';
    const globalPositions = renderDeckListCompetitiveStats(globalDeckRanking());
    const globalPosition = globalPositions.get(String(deck.id || ''))?.position || '-';
    const profileEditorSelect = document.querySelector('[data-gestion-deck-select]');
    if (profileEditorSelect && profileEditorSelect.value !== String(deck.id || '')) {
      profileEditorSelect.value = String(deck.id || '');
      profileEditorSelect.dispatchEvent(new Event('change'));
    }
    const primary = deck.team_primary_color || '#36d8ff';
    const secondary = deck.team_secondary_color || '#ffd25f';
    document.querySelector('[data-profile-name]').textContent = deck.name;
    document.querySelector('[data-profile-division]').textContent = [stat.formatLabel, deck.division].filter(Boolean).join(' | ');
    const rankInfo = window.YgoStatistics?.profileRankInfo(deck);
    document.querySelector('[data-profile-rank]').textContent = rankInfo?.label || 'Ranking global: sin datos';
    const rankDetail = document.querySelector('[data-profile-rank-detail]');
    if (rankDetail) rankDetail.textContent = rankInfo?.detail || 'No se ha podido consultar la clasificación actual.';
    const deckTierRows = window.YgoStatistics?.tierHistory(deck)||[];
    const tierWeight = { 'Tier Zero': 0, 'Tier 1': 1, 'Tier 2': 2, 'Tier 3': 3 };
    const currentTierRow = deckTierRows.find(row => Number(row.season_id) === Number(data.currentSeasonId));
    const bestTierRow = deckTierRows.slice().sort((a, b) => (tierWeight[a.tier] ?? 99) - (tierWeight[b.tier] ?? 99) || String(a.snapshot_on || '').localeCompare(String(b.snapshot_on || '')))[0];
    const tierHistory = document.querySelector('[data-profile-tier-history]');
    if (tierHistory) tierHistory.innerHTML = `<article><span>Tier actual</span><b>${escapeHtml(currentTierRow?.tier || 'Sin tier')}</b><small>${escapeHtml(currentTierRow?.snapshot_on || 'Sin fecha')}</small></article><article><span>Mejor tier historico</span><b>${escapeHtml(bestTierRow?.tier || 'Sin tier')}</b><small>${escapeHtml(bestTierRow?.snapshot_on || 'Sin fecha')}</small></article><article><span>Cambios de tier</span><b>${deckTierRows.reduce((total, row, index) => total + (index && row.tier !== deckTierRows[index - 1].tier ? 1 : 0), 0)}</b><small>${deckTierRows.length} cortes persistidos</small></article>`;
    const profileExport = document.querySelector('[data-profile-export-pdf]');
    if (profileExport) profileExport.href = './?api=statistics&export=pdf&collection=profile&entity_team_id=' + encodeURIComponent(deck.team_id || '');
    if(profileExport)profileExport.hidden=!deck.team_id;
    const shareProfile = document.querySelector('[data-share-deck-profile]');
    if (shareProfile) shareProfile.onclick = () => shareCanvas('Perfil de equipo / deck', [deck.name, deck.team || 'Sin equipo', window.YgoStatistics?.profileRank(deck)||'Ranking por formato pendiente', `${stat.wins}/${stat.draws}/${stat.losses} partidas`, `${stat.duelWins}/${stat.duelDraws}/${stat.duelLosses} duelos`, stat.winRate==null?'WR: N/D':`${Number(stat.winRate).toFixed(2)}% winrate`, currentTierRow?.tier || 'Sin tier'], 'deck-' + normalize(deck.name).replaceAll(' ', '-') + '.png');
    document.querySelector('[data-profile-initials]').textContent = deck.name.split(' ').slice(0, 2).map((word) => word[0]).join('');
    const profileTeam = document.querySelector('[data-profile-team]');
    if (profileTeam) {
      const linkedTeam = (data.teams || []).find((team) => String(team.id || '') === String(deck.team_id || '')) || {};
      const teamCode = deck.team_code || deck.team_symbol || String(deck.team || 'YMD').slice(0, 3);
      const teamLogo = deck.team_logo || linkedTeam.logo_url || '';
      profileTeam.innerHTML = teamLogo
        ? `<span class="profile-team-logo"><img src="${escapeHtml(teamLogo)}" alt="${escapeHtml(deck.team || 'Equipo')}" onerror="this.parentElement.replaceWith(Object.assign(document.createElement('span'),{className:'team-inline-badge',textContent:'${escapeHtml(teamCode)}'}))"></span><span>${escapeHtml(deck.team || 'Sin equipo')}</span>`
        : `<span class="team-inline-badge" style="--team-primary:${primary};--team-secondary:${secondary};">${escapeHtml(teamCode)}</span> ${escapeHtml(deck.team || 'Sin equipo')}`;
    }
    // Identity remains native; player-facing archetype/style come exclusively from the
    // A01 profile activated by the authenticated Station X ZIP import.
    document.querySelector('[data-profile-archetype]').textContent = deck.display_archetype || 'Sin análisis importado';
    document.querySelector('[data-profile-style]').textContent = String(deck.display_style || '').trim() || 'Sin análisis importado';
    document.querySelector('[data-profile-cards]').textContent = deck.key_cards || 'Pendiente de registrar cartas clave.';
    document.querySelector('[data-profile-variations]').textContent = deck.variations || 'Pendiente de registrar variaciones.';
    document.querySelector('[data-profile-notes]').textContent = deck.notes || 'Ficha editable desde Gestion.';
    const profileEdit = document.querySelector('[data-profile-edit]');
    if (profileEdit) profileEdit.dataset.deckId = deck.id || '';
    const cardList = document.querySelector('[data-profile-card-list]');
    const mediaList = document.querySelector('[data-profile-media-list]');
    const cardGallery = document.querySelector('[data-card-gallery]');
    const deckCards = (data.cards || []).filter((card) => String(card.deck_id) === String(deck.id));
    const deckMedia = (data.media || []).filter((item) => String(item.deck_id) === String(deck.id));
    const galleryCards = deckCards;
    if (cardGallery && window.YgoDeckBuilder) {
      window.YgoDeckBuilder.mount(cardGallery, deck, data);
    } else if (cardGallery) {
      const sections = ['main', 'extra', 'side'];
      cardGallery.innerHTML = sections.map((section) => {
        const rows = galleryCards.filter((card) => (card.section || 'main').toLowerCase() === section).slice(0, section === 'main' ? 18 : 12);
        const label = section === 'main' ? 'Main Deck' : (section === 'extra' ? 'Extra Deck' : 'Side Deck');
        return `<section><h3>${label} <span>${rows.length}</span></h3><div>${rows.length ? rows.map((card, index) => `<button type="button" class="duel-card" style="--i:${index + section.length}"><b>${card.card_name}</b><small>${card.quantity || 1}</small></button>`).join('') : '<p class="note">Sin lista exacta cargada.</p>'}</div></section>`;
      }).join('');
    }
    if (cardList) {
      cardList.innerHTML = deckCards.length
        ? deckCards.map((card) => `<span><b>${card.quantity || 1}x ${card.card_name || 'Carta'}</b><small>${card.section || 'main'} | ${card.role || 'Sin rol'}</small></span>`).join('')
        : '<p class="note">Sin cartas registradas. Usa Editar decklist para agregar cartas.</p>';
    }
    if (mediaList) {
      mediaList.innerHTML = deckMedia.length
        ? deckMedia.map((item) => {
          if (item.media_type === 'video') {
            return `<a href="${item.url}" target="_blank" rel="noopener"><i class="bi bi-play-btn"></i><span>${item.title || 'Video del deck'}</span></a>`;
          }
          return `<a href="${item.url}" target="_blank" rel="noopener"><img src="${item.url}" alt="${item.title || deck.name}" loading="lazy"><span>${item.title || item.media_type || 'Imagen'}</span></a>`;
        }).join('')
        : '<p class="note">Sin imagenes, logos o videos extra registrados.</p>';
    }
    const cover = document.querySelector('[data-profile-cover]');
    cover.classList.toggle('has-deck-cover', Boolean(deck.cover_url));
    cover.style.setProperty('--deck-cover-url', deck.cover_url ? `url("${deck.cover_url}")` : 'none');
    cover.style.backgroundImage = deck.cover_url
      ? `linear-gradient(180deg, rgba(7,10,18,.08), rgba(7,10,18,.64)), var(--deck-cover-url)`
      : `radial-gradient(circle at 70% 25%, ${secondary}55, transparent 11rem), linear-gradient(145deg, ${primary}44, rgba(7,14,27,.96))`;
    cover.style.setProperty('--team-primary', primary);
    cover.style.setProperty('--team-secondary', secondary);
    const deckHistory = allMatchList()
      .filter((match) => [match.deck_a_id, match.deck_b_id, match.deck_c_id, match.deck_d_id, match.deck_e_id, match.deck_f_id].some((id) => String(id || '') === String(deck.id || ''))
        || matchSideDecks(match, 'a').includes(deck.name)
        || matchSideDecks(match, 'b').includes(deck.name))
      .sort((left, right) =>
        String(left.scheduled_on || '9999-12-31').localeCompare(String(right.scheduled_on || '9999-12-31'))
        || String(left.starts_at || '99:99').localeCompare(String(right.starts_at || '99:99'))
        || Number(left.ronda || 0) - Number(right.ronda || 0)
        || Number(left.mesa || 0) - Number(right.mesa || 0)
      );
    const nextMatch = deckHistory.find((match) => match.scheduled_on && match.scheduled_on >= serverToday && !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')));
    const doneCount = deckHistory.filter((match) => ['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')) || match.score).length;
    const pendingCount = deckHistory.length - doneCount;
    const setProfileText = (selector, value) => { const node = document.querySelector(selector); if (node) node.textContent = String(value); };
    setProfileText('[data-profile-match-record]', `${stat.wins} / ${stat.draws} / ${stat.losses}`);
    setProfileText('[data-profile-match-rate]', stat.winRate==null?'N/D · sin muestra confirmada':`${Number(stat.winRate).toFixed(2)}% de victorias en partidas`);
    setProfileText('[data-profile-duel-record]', `${stat.duelWins} / ${stat.duelDraws} / ${stat.duelLosses}`);
    setProfileText('[data-profile-duel-rate]', stat.duelWinRate==null?'N/D · sin muestra confirmada':`${Number(stat.duelWinRate).toFixed(2)}% de victorias en duelos`);
    setProfileText('[data-profile-total-scheduled]', deckHistory.length);
    setProfileText('[data-profile-pending]', `${pendingCount} pendientes | ${doneCount} finalizadas`);
    const statGroup = (title, icon, rows) => `<section class="deck-stat-group"><h3><i class="bi ${icon}"></i>${title}</h3><div>${rows.map(([label, value]) => `<span><b>${label}</b><strong>${escapeHtml(value)}</strong></span>`).join('')}</div></section>`;
    document.querySelector('[data-profile-stats]').innerHTML = [
      statGroup('Partidas', 'bi-controller', [
        ['PTS oficiales', stat.points], ['Jugadas', stat.matches], ['Pendientes', pendingCount], ['Totales', deckHistory.length],
        ['G / E / P', `${stat.wins} / ${stat.draws} / ${stat.losses}`], ['Winrate', stat.winRate==null?'N/D':`${Number(stat.winRate).toFixed(2)}%`], ['Perfectas', stat.perfectMatches], ['Mejor racha', stat.bestMatchStreak]
      ]),
      statGroup('Duelos', 'bi-grid-3x3-gap', [
        ['G / E / P', `${stat.duelWins} / ${stat.duelDraws} / ${stat.duelLosses}`], ['Winrate', `${stat.duelWinRate}%`],
        ['WR primero', stat.firstStarts ? `${stat.firstWinRate}% (${stat.firstWins}/${stat.firstStarts})` : 'N/D'], ['WR segundo', stat.secondStarts ? `${stat.secondWinRate}% (${stat.secondWins}/${stat.secondStarts})` : 'N/D'],
        ['Dados ganados', stat.dicePlayed ? `${stat.diceWins}/${stat.dicePlayed}` : 'N/D'], ['Manos brick', `${stat.brickHands} (${stat.brickRate}%)`], ['Victorias con brick', stat.brickWins]
      ]),
      statGroup('Ritmo', 'bi-stopwatch', [
        ['Turnos promedio', stat.averageTurns || 'N/D'], ['Victoria en turnos', stat.fastestWinTurns ? `${stat.fastestWinTurns} turnos` : 'N/D'],
        ['Tiempo promedio', stat.averageDuration ? formatDuration(stat.averageDuration) : 'N/D'], ['Victoria mas corta', stat.fastestWinSeconds ? formatDuration(stat.fastestWinSeconds) : 'N/D']
      ]),
      statGroup('Puntos de vida', 'bi-heart-pulse', [
        ['Dano hecho', `${stat.damageDealt.toLocaleString('es-CO')} LP`], ['Dano recibido', `${stat.damageReceived.toLocaleString('es-CO')} LP`],
        ['Sin perder LP', stat.flawlessWins], ['Cobertura', `${stat.lpCovered}/${stat.lpTotal}`]
      ]),
      statGroup('Formas de cierre', 'bi-flag', [
        ['Ganadas por rendicion', stat.surrenderWins], ['Perdidas por rendicion', stat.surrenderLosses], ['Ganadas por LP', stat.lifePointWins],
        ['Perdidas por LP', stat.lifePointLosses], ['Ganadas por deck-out', stat.deckOutWins], ['Ganadas por tiempo', stat.timeWins], ['Ganadas por efecto', stat.effectWins]
      ]),
      statGroup('Agenda', 'bi-calendar-event', [['Proximo combate', nextMatch ? `${formatDate(nextMatch.scheduled_on)} ${nextMatch.starts_at ? nextMatch.starts_at.slice(0, 5) : ''}` : 'Sin pendiente']])
    ].join('');
    const renderRivals = (selector, rows, valueKey, empty) => {
      const node = document.querySelector(selector);
      if (!node) return;
      node.innerHTML = rows.length
        ? rows.map((row, index) => `<span><b>#${index + 1} ${escapeHtml(row.name)}</b><small>${row[valueKey]} ${row[valueKey] === 1 ? 'partida' : 'partidas'} | duelos ${row.wins}-${row.draws}-${row.losses}</small></span>`).join('')
        : `<p class="note">${empty}</p>`;
    };
    renderRivals('[data-profile-top-beaten]', stat.topBeaten, 'matchWins', 'Sin partidas ganadas contra otros decks.');
    renderRivals('[data-profile-top-lost-to]', stat.topLostTo, 'matchLosses', 'Sin partidas perdidas contra otros decks.');
    const profileHonors = document.querySelector('[data-profile-honors]');
    if (profileHonors) {
      const honors = (data.honors || []).filter((honor) => String(honor.champion_deck_id || '') === String(deck.id || '') || String(honor.runner_up_deck_id || '') === String(deck.id || ''));
      profileHonors.innerHTML = honors.length ? honors.map((honor) => {
        const champion = String(honor.champion_deck_id || '') === String(deck.id || '');
        return `<article><i class="bi ${champion ? 'bi-trophy-fill' : 'bi-award'}"></i><span><b>${escapeHtml(honor.title || (champion ? 'Campeon' : 'Subcampeon'))}</b><small>${escapeHtml(honor.competition_name || 'Palmares general')}</small></span><em>${champion ? 'Titulo' : 'Finalista'}</em></article>`;
      }).join('') : '<p class="note">Todavia no tiene titulos ni subcampeonatos registrados.</p>';
    }
    const profileMedals = document.querySelector('[data-profile-medals]');
    if(profileMedals && window.YgoStatistics)window.YgoStatistics.renderProfile(profileMedals,deck);
    const historyBody = document.querySelector('[data-profile-history]');
    if (historyBody) {
      historyBody.innerHTML = deckHistory.length ? deckHistory.map((match) => {
        const side = matchSideDecks(match, 'a').includes(deck.name) || String(match.deck_a_id || '') === String(deck.id || '') || String(match.deck_c_id || '') === String(deck.id || '') || String(match.deck_e_id || '') === String(deck.id || '') ? 'a' : 'b';
        const rivalSide = side === 'a' ? 'b' : 'a';
        const rival = matchSideLabel(match, rivalSide) || 'Por definir';
        const eventName = match.competition_name || match.phase || match.division || 'Sin evento';
        const dateLabel = match.scheduled_on ? `${formatDate(match.scheduled_on)}${match.starts_at ? `<br><small>${match.starts_at.slice(0, 5)}</small>` : ''}` : '<span class="status-chip warn">Sin fecha</span>';
        const ruleLabel = [match.match_format ? String(match.match_format).toUpperCase() : '', match.best_of ? `Mejor de ${match.best_of}` : '', match.competition_ruleset_version || match.competition_ruleset || ''].filter(Boolean).join(' | ');
        const values = getSavedResult(match).filter(Boolean);
        const duelRecord = values.reduce((record, winner) => {
          if (winner === 'E') record[1] += 1;
          else if ((side === 'a' && winner === 'A') || (side === 'b' && winner === 'B')) record[0] += 1;
          else record[2] += 1;
          return record;
        }, [0, 0, 0]);
        const matchResult = evaluateDuel(values, match);
        const ownSide = side.toUpperCase();
        const resultLabel = matchResult.state !== 'FINALIZADA' ? 'Pendiente' : (matchResult.winner === 'E' ? 'Empate' : matchResult.winner === ownSide ? 'Victoria' : 'Derrota');
        const resultClass = resultLabel === 'Victoria' ? 'success' : resultLabel === 'Derrota' ? 'danger' : resultLabel === 'Empate' ? 'warn' : '';
        return `<tr data-deck-history-row data-history-date="${escapeHtml(match.scheduled_on || '')}" data-history-completed="${escapeHtml(match.completed_at || '')}" data-history-time="${escapeHtml(match.starts_at || '')}" data-history-event="${escapeHtml(String(match.competition_id || match.phase || match.division || 'none'))}" data-history-state="${escapeHtml(match.estado || match.status || '')}" data-history-result="${resultLabel}" data-history-round="${escapeHtml(String(match.ronda || match.round_number || ''))}">
          <td data-label="Fecha">${dateLabel}</td>
          <td data-label="Evento"><strong>${escapeHtml(eventName)}</strong><br><small>${escapeHtml(ruleLabel || match.phase || '')}</small></td>
          <td data-label="Ronda / mesa">R${escapeHtml(match.ronda || '-')} / M${escapeHtml(match.mesa || '-')}<br><small>${escapeHtml(match.venue || 'Sin sede')}</small></td>
          <td data-label="Rival">${escapeHtml(rival)}</td>
          <td data-label="Estado"><span class="status-chip ${statusClassForMatch(match).replace('is-', '')}">${escapeHtml(match.estado || 'PENDIENTE')}</span></td>
          <td data-label="Resultado"><span class="status-chip ${resultClass}">${resultLabel}</span><br><small>${escapeHtml(match.score || '0-0')}</small></td>
          <td data-label="Duelos G/E/P"><strong>${duelRecord.join(' / ')}</strong></td>
          <td data-label="Accion"><button class="btn btn-outline-info btn-sm" type="button" data-profile-open-match="${escapeHtml(match.id)}"><i class="bi bi-box-arrow-up-right"></i></button></td>
        </tr>`;
      }).join('') : '<tr><td colspan="8">Este deck todavia no tiene partidas registradas en BD.</td></tr>';
      Array.from(historyBody.querySelectorAll('[data-profile-open-match]')).forEach((button) => {
        button.addEventListener('click', () => openMatchSheet(button.dataset.profileOpenMatch));
      });
    }
    setProfileText('[data-profile-history-count]', `${deckHistory.length} ${deckHistory.length === 1 ? 'partida' : 'partidas'}`);
    window.YgoDeckHistory?.mount(historyBody, deck.id);
    const honorsCount = (data.honors || []).filter((honor) => String(honor.champion_deck_id || '') === String(deck.id || '') || String(honor.runner_up_deck_id || '') === String(deck.id || '')).length;
    const matchupCount = (data.allDecks || []).filter((rival) => String(rival.id || '') !== String(deck.id || '') && (matchupObservedStats(deck, rival).matches || selectMatchupAnalysis(deck.id, rival.id))).length;
    const analysisProfile = (data.deckAnalysisProfiles || []).find((row) => String(row.deck_id || '') === String(deck.id || ''));
    const eventHistory = window.YgoDeckEvents?.render(document.querySelector('[data-profile-events]'), deck, data, allMatchList()) || [];
    const categoryMetrics = {
      performance: `${stat.matches} partidas | ${stat.winRate}% WR`,
      history: `${deckHistory.length} registros | ${pendingCount} pendientes`,
      events: `${eventHistory.length} eventos registrados`,
      decklist: `${deckCards.length} cartas | ${deckMedia.length} archivos`,
      ranking: `#${globalPosition} | ${currentTierRow?.tier || 'Sin tier'}`,
      achievements: `${honorsCount} t?tulos | ${profileMedals ? profileMedals.querySelectorAll('[data-achievement-state="unlocked"]').length : 0} logros`,
      strategy: analysisProfile ? 'Perfil documentado' : 'Perfil pendiente',
      'play-calculator': deckCards.some(c=>String(c.section||'main').toLowerCase()==='main')?'Tablero interactivo · cálculo local':'Carga primero el Main Deck',
      matchups: `${matchupCount} rivales con informacion`,
      edit: 'Datos, logo y notas'
    };
    Object.entries(categoryMetrics).forEach(([key, value]) => {
      const node = document.querySelector(`[data-deck-category-metric="${key}"]`);
      if (node) node.textContent = value;
    });
    window.YgoDeckData?.mount(deck);
    window.YgoComboCreator?.mountViewer(document.querySelector('[data-deck-created-combos]'), deck);
    window.YgoPlayCalculator?.mount(document.querySelector('[data-play-calculator]'), deck, data);
    renderDeckAnalysis(deck);
  }

  const statsBody = document.querySelector('[data-stats-body]');
  const statsDivisionBars = document.querySelector('[data-stats-division-bars]');
  const statsTopBars = document.querySelector('[data-stats-top-bars]');
  const statsDonut = document.querySelector('[data-stats-donut]');
  const statsDonutLegend = document.querySelector('[data-stats-donut-legend]');
  const statsDivisionSummary = document.querySelector('[data-stats-division-summary]');
  const statsTopSummary = document.querySelector('[data-stats-top-summary]');
  const statsMatchSummary = document.querySelector('[data-stats-match-summary]');
  const statsTeamBars = document.querySelector('[data-stats-team-bars]');
  const statsOffenseBars = document.querySelector('[data-stats-offense-bars]');
  const statsDefenseBars = document.querySelector('[data-stats-defense-bars]');
  const statsBestEfficiency = document.querySelector('[data-stats-best-efficiency]');
  const statsBestSweep = document.querySelector('[data-stats-best-sweep]');
  const statsD5Pressure = document.querySelector('[data-stats-d5-pressure]');
  const statsBestTeam = document.querySelector('[data-stats-best-team]');
  const statsEventBars = document.querySelector('[data-stats-event-bars]');
  const statsConfigBars = document.querySelector('[data-stats-config-bars]');
  const statsStatusBars = document.querySelector('[data-stats-status-bars]');
  const statsGlobalPreview = document.querySelector('[data-stats-global-preview]');
  const compareA = document.querySelector('[data-compare-a]');
  const compareB = document.querySelector('[data-compare-b]');
  const compareBody = document.querySelector('[data-compare-body]');

  function renderCompactBars(node, rows, key, suffix = '') {
    if (!node) return;
    const max = Math.max(1, ...rows.map((row) => Math.abs(Number(row[key] || 0))));
    node.innerHTML = rows.length ? rows.map((row) => `
      <div class="stat-bar-row"><span>${row.team ? `${row.team} / ${row.name}` : row.name}</span><div><i style="width:${Math.max(4, (Math.abs(Number(row[key] || 0)) / max) * 100)}%"></i></div><b>${row[key] || 0}${suffix}</b><small>${row.division || ''}</small></div>
    `).join('') : '<p class="note">Sin datos jugados todavia.</p>';
  }

  function renderComparator(rows) {
    if (!compareA || !compareB || !compareBody) return;
    const options = rows.map((row) => `<option value="${row.name}">${row.team} - ${row.name}</option>`).join('');
    if (!compareA.innerHTML) compareA.innerHTML = options;
    if (!compareB.innerHTML) {
      compareB.innerHTML = options;
      compareB.selectedIndex = Math.min(1, rows.length - 1);
    }
    const a = rows.find((row) => row.name === compareA.value) || rows[0];
    const b = rows.find((row) => row.name === compareB.value) || rows[1] || rows[0];
    if (!a || !b) return;
    const rateA = a.pj ? Math.round((a.pg / a.pj) * 100) : 0;
    const rateB = b.pj ? Math.round((b.pg / b.pj) * 100) : 0;
    const metric = (label, left, right) => `<tr><td>${label}</td><td>${left}</td><td>${right}</td><td>${left === right ? 'Empate' : (left > right ? a.name : b.name)}</td></tr>`;
    compareBody.innerHTML = [
      metric('PTS', a.pts, b.pts),
      metric('Winrate', rateA, rateB),
      metric('DP', a.dp, b.dp),
      metric('CG', a.cg, b.cg),
      metric('D5 ganadas', a.d5, b.d5),
    ].join('');
  }

  function renderStats() {
    if (!statsBody) return;
    const rows = Object.keys(data.divisions).flatMap((division) => computeStandings(division).map((row) => ({ ...row, division })));
    statsBody.innerHTML = rows.map((row) => {
      const winRate = row.pj ? `${Math.round((row.pg / row.pj) * 100)}%` : '0%';
      const efficiency = row.pj ? Math.round(((row.pg * 3 + row.pe) / (row.pj * 3)) * 100) : 0;
      return `<tr><td><span class="team-inline-badge" style="--team-primary:${row.teamPrimary};--team-secondary:${row.teamSecondary};">${row.teamCode || row.team.slice(0, 3)}</span> <b>${row.team}</b><br><small>${row.name}</small></td><td>${row.division}</td><td>${row.pj}</td><td>${winRate}</td><td>${efficiency}%</td><td>${row.cg}</td><td>${row.cp}</td><td>${row.dp}</td><td>${row.sweep}</td><td>${row.d5}</td><td>${row.streak}</td></tr>`;
    }).join('');
    if (statsDivisionBars) {
      const divisionRows = officialDivisions.map((division) => {
        const standings = computeStandings(division);
        const played = standings.reduce((sum, row) => sum + row.pj, 0);
        const wins = standings.reduce((sum, row) => sum + row.pg, 0);
        const rate = played ? Math.round((wins / played) * 100) : 0;
        return { label: division, value: rate, sub: `${played} PJ` };
      });
      const max = Math.max(100, ...divisionRows.map((row) => row.value));
      statsDivisionBars.innerHTML = divisionRows.map((row) => `
        <div class="stat-bar-row"><span>${row.label}</span><div><i style="width:${Math.max(4, (row.value / max) * 100)}%"></i></div><b>${row.value}%</b><small>${row.sub}</small></div>
      `).join('');
      if (statsDivisionSummary) {
        const average = divisionRows.length ? Math.round(divisionRows.reduce((sum, row) => sum + row.value, 0) / divisionRows.length) : 0;
        statsDivisionSummary.textContent = `${average}%`;
      }
    }
    if (statsTopBars) {
      const topRows = [...rows].sort((a, b) => b.pts - a.pts || b.dp - a.dp || b.cg - a.cg).slice(0, 6);
      const max = Math.max(1, ...topRows.map((row) => row.pts));
      statsTopBars.innerHTML = topRows.map((row) => `
        <div class="stat-bar-row"><span>${row.name}</span><div><i style="width:${Math.max(4, (row.pts / max) * 100)}%"></i></div><b>${row.pts}</b><small>${row.division}</small></div>
      `).join('');
      if (statsTopSummary) statsTopSummary.textContent = `${topRows[0]?.pts || 0} pts`;
    }
    const teamRows = Object.values(rows.reduce((map, row) => {
      const key = row.team || row.name;
      map[key] = map[key] || { name: key, pts: 0, pj: 0, cg: 0, cp: 0, dp: 0, division: 'Equipo' };
      map[key].pts += row.pts;
      map[key].pj += row.pj;
      map[key].cg += row.cg;
      map[key].cp += row.cp;
      map[key].dp += row.dp;
      return map;
    }, {})).sort((a, b) => b.pts - a.pts || b.dp - a.dp).slice(0, 8);
    const activeRows = rows.filter((row) => row.pj > 0);
    const efficiencyRows = [...activeRows].map((row) => ({
      ...row,
      efficiency: Math.round((((row.pg * 3) + row.pe) / Math.max(1, row.pj * 3)) * 100)
    })).sort((a, b) => b.efficiency - a.efficiency || b.dp - a.dp || b.cg - a.cg);
    const sweepRows = [...activeRows].sort((a, b) => b.sweep - a.sweep || b.pg - a.pg || b.dp - a.dp);
    const d5Rows = [...activeRows].sort((a, b) => b.d5 - a.d5 || b.pj - a.pj || b.dp - a.dp);
    if (statsBestEfficiency) statsBestEfficiency.textContent = efficiencyRows[0] ? `${efficiencyRows[0].team} / ${efficiencyRows[0].name} (${efficiencyRows[0].efficiency}%)` : 'Sin datos';
    if (statsBestSweep) statsBestSweep.textContent = sweepRows[0] ? `${sweepRows[0].team} / ${sweepRows[0].name} (${sweepRows[0].sweep})` : 'Sin datos';
    if (statsD5Pressure) statsD5Pressure.textContent = d5Rows[0] ? `${d5Rows[0].team} / ${d5Rows[0].name} (${d5Rows[0].d5})` : 'Sin datos';
    if (statsBestTeam) statsBestTeam.textContent = teamRows[0] ? `${teamRows[0].name} (${teamRows[0].pts} pts)` : 'Sin datos';
    renderCompactBars(statsTeamBars, teamRows, 'pts');
    renderCompactBars(statsOffenseBars, [...rows].sort((a, b) => b.cg - a.cg).slice(0, 8), 'cg');
    renderCompactBars(statsDefenseBars, [...rows].filter((row) => row.pj > 0).sort((a, b) => a.cp - b.cp).slice(0, 8), 'cp');
    renderComparator(rows);
    const matches = allMatchList();
    const statusCounts = matches.reduce((map, match) => {
      const key = match.estado || 'PENDIENTE';
      map[key] = (map[key] || 0) + 1;
      return map;
    }, {});
    const matchCountByCompetition = matches.reduce((map, match) => {
      const key = String(match.competition_id || '');
      if (!key) return map;
      map[key] = (map[key] || 0) + 1;
      return map;
    }, {});
    const eventRows = (data.competitions || [])
      .map((competition) => ({
        name: competition.name || competition.code || 'Evento',
        value: matchCountByCompetition[String(competition.id || '')] || 0,
        division: competition.status || ''
      }))
      .filter((row) => row.value > 0)
      .sort((a, b) => b.value - a.value)
      .slice(0, 8);
    renderCompactBars(statsEventBars, eventRows, 'value');
    const statusSummary = Object.entries((data.competitions || []).reduce((map, competition) => {
      const key = normalizeStatusKey(competition.status || 'pending_configuration') || 'pending_configuration';
      map[key] = (map[key] || 0) + 1;
      return map;
    }, {})).map(([name, value]) => ({ name, value, division: 'Eventos' })).sort((a, b) => b.value - a.value);
    const undatedEvents = (data.competitions || []).filter((competition) => !competition.starts_on).length;
    const configuredEvents = (data.competitions || []).filter((competition) => Number(competition.deck_pool_count || 0) > 0 || matchCountByCompetition[String(competition.id || '')]).length;
    const scheduledMatches = matches.filter((match) => match.scheduled_on).length;
    renderCompactBars(statsConfigBars, [
      { name: 'Eventos con decks o duelos', value: configuredEvents, division: 'BD' },
      { name: 'Combates con fecha', value: scheduledMatches, division: 'Calendario' },
      { name: 'Eventos sin fecha', value: undatedEvents, division: 'Pendiente' },
      { name: 'Reglamentos activos', value: (data.rulesets || []).length, division: 'Config' },
      { name: 'Reglas custom', value: (data.gameRules || []).length, division: 'Config' }
    ], 'value');
    renderCompactBars(statsStatusBars, statusSummary, 'value');
    const totalMatches = Math.max(1, matches.length);
    const finalPct = Math.round(((statusCounts.FINALIZADA || 0) / totalMatches) * 100);
    const pendingPct = Math.round(((statusCounts.PENDIENTE || 0) / totalMatches) * 100);
    const progressPct = Math.max(0, 100 - finalPct - pendingPct);
    if (statsDonut) {
      statsDonut.style.background = `radial-gradient(circle, #07101f 0 48%, transparent 49%), conic-gradient(#35d27f 0 ${finalPct}%, #ffd25f ${finalPct}% ${finalPct + progressPct}%, #4f86ff ${finalPct + progressPct}% 100%)`;
    }
    if (statsDonutLegend) {
      statsDonutLegend.innerHTML = `
        <span><i style="--accent:#35d27f"></i>Finalizadas ${statusCounts.FINALIZADA || 0}</span>
        <span><i style="--accent:#ffd25f"></i>En curso ${(statusCounts['EN CURSO'] || 0) + (statusCounts['REQUIERE D5'] || 0)}</span>
        <span><i style="--accent:#4f86ff"></i>Pendientes ${statusCounts.PENDIENTE || 0}</span>
      `;
    }
    if (statsMatchSummary) statsMatchSummary.textContent = String(matches.length);
  }

  const analyticsFilters = Array.from(document.querySelectorAll('[data-stats-filter]'));
  const analyticsTabs = Array.from(document.querySelectorAll('[data-stats-tab]'));
  const analyticsViews = Array.from(document.querySelectorAll('[data-stats-view]'));
  const analyticsRanking = document.querySelector('[data-stats-ranking]');
  const analyticsMetaEventType = document.querySelector('[data-stats-meta-event-type]');
  const analyticsRankingEntities = Array.from(document.querySelectorAll('[data-stats-ranking-entity]'));
  const analyticsRankingPanels = Array.from(document.querySelectorAll('[data-stats-ranking-panel]'));
  const analyticsMetaGroup = document.querySelector('[data-stats-meta-group]');
  const analyticsMetaPeriod = document.querySelector('[data-stats-meta-period]');
  const analyticsAdvancedTop = document.querySelector('[data-stats-advanced-top]');
  const statsCategoryOrder = ['summary', 'rankings', 'matchups', 'meta', 'records', 'quality', 'advanced', 'operations'];
  const statsCategoryMeta = {
    summary: ['Resumen general', 'Estado competitivo, lideres, actividad y principales indicadores.'],
    rankings: ['Rankings competitivos', 'Clasificaciones de decks, equipos y jugadores con diferentes sistemas.'],
    matchups: ['Enfrentamientos', 'Historial directo, matriz de rivales y desglose por duelo.'],
    meta: ['Metagame', 'Presencia, rendimiento, tiers y evolucion de los decks registrados.'],
    records: ['Records e historico', 'Marcas, palmares, Elo, posiciones, jugadores y actividad por evento.'],
    quality: ['Calidad de datos', 'Cobertura, procedencia y registros que necesitan correccion.'],
    advanced: ['Analitica avanzada', 'Tops, rendimiento por tier, ritmo, brick, LP y cierres.'],
    operations: ['Centro operativo', 'Marcador publico, scouting, check-in, juez y auditoria.']
  };
  const statsCategoryModalNode = document.querySelector('[data-stats-category-modal]');
  const statsCategoryModal = statsCategoryModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(statsCategoryModalNode) : null;
  const statsCategoryBody = document.querySelector('[data-stats-category-body]');
  const statsCategoryStaging = document.querySelector('[data-stats-category-staging]');
  const statsFilterModalNode = document.querySelector('[data-stats-filter-modal]');
  const statsFilterModal = statsFilterModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(statsFilterModalNode) : null;
  const statsFilterBody = document.querySelector('[data-stats-filter-body]');
  const statsFilterStaging = document.querySelector('[data-stats-filter-staging]');
  const statsFilterPanel = document.querySelector('[data-stats-filter-panel]');
  const statsFilterNodes = [statsFilterPanel?.querySelector('.analytics-filter-primary'), statsFilterPanel?.querySelector('.analytics-advanced-filters')].filter(Boolean);
  let activeStatsCategory = '';

  if (statsCategoryStaging) {
    analyticsViews.forEach((view) => {
      view.hidden = true;
      statsCategoryStaging.appendChild(view);
    });
  }
  if (statsFilterStaging) statsFilterNodes.forEach((node) => statsFilterStaging.appendChild(node));

  function statsFilter(name) {
    const node = document.querySelector('[data-stats-filter="' + name + '"]');
    return node ? node.value : '';
  }

  function statsCompetition(match) {
    return (data.competitions || []).find((item) => String(item.id || '') === String(match.competition_id || '')) || null;
  }

  function statsContext() {
    const friendlies = document.querySelector('[data-stats-filter="friendlies"]');
    return {
      ruleset: statsFilter('ruleset'),
      event: statsFilter('event'),
      type: statsFilter('type'),
      division: statsFilter('division'),
      season: statsFilter('season'),
      deck: statsFilter('deck'),
      team: statsFilter('team'),
      player: statsFilter('player'),
      from: statsFilter('from'),
      to: statsFilter('to'),
      sample: Math.max(1, Number(statsFilter('sample') || 5)),
      friendlies: Boolean(friendlies && friendlies.checked)
    };
  }

  function statsMatchAllowed(match, context) {
    const competition = statsCompetition(match);
    const type = String(match.competition_type || (competition && competition.type) || match.phase || '');
    const ruleset = String(match.competition_ruleset || (competition && competition.event_ruleset) || '');
    const date = String(match.scheduled_on || (competition && competition.starts_on) || '').slice(0, 10);
    const friendly = String(match.phase || '').toLowerCase() === 'friendly'
      || type.toLowerCase() === 'friendly'
      || String((competition && competition.ranking_impact) || '').toUpperCase() === 'NONE';
    if (!context.friendlies && friendly) return false;
    if (context.ruleset && ruleset !== context.ruleset) return false;
    if (context.event && String(match.competition_id || '') !== context.event) return false;
    if (context.type && type !== context.type) return false;
    if (context.division && String(match.division || '') !== context.division) return false;
    if (context.season && String((competition && competition.season_id) || '') !== context.season) return false;
    if (context.deck && match.a !== context.deck && match.b !== context.deck && !(match.team_a_extra || []).includes(context.deck) && !(match.team_b_extra || []).includes(context.deck)) return false;
    if (context.team && String(match.team_a || '') !== context.team && String(match.team_b || '') !== context.team) return false;
    if (context.player) {
      const playerDeckIds = (data.playerDecks || []).filter((row) => String(row.player_id) === context.player).map((row) => Number(row.deck_id));
      const matchDeckIds = [match.deck_a_id, match.deck_b_id, match.deck_c_id, match.deck_d_id, match.deck_e_id, match.deck_f_id].map(Number);
      if (!playerDeckIds.some((id) => matchDeckIds.includes(id))) return false;
    }
    if (context.from && (!date || date < context.from)) return false;
    if (context.to && (!date || date > context.to)) return false;
    return true;
  }

  function statsEmpty(message, colspan) {
    return '<tr><td colspan="' + (colspan || 1) + '"><div class="empty-state">' + escapeHtml(message) + '</div></td></tr>';
  }

  function buildStatsModel() {
    const context = statsContext();
    const matches = individualMatchList().filter((match) => statsMatchAllowed(match, context));
    const deckMap = new Map();
    const teamMap = new Map();
    const matchupMap = new Map();
    const eventMap = new Map();
    const playerStatMap = new Map();
    const chronology = [];
    const ratingMatches = [];
    const duelPositions = Array.from({ length: 5 }, (_, index) => ({ position: index + 1, a: 0, b: 0, draws: 0, total: 0 }));
    const ensureDeck = (name) => {
      if (!deckMap.has(name)) {
        const info = deckInfoByName(name);
        deckMap.set(name, {
          name: name,
          id: Number(info.id || 0),
          team: info.team || 'Sin equipo',
          logo: info.logo_url || info.cover_url || '',
          rulesets: new Set(Array.isArray(info.format_codes) ? info.format_codes : []),
          points: 0, matches: 0, matchWins: 0, matchDraws: 0, matchLosses: 0,
          duelWins: 0, duelLosses: 0, duelDraws: 0,
          firstStarts: 0, firstWins: 0, secondStarts: 0, secondWins: 0,
          brickHands: 0, turnsRecorded: 0, totalTurns: 0, opponents: new Map(),
          sweeps: 0, d5Wins: 0, d5Played: 0, comebacks: 0, topCutMatches: 0, topCutWins: 0,
          fastestTurns: 0, fastestSeconds: 0,
          elo: 1500, recentResults: [], ratingOpponents: [],
          dicePlayed: 0, diceWins: 0, diceMatchWins: 0, brickWins: 0, lpDiffTotal: 0, lpDiffCount: 0,
          damageDealt: 0, damageTurns: 0, winTurnsTotal: 0, winTurnsCount: 0, lossTurnsTotal: 0, lossTurnsCount: 0,
          winDurationTotal: 0, winDurationCount: 0, lossDurationTotal: 0, lossDurationCount: 0,
          surrenderWins: 0, surrenderLosses: 0, venueResults: new Map(), judgeResults: new Map(), closeScores: new Map(),
          resultSeries: [], matchRivalsBeaten: new Set(), positionPlayed: [0, 0, 0, 0, 0], positionWins: [0, 0, 0, 0, 0],
          currentStreak: 0, bestStreak: 0, lastResult: '-'
        });
      }
      return deckMap.get(name);
    };
    const ensureTeam = (name) => {
      if (!teamMap.has(name)) {
        teamMap.set(name, { name: name, points: 0, matches: 0, matchWins: 0, matchDraws: 0, matchLosses: 0, duelWins: 0, duelLosses: 0, duelDraws: 0, brickHands: 0, turnsRecorded: 0, totalTurns: 0 });
      }
      return teamMap.get(name);
    };
    const ensurePlayerStat = (id) => {
      const player = (data.players || []).find((item) => Number(item.id) === Number(id));
      if (!player) return null;
      if (!playerStatMap.has(Number(id))) playerStatMap.set(Number(id), { player, deckIds: new Set(), matches: 0, wins: 0, draws: 0, losses: 0, duels: 0, points: 0 });
      return playerStatMap.get(Number(id));
    };
    matches.forEach((match) => {
      const competition = statsCompetition(match);
      const eventKey = String(match.competition_id || 'general');
      if (!eventMap.has(eventKey)) {
        eventMap.set(eventKey, {
          id: eventKey,
          name: (competition && competition.name) || match.competition_name || 'Sin evento asociado',
          ruleset: (competition && competition.event_ruleset) || match.competition_ruleset || 'general',
          startsOn: (competition && competition.starts_on) || match.scheduled_on || '',
          matches: 0, finalized: 0, duels: 0, leaders: new Map()
        });
      }
      const event = eventMap.get(eventKey);
      event.matches += 1;
      const values = getSavedResult(match).filter(Boolean);
      const duelDetails = parseJsonValue(match.duel_details || '', []);
      const result = evaluateDuel(values, match);
      if (result.state !== 'FINALIZADA') return;
      event.finalized += 1;
      event.duels += values.length;
      const sideA = matchSideDecks(match, 'a').map(ensureDeck);
      const sideB = matchSideDecks(match, 'b').map(ensureDeck);
      const a = sideA[0];
      const b = sideB[0];
      const teamA = ensureTeam(a.team);
      const teamB = ensureTeam(b.team);
      const ruleset = String(match.competition_ruleset || (competition && competition.event_ruleset) || match.division || 'general');
      const impact = String((competition && competition.ranking_impact) || 'FULL').toUpperCase();
      const winPoints = impact === 'REDUCED' ? 1 : (impact === 'BONUS' ? 4 : 3);
      const drawPoints = impact === 'REDUCED' ? 0 : 1;
      const isTopCut = ['top_cut', 'playoff', 'final', 'cup'].includes(String(match.phase || '').toLowerCase());
      sideA.concat(sideB).forEach((deck) => { deck.rulesets.add(ruleset); deck.matches += 1; if (isTopCut) deck.topCutMatches += 1; });
      teamA.matches += 1; teamB.matches += 1;
      const matchPlayerA = new Set();
      const matchPlayerB = new Set();
      values.forEach((winner, index) => {
        const detail = Array.isArray(duelDetails) ? (duelDetails.find((item) => Number(item.duel) === index + 1) || {}) : {};
        const attributedA = sideA.find((deck) => Number(deck.id) === Number(detail.deck_a_id)) || null;
        const attributedB = sideB.find((deck) => Number(deck.id) === Number(detail.deck_b_id)) || null;
        const duelSideA = attributedA ? [attributedA] : sideA;
        const duelSideB = attributedB ? [attributedB] : sideB;
        const playerA = ensurePlayerStat(detail.player_a_id);
        const playerB = ensurePlayerStat(detail.player_b_id);
        if (playerA) { playerA.deckIds.add(Number(detail.deck_a_id || attributedA?.id || 0)); matchPlayerA.add(Number(detail.player_a_id)); }
        if (playerB) { playerB.deckIds.add(Number(detail.deck_b_id || attributedB?.id || 0)); matchPlayerB.add(Number(detail.player_b_id)); }
        const starts = String(detail.starts_player || (index === 0 ? match.starts_player || '' : '')).toUpperCase();
        const turnCount = Math.max(0, Number(detail.turn_count || 0));
        const durationSeconds = Math.max(0, Number(detail.duration_seconds || 0));
        const recordAdvancedDuel = (deckRows, side) => {
          const own = side.toLowerCase();
          const rival = side === 'A' ? 'b' : 'a';
          const won = winner === side;
          const lost = winner && winner !== 'E' && !won;
          const ownStart = Math.max(1, Number(detail['lp_start_' + own] || 8000));
          const rivalStart = Math.max(1, Number(detail['lp_start_' + rival] || 8000));
          const ownFinal = Number(detail['lp_' + own]);
          const rivalFinal = Number(detail['lp_' + rival]);
          const method = String(detail.defeat_method || '').toLowerCase();
          deckRows.forEach((deck) => {
            if (Number.isFinite(ownFinal) && Number.isFinite(rivalFinal)) {
              deck.lpDiffTotal += ownFinal - rivalFinal;
              deck.lpDiffCount += 1;
              deck.damageDealt += Math.max(0, rivalStart - rivalFinal);
              if (turnCount) deck.damageTurns += turnCount;
            }
            if (won && detail['brick_' + own]) deck.brickWins += 1;
            if (won && turnCount) { deck.winTurnsTotal += turnCount; deck.winTurnsCount += 1; }
            if (lost && turnCount) { deck.lossTurnsTotal += turnCount; deck.lossTurnsCount += 1; }
            if (won && durationSeconds) { deck.winDurationTotal += durationSeconds; deck.winDurationCount += 1; }
            if (lost && durationSeconds) { deck.lossDurationTotal += durationSeconds; deck.lossDurationCount += 1; }
            if (won && method === 'surrender') deck.surrenderWins += 1;
            if (lost && method === 'surrender') deck.surrenderLosses += 1;
          });
        };
        recordAdvancedDuel(duelSideA, 'A');
        recordAdvancedDuel(duelSideB, 'B');
        duelSideA.forEach((deck) => {
          if (detail.brick_a) deck.brickHands += 1;
          if (turnCount) { deck.turnsRecorded += 1; deck.totalTurns += turnCount; }
        });
        duelSideB.forEach((deck) => {
          if (detail.brick_b) deck.brickHands += 1;
          if (turnCount) { deck.turnsRecorded += 1; deck.totalTurns += turnCount; }
        });
        if (detail.brick_a) teamA.brickHands += 1;
        if (detail.brick_b) teamB.brickHands += 1;
        if (turnCount) {
          teamA.turnsRecorded += 1; teamA.totalTurns += turnCount;
          teamB.turnsRecorded += 1; teamB.totalTurns += turnCount;
        }
        duelSideA.forEach((deck) => duelSideB.forEach((rival) => {
          const row = deck.opponents.get(rival.name) || { name: rival.name, wins: 0, losses: 0, draws: 0 };
          if (winner === 'A') row.wins += 1;
          else if (winner === 'B') row.losses += 1;
          else row.draws += 1;
          deck.opponents.set(rival.name, row);
        }));
        duelSideB.forEach((deck) => duelSideA.forEach((rival) => {
          const row = deck.opponents.get(rival.name) || { name: rival.name, wins: 0, losses: 0, draws: 0 };
          if (winner === 'B') row.wins += 1;
          else if (winner === 'A') row.losses += 1;
          else row.draws += 1;
          deck.opponents.set(rival.name, row);
        }));
        if (starts === 'A') {
          duelSideA.forEach((deck) => { deck.firstStarts += 1; if (winner === 'A') deck.firstWins += 1; });
          duelSideB.forEach((deck) => { deck.secondStarts += 1; if (winner === 'B') deck.secondWins += 1; });
        } else if (starts === 'B') {
          duelSideB.forEach((deck) => { deck.firstStarts += 1; if (winner === 'B') deck.firstWins += 1; });
          duelSideA.forEach((deck) => { deck.secondStarts += 1; if (winner === 'A') deck.secondWins += 1; });
        }
        const position = duelPositions[index];
        if (position) {
          position.total += 1;
          if (winner === 'A') position.a += 1;
          else if (winner === 'B') position.b += 1;
          else position.draws += 1;
        }
        duelSideA.forEach((deck) => { deck.positionPlayed[index] += 1; if (winner === 'A') deck.positionWins[index] += 1; });
        duelSideB.forEach((deck) => { deck.positionPlayed[index] += 1; if (winner === 'B') deck.positionWins[index] += 1; });
        if (winner === 'A') {
          duelSideA.forEach((deck) => { deck.duelWins += 1; }); duelSideB.forEach((deck) => { deck.duelLosses += 1; }); teamA.duelWins += 1; teamB.duelLosses += 1;
          duelSideA.forEach((deck) => { if (turnCount && (!deck.fastestTurns || turnCount < deck.fastestTurns)) deck.fastestTurns = turnCount; if (durationSeconds && (!deck.fastestSeconds || durationSeconds < deck.fastestSeconds)) deck.fastestSeconds = durationSeconds; });
          if (playerA) playerA.duels += 1;
        } else if (winner === 'B') {
          duelSideB.forEach((deck) => { deck.duelWins += 1; }); duelSideA.forEach((deck) => { deck.duelLosses += 1; }); teamB.duelWins += 1; teamA.duelLosses += 1;
          duelSideB.forEach((deck) => { if (turnCount && (!deck.fastestTurns || turnCount < deck.fastestTurns)) deck.fastestTurns = turnCount; if (durationSeconds && (!deck.fastestSeconds || durationSeconds < deck.fastestSeconds)) deck.fastestSeconds = durationSeconds; });
          if (playerB) playerB.duels += 1;
        } else {
          duelSideA.concat(duelSideB).forEach((deck) => { deck.duelDraws += 1; });
          teamA.duelDraws += 1; teamB.duelDraws += 1;
        }
      });
      if (values[4]) {
        sideA.concat(sideB).forEach((deck) => { deck.d5Played += 1; });
        if (values[4] === 'A') sideA.forEach((deck) => { deck.d5Wins += 1; });
        if (values[4] === 'B') sideB.forEach((deck) => { deck.d5Wins += 1; });
      }
      if (result.winner === 'A') {
        sideA.forEach((deck) => { deck.points += winPoints; deck.matchWins += 1; if (!values.includes('B')) deck.sweeps += 1; if (values[0] === 'B') deck.comebacks += 1; if (isTopCut) deck.topCutWins += 1; });
        sideB.forEach((deck) => { deck.matchLosses += 1; });
        teamA.points += winPoints; teamA.matchWins += 1; teamB.matchLosses += 1;
      } else if (result.winner === 'B') {
        sideB.forEach((deck) => { deck.points += winPoints; deck.matchWins += 1; if (!values.includes('A')) deck.sweeps += 1; if (values[0] === 'A') deck.comebacks += 1; if (isTopCut) deck.topCutWins += 1; });
        sideA.forEach((deck) => { deck.matchLosses += 1; });
        teamB.points += winPoints; teamB.matchWins += 1; teamA.matchLosses += 1;
      } else {
        sideA.concat(sideB).forEach((deck) => { deck.points += drawPoints; deck.matchDraws += 1; });
        teamA.points += drawPoints; teamB.points += drawPoints; teamA.matchDraws += 1; teamB.matchDraws += 1;
      }
      const matchDateLabel = String(match.scheduled_on || '');
      const venueLabel = String(match.venue || 'Sin sede');
      const judgeLabel = String(match.judge || 'Sin juez');
      sideA.concat(sideB).forEach((deck) => {
        const ownSide = sideA.includes(deck) ? 'A' : 'B';
        const won = result.winner === ownSide;
        const draw = result.winner === 'E';
        deck.resultSeries.push(won ? 1 : (draw ? .5 : 0));
        if (won) deck.matchRivalsBeaten.add(ownSide === 'A' ? b.name : a.name);
        const venue = deck.venueResults.get(venueLabel) || { name: venueLabel, matches: 0, wins: 0 };
        venue.matches += 1; if (won) venue.wins += 1; deck.venueResults.set(venueLabel, venue);
        const judge = deck.judgeResults.get(judgeLabel) || { name: judgeLabel, matches: 0, wins: 0 };
        judge.matches += 1; if (won) judge.wins += 1; deck.judgeResults.set(judgeLabel, judge);
        const scoreKey = String(result.score || values.filter((value) => value === ownSide).length + '-' + values.filter((value) => value !== ownSide && value !== 'E').length);
        if (won) deck.closeScores.set(scoreKey, (deck.closeScores.get(scoreKey) || 0) + 1);
        if (matchDateLabel) deck.lastPlayedOn = matchDateLabel;
      });
      if (match.dice_winner === 'A' || match.dice_winner === 'B') {
        sideA.concat(sideB).forEach((deck) => {
          const ownSide = sideA.includes(deck) ? 'A' : 'B';
          deck.dicePlayed += 1;
          if (match.dice_winner === ownSide) {
            deck.diceWins += 1;
            if (result.winner === ownSide) deck.diceMatchWins += 1;
          }
        });
      }
      matchPlayerA.forEach((id) => { const player = ensurePlayerStat(id); if (player) { player.matches += 1; if (result.winner === 'A') { player.wins += 1; player.points += winPoints; } else if (result.winner === 'E') { player.draws += 1; player.points += drawPoints; } else player.losses += 1; } });
      matchPlayerB.forEach((id) => { const player = ensurePlayerStat(id); if (player) { player.matches += 1; if (result.winner === 'B') { player.wins += 1; player.points += winPoints; } else if (result.winner === 'E') { player.draws += 1; player.points += drawPoints; } else player.losses += 1; } });
      sideA.forEach((deck) => event.leaders.set(deck.name, (event.leaders.get(deck.name) || 0) + (result.winner === 'A' ? winPoints : result.winner === 'E' ? drawPoints : 0)));
      sideB.forEach((deck) => event.leaders.set(deck.name, (event.leaders.get(deck.name) || 0) + (result.winner === 'B' ? winPoints : result.winner === 'E' ? drawPoints : 0)));
      const pair = [a.name, b.name].sort((left, right) => left.localeCompare(right));
      const pairKey = pair.join('|||');
      if (!matchupMap.has(pairKey)) matchupMap.set(pairKey, { a: pair[0], b: pair[1], matches: 0, aWins: 0, bWins: 0, draws: 0, aDuels: 0, bDuels: 0 });
      const matchup = matchupMap.get(pairKey);
      const originalAIsA = match.a === matchup.a;
      matchup.matches += 1;
      matchup.aDuels += values.filter((winner) => winner === (originalAIsA ? 'A' : 'B')).length;
      matchup.bDuels += values.filter((winner) => winner === (originalAIsA ? 'B' : 'A')).length;
      if (result.winner === 'E') matchup.draws += 1;
      else if ((result.winner === 'A') === originalAIsA) matchup.aWins += 1;
      else matchup.bWins += 1;
      chronology.push({
        date: String(match.scheduled_on || (competition && competition.starts_on) || '0000-00-00'),
        id: Number(match.id || 0), a: a.name, b: b.name, winner: result.winner,
        event: (competition && competition.name) || match.competition_name || 'Sin evento'
      });
      ratingMatches.push({
        date: String(match.scheduled_on || (competition && competition.starts_on) || '0000-00-00'),
        id: Number(match.id || 0), sideA: sideA.map((deck) => deck.name), sideB: sideB.map((deck) => deck.name), winner: result.winner
      });
    });
    const scopedCompetitionDeckIds = new Set();
    (data.competitions || []).filter((competition) => {
      if (context.event && String(competition.id || '') !== context.event) return false;
      if (context.ruleset && String(competition.event_ruleset || '') !== context.ruleset) return false;
      if (context.type && String(competition.type || '') !== context.type) return false;
      if (context.season && String(competition.season_id || '') !== context.season) return false;
      if (context.from && String(competition.starts_on || '') < context.from) return false;
      if (context.to && String(competition.starts_on || '') > context.to) return false;
      return true;
    }).forEach((competition) => (competition.selected_deck_ids || []).forEach((id) => scopedCompetitionDeckIds.add(Number(id))));
    const hasCompetitionScope = Boolean(context.event || context.ruleset || context.type || context.season || context.from || context.to);
    (data.allDecks || []).forEach((deck) => {
      if (context.deck && deck.name !== context.deck) return;
      if (context.team && deck.team !== context.team) return;
      if (hasCompetitionScope && !scopedCompetitionDeckIds.has(Number(deck.id || 0))) return;
      ensureDeck(deck.name);
      ensureTeam(deck.team || 'Sin equipo');
    });
    chronology.sort((left, right) => left.date.localeCompare(right.date) || left.id - right.id);
    chronology.forEach((item) => {
      [[item.a, 'A'], [item.b, 'B']].forEach((entry) => {
        const deck = deckMap.get(entry[0]);
        if (!deck) return;
        if (item.winner === entry[1]) {
          deck.currentStreak += 1;
          deck.bestStreak = Math.max(deck.bestStreak, deck.currentStreak);
          deck.lastResult = 'W';
        } else {
          deck.currentStreak = 0;
          deck.lastResult = item.winner === 'E' ? 'E' : 'L';
        }
      });
    });
    ratingMatches.sort((left, right) => left.date.localeCompare(right.date) || left.id - right.id);
    ratingMatches.forEach((item) => {
      const sideA = item.sideA.map((name) => deckMap.get(name)).filter(Boolean);
      const sideB = item.sideB.map((name) => deckMap.get(name)).filter(Boolean);
      if (!sideA.length || !sideB.length) return;
      const ratingA = sideA.reduce((sum, deck) => sum + deck.elo, 0) / sideA.length;
      const ratingB = sideB.reduce((sum, deck) => sum + deck.elo, 0) / sideB.length;
      const expectedA = 1 / (1 + Math.pow(10, (ratingB - ratingA) / 400));
      const scoreA = item.winner === 'A' ? 1 : (item.winner === 'E' ? .5 : 0);
      const delta = Math.max(1, Math.min(128, Number(settings.elo_k_factor || 32))) * (scoreA - expectedA);
      sideA.forEach((deck) => {
        deck.elo += delta;
        deck.recentResults.push(item.winner === 'A' ? 3 : (item.winner === 'E' ? 1 : 0));
        sideB.forEach((opponent) => deck.ratingOpponents.push(opponent.name));
      });
      sideB.forEach((deck) => {
        deck.elo -= delta;
        deck.recentResults.push(item.winner === 'B' ? 3 : (item.winner === 'E' ? 1 : 0));
        sideA.forEach((opponent) => deck.ratingOpponents.push(opponent.name));
      });
    });
    const recentPower = (results, limit) => {
      const sample = results.slice(-limit);
      return sample.length ? Math.round((sample.reduce((sum, value) => sum + value, 0) / (sample.length * 3)) * 100) : 0;
    };
    const wilsonScore = (wins, draws, total) => {
      if (!total) return 0;
      const z = 1.96;
      const p = (wins + (draws * .5)) / total;
      const denominator = 1 + ((z * z) / total);
      const centre = p + ((z * z) / (2 * total));
      const margin = z * Math.sqrt(((p * (1 - p)) + ((z * z) / (4 * total))) / total);
      return Math.max(0, Math.round(((centre - margin) / denominator) * 100));
    };
    const decks = Array.from(deckMap.values()).map((deck) => {
      const rivals = Array.from(deck.opponents.values());
      const repeated = rivals.slice().sort((a, b) => (b.wins + b.losses + b.draws) - (a.wins + a.losses + a.draws))[0];
      const favorable = rivals.filter((row) => row.wins).sort((a, b) => b.wins - a.wins || a.losses - b.losses)[0];
      const difficult = rivals.filter((row) => row.losses).sort((a, b) => b.losses - a.losses || a.wins - b.wins)[0];
      const debutSample = Math.max(1, Number(settings.best_debut_min_matches || 3));
      const debutRows = deck.resultSeries.slice(0, debutSample);
      const playedDuels = deck.duelWins + deck.duelLosses + deck.duelDraws;
      return {
        ...deck,
        ruleset: Array.from(deck.rulesets).join(', ') || 'general',
        duelDiff: deck.duelWins - deck.duelLosses,
        winRate: deck.matches ? Math.round((deck.matchWins / deck.matches) * 100) : 0,
        firstWinRate: deck.firstStarts ? Math.round((deck.firstWins / deck.firstStarts) * 100) : 0,
        secondWinRate: deck.secondStarts ? Math.round((deck.secondWins / deck.secondStarts) * 100) : 0,
        brickRate: playedDuels ? Math.round((deck.brickHands / playedDuels) * 100) : 0,
        averageTurns: deck.turnsRecorded ? Math.round((deck.totalTurns / deck.turnsRecorded) * 10) / 10 : 0,
        elo: Math.round(deck.elo),
        power5: recentPower(deck.recentResults, 5),
        power10: recentPower(deck.recentResults, 10),
        consistency: wilsonScore(deck.matchWins, deck.matchDraws, deck.matches),
        strength: deck.ratingOpponents.length ? Math.round(deck.ratingOpponents.reduce((sum, name) => sum + Number(deckMap.get(name)?.elo || 1500), 0) / deck.ratingOpponents.length) : 0,
        diceRate: deck.dicePlayed ? Math.round((deck.diceWins / deck.dicePlayed) * 100) : 0,
        diceEffectiveness: deck.diceWins ? Math.round((deck.diceMatchWins / deck.diceWins) * 100) : 0,
        brickWinRate: deck.brickHands ? Math.round((deck.brickWins / deck.brickHands) * 100) : 0,
        averageLpDiff: deck.lpDiffCount ? Math.round(deck.lpDiffTotal / deck.lpDiffCount) : 0,
        damagePerTurn: deck.damageTurns ? Math.round(deck.damageDealt / deck.damageTurns) : 0,
        winTurns: deck.winTurnsCount ? Math.round((deck.winTurnsTotal / deck.winTurnsCount) * 10) / 10 : 0,
        lossTurns: deck.lossTurnsCount ? Math.round((deck.lossTurnsTotal / deck.lossTurnsCount) * 10) / 10 : 0,
        winDuration: deck.winDurationCount ? Math.round(deck.winDurationTotal / deck.winDurationCount) : 0,
        lossDuration: deck.lossDurationCount ? Math.round(deck.lossDurationTotal / deck.lossDurationCount) : 0,
        rivalsBeaten: deck.matchRivalsBeaten.size,
        rankingRise: 0,
        rankingFall: 0,
        positionRecord: deck.positionPlayed.map((played, index) => played ? Math.round((deck.positionWins[index] / played) * 100) + '%' : 'N/D').join(' / '),
        repeatedRival: repeated ? repeated.wins + repeated.losses + repeated.draws : 0,
        repeatedRivalLabel: repeated ? repeated.name : 'Sin rival',
        favorableMatchup: favorable ? favorable.wins : 0,
        favorableMatchupLabel: favorable ? favorable.name : 'Sin rival',
        difficultMatchup: difficult ? difficult.losses : 0,
        difficultMatchupLabel: difficult ? difficult.name : 'Sin rival',
        bestDebut: debutRows.length >= debutSample ? Math.round((debutRows.reduce((sum, value) => sum + value, 0) / debutSample) * 100) : 0,
        stability: deck.resultSeries.length > 1 ? Math.max(0, Math.round(100 - (Math.sqrt(deck.resultSeries.reduce((sum, value) => sum + Math.pow(value - (deck.resultSeries.reduce((a, b) => a + b, 0) / deck.resultSeries.length), 2), 0) / deck.resultSeries.length) * 100))) : 0,
        confidence: wilsonScore(deck.matchWins, deck.matchDraws, deck.matches),
        venues: Array.from(deck.venueResults.values()), judges: Array.from(deck.judgeResults.values()), closes: Array.from(deck.closeScores.entries()).map(([name, value]) => ({ name, value })),
        topBeaten: rivals.filter((row) => row.wins).sort((a, b) => b.wins - a.wins || a.name.localeCompare(b.name)).slice(0, 3),
        topLostTo: rivals.filter((row) => row.losses).sort((a, b) => b.losses - a.losses || a.name.localeCompare(b.name)).slice(0, 3)
      };
    }).filter((deck) => (!context.deck || deck.name === context.deck) && (!context.team || deck.team === context.team));
    const teams = Array.from(teamMap.values()).map((team) => {
      const teamDecks = decks.filter((deck) => deck.team === team.name);
      const activeDecks = teamDecks.filter((deck) => deck.matches);
      const average = (key) => activeDecks.length ? Math.round(activeDecks.reduce((sum, deck) => sum + Number(deck[key] || 0), 0) / activeDecks.length) : (key === 'elo' ? 1500 : 0);
      return {
        ...team,
        duelDiff: team.duelWins - team.duelLosses,
        winRate: team.matches ? Math.round((team.matchWins / team.matches) * 100) : 0,
        averageTurns: team.turnsRecorded ? Math.round((team.totalTurns / team.turnsRecorded) * 10) / 10 : 0,
        elo: average('elo'), power5: average('power5'), power10: average('power10'),
        consistency: wilsonScore(team.matchWins, team.matchDraws, team.matches),
        strength: average('strength')
      };
    }).filter((team) => !context.team || team.name === context.team);
    const competitions = (data.competitions || []).filter((competition) => {
      const friendly = String(competition.type || '').toLowerCase() === 'friendly' || String(competition.ranking_impact || '').toUpperCase() === 'NONE';
      if (!context.friendlies && friendly) return false;
      if (context.event && String(competition.id || '') !== context.event) return false;
      if (context.ruleset && String(competition.event_ruleset || '') !== context.ruleset) return false;
      if (context.type && String(competition.type || '') !== context.type) return false;
      if (context.season && String(competition.season_id || '') !== context.season) return false;
      if (context.from && String(competition.starts_on || '') < context.from) return false;
      if (context.to && String(competition.starts_on || '') > context.to) return false;
      return true;
    });
    const includedCompetitionIds = new Set(matches.map((match) => String(match.competition_id || '')).filter(Boolean));
    const officialPoints = new Map();
    competitions.filter((competition) => includedCompetitionIds.has(String(competition.id || ''))).forEach((competition) => {
      const event = eventMap.get(String(competition.id || ''));
      if (event) event.leaders.clear();
      (Array.isArray(competition.ranking) ? competition.ranking : []).forEach((row) => {
        const deck = (data.allDecks || []).find((item) => String(item.id || '') === String(row.id || row.deck_id || ''))
          || (data.allDecks || []).find((item) => normalize(item.name) === normalize(row.name || ''));
        if (!deck) return;
        const points = Number(row.pts || row.points || 0);
        officialPoints.set(deck.name, (officialPoints.get(deck.name) || 0) + points);
        if (event) event.leaders.set(deck.name, points);
      });
    });
    decks.forEach((deck) => { deck.points = officialPoints.get(deck.name) || 0; });
    const globalRankingHistory = (data.rankingHistory || []).filter((row) => String(row.scope_key || 'global') === 'global' && Number(row.season_id || 0) === 0);
    decks.forEach((deck) => {
      const latest = globalRankingHistory.filter((row) => Number(row.deck_id) === Number(deck.id)).sort((a, b) => String(b.snapshot_on || '').localeCompare(String(a.snapshot_on || '')))[0];
      deck.rankPosition = Number(latest?.position || 0);
      deck.previousRankPosition = Number(latest?.previous_position || 0);
      deck.rankMovement = Number(latest?.position_delta || 0);
      deck.rankingRise = Math.max(0, deck.rankMovement);
      deck.rankingFall = Math.max(0, -deck.rankMovement);
    });
    teams.forEach((team) => {
      team.points = decks.filter((deck) => deck.team === team.name).reduce((sum, deck) => sum + deck.points, 0);
    });
    return { context, matches, decks, teams, playerStats: Array.from(playerStatMap.values()), matchups: Array.from(matchupMap.values()), duelPositions, events: Array.from(eventMap.values()), competitions, chronology };
  }

  function populateStatsFilters() {
    const rulesets = new Map();
    const types = new Map();
    const divisions = new Set();
    (data.competitions || []).forEach((competition) => {
      if (competition.event_ruleset) rulesets.set(String(competition.event_ruleset), String(competition.event_ruleset).replaceAll('_', ' ').toUpperCase());
      if (competition.type) types.set(String(competition.type), String(competition.type).replaceAll('_', ' ').toUpperCase());
    });
    allMatchList().forEach((match) => { if (match.division) divisions.add(String(match.division)); });
    const setOptions = (name, rows, placeholder) => {
      const select = document.querySelector('[data-stats-filter="' + name + '"]');
      if (!select || select.dataset.ready) return;
      select.innerHTML = '<option value="">' + placeholder + '</option>' + rows.map((row) => '<option value="' + escapeHtml(row[0]) + '">' + escapeHtml(row[1]) + '</option>').join('');
      select.dataset.ready = '1';
    };
    setOptions('ruleset', Array.from(rulesets.entries()).sort((a, b) => a[1].localeCompare(b[1])), 'Todos los formatos');
    setOptions('type', Array.from(types.entries()).sort((a, b) => a[1].localeCompare(b[1])), 'Todos los tipos');
    setOptions('division', Array.from(divisions).sort().map((value) => [value, value]), 'Todas las divisiones');
    setOptions('season', (data.seasons || []).map((season) => [String(season.id), season.name]).sort((a, b) => b[0] - a[0]), 'Todas las temporadas');
    setOptions('deck', (data.allDecks || []).map((deck) => [deck.name, deck.name]).sort((a, b) => a[1].localeCompare(b[1])), 'Todos los decks');
    setOptions('team', (data.teams || []).map((team) => [team.name, team.name]).sort((a, b) => a[1].localeCompare(b[1])), 'Todos los equipos');
    setOptions('player', (data.players || []).map((player) => [String(player.id), player.alias || player.display_name]).sort((a, b) => a[1].localeCompare(b[1])), 'Todos los jugadores');
    setOptions('event', (data.competitions || []).map((competition) => [String(competition.id), competition.name || competition.code || 'Evento #' + competition.id]), 'Todos los eventos');
  }

  function renderStatsComparator(rows) {
    if (!compareA || !compareB || !compareBody) return;
    const previousA = compareA.value;
    const previousB = compareB.value;
    const options = rows.map((row) => '<option value="' + escapeHtml(row.name) + '">' + escapeHtml(row.team) + ' - ' + escapeHtml(row.name) + '</option>').join('');
    compareA.innerHTML = options;
    compareB.innerHTML = options;
    compareA.value = rows.some((row) => row.name === previousA) ? previousA : ((rows[0] && rows[0].name) || '');
    compareB.value = rows.some((row) => row.name === previousB) ? previousB : ((rows[1] && rows[1].name) || (rows[0] && rows[0].name) || '');
    const a = rows.find((row) => row.name === compareA.value);
    const b = rows.find((row) => row.name === compareB.value);
    if (!a || !b) {
      compareBody.innerHTML = statsEmpty('No hay dos decks con resultados para comparar.', 4);
      return;
    }
    const metric = (label, left, right, suffix) => '<tr><td data-label="Metrica">' + label + '</td><td data-label="Deck A">' + left + (suffix || '') + '</td><td data-label="Deck B">' + right + (suffix || '') + '</td><td data-label="Ventaja">' + (left === right ? 'Empate' : escapeHtml(left > right ? a.name : b.name)) + '</td></tr>';
    compareBody.innerHTML = [
      metric('Puntos', a.points, b.points), metric('Matches ganados', a.matchWins, b.matchWins),
      metric('Winrate', a.winRate, b.winRate, '%'), metric('Duelos ganados', a.duelWins, b.duelWins),
      metric('Winrate empezando primero', a.firstWinRate, b.firstWinRate, '%'), metric('Winrate empezando segundo', a.secondWinRate, b.secondWinRate, '%'),
      metric('Diferencia de duelos', a.duelDiff, b.duelDiff), metric('Victorias perfectas', a.sweeps, b.sweeps),
      metric('Victorias D5', a.d5Wins, b.d5Wins), metric('Mejor racha', a.bestStreak, b.bestStreak)
    ].join('');
  }

  function buildMetaPerformance(sourceMatches, groupBy = 'deck') {
    const rows = new Map();
    const finalizedMatches = [];
    const ensure = (name) => {
      if (!rows.has(name)) {
        const info = deckInfoByName(name);
        rows.set(name, { name, team: info.team || 'Sin equipo', logo: info.logo_url || info.cover_url || '', appearances: 0, points: 0, matches: 0, wins: 0, draws: 0, losses: 0, duelWins: 0, duelDraws: 0, duelLosses: 0 });
      }
      return rows.get(name);
    };
    (sourceMatches || []).forEach((match) => {
      const values = getSavedResult(match).filter(Boolean);
      const result = evaluateDuel(values, match);
      if (result.state !== 'FINALIZADA') return;
      finalizedMatches.push(match);
      const sideA = Array.from(new Set(matchSideDecks(match, 'a'))).map(ensure);
      const sideB = Array.from(new Set(matchSideDecks(match, 'b'))).map(ensure);
      sideA.concat(sideB).forEach((row) => { row.appearances += 1; row.matches += 1; });
      if (result.winner === 'A') { sideA.forEach((row) => { row.wins += 1; }); sideB.forEach((row) => { row.losses += 1; }); }
      else if (result.winner === 'B') { sideB.forEach((row) => { row.wins += 1; }); sideA.forEach((row) => { row.losses += 1; }); }
      else sideA.concat(sideB).forEach((row) => { row.draws += 1; });
      const details = parseJsonValue(match.duel_details || '', []);
      values.forEach((winner, index) => {
        const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === index + 1) || {}) : {};
        const attributedA = sideA.find((row) => Number(deckInfoByName(row.name).id || 0) === Number(detail.deck_a_id || 0));
        const attributedB = sideB.find((row) => Number(deckInfoByName(row.name).id || 0) === Number(detail.deck_b_id || 0));
        const duelA = attributedA ? [attributedA] : sideA;
        const duelB = attributedB ? [attributedB] : sideB;
        if (winner === 'A') { duelA.forEach((row) => { row.duelWins += 1; }); duelB.forEach((row) => { row.duelLosses += 1; }); }
        else if (winner === 'B') { duelB.forEach((row) => { row.duelWins += 1; }); duelA.forEach((row) => { row.duelLosses += 1; }); }
        else duelA.concat(duelB).forEach((row) => { row.duelDraws += 1; });
      });
    });
    const competitionIds = new Set(finalizedMatches.map((match) => String(match.competition_id || '')).filter(Boolean));
    (data.competitions || []).filter((competition) => competitionIds.has(String(competition.id || ''))).forEach((competition) => {
      (Array.isArray(competition.ranking) ? competition.ranking : []).forEach((ranking) => {
        const deck = (data.allDecks || []).find((item) => String(item.id || '') === String(ranking.id || ranking.deck_id || ''))
          || (data.allDecks || []).find((item) => normalize(item.name) === normalize(ranking.name || ''));
        if (deck && rows.has(deck.name)) rows.get(deck.name).points += Number(ranking.pts || ranking.points || 0);
      });
    });
    let groupedRows = Array.from(rows.values());
    if (groupBy !== 'deck') {
      const grouped = new Map();
      groupedRows.forEach((row) => {
        const info = deckInfoByName(row.name);
        const key = groupBy === 'archetype' ? (info.archetype || row.name) : (info.variations || info.archetype || row.name);
        if (!grouped.has(key)) grouped.set(key, { name: key, team: groupBy === 'archetype' ? 'Arquetipo' : 'Variante', logo: row.logo, appearances: 0, points: 0, matches: 0, wins: 0, draws: 0, losses: 0, duelWins: 0, duelDraws: 0, duelLosses: 0 });
        const target = grouped.get(key);
        ['appearances', 'points', 'matches', 'wins', 'draws', 'losses', 'duelWins', 'duelDraws', 'duelLosses'].forEach((field) => { target[field] += Number(row[field] || 0); });
      });
      groupedRows = Array.from(grouped.values());
    }
    const totalAppearances = groupedRows.reduce((sum, row) => sum + row.appearances, 0);
    const maxAppearances = Math.max(1, ...groupedRows.map((row) => row.appearances));
    const maxMatches = Math.max(0, ...groupedRows.map((row) => row.matches));
    return {
      finalizedMatches,
      totalAppearances,
      maxMatches,
      rows: groupedRows.map((row) => {
        const duelTotal = row.duelWins + row.duelDraws + row.duelLosses;
        const share = totalAppearances ? Math.round((row.appearances / totalAppearances) * 1000) / 10 : 0;
        const winRate = row.matches ? Math.round((row.wins / row.matches) * 100) : 0;
        const duelWinRate = duelTotal ? Math.round((row.duelWins / duelTotal) * 100) : 0;
        const presenceIndex = Math.round((row.appearances / maxAppearances) * 100);
        const presenceWeight = Math.max(0, Math.min(100, Number(settings.meta_presence_weight || 50))) / 100;
        const performanceWeight = 1 - presenceWeight;
        const metaScore = Math.round((presenceIndex * presenceWeight) + (winRate * performanceWeight));
        return { ...row, share, winRate, duelWinRate, duelDiff: row.duelWins - row.duelLosses, metaScore };
      })
    };
  }

