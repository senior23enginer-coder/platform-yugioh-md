/**
 * matchup-analysis: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function analysisList(value) {
    const rows = parseJsonValue(value || '[]', []);
    return Array.isArray(rows) ? rows.filter(Boolean) : [];
  }

  function matchupObservedStats(deck, opponent, filters = {}) {
    const summary = { sourceType: 'CALCULATED', matches: 0, wins: 0, draws: 0, losses: 0, duels: 0, duelWins: 0, duelDraws: 0, duelLosses: 0, firstDuels: 0, firstWins: 0, secondDuels: 0, secondWins: 0, coveredDuels: 0, exactSnapshots: 0, inferredSnapshots: 0, lastPlayed: '' };
    const now = new Date(`${data.serverToday || new Date().toISOString().slice(0, 10)}T23:59:59`);
    const currentSeason = (data.seasons || []).find((season) => Number(season.id) === Number(data.currentSeasonId)) || (data.seasons || []).find((season) => season.status === 'active') || (data.seasons || [])[0] || {};
    individualMatchList().forEach((match) => {
      const side = matchIncludesDeck(match, deck, 'A') ? 'A' : (matchIncludesDeck(match, deck, 'B') ? 'B' : '');
      if (!side || !matchIncludesDeck(match, opponent, side === 'A' ? 'B' : 'A')) return;
      const playedOn = String(match.scheduled_on || '').slice(0, 10);
      const playedDate = playedOn ? new Date(`${playedOn}T12:00:00`) : null;
      if (filters.period === '30' && (!playedDate || (now - playedDate) / 86400000 > 30)) return;
      if (filters.period === '90' && (!playedDate || (now - playedDate) / 86400000 > 90)) return;
      if (filters.period === 'year' && (!playedOn || playedOn.slice(0, 4) !== String(data.serverToday || '').slice(0, 4))) return;
      const competition = (data.competitions || []).find((row) => String(row.id || '') === String(match.competition_id || '')) || {};
      if (filters.period === 'season' && currentSeason.id && String(competition.season_id || '') !== String(currentSeason.id)) return;
      if (filters.event && String(match.competition_id || '') !== String(filters.event)) return;
      if (filters.division && String(competition.division_code || match.division_code || '') !== String(filters.division)) return;
      const ruleVersion = String(match.competition_ruleset_version || competition.ruleset_version || '');
      if (filters.rulesetVersion && ruleVersion !== filters.rulesetVersion) return;
      const snapshots = (data.matchSnapshots || []).filter((row) => String(row.match_id || '') === String(match.id || '') && String(row.deck_id || '') === String(deck.id || ''));
      if (filters.version && !snapshots.some((row) => String(row.variation || '').trim() === filters.version)) return;
      const opponentSnapshots = (data.matchSnapshots || []).filter((row) => String(row.match_id || '') === String(match.id || '') && String(row.deck_id || '') === String(opponent.id || ''));
      if (filters.opponentVersion && !opponentSnapshots.some((row) => String(row.variation || '').trim() === filters.opponentVersion)) return;
      const values = getSavedResult(match).filter(Boolean);
      const result = evaluateDuel(values, match);
      if (result.state !== 'FINALIZADA') return;
      const details = parseJsonValue(match.duel_details || '', []);
      const duelRows = values.map((winner, index) => {
        const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === index + 1) || {}) : {};
        const attributedId = Number(side === 'A' ? detail.deck_a_id : detail.deck_b_id);
        if (attributedId && attributedId !== Number(deck.id)) return null;
        const starts = String(detail.starts_player || (index === 0 ? match.starts_player || '' : '')).toUpperCase();
        const position = starts ? (starts === side ? 'first' : 'second') : '';
        return { winner, detail, position, duel: index + 1 };
      }).filter(Boolean);
      const firstDuelPosition = duelRows[0]?.position || '';
      if (filters.position && firstDuelPosition !== filters.position) return;
      summary.matches += 1;
      if (result.winner === side) summary.wins += 1;
      else if (result.winner === 'E') summary.draws += 1;
      else summary.losses += 1;
      if (playedOn > summary.lastPlayed) summary.lastPlayed = playedOn;
      duelRows.forEach((row) => {
        summary.duels += 1;
        if (row.winner === side) summary.duelWins += 1;
        else if (row.winner === 'E') summary.duelDraws += 1;
        else summary.duelLosses += 1;
        if (row.position === 'first') { summary.firstDuels += 1; if (row.winner === side) summary.firstWins += 1; }
        if (row.position === 'second') { summary.secondDuels += 1; if (row.winner === side) summary.secondWins += 1; }
        if (row.detail && Object.keys(row.detail).length) summary.coveredDuels += 1;
        const snapshot = snapshots.find((item) => Number(item.duel_number || 0) === row.duel);
        if (snapshot) {
          if (String(snapshot.snapshot_status || snapshot.data_origin || '').toLowerCase() === 'exact') summary.exactSnapshots += 1;
          else summary.inferredSnapshots += 1;
        }
      });
    });
    summary.matchWinRate = summary.matches ? Math.round((summary.wins / summary.matches) * 1000) / 10 : 0;
    summary.duelWinRate = summary.duels ? Math.round((summary.duelWins / summary.duels) * 1000) / 10 : 0;
    summary.goingFirstWinRate = summary.firstDuels ? Math.round((summary.firstWins / summary.firstDuels) * 1000) / 10 : null;
    summary.goingSecondWinRate = summary.secondDuels ? Math.round((summary.secondWins / summary.secondDuels) * 1000) / 10 : null;
    return summary;
  }

  function matchupClassificationLabel(value) {
    return ({ MUY_FAVORABLE: 'Muy favorable', FAVORABLE: 'Favorable', EQUILIBRADO: 'Equilibrado', DESFAVORABLE: 'Desfavorable', MUY_DESFAVORABLE: 'Muy desfavorable' })[String(value || '')] || 'Pendiente de analisis';
  }

  function matchupComparison(analysis, observed) {
    const minimum = Math.max(1, Number(settings.stats_minimum_sample || 3));
    const score = analysis ? Number(analysis.strategic_score || 0) : null;
    const sample = observed.matches < minimum ? 'INSUFFICIENT' : (observed.matches < minimum * 2 ? 'INITIAL' : 'TREND');
    if (!analysis || sample === 'INSUFFICIENT') return { code: 'INSUFFICIENT', label: 'Datos insuficientes', review: false, sample, tone: 'neutral' };
    const expected = score > 0 ? 1 : (score < 0 ? -1 : 0);
    const actual = observed.matchWinRate >= 60 ? 1 : (observed.matchWinRate <= 40 ? -1 : 0);
    if (actual === expected) return { code: 'ALIGNED', label: 'Alineado', review: false, sample, tone: 'success' };
    const code = actual > expected ? 'OVERPERFORMING' : 'UNDERPERFORMING';
    return { code, label: code === 'OVERPERFORMING' ? 'Mejor desempeno observado' : 'Menor desempeno observado', review: observed.matches >= minimum * 2, sample, tone: code === 'OVERPERFORMING' ? 'info' : 'warning' };
  }

  function selectMatchupAnalysis(deckId, opponentId, filters = {}) {
    const candidates = (data.matchupAnalyses || []).filter((row) => String(row.deck_id) === String(deckId) && String(row.opponent_deck_id) === String(opponentId));
    const scoped = candidates.filter((row) => (!row.deck_version || row.deck_version === filters.version) && (!row.opponent_version || row.opponent_version === filters.opponentVersion) && (!row.ruleset_version || row.ruleset_version === filters.rulesetVersion));
    return scoped.sort((left, right) => {
      const specificity = (row) => (row.deck_version ? 4 : 0) + (row.opponent_version ? 2 : 0) + (row.ruleset_version ? 1 : 0);
      return specificity(right) - specificity(left) || String(right.updated_at || '').localeCompare(String(left.updated_at || ''));
    })[0] || null;
  }

  function analysisScopeFilters() {
    return { period: matchupPeriod?.value || 'all', event: matchupEvent?.value || '', division: matchupDivision?.value || '', rulesetVersion: matchupRulesetVersion?.value || '', version: matchupVersion?.value || '', opponentVersion: matchupOpponentVersion?.value || '', position: matchupPosition?.value || '' };
  }

  function setMatchupField(selector, value) {
    const field = document.querySelector(selector);
    if (field) field.value = value == null ? '' : String(value);
  }

  function renderDeckStrategyProfile(deck) {
    const profile = (data.deckAnalysisProfiles || []).find((row) => String(row.deck_id) === String(deck.id)) || null;
    const observed = deckProfileCompetitiveStats(deck);
    const pending = '<span class="analysis-pending">Pendiente de documentar</span>';
    const text = (value) => value ? `<p>${escapeHtml(value)}</p>` : pending;
    const list = (value) => {
      const rows = analysisList(value);
      return rows.length ? `<ul>${rows.map((row) => `<li>${escapeHtml(row)}</li>`).join('')}</ul>` : pending;
    };
    const identity = document.querySelector('[data-deck-strategy-identity]');
    if (identity) identity.innerHTML = [
      ['Arquetipo', deck.archetype || deck.name],
      ['Estilo de juego', profile?.play_style || deck.style || 'Pendiente'],
      ['Ritmo', profile?.pace ? String(profile.pace).replaceAll('_', ' ') : 'Pendiente'],
      ['Complejidad', profile?.complexity ? String(profile.complexity).replaceAll('_', ' ') : 'Pendiente'],
      ['Confianza editorial', profile?.confidence || 'Sin evaluar'],
      ['Revision', profile ? `v${Number(profile.revision || 1)}` : 'Sin perfil'],
    ].map(([label, value]) => `<article><span>${label}</span><b>${escapeHtml(value)}</b></article>`).join('');
    const plans = document.querySelector('[data-deck-strategy-plans]');
    if (plans) plans.innerHTML = `<h4>Plan estrategico</h4><article><b>Plan principal</b>${text(profile?.primary_game_plan || deck.reason)}</article><article><b>Condicion de victoria</b>${text(profile?.win_condition)}</article><article><b>Motor de recursos</b>${text(profile?.resource_engine)}</article><div class="strategy-phase-grid"><article><b>Apertura</b>${text(profile?.opening_plan)}</article><article><b>Juego medio</b>${text(profile?.mid_game_plan)}</article><article><b>Juego tardio</b>${text(profile?.late_game_plan)}</article><article><b>Empezando primero</b>${text(profile?.going_first_plan)}</article><article><b>Empezando segundo</b>${text(profile?.going_second_plan)}</article><article><b>Side Deck</b>${text(profile?.side_deck_strategy)}</article></div>`;
    const strengths = document.querySelector('[data-deck-strategy-strengths]');
    if (strengths) strengths.innerHTML = `<h4>Lectura estrategica</h4><article><b>Fortalezas</b>${list(profile?.strengths_json)}</article><article><b>Debilidades</b>${list(profile?.weaknesses_json)}</article><article><b>Etiquetas</b><div class="strategy-tags">${analysisList(profile?.strategy_tags_json).length ? analysisList(profile?.strategy_tags_json).map((row) => `<span>${escapeHtml(row)}</span>`).join('') : pending}</div></article><article><b>Notas</b>${text(profile?.analysis_notes)}</article>`;
    const scoreRows = [
      ['Consistencia', profile?.consistency_score], ['Techo de poder', profile?.ceiling_score], ['Resiliencia', profile?.resilience_score], ['Interaccion', profile?.interaction_score], ['Recuperacion', profile?.recovery_score], ['Primero', profile?.first_score], ['Segundo', profile?.second_score],
    ];
    const scores = document.querySelector('[data-deck-strategy-scores]');
    if (scores) scores.innerHTML = `<h4>Valoracion editorial</h4><div class="strategy-score-list">${scoreRows.map(([label, value]) => { const score = Math.max(0, Math.min(5, Number(value || 0))); return `<div><span>${label}</span><i><em style="width:${score * 20}%"></em></i><b>${score ? `${score}/5` : 'Pendiente'}</b></div>`; }).join('')}</div><div class="observed-profile-facts"><h4>Indicadores observados</h4><article><span>Ritmo real</span><b>${observed.averageTurns ? `${observed.averageTurns} turnos` : 'Sin cobertura'}</b></article><article><span>WR primero</span><b>${observed.firstStarts ? `${observed.firstWinRate}% (${observed.firstStarts})` : 'Sin cobertura'}</b></article><article><span>WR segundo</span><b>${observed.secondStarts ? `${observed.secondWinRate}% (${observed.secondStarts})` : 'Sin cobertura'}</b></article><article><span>Brick observado</span><b>${observed.duelWins + observed.duelDraws + observed.duelLosses ? `${observed.brickRate}%` : 'Sin cobertura'}</b></article></div>`;
    const source = document.querySelector('[data-deck-analysis-source]');
    if (source) source.textContent = profile?.source_type || 'EDITORIAL';
    setMatchupField('[data-deck-analysis-form-id]', deck.id);
    setMatchupField('[data-deck-analysis-play-style]', profile?.play_style || deck.style || '');
    setMatchupField('[data-deck-analysis-pace]', profile?.pace || '');
    setMatchupField('[data-deck-analysis-complexity]', profile?.complexity || '');
    setMatchupField('[data-deck-analysis-confidence]', profile?.confidence || 'MEDIA');
    setMatchupField('[data-deck-analysis-source-type]', profile?.source_type || 'EDITORIAL');
    setMatchupField('[data-deck-analysis-primary-plan]', profile?.primary_game_plan || deck.reason || '');
    setMatchupField('[data-deck-analysis-win-condition]', profile?.win_condition || '');
    setMatchupField('[data-deck-analysis-resource-engine]', profile?.resource_engine || '');
    setMatchupField('[data-deck-analysis-opening-plan]', profile?.opening_plan || '');
    setMatchupField('[data-deck-analysis-mid-plan]', profile?.mid_game_plan || '');
    setMatchupField('[data-deck-analysis-late-plan]', profile?.late_game_plan || '');
    setMatchupField('[data-deck-analysis-first-plan]', profile?.going_first_plan || '');
    setMatchupField('[data-deck-analysis-second-plan]', profile?.going_second_plan || '');
    setMatchupField('[data-deck-analysis-side-strategy]', profile?.side_deck_strategy || '');
    setMatchupField('[data-deck-analysis-strengths]', analysisList(profile?.strengths_json).join('\n'));
    setMatchupField('[data-deck-analysis-weaknesses]', analysisList(profile?.weaknesses_json).join('\n'));
    setMatchupField('[data-deck-analysis-tags]', analysisList(profile?.strategy_tags_json).join('\n'));
    setMatchupField('[data-deck-analysis-consistency]', profile?.consistency_score || 0);
    setMatchupField('[data-deck-analysis-ceiling]', profile?.ceiling_score || 0);
    setMatchupField('[data-deck-analysis-resilience]', profile?.resilience_score || 0);
    setMatchupField('[data-deck-analysis-interaction]', profile?.interaction_score || 0);
    setMatchupField('[data-deck-analysis-recovery]', profile?.recovery_score || 0);
    setMatchupField('[data-deck-analysis-first-score]', profile?.first_score || 0);
    setMatchupField('[data-deck-analysis-second-score]', profile?.second_score || 0);
    setMatchupField('[data-deck-analysis-notes]', profile?.analysis_notes || '');
    const editorState = document.querySelector('[data-deck-analysis-editor-state]');
    const historyCount = (data.deckAnalysisProfileHistory || []).filter((row) => String(row.deck_id) === String(deck.id)).length;
    if (editorState) editorState.textContent = profile ? `Revision ${profile.revision} | ${historyCount} versiones persistidas | actualizada ${String(profile.updated_at || '').slice(0, 16)}` : 'Perfil estrategico pendiente. Los resultados no lo completan automaticamente.';
    const editor = document.querySelector('.deck-strategy-editor');
    if (editor && !profile) editor.open = true;
    window.YgoDeckStrategy?.render(deck, profile, observed);
  }

  function renderDeckAnalysis(deck) {
    if (!deck || !matchupOpponent) return;
    renderDeckStrategyProfile(deck);
    const currentOpponentId = matchupOpponent.value;
    matchupOpponent.innerHTML = (data.allDecks || []).filter((row) => String(row.id) !== String(deck.id)).map((row) => `<option value="${escapeHtml(row.id)}">${escapeHtml(row.name)}${row.team ? ' - ' + escapeHtml(row.team) : ''}</option>`).join('');
    if (Array.from(matchupOpponent.options).some((option) => option.value === currentOpponentId)) matchupOpponent.value = currentOpponentId;
    const opponent = (data.allDecks || []).find((row) => String(row.id) === String(matchupOpponent.value)) || (data.allDecks || []).find((row) => String(row.id) !== String(deck.id));
    if (!opponent) return;
    if (!deckAnalysisFiltersReady) {
      if (matchupEvent) matchupEvent.innerHTML += (data.competitions || []).map((row) => `<option value="${escapeHtml(row.id)}">${escapeHtml(row.name)}</option>`).join('');
      if (matchupDivision) matchupDivision.innerHTML += Object.entries(data.divisions || {}).map(([code, row]) => `<option value="${escapeHtml(code)}">${escapeHtml(row.name || code)}</option>`).join('');
      if (matchupRulesetVersion) matchupRulesetVersion.innerHTML += [...new Set((data.matchSnapshots || []).map((row) => String(row.ruleset_version || '')).filter(Boolean))].sort().map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
      [matchupOpponent, matchupPeriod, matchupEvent, matchupDivision, matchupRulesetVersion, matchupVersion, matchupOpponentVersion, matchupPosition].forEach((field) => field?.addEventListener('change', () => renderDeckAnalysis((data.allDecks || []).find((row) => row.name === deckSelect?.value) || deck)));
      deckAnalysisFiltersReady = true;
    }
    const versions = [...new Set((data.matchSnapshots || []).filter((row) => String(row.deck_id) === String(deck.id)).map((row) => String(row.variation || '').trim()).filter(Boolean))].sort();
    const selectedVersion = matchupVersion?.value || '';
    if (matchupVersion) {
      matchupVersion.innerHTML = '<option value="">Todas las versiones</option>' + versions.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
      if (versions.includes(selectedVersion)) matchupVersion.value = selectedVersion;
    }
    const opponentVersions = [...new Set((data.matchSnapshots || []).filter((row) => String(row.deck_id) === String(opponent.id)).map((row) => String(row.variation || '').trim()).filter(Boolean))].sort();
    const selectedOpponentVersion = matchupOpponentVersion?.value || '';
    if (matchupOpponentVersion) {
      matchupOpponentVersion.innerHTML = '<option value="">Todas las versiones</option>' + opponentVersions.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
      if (opponentVersions.includes(selectedOpponentVersion)) matchupOpponentVersion.value = selectedOpponentVersion;
    }
    const filters = analysisScopeFilters();
    const observed = matchupObservedStats(deck, opponent, filters);
    const analysis = selectMatchupAnalysis(deck.id, opponent.id, filters);
    const comparison = matchupComparison(analysis, observed);
    const globalEntry = globalDeckRanking().findIndex((entry) => String(entry.deck.id) === String(deck.id));
    const tierRow = (data.metaHistory || []).filter((row) => row.group_type === 'deck' && row.group_key === deck.name).sort((a, b) => String(b.snapshot_on || '').localeCompare(String(a.snapshot_on || '')))[0];
    const title = document.querySelector('[data-matchup-analysis-title]');
    if (title) title.textContent = `${deck.name} vs ${opponent.name}`;
    const overview = document.querySelector('[data-matchup-overview]');
    if (overview) overview.innerHTML = `<article><span>Ranking actual</span><b>#${globalEntry >= 0 ? globalEntry + 1 : '-'}</b><small>Ranking global calculado</small></article><article><span>Tier actual</span><b>${escapeHtml(tierRow?.tier || 'Sin tier')}</b><small>${escapeHtml(tierRow?.snapshot_on || 'Sin corte historico')}</small></article><article><span>Rival seleccionado</span><b>${escapeHtml(opponent.name)}</b><small>${escapeHtml(opponent.team || 'Sin equipo')}</small></article><article><span>Cobertura</span><b>${observed.coveredDuels}/${observed.duels} duelos</b><small>${observed.exactSnapshots} exactos, ${observed.inferredSnapshots} inferidos</small></article>`;
    const strategic = document.querySelector('[data-matchup-strategic-summary]');
    if (strategic) strategic.innerHTML = analysis ? `<div class="analysis-score"><strong>${Number(analysis.strategic_score) > 0 ? '+' : ''}${Number(analysis.strategic_score)}</strong><span><b>${matchupClassificationLabel(analysis.classification)}</b><small>Confianza ${escapeHtml(String(analysis.confidence || 'MEDIA').toLowerCase())} | revision ${Number(analysis.revision || 1)}</small></span></div><p>${escapeHtml(analysis.reason || 'Sin razon documentada.')}</p><div class="analysis-factor-grid"><div><b>Ventajas</b>${analysisList(analysis.key_advantages_json).length ? `<ul>${analysisList(analysis.key_advantages_json).map((row) => `<li>${escapeHtml(row)}</li>`).join('')}</ul>` : '<small>Sin registrar</small>'}</div><div><b>Desventajas</b>${analysisList(analysis.key_disadvantages_json).length ? `<ul>${analysisList(analysis.key_disadvantages_json).map((row) => `<li>${escapeHtml(row)}</li>`).join('')}</ul>` : '<small>Sin registrar</small>'}</div><div><b>Choke points</b>${analysisList(analysis.choke_points_json).length ? `<ul>${analysisList(analysis.choke_points_json).map((row) => `<li>${escapeHtml(row)}</li>`).join('')}</ul>` : '<small>Sin registrar</small>'}</div></div>` : '<div class="analysis-empty"><i class="bi bi-pencil-square"></i><b>Analisis estrategico pendiente</b><p>No se genero desde los resultados. Debe documentarse y revisarse manualmente.</p></div>';
    const observedNode = document.querySelector('[data-matchup-observed-summary]');
    if (observedNode) observedNode.innerHTML = `<div class="observed-record"><strong>${observed.wins}-${observed.draws}-${observed.losses}</strong><span><b>${observed.matchWinRate}% WR de partidas</b><small>${observed.matches} partidas | ultima ${escapeHtml(observed.lastPlayed || 'sin registro')}</small></span></div><dl><div><dt>Duelos</dt><dd>${observed.duelWins}-${observed.duelDraws}-${observed.duelLosses} (${observed.duelWinRate}%)</dd></div><div><dt>Empezando primero</dt><dd>${observed.goingFirstWinRate == null ? 'Sin datos' : observed.goingFirstWinRate + '%'} (${observed.firstDuels})</dd></div><div><dt>Empezando segundo</dt><dd>${observed.goingSecondWinRate == null ? 'Sin datos' : observed.goingSecondWinRate + '%'} (${observed.secondDuels})</dd></div><div><dt>Fuente</dt><dd>Resultados persistidos</dd></div></dl>`;
    const comparisonNode = document.querySelector('[data-matchup-comparison]');
    if (comparisonNode) comparisonNode.innerHTML = `<span class="comparison-status is-${comparison.tone}">${escapeHtml(comparison.label)}</span><p>${comparison.code === 'INSUFFICIENT' ? 'Falta analisis editorial o muestra minima para comparar.' : 'La comparacion usa tendencias cualitativas; el winrate nunca modifica el score estrategico.'}</p>${comparison.review ? '<strong><i class="bi bi-exclamation-triangle"></i> Revision recomendada</strong>' : ''}<small>Muestra: ${comparison.sample} | minimo: ${Math.max(1, Number(settings.stats_minimum_sample || 3))} partidas</small>`;

    setMatchupField('[data-matchup-form-deck-id]', deck.id);
    setMatchupField('[data-matchup-form-opponent-id]', opponent.id);
    setMatchupField('[data-matchup-form-score]', analysis?.strategic_score ?? 0);
    setMatchupField('[data-matchup-form-confidence]', analysis?.confidence || 'MEDIA');
    setMatchupField('[data-matchup-form-source]', analysis?.source_type || 'EDITORIAL');
    setMatchupField('[data-matchup-form-ruleset]', analysis?.ruleset_version || filters.rulesetVersion);
    setMatchupField('[data-matchup-form-deck-version]', analysis?.deck_version || filters.version);
    setMatchupField('[data-matchup-form-opponent-version]', analysis?.opponent_version || filters.opponentVersion);
    setMatchupField('[data-matchup-form-reason]', analysis?.reason || '');
    setMatchupField('[data-matchup-form-advantages]', analysisList(analysis?.key_advantages_json).join('\n'));
    setMatchupField('[data-matchup-form-disadvantages]', analysisList(analysis?.key_disadvantages_json).join('\n'));
    setMatchupField('[data-matchup-form-choke-points]', analysisList(analysis?.choke_points_json).join('\n'));
    setMatchupField('[data-matchup-form-first-plan]', analysis?.going_first_plan || '');
    setMatchupField('[data-matchup-form-second-plan]', analysis?.going_second_plan || '');
    setMatchupField('[data-matchup-form-side-plan]', analysis?.side_plan || '');
    setMatchupField('[data-matchup-form-review-notes]', analysis?.review_notes || '');
    const editorState = document.querySelector('[data-matchup-editor-state]');
    if (editorState) editorState.textContent = analysis ? `Revision ${analysis.revision} | actualizada ${String(analysis.updated_at || '').slice(0, 16)}` : 'Sin analisis guardado para este alcance.';
    const historyRows = analysis ? (data.matchupAnalysisHistory || []).filter((row) => String(row.analysis_id) === String(analysis.id)) : [];
    const historyNode = document.querySelector('[data-matchup-history]');
    if (historyNode) historyNode.innerHTML = historyRows.length ? historyRows.map((row) => { const snapshot = parseJsonValue(row.snapshot_json || '{}', {}); return `<article><span>v${Number(row.revision || 1)}</span><div><b>${matchupClassificationLabel(snapshot.classification)} (${Number(snapshot.strategic_score) > 0 ? '+' : ''}${Number(snapshot.strategic_score || 0)})</b><small>${escapeHtml(String(row.created_at || '').slice(0, 16))}</small><p>${escapeHtml(row.change_reason || snapshot.reason || 'Revision guardada')}</p></div></article>`; }).join('') : '<p class="note">Todavia no hay revisiones persistidas para este matchup.</p>';
    const historyCount = document.querySelector('[data-matchup-history-count]');
    if (historyCount) historyCount.textContent = `${historyRows.length} revisiones`;

    const rivalRows = (data.allDecks || []).filter((row) => String(row.id) !== String(deck.id)).map((rival) => {
      const reality = matchupObservedStats(deck, rival, filters);
      const theory = selectMatchupAnalysis(deck.id, rival.id, filters);
      return { rival, reality, theory, comparison: matchupComparison(theory, reality) };
    }).filter((row) => row.reality.matches || row.theory).sort((a, b) => b.reality.matches - a.reality.matches || a.rival.name.localeCompare(b.rival.name));
    const rivalsTable = document.querySelector('[data-matchup-rivals-table]');
    if (rivalsTable) rivalsTable.innerHTML = rivalRows.length ? rivalRows.map((row) => `<tr><td data-label="Rival"><b>${escapeHtml(row.rival.name)}</b><small>${escapeHtml(row.rival.team || 'Sin equipo')}</small></td><td data-label="Teoria">${row.theory ? `${matchupClassificationLabel(row.theory.classification)} (${Number(row.theory.strategic_score) > 0 ? '+' : ''}${Number(row.theory.strategic_score)})` : 'Pendiente'}</td><td data-label="Muestra">${row.reality.matches} partidas</td><td data-label="Partidas G/E/P">${row.reality.wins} / ${row.reality.draws} / ${row.reality.losses}</td><td data-label="WR partidas">${row.reality.matchWinRate}%</td><td data-label="WR duelos">${row.reality.duelWinRate}%</td><td data-label="Comparacion"><span class="comparison-status is-${row.comparison.tone}">${escapeHtml(row.comparison.label)}</span></td><td data-label="Revision">${row.comparison.review ? 'Recomendada' : 'No requerida'}</td></tr>`).join('') : '<tr><td colspan="8">No hay resultados ni analisis editoriales para los filtros seleccionados.</td></tr>';
    const tableCount = document.querySelector('[data-matchup-table-count]');
    if (tableCount) tableCount.textContent = `${rivalRows.length} rivales`;
  }

  function renderDeckListCompetitiveStats(ranking) {
    const positions = new Map(ranking.map((entry, index) => [String(entry.deck.id || ''), { ...entry, position: data.statisticsV8?window.YgoStatistics?.directoryPosition(entry.deck):index+1 }]));
    document.querySelectorAll('[data-deck-list-id]').forEach((row) => {
      const entry = positions.get(String(row.dataset.deckListId || ''));
      if (!entry) return;
      const stat = entry.stat;
      const set = (selector, value) => { const node = row.querySelector(selector); if (node) node.textContent = String(value); };
      const formatLabels = { tcg_advanced: 'TCG', tcg_traditional: 'TCG Tradicional', rush_duel: 'RUSH DUEL', goat: 'GOAT', ocg: 'OCG' };
      const formats = [...new Set((entry.deck.format_codes || []).map((code) => formatLabels[code] || (data.rulesets || []).find((rule) => rule.code === code)?.label || code.replaceAll('_', ' ').toUpperCase()))];
      const division = Object.values(data.divisions || {}).find((item) => String(item.id) === String(entry.deck.division_id));
      const league = (data.competitions || []).find((item) => item.type === 'league' && String(item.id) === String(division?.league_competition_id) && Number(item.active ?? 1) !== 0 && !['completed', 'finished', 'cancelled', 'archived'].includes(item.status));
      const divisionNumber = league ? leagueMovementDivisions(league).findIndex((item) => String(item.id) === String(division.id)) + 1 : 0;
      set('[data-deck-list-format]', formats.join(' / ') || 'Sin formato');
      set('[data-deck-list-league]', league?.name || 'Sin liga actual');
      set('[data-deck-list-division]', divisionNumber ? `Divisi\u00f3n ${divisionNumber}` : 'Sin divisi\u00f3n');
      set('[data-deck-list-matches]', stat.matches);
      set('[data-deck-list-match-record]', `${stat.wins} / ${stat.draws} / ${stat.losses}`);
      const leaguePosition = window.YgoDeckLeaguePosition?.resolve(data, entry.deck, league, division);
      set('[data-deck-list-league-position]', leaguePosition?.label || 'Sin clasificación');
      const positionNode = row.querySelector('[data-deck-list-league-position]');
      if (positionNode) positionNode.title = leaguePosition?.reason || '';
      set('[data-deck-list-winrate]', stat.winRate==null?'N/D':Number(stat.winRate).toFixed(2)+'%');
      const tierRows = window.YgoStatistics?.tierHistory(entry.deck)||[];
      row.dataset.format = normalize(formats.join(' / '));
      row.dataset.tier = normalize(tierRows[0]?.tier || 'Sin tier');
      row.dataset.matches = String(stat.matches || 0);
      row.dataset.winrate = String(stat.winRate || 0);
      row.dataset.rank = String(entry.position || 9999);
      row.dataset.name = normalize(entry.deck.name || '');
    });
    applyDeckListFilters();
    return positions;
  }

  window.addEventListener('ygo:statistics-updated', () => {
    const deck = data.allDecks.find(item => item.name === deckSelect?.value);
    if (!deck) return;
    const info = window.YgoStatistics?.profileRankInfo(deck);
    const label = document.querySelector('[data-profile-rank]'), detail = document.querySelector('[data-profile-rank-detail]');
    if (label && info) label.textContent = info.label;
    if (detail && info) detail.textContent = info.detail;
  });

