/**
 * match-sheets: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function qualifierMatches() {
    return allMatchList().filter((match) => match.phase === 'qualifier'
      && match.id
      && (!selectedQualifierEventId || String(match.competition_id || '') === selectedQualifierEventId));
  }

  function syncQualifierRoundOptions() {
    if (!qualifierMatchRound) return;
    const current = qualifierMatchRound.value;
    const rounds = Array.from(new Set(qualifierMatches().map((match) => Number(match.ronda || 0)).filter(Boolean))).sort((a, b) => a - b);
    qualifierMatchRound.innerHTML = '<option value="">Todas las rondas</option>' + rounds.map((round) => `<option value="${round}">Ronda ${round}</option>`).join('');
    if (rounds.some((round) => String(round) === String(current))) qualifierMatchRound.value = current;
  }

  function filteredQualifierMatches() {
    const query = normalize(qualifierMatchSearch ? qualifierMatchSearch.value : '');
    const status = qualifierMatchStatus ? qualifierMatchStatus.value : '';
    const round = qualifierMatchRound ? qualifierMatchRound.value : '';
    const matches = qualifierMatches().filter((match) => {
      const haystack = normalize(`#${match.id} duelo ${match.id} match ${match.id} ronda ${match.ronda} mesa ${match.mesa} ${match.a} ${match.b} ${match.team_a || ''} ${match.team_b || ''} ${match.team_a_code || ''} ${match.team_b_code || ''} ${matchLabelWithTeam(match.a)} ${matchLabelWithTeam(match.b)} ${match.scheduled_on} ${match.starts_at} ${match.estado} ${match.score}`);
      return (!status || match.estado === status)
        && (!round || String(match.ronda) === String(round))
        && (!query || haystack.includes(query));
    });
    const order = qualifierMatchOrder?.value || 'pending_first';
    const stateRank = (match) => {
      const state = String(match.estado || '').toUpperCase();
      if (state === 'PENDIENTE') return 0;
      if (state.includes('CURSO')) return 1;
      return 2;
    };
    const scheduleKey = (match) => `${match.scheduled_on || '9999-12-31'} ${match.starts_at || '23:59:59'}`;
    return matches.sort((left, right) => {
      if (order === 'finished_first') return stateRank(right) - stateRank(left) || scheduleKey(right).localeCompare(scheduleKey(left));
      if (order === 'date_desc') return scheduleKey(right).localeCompare(scheduleKey(left));
      if (order === 'date_asc') return scheduleKey(left).localeCompare(scheduleKey(right));
      if (order === 'round_desc') return Number(right.ronda || 0) - Number(left.ronda || 0) || Number(right.mesa || 0) - Number(left.mesa || 0);
      if (order === 'round_asc') return Number(left.ronda || 0) - Number(right.ronda || 0) || Number(left.mesa || 0) - Number(right.mesa || 0);
      return stateRank(left) - stateRank(right) || scheduleKey(left).localeCompare(scheduleKey(right));
    });
  }

  function qualifierMatchSide(match, side) {
    const key = side.toLowerCase();
    const deck = match[key] || `Deck ${side}`;
    const team = match[`team_${key}`] || 'Sin equipo';
    const logo = matchSideLogoUrl(match, key);
    const fallback = matchSideFallback(match, key) || side;
    return `<span class="qualifier-match-side"><span class="team-inline-badge${logo ? ' has-image' : ''}">${logo ? `<img src="${escapeHtml(logo)}" alt="${escapeHtml(team)}">` : escapeHtml(fallback)}</span><span><strong>${escapeHtml(deck)}</strong><small>${escapeHtml(team)}</small></span></span>`;
  }

  function renderQualifierMatchList() {
    if (!qualifierMatchList) return;
    const matches = filteredQualifierMatches();
    if (!matches.length) {
      qualifierMatchList.innerHTML = '<tr><td colspan="8"><div class="empty-state">No hay combates con esos filtros.</div></td></tr>';
      return;
    }
    qualifierMatchList.innerHTML = matches.map((match) => `
      <tr class="clickable-row" data-qualifier-match-row data-match-id="${match.id}">
        <td data-label="Fecha"><strong>${match.scheduled_on || 'Sin fecha'}</strong></td>
        <td data-label="Hora">${match.starts_at ? match.starts_at.slice(0, 5) : 'Sin hora'}</td>
        <td data-label="Ronda">R${match.ronda}</td>
        <td data-label="Mesa">M${match.mesa}</td>
        <td data-label="Combate"><div class="qualifier-match-versus">${qualifierMatchSide(match, 'A')}<b>VS</b>${qualifierMatchSide(match, 'B')}</div><small>ID duelo #${match.id} | ${String(match.match_format || '1v1').toUpperCase()} | BO${match.best_of || 5}</small></td>
        <td data-label="Estado"><span class="status-chip">${match.estado || 'PENDIENTE'}</span></td>
        <td data-label="Resultado"><strong>${escapeHtml(match.score || '-')}</strong></td>
        <td data-label="Ficha"><button class="btn btn-outline-info btn-sm" type="button" data-open-match-sheet data-match-id="${match.id}"><i class="bi bi-eye"></i> Ficha</button></td>
      </tr>
    `).join('');
    Array.from(qualifierMatchList.querySelectorAll('[data-qualifier-match-row]')).forEach((row) => {
      row.addEventListener('click', () => openMatchSheet(row.dataset.matchId));
    });
    Array.from(qualifierMatchList.querySelectorAll('[data-open-match-sheet]')).forEach((button) => {
      button.addEventListener('click', (event) => {
        event.stopPropagation();
        openMatchSheet(button.dataset.matchId);
      });
    });
  }

  function setMatchSheetText(selector, value) {
    const node = matchSheetModalNode ? matchSheetModalNode.querySelector(selector) : null;
    if (node) node.textContent = value || '-';
  }

  function setMatchSheetLogo(selector, logoUrl, fallbackText, altText) {
    const node = matchSheetModalNode ? matchSheetModalNode.querySelector(selector) : null;
    if (!node) return;
    const fallback = String(fallbackText || '?').replace(/[^a-z0-9]/gi, '').slice(0, 2).toUpperCase() || '?';
    node.classList.toggle('has-image', !!logoUrl);
    node.innerHTML = logoUrl
      ? `<img src="${escapeHtml(logoUrl)}" alt="${escapeHtml(altText || fallback)}">`
      : escapeHtml(fallback);
  }

  function setQualifierDeckText(selector, value) {
    const node = qualifierDeckSheetModalNode ? qualifierDeckSheetModalNode.querySelector(selector) : null;
    if (node) node.textContent = value || '-';
  }

  function openQualifierDeckSheet(deckName) {
    if (!qualifierDeckSheetModalNode || !qualifierDeckSheetModal) return;
    const deck = deckInfoByName(deckName);
    if (!deck.name) return;
    activeQualifierDeckName = deck.name;
    const matches = qualifierMatches().filter((match) => match.a === deck.name || match.b === deck.name);
    const played = matches.filter((match) => match.estado === 'FINALIZADA').length;
    const rivals = new Set(matches.filter((match) => match.estado === 'FINALIZADA').map((match) => match.a === deck.name ? match.b : match.a));
    const progress = Math.min(100, Math.round((Math.min(played, 3) / 3) * 70 + (Math.min(rivals.size, 3) / 3) * 30));
    setQualifierDeckText('[data-qualifier-deck-title]', `${deck.team || 'Sin equipo'} / ${deck.name}`);
    setQualifierDeckText('[data-qualifier-deck-meta]', `${deck.archetype || deck.name} | ${deck.division || 'CLASIFICATORIO'} | Ranking base ${deck.rank || '-'}`);
    setQualifierDeckText('[data-qualifier-deck-scheduled]', String(matches.length));
    setQualifierDeckText('[data-qualifier-deck-played]', String(played));
    setQualifierDeckText('[data-qualifier-deck-rivals]', String(rivals.size));
    setQualifierDeckText('[data-qualifier-deck-status]', `${progress}%`);
    const body = qualifierDeckSheetModalNode.querySelector('[data-qualifier-deck-matches]');
    if (body) {
      body.innerHTML = matches.length ? matches.map((match) => {
        const isA = match.a === deck.name;
        const rival = isA ? match.b : match.a;
        const result = match.result ? (match.result === 'E' ? 'Empate' : (match.result === (isA ? 'A' : 'B') ? 'Victoria' : 'Derrota')) : 'Pendiente';
        return `
          <tr class="clickable-row" data-deck-match-id="${match.id}">
            <td data-label="ID">#${match.id}</td>
            <td data-label="Fecha"><strong>${match.scheduled_on || 'Sin fecha'}</strong><br><small>${match.starts_at ? match.starts_at.slice(0, 5) : 'Sin hora'}</small></td>
            <td data-label="Ronda">R${match.ronda}</td>
            <td data-label="Mesa">M${match.mesa}</td>
            <td data-label="Rival">${matchLabelWithTeam(rival)}</td>
            <td data-label="Lado">${isA ? 'A' : 'B'}</td>
            <td data-label="Estado"><span class="status-chip">${match.estado || 'PENDIENTE'}</span></td>
            <td data-label="Resultado">${match.score || '0-0'} | ${result}</td>
            <td data-label="Accion"><button class="btn btn-outline-info btn-sm" type="button" data-open-match-sheet data-match-id="${match.id}"><i class="bi bi-eye"></i> Combate</button></td>
          </tr>
        `;
      }).join('') : '<tr><td colspan="9"><div class="empty-state">Este deck aun no tiene combates programados.</div></td></tr>';
      Array.from(body.querySelectorAll('[data-deck-match-id]')).forEach((row) => {
        row.addEventListener('click', () => {
          qualifierDeckSheetModal.hide();
          window.setTimeout(() => openMatchSheet(row.dataset.deckMatchId), 200);
        });
      });
      Array.from(body.querySelectorAll('[data-open-match-sheet]')).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.stopPropagation();
          qualifierDeckSheetModal.hide();
          window.setTimeout(() => openMatchSheet(button.dataset.matchId), 200);
        });
      });
    }
    qualifierDeckSheetModal.show();
  }

  function openMatchSheet(matchId) {
    if (!matchSheetModalNode || !matchSheetModal) return;
    const match = allMatchList().find((item) => String(item.id) === String(matchId));
    if (!match) return;
    activeSheetMatchId = String(match.id);
    const saved = getSavedResult(match);
    const evaluated = evaluateDuel(saved, match);
    const details = parseJsonValue(match.duel_details || '', []);
    const deckA = deckInfoByName(match.a);
    const deckB = deckInfoByName(match.b);
    setMatchSheetText('[data-match-sheet-phase]', (match.phase || 'combate').toUpperCase());
    setMatchSheetText('[data-match-sheet-title]', matchVersusLabel(match));
    setMatchSheetText('[data-match-sheet-meta]', `Ronda ${match.ronda} | Mesa ${match.mesa} | ${String(match.match_format || '1v1').toUpperCase()} | Mejor de ${match.best_of || 5} | ID duelo #${match.id}`);
    const sheetIdentity=matchSheetModalNode.querySelector('[data-match-sheet-identity]');
    if(sheetIdentity) sheetIdentity.innerHTML=calendarEventIdentity({competitionId:match.competition_id,matches:[match],division:match.division,type:match.phase});
    renderMatchLeaguePositions(match, 'match-sheet');
    setMatchSheetText('[data-match-sheet-team-a]', deckA.team || 'Sin equipo');
    setMatchSheetText('[data-match-sheet-team-b]', deckB.team || 'Sin equipo');
    setMatchSheetLogo('[data-match-sheet-logo-a]', matchSideLogoUrl(match, 'a'), matchSideFallback(match, 'a') || 'A', deckA.team || match.a || 'Equipo A');
    setMatchSheetLogo('[data-match-sheet-logo-b]', matchSideLogoUrl(match, 'b'), matchSideFallback(match, 'b') || 'B', deckB.team || match.b || 'Equipo B');
    setMatchSheetText('[data-match-sheet-a]', matchSideDecks(match, 'a').join(' + '));
    setMatchSheetText('[data-match-sheet-b]', matchSideDecks(match, 'b').join(' + '));
    window.YgoMatchSheetLinks?.render({
      modal: matchSheetModalNode, match, decks: data.allDecks || [], competitions: data.competitions || [],
      onDeck(id) { openSearchItem({type:'Deck', id}); },
      onEvent(id) { openSearchItem({type:'Evento', id}); }
    });
    setMatchSheetText('[data-match-sheet-id]', `#${match.id}`);
    setMatchSheetText('[data-match-sheet-status]', match.estado || evaluated.state || 'PENDIENTE');
    setMatchSheetText('[data-match-sheet-score]', match.score || evaluated.score || '0-0');
    setMatchSheetText('[data-match-sheet-winner]', match.result || evaluated.winner || 'Pendiente');
    setMatchSheetText('[data-match-sheet-date]', match.scheduled_on || 'Sin fecha');
    setMatchSheetText('[data-match-sheet-completed]', completionLabel(match.completed_at));
    setMatchSheetText('[data-match-sheet-time]', match.starts_at ? match.starts_at.slice(0, 5) : 'Sin hora');
    setMatchSheetText('[data-match-sheet-venue]', match.venue || 'Station X Tulua');
    setMatchSheetText('[data-match-sheet-judge]', match.judge || 'Por asignar');
    const dice = parseJsonValue(match.dice_roll || '', null);
    const turnText = match.starts_player
      ? `Dado ${dice?.a || '-'}-${dice?.b || '-'} | ${match.turn_chosen_by || dice?.chosen_by || dice?.winner || '-'} eligio ${match.turn_decision || dice?.choice || '-'}; empieza ${match.starts_player}`
      : (match.turn_choice || 'Sin dado registrado');
    setMatchSheetText('[data-match-sheet-turn]', turnText);
    setMatchSheetText('[data-match-sheet-notes]', match.notes || 'Sin notas');
    const bars = matchSheetModalNode.querySelector('[data-match-sheet-duel-bars]');
    if (bars) {
      const labels = ['D1', 'D2', 'D3', 'D4', 'D5'];
      bars.innerHTML = labels.map((label, index) => {
        const value = saved[index] || '';
        if (!value) return '';
        const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === index + 1) || {}) : {};
        const text = value === 'A' ? matchLabelWithTeam(match.a) : (value === 'B' ? matchLabelWithTeam(match.b) : (value === 'E' ? 'Empate' : 'Pendiente'));
        const chooser = detail.turn_chosen_by || (index === 0 ? (match.turn_chosen_by || dice?.chosen_by || dice?.winner || '') : '');
        const decision = detail.turn_decision || (index === 0 ? (match.turn_decision || dice?.choice || '') : '');
        const starts = detail.starts_player || (index === 0 ? (match.starts_player || dice?.starts || '') : '');
        const turnMeta = chooser && decision && starts ? `<small>${index === 0 && dice?.winner ? `Dado ${dice.a}-${dice.b} | ` : ''}${escapeHtml(chooser)} eligio ${escapeHtml(decision)} | Empieza ${escapeHtml(starts)}</small>` : '<small>Inicio pendiente</small>';
        const width = value ? 100 : 18;
        return `<div class="stat-row match-sheet-duel-row"><span>${label}</span><div><i style="width:${width}%"></i></div><b>${text}<small>Finalizacion: ${escapeHtml(completionLabel(detail.completed_at))}</small></b>${turnMeta}</div>`;
      }).join('');
    }
    matchSheetModal.show();
  }

  if (matchSheetModalNode) {
    const capture = matchSheetModalNode.querySelector('[data-match-sheet-capture]');
    const viewResults = matchSheetModalNode.querySelector('[data-match-sheet-results]');
    if (capture) capture.addEventListener('click', () => {
      matchSheetModal.hide();
      window.setTimeout(() => openResultCapture(activeSheetMatchId), 200);
    });
    if (viewResults) viewResults.addEventListener('click', () => {
      matchSheetModal.hide();
      if (resultMatch) resultMatch.value = activeSheetMatchId;
      routeTo('resultados');
      window.setTimeout(loadResult, 80);
    });
  }
  document.querySelectorAll('[data-dashboard-match-open]').forEach((row) => {
    const openDashboardMatch = () => openMatchSheet(row.dataset.matchId || '');
    row.addEventListener('click', openDashboardMatch);
    row.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openDashboardMatch();
      }
    });
  });
  if (qualifierDeckSheetModalNode) {
    const profileButton = qualifierDeckSheetModalNode.querySelector('[data-qualifier-deck-profile]');
    if (profileButton) profileButton.addEventListener('click', () => {
      qualifierDeckSheetModal.hide();
      const select = document.querySelector('[data-deck-select]');
      if (select) select.value = activeQualifierDeckName;
      routeTo('deck');
      window.setTimeout(renderProfile, 80);
    });
  }
  Array.from(document.querySelectorAll('[data-qualifier-deck-row]')).forEach((row) => {
    row.addEventListener('click', () => openQualifierDeckSheet(row.dataset.deckName));
  });
  function sortQualifierRanking() {
    if (!qualifierRankingBody) return;
    const direction = qualifierRankingOrder?.value === 'desc' ? -1 : 1;
    const rows = Array.from(qualifierRankingBody.querySelectorAll('[data-qualifier-position]'));
    rows.sort((left, right) => direction * (Number(left.dataset.qualifierPosition || 0) - Number(right.dataset.qualifierPosition || 0)));
    rows.forEach((row) => qualifierRankingBody.appendChild(row));
  }

  [qualifierMatchSearch, qualifierMatchStatus, qualifierMatchRound, qualifierMatchOrder].forEach((control) => {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', renderQualifierMatchList);
  });
  qualifierRankingOrder?.addEventListener('change', sortQualifierRanking);
  if (qualifierClearFilters) qualifierClearFilters.addEventListener('click', () => {
    if (qualifierMatchSearch) qualifierMatchSearch.value = '';
    if (qualifierMatchStatus) qualifierMatchStatus.value = '';
    if (qualifierMatchRound) qualifierMatchRound.value = '';
    if (qualifierMatchOrder) qualifierMatchOrder.value = 'pending_first';
    renderQualifierMatchList();
  });
  syncQualifierRoundOptions();
  renderQualifierMatchList();
  sortQualifierRanking();

  function openQualifierResultModal(matchId) {
    openResultCapture(matchId);
  }

  document.querySelectorAll('[data-open-result-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      openQualifierResultModal(button.dataset.matchId);
    });
  });

  const tableDivision = document.querySelector('[data-table-division]');
  const standingsBody = document.querySelector('[data-standings-body]');

  function renderStandings() {
    if (!standingsBody || !tableDivision) return;
    const division = tableDivision.value;
    const rows = computeStandings(division);
    if (!rows.some((row) => row.pj > 0)) {
      standingsBody.innerHTML = '<tr><td colspan="11"><div class="empty-state">Sin clasificacion deportiva todavia. Esta fase debe jugarse y tener resultados guardados para ordenar posiciones.</div></td></tr>';
      return;
    }
    standingsBody.innerHTML = rows.map((row, index) => {
      let zone = '';
      if (division !== 'TITAN' && index < 2) zone = 'Ascenso';
      if (division !== 'CHALLENGER' && index >= rows.length - 2) zone = 'Descenso';
      if (division === 'TITAN' && index < 3) zone = 'Eternal';
      return `<tr class="clickable-row" data-table-deck="${row.name}"><td>${index + 1}</td><td><span class="team-inline-badge" style="--team-primary:${row.teamPrimary};--team-secondary:${row.teamSecondary};">${row.teamCode || row.team.slice(0, 3)}</span> <b>${row.team}</b><br><small>${row.name}</small></td><td>${row.pts}</td><td>${row.pj}</td><td>${row.pg}</td><td>${row.pe}</td><td>${row.pp}</td><td>${row.cg}</td><td>${row.cp}</td><td>${row.dp}</td><td>${zone}</td></tr>`;
    }).join('');
    standingsBody.querySelectorAll('[data-table-deck]').forEach((row) => {
      row.addEventListener('click', () => {
        if (deckSelect) deckSelect.value = row.dataset.tableDeck || '';
        renderProfile();
        routeTo('deck');
      });
    });
  }

  if (tableDivision) tableDivision.addEventListener('change', renderStandings);
  renderStandings();

  const deckSelect = document.querySelector('[data-deck-select]');

  function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Number(totalSeconds || 0));
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;
    return `${minutes}:${String(remainder).padStart(2, '0')}`;
  }

