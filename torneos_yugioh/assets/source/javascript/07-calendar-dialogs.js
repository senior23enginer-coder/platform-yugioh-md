/**
 * calendar-dialogs: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function submitMatchSchedule(event, nextDate, nextTime) {
    const match = (event.matches || [])[0];
    if (!match || !match.id) return false;
    const form = document.createElement('form');
    form.method = 'post';
    form.style.display = 'none';
    const values = {
      action: 'save_match_schedule',
      schedule_match_id: match.id,
      scheduled_on: nextDate || event.date || '',
      starts_at: nextTime || event.time || '',
      venue: event.venue || match.venue || '',
      judge: event.judge || match.judge || '',
      match_notes: event.notes || match.notes || ''
    };
    Object.entries(values).forEach(([name, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value || '';
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    return true;
  }

  function calendarEventStatus(event) {
    const matches = event.matches || [];
    if (matches.length && matches.every(isCalendarMatchComplete)) return 'Finalizado';
    if (matches.some(match => matchTimingStatus(match).key === 'in_progress')) return 'En curso';
    const status = String(event.status || 'scheduled');
    return ({ scheduled: 'Programado', pending: 'Pendiente', active: 'En curso', completed: 'Finalizado', cancelled: 'Cancelado' })[status.toLowerCase()] || status;
  }

  function calendarEventIdentity(event, includeEvent = true) {
    const matches=event.matches||[];
    const id=event.competitionId||matches.find(m=>m.competition_id)?.competition_id;
    const competition=(data.competitions||[]).find(c=>String(c.id)===String(id));
    const league=competition?.type==='league'||event.type==='league';
    const name=competition?.name||matches[0]?.competition_name||event.title||'Amistoso';
    const code=competition?.event_ruleset||event.ruleset||matches[0]?.competition_ruleset||'';
    const format=(data.rulesets||[]).find(r=>r.code===code)?.label||({tcg_advanced:'TCG Avanzado',tcg_traditional:'TCG Tradicional',goat:'GOAT',rush_duel:'Rush Duel'})[code]||'Sin formato registrado';
    const divisions=[...new Set([...matches.map(m=>m.division),event.division].filter(Boolean))]
      .map(key=>({key,division:data.divisions?.[key]}))
      .filter(({division})=>division&&(!league||String(division.league_competition_id)===String(id)))
      .map(({key,division})=>{let label=division.name||division.short||key;if(label.startsWith(name))label=label.slice(name.length).replace(/^[\s\u2014\u2013-]+/u,'');return label;});
    return `<dl class="calendar-event-identity">${includeEvent?`<div><dt>${league?'Liga':'Evento'}</dt><dd>${escapeHtml(name)}</dd></div>`:''}<div><dt>Formato</dt><dd>${escapeHtml(format)}</dd></div><div><dt>Divisi\u00f3n</dt><dd>${escapeHtml(divisions.join(' / ')||(league?'Sin divisi\u00f3n registrada':'No aplica'))}</dd></div></dl>`;
  }

  function openCalendarDayModal(dayIso, dayEvents) {
    if (!calendarDayModalNode || !calendarDayModal) return;
    const events = [...(dayEvents || [])].sort((a, b) => String(a.time || '99:99').localeCompare(String(b.time || '99:99')));
    const title = calendarDayModalNode.querySelector('[data-calendar-day-modal-title]');
    const meta = calendarDayModalNode.querySelector('[data-calendar-day-modal-meta]');
    const body = calendarDayModalNode.querySelector('[data-calendar-day-modal-events]');
    if (title) title.textContent = formatDate(dayIso);
    const totalMatches = events.reduce((total, event) => total + (event.matches || []).length, 0);
    if (meta) meta.textContent = totalMatches ? `${totalMatches} combates programados` : (events.length ? `${events.length} eventos programados` : 'Sin registros programados');
    if (body) {
      body.innerHTML = events.length ? events.map((event) => `
        <article class="day-event-card" style="--accent:${event.accent || '#36d8ff'}">
          <div class="day-event-head">
            <span class="tag">${event.typeLabel || event.type || 'Evento'}</span>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
              <button class="btn btn-outline-light btn-sm" type="button" data-day-open-event="${event.id}"><i class="bi bi-box-arrow-up-right"></i> Ver evento</button>
              ${(event.matches || []).length === 1 && isManualFriendly(event.matches[0]) ? `<button class="btn btn-outline-danger btn-sm" type="button" data-delete-friendly="${event.matches[0].id}"><i class="bi bi-trash3"></i> Eliminar</button>` : ''}
            </div>
          </div>
          <h3>${event.title}</h3>
          <p>${event.time ? event.time.slice(0, 5) : 'Todo el dia'} | ${calendarEventStatus(event)}</p>
          ${calendarEventIdentity(event)}
          <div class="day-match-list">
            ${(event.matches || []).length ? (event.matches || []).map((match) => calendarMatchCard(match, event, 'data-day-open-match')).join('') : '<span class="note">Este evento aun no tiene combates asociados.</span>'}
          </div>
        </article>
      `).join('') : '<div class="calendar-empty"><b>Sin eventos</b><span>No hay combates o jornadas programadas para este dia.</span></div>';
      Array.from(body.querySelectorAll('[data-day-open-event]')).forEach((button) => {
        button.addEventListener('click', () => {
          const event = events.find((item) => item.id === button.dataset.dayOpenEvent);
          if (!event) return;
          const competitionId = event.competitionId || event.matches?.find((match) => match.competition_id)?.competition_id;
          calendarDayModal.hide();
          window.setTimeout(() => {
            if (competitionId && (data.competitions || []).some((item) => String(item.id) === String(competitionId))) {
              setEventFichaUrl(competitionId);
              if (fillCompetitionConfig(competitionId)) routeTo('evento_ficha');
            } else {
              openCalendarEventModal(event);
            }
          }, 180);
        });
      });
      Array.from(body.querySelectorAll('[data-day-open-match]')).forEach((button) => {
        button.addEventListener('click', () => {
          const matchId = button.dataset.dayOpenMatch;
          const event = events.find((item) => (item.matches || []).some((match) => String(match.id || '') === String(matchId || '')));
          if (!event) return;
          calendarDayModal.hide();
          window.setTimeout(() => openCalendarEventModal(event, matchId), 180);
        });
      });
      Array.from(body.querySelectorAll('[data-delete-friendly]')).forEach((button) => {
        button.addEventListener('click', () => {
          const match = allMatchList().find((item) => String(item.id || '') === String(button.dataset.deleteFriendly || ''));
          if (!match) return;
          calendarDayModal.hide();
          window.setTimeout(() => openDeleteFriendlyModal(match), 180);
        });
      });
    }
    calendarDayModal.show();
  }

  function openCalendarEventModal(event, selectedMatchId = '') {
    if(event.ffa){location.href='?event_id='+encodeURIComponent(event.competitionId)+'#evento_ficha';return;}
    if (!calendarModalNode || !calendarModal) return;
    const eventMatches = event.matches || [];
    const isFinalizedMatch = (match) => ['FINALIZADA', 'FINALIZADO', 'COMPLETED'].includes(String(match?.estado || match?.status || '').toUpperCase());
    let match = eventMatches.find((item) => String(item.id || '') === String(selectedMatchId || ''))
      || eventMatches.find((item) => !isFinalizedMatch(item))
      || eventMatches[0]
      || null;
    const setText = (selector, value) => {
      const node = calendarModalNode.querySelector(selector);
      if (node) node.textContent = value || '';
    };
    const setField = (selector, value) => {
      const node = calendarModalNode.querySelector(selector);
      if (node) node.value = value || '';
    };
    setText('[data-calendar-modal-type]', event.typeLabel || event.type || 'Evento');
    setText('[data-calendar-modal-title]', event.title || 'Detalle del calendario');
    setText('[data-calendar-modal-meta]', `${formatDate(event.date)} | ${event.time ? event.time.slice(0, 5) : 'Todo el dia'} | ${event.division || 'GENERAL'} | ${calendarEventStatus(event)}`);
    const matchesNode = calendarModalNode.querySelector('[data-calendar-modal-matches]');
    if (matchesNode) {
      matchesNode.innerHTML = eventMatches.length ? eventMatches.map((eventMatch) => {
        const result = evaluateDuel(getSavedResult(eventMatch), eventMatch);
        const finalized = isFinalizedMatch(eventMatch);
        const selected = match && String(match.id || '') === String(eventMatch.id || '');
        return `
          <article class="modal-match-card${selected ? ' is-selected' : ''}" style="--accent:${event.accent}" data-calendar-schedule-card="${eventMatch.id}">
            <b>Mesa ${eventMatch.mesa || '-'} | Ronda ${eventMatch.ronda || event.round || '-'}</b>
            <span>${eventMatch.a || 'Deck A'} vs ${eventMatch.b || 'Deck B'}</span>
            <small>${eventMatch.estado || event.status || 'PENDIENTE'}${eventMatch.score || result?.score ? ` | ${eventMatch.score || result.score}` : ''}</small>
            <div class="modal-match-stats">
              <span><b>Fecha</b>${eventMatch.scheduled_on || event.date || 'Sin fecha'}</span>
              <span><b>Hora</b>${eventMatch.starts_at ? eventMatch.starts_at.slice(0, 5) : (event.time ? event.time.slice(0, 5) : 'Sin hora')}</span>
              <span><b>Sede</b>${eventMatch.venue || event.venue || 'Station X Tulua'}</span>
              <span><b>Juez</b>${eventMatch.judge || event.judge || 'Por asignar'}</span>
              <span><b>Duelos</b>${(getSavedResult(eventMatch) || []).join(' / ') || 'Sin captura'}</span>
              <span><b>Resultado</b>${result?.winner ? (result.winner === 'E' ? 'Empate' : `Gana ${result.winner}`) : 'Pendiente'}</span>
            </div>
            ${event.notes || eventMatch.notes ? `<p>${event.notes || eventMatch.notes}</p>` : ''}
            <div class="modal-actions">
              <button class="btn btn-outline-light btn-sm" type="button" data-open-result-match="${eventMatch.id}"><i class="bi bi-clipboard2-check"></i> Resultado</button>
              ${finalized
                ? '<span class="calendar-schedule-locked"><i class="bi bi-lock-fill"></i> Fecha historica</span>'
                : `<button class="btn btn-outline-info btn-sm${selected ? ' active' : ''}" type="button" data-edit-calendar-match="${eventMatch.id}"><i class="bi bi-calendar2-week"></i> Modificar</button>`}
              ${isManualFriendly(eventMatch) ? `<button class="btn btn-outline-danger btn-sm" type="button" data-delete-friendly="${eventMatch.id}"><i class="bi bi-trash3"></i> Eliminar</button>` : ''}
            </div>
          </article>
        `;
      }).join('') : '<p class="note">Este evento todavia no tiene combates asociados.</p>';
    }
    const form = calendarModalNode.querySelector('[data-calendar-modal-form]');
    const selectScheduleMatch = (selectedMatch) => {
      match = selectedMatch || null;
      const locked = isFinalizedMatch(match);
      if (form) form.hidden = !match || locked;
      setText('[data-calendar-modal-form-title]', match ? `R${match.ronda || event.round || '-'} | M${match.mesa || '-'}` : 'Selecciona un duelo');
      setText('[data-calendar-modal-form-meta]', match
        ? `${match.a || 'Deck A'} vs ${match.b || 'Deck B'}`
        : 'Elige el combate que deseas cambiar.');
      setField('[data-calendar-modal-match-id]', match ? match.id : '');
      setField('[data-calendar-modal-date]', match?.scheduled_on || event.date || '');
      setField('[data-calendar-modal-time]', match?.starts_at ? match.starts_at.slice(0, 5) : (event.time ? event.time.slice(0, 5) : ''));
      setField('[data-calendar-modal-venue]', match?.venue || event.venue || '');
      setField('[data-calendar-modal-judge]', match?.judge || event.judge || '');
      setField('[data-calendar-modal-notes]', match?.notes || event.notes || '');
      calendarModalNode.querySelectorAll('[data-calendar-schedule-card]').forEach((card) => {
        card.classList.toggle('is-selected', Boolean(match) && String(card.dataset.calendarScheduleCard || '') === String(match.id || ''));
      });
      calendarModalNode.querySelectorAll('[data-edit-calendar-match]').forEach((button) => {
        button.classList.toggle('active', Boolean(match) && String(button.dataset.editCalendarMatch || '') === String(match.id || ''));
      });
    };
    selectScheduleMatch(match);
    calendarModalNode.querySelectorAll('[data-open-result-match]').forEach((button) => {
      button.addEventListener('click', () => {
        const matchId = button.dataset.openResultMatch;
        calendarModal.hide();
        window.setTimeout(() => openResultCapture(matchId), 200);
      });
    });
    calendarModalNode.querySelectorAll('[data-edit-calendar-match]').forEach((button) => {
      button.addEventListener('click', () => {
        const selected = eventMatches.find((item) => String(item.id || '') === String(button.dataset.editCalendarMatch || ''));
        if (selected) {
          selectScheduleMatch(selected);
          window.dispatchEvent(new CustomEvent('dml:modify-match', {detail:{matchId:selected.id,mode:'schedule',parentId:'calendarEventModal'}}));
        }
      });
    });
    calendarModalNode.querySelectorAll('[data-delete-friendly]').forEach((button) => {
      button.addEventListener('click', () => {
        const eventMatch = (event.matches || []).find((item) => String(item.id || '') === String(button.dataset.deleteFriendly || ''));
        if (!eventMatch) return;
        calendarModal.hide();
        window.setTimeout(() => openDeleteFriendlyModal(eventMatch), 180);
      });
    });
    calendarModal.show();
  }

  function fullCalendarViewName() {
    if (calendarView === 'week') return 'timeGridWeek';
    if (calendarView === 'day') return 'timeGridDay';
    if (calendarView === 'year') return 'multiMonthYear';
    if (calendarView === 'agenda') return 'listWeek';
    return 'dayGridMonth';
  }

  function renderFullCalendar(events) {
    if (fullCalendarNode) fullCalendarNode.hidden = true;
    fallbackNodes.forEach((node) => { node.hidden = false; });
    return;
    if (!fullCalendarNode || !window.FullCalendar) return;
    const fcEvents = events.map((event) => ({
      id: event.id,
      title: event.title,
      start: event.time ? `${event.date}T${event.time}` : event.date,
      allDay: !event.time,
      backgroundColor: event.accent || '#36d8ff',
      borderColor: event.accent || '#36d8ff',
      textColor: '#07101f',
      classNames: [`fc-dml-${event.type || 'event'}`, `fc-dml-status-${normalize(event.status).replace(/[^a-z0-9]+/g, '-') || 'none'}`],
      extendedProps: event
    }));
    const showFallback = () => {
      fullCalendarNode.hidden = true;
      fallbackNodes.forEach((node) => { node.hidden = false; });
    };
    const confirmRenderedCalendar = () => {
      const renderedDay = fullCalendarNode.querySelector('.fc-daygrid-day-number, .fc-timegrid-slot, .fc-list-table, .fc-multimonth-month');
      const viewHarness = fullCalendarNode.querySelector('.fc-view-harness');
      const shellHeight = Math.max(fullCalendarNode.getBoundingClientRect().height, fullCalendarNode.scrollHeight);
      const viewHeight = viewHarness ? viewHarness.getBoundingClientRect().height : 0;
      const usable = Boolean(renderedDay && shellHeight >= 0 && viewHeight >= 0);
      fullCalendarNode.hidden = !usable;
      fallbackNodes.forEach((node) => { node.hidden = usable; });
      return usable;
    };
    try {
      fallbackNodes.forEach((node) => { node.hidden = false; });
      fullCalendarNode.hidden = false;
      if (!fullCalendar) {
        fullCalendar = new window.FullCalendar.Calendar(fullCalendarNode, {
          initialView: fullCalendarViewName(),
          initialDate: selectedCalendarDate,
          locale: 'es',
          height: 'auto',
          contentHeight: 'auto',
          expandRows: true,
          handleWindowResize: true,
          firstDay: 1,
          nowIndicator: true,
          navLinks: true,
          editable: true,
          eventStartEditable: true,
          eventDurationEditable: false,
          dayHeaders: true,
          weekNumbers: false,
          allDayText: 'Todo el dia',
          slotMinTime: '08:00:00',
          slotMaxTime: '23:00:00',
          slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
          eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
          views: {
            multiMonthYear: {
              type: 'multiMonth',
              duration: { months: 12 },
              buttonText: 'Ano'
            }
          },
          headerToolbar: false,
          buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Dia', year: 'Ano', list: 'Agenda' },
          eventDisplay: 'block',
          dayMaxEvents: 3,
          moreLinkText(count) {
            return `+${count} mas`;
          },
          events: fcEvents,
          eventDidMount(info) {
            const event = info.event.extendedProps;
            info.el.title = `${event.typeLabel || event.type}: ${event.title}${event.meta ? ` | ${event.meta}` : ''}`;
          },
          dateClick(info) {
            selectedCalendarDate = info.dateStr;
            openCalendarDayModal(selectedCalendarDate, eventsForDay(filteredCalendarEvents(), selectedCalendarDate));
            renderCalendarDetail(filteredCalendarEvents());
          },
          eventClick(info) {
            selectedCalendarDate = info.event.startStr.slice(0, 10);
            const event = info.event.extendedProps;
            renderCalendarDetail([event]);
            openCalendarEventModal(event);
          },
          eventDrop(info) {
            const event = info.event.extendedProps;
            const match = (event.matches || [])[0];
            if (!match || !match.id) {
              info.revert();
              openCalendarEventModal(event);
              return;
            }
            const next = eventDateTime(info.event.start);
            submitMatchSchedule(event, next.date, info.event.allDay ? '' : next.time);
          }
        });
        fullCalendar.render();
      } else {
        fullCalendar.gotoDate(selectedCalendarDate || calendarCursor);
        fullCalendar.changeView(fullCalendarViewName());
        fullCalendar.removeAllEvents();
        fullCalendar.addEventSource(fcEvents);
        fullCalendar.updateSize();
      }
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
          if (fullCalendar) fullCalendar.updateSize();
          confirmRenderedCalendar();
        });
      });
    } catch (error) {
      fullCalendar = null;
      showFallback();
      console.warn('FullCalendar fallback activo:', error);
    }
  }

  function renderCalendarDetail(events) {
    if (!calendarDetail) return;
    const dayEvents = events.filter((event) => event.date === selectedCalendarDate);
    const title = formatDate(selectedCalendarDate);
    if (!dayEvents.length) {
      calendarDetail.innerHTML = `<div class="calendar-empty"><b>${title}</b><span>No hay eventos con los filtros actuales.</span></div>`;
      return;
    }
    calendarDetail.innerHTML = `<h3>${title}</h3>${dayEvents.map((event) => `
      <article class="calendar-detail-card" role="button" tabindex="0" data-calendar-event-id="${escapeHtml(event.id)}" style="--accent:${escapeHtml(event.accent || '#36d8ff')}">
        <div class="event-date-badge"><b>${String(new Date(`${event.date}T12:00:00`).getDate()).padStart(2, '0')}</b><small>${new Date(`${event.date}T12:00:00`).toLocaleDateString('es-CO', { month: 'short' }).toUpperCase()}</small></div>
        <div class="calendar-detail-main"><span class="tag">${escapeHtml(event.typeLabel || event.type || 'Evento')}</span><h4>${escapeHtml(event.title || 'Evento')}</h4><p>${escapeHtml(event.meta || '')}</p><small>${event.time ? event.time.slice(0, 5) : 'Todo el dia'} | ${escapeHtml(event.division || 'GENERAL')} | ${escapeHtml(calendarEventStatus(event))}</small></div>
        ${event.matches && event.matches.length ? `<div class="calendar-detail-matches">${event.matches.slice(0, 5).map((match) => calendarMatchCard(match, event, 'data-calendar-detail-match')).join('')}</div>` : ''}
      </article>
    `).join('')}`;
    Array.from(calendarDetail.querySelectorAll('[data-calendar-event-id]')).forEach((button) => {
      button.addEventListener('click', () => {
        const event = dayEvents.find((item) => item.id === button.dataset.calendarEventId);
        if (event) openCalendarEventModal(event);
      });
      button.addEventListener('keydown', (keyEvent) => {
        if (!['Enter', ' '].includes(keyEvent.key)) return;
        keyEvent.preventDefault();
        button.click();
      });
    });
    Array.from(calendarDetail.querySelectorAll('[data-calendar-detail-match]')).forEach((button) => {
      button.addEventListener('click', (event) => {
        event.stopPropagation();
        const matchId = button.dataset.calendarDetailMatch;
        const calendarEvent = dayEvents.find((item) => (item.matches || []).some((match) => String(match.id || '') === String(matchId || '')));
        if (calendarEvent) openCalendarEventModal(calendarEvent, matchId);
      });
    });
  }

  [calendarType, calendarDivision, calendarStatus, calendarSearch].forEach((control) => {
    if (control) control.addEventListener('input', renderCalendar);
    if (control) control.addEventListener('change', renderCalendar);
  });
  calendarViewButtons.forEach((button) => {
    button.addEventListener('click', () => {
      calendarView = button.dataset.calendarView || 'month';
      localStorage.setItem('dml_calendar_view', calendarView);
      renderCalendar();
    });
  });
  if (calendarPrev) calendarPrev.addEventListener('click', () => {
    if (calendarView === 'day') {
      selectedCalendarDate = addDays(selectedCalendarDate, -1);
      calendarCursor = new Date(`${selectedCalendarDate}T12:00:00`);
    } else if (calendarView === 'week') {
      selectedCalendarDate = addDays(selectedCalendarDate, -7);
      calendarCursor = new Date(`${selectedCalendarDate}T12:00:00`);
    } else if (calendarView === 'year') {
      calendarCursor.setFullYear(calendarCursor.getFullYear() - 1);
      selectedCalendarDate = isoDate(calendarCursor);
    } else {
      calendarCursor.setMonth(calendarCursor.getMonth() - 1);
      selectedCalendarDate = isoDate(calendarCursor);
    }
    renderCalendar();
  });
  if (calendarNext) calendarNext.addEventListener('click', () => {
    if (calendarView === 'day') {
      selectedCalendarDate = addDays(selectedCalendarDate, 1);
      calendarCursor = new Date(`${selectedCalendarDate}T12:00:00`);
    } else if (calendarView === 'week') {
      selectedCalendarDate = addDays(selectedCalendarDate, 7);
      calendarCursor = new Date(`${selectedCalendarDate}T12:00:00`);
    } else if (calendarView === 'year') {
      calendarCursor.setFullYear(calendarCursor.getFullYear() + 1);
      selectedCalendarDate = isoDate(calendarCursor);
    } else {
      calendarCursor.setMonth(calendarCursor.getMonth() + 1);
      selectedCalendarDate = isoDate(calendarCursor);
    }
    renderCalendar();
  });
  if (calendarToday) calendarToday.addEventListener('click', () => {
    const today = new Date();
    calendarCursor = new Date(today.getFullYear(), today.getMonth(), 1);
    selectedCalendarDate = isoDate(today);
    renderCalendar();
  });
  window.addEventListener('dml:routechange', (event) => {
    if (event.detail && event.detail.target === 'calendario') {
      window.setTimeout(() => {
        renderCalendar();
        if (fullCalendar) {
          fullCalendar.render();
          fullCalendar.updateSize();
        }
      }, 80);
    }
  });

  renderCalendar();

  const resultDivision = document.querySelector('[data-result-division]');
  const resultMatch = document.querySelector('[data-result-match]');
  const resultA = document.querySelector('[data-result-a]');
  const resultB = document.querySelector('[data-result-b]');
  const duelSelects = Array.from(document.querySelectorAll('[data-duel]'));
  const resultSummary = document.querySelector('[data-result-summary]');
  const resultMatchId = document.querySelector('[data-result-match-id]');
  const resultPhase = document.querySelector('[data-result-phase]');
  const resultEventFilter = document.querySelector('[data-result-event-filter]');
  const resultLeagueDivisionFilter = document.querySelector('[data-result-league-division]');
  const resultStatus = document.querySelector('[data-result-status]');
  const resultRoundFilter = document.querySelector('[data-result-round-filter]');
  const resultScheduleFilter = document.querySelector('[data-result-schedule-filter]');
  const resultSort = document.querySelector('[data-result-sort]');
  const resultDate = document.querySelector('[data-result-date]');
  const resultPeriod = document.querySelector('[data-result-period]');
  const resultMonth = document.querySelector('[data-result-month]');
  const resultDateFrom = document.querySelector('[data-result-date-from]');
  const resultDateTo = document.querySelector('[data-result-date-to]');
