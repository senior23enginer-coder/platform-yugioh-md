/**
 * analytics: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function renderDataQuality(model) {
    const body = document.querySelector('[data-quality-body]');
    if (!body) return;
    const rows = [];
    model.matches.forEach((match) => {
      const values = getSavedResult(match).filter(Boolean);
      if (evaluateDuel(values, match).state !== 'FINALIZADA') return;
      const details = parseJsonValue(match.duel_details || '', []);
      values.forEach((winner, index) => {
        const duel = index + 1;
        const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === duel) || {}) : {};
        const snapshots = (data.matchSnapshots || []).filter((snapshot) => String(snapshot.match_id) === String(match.id) && Number(snapshot.duel_number) === duel);
        const turnHistory = parseJsonValue(detail.turn_history || detail.turn_history_json || '', []);
        const hasValue = (key) => detail[key] !== undefined && detail[key] !== null && detail[key] !== '';
        const checks = [
          { key: 'player', label: 'jugadores', ok: Number(detail.player_a_id || 0) > 0 && Number(detail.player_b_id || 0) > 0 },
          { key: 'lp', label: 'LP', ok: hasValue('lp_a') && hasValue('lp_b') },
          { key: 'turns', label: 'turnos', ok: Number(detail.turn_count || 0) > 0 },
          { key: 'turnHistory', label: 'historial de turnos', ok: Array.isArray(turnHistory) && turnHistory.length > 0 },
          { key: 'duration', label: 'duracion', ok: Number(detail.duration_seconds || 0) > 0 },
          { key: 'brick', label: 'brick', ok: hasValue('brick_a') && hasValue('brick_b') },
          { key: 'closure', label: 'forma de cierre', ok: Boolean(detail.defeat_method || detail.finish_method) },
          { key: 'decks', label: 'decks participantes', ok: Number(detail.deck_a_id || match.deck_a_id || 0) > 0 && Number(detail.deck_b_id || match.deck_b_id || 0) > 0 },
          { key: 'snapshot', label: 'fotografia historica', ok: snapshots.some((snapshot) => snapshot.side === 'A') && snapshots.some((snapshot) => snapshot.side === 'B') },
          { key: 'result', label: 'resultado confirmado', ok: ['A', 'B', 'E'].includes(String(winner || '').toUpperCase()) }
        ];
        if (duel === 1) checks.unshift({ key: 'dice', label: 'dados', ok: Number(detail.dice_a || match.dice_a || 0) > 0 && Number(detail.dice_b || match.dice_b || 0) > 0 });
        const source = normalize(detail.data_origin || detail.source || detail.import_source || snapshots[0]?.data_origin || match.data_origin || '');
        const origin = source.includes('infer') || source.includes('backfill') ? 'inferred'
          : source.includes('add') ? 'added'
            : ((Array.isArray(turnHistory) && turnHistory.length) || detail.duel_started_at ? 'captured' : 'unknown');
        const covered = checks.filter((check) => check.ok).length;
        rows.push({
          match, duel, origin, covered, total: checks.length,
          complete: covered === checks.length,
          missing: checks.filter((check) => !check.ok).map((check) => check.label),
          hasPlayers: checks.find((check) => check.key === 'player')?.ok || false,
          hasTurnHistory: checks.find((check) => check.key === 'turnHistory')?.ok || false
        });
      });
    });
    const setKpi = (name, value) => { const node = document.querySelector('[data-quality-kpi="' + name + '"]'); if (node) node.textContent = String(value); };
    const totalChecks = rows.reduce((sum, row) => sum + row.total, 0);
    const coveredChecks = rows.reduce((sum, row) => sum + row.covered, 0);
    setKpi('duels', rows.length);
    setKpi('complete', rows.filter((row) => row.complete).length);
    setKpi('inferred', rows.filter((row) => row.origin === 'inferred').length);
    setKpi('players', rows.filter((row) => row.hasPlayers).length);
    setKpi('turnHistory', rows.filter((row) => row.hasTurnHistory).length);
    setKpi('coverage', totalChecks ? Math.round((coveredChecks / totalChecks) * 100) + '%' : '0%');
    const status = document.querySelector('[data-quality-filter="status"]')?.value || '';
    const origin = document.querySelector('[data-quality-filter="origin"]')?.value || '';
    const visible = rows.filter((row) => (!status || (status === 'complete' ? row.complete : !row.complete)) && (!origin || row.origin === origin));
    const originLabels = { captured: 'Capturado en duelo', added: 'Agregado despues', inferred: 'Inferido historicamente', unknown: 'Origen no registrado' };
    body.innerHTML = visible.length ? visible.map((row) => {
      const competition = statsCompetition(row.match);
      const pct = Math.round((row.covered / row.total) * 100);
      return '<tr><td data-label="Evento / partida"><b>' + escapeHtml(competition?.name || row.match.competition_name || 'Sin evento') + '</b><small class="d-block">R' + escapeHtml(row.match.ronda || '-') + ' M' + escapeHtml(row.match.mesa || '-') + ' | ' + escapeHtml(row.match.a || '') + ' vs ' + escapeHtml(row.match.b || '') + '</small></td><td data-label="Duelo">D' + row.duel + '</td><td data-label="Procedencia"><span class="quality-origin is-' + row.origin + '">' + originLabels[row.origin] + '</span></td><td data-label="Cobertura"><b>' + row.covered + '/' + row.total + ' (' + pct + '%)</b></td><td data-label="Faltantes">' + (row.missing.length ? escapeHtml(row.missing.join(', ')) : '<span class="text-success">Completo</span>') + '</td><td data-label="Accion"><button class="btn btn-outline-info btn-sm" type="button" data-quality-open-match="' + escapeHtml(row.match.id) + '"><i class="bi bi-eye"></i> Revisar</button></td></tr>';
    }).join('') : statsEmpty('No hay duelos que coincidan con estos filtros.', 6);
    body.querySelectorAll('[data-quality-open-match]').forEach((button) => button.addEventListener('click', () => openResultCapture(button.dataset.qualityOpenMatch)));
  }

  function renderAdvancedAnalytics(model, tieredRows) {
    const body = document.querySelector('[data-stats-advanced-body]');
    const topBody = document.querySelector('[data-stats-advanced-top-body]');
    const tierBody = document.querySelector('[data-stats-tier-performance]');
    const sample = model.context.sample;
    const tierByDeck = new Map(tieredRows.map((row) => [row.name, row.tier]));
    const tierOrder = ['Tier Zero', 'Tier 1', 'Tier 2', 'Tier 3'];
    const tierSample = Math.max(1, Number(settings.tier_sample_minimum || sample));
    const tierAtDate = (deckName, playedOn, formatCode) => {
      const historical = (data.metaHistory || []).filter((row) => String(row.group_type || '') === 'deck' && String(row.group_key || '') === String(deckName) && (!formatCode || String(row.format_code || '') === String(formatCode)) && String(row.snapshot_on || '') <= String(playedOn || '9999-12-31')).sort((a, b) => String(b.snapshot_on || '').localeCompare(String(a.snapshot_on || '')))[0];
      return String(historical?.tier || tierByDeck.get(deckName) || 'Tier 3');
    };
    const tierRecords = new Map(model.decks.map((deck) => [deck.name, Object.fromEntries(tierOrder.map((tier) => [tier, { wins: 0, draws: 0, losses: 0, matches: 0 }]))]));
    model.matches.forEach((match) => {
      const result = evaluateDuel(getSavedResult(match), match);
      if (result.state !== 'FINALIZADA') return;
      const sideA = matchSideDecks(match, 'a').map((name) => model.decks.find((deck) => deck.name === name)).filter(Boolean);
      const sideB = matchSideDecks(match, 'b').map((name) => model.decks.find((deck) => deck.name === name)).filter(Boolean);
      const playedOn = match.scheduled_on || statsCompetition(match)?.starts_on || serverToday;
      const formatCode = match.competition_ruleset || statsCompetition(match)?.event_ruleset || '';
      sideA.forEach((deck) => sideB.forEach((opponent) => {
        const record = tierRecords.get(deck.name)?.[tierAtDate(opponent.name, playedOn, formatCode)]; if (!record) return;
        record.matches += 1; if (result.winner === 'A') record.wins += 1; else if (result.winner === 'E') record.draws += 1; else record.losses += 1;
      }));
      sideB.forEach((deck) => sideA.forEach((opponent) => {
        const record = tierRecords.get(deck.name)?.[tierAtDate(opponent.name, playedOn, formatCode)]; if (!record) return;
        record.matches += 1; if (result.winner === 'B') record.wins += 1; else if (result.winner === 'E') record.draws += 1; else record.losses += 1;
      }));
    });
    model.decks.forEach((deck) => {
      deck.tierRecords = tierRecords.get(deck.name) || {};
      const eligibleTier = tierOrder.map((tier) => ({ tier, ...(deck.tierRecords[tier] || {}) })).filter((record) => record.matches >= tierSample).map((record) => ({ ...record, rate: Math.round(((record.wins + record.draws * .5) / record.matches) * 100) })).sort((a, b) => b.rate - a.rate || b.matches - a.matches)[0];
      deck.tierPerformance = eligibleTier?.rate || 0;
      deck.tierPerformanceLabel = eligibleTier ? eligibleTier.tier : 'Muestra insuficiente';
      deck.firstWins = deck.firstWins || 0;
      deck.secondWins = deck.secondWins || 0;
      deck.perfectMatches = deck.sweeps;
      deck.lowBrick = deck.brickRate;
    });
    if (tierBody) {
      const cell = (record) => record && record.matches ? `<b>${record.wins}/${record.draws}/${record.losses}</b><small>${Math.round(((record.wins + record.draws * .5) / record.matches) * 100)}% | ${record.matches} PJ${record.matches < tierSample ? ' | muestra insuficiente' : ''}</small>` : '<span class="note">Sin enfrentamientos</span>';
      const rows = model.decks.filter((deck) => deck.matches).sort((a, b) => b.matches - a.matches);
      tierBody.innerHTML = rows.length ? rows.map((deck) => `<tr><td data-label="Deck"><b>${escapeHtml(deck.name)}</b><small>${escapeHtml(deck.team)}</small></td>${tierOrder.map((tier) => `<td data-label="${tier}">${cell(deck.tierRecords[tier])}</td>`).join('')}</tr>`).join('') : statsEmpty('No hay partidas finalizadas para cruzar contra tiers.', 5);
    }
    if (body) {
      const rows = model.decks.filter((deck) => deck.matches > 0).sort((a, b) => b.matches - a.matches || b.points - a.points);
      body.innerHTML = rows.length ? rows.map((deck) => '<tr><td data-label="Deck"><b>' + escapeHtml(deck.name) + '</b><small class="d-block">' + escapeHtml(deck.team) + '</small></td><td data-label="Fuerza rival">' + (deck.strength || 'N/D') + '</td><td data-label="Dado ganado">' + (deck.dicePlayed ? deck.diceRate + '% (' + deck.diceWins + '/' + deck.dicePlayed + ')' : 'N/D') + '<small class="d-block">Efectividad: ' + (deck.diceWins ? deck.diceEffectiveness + '%' : 'N/D') + '</small></td><td data-label="WR D1-D5">' + deck.positionRecord + '</td><td data-label="Con brick">' + (deck.brickHands ? deck.brickWinRate + '% (' + deck.brickWins + '/' + deck.brickHands + ')' : 'N/D') + '</td><td data-label="LP +/-">' + (deck.lpDiffCount ? (deck.averageLpDiff > 0 ? '+' : '') + deck.averageLpDiff : 'N/D') + '</td><td data-label="Dano/turno">' + (deck.damageTurns ? deck.damagePerTurn + ' LP' : 'N/D') + '</td><td data-label="Turnos V/D">' + (deck.winTurns || 'N/D') + ' / ' + (deck.lossTurns || 'N/D') + '</td><td data-label="Tiempo V/D">' + (deck.winDuration ? formatDuration(deck.winDuration) : 'N/D') + ' / ' + (deck.lossDuration ? formatDuration(deck.lossDuration) : 'N/D') + '</td><td data-label="Rendicion F/P">' + deck.surrenderWins + ' / ' + deck.surrenderLosses + '</td><td data-label="Estabilidad">' + deck.stability + '%</td><td data-label="Confianza">' + deck.confidence + '%</td></tr>').join('') : statsEmpty('No hay partidas finalizadas para calcular indicadores avanzados.', 12);
    }
    if (topBody) {
      const key = analyticsAdvancedTop?.value || 'tierPerformance';
      const lowerIsBetter = key === 'lowBrick' || key === 'fastestSeconds' || key === 'fastestTurns';
      const eligible = model.decks.filter((deck) => deck.matches >= sample).filter((deck) => !['fastestSeconds', 'fastestTurns'].includes(key) || Number(deck[key] || 0) > 0);
      eligible.sort((a, b) => lowerIsBetter ? Number(a[key] || Number.MAX_SAFE_INTEGER) - Number(b[key] || Number.MAX_SAFE_INTEGER) : Number(b[key] || 0) - Number(a[key] || 0));
      const formatValue = (deck) => {
        if (key === 'fastestSeconds') return formatDuration(deck[key]);
        if (key === 'lowBrick') return deck[key] + '% brick';
        if (['brickWins'].includes(key)) return deck[key] + ' victorias';
        if (key === 'damageDealt' || key === 'damagePerTurn') return Number(deck[key] || 0).toLocaleString('es-CO') + ' LP';
        if (key === 'tierPerformance') return escapeHtml(deck.tierPerformanceLabel) + ': ' + deck[key] + '%';
        if (key === 'repeatedRival') return escapeHtml(deck.repeatedRivalLabel) + ': ' + deck[key] + ' duelos';
        if (key === 'favorableMatchup') return escapeHtml(deck.favorableMatchupLabel) + ': ' + deck[key] + ' victorias';
        if (key === 'difficultMatchup') return escapeHtml(deck.difficultMatchupLabel) + ': ' + deck[key] + ' derrotas';
        if (key === 'rankingRise') return deck[key] ? '+' + deck[key] + ' posiciones' : 'Sin subida';
        if (key === 'rankingFall') return deck[key] ? '-' + deck[key] + ' posiciones' : 'Sin caida';
        if (key === 'bestDebut') return deck[key] + '% en primeras ' + Number(settings.best_debut_min_matches || 3);
        return String(deck[key] || 0);
      };
      topBody.innerHTML = eligible.length ? eligible.slice(0, 15).map((deck, index) => '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Deck / equipo"><b>' + escapeHtml(deck.name) + '</b><small class="d-block">' + escapeHtml(deck.team) + '</small></td><td data-label="Valor"><b>' + formatValue(deck) + '</b></td><td data-label="Muestra">' + deck.matches + ' partidas</td><td data-label="Cobertura">LP ' + deck.lpDiffCount + ' | turnos ' + deck.turnsRecorded + '</td></tr>').join('') : statsEmpty('No hay decks con la muestra minima de ' + sample + ' partidas para este top.', 5);
    }
    const aggregate = (field) => {
      const map = new Map();
      model.decks.forEach((deck) => (deck[field] || []).forEach((row) => {
        const current = map.get(row.name) || { name: row.name, value: 0, matches: 0 };
        current.value += row.wins; current.matches += row.matches; map.set(row.name, current);
      }));
      return Array.from(map.values()).map((row) => ({ ...row, division: row.matches + ' partidas | ' + (row.matches ? Math.round((row.value / row.matches) * 100) : 0) + '% WR' })).sort((a, b) => b.value - a.value || b.matches - a.matches).slice(0, 10);
    };
    const closes = new Map();
    model.decks.forEach((deck) => deck.closes.forEach((row) => closes.set(row.name, (closes.get(row.name) || 0) + row.value)));
    renderCompactBars(document.querySelector('[data-stats-close-scores]'), Array.from(closes.entries()).map(([name, value]) => ({ name, value, division: 'cierres' })).sort((a, b) => b.value - a.value), 'value');
    renderCompactBars(document.querySelector('[data-stats-venues]'), aggregate('venues'), 'value');
    renderCompactBars(document.querySelector('[data-stats-judges]'), aggregate('judges'), 'value');
  }

  function renderMetaEvolution(matches, groupBy, period, context) {
    const bucketLabel = (match) => {
      const date = new Date(String(match.scheduled_on || statsCompetition(match)?.starts_on || serverToday) + 'T12:00:00');
      if (period === 'season') return statsCompetition(match)?.season_id ? 'Temporada ' + statsCompetition(match).season_id : 'Sin temporada';
      if (period === 'month') return date.toISOString().slice(0, 7);
      const first = new Date(date); first.setDate(date.getDate() - ((date.getDay() + 6) % 7));
      return 'Semana ' + first.toISOString().slice(0, 10);
    };
    const buckets = new Map();
    matches.forEach((match) => { const key = bucketLabel(match); if (!buckets.has(key)) buckets.set(key, []); buckets.get(key).push(match); });
    const periods = Array.from(buckets.entries()).sort((a, b) => a[0].localeCompare(b[0])).map(([label, rows]) => {
      const meta = buildMetaPerformance(rows, groupBy);
      const ranked = meta.rows.map((row) => ({ ...row, tier: row.share >= 35 && row.winRate >= 65 ? 'Tier Zero' : row.metaScore >= 70 ? 'Tier 1' : row.metaScore >= 45 ? 'Tier 2' : 'Tier 3' })).sort((a, b) => b.metaScore - a.metaScore);
      return { label, rows: ranked, leader: ranked[0] };
    });
    const evolution = document.querySelector('[data-stats-meta-evolution]');
    if (evolution) evolution.innerHTML = periods.length ? periods.slice(-12).reverse().map((item) => '<div><time>' + escapeHtml(item.label) + '</time><span>' + escapeHtml(item.leader?.tier || 'Sin tier') + '</span><b>' + escapeHtml(item.leader?.name || 'Sin resultados') + '<small> ' + (item.leader?.share || 0) + '% presencia | ' + (item.leader?.winRate || 0) + '% WR</small></b></div>').join('') : '<div class="empty-state">Sin periodos con resultados.</div>';
    const movements = document.querySelector('[data-stats-meta-movements]');
    if (movements) {
      const previous = periods.at(-2)?.rows || [];
      const current = periods.at(-1)?.rows || [];
      const previousNames = new Set(previous.filter((row) => ['Tier Zero', 'Tier 1', 'Tier 2'].includes(row.tier)).map((row) => row.name));
      const currentNames = new Set(current.filter((row) => ['Tier Zero', 'Tier 1', 'Tier 2'].includes(row.tier)).map((row) => row.name));
      const changes = [...Array.from(currentNames).filter((name) => !previousNames.has(name)).map((name) => ({ name, type: 'Entrada' })), ...Array.from(previousNames).filter((name) => !currentNames.has(name)).map((name) => ({ name, type: 'Salida' }))];
      movements.innerHTML = changes.length ? changes.map((row) => '<div><time>' + escapeHtml(periods.at(-1)?.label || '') + '</time><span>' + row.type + '</span><b>' + escapeHtml(row.name) + '</b></div>').join('') : '<div class="empty-state">Sin entradas o salidas entre los dos ultimos periodos.</div>';
    }
    const distribution = new Map();
    matches.forEach((match) => {
      const competition = statsCompetition(match);
      [String(match.competition_ruleset || competition?.event_ruleset || 'general').toUpperCase(), String(match.division || competition?.division_code || 'Sin division'), String(match.competition_type || competition?.type || match.phase || 'general').toUpperCase()].forEach((name) => distribution.set(name, (distribution.get(name) || 0) + 1));
    });
    renderCompactBars(document.querySelector('[data-stats-meta-distribution]'), Array.from(distribution.entries()).map(([name, value]) => ({ name, value, division: 'partidas' })).sort((a, b) => b.value - a.value).slice(0, 12), 'value');
    const updated = document.querySelector('[data-stats-meta-updated]');
    if (updated) updated.textContent = 'Actualizado: ' + (matches.map((match) => match.scheduled_on).filter(Boolean).sort().at(-1) || serverToday) + ' | muestra minima ' + context.sample;
  }

  function shareCanvas(title, lines, filename) {
    title = window.StationXLabels?.text(title) || title;
    lines = lines.map((line) => window.StationXLabels?.text(line) || line);
    const canvas = document.createElement('canvas'); canvas.width = 1080; canvas.height = 1080;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#07101f'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#ffd25f'; ctx.lineWidth = 10; ctx.strokeRect(36, 36, 1008, 1008);
    ctx.fillStyle = '#ffd25f'; ctx.font = 'bold 42px Arial'; ctx.fillText('STATION X TULUA', 80, 120);
    ctx.fillStyle = '#f2f6ff'; ctx.font = 'bold 62px Arial'; ctx.fillText(title.slice(0, 28), 80, 220);
    ctx.font = '34px Arial'; lines.slice(0, 10).forEach((line, index) => ctx.fillText(String(line).slice(0, 46), 80, 310 + (index * 66)));
    ctx.fillStyle = '#58ddff'; ctx.font = '28px Arial'; ctx.fillText('stationxtulua.com/yugioh', 80, 990);
    const link = document.createElement('a'); link.download = filename; link.href = canvas.toDataURL('image/png'); link.click();
  }

  function renderOperations(model, tieredRows) {
    const live = document.querySelector('[data-live-match-center]');
    if (live) {
      const rows = model.matches.filter((match) => !['FINALIZADA', 'COMPLETED'].includes(String(match.estado || match.status || '').toUpperCase()) && evaluateDuel(getSavedResult(match), match).state !== 'FINALIZADA').sort((a, b) => String(a.scheduled_on || '').localeCompare(String(b.scheduled_on || ''))).slice(0, 12);
      live.innerHTML = rows.length ? rows.map((match) => '<button type="button" data-live-open="' + escapeHtml(match.id) + '"><span>R' + escapeHtml(match.ronda || '-') + ' M' + escapeHtml(match.mesa || '-') + '</span><b>' + escapeHtml(match.a) + ' vs ' + escapeHtml(match.b) + '</b><small>' + escapeHtml(match.scheduled_on || 'Sin fecha') + ' ' + escapeHtml(String(match.starts_at || '').slice(0, 5)) + ' | ' + escapeHtml(match.estado || 'PENDIENTE') + '</small></button>').join('') : '<div class="empty-state">No hay partidas pendientes o en curso en este alcance.</div>';
      live.querySelectorAll('[data-live-open]').forEach((button) => button.addEventListener('click', () => openResultCapture(button.dataset.liveOpen)));
    }
    const scoutA = document.querySelector('[data-scout-a]');
    const scoutB = document.querySelector('[data-scout-b]');
    const scoutReport = document.querySelector('[data-scout-report]');
    if (scoutA && scoutB && scoutReport) {
      const previousA = scoutA.value; const previousB = scoutB.value;
      const options = model.decks.map((deck) => '<option value="' + escapeHtml(deck.name) + '">' + escapeHtml(deck.name + ' / ' + deck.team) + '</option>').join('');
      scoutA.innerHTML = options; scoutB.innerHTML = options;
      scoutA.value = model.decks.some((deck) => deck.name === previousA) ? previousA : (model.decks[0]?.name || '');
      scoutB.value = model.decks.some((deck) => deck.name === previousB) ? previousB : (model.decks[1]?.name || model.decks[0]?.name || '');
      const renderScout = () => {
        const a = model.decks.find((deck) => deck.name === scoutA.value); const b = model.decks.find((deck) => deck.name === scoutB.value);
        const matchup = model.matchups.find((row) => [row.a, row.b].includes(a?.name) && [row.a, row.b].includes(b?.name));
        if (!a || !b) { scoutReport.innerHTML = '<div class="empty-state">Selecciona dos decks.</div>'; return; }
        scoutReport.innerHTML = '<div class="scout-summary"><article><span>' + escapeHtml(a.name) + '</span><b>' + a.winRate + '% WR</b><small>Elo ' + a.elo + ' | Power 5 ' + a.power5 + '% | ' + (tieredRows.find((row) => row.name === a.name)?.tier || 'Sin tier') + '</small></article><article><span>' + escapeHtml(b.name) + '</span><b>' + b.winRate + '% WR</b><small>Elo ' + b.elo + ' | Power 5 ' + b.power5 + '% | ' + (tieredRows.find((row) => row.name === b.name)?.tier || 'Sin tier') + '</small></article></div><p class="note">Enfrentamiento directo: ' + (matchup ? matchup.matches + ' partidas | ' + matchup.aWins + '-' + matchup.draws + '-' + matchup.bWins : 'sin historial') + '. Ritmo: ' + (a.averageTurns || 'N/D') + ' vs ' + (b.averageTurns || 'N/D') + ' turnos promedio.</p>';
      };
      scoutA.onchange = renderScout; scoutB.onchange = renderScout; renderScout();
      const shareScout = document.querySelector('[data-share-scout]');
      if (shareScout) shareScout.onclick = () => shareCanvas('Informe de scouting', [scoutA.value, 'VS', scoutB.value, scoutReport.textContent.trim()], 'scouting-station-x.png');
    }
    const checkinMatch = document.querySelector('[data-checkin-match]');
    if (checkinMatch) {
      const previous = checkinMatch.value;
      checkinMatch.innerHTML = model.matches.map((match) => '<option value="' + escapeHtml(match.id) + '" data-competition="' + escapeHtml(match.competition_id || '') + '">R' + escapeHtml(match.ronda || '-') + ' M' + escapeHtml(match.mesa || '-') + ' | ' + escapeHtml(match.a) + ' vs ' + escapeHtml(match.b) + '</option>').join('');
      if (Array.from(checkinMatch.options).some((option) => option.value === previous)) checkinMatch.value = previous;
      const syncCheckin = () => {
        const option = checkinMatch.selectedOptions[0];
        const code = 'YGO-' + (option?.dataset.competition || '0') + '-' + (checkinMatch.value || '0');
        const matchInput = document.querySelector('[data-checkin-match-id]'); const competitionInput = document.querySelector('[data-checkin-competition-id]'); const codeInput = document.querySelector('[data-checkin-code]');
        if (matchInput) matchInput.value = checkinMatch.value; if (competitionInput) competitionInput.value = option?.dataset.competition || ''; if (codeInput) codeInput.value = code;
      };
      checkinMatch.onchange = syncCheckin; syncCheckin();
      const qrButton = document.querySelector('[data-generate-checkin-qr]');
      if (qrButton) qrButton.onclick = () => {
        const target = document.querySelector('[data-checkin-qr]'); if (!target) return; target.innerHTML = '';
        const code = document.querySelector('[data-checkin-code]')?.value || '';
        const url = location.origin + location.pathname + '?checkin_match=' + encodeURIComponent(checkinMatch.value) + '&checkin_code=' + encodeURIComponent(code) + '#estadisticas';
        if (window.QRCode) new window.QRCode(target, { text: url, width: 180, height: 180, colorDark: '#07101f', colorLight: '#ffffff' });
        else target.textContent = url;
      };
    }
    const checkinList = document.querySelector('[data-checkin-list]');
    if (checkinList) checkinList.innerHTML = (data.checkins || []).length ? (data.checkins || []).slice(0, 20).map((row) => '<span><b>' + escapeHtml(row.display_name) + '</b><small>' + escapeHtml(row.entity_type) + ' | ' + escapeHtml(row.checked_in_at || '') + '</small></span>').join('') : '<p class="note">Todavia no hay check-ins registrados.</p>';
    const judgeBoard = document.querySelector('[data-judge-board]');
    if (judgeBoard) {
      const today = serverToday;
      const overdue = model.matches.filter((match) => match.scheduled_on && match.scheduled_on < today && evaluateDuel(getSavedResult(match), match).state !== 'FINALIZADA');
      const incomplete = model.matches.filter((match) => evaluateDuel(getSavedResult(match), match).state === 'FINALIZADA' && !String(match.duel_details || '').includes('turn_history'));
      const corrections = (data.audit || []).filter((row) => ['save_result', 'turn_added', 'blocked_result_by_date'].includes(String(row.action || '')));
      judgeBoard.innerHTML = '<article><span>Partidas retrasadas</span><b>' + overdue.length + '</b><small>' + (overdue.slice(0, 3).map((row) => 'R' + row.ronda + ' M' + row.mesa).join(', ') || 'Sin retrasos') + '</small></article><article><span>Datos incompletos</span><b>' + incomplete.length + '</b><small>Revisar en Calidad de datos</small></article><article><span>Cambios auditados</span><b>' + corrections.length + '</b><small>Guardados, turnos y bloqueos</small></article>';
    }
    const audit = document.querySelector('[data-result-audit-history]');
    if (audit) audit.innerHTML = (data.audit || []).filter((row) => ['save_result', 'turn_added', 'blocked_result_by_date'].includes(String(row.action || ''))).slice(0, 40).map((row) => '<div><time>' + escapeHtml(String(row.created_at || '').slice(0, 16)) + '</time><span>' + escapeHtml(row.action || '') + '</span><b>Partida #' + escapeHtml(row.entity_id || '') + '</b></div>').join('') || '<div class="empty-state">Sin cambios de resultados auditados.</div>';
    const eventSelect = document.querySelector('[data-event-summary-select]');
    const eventSummary = document.querySelector('[data-event-auto-summary]');
    if (eventSelect && eventSummary) {
      const previous = eventSelect.value;
      eventSelect.innerHTML = model.events.map((event) => '<option value="' + escapeHtml(event.id) + '">' + escapeHtml(event.name) + '</option>').join('');
      if (model.events.some((event) => event.id === previous)) eventSelect.value = previous;
      const renderSummary = () => {
        const event = model.events.find((row) => String(row.id) === String(eventSelect.value));
        if (!event) { eventSummary.innerHTML = '<div class="empty-state">Sin evento seleccionado.</div>'; return; }
        const leader = Array.from(event.leaders.entries()).sort((a, b) => b[1] - a[1])[0];
        const persisted = (data.eventSummaries || []).find((row) => String(row.competition_id) === String(event.id));
        eventSummary.innerHTML = '<div class="event-auto-summary"><b>' + escapeHtml(event.name) + '</b><span>' + event.finalized + '/' + event.matches + ' partidas finalizadas</span><span>' + event.duels + ' duelos guardados</span><span>' + (persisted ? 'Campeon congelado: ' + escapeHtml(persisted.champion_name || 'Sin definir') : 'Lider actual: ' + escapeHtml(leader?.[0] || 'Sin lider') + (leader ? ' (' + leader[1] + ' pts)' : '')) + '</span><small>' + (persisted ? 'Resumen final persistido el ' + escapeHtml(String(persisted.finalized_on || '').slice(0, 16)) : (event.finalized === event.matches && event.matches ? 'Evento listo para cierre.' : 'Evento en progreso o pendiente de resultados.')) + '</small><div><a class="btn btn-outline-info btn-sm" href="./?export=entity_report&entity_type=event&entity_id=' + encodeURIComponent(event.id) + '&format=pdf"><i class="bi bi-file-earmark-pdf"></i> PDF final</a> <button class="btn btn-outline-info btn-sm" type="button" data-share-event-summary><i class="bi bi-share"></i> Compartir</button></div></div>';
        eventSummary.querySelector('[data-share-event-summary]')?.addEventListener('click', () => shareCanvas('Resultado de evento', [event.name, persisted ? 'Campeon: ' + (persisted.champion_name || 'Sin definir') : 'Lider: ' + (leader?.[0] || 'Sin lider'), persisted?.runner_up_name ? 'Subcampeon: ' + persisted.runner_up_name : '', `${event.finalized} partidas finalizadas`, `${event.duels} duelos guardados`], 'evento-' + normalize(event.name).replaceAll(' ', '-') + '.png'));
      };
      eventSelect.onchange = renderSummary; renderSummary();
    }
    const shareRanking = document.querySelector('[data-share-ranking]');
    if (shareRanking) shareRanking.onclick = () => shareCanvas('Ranking competitivo', model.decks.slice().sort((a, b) => b.points - a.points).slice(0, 8).map((deck, index) => '#' + (index + 1) + ' ' + deck.name + ' - ' + deck.points + ' pts'), 'ranking-station-x.png');
    const publicSeasonLink = document.querySelector('[data-public-season-link]');
    if (publicSeasonLink) {
      const activeSeason = (data.seasons || []).find((season) => Number(season.id) === Number(data.currentSeasonId)) || (data.seasons || []).find((season) => season.status === 'active') || (data.seasons || [])[0] || {};
      const seasonId = model.context.season || activeSeason.id || '';
      publicSeasonLink.href = './?public_season=' + encodeURIComponent(seasonId) + '#estadisticas';
    }
    const printButton = document.querySelector('[data-stats-print]'); if (printButton) printButton.onclick = () => window.print();
  }

