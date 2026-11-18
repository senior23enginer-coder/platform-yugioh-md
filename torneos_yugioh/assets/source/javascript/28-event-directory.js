/**
 * event-directory: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function applyEventFilters() {
    const cards = Array.from(document.querySelectorAll('[data-event-card]'));
    cards.forEach((card) => {
      const queryOk = !eventFilterState.query || (card.dataset.eventSearch || '').includes(eventFilterState.query);
      const typeOk = !eventFilterState.type || card.dataset.eventType === eventFilterState.type;
      let rulesetOk = true;
      if (eventFilterState.ruleset) {
        const [filterType, ...filterValueParts] = eventFilterState.ruleset.split(':');
        const filterValue = filterValueParts.join(':');
        if (filterType === 'version') {
          rulesetOk = (card.dataset.eventVersion || '') === filterValue;
        } else if (filterType === 'ruleset') {
          rulesetOk = (card.dataset.eventRuleset || '') === filterValue;
        } else {
          rulesetOk = (card.dataset.eventRuleset || '') === eventFilterState.ruleset;
        }
      }
      const statusOk = !eventFilterState.status || card.dataset.eventStatus === eventFilterState.status;
      const [formatKind, ...formatParts] = eventFilterState.division.split(':');
      const formatValue = formatParts.join(':');
      const divisionOk = !eventFilterState.division
        || (formatKind === 'ruleset' ? card.dataset.eventRuleset === formatValue : card.dataset.eventDivision === formatValue);
      const seasonOk = !eventFilterState.season || card.dataset.eventSeason === eventFilterState.season;
      const show = queryOk && typeOk && rulesetOk && statusOk && divisionOk && seasonOk;
      card.hidden = !show;
    });
  }
  const eventSeasonFilter = document.querySelector('[data-event-season-filter]');
  if (eventSeasonFilter) eventSeasonFilter.value = eventFilterState.season;
  const eventSearchFilter = document.querySelector('[data-event-search-filter]');
  const eventTypeFilter = document.querySelector('[data-event-type-filter]');
  const eventRulesetFilter = document.querySelector('[data-event-ruleset-filter]');
  const eventStatusFilter = document.querySelector('[data-event-status-filter]');
  const eventDivisionFilter = document.querySelector('[data-event-division-filter]');
  const eventFilterClear = document.querySelector('[data-event-filter-clear]');
  [
    [eventSeasonFilter, 'season'],
    [eventSearchFilter, 'query'],
    [eventTypeFilter, 'type'],
    [eventRulesetFilter, 'ruleset'],
    [eventStatusFilter, 'status'],
    [eventDivisionFilter, 'division'],
  ].forEach(([control, key]) => {
    if (!control) return;
    control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', () => {
      eventFilterState[key] = key === 'query' ? String(control.value || '').trim().toLowerCase() : String(control.value || '');
      applyEventFilters();
    });
  });
  if (eventFilterClear) {
    eventFilterClear.addEventListener('click', () => {
      Object.keys(eventFilterState).forEach((key) => { eventFilterState[key] = ''; });
      [eventSeasonFilter, eventSearchFilter, eventTypeFilter, eventRulesetFilter, eventStatusFilter, eventDivisionFilter].forEach((control) => {
        if (control) control.value = '';
      });
      applyEventFilters();
    });
  }
  window.addEventListener('dml:routechange', (event) => {
    if (event.detail?.target !== 'torneos') return;
    const shortcutType = sessionStorage.getItem('dml_event_type_shortcut');
    if (shortcutType === null) return;
    sessionStorage.removeItem('dml_event_type_shortcut');
    eventFilterState.type = shortcutType;
    if (eventTypeFilter) eventTypeFilter.value = shortcutType;
    applyEventFilters();
  });
  const eventSortTable = document.querySelector('[data-event-sort-table]');
  const eventSortState = { key: 'start', dir: 'asc' };
  function sortEventTable(key) {
    if (!eventSortTable) return;
    const tbody = eventSortTable.querySelector('tbody');
    if (!tbody) return;
    eventSortState.dir = eventSortState.key === key && eventSortState.dir === 'asc' ? 'desc' : 'asc';
    eventSortState.key = key;
    const rows = Array.from(tbody.querySelectorAll('[data-event-card]'));
    const numericKeys = new Set(['decks', 'progress']);
    rows.sort((left, right) => {
      const a = left.dataset[`sort${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '';
      const b = right.dataset[`sort${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '';
      const result = numericKeys.has(key)
        ? (Number(a || 0) - Number(b || 0))
        : String(a).localeCompare(String(b), 'es', { numeric: true, sensitivity: 'base' });
      return eventSortState.dir === 'asc' ? result : -result;
    });
    rows.forEach((row) => tbody.appendChild(row));
    eventSortTable.querySelectorAll('[data-event-sort]').forEach((button) => {
      button.classList.toggle('active', button.dataset.eventSort === key);
      button.dataset.sortDir = button.dataset.eventSort === key ? eventSortState.dir : '';
    });
  }
  if (eventSortTable) {
    eventSortTable.querySelectorAll('[data-event-sort]').forEach((button) => {
      button.addEventListener('click', () => sortEventTable(button.dataset.eventSort || 'name'));
    });
  }

  const cupRows = Array.from(document.querySelectorAll('[data-cup-row]'));
  const cupSearch = document.querySelector('[data-cup-search]');
  const cupStatusFilter = document.querySelector('[data-cup-status-filter]');
  const cupRulesetFilter = document.querySelector('[data-cup-ruleset-filter]');
  const cupFilterClear = document.querySelector('[data-cup-filter-clear]');
  const cupVisibleCount = document.querySelector('[data-cup-visible-count]');
  const cupSortTable = document.querySelector('[data-cup-sort-table]');
  const cupSortState = { key: 'start', dir: 'asc' };
  function applyCupFilters() {
    const query = normalize(cupSearch ? cupSearch.value : '');
    const status = cupStatusFilter ? cupStatusFilter.value : '';
    const ruleset = cupRulesetFilter ? cupRulesetFilter.value : '';
    let visible = 0;
    cupRows.forEach((row) => {
      const matches = (!query || normalize(row.dataset.cupSearch || '').includes(query))
        && (!status || row.dataset.cupStatus === status)
        && (!ruleset || row.dataset.cupRuleset === ruleset);
      row.hidden = !matches;
      if (matches) visible += 1;
    });
    if (cupVisibleCount) cupVisibleCount.textContent = `${visible} copas`;
  }
  function sortCupTable(key) {
    if (!cupSortTable) return;
    const tbody = cupSortTable.querySelector('tbody');
    if (!tbody) return;
    cupSortState.dir = cupSortState.key === key && cupSortState.dir === 'asc' ? 'desc' : 'asc';
    cupSortState.key = key;
    const rows = Array.from(tbody.querySelectorAll('[data-cup-row]'));
    const numericKeys = new Set(['decks', 'matches']);
    rows.sort((left, right) => {
      const dataKey = `sort${key.charAt(0).toUpperCase()}${key.slice(1)}`;
      const a = left.dataset[dataKey] || '';
      const b = right.dataset[dataKey] || '';
      const result = numericKeys.has(key)
        ? Number(a || 0) - Number(b || 0)
        : String(a).localeCompare(String(b), 'es', { numeric: true, sensitivity: 'base' });
      return cupSortState.dir === 'asc' ? result : -result;
    });
    rows.forEach((row) => tbody.appendChild(row));
    cupSortTable.querySelectorAll('[data-cup-sort]').forEach((button) => {
      button.classList.toggle('active', button.dataset.cupSort === key);
      button.dataset.sortDir = button.dataset.cupSort === key ? cupSortState.dir : '';
    });
  }
  [cupSearch, cupStatusFilter, cupRulesetFilter].forEach((control) => {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', applyCupFilters);
  });
  if (cupFilterClear) {
    cupFilterClear.addEventListener('click', () => {
      [cupSearch, cupStatusFilter, cupRulesetFilter].forEach((control) => {
        if (control) control.value = '';
      });
      applyCupFilters();
    });
  }
  if (cupSortTable) {
    cupSortTable.querySelectorAll('[data-cup-sort]').forEach((button) => {
      button.addEventListener('click', () => sortCupTable(button.dataset.cupSort || 'name'));
    });
  }
  applyCupFilters();

  const eventDetail = document.querySelector('[data-event-detail]');
