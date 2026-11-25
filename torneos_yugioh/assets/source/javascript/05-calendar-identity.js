/**
 * calendar-identity: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function formatDate(value) {
    if (!value) return 'Sin fecha';
    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function isoDate(date) {
    return date.toISOString().slice(0, 10);
  }

  function addDays(value, days) {
    const base = value ? new Date(`${value}T12:00:00`) : new Date();
    base.setDate(base.getDate() + days);
    return isoDate(base);
  }

  function calendarTeamMark(match, side) {
    const logo = matchSideLogoUrl(match, side);
    const fallback = matchSideFallback(match, side);
    const teamName = side === 'a' ? (match.team_a || deckInfoByName(match.a).team || match.a) : (match.team_b || deckInfoByName(match.b).team || match.b);
    return logo
      ? `<span class="calendar-team-logo"><img loading="lazy" decoding="async" src="${escapeHtml(logo)}" alt="${escapeHtml(teamName || 'Equipo')}"></span>`
      : `<span class="calendar-team-logo calendar-team-logo-fallback">${escapeHtml(fallback)}</span>`;
  }

  function calendarMatchCard(match, event, attribute = 'data-calendar-match-id') {
    if(match.ffa_id)return `<a class="calendar-match-card" href="?event_id=${Number(match.competition_id)}#evento_ficha"><b>Battle Royale · Mesa ${Number(match.ffa_id)}</b><span>${escapeHtml(match.player_names)}</span><span>${escapeHtml(match.estado)} · ${escapeHtml(match.starts_at||'')}</span><small>Consultar mesa multijugador</small></a>`;
    const timing = matchTimingStatus(match);
    const deckA = deckInfoByName(match.a);
    const deckB = deckInfoByName(match.b);
    const score = match.score && match.score !== '0-0' ? match.score : 'VS';
    return `<button class="calendar-match-card" type="button" ${attribute}="${escapeHtml(match.id)}">
      <span class="calendar-match-meta"><b>${match.starts_at ? match.starts_at.slice(0, 5) : (event.time ? event.time.slice(0, 5) : 'Sin hora')}</b><small>R${escapeHtml(match.ronda || event.round || '-')} · M${escapeHtml(match.mesa || '-')}</small></span>
      <span class="calendar-match-team">${calendarTeamMark(match, 'a')}<span><strong>${escapeHtml(deckA.team || match.team_a || 'Equipo A')}</strong><small>${escapeHtml(match.a || 'Deck A')}</small></span></span>
      <em>${escapeHtml(score)}</em>
      <span class="calendar-match-team side-b">${calendarTeamMark(match, 'b')}<span><strong>${escapeHtml(deckB.team || match.team_b || 'Equipo B')}</strong><small>${escapeHtml(match.b || 'Deck B')}</small></span></span>
      <span class="status-chip ${escapeHtml(timing.className)}">${escapeHtml(timing.label)}</span>
      <i class="bi bi-chevron-right" aria-hidden="true"></i>
    </button>`;
  }

  function isManualFriendly(match) {
    return Boolean(match?.manual_friendly)
      || (String(match?.phase || '').toLowerCase() === 'friendly' && !match?.competition_id);
  }

  function openDeleteFriendlyModal(match) {
    if (!deleteFriendlyModalNode || !deleteFriendlyModal || !isManualFriendly(match)) return;
    const idInput = deleteFriendlyModalNode.querySelector('[data-delete-friendly-match-id]');
    const label = deleteFriendlyModalNode.querySelector('[data-delete-friendly-label]');
    if (idInput) idInput.value = String(match.id || '');
    if (label) label.textContent = `${matchVersusLabel(match)}${match.scheduled_on ? ` | ${formatDate(match.scheduled_on)}` : ''}`;
    deleteFriendlyModal.show();
  }

  function settingEnabled(key) {
    return ['1', 'true', 'yes', 'on'].includes(String(settings[key] || '0').toLowerCase());
  }

  function matchTimingStatus(match) {
    if (!match || match.estado === 'FINALIZADA') {
      return { key: 'done', label: 'Finalizado', className: 'ok', canCapture: true };
    }
    if (match.competition_can_execute === false || match.competition_can_execute === '0') {
      return {
        key: 'competition_blocked',
        label: match.competition_blocked_reason || 'Evento bloqueado por prerequisitos',
        className: 'blocked',
        canCapture: false
      };
    }
    if (['EN CURSO', 'IN_PROGRESS', 'RUNNING', 'PAUSED'].includes(String(match.estado || match.status || '').toUpperCase())) {
      const canCapture = Boolean(match.scheduled_on) && (match.scheduled_on < serverToday ? settingEnabled('allow_overdue_results') : match.scheduled_on > serverToday ? settingEnabled('allow_early_results') : true);
      return { key: 'in_progress', label: 'En curso', className: 'ok', canCapture };
    }
    if (!match.scheduled_on) {
      return { key: 'unscheduled', label: 'Sin fecha', className: 'warn', canCapture: false };
    }
    if (match.scheduled_on < serverToday) {
      return { key: 'overdue', label: 'Vencido', className: 'danger', canCapture: settingEnabled('allow_overdue_results') };
    }
    if (match.scheduled_on === serverToday) {
      return { key: 'today', label: 'Hoy', className: 'ok', canCapture: true };
    }
    return {
      key: 'future',
      label: settingEnabled('allow_early_results') ? 'Adelantable' : 'Fecha futura',
      className: settingEnabled('allow_early_results') ? 'warn' : 'blocked',
      canCapture: settingEnabled('allow_early_results')
    };
  }

  function isPlayableDate(dayIso) {
    if (!dayIso) return false;
    const date = new Date(`${dayIso}T12:00:00`);
    if (Number.isNaN(date.getTime())) return false;
    const weekday = String(date.getDay());
    const validDays = String(settings.valid_weekdays || '1,2,3,4,5,6,0').split(',').map((value) => value.trim()).filter(Boolean);
    const specialDays = String(settings.special_days || '').split(/[\s,]+/).map((value) => value.trim()).filter(Boolean);
    return validDays.includes(weekday) || (settingEnabled('allow_special_days') && specialDays.includes(dayIso));
  }

  function operationalAlertBuckets() {
    const matches = alertableMatchList();
    const tomorrow = addDays(serverToday, 1);
    const running = match => ['en_curso', 'in_progress', 'running', 'paused', 'requiere_d5'].includes(normalizeStatusKey(match.estado || match.status || ''));
    const pending = matches.filter(match => !running(match));
    const byDate = (a, b) => String(a.scheduled_on || '9999-12-31').localeCompare(String(b.scheduled_on || '9999-12-31'))
      || String(a.starts_at || '99:99:99').localeCompare(String(b.starts_at || '99:99:99'))
      || Number(a.mesa || 0) - Number(b.mesa || 0) || Number(a.id || 0) - Number(b.id || 0);
    return {
      inProgress: matches.filter(running).sort(byDate),
      overdue: pending.filter(match => match.scheduled_on && match.scheduled_on < serverToday).sort(byDate),
      today: pending.filter(match => match.scheduled_on === serverToday).sort(byDate),
      upcoming: pending.filter(match => match.scheduled_on === tomorrow).sort(byDate),
      unscheduled: pending.filter(match => !match.scheduled_on).sort(byDate),
      invalidDays: matches.filter(match => match.scheduled_on && !isPlayableDate(match.scheduled_on)).sort(byDate),
    };
  }

  function matchLeagueSideText(info, side, includeNames = false) {
    const rows = info?.[side] || [];
    return rows.map(row => `${includeNames || rows.length > 1 ? row.name + ': ' : ''}${row.label}`).join(' · ') || 'Sin clasificación';
  }

  function renderMatchLeaguePositions(match, prefix) {
    const info = window.YgoDeckLeaguePosition?.forMatch(data, match);
    ['a', 'b'].forEach(side => {
      const node = document.querySelector(`[data-${prefix}-league-${side}]`);
      if (!node) return;
      node.hidden = !info;
      node.textContent = info ? `Posición en liga: ${matchLeagueSideText(info, side)}` : '';
    });
  }

  function alertMatchContext(match) {
    const event = (data.competitions || []).find(c => String(c.id) === String(match.competition_id));
    const league = event?.type === 'league' || match.phase === 'league';
    const originalName = event?.name || match.competition_name || 'Amistoso';
    const name = window.StationXLabels?.text(originalName) || originalName.replace(/^(.+) League$/, 'Liga $1');
    const division = data.divisions?.[match.division] || Object.values(data.divisions || {}).find(d => Number(match.division_id) > 0 && Number(d.id) === Number(match.division_id));
    const ownDivision = league && division && String(division.league_competition_id) === String(match.competition_id);
    let divisionName = ownDivision ? String(division.name || '') : '';
    if (divisionName.startsWith(originalName)) divisionName = divisionName.slice(originalName.length).replace(/^[\s\u2014\u2013-]+/u, '');
    const code = event?.event_ruleset || match.competition_ruleset || '';
    const format = ({tcg_advanced:'TCG',tcg_traditional:'TCG Tradicional',rush_duel:'RUSH DUEL',goat:'GOAT'})[code] || (data.rulesets || []).find(r => r.code === code)?.label || 'Sin formato registrado';
    return `<span class="alert-match-context"><b>${league ? 'Liga' : 'Evento'}:</b> ${escapeHtml(name)}<br><b>Formato:</b> ${escapeHtml(format)}${league ? `<br><b>División:</b> ${escapeHtml(divisionName || 'Sin división registrada')}` : ''}</span>`;
  }

  function alertMatchColor(match) {
    const event = (data.competitions || []).find(item => String(item.id) === String(match.competition_id));
    const type = styleForEvent(event?.type || match.phase || 'tournament');
    return calendarFormatStyle(event?.event_ruleset || match.competition_ruleset || match.division, type.accent, event?.type || match.competition_type || match.phase).accent;
  }

  function alertCard(title, items, tone, emptyText) {
    return `
      <article class="alert-bucket ${tone}">
        <header><span>${title}</span><b>${items.length}</b></header>
        ${items.length ? `<div>${items.map((match) => `
          <button type="button" data-alert-open-match="${match.id}" style="--accent:${escapeHtml(alertMatchColor(match))}">
            <strong>#${match.id} | Ronda ${match.ronda} · Mesa ${match.mesa}</strong>
            <span>${escapeHtml(matchVersusLabel(match))}</span>
            ${alertMatchContext(match)}
            ${(() => { const info = window.YgoDeckLeaguePosition?.forMatch(data, match); return info ? `<span class="match-league-position"><b>Posiciones en liga</b><br>${escapeHtml(matchLeagueSideText(info, 'a', true))}<br>${escapeHtml(matchLeagueSideText(info, 'b', true))}</span>` : ''; })()}
            <small>${escapeHtml(match.scheduled_on || 'Sin fecha')} ${escapeHtml(match.starts_at ? match.starts_at.slice(0, 5) : '')} | ${escapeHtml(match.estado || 'PENDIENTE')}</small>
          </button>
        `).join('')}</div>` : `<p>${emptyText}</p>`}
      </article>
    `;
  }

  function renderOperationalAlerts() {
    const buckets = operationalAlertBuckets();
    const matches = alertableMatchList();
    const overdueCount = matches.filter(match => match.scheduled_on && match.scheduled_on < serverToday).length;
    const unscheduledCount = matches.filter(match => !match.scheduled_on).length;
    const critical = overdueCount + unscheduledCount + buckets.invalidDays.length;
    alertBadges.forEach((badge) => {
      const type = badge.dataset.alertBadge || 'total';
      const value = type === 'total' ? critical : (type === 'calendar' ? overdueCount + buckets.invalidDays.length : overdueCount + unscheduledCount);
      badge.textContent = String(value);
      badge.hidden = value <= 0;
    });
    alertCenters.forEach((center) => {
      const mode = center.dataset.alertCenter || 'dashboard';
      if (!activeAlertCompetitionIds().size) {
        center.innerHTML = '<div class="calendar-empty"><b>Sin alertas</b><span>No hay eventos activos en base de datos para generar notificaciones.</span></div>';
        return;
      }
      const cards = [
        ...(buckets.inProgress.length ? [alertCard('Duelos en curso', buckets.inProgress, 'ok is-in-progress', '')] : []),
        alertCard('Vencidos pendientes', buckets.overdue, 'danger', 'Sin duelos vencidos.'),
        alertCard('Duelos de hoy', buckets.today, 'ok', 'No hay duelos para hoy.'),
        ...(buckets.upcoming.length ? [alertCard('Próximos duelos · Mañana', buckets.upcoming, 'warn', '')] : []),
        alertCard('Sin fecha', buckets.unscheduled, 'muted', 'Todos los duelos visibles tienen fecha.'),
      ];
      if (mode !== 'results') cards.push(alertCard('Dias no permitidos', buckets.invalidDays, 'blocked', 'No hay duelos en dias bloqueados.'));
      center.innerHTML = `<div class="alert-center-head"><div><h2>Centro de alertas</h2><p>En curso primero; después vencidos, hoy, mañana y sin fecha.</p></div><span class="status-chip danger">${critical} criticas</span></div><div class="alert-bucket-grid">${cards.join('')}</div>`;
      Array.from(center.querySelectorAll('[data-alert-open-match]')).forEach((button) => {
        button.addEventListener('click', () => openMatchSheet(button.dataset.alertOpenMatch));
      });
    });
  }

  if (alertModalOpen && alertModal) {
    alertModalOpen.addEventListener('click', () => {
      renderOperationalAlerts();
      alertModal.show();
    });
  }

  function startOfWeek(date) {
    const start = new Date(date);
    const offset = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - offset);
    return start;
  }

  function eventsForDay(events, dayIso) {
    return events.filter((event) => event.date === dayIso);
  }

  function calendarDisplayCount(events) {
    return events.reduce((total, event) => total + Math.max(1, (event.matches || []).length), 0);
  }

  function isCalendarMatchComplete(match) {
    if (!match) return false;
    const status = String(match.estado || match.status || '').toUpperCase();
    return ['FINALIZADA', 'FINALIZADO', 'COMPLETED'].includes(status)
      || evaluateDuel(getSavedResult(match), match).state === 'FINALIZADA';
  }

  function calendarCompletion(matches = []) {
    const rows = Array.isArray(matches) ? matches.filter(Boolean) : [];
    if (!rows.length) return 'empty';
    return rows.every(isCalendarMatchComplete) ? 'complete' : 'incomplete';
  }

  function calendarDateCompletion(events = []) {
    const matches = events.flatMap((event) => event.matches || []);
    if (matches.length) return calendarCompletion(matches);
    if (!events.length) return 'empty';
    return events.every((event) => event.completion === 'complete') ? 'complete' : 'incomplete';
  }

  function shortCalendarTitle(event, maxLength = 34) {
    const title = String(event.title || event.typeLabel || 'Evento');
    const clean = title
      .replace(/^mesa\s+/i, 'M')
      .replace(/\s+-\s+jornada\s+\d+$/i, '')
      .replace(/\s+/g, ' ')
      .trim();
    return clean.length > maxLength ? `${clean.slice(0, maxLength - 1).trim()}...` : clean;
  }

  function eventChip(event, compact = false) {
    const label = compact ? shortCalendarTitle(event, window.innerWidth <= 720 ? 18 : 30) : String(event.title || 'Evento');
    const draggable = (event.matches || [])[0]?.id ? ' draggable="true"' : '';
    const count = (event.matches || []).length;
    return `<span class="calendar-event is-${escapeHtml(event.completion || 'incomplete')}" style="--accent:${escapeHtml(event.accent || '#2f80ed')}" data-calendar-event-id="${escapeHtml(event.id)}" data-calendar-format="${escapeHtml(event.format || 'other')}"${draggable} title="${escapeHtml(event.title || '')}">
      <i>${escapeHtml(event.typeLabel || event.type || 'Evento')}</i>
      <strong>${escapeHtml(label)}</strong>
      ${count > 1 ? `<em>${count}</em>` : ''}
    </span>`;
  }

  function eventDragAttrs(event) {
    if(event.ffa)return ` data-calendar-event-id="${event.id}"`;
    return (event.matches || [])[0]?.id ? ` data-calendar-event-id="${event.id}" draggable="true"` : ` data-calendar-event-id="${event.id}"`;
  }

  function setCalendarModeClass() {
    if (!calendarGrid) return;
    calendarGrid.classList.remove('is-month', 'is-week', 'is-day', 'is-year', 'is-agenda');
    calendarGrid.classList.add(`is-${calendarView}`);
    if (calendarWeekdays) {
      calendarWeekdays.hidden = !['month', 'week'].includes(calendarView);
    }
    calendarViewButtons.forEach((button) => {
      button.classList.toggle('active', button.dataset.calendarView === calendarView);
    });
  }

  const eventTypeStyles = {
    qualifier: { accent: '#00ff9c', label: 'Clasificatorio' },
    league: { accent: '#00d9ff', label: 'Liga' },
    fixture: { accent: '#3d6dff', label: 'Ronda' },
    cup: { accent: '#ff2bd6', label: 'Copa' },
    world: { accent: '#9b5cff', label: 'Mundial' },
    final: { accent: '#ff315a', label: 'Final' },
    friendly: { accent: '#20f7d2', label: 'Amistoso' },
    special: { accent: '#ff8a00', label: 'Evento especial' },
    tournament: { accent: '#ffd400', label: 'Torneo' },
    torneo: { accent: '#ffd400', label: 'Torneo' }
  };

  function styleForEvent(type, fallbackAccent) {
    const style = eventTypeStyles[type] || eventTypeStyles.torneo;
    return {
      accent: style.accent || fallbackAccent || '#36d8ff',
      label: style.label || type || 'Evento'
    };
  }

  function calendarFormatStyle(formatCode, fallbackAccent = '#8a8f9c', eventType = '') {
    const format = normalize(String(formatCode || '')).replaceAll(' ', '_');
    const cupAccent = ['cup', 'copa', 'final'].includes(normalize(String(eventType || ''))) ? '#ffd45f' : '';
    if (format.includes('rush')) return { key: 'rush', accent: '#ffd45f' };
    if (format.includes('goat')) return { key: 'goat', accent: cupAccent || '#ff8a00' };
    if (format.includes('tcg')) return { key: 'tcg', accent: cupAccent || '#2f80ed' };
    return { key: 'other', accent: cupAccent || fallbackAccent || '#8a8f9c' };
  }

  document.querySelectorAll('[data-event-sort-table] [data-event-card]').forEach(row => {
    const typeStyle = styleForEvent(row.dataset.eventType);
    const formatStyle = calendarFormatStyle(row.dataset.eventRuleset || row.dataset.eventDivision, typeStyle.accent, row.dataset.eventType);
    row.style.setProperty('--event-accent', formatStyle.accent);
    row.style.setProperty('--accent', formatStyle.accent);
    row.style.borderLeftColor = formatStyle.accent;
    row.dataset.calendarFormat = formatStyle.key;
  });

  function eventDateTime(date) {
    const pad = (value) => String(value).padStart(2, '0');
    return {
      date: `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
      time: `${pad(date.getHours())}:${pad(date.getMinutes())}`
    };
  }

  function competitionFor(type, division) {
    const competitions = data.competitions || [];
    return competitions.find((competition) =>
      competition.type === type && (!division || competition.division_code === division)
    ) || competitions.find((competition) => competition.type === type) || null;
  }

  function activeCalendarCompetitions() {
    const competitions = (data.competitions || []).filter((competition) => normalize(competition.active ?? '1') !== '0');
    const visibleStatuses = ['active', 'activo', 'en curso', 'en_curso', 'pending event', 'pending_event', 'pendiente evento', 'pendiente_evento', 'pending configuration', 'pending_configuration', 'pendiente configuracion', 'pendiente_configuracion', 'completed', 'finalizado', 'finalizada'];
    const active = competitions.filter((competition) => visibleStatuses.includes(normalize(competition.status)));
    return active;
  }

  function activeCompetitionFor(type, division) {
    const competitions = activeCalendarCompetitions();
    return competitions.find((competition) =>
      competition.type === type && (!division || competition.division_code === division)
    ) || competitions.find((competition) => competition.type === type) || null;
  }

  function calendarEvents() {
    const activeCompetitions = activeCalendarCompetitions();
    const activeCompetitionIds = new Set(activeCompetitions.map((competition) => String(competition.id || '')));
    const scheduledMatchesByDate = allMatchList()
      .filter((match) => match.scheduled_on)
      .reduce((map, match) => {
        map[match.scheduled_on] = map[match.scheduled_on] || [];
        map[match.scheduled_on].push(match);
        return map;
      }, {});
    const scheduledMatchesByDateAndCompetition = allMatchList()
      .filter((match) => match.scheduled_on && match.competition_id)
      .reduce((map, match) => {
        const key = `${match.scheduled_on}|${match.competition_id}`;
        map[key] = map[key] || [];
        map[key].push(match);
        return map;
      }, {});
    const storedEventRows = (data.events || []).filter((event) => {
      const eventCompetition = String(event.competition_id || '');
      const eventCompetitionStatus = normalize(event.competition_status || '');
      return !eventCompetition || activeCompetitionIds.has(eventCompetition) || ['active', 'activo', 'en curso', 'en_curso'].includes(eventCompetitionStatus);
    });
    const storedEventByKey = new Map();
    storedEventRows.forEach((event) => {
      const eventCompetition = String(event.competition_id || '');
      const key = eventCompetition ? `${event.event_date}|${eventCompetition}|${event.event_type || event.competition_type || ''}` : `raw-${event.id}`;
      const current = storedEventByKey.get(key);
      const score = (String(event.title || '').toLowerCase().includes('jornada') ? 10 : 0)
        + (String(event.notes || '').toLowerCase().includes('combates') ? 5 : 0)
        + (event.starts_at ? 1 : 0);
      const currentScore = current ? ((String(current.title || '').toLowerCase().includes('jornada') ? 10 : 0)
        + (String(current.notes || '').toLowerCase().includes('combates') ? 5 : 0)
        + (current.starts_at ? 1 : 0)) : -1;
      if (!current || score >= currentScore) storedEventByKey.set(key, event);
    });
    const storedEventCoverage = new Set();
    const storedEvents = Array.from(storedEventByKey.values()).map((event) => {
      const eventCompetition = String(event.competition_id || '');
      const competition = activeCompetitions.find((item) => String(item.id || '') === eventCompetition);
      if (eventCompetition) storedEventCoverage.add(`${event.event_date}|${eventCompetition}`);
      const eventMatches = eventCompetition ? (scheduledMatchesByDateAndCompetition[`${event.event_date}|${eventCompetition}`] || []) : (scheduledMatchesByDate[event.event_date] || []);
      return {
        id: `event-${event.id}`,
        competitionId: eventCompetition,
        date: event.event_date,
        type: event.event_type || event.competition_type || 'torneo',
        ruleset: competition?.event_ruleset || eventMatches[0]?.competition_ruleset || '',
        division: event.division_code || 'GENERAL',
        status: event.status || 'scheduled',
        competitionStatus: competition?.status || event.competition_status || '',
        title: event.title,
        meta: event.notes || event.competition_name || 'Evento programado',
        time: event.starts_at || '',
        venue: event.venue || '',
        accent: styleForEvent(event.event_type || event.competition_type || 'torneo').accent,
        matches: eventMatches
      };
    });
    const fixtureEvents = Object.keys(data.fixtures || {}).flatMap((division) => {
      const divisionRounds = fixtureRoundsFor(division);
      const firstPhase = divisionRounds[0]?.matches?.[0]?.phase;
      const type = firstPhase || (division === 'CLASIFICATORIO' ? 'qualifier' : 'league');
      const matchEvents = divisionRounds.flatMap((round) => (round.matches || [])
        .filter((match) => match.scheduled_on && (!match.competition_id || activeCompetitionIds.has(String(match.competition_id))))
        .filter((match) => !match.competition_id || !storedEventCoverage.has(`${match.scheduled_on}|${match.competition_id}`))
        .map((match) => {
          const competition = activeCompetitions.find((item) => String(item.id || '') === String(match.competition_id || ''));
          return {
            id: `match-${match.id}`,
            date: match.scheduled_on,
            type: competition?.type || match.phase || type,
            ruleset: competition?.event_ruleset || match.competition_ruleset || '',
            division,
            round: round.ronda,
            status: match.estado || 'PENDIENTE',
            title: `Mesa ${match.mesa}: ${matchVersusLabel(match)}`,
            meta: `Ronda ${round.ronda} | ${String(match.match_format || '1v1').toUpperCase()} | Mejor de ${match.best_of || 5}${match.starts_at ? ` | ${match.starts_at.slice(0, 5)}` : ''}${match.venue ? ` | ${match.venue}` : ''}`,
            time: match.starts_at || '',
            venue: match.venue || '',
            judge: match.judge || '',
            notes: match.notes || '',
            accent: competition?.accent || (data.divisions[division] ? data.divisions[division].accent : '#36d8ff'),
            matches: [match]
          };
        }));
      return matchEvents;
    });
    const ffaEvents=(data.ffaMatches||[]).filter(row=>activeCompetitionIds.has(String(row.competition_id))).map(row=>{
      const c=activeCompetitions.find(c=>String(c.id)===String(row.competition_id)),names=row.state.players.map(p=>(data.allDecks||[]).find(d=>Number(d.id)===p.deck_id)?.name||p.deck_id).join(' · ');
      return {id:'ffa-'+row.id,ffa:true,competitionId:String(row.competition_id),date:row.scheduled_on,type:c?.type||'special',ruleset:c?.event_ruleset||'',division:'GENERAL',status:row.status,competitionStatus:c?.status,title:(c?.name||'Battle Royale')+' · '+names,meta:'3-4 jugadores independientes',time:row.starts_at,accent:c?.accent||'#36d8ff',matches:[{id:'ffa-'+row.id,ffa_id:row.id,competition_id:row.competition_id,player_names:names,estado:row.status,scheduled_on:row.scheduled_on,starts_at:row.starts_at}]};
    });
    return [...storedEvents, ...fixtureEvents,...ffaEvents].map((event) => {
      const style = styleForEvent(event.type, event.accent);
      const matchCompletion = calendarCompletion(event.matches || []);
      const eventStatus = normalizeStatusKey(event.competitionStatus || event.status || '');
      const completion = matchCompletion === 'empty'
        ? (['completed', 'finalizado', 'finalizada'].includes(eventStatus) ? 'complete' : 'incomplete')
        : matchCompletion;
      const format = calendarFormatStyle(event.ruleset || event.matches?.[0]?.competition_ruleset || event.division, style.accent, event.type);
      return { ...event, typeLabel: style.label, typeAccent: style.accent, format: format.key, completion, accent: format.accent };
    });
  }

  function syncFriendlyMatchDecks() {
    if (!friendlyMatchModalNode || !friendlyDivisionSelect || !friendlyDeckSelects.length) return;
    const selectedFormat = String(friendlyDivisionSelect.value || '');
    const [filterType = '', filterValue = ''] = selectedFormat.includes(':') ? selectedFormat.split(':', 2) : ['', ''];
    if (friendlyDivisionValue) friendlyDivisionValue.value = filterType === 'division' ? filterValue : '';
    if (friendlyRulesetValue) friendlyRulesetValue.value = filterType === 'ruleset' ? filterValue : '';
    let availableCount = 0;
    friendlyDeckSelects.forEach((select) => {
      Array.from(select.options).forEach((option) => {
        const formatCodes = String(option.dataset.formatCodes || '').split(',').filter(Boolean);
        const allowed = !filterValue
          || (filterType === 'division' && String(option.dataset.divisionId || '') === filterValue)
          || (filterType === 'ruleset' && formatCodes.includes(filterValue));
        option.hidden = !allowed;
        option.disabled = !allowed;
      });
      availableCount = Array.from(select.options).filter((option) => !option.disabled && option.value).length;
      const current = select.selectedOptions[0];
      if (current && current.disabled) {
        const next = Array.from(select.options).find((option) => !option.disabled && option.value);
        if (next) select.value = next.value;
      }
    });
    const [deckA, deckB] = friendlyDeckSelects;
    if (deckA && deckB && deckA.value && deckA.value === deckB.value) {
      const replacement = Array.from(deckB.options).find((option) => !option.disabled && option.value && option.value !== deckA.value);
      if (replacement) deckB.value = replacement.value;
    }
    const summary = friendlyMatchModalNode.querySelector('[data-friendly-pool-summary]');
    if (summary) {
      summary.textContent = availableCount >= 2
        ? `${availableCount} decks disponibles para este formato.`
        : 'Este formato necesita al menos dos decks asociados para crear el amistoso.';
    }
    const submit = friendlyMatchModalNode.querySelector('button[type="submit"]');
    if (submit) submit.disabled = availableCount < 2 || !deckA?.value || !deckB?.value || deckA.value === deckB.value;
  }

  if (friendlyDivisionSelect) friendlyDivisionSelect.addEventListener('change', syncFriendlyMatchDecks);
  friendlyDeckSelects.forEach((select) => select.addEventListener('change', syncFriendlyMatchDecks));
  syncFriendlyMatchDecks();

  function filteredCalendarEvents() {
    const type = calendarType ? calendarType.value : '';
    const division = calendarDivision ? calendarDivision.value : '';
    const query = normalize(calendarSearch ? calendarSearch.value : '');
    return calendarEvents().map((event) => {
      if (!calendarProfileScope) return event;
      const scopedMatches = (event.matches || []).filter((match) => calendarMatchBelongsToScope(match, calendarProfileScope));
      return scopedMatches.length ? { ...event, matches: scopedMatches } : null;
    }).filter(Boolean).filter((event) => {
      const haystack = normalize(`${event.title} ${event.meta} ${event.type} ${event.division} ${event.status}`);
      const eventDivision = String(event.division || '');
      return (!type || event.type === type)
        && (!division || eventDivision === division || eventDivision.endsWith(`_${division}`))
        && (!query || haystack.includes(query));
    });
  }

  function calendarMatchBelongsToScope(match, scope) {
    if (!match || !scope) return false;
    const participantIds = [match.deck_a_id, match.deck_b_id, match.deck_c_id, match.deck_d_id, match.deck_e_id, match.deck_f_id]
      .map((id) => String(id || '')).filter(Boolean);
    if (scope.type === 'deck') {
      return participantIds.includes(String(scope.id || ''))
        || [...matchSideDecks(match, 'a'), ...matchSideDecks(match, 'b')].some((name) => normalize(name) === normalize(scope.label));
    }
    const teamDecks = (data.allDecks || []).filter((deck) => String(deck.team_id || '') === String(scope.id || ''));
    const teamDeckIds = new Set(teamDecks.map((deck) => String(deck.id || '')));
    const teamDeckNames = new Set(teamDecks.map((deck) => normalize(deck.name || '')));
    return participantIds.some((id) => teamDeckIds.has(id))
      || [...matchSideDecks(match, 'a'), ...matchSideDecks(match, 'b')].some((name) => teamDeckNames.has(normalize(name)))
      || [match.team_a, match.team_b].some((name) => normalize(name || '') === normalize(scope.label));
  }

  function syncCalendarProfileScope() {
    if (!calendarProfileScopeNode) return;
    calendarProfileScopeNode.hidden = !calendarProfileScope;
    if (!calendarProfileScope) return;
    if (calendarProfileKind) calendarProfileKind.textContent = calendarProfileScope.type === 'deck' ? 'Calendario exclusivo del deck' : 'Calendario exclusivo del equipo';
    if (calendarProfileLabel) calendarProfileLabel.textContent = calendarProfileScope.label || 'Seleccion actual';
  }

  function clearCalendarProfileScope(shouldRender = true) {
    calendarProfileScope = null;
    syncCalendarProfileScope();
    if (shouldRender) renderCalendar();
  }

  function openScopedCalendar(type, entity) {
    if (!entity || !entity.id) return;
    calendarProfileScope = { type, id: String(entity.id), label: String(entity.name || '') };
    syncCalendarProfileScope();
    const scopedEvents = filteredCalendarEvents().sort((left, right) => String(left.date || '').localeCompare(String(right.date || '')));
    const targetEvent = scopedEvents.find((event) => String(event.date || '') >= serverToday) || scopedEvents[scopedEvents.length - 1];
    selectedCalendarDate = targetEvent?.date || serverToday;
    const targetDate = new Date(`${selectedCalendarDate}T12:00:00`);
    calendarCursor = Number.isNaN(targetDate.getTime()) ? new Date() : new Date(targetDate.getFullYear(), targetDate.getMonth(), 1);
    routeTo('calendario');
  }

  if (calendarProfileClear) calendarProfileClear.addEventListener('click', () => clearCalendarProfileScope());
  if (profileCalendarButton) profileCalendarButton.addEventListener('click', () => {
    const deck = (data.allDecks || []).find((item) => String(item.id || '') === String(profileCalendarButton.dataset.deckId || ''));
    openScopedCalendar('deck', deck);
  });
  if (teamCalendarButton) teamCalendarButton.addEventListener('click', () => {
    const team = (data.teams || []).find((item) => String(item.id || '') === String(teamCalendarButton.dataset.teamId || ''));
    openScopedCalendar('team', team);
  });

