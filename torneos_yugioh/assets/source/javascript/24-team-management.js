/**
 * team-management: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function setValue(selector, value) {
    const node = document.querySelector(selector);
    if (node) node.value = value == null ? '' : value;
  }

  function openGestionModule(module, payload = {}) {
    const target = module || 'resumen';
    document.querySelectorAll('[data-gestion-tab]').forEach((button) => {
      button.classList.toggle('active', button.dataset.gestionTab === target);
    });
    document.querySelectorAll('[data-gestion-module]').forEach((section) => {
      const visible = section.dataset.gestionModule === target || (target === 'resumen' && section.dataset.gestionModule === 'resumen');
      section.hidden = !visible;
    });
    localStorage.setItem('dml_gestion_module', target);
    if (payload.deckId && adminDeckSelect) {
      adminDeckSelect.value = String(payload.deckId);
      adminDeckSelect.dispatchEvent(new Event('change'));
    }
    if (payload.teamId && adminTeamSelect) {
      adminTeamSelect.value = String(payload.teamId);
      adminTeamSelect.dispatchEvent(new Event('change'));
    }
    if (payload.divisionId && adminDivisionSelect) {
      adminDivisionSelect.value = String(payload.divisionId);
      adminDivisionSelect.dispatchEvent(new Event('change'));
    }
    if (payload.matchId && adminMatchSelect) {
      adminMatchSelect.value = String(payload.matchId);
      adminMatchSelect.dispatchEvent(new Event('change'));
    }
    if (payload.competitionId && adminCompetitionSelect) {
      adminCompetitionSelect.value = String(payload.competitionId);
      adminCompetitionSelect.dispatchEvent(new Event('change'));
    }
    if (!payload.noRoute) {
      if (target === 'decks') routeTo('deck');
      else if (target === 'equipos') routeTo('equipos');
      else routeTo('gestion');
    }
  }

  document.querySelectorAll('[data-gestion-tab]').forEach((button) => {
    button.addEventListener('click', () => openGestionModule(button.dataset.gestionTab || 'resumen'));
  });

  document.querySelectorAll('[data-open-gestion-module]').forEach((button) => {
    button.addEventListener('click', () => {
      const payload = {
        deckId: button.dataset.deckId,
        teamId: button.dataset.gestionTeamId,
        divisionId: button.dataset.gestionDivisionId,
        matchId: button.dataset.gestionMatchId,
        competitionId: button.dataset.gestionCompetitionId
      };
      openGestionModule(button.dataset.openGestionModule || 'resumen', payload);
    });
  });
  window.addEventListener('dml:routechange', (event) => {
    if (event.detail?.target === 'deck') {
      openGestionModule('decks', { noRoute: true });
    } else if (event.detail?.target === 'equipos') {
      openGestionModule('equipos', { noRoute: true });
    }
  });

  function renderTeamSheet(team) {
    const currentDecks = (data.allDecks || []).filter((deck) => String(deck.team_id || '') === String(team?.id || ''));
    const availableDecks = (data.allDecks || []).filter((deck) => !String(deck.team_id || ''));
    const removeSelect = document.querySelector('[data-team-remove-decks]');
    const addSelect = document.querySelector('[data-team-add-decks]');
    const deckPicker = document.querySelector('[data-team-deck-picker]');
    const deckPickerOptions = document.querySelector('[data-team-deck-picker-options]');
    const addPickedDeck = document.querySelector('[data-team-add-picked-deck]');
    const summary = document.querySelector('[data-team-link-summary]');
    const list = document.querySelector('[data-team-sheet-current-decks]');
    const count = document.querySelector('[data-team-sheet-deck-count]');
    const logoPreview = document.querySelector('[data-team-logo-preview]');
    const selectedAdd = new Set();
    const selectedRemove = new Set();
    if (teamCalendarButton) {
      teamCalendarButton.dataset.teamId = team?.id || '';
      teamCalendarButton.disabled = !team?.id;
    }

    const setTeamStat = (selector, value) => {
      const node = document.querySelector(selector);
      if (node) node.textContent = String(value);
    };

    const renderTeamCompetitiveProfile = () => {
      const deckIds = new Set(currentDecks.map((deck) => String(deck.id || '')));
      const deckNames = new Set(currentDecks.map((deck) => normalize(deck.name || '')));
      const eventStats = new Map();
      const opponentStats = new Map();
      const totals = { matches: 0, wins: 0, losses: 0, draws: 0, duelsWon: 0, duelsDrawn: 0, duelsLost: 0, bricks: 0, turnsRecorded: 0, totalTurns: 0 };
      if (team) {
        allMatchList().forEach((match) => {
          const sideA = deckIds.has(String(match.deck_a_id || '')) || deckNames.has(normalize(match.a || ''));
          const sideB = deckIds.has(String(match.deck_b_id || '')) || deckNames.has(normalize(match.b || ''));
          if (!sideA && !sideB) return;
          const result = String(match.result || '').toUpperCase();
          const score = String(match.score || '0-0').split('-').map((value) => Number(value) || 0);
          const finalized = ['FINALIZADA', 'FINALIZADO', 'COMPLETED'].includes(String(match.estado || match.status || '').toUpperCase()) || ['A', 'B', 'E'].includes(result);
          if (!finalized) return;
          const competition = (data.competitions || []).find((item) => String(item.id || '') === String(match.competition_id || '')) || {};
          const eventId = String(match.competition_id || `phase:${match.phase || match.division || 'general'}`);
          const stats = eventStats.get(eventId) || {
            name: match.competition_name || competition.name || match.division || 'Evento',
            format: competition.event_ruleset || match.competition_ruleset || match.phase || 'general',
            matches: 0, wins: 0, losses: 0, draws: 0, duelsWon: 0, duelsDrawn: 0, duelsLost: 0
          };
          const won = (sideA && result === 'A') || (sideB && result === 'B');
          const lost = (sideA && result === 'B') || (sideB && result === 'A');
          const drawn = result === 'E';
          const wonDuels = sideA ? score[0] : score[1];
          const lostDuels = sideA ? score[1] : score[0];
          stats.matches += 1;
          totals.matches += 1;
          stats.duelsWon += wonDuels;
          stats.duelsLost += lostDuels;
          totals.duelsWon += wonDuels;
          totals.duelsLost += lostDuels;
          const drawnDuels = getSavedResult(match).filter((winner) => winner === 'E').length;
          stats.duelsDrawn += drawnDuels;
          totals.duelsDrawn += drawnDuels;
          if (won) { stats.wins += 1; totals.wins += 1; }
          if (lost) { stats.losses += 1; totals.losses += 1; }
          if (drawn) { stats.draws += 1; totals.draws += 1; }
          const duelDetails = parseJsonValue(match.duel_details || '', []);
          getSavedResult(match).filter(Boolean).forEach((duelWinner, duelIndex) => {
            const detail = Array.isArray(duelDetails) ? (duelDetails.find((item) => Number(item.duel) === duelIndex + 1) || {}) : {};
            if (sideA ? detail.brick_a : detail.brick_b) totals.bricks += 1;
            const turns = Math.max(0, Number(detail.turn_count || 0));
            if (turns) { totals.turnsRecorded += 1; totals.totalTurns += turns; }
            const rivalDeckId = Number(sideA ? detail.deck_b_id : detail.deck_a_id);
            const rivalDeck = (data.allDecks || []).find((deck) => Number(deck.id) === rivalDeckId);
            const rivalName = rivalDeck?.name || matchSideDecks(match, sideA ? 'b' : 'a')[0] || 'Rival sin identificar';
            const rival = opponentStats.get(rivalName) || { name: rivalName, wins: 0, losses: 0, draws: 0 };
            if ((sideA && duelWinner === 'A') || (sideB && duelWinner === 'B')) rival.wins += 1;
            else if (duelWinner === 'E') rival.draws += 1;
            else rival.losses += 1;
            opponentStats.set(rivalName, rival);
          });
          eventStats.set(eventId, stats);
        });
      }

      const winrate = totals.matches ? Math.round((totals.wins / totals.matches) * 100) : 0;
      setTeamStat('[data-team-stat-events]', eventStats.size);
      setTeamStat('[data-team-stat-matches]', totals.matches);
      setTeamStat('[data-team-stat-wins]', totals.wins);
      setTeamStat('[data-team-stat-losses]', totals.losses);
      setTeamStat('[data-team-stat-draws]', totals.draws);
      setTeamStat('[data-team-stat-duels-won]', totals.duelsWon);
      setTeamStat('[data-team-stat-duels-lost]', totals.duelsLost);
      setTeamStat('[data-team-stat-duels-drawn]', totals.duelsDrawn);
      setTeamStat('[data-team-stat-winrate]', `${winrate}%`);
      setTeamStat('[data-team-stat-bricks]', totals.bricks);
      setTeamStat('[data-team-stat-turns]', totals.turnsRecorded ? Math.round((totals.totalTurns / totals.turnsRecorded) * 10) / 10 : 'N/D');
      setTeamStat('[data-team-stats-record]', `${totals.wins}-${totals.draws}-${totals.losses}`);
      const exportButton = document.querySelector('[data-team-export-pdf]');
      if (exportButton) exportButton.href = team ? './?export=entity_report&entity_type=team&entity_id=' + encodeURIComponent(team.id || '') + '&format=pdf' : '#';
      const teamTierRows = (data.metaHistory || []).filter((row) => String(row.group_type || '') === 'deck' && currentDecks.some((deck) => String(deck.name) === String(row.group_key))).sort((a, b) => String(b.snapshot_on || '').localeCompare(String(a.snapshot_on || '')));
      const tierWeight = { 'Tier Zero': 0, 'Tier 1': 1, 'Tier 2': 2, 'Tier 3': 3 };
      const latestByDeck = new Map(); teamTierRows.forEach((row) => { if (!latestByDeck.has(row.group_key)) latestByDeck.set(row.group_key, row); });
      const currentTier = Array.from(latestByDeck.values()).sort((a, b) => (tierWeight[a.tier] ?? 99) - (tierWeight[b.tier] ?? 99))[0];
      const bestTier = teamTierRows.slice().sort((a, b) => (tierWeight[a.tier] ?? 99) - (tierWeight[b.tier] ?? 99) || String(a.snapshot_on || '').localeCompare(String(b.snapshot_on || '')))[0];
      const teamTierHistory = document.querySelector('[data-team-tier-history]');
      if (teamTierHistory) teamTierHistory.innerHTML = `<article><span>Tier actual mas alto</span><b>${escapeHtml(currentTier?.tier || 'Sin tier')}</b><small>${escapeHtml(currentTier?.group_key || 'Sin resultados')}</small></article><article><span>Maximo historico</span><b>${escapeHtml(bestTier?.tier || 'Sin tier')}</b><small>${escapeHtml(bestTier?.snapshot_on || 'Sin fecha')}</small></article>`;
      const shareTeam = document.querySelector('[data-share-team-profile]');
      if (shareTeam) shareTeam.onclick = () => team && shareCanvas('Perfil de equipo', [team.name, `${currentDecks.length} decks`, `${totals.wins}/${totals.draws}/${totals.losses} partidas`, `${totals.duelsWon}/${totals.duelsDrawn}/${totals.duelsLost} duelos`, `${winrate}% winrate`, currentTier?.tier || 'Sin tier'], 'equipo-' + normalize(team.name).replaceAll(' ', '-') + '.png');
      const renderTeamRivals = (selector, rows, key, empty) => {
        const node = document.querySelector(selector);
        if (!node) return;
        node.innerHTML = rows.length ? rows.map((row, index) => `<span><b>#${index + 1} ${escapeHtml(row.name)}</b><small>${row[key]} ${row[key] === 1 ? 'duelo' : 'duelos'}</small></span>`).join('') : `<p class="note">${empty}</p>`;
      };
      const rivalRows = Array.from(opponentStats.values());
      renderTeamRivals('[data-team-top-beaten]', rivalRows.filter((row) => row.wins).sort((a, b) => b.wins - a.wins || a.name.localeCompare(b.name)).slice(0, 3), 'wins', 'Sin victorias registradas.');
      renderTeamRivals('[data-team-top-lost-to]', rivalRows.filter((row) => row.losses).sort((a, b) => b.losses - a.losses || a.name.localeCompare(b.name)).slice(0, 3), 'losses', 'Sin derrotas registradas.');

      const honors = team ? (data.honors || []).filter((honor) =>
        String(honor.champion_team_id || '') === String(team.id || '') || deckIds.has(String(honor.champion_deck_id || ''))
      ) : [];
      setTeamStat('[data-team-honor-count]', `${honors.length} ${honors.length === 1 ? 'logro' : 'logros'}`);
      const shelf = document.querySelector('[data-team-honor-shelf]');
      if (shelf) {
        shelf.innerHTML = honors.length ? honors.map((honor) => {
          const competition = (data.competitions || []).find((item) => String(item.id || '') === String(honor.competition_id || '')) || {};
          const icon = competition.type === 'league' ? 'bi-shield-check' : competition.type === 'qualifier' ? 'bi-award' : competition.type === 'world' ? 'bi-globe-americas' : 'bi-trophy-fill';
          return `<article class="team-honor-medal">
            <i class="bi ${icon}"></i>
            <span><b>${escapeHtml(honor.title || 'Campeon')}</b><small>${escapeHtml(honor.competition_name || competition.name || 'Logro general')}</small><small>${escapeHtml(String(honor.created_at || '').slice(0, 10))}</small></span>
          </article>`;
        }).join('') : '<p class="note">Este equipo aun no tiene eventos ganados.</p>';
        const teamMedals = team ? (data.medalUnlocks || []).filter((unlock) => unlock.entity_type === 'team' && String(unlock.entity_id) === String(team.id)) : [];
        if (teamMedals.length) shelf.insertAdjacentHTML('beforeend', teamMedals.map((unlock) => '<article class="team-honor-medal"><i class="bi bi-award-fill"></i><span><b>' + escapeHtml(unlock.medal_name) + '</b><small>' + escapeHtml(unlock.rarity || 'common') + ' | ' + escapeHtml(String(unlock.unlocked_at || '').slice(0, 10)) + '</small></span></article>').join(''));
        const teamProgress = team ? (data.medalProgress || []).filter((row) => row.entity_type === 'team' && String(row.entity_id) === String(team.id) && !Number(row.unlocked || 0)).slice(0, 8) : [];
        if (teamProgress.length) shelf.insertAdjacentHTML('beforeend', '<section class="team-medal-progress"><h3>Proximas medallas</h3>' + teamProgress.map((row) => `<article class="team-honor-medal is-progress"><i class="bi bi-hourglass-split"></i><span><b>${escapeHtml(row.secret ? 'Medalla secreta' : row.medal_name)}</b><small>${Number(row.current_value || 0)}/${Number(row.target_value || 1)} | ${escapeHtml(row.rarity || 'common')}</small></span></article>`).join('') + '</section>');
      }

      const history = document.querySelector('[data-team-event-history]');
      if (history) {
        const rows = Array.from(eventStats.values()).sort((left, right) => right.wins - left.wins || right.duelsWon - left.duelsWon || left.name.localeCompare(right.name));
        history.innerHTML = rows.length ? rows.map((stats) => {
          const eventWinrate = stats.matches ? Math.round((stats.wins / stats.matches) * 100) : 0;
          return `<tr>
            <td data-label="Evento"><strong>${escapeHtml(stats.name)}</strong></td>
            <td data-label="Formato"><span class="tag">${escapeHtml(String(stats.format).replace(/_/g, ' '))}</span></td>
            <td data-label="MJ">${stats.matches}</td><td data-label="MG">${stats.wins}</td><td data-label="MP">${stats.losses}</td>
            <td data-label="DG">${stats.duelsWon}</td><td data-label="DP">${stats.duelsLost}</td><td data-label="Winrate"><strong>${eventWinrate}%</strong></td>
          </tr>`;
        }).join('') : '<tr><td colspan="8"><div class="empty-state">Este equipo aun no tiene resultados finalizados.</div></td></tr>';
      }
    };
    const syncTeamDeckSelections = () => {
      if (addSelect) {
        addSelect.innerHTML = Array.from(selectedAdd).map((id) => `<option value="${escapeHtml(id)}" selected>${escapeHtml(id)}</option>`).join('');
      }
      if (removeSelect) {
        removeSelect.innerHTML = Array.from(selectedRemove).map((id) => `<option value="${escapeHtml(id)}" selected>${escapeHtml(id)}</option>`).join('');
      }
      const addTotal = selectedAdd.size;
      const removeTotal = selectedRemove.size;
      if (summary) {
        summary.textContent = addTotal || removeTotal
          ? `${addTotal} por asociar | ${removeTotal} por quitar`
          : 'Selecciona decks para asociar o quitar.';
      }
    };
    setValue('[data-team-link-team-id]', team ? team.id : '');
    if (count) count.textContent = `${currentDecks.length} decks`;
    if (logoPreview) {
      const logo = String(team?.logo_url || '').trim();
      logoPreview.innerHTML = logo
        ? `<img src="${escapeHtml(logo)}" alt="${escapeHtml(team?.name || 'Equipo')}">`
        : '<i class="bi bi-shield"></i>';
    }
    if (deckPicker) {
      deckPicker.value = '';
      deckPicker.disabled = !team;
    }
    if (deckPickerOptions) {
      deckPickerOptions.innerHTML = availableDecks
        .map((deck) => `<option value="${escapeHtml(deck.name || 'Deck')} | ${escapeHtml(deck.division || 'Sin division')} | ${escapeHtml(deck.archetype || '')}" data-id="${escapeHtml(deck.id)}"></option>`)
        .join('');
    }
    if (list) {
      list.innerHTML = currentDecks.length
        ? currentDecks.map((deck) => `
          <span class="team-assigned-deck" data-team-current-deck="${escapeHtml(deck.id)}">
            <b>${escapeHtml(deck.name || 'Deck')}</b>
            <small>${escapeHtml(deck.division || 'Sin division')} | ${escapeHtml(deck.archetype || deck.name || '')}</small>
            <button class="btn btn-outline-light btn-sm" type="button" data-team-remove-current="${escapeHtml(deck.id)}"><i class="bi bi-x-lg"></i> Quitar</button>
          </span>`).join('')
        : '<p class="note">Este equipo aun no tiene decks asociados.</p>';
      list.querySelectorAll('[data-team-remove-current]').forEach((button) => {
        button.addEventListener('click', () => {
          const id = String(button.dataset.teamRemoveCurrent || '');
          if (selectedRemove.has(id)) selectedRemove.delete(id);
          else selectedRemove.add(id);
          button.closest('[data-team-current-deck]')?.classList.toggle('selected-remove', selectedRemove.has(id));
          syncTeamDeckSelections();
        });
      });
    }
    if (addPickedDeck) {
      addPickedDeck.onclick = () => {
        if (!team || !deckPicker) return;
        const picked = availableDecks.find((deck) => {
          const label = `${deck.name || 'Deck'} | ${deck.division || 'Sin division'} | ${deck.archetype || ''}`;
          return label === deckPicker.value || String(deck.id) === deckPicker.value;
        });
        if (!picked) return;
        selectedAdd.add(String(picked.id));
        deckPicker.value = '';
        syncTeamDeckSelections();
      };
    }
    renderTeamCompetitiveProfile();
    syncTeamDeckSelections();
  }

  const adminTeamSelect = document.querySelector('[data-gestion-team-select]');
  const teamSheetModalNode = document.querySelector('[data-team-sheet-modal]');
  const teamSheetModal = teamSheetModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(teamSheetModalNode) : null;
  if (adminTeamSelect) {
    adminTeamSelect.addEventListener('change', () => {
      const team = (data.teams || []).find((item) => String(item.id) === adminTeamSelect.value);
      setValue('[data-gestion-team-id]', team ? team.id : '');
      setValue('[data-gestion-team-name]', team ? team.name : '');
      setValue('[data-gestion-team-city]', team ? team.city : '');
      setValue('[data-gestion-team-code]', team ? team.short_code : '');
      setValue('[data-gestion-team-symbol]', team ? team.symbol : '');
      renderTeamSheet(team);
    });
  }

  document.querySelectorAll('[data-team-deck]').forEach((button) => {
    button.addEventListener('click', () => {
      if (deckSelect) deckSelect.value = button.dataset.teamDeck || '';
      renderProfile();
      routeTo('deck_ficha');
    });
  });

  document.querySelectorAll('[data-open-deck-profile], [data-open-deck-sheet]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.openDeckSheet || '';
      const deck = id ? (data.allDecks || []).find((item) => String(item.id) === String(id)) : null;
      if (adminDeckSelect) {
        adminDeckSelect.value = deck ? String(deck.id) : '';
        adminDeckSelect.dispatchEvent(new Event('change'));
      }
      if (deckSelect) deckSelect.value = deck ? deck.name : (button.dataset.openDeckProfile || deckSelect.value || '');
      renderProfile();
      routeTo('deck_ficha');
    });
  });

  document.querySelectorAll('[data-open-team-sheet]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.openTeamSheet || '';
      if (adminTeamSelect) {
        adminTeamSelect.value = id;
        adminTeamSelect.dispatchEvent(new Event('change'));
      }
      routeTo('equipo_ficha');
    });
  });

  document.querySelectorAll('[data-open-team-decks]').forEach((button) => {
    button.addEventListener('click', () => {
      const teamId = document.querySelector('[data-gestion-team-id]')?.value || '';
      if (!teamId) {
        window.alert('Guarda o selecciona un equipo antes de asociar decks.');
        return;
      }
      if (adminTeamSelect) {
        adminTeamSelect.value = teamId;
        adminTeamSelect.dispatchEvent(new Event('change'));
      }
      if (teamSheetModal) teamSheetModal.show();
    });
  });

