/** Calendar event and alert projection; dependencies supplied by the browser adapter. */
// @runtime-start
(function (root) {
  "use strict";

  function operationalAlertBuckets(alertableMatchList, addDays, serverToday, normalizeStatusKey, isPlayableDate) {
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

  function activeCalendarCompetitions(data, normalize) {
    const competitions = (data.competitions || []).filter((competition) => normalize(competition.active ?? '1') !== '0');
    const visibleStatuses = ['active', 'activo', 'en curso', 'en_curso', 'pending event', 'pending_event', 'pendiente evento', 'pendiente_evento', 'pending configuration', 'pending_configuration', 'pendiente configuracion', 'pendiente_configuracion', 'completed', 'finalizado', 'finalizada'];
    const active = competitions.filter((competition) => visibleStatuses.includes(normalize(competition.status)));
    return active;
  }

  function calendarEvents(data, {activeCalendarCompetitions, allMatchList, normalize, styleForEvent, fixtureRoundsFor, matchVersusLabel, calendarCompletion, normalizeStatusKey, calendarFormatStyle}) {
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

  function filteredCalendarEvents({calendarEvents, type, division, search, normalize, calendarProfileScope, calendarMatchBelongsToScope}) {
    const query = normalize(search);
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

  function calendarMatchBelongsToScope(match, scope, data, matchSideDecks, normalize) {
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

  const api = Object.freeze({
    operationalAlertBuckets,
    activeCalendarCompetitions,
    calendarEvents,
    filteredCalendarEvents,
    calendarMatchBelongsToScope
  });
  root.StationXCalendarProjection = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
