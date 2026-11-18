/**
 * calendar-views: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function renderCalendar() {
    if (!calendarGrid) return;
    syncCalendarProfileScope();
    const events = filteredCalendarEvents();
    const year = calendarCursor.getFullYear();
    const month = calendarCursor.getMonth();
    const first = new Date(year, month, 1);
    const selectedDate = new Date(`${selectedCalendarDate}T12:00:00`);
    setCalendarModeClass();
    if (calendarCount) calendarCount.textContent = `${calendarDisplayCount(events)} combates/eventos`;

    if (calendarView === 'month') {
      const startOffset = (first.getDay() + 6) % 7;
      const start = new Date(year, month, 1 - startOffset);
      if (calendarTitle) calendarTitle.textContent = first.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' });
      calendarGrid.innerHTML = Array.from({ length: 42 }, (_, index) => {
        const day = new Date(start);
        day.setDate(start.getDate() + index);
        const dayIso = isoDate(day);
        const dayEvents = eventsForDay(events, dayIso);
        const outside = day.getMonth() !== month ? ' outside' : '';
        const selected = dayIso === selectedCalendarDate ? ' selected' : '';
        const blocked = isPlayableDate(dayIso) ? '' : ' blocked-day';
        const completion = calendarDateCompletion(dayEvents);
        const completionClass = completion === 'empty' ? '' : ` date-${completion}`;
        const chips = dayEvents.slice(0, 3).map((event) => eventChip(event, true)).join('');
        const more = dayEvents.length > 3 ? `<small class="calendar-more">+${dayEvents.length - 3} mas</small>` : '';
        const dayCount = calendarDisplayCount(dayEvents);
        return `<button class="calendar-day${outside}${selected}${blocked}${completionClass}" type="button" data-calendar-day="${dayIso}" data-date-completion="${completion}"><b>${day.getDate()}</b><span class="calendar-day-total">${dayCount || ''}</span><span class="calendar-day-action">${blocked ? 'Dia bloqueado' : 'Ver dia'}</span><div class="calendar-day-events">${chips}${more}</div></button>`;
      }).join('');
    } else if (calendarView === 'week') {
      const start = startOfWeek(selectedDate);
      const end = new Date(start);
      end.setDate(start.getDate() + 6);
      if (calendarTitle) calendarTitle.textContent = `${formatDate(isoDate(start))} - ${formatDate(isoDate(end))}`;
      calendarGrid.innerHTML = Array.from({ length: 7 }, (_, index) => {
        const day = new Date(start);
        day.setDate(start.getDate() + index);
        const dayIso = isoDate(day);
        const dayEvents = eventsForDay(events, dayIso).sort((a, b) => String(a.time || '99:99').localeCompare(String(b.time || '99:99')));
        const selected = dayIso === selectedCalendarDate ? ' selected' : '';
        const blocked = isPlayableDate(dayIso) ? '' : ' blocked-day';
        const completion = calendarDateCompletion(dayEvents);
        const completionClass = completion === 'empty' ? '' : ` date-${completion}`;
        return `<button class="calendar-day week-day${selected}${blocked}${completionClass}" type="button" data-calendar-day="${dayIso}" data-date-completion="${completion}">
          <b>${day.toLocaleDateString('es-CO', { weekday: 'short' })} ${day.getDate()}</b><span class="calendar-day-action">Ver dia</span>
          <div class="week-slots">
            ${dayEvents.length ? dayEvents.slice(0, 10).map((event) => `
              <span class="week-slot" style="--accent:${escapeHtml(event.accent || '#36d8ff')}" data-calendar-event-id="${escapeHtml(event.id)}"${(event.matches || [])[0]?.id ? ' draggable="true"' : ''}>
                <em>${event.time ? event.time.slice(0, 5) : 'Todo el Dia'}</em>
                <strong>${escapeHtml(shortCalendarTitle(event, 46))}</strong>
              </span>
            `).join('') : '<i>Sin eventos</i>'}
          </div>
        </button>`;
      }).join('');
    } else if (calendarView === 'day') {
      if (calendarTitle) calendarTitle.textContent = formatDate(selectedCalendarDate);
      const dayEvents = eventsForDay(events, selectedCalendarDate).sort((a, b) => String(a.time || '99:99').localeCompare(String(b.time || '99:99')));
      const dayMatches = dayEvents.flatMap((event) => (event.matches || []).map((match) => ({ match, event })))
        .sort((left, right) => String(left.match.starts_at || left.event.time || '99:99').localeCompare(String(right.match.starts_at || right.event.time || '99:99')));
      calendarGrid.innerHTML = `<section class="calendar-day-agenda">
        <h3>${formatDate(selectedCalendarDate)}</h3>
        <p>${dayMatches.length} ${dayMatches.length === 1 ? 'combate programado' : 'combates programados'}</p>
        <div class="calendar-day-match-grid">${dayMatches.length ? dayMatches.map(({ match, event }) => calendarMatchCard(match, event)).join('') : '<div class="calendar-empty"><b>Sin combates</b><span>No hay combates programados para este dia.</span></div>'}</div>
      </section>`;
    } else if (calendarView === 'year') {
      if (calendarTitle) calendarTitle.textContent = String(year);
      calendarGrid.innerHTML = Array.from({ length: 12 }, (_, monthIndex) => {
        const monthDate = new Date(year, monthIndex, 1);
        const monthEvents = events.filter((event) => {
          const eventDate = new Date(`${event.date}T12:00:00`);
          return eventDate.getFullYear() === year && eventDate.getMonth() === monthIndex;
        });
        return `<button class="calendar-mini-month" type="button" data-calendar-month="${monthIndex}">
          <b>${monthDate.toLocaleDateString('es-CO', { month: 'long' })}</b>
          <span>${monthEvents.length} registros</span>
        </button>`;
      }).join('');
    } else {
      if (calendarTitle) calendarTitle.textContent = 'Agenda';
      const upcoming = [...events].sort((a, b) => `${a.date} ${a.time || ''}`.localeCompare(`${b.date} ${b.time || ''}`));
      calendarGrid.innerHTML = `<section class="calendar-agenda-list">
        ${upcoming.length ? upcoming.map((event) => `
          <button class="agenda-row" type="button" data-calendar-day="${event.date}"${eventDragAttrs(event)} style="--accent:${escapeHtml(event.accent || '#36d8ff')}">
            <span>${formatDate(event.date)}</span>
            <b>${escapeHtml(event.title || 'Evento')}</b>
            <small>${event.time ? event.time.slice(0, 5) : 'Todo el dia'} | ${escapeHtml(event.typeLabel || event.type || 'Evento')} | ${escapeHtml(calendarEventStatus(event))}</small>
          </button>`).join('') : '<div class="calendar-empty"><b>Sin eventos</b><span>No hay eventos con los filtros actuales.</span></div>'}
      </section>`;
    }

    Array.from(calendarGrid.querySelectorAll('[data-calendar-day]')).forEach((button) => {
      button.addEventListener('click', (clickEvent) => {
        if (clickEvent.target.closest('[data-calendar-event-id]')) return;
        selectedCalendarDate = button.dataset.calendarDay;
        const eventId = button.dataset.calendarEventId;
        if (eventId) {
          const event = events.find((item) => item.id === eventId);
          if (event) openCalendarEventModal(event);
        } else {
          openCalendarDayModal(selectedCalendarDate, eventsForDay(events, selectedCalendarDate));
        }
        renderCalendar();
      });
      button.addEventListener('dragover', (dragEvent) => {
        dragEvent.preventDefault();
        button.classList.add('drop-target');
      });
      button.addEventListener('dragleave', () => {
        button.classList.remove('drop-target');
      });
      button.addEventListener('drop', (dropEvent) => {
        dropEvent.preventDefault();
        button.classList.remove('drop-target');
        const eventId = dropEvent.dataTransfer.getData('text/dml-event');
        const event = events.find((item) => item.id === eventId);
        if (event) {
          selectedCalendarDate = button.dataset.calendarDay;
          submitMatchSchedule(event, selectedCalendarDate, event.time || '');
        }
      });
    });
    Array.from(calendarGrid.querySelectorAll('[data-calendar-event-id]')).forEach((button) => {
      button.addEventListener('dragstart', (dragEvent) => {
        const event = events.find((item) => item.id === button.dataset.calendarEventId);
        if (!event || !(event.matches || [])[0]?.id) {
          dragEvent.preventDefault();
          return;
        }
        dragEvent.dataTransfer.effectAllowed = 'move';
        dragEvent.dataTransfer.setData('text/dml-event', event.id);
      });
      button.addEventListener('click', (clickEvent) => {
        clickEvent.stopPropagation();
        const event = events.find((item) => item.id === button.dataset.calendarEventId);
        if (event) {
          selectedCalendarDate = event.date;
          openCalendarEventModal(event);
          renderCalendarDetail([event]);
        }
      });
    });
    Array.from(calendarGrid.querySelectorAll('[data-calendar-match-id]')).forEach((button) => {
      button.addEventListener('click', () => {
        const matchId = button.dataset.calendarMatchId;
        const event = events.find((item) => (item.matches || []).some((match) => String(match.id || '') === String(matchId || '')));
        if (event) openCalendarEventModal(event, matchId);
      });
    });
    Array.from(calendarGrid.querySelectorAll('[data-calendar-month]')).forEach((button) => {
      button.addEventListener('click', () => {
        calendarView = 'month';
        localStorage.setItem('dml_calendar_view', calendarView);
        calendarCursor = new Date(year, Number(button.dataset.calendarMonth || 0), 1);
        selectedCalendarDate = isoDate(calendarCursor);
        renderCalendar();
      });
    });
    renderCalendarDetail(events);
    renderOperationalAlerts();
    renderFullCalendar(events);
  }

