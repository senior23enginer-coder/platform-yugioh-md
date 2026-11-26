(function (root) {
  "use strict";
  function create({ key, readValue, writeValue, now, random, uuid }) {
    function read() {
      try {
        const value = JSON.parse(readValue(key) || "[]");
        if (Array.isArray(value)) return value;
        return value && Array.isArray(value.payload)
          ? [{ id: value.id || `legacy-${now()}`, ...value }]
          : [];
      } catch {
        return [];
      }
    }
    function write(queue) {
      writeValue(key, JSON.stringify(queue));
    }
    function requestId() {
      const id = uuid();
      return id === null
        ? `offline-${now()}-${random().toString(16).slice(2)}`
        : id;
    }
    return Object.freeze({ read, write, requestId });
  }
  const api = Object.freeze({ create });
  root.StationXOfflineQueue = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
(function () {
  const data = window.DML_DATA || { divisions: {}, fixtures: {}, allDecks: [], teams: [], competitions: [], cards: [], media: [], honors: [], audit: [], events: [], rulesets: [], rulesetVersions: [], matchTypes: [], gameRules: [], gameRuleConditions: [], competitionDependencies: [], statusHistory: [], standingSnapshots: [], matchSnapshots: [], matchupAnalyses: [], matchupAnalysisHistory: [], deckAnalysisProfiles: [], deckAnalysisProfileHistory: [], medalUnlocks: [], medalProgress: [], rankingHistory: [], eventSummaries: [], checkins: [], metaHistory: [], eloHistory: [], settings: {} };
  const settings = data.settings || {};
  const OFFLINE_QUEUE_KEY = 'dml_offline_result_queue';
  const offlineQueue = window.StationXOfflineQueue.create({
    key: OFFLINE_QUEUE_KEY,
    readValue: (key) => localStorage.getItem(key),
    writeValue: (key, value) => localStorage.setItem(key, value),
    now: () => Date.now(),
    random: () => Math.random(),
    uuid: () => window.crypto?.randomUUID ? window.crypto.randomUUID() : null
  });
  const readOfflineQueue = offlineQueue.read;
  const writeOfflineQueue = offlineQueue.write;
  const makeRequestId = offlineQueue.requestId;
  const serverToday = data.serverToday || new Date().toISOString().slice(0, 10);
  const appMain = document.querySelector('.app-main');
  const detachedEventScreen = document.querySelector('[data-screen="evento_ficha"]');
  if (appMain && detachedEventScreen && detachedEventScreen.parentElement !== appMain) {
    appMain.appendChild(detachedEventScreen);
  }
  const screens = Array.from(document.querySelectorAll('[data-screen]'));
  const routeButtons = Array.from(document.querySelectorAll('[data-route]'));
  const search = document.querySelector('[data-global-search]');
  const searchResults = document.querySelector('[data-search-results]');
  const searchPageInput = document.querySelector('[data-search-page-input]');
  const searchPageResults = document.querySelector('[data-search-page-results]');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebarOpen = document.querySelector('[data-sidebar-open]');
  const sidebarCloseButtons = Array.from(document.querySelectorAll('[data-sidebar-close]'));
  const alertCenters = Array.from(document.querySelectorAll('[data-alert-center]'));
  const alertBadges = Array.from(document.querySelectorAll('[data-alert-badge]'));
  const alertModalNode = document.querySelector('[data-alert-modal]');
  const alertModalOpen = document.querySelector('[data-alert-modal-open]');
  const alertModal = alertModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(alertModalNode) : null;
  const officialDivisions = Object.keys(data.divisions || {}).filter((division) => Number(data.divisions[division].id || 0) > 0);
  let activeEventTypeShortcut = '';
  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  if (localStorage.getItem('dml_sidebar_collapsed') === '1') {
    document.body.classList.add('sidebar-collapsed');
  }

  function syncSidebarCollapseControl() {
    if (!sidebarToggle) return;
    const desktop = window.innerWidth > 1200;
    const collapsed = desktop && document.body.classList.contains('sidebar-collapsed');
    sidebarToggle.title = desktop ? (collapsed ? 'Expandir menu' : 'Contraer menu') : 'Cerrar menu';
    sidebarToggle.setAttribute('aria-label', sidebarToggle.title);
    sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    const icon = sidebarToggle.querySelector('i');
    if (icon) icon.className = `bi ${collapsed ? 'bi-chevron-right' : 'bi-chevron-left'}`;
    document.querySelectorAll('.side-menu [data-route], .side-group summary').forEach((button) => {
      const label = button.querySelector('span')?.textContent?.trim() || '';
      if (label) { button.title = collapsed ? label : ''; button.setAttribute('aria-label', label); }
    });
  }

  syncSidebarCollapseControl();

  const flashMessages = Array.from(document.querySelectorAll('[data-flash-message]'));
  if (flashMessages.length) {
    window.setTimeout(() => {
      flashMessages.forEach((message) => {
        message.style.transition = 'opacity 180ms ease, transform 180ms ease, max-height 220ms ease, margin 220ms ease, padding 220ms ease';
        message.style.opacity = '0';
        message.style.transform = 'translateY(-6px)';
        message.style.maxHeight = '0';
        message.style.marginTop = '0';
        message.style.marginBottom = '0';
        message.style.paddingTop = '0';
        message.style.paddingBottom = '0';
        window.setTimeout(() => message.remove(), 240);
      });
    }, 3500);
    const url = new URL(window.location.href);
    if (url.searchParams.has('saved')) {
      url.searchParams.delete('saved');
      history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
    }
  }

  function syncMobileCollapsibles() {
    document.querySelectorAll('.mobile-collapsible').forEach((node) => {
      if (window.innerWidth <= 640) {
        node.removeAttribute('open');
      } else {
        node.setAttribute('open', 'open');
      }
    });
  }

  syncMobileCollapsibles();
  window.addEventListener('resize', syncMobileCollapsibles);
  window.addEventListener('resize', syncSidebarCollapseControl);

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      if (window.innerWidth <= 1200) {
        document.body.classList.remove('sidebar-open');
        return;
      }
      document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('dml_sidebar_collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
      syncSidebarCollapseControl();
    });
  }
  if (sidebarOpen) {
    sidebarOpen.addEventListener('click', () => document.body.classList.add('sidebar-open'));
  }
  sidebarCloseButtons.forEach((button) => {
    button.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
  });

  function routeTo(route, navigationMode = 'push') {
    const requested = route === 'admin' ? 'gestion' : (route || 'dashboard');
    const routed = requested === 'tabla' ? 'estadisticas' : requested;
    if (requested === 'tabla') window.YgoStatisticsWorkspace?.open('tables');
    const target = screens.some((screen) => screen.dataset.screen === routed) ? routed : 'dashboard';
    screens.forEach((screen) => {
      screen.hidden = screen.dataset.screen !== target;
    });
    routeButtons.forEach((button) => {
      const buttonShortcut = button.dataset.eventTypeShortcut || '';
      const eventParent = button.closest('.side-menu') && button.dataset.route === 'torneos'
        && ['torneos', 'clasificatorio', 'copas', 'mundial', 'divisiones', 'crear_evento', 'evento_ficha'].includes(target);
      const isActive = eventParent || (button.dataset.route === target
        && (target !== 'torneos' || buttonShortcut === activeEventTypeShortcut));
      button.classList.toggle('active', isActive);
      if (isActive) {
        const group = button.closest('.side-group');
        if (group) group.open = true;
      }
    });
    localStorage.setItem('dml_screen', target);
    if (location.hash !== `#${target}`) {
      // Record real user navigation so Back/Forward return to the previous
      // Station X screen. Restoring history or bootstrapping a deep link
      // must never add another entry.
      const url = new URL(window.location.href);
      url.hash = target;
      if (navigationMode === 'push') {
        history.pushState(history.state, '', `${url.pathname}${url.search}${url.hash}`);
      } else {
        history.replaceState(history.state, '', `${url.pathname}${url.search}${url.hash}`);
      }
    }
    document.body.classList.remove('sidebar-open');
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    window.dispatchEvent(new CustomEvent('dml:routechange', { detail: { target, requested } }));
  }

  function setEventFichaUrl(competitionId) {
    const id = String(competitionId || '');
    if (!id) return;
    localStorage.setItem('dml_event_ficha_id', id);
    const url = new URL(window.location.href);
    url.searchParams.set('event_id', id);
    url.hash = 'evento_ficha';
    history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function requestedEventFichaId() {
    const params = new URLSearchParams(window.location.search);
    const direct = params.get('event_id') || params.get('evento') || '';
    if (direct) return direct;
    const saved = params.get('saved') || '';
    const savedMatch = saved.match(/^evento_configurado_(\d+)$/);
    if (savedMatch) return savedMatch[1];
    return localStorage.getItem('dml_event_ficha_id') || '';
  }

  routeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (button.dataset.route === 'clasificatorio') {
        const directoryUrl = new URL(window.location.href);
        if (directoryUrl.searchParams.has('event_id')) {
          directoryUrl.searchParams.delete('event_id');
          directoryUrl.hash = 'clasificatorio';
          window.location.href = directoryUrl.toString();
          return;
        }
      }
      if (button.dataset.route === 'calendario') clearCalendarProfileScope(false);
      if (button.dataset.eventTypeShortcut !== undefined) {
        activeEventTypeShortcut = button.dataset.eventTypeShortcut || '';
        sessionStorage.setItem('dml_event_type_shortcut', activeEventTypeShortcut);
      } else {
        activeEventTypeShortcut = '';
      }
      routeTo(button.dataset.route);
    });
  });
  const restoreBrowserRoute = () => {
    routeTo((location.hash || '').replace('#', '') || 'dashboard', 'restore');
  };
  window.addEventListener('popstate', restoreBrowserRoute);
  window.addEventListener('hashchange', restoreBrowserRoute);

  const divisionOpenButtons = Array.from(document.querySelectorAll('[data-division-open]'));
  const divisionDetailPanels = Array.from(document.querySelectorAll('[data-division-detail]'));
  const divisionCategoryOrder = ['decks', 'processes', 'events', 'matches'];
  const divisionCategoryMeta = {
    decks: ['Decks y equipos', 'Participantes, equipos, arquetipos y estilos registrados en esta division.'],
    processes: ['Procesos de la division', 'Accesos operativos para tabla, calendario, resultados y eventos asociados.'],
    events: ['Eventos asociados', 'Copas, ligas y torneos vinculados con esta division.'],
    matches: ['Combates de la division', 'Fixture, fechas, horas y estados del calendario de esta categoria.']
  };
  const divisionCategoryModalNode = document.querySelector('[data-division-category-modal]');
  const divisionCategoryModal = divisionCategoryModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(divisionCategoryModalNode) : null;
  const divisionCategoryBody = document.querySelector('[data-division-category-body]');
  const divisionCategoryStaging = document.querySelector('[data-division-category-staging]');
  let activeDivisionCategory = '';
  let activeDivisionKey = '';

  if (divisionCategoryStaging) {
    document.querySelectorAll('[data-division-category-source]').forEach((node) => divisionCategoryStaging.appendChild(node));
  }

  function openDivisionDetail(division, shouldScroll = false) {
    if (!divisionDetailPanels.length) return;
    const target = division || divisionOpenButtons[0]?.dataset.divisionOpen || '';
    divisionOpenButtons.forEach((button) => button.classList.toggle('active', button.dataset.divisionOpen === target));
    divisionDetailPanels.forEach((panel) => {
      panel.hidden = panel.dataset.divisionDetail !== target;
    });
    activeDivisionKey = target;
    if (shouldScroll) {
      const panel = divisionDetailPanels.find((item) => item.dataset.divisionDetail === target);
      panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  divisionOpenButtons.forEach((button) => {
    button.addEventListener('click', () => openDivisionDetail(button.dataset.divisionOpen, button.hasAttribute('data-division-scroll')));
  });
  openDivisionDetail();

  function setDivisionCategoryUrl(category) {
    const url = new URL(window.location.href);
    if (activeDivisionKey) url.searchParams.set('division', activeDivisionKey);
    if (category) url.searchParams.set('division_section', category);
    else url.searchParams.delete('division_section');
    history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function openDivisionCategory(owner, category, updateUrl = true) {
    if (!divisionCategoryModal || !divisionCategoryBody || !divisionCategoryOrder.includes(category)) return;
    const key = owner || activeDivisionKey || divisionDetailPanels[0]?.dataset.divisionDetail || '';
    openDivisionDetail(key);
    Array.from(divisionCategoryBody.children).forEach((node) => divisionCategoryStaging?.appendChild(node));
    const source = divisionCategoryStaging?.querySelector(`[data-division-category-source="${CSS.escape(category)}"][data-division-category-owner="${CSS.escape(key)}"]`);
    if (!source) return;
    divisionCategoryBody.appendChild(source);
    activeDivisionKey = key;
    activeDivisionCategory = category;
    const meta = divisionCategoryMeta[category];
    const panel = divisionDetailPanels.find((item) => item.dataset.divisionDetail === key);
    const divisionName = panel?.querySelector('.division-hero h2')?.textContent?.trim() || 'Division';
    const context = document.querySelector('[data-division-category-context]');
    const title = document.querySelector('[data-division-category-title]');
    const subtitle = document.querySelector('[data-division-category-subtitle]');
    const position = document.querySelector('[data-division-category-position]');
    if (context) context.textContent = divisionName;
    if (title) title.textContent = meta[0];
    if (subtitle) subtitle.textContent = meta[1];
    if (position) position.textContent = `${divisionCategoryOrder.indexOf(category) + 1} de ${divisionCategoryOrder.length}`;
    if (updateUrl) setDivisionCategoryUrl(category);
    divisionCategoryModal.show();
  }

  document.querySelectorAll('[data-division-category-open]').forEach((button) => {
    button.addEventListener('click', () => openDivisionCategory(button.dataset.divisionCategoryOwner, button.dataset.divisionCategoryOpen));
  });
  document.querySelector('[data-division-category-prev]')?.addEventListener('click', () => {
    const index = divisionCategoryOrder.indexOf(activeDivisionCategory);
    openDivisionCategory(activeDivisionKey, divisionCategoryOrder[(index - 1 + divisionCategoryOrder.length) % divisionCategoryOrder.length]);
  });
  document.querySelector('[data-division-category-next]')?.addEventListener('click', () => {
    const index = divisionCategoryOrder.indexOf(activeDivisionCategory);
    openDivisionCategory(activeDivisionKey, divisionCategoryOrder[(index + 1) % divisionCategoryOrder.length]);
  });
  divisionCategoryModalNode?.addEventListener('hidden.bs.modal', () => {
    Array.from(divisionCategoryBody?.children || []).forEach((node) => divisionCategoryStaging?.appendChild(node));
    activeDivisionCategory = '';
    setDivisionCategoryUrl('');
  });

  const qualifierTabs = Array.from(document.querySelectorAll('[data-qualifier-tab]'));
  const qualifierPanels = Array.from(document.querySelectorAll('[data-qualifier-panel]'));
  const qualifierEventSwitch = document.querySelector('[data-qualifier-event-switch]');

  function openQualifierPanel(panelName) {
    if (!qualifierPanels.length) return;
    const target = qualifierPanels.some((panel) => panel.dataset.qualifierPanel === panelName) ? panelName : 'configurar';
    qualifierTabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.qualifierTab === target));
    qualifierPanels.forEach((panel) => {
      panel.hidden = panel.dataset.qualifierPanel !== target;
      panel.classList.toggle('active', panel.dataset.qualifierPanel === target);
    });
    localStorage.setItem('dml_qualifier_panel', target);
  }

  qualifierTabs.forEach((tab) => {
    tab.addEventListener('click', () => openQualifierPanel(tab.dataset.qualifierTab));
  });
  qualifierEventSwitch?.addEventListener('change', () => {
    const eventId = qualifierEventSwitch.value;
    if (!eventId) return;
    const url = new URL(window.location.href);
    url.searchParams.set('event_id', eventId);
    url.hash = 'clasificatorio';
    window.location.href = url.toString();
  });
  openQualifierPanel(localStorage.getItem('dml_qualifier_panel') || 'configurar');

  const qualifierDirectoryRows = Array.from(document.querySelectorAll('[data-qualifier-directory-row]'));
  const qualifierDirectorySearch = document.querySelector('[data-qualifier-directory-search]');
  const qualifierDirectoryStatus = document.querySelector('[data-qualifier-directory-status]');
  const qualifierDirectoryFormat = document.querySelector('[data-qualifier-directory-format]');
  const qualifierDirectoryClear = document.querySelector('[data-qualifier-directory-clear]');
  const qualifierDirectoryCount = document.querySelector('[data-qualifier-directory-count]');
  const qualifierDirectoryTable = document.querySelector('[data-qualifier-directory-table]');
  const qualifierDirectorySortState = { key: 'start', dir: 'asc' };

  function applyQualifierDirectoryFilters() {
    const query = normalize(qualifierDirectorySearch?.value || '');
    const status = qualifierDirectoryStatus?.value || '';
    const format = qualifierDirectoryFormat?.value || '';
    let visible = 0;
    qualifierDirectoryRows.forEach((row) => {
      const show = (!query || normalize(row.dataset.qualifierSearch || '').includes(query))
        && (!status || row.dataset.qualifierStatus === status)
        && (!format || row.dataset.qualifierFormat === format);
      row.hidden = !show;
      if (show) visible += 1;
    });
    if (qualifierDirectoryCount) qualifierDirectoryCount.textContent = `${visible} ${visible === 1 ? 'evento' : 'eventos'}`;
  }

  function sortQualifierDirectory(key, toggle = true) {
    const tbody = qualifierDirectoryTable?.querySelector('tbody');
    if (!tbody) return;
    if (toggle) {
      qualifierDirectorySortState.dir = qualifierDirectorySortState.key === key && qualifierDirectorySortState.dir === 'asc' ? 'desc' : 'asc';
    }
    qualifierDirectorySortState.key = key;
    const numeric = key === 'participants';
    qualifierDirectoryRows.sort((left, right) => {
      const dataKey = `sort${key.charAt(0).toUpperCase()}${key.slice(1)}`;
      const a = left.dataset[dataKey] || '';
      const b = right.dataset[dataKey] || '';
      const compared = numeric ? Number(a) - Number(b) : String(a).localeCompare(String(b), 'es', { numeric: true, sensitivity: 'base' });
      return qualifierDirectorySortState.dir === 'asc' ? compared : -compared;
    });
    qualifierDirectoryRows.forEach((row) => tbody.appendChild(row));
    qualifierDirectoryTable.querySelectorAll('[data-qualifier-directory-sort]').forEach((button) => {
      button.classList.toggle('active', button.dataset.qualifierDirectorySort === key);
    });
  }

  [qualifierDirectorySearch, qualifierDirectoryStatus, qualifierDirectoryFormat].forEach((control) => {
    control?.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', applyQualifierDirectoryFilters);
  });
  qualifierDirectoryClear?.addEventListener('click', () => {
    [qualifierDirectorySearch, qualifierDirectoryStatus, qualifierDirectoryFormat].forEach((control) => { if (control) control.value = ''; });
    applyQualifierDirectoryFilters();
  });
  qualifierDirectoryTable?.querySelectorAll('[data-qualifier-directory-sort]').forEach((button) => {
    button.addEventListener('click', () => sortQualifierDirectory(button.dataset.qualifierDirectorySort || 'name'));
  });
  document.querySelectorAll('[data-qualifier-directory-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const url = new URL(window.location.href);
      url.searchParams.set('event_id', button.dataset.qualifierDirectoryOpen || '');
      url.hash = 'clasificatorio';
      window.location.href = url.toString();
    });
  });
  document.querySelectorAll('[data-qualifier-directory-back]').forEach((button) => {
    button.addEventListener('click', () => {
      const url = new URL(window.location.href);
      url.searchParams.delete('event_id');
      url.hash = 'clasificatorio';
      window.location.href = url.toString();
    });
  });
  sortQualifierDirectory('start', false);
  applyQualifierDirectoryFilters();

  function normalize(value) {
    return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  function normalizeStatusKey(value) {
    return normalize(value).replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
  }

  function searchItems(query, limit = 12) {
    const catalog = { ...data, competitions: (data.competitions || []).map(event => ({ ...event, display_name: window.StationXLabels?.text(event.name) || event.name })) };
    return window.YgoGlobalSearch.search(catalog, query, limit);
  }

  function setSearchEntityUrl(parameter, id) {
    const url = new URL(window.location.href);
    ['deck_id', 'team_id', 'event_id', 'deck_section'].forEach(key => url.searchParams.delete(key));
    url.searchParams.set(parameter, String(id));
    history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function openSearchItem(item) {
    if (!item) return;
    if (searchResults) searchResults.hidden = true;
    if (item.type === 'Deck') {
      const deck = (data.allDecks || []).find(d => String(d.id) === String(item.id));
      if (!deck || !deckSelect) return;
      setSearchEntityUrl('deck_id', item.id);
      deckSelect.value = deck.name;
      renderProfile(item.id);
      routeTo('deck_ficha');
    } else if (item.type === 'Equipo') {
      if (!adminTeamSelect) return;
      setSearchEntityUrl('team_id', item.id);
      adminTeamSelect.value = String(item.id);
      adminTeamSelect.dispatchEvent(new Event('change'));
      routeTo('equipo_ficha');
    } else if (item.type === 'Evento') {
      if (fillCompetitionConfig(item.id)) { setSearchEntityUrl('event_id', item.id); routeTo('evento_ficha'); }
    }
  }

  function renderSearchResults(container, query, compact) {
    if (!container) return;
    const items = searchItems(query, compact ? 12 : 1000);
    if (!normalize(query)) {
      container.hidden = compact;
      container.innerHTML = compact ? '' : '<p class="note">Escribe para buscar en decks, equipos y eventos.</p>';
      return;
    }
    if (!items.length) {
      container.hidden = false;
      container.innerHTML = '<div class="search-empty">Sin resultados. Revisa nombre del deck, equipo o evento.</div>';
      return;
    }
    container.hidden = false;
    container.innerHTML = items.map((item, index) => `
      <button type="button" class="search-result-item" data-search-index="${index}">
        <span>${escapeHtml(item.type)}</span>
        <b>${escapeHtml(item.label)}</b>
        <small>${escapeHtml(item.description)}</small>
      </button>
    `).join('');
    Array.from(container.querySelectorAll('[data-search-index]')).forEach((button) => {
      button.addEventListener('click', () => openSearchItem(items[Number(button.dataset.searchIndex)]));
    });
  }

  function fixtureRoundsFor(division) {
    const rounds = data.fixtures?.[division] || [];
    return Array.isArray(rounds) ? rounds : Object.values(rounds || {});
  }

  function allMatches(division) {
    return fixtureRoundsFor(division).flatMap((round) =>
      (round.matches || []).map((match) => ({ ...match, ronda: round.ronda, division }))
    );
  }

  function allMatchList() {
    return Object.keys(data.fixtures || {}).flatMap((division) => allMatches(division));
  }

  function individualMatchList() {
    return allMatchList().flatMap(match => {
      const event=(data.competitions || []).find(c=>String(c.id)===String(match.competition_id));
      if(!event?.teamConfig)return [match];
      const r=data.teamResults?.[match.id];if(!r || r.kind!=='team')return [];
      return r.evaluations.map((evaluation,seat)=>({...match,deck_a_id:r.lineup_a.decks[seat],deck_b_id:r.lineup_b.decks[seat],deck_c_id:null,deck_d_id:null,deck_e_id:null,deck_f_id:null,a:deckInfoById(r.lineup_a.decks[seat])?.name || '',b:deckInfoById(r.lineup_b.decks[seat])?.name || '',team_a_extra:[],team_b_extra:[],match_format:'1v1',participants_per_side:1,duels:r.sequences[seat],result:evaluation.winner,score:evaluation.score,duel_details:'[]'}));
    });
  }
  function deckInfoById(id) { return (data.allDecks || []).find(d=>String(d.id)===String(id)); }

  function activeAlertCompetitionIds() {
    return new Set((data.competitions || [])
      .filter((competition) => {
        const status = normalizeStatusKey(competition.status || '');
        return Number(competition.id || 0) > 0 && ['active', 'activo', 'en_curso'].includes(status);
      })
      .map((competition) => String(competition.id)));
  }

  function alertableMatchList() {
    const activeIds = activeAlertCompetitionIds();
    if (!activeIds.size) return [];
    return allMatchList().filter((match) =>
      match.id
      && match.competition_id
      && activeIds.has(String(match.competition_id))
      && !['FINALIZADA', 'completed', 'finalizada', 'cancelled', 'canceled', 'anulado'].includes(String(match.estado || match.status || ''))
    );
  }

  function deckInfoByName(name) {
    return (data.allDecks || []).find((deck) => normalize(deck.name) === normalize(name)) || {};
  }

  function matchSideLogoUrl(match, side) {
    const deckName = side === 'a' ? match.a : match.b;
    const deck = deckInfoByName(deckName);
    const teamLogo = side === 'a' ? match.team_a_logo : match.team_b_logo;
    return teamLogo || deck.team_logo || deck.logo_url || deck.cover_url || '';
  }

  function matchSideFallback(match, side) {
    const deckName = side === 'a' ? match.a : match.b;
    const teamName = side === 'a' ? match.team_a : match.team_b;
    const teamCode = side === 'a' ? match.team_a_code : match.team_b_code;
    const deck = deckInfoByName(deckName);
    return String(teamCode || deck.team_code || deck.team_symbol || teamName || deck.team || deckName || '?').replace(/[^a-z0-9]/gi, '').slice(0, 3).toUpperCase() || '?';
  }

  function matchLabelWithTeam(deckName) {
    const deck = deckInfoByName(deckName);
    return `${deck.team || 'Sin equipo'} / ${deckName}`;
  }

  function matchSideDecks(match, side) {
    const base = side === 'a' ? match.a : match.b;
    const extra = side === 'a' ? (match.team_a_extra || []) : (match.team_b_extra || []);
    return [base, ...extra].filter(Boolean);
  }

  function matchSideLabel(match, side) {
    return matchSideDecks(match, side).map(matchLabelWithTeam).join(' + ');
  }

  function resultTeamLogoCell(match, side) {
    const deckName = side === 'a' ? match.a : match.b;
    const teamName = side === 'a' ? match.team_a : match.team_b;
    const extraDecks = side === 'a' ? (match.team_a_extra || []) : (match.team_b_extra || []);
    const safeTeamName = teamName || 'Sin equipo';
    const logoUrl = matchSideLogoUrl(match, side);
    const fallback = matchSideFallback(match, side);
    const logo = logoUrl
      ? `<span class="result-team-logo"><img loading="lazy" decoding="async" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(safeTeamName)}"></span>`
      : `<span class="result-team-logo result-team-logo-fallback">${escapeHtml(fallback)}</span>`;
    const extra = extraDecks.length ? ` + ${extraDecks.length} deck${extraDecks.length === 1 ? '' : 's'}` : '';
    return `
      <div class="result-team-cell">
        ${logo}
        <span class="result-team-copy">
          <strong title="${escapeHtml(safeTeamName)}">${escapeHtml(safeTeamName)}</strong>
          <small title="${escapeHtml(deckName || 'Por definir')}">${escapeHtml(deckName || 'Por definir')}${escapeHtml(extra)}</small>
        </span>
      </div>`;
  }

  function matchVersusLabel(match) {
    return `${matchSideLabel(match, 'a')} vs ${matchSideLabel(match, 'b')}`;
  }

  function matchShortSideLabel(match, side) {
    return matchSideDecks(match, side).join(' + ') || 'Por definir';
  }

  function statusClassForMatch(match) {
    const status = normalizeStatusKey(match.estado || match.status || 'PENDIENTE');
    if (['finalizada', 'completed', 'finalizado'].includes(status)) return 'is-done';
    if (['activo', 'active', 'en_curso'].includes(status)) return 'is-active';
    return 'is-pending';
  }

  function renderCupBracket(matches, competition, success = '') {
    if (window.StationXCupTree && !competition?.workflow && ['single_elimination', 'bracket', 'manual'].includes(competition?.execution_model || 'manual')) {
      return success + window.StationXCupTree.render(matches, competition || {}, data.settings || {}, data.allDecks || [], escapeHtml, {logo: matchSideLogoUrl});
    }
    const sorted = matches.slice().sort((left, right) =>
      Number(left.display_order || 0) - Number(right.display_order || 0)
      || Number(left.ronda || left.round_number || 0) - Number(right.ronda || right.round_number || 0)
      || Number(left.mesa || left.table_number || 0) - Number(right.mesa || right.table_number || 0)
    );
    if (!sorted.length) {
      return '<p class="note">No hay duelos programados para mostrar la llave de esta copa.</p>';
    }
    const rounds = sorted.reduce((map, match) => {
      const key = Number(match.ronda || match.round_number || 1);
      if (!map[key]) map[key] = [];
      map[key].push(match);
      return map;
    }, {});
    const roundKeys = Object.keys(rounds).map(Number).sort((a, b) => a - b);
    const title = competition?.name || 'Copa';
    return `${success}
      <div class="cup-bracket-shell" style="--event-accent: ${escapeHtml(competition?.accent || '#ff2bd6')}; --bracket-columns: ${Math.max(1, roundKeys.length)}">
        <div class="cup-bracket-stage">
          ${roundKeys.map((roundNumber, index) => `
            <section class="cup-bracket-round">
              <header>
                <span>${escapeHtml(rounds[roundNumber][0]?.bracket_round_label || `Ronda ${roundNumber}`)}</span>
                <b>${escapeHtml(rounds[roundNumber].length)} combates</b>
              </header>
              <div class="cup-bracket-matches">
                ${rounds[roundNumber].map((match) => `
                  <article class="cup-bracket-match ${statusClassForMatch(match)}">
                    <div class="cup-bracket-meta">
                      <span>Slot ${escapeHtml(match.bracket_slot || match.mesa || match.table_number || '-')}</span>
                      <b>${escapeHtml(match.scheduled_on || 'Sin fecha')}</b>
                    </div>
                    <div class="cup-bracket-team team-a">${escapeHtml(matchShortSideLabel(match, 'a'))}</div>
                    <div class="cup-bracket-team team-b">${escapeHtml(matchShortSideLabel(match, 'b'))}</div>
                    <small>${escapeHtml(match.estado || match.status || 'PENDIENTE')}</small>
                    ${competition?.workflow ? `<small>${escapeHtml(match.bracket_round_label || '')}</small>` : ''}
                    <button class="btn btn-outline-info btn-sm" type="button" data-schedule-open-match="${escapeHtml(match.id)}"><i class="bi bi-eye"></i> Ver ficha</button>
                  </article>
                `).join('')}
              </div>
            </section>
          `).join('')}
          <section class="cup-bracket-winner">
            <i class="bi bi-trophy-fill"></i>
            <span>Ganador</span>
            <b>${escapeHtml(title)}</b>
          </section>
        </div>
      </div>`;
  }

  function ruleForMatch(match) {
    if (match?.game_rule_id) {
      const directRule = (data.gameRules || []).find((rule) => String(rule.id || '') === String(match.game_rule_id || '') && String(rule.active ?? '1') !== '0');
      if (directRule) return directRule;
    }
    const competition = (data.competitions || []).find((item) => String(item.id || '') === String(match?.competition_id || ''));
    if (competition?.game_rule_id) {
      const competitionRule = (data.gameRules || []).find((rule) => String(rule.id || '') === String(competition.game_rule_id || '') && String(rule.active ?? '1') !== '0');
      if (competitionRule) return competitionRule;
    }
    const phase = String(match?.phase || '');
    const scopes = phase === 'friendly'
      ? ['all_matches', 'friendly', 'match', 'general']
      : ['all_matches', 'match', 'general'];
    return (data.gameRules || [])
      .filter((rule) => String(rule.active ?? '1') !== '0' && scopes.includes(String(rule.scope || '')))
      .sort((a, b) => {
        const scopeDiff = scopes.indexOf(String(a.scope || '')) - scopes.indexOf(String(b.scope || ''));
        if (scopeDiff !== 0) return scopeDiff;
        return Number(a.priority_order || 100) - Number(b.priority_order || 100);
      })[0] || null;
  }

  function evaluateDuelByRule(values, match = null) {
    const rule = ruleForMatch(match);
    return rule ? window.YgoEventModels.evaluateSequence(values, match?.best_of || 3, rule.conditions || []) : null;
  }

  function evaluateBestOfDuel(values, configuredBestOf = 3) {
    return window.YgoEventModels.evaluateSequence(values, configuredBestOf);
  }

  function evaluateDuel(values, match = null) {
    const ruleResult = evaluateDuelByRule(values, match);
    if (ruleResult) return ruleResult;
    return evaluateBestOfDuel(values, match?.best_of);
  }

  function resultKey(match) {
    return `dml_result_${match.division}_${match.ronda}_${match.mesa}`;
  }

  function getSavedResult(match) {
    if (match && Array.isArray(match.duels) && match.duels.length) return match.duels;
    try {
      return JSON.parse(localStorage.getItem(resultKey(match)) || '[]');
    } catch {
      return [];
    }
  }

  function computeStandings(division) {
    if (!data.divisions[division]) return [];
    const rows = data.divisions[division].decks.map((deck) => {
      const fullDeck = (data.allDecks || []).find((item) => item.name === deck[1]) || {};
      return {
        rank: deck[0],
        name: deck[1],
        team: fullDeck.team || deck.team || 'Sin equipo',
        teamCode: fullDeck.team_code || fullDeck.team_symbol || '',
        teamPrimary: fullDeck.team_primary_color || '#36d8ff',
        teamSecondary: fullDeck.team_secondary_color || '#ffd25f',
        pts: 0,
        pj: 0,
        pg: 0,
        pe: 0,
        pp: 0,
        cg: 0,
        cp: 0,
        dp: 0,
        d5: 0,
        sweep: 0,
        streak: '-'
      };
    });
    const byName = Object.fromEntries(rows.map((row) => [row.name, row]));
    allMatches(division).forEach((match) => {
      const result = evaluateDuel(getSavedResult(match), match);
      if (result.state !== 'FINALIZADA') return;
      const a = byName[match.a];
      const b = byName[match.b];
      if (!a || !b) return;
      const [cgA, cgB] = result.score.split('-').map(Number);
      a.pj += 1; b.pj += 1;
      a.cg += cgA; a.cp += cgB; b.cg += cgB; b.cp += cgA;
      if (getSavedResult(match)[4]) {
        if (result.winner === 'A') a.d5 += 1;
        if (result.winner === 'B') b.d5 += 1;
      }
      if (result.score === '2-0') a.sweep += 1;
      if (result.score === '0-2') b.sweep += 1;
      if (result.winner === 'A') { a.pts += 3; a.pg += 1; b.pp += 1; a.streak = 'W'; b.streak = 'L'; }
      if (result.winner === 'B') { b.pts += 3; b.pg += 1; a.pp += 1; b.streak = 'W'; a.streak = 'L'; }
      if (result.winner === 'E') { a.pts += 1; b.pts += 1; a.pe += 1; b.pe += 1; a.streak = 'D'; b.streak = 'D'; }
    });
    rows.forEach((row) => { row.dp = row.cg - row.cp; });
    return rows.sort((a, b) => b.pts - a.pts || b.dp - a.dp || b.cg - a.cg || a.rank - b.rank);
  }

  function hasPlayedMatches(division) {
    return computeStandings(division).some((row) => row.pj > 0);
  }

  function competitionStatus(type, division) {
    const competition = competitionFor(type, division);
    return competition ? String(competition.status || 'draft').toLowerCase() : 'draft';
  }

  const fixtureCompetition = document.querySelector('[data-fixture-competition]');
  const fixtureRound = document.querySelector('[data-fixture-round]');
  const fixtureBody = document.querySelector('[data-fixture-body]');
  const fixtureBye = document.querySelector('[data-fixture-bye]');

  function activeFixtureCompetitions() {
    const competitionIdsWithMatches = new Set(allMatchList()
      .map((match) => String(match.competition_id || ''))
      .filter(Boolean));
    return (data.competitions || [])
      .filter((competition) => {
        const status = normalizeStatusKey(competition.status || '');
        return competitionIdsWithMatches.has(String(competition.id || ''))
          && ['active', 'activo', 'en_curso'].includes(status);
      })
      .sort((left, right) => String(left.name || '').localeCompare(String(right.name || ''), 'es'));
  }

  function fixtureRoundsForCompetition(competitionId) {
    const grouped = new Map();
    allMatchList()
      .filter((match) => String(match.competition_id || '') === String(competitionId || ''))
      .forEach((match) => {
        const roundNumber = Number(match.ronda || match.round_number || 1);
        if (!grouped.has(roundNumber)) grouped.set(roundNumber, { ronda: roundNumber, matches: [] });
        grouped.get(roundNumber).matches.push(match);
      });
    return Array.from(grouped.values())
      .sort((left, right) => left.ronda - right.ronda)
      .map((round) => ({
        ...round,
        matches: round.matches.sort((left, right) => Number(left.mesa || 0) - Number(right.mesa || 0) || Number(left.id || 0) - Number(right.id || 0))
      }));
  }

  function renderFixtureCompetitionOptions() {
    if (!fixtureCompetition) return;
    const competitions = activeFixtureCompetitions();
    const requestedId = new URLSearchParams(window.location.search).get('event_id') || '';
    const currentId = fixtureCompetition.value;
    fixtureCompetition.innerHTML = competitions.length
      ? competitions.map((competition) => `<option value="${escapeHtml(competition.id)}">${escapeHtml(competition.name || 'Evento')} | ${escapeHtml(eventTypeLabel(competition.type))}</option>`).join('')
      : '<option value="">No hay eventos activos con partidas</option>';
    const preferredId = [currentId, requestedId].find((id) => competitions.some((competition) => String(competition.id) === String(id)));
    if (preferredId) fixtureCompetition.value = preferredId;
  }

  function renderFixture() {
    if (!fixtureCompetition || !fixtureRound || !fixtureBody) return;
    const competitionId = fixtureCompetition.value;
    const rounds = fixtureRoundsForCompetition(competitionId);
    const selected = Number(fixtureRound.value || rounds[0]?.ronda || 1);
    fixtureRound.innerHTML = rounds.length
      ? rounds.map((round) => `<option value="${round.ronda}" ${round.ronda === selected ? 'selected' : ''}>Ronda ${round.ronda}</option>`).join('')
      : '<option value="">Sin rondas programadas</option>';
    const round = rounds.find((item) => item.ronda === selected) || rounds[0];
    fixtureBye.textContent = round ? `${round.matches.length} partida${round.matches.length === 1 ? '' : 's'}` : 'Sin partidas';
    fixtureBody.innerHTML = round
      ? (round.matches || []).map((match) => `<tr><td>${escapeHtml(match.mesa)}</td><td>${escapeHtml(matchSideLabel(match, 'a'))}</td><td>${escapeHtml(matchSideLabel(match, 'b'))}</td><td><span class="status-chip">${escapeHtml(match.estado || 'PENDIENTE')}</span></td></tr>`).join('')
      : '<tr><td colspan="4"><div class="empty-state">No hay partidas programadas para este evento activo.</div></td></tr>';
  }

  if (fixtureCompetition) fixtureCompetition.addEventListener('change', () => { fixtureRound.value = ''; renderFixture(); });
  if (fixtureRound) fixtureRound.addEventListener('change', renderFixture);
  renderFixtureCompetitionOptions();
  renderFixture();

  const calendarGrid = document.querySelector('[data-calendar-grid]');
  const calendarTitle = document.querySelector('[data-calendar-title]');
  const calendarCount = document.querySelector('[data-calendar-count]');
  const calendarDetail = document.querySelector('[data-calendar-detail]');
  const calendarPrev = document.querySelector('[data-calendar-prev]');
  const calendarNext = document.querySelector('[data-calendar-next]');
  const calendarToday = document.querySelector('[data-calendar-today]');
  const calendarType = document.querySelector('[data-calendar-type]');
  const calendarDivision = document.querySelector('[data-calendar-division]');
  const calendarStatus = document.querySelector('[data-calendar-status]');
  const calendarSearch = document.querySelector('[data-calendar-search]');
  const calendarProfileScopeNode = document.querySelector('[data-calendar-profile-scope]');
  const calendarProfileKind = document.querySelector('[data-calendar-profile-kind]');
  const calendarProfileLabel = document.querySelector('[data-calendar-profile-label]');
  const calendarProfileClear = document.querySelector('[data-calendar-profile-clear]');
  const profileCalendarButton = document.querySelector('[data-profile-calendar]');
  const teamCalendarButton = document.querySelector('[data-team-calendar]');
  const calendarWeekdays = document.querySelector('[data-calendar-weekdays]');
  const calendarViewButtons = Array.from(document.querySelectorAll('[data-calendar-view]'));
  const fullCalendarNode = document.querySelector('.fullcalendar-shell[data-fullcalendar]');
  const fallbackNodes = [calendarWeekdays, calendarGrid].filter(Boolean);
  const calendarModalNode = document.querySelector('[data-calendar-modal]');
  const calendarModal = calendarModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(calendarModalNode) : null;
  const calendarDayModalNode = document.querySelector('[data-calendar-day-modal]');
  const calendarDayModal = calendarDayModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(calendarDayModalNode) : null;
  const deleteFriendlyModalNode = document.querySelector('[data-delete-friendly-modal]');
  const deleteFriendlyModal = deleteFriendlyModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(deleteFriendlyModalNode) : null;
  const friendlyMatchModalNode = document.querySelector('[data-friendly-match-modal]');
  const friendlyDivisionSelect = friendlyMatchModalNode ? friendlyMatchModalNode.querySelector('[data-friendly-division]') : null;
  const friendlyDivisionValue = friendlyMatchModalNode ? friendlyMatchModalNode.querySelector('[data-friendly-division-value]') : null;
  const friendlyRulesetValue = friendlyMatchModalNode ? friendlyMatchModalNode.querySelector('[data-friendly-ruleset-value]') : null;
  const friendlyDeckSelects = friendlyMatchModalNode ? Array.from(friendlyMatchModalNode.querySelectorAll('[data-friendly-deck-select]')) : [];
  let fullCalendar = null;
  let calendarCursor = new Date();
  calendarCursor.setDate(1);
  let selectedCalendarDate = serverToday;
  let calendarView = localStorage.getItem('dml_calendar_view') || 'month';
  let calendarProfileScope = null;

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
  function syncResultPeriodFields() {
    const period = resultPeriod?.value || 'all';
    document.querySelectorAll('[data-result-period-field]').forEach((field) => { field.hidden = field.dataset.resultPeriodField !== period; });
    const invalid = period === 'range' && resultDateFrom?.value && resultDateTo?.value && resultDateFrom.value > resultDateTo.value;
    resultDateTo?.setCustomValidity(invalid ? 'La fecha hasta debe ser igual o posterior a la fecha desde.' : '');
    const error = document.querySelector('[data-result-period-error]');
    if (error) error.hidden = !invalid;
  }
  function resultScheduleMatches(match) {
    const selection = resultScheduleFilter?.value || 'all';
    const date = String(match.scheduled_on || '').slice(0, 10);
    if (selection === 'unscheduled') return !date;
    if (selection === 'today') return date === serverToday;
    if (selection === 'future') return Boolean(date) && date > serverToday;
    if (selection === 'overdue') {
      const status = String(match.estado || match.status || '').trim().toUpperCase();
      const closed = ['FINALIZADA', 'FINALIZADO', 'COMPLETED', 'FINISHED', 'CLOSED', 'CANCELLED', 'CANCELED', 'CANCELADA', 'CANCELADO'].includes(status);
      return Boolean(date) && date < serverToday && !closed;
    }
    return true;
  }
  function resultPeriodMatches(match) {
    const date = String(match.scheduled_on || '').slice(0, 10);
    const period = resultPeriod?.value || 'all';
    if (period === 'today') return date === serverToday;
    if (period === 'day') return !resultDate?.value || date === resultDate.value;
    if (period === 'month') return !resultMonth?.value || date.startsWith(resultMonth.value + '-');
    if (period === 'range') {
      const from = resultDateFrom?.value || '', to = resultDateTo?.value || '';
      return (!from && !to) || (Boolean(date) && (!from || date >= from) && (!to || date <= to));
    }
    return true;
  }
  syncResultPeriodFields();
  const resultSearch = document.querySelector('[data-result-search]');
  const resultQueue = document.querySelector('[data-result-queue]');
  const resultFilterCount = document.querySelector('[data-result-filter-count]');
  const resultClearFilters = document.querySelector('[data-result-clear-filters]');
  const resultKpiTotal = document.querySelector('[data-result-kpi-total]');
  const resultKpiPending = document.querySelector('[data-result-kpi-pending]');
  const resultKpiProgress = document.querySelector('[data-result-kpi-progress]');
  const resultKpiDone = document.querySelector('[data-result-kpi-done]');
  const resultKpiOverdue = document.querySelector('[data-result-kpi-overdue]');
  const resultKpiToday = document.querySelector('[data-result-kpi-today]');
  const resultKpiFuture = document.querySelector('[data-result-kpi-future]');
  const resultSaveButton = document.querySelector('[data-result-save-button]');
  const resultCaptureModalNode = document.querySelector('[data-result-capture-modal]');
  const resultCaptureModal = resultCaptureModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(resultCaptureModalNode) : null;
  const resultDuelReadModal = resultCaptureModalNode?.querySelector('[data-result-duel-read-modal]');
  const duelTimerConfirmNode = document.querySelector('[data-duel-timer-confirm-modal]');
  let pendingTimerDuel = 0;
  let activeResultCaptureMatchId = '';
  let resultLpState = null;
  let resultLpTimerTick = null;
  let resultStandardState = { display: '0', accumulator: null, operator: '', replace: true };
  let liveDuelRevision = 0;
  let liveDuelPollTimer = null;
  let liveDuelSyncTimer = null;
  let liveDuelBusy = false;
  let applyingLiveDuelState = false;

  function setResultSaveState(state) {
    const node = resultCaptureModalNode?.querySelector('[data-result-save-state]');
    if (!node) return;
    const states = {
      pending: ['bi-pencil-square', 'Cambios pendientes'],
      saving: ['bi-arrow-repeat', 'Guardando...'],
      saved: ['bi-cloud-check', 'Turnos guardados']
    };
    const [icon, label] = states[state] || states.saved;
    node.dataset.state = state;
    node.innerHTML = `<i class="bi ${icon}"></i> ${label}`;
  }

  function closeResultDuelRead() {
    if (resultDuelReadModal) resultDuelReadModal.hidden = true;
  }

  function completionLabel(value) {
    if (!value) return 'Sin fecha de cierre registrada';
    const date = new Date(String(value).replace(' ', 'T') + (/Z$|[+-]\d\d:\d\d$/.test(value) ? '' : '-05:00'));
    return Number.isNaN(date.getTime()) ? 'Sin fecha de cierre registrada' : date.toLocaleString('es-CO', { timeZone: 'America/Bogota', dateStyle: 'short', timeStyle: 'medium' });
  }

  function openResultDuelRead(duelNumber) {
    if (!resultDuelReadModal) return;
    const match = allMatchList().find((item) => String(item.id || '') === String(activeResultCaptureMatchId || ''));
    if (!match) return;
    const saved = getSavedResult(match);
    const details = parseJsonValue(match.duel_details || '', []);
    const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === Number(duelNumber)) || {}) : {};
    const winner = String(detail.winner || saved[Number(duelNumber) - 1] || '').toUpperCase();
    if (!winner) return;
    const dice = parseJsonValue(match.dice_roll || '', {});
    const value = (raw, fallback = 'No registrado') => raw === '' || raw === null || raw === undefined ? fallback : String(raw);
    const yesNo = (key) => Object.prototype.hasOwnProperty.call(detail, key) ? (detail[key] ? 'Si' : 'No') : 'No registrado';
    const deckName = (id, fallback) => ((data.allDecks || []).find((deck) => Number(deck.id) === Number(id)) || {}).name || fallback || 'No registrado';
    const playerName = (id) => {
      const player = (data.players || []).find((item) => Number(item.id) === Number(id));
      return player ? (player.alias || player.display_name || player.name) : 'Sin asignar';
    };
    const methodLabels = { life_points: 'Derrota por puntos de vida', surrender: 'Rendicion del oponente', time_limit: 'Tiempo finalizado', deck_out: 'Deck sin cartas', effect_win: 'Condicion de victoria por efecto' };
    const sideADeck = deckName(detail.deck_a_id, match.a);
    const sideBDeck = deckName(detail.deck_b_id, match.b);
    const winnerText = winner === 'A' ? `${match.team_a || 'Equipo A'} / ${sideADeck}` : winner === 'B' ? `${match.team_b || 'Equipo B'} / ${sideBDeck}` : 'Empate';
    const starts = value(detail.starts_player || (Number(duelNumber) === 1 ? match.starts_player || dice.starts : ''), 'No registrado').toUpperCase();
    const firstText = starts === 'A' ? `${match.team_a || 'Equipo A'} / ${sideADeck}` : starts === 'B' ? `${match.team_b || 'Equipo B'} / ${sideBDeck}` : 'No registrado';
    const secondText = starts === 'A' ? `${match.team_b || 'Equipo B'} / ${sideBDeck}` : starts === 'B' ? `${match.team_a || 'Equipo A'} / ${sideADeck}` : 'No registrado';
    const diceA = value(detail.dice_a || (Number(duelNumber) === 1 ? dice.a : ''), '-');
    const diceB = value(detail.dice_b || (Number(duelNumber) === 1 ? dice.b : ''), '-');
    const startA = Number(detail.lp_start_a || 8000);
    const startB = Number(detail.lp_start_b || 8000);
    const hasLpA = detail.lp_a !== '' && detail.lp_a !== null && detail.lp_a !== undefined;
    const hasLpB = detail.lp_b !== '' && detail.lp_b !== null && detail.lp_b !== undefined;
    const history = Array.isArray(detail.lp_history) ? detail.lp_history : [];
    const turnHistory = Array.isArray(detail.turn_history) ? detail.turn_history : [];
    const title = resultDuelReadModal.querySelector('[data-result-duel-read-title]');
    const subtitle = resultDuelReadModal.querySelector('[data-result-duel-read-subtitle]');
    const body = resultDuelReadModal.querySelector('[data-result-duel-read-body]');
    if (title) title.textContent = `D${duelNumber} | ${winnerText}`;
    if (subtitle) subtitle.textContent = `R${match.ronda || '-'} M${match.mesa || '-'} | ${match.competition_name || match.division || 'Combate'} | Solo lectura`;
    const metric = (label, metricValue, accent = '') => `<div class="result-duel-read-metric${accent ? ` ${accent}` : ''}"><span>${escapeHtml(label)}</span><strong>${escapeHtml(String(metricValue))}</strong></div>`;
    const historyHtml = history.length ? history.map((item) => {
      const side = String(item.side || '').toUpperCase();
      const owner = item.name || (side === 'A' ? match.team_a : match.team_b) || `Equipo ${side}`;
      return `<tr><td data-label="Equipo">${escapeHtml(owner)}</td><td data-label="Operacion">${escapeHtml(item.action || item.operation || 'Cambio')}</td><td data-label="Antes">${formatResultLp(item.before)} → ${formatResultLp(item.after)} LP</td></tr>`;
    }).join('') : '<tr><td colspan="4"><div class="empty-state">Este duelo no tiene movimientos individuales de LP registrados.</div></td></tr>';
    const turnHistoryHtml = turnHistory.length ? turnHistory.map((item) => {
      const side = String(item.side || '').toUpperCase();
      const owner = side === 'A' ? match.team_a || 'Equipo A' : match.team_b || 'Equipo B';
      const endedAt = item.ended_at ? new Date(item.ended_at) : null;
      const recorded = endedAt && !Number.isNaN(endedAt.getTime())
        ? endedAt.toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'medium' })
        : 'Sin fecha';
      return `<tr><td data-label="Turno">${escapeHtml(item.turn || '-')}</td><td data-label="Equipo">${escapeHtml(owner)} (${escapeHtml(side)})</td><td data-label="Tiempo">${formatResultLpTimer(item.elapsed_seconds || 0)}</td><td data-label="Registrado">${escapeHtml(recorded)}</td></tr>`;
    }).join('') : '<tr><td colspan="4"><div class="empty-state">Este duelo no tiene cierres de turno registrados.</div></td></tr>';
    if (body) body.innerHTML = `
      <section class="result-duel-read-result"><span>Resultado final</span><strong>${escapeHtml(winnerText)}</strong><small>${escapeHtml(methodLabels[detail.defeat_method] || value(detail.defeat_method, 'Metodo no registrado'))}</small></section>
      <section class="result-duel-read-grid">
        ${metric('Finalizacion de esta partida', completionLabel(detail.completed_at), 'wide')}
        ${metric('Dado Equipo A', diceA)}${metric('Dado Equipo B', diceB)}
        ${metric('Eligio el turno', value(detail.turn_chosen_by || (Number(duelNumber) === 1 ? match.turn_chosen_by || dice.chosen_by || dice.winner : '')))}
        ${metric('Decision', value(detail.turn_decision || (Number(duelNumber) === 1 ? match.turn_decision || dice.choice : '')))}
        ${metric('Empieza primero', firstText, 'wide')}${metric('Empieza segundo', secondText, 'wide')}
        ${metric('Numero de turnos', detail.turn_count ? detail.turn_count : 'No registrado')}
        ${metric('Duracion', detail.duration_seconds ? formatResultLpTimer(detail.duration_seconds) : 'No registrada')}
      </section>
      <section class="result-duel-read-sides">
        <article><header><span>Equipo A</span><strong>${escapeHtml(match.team_a || 'Equipo A')}</strong></header><dl><div><dt>Deck participante</dt><dd>${escapeHtml(sideADeck)}</dd></div><div><dt>Jugador</dt><dd>${escapeHtml(playerName(detail.player_a_id))}</dd></div><div><dt>LP inicial</dt><dd>${formatResultLp(startA)}</dd></div><div><dt>LP final</dt><dd>${hasLpA ? formatResultLp(detail.lp_a) : 'No registrado'}</dd></div><div><dt>Mano brick</dt><dd>${yesNo('brick_a')}</dd></div></dl></article>
        <article><header><span>Equipo B</span><strong>${escapeHtml(match.team_b || 'Equipo B')}</strong></header><dl><div><dt>Deck participante</dt><dd>${escapeHtml(sideBDeck)}</dd></div><div><dt>Jugador</dt><dd>${escapeHtml(playerName(detail.player_b_id))}</dd></div><div><dt>LP inicial</dt><dd>${formatResultLp(startB)}</dd></div><div><dt>LP final</dt><dd>${hasLpB ? formatResultLp(detail.lp_b) : 'No registrado'}</dd></div><div><dt>Mano brick</dt><dd>${yesNo('brick_b')}</dd></div></dl></article>
      </section>
      <section class="result-duel-read-notes"><span>Notas del duelo</span><p>${escapeHtml(value(detail.notes, 'Sin notas registradas.'))}</p></section>
      <section class="result-duel-read-history"><div><h3>Historial de turnos</h3><span>${turnHistory.length} turnos registrados</span></div><div class="table-responsive"><table class="table app-table compact mobile-card-table"><thead><tr><th>Turno</th><th>Equipo</th><th>Tiempo acumulado</th><th>Registrado</th></tr></thead><tbody>${turnHistoryHtml}</tbody></table></div></section>
      <section class="result-duel-read-history"><div><h3>Historial de puntos de vida</h3><span>${history.length} movimientos registrados</span></div><div class="table-responsive"><table class="table app-table compact mobile-card-table"><thead><tr><th>Equipo</th><th>Operacion</th><th>Antes</th><th>Despues</th></tr></thead><tbody>${historyHtml}</tbody></table></div></section>`;
    resultDuelReadModal.hidden = false;
    resultDuelReadModal.querySelector('.result-duel-read-dialog')?.scrollTo({ top: 0 });
  }

  resultDuelReadModal?.querySelectorAll('[data-result-duel-read-close]').forEach((button) => button.addEventListener('click', closeResultDuelRead));

  function resultBaseMatches() {
    const division = resultDivision ? resultDivision.value : '';
    const matches = division ? allMatches(division) : allMatchList();
    return matches.filter((match) => match.id);
  }

  function syncResultEventOptions() {
    if (!resultEventFilter) return;
    const current = resultEventFilter.value;
    const phase = resultPhase?.value || '';
    const phaseMatches = (value) => phase === 'events'
      ? ['event', 'events', 'special', 'eternal', 'tiebreaker', 'final'].includes(String(value || '').toLowerCase())
      : String(value || '').toLowerCase() === phase;
    const matches = resultBaseMatches();
    const events = (data.competitions || []).filter(event =>
      ['active', 'activo', 'en_curso', 'in_progress'].includes(normalizeStatusKey(event.status || ''))
      && (!phase || phaseMatches(event.type) || matches.some(match => String(match.competition_id) === String(event.id) && phaseMatches(match.phase)))
    ).sort((a, b) => String(a.name).localeCompare(String(b.name), 'es'));
    const allLabel = ({league:'Todas las ligas',cup:'Todas las copas',qualifier:'Todos los clasificatorios',world:'Todos los mundiales',friendly:'Todos los amistosos'})[phase] || 'Todos los eventos';
    resultEventFilter.replaceChildren(new Option(allLabel, ''), ...events.map(event => new Option(window.StationXLabels?.text(event.name) || String(event.name).replace(/^(.+) League$/, 'Liga $1'), String(event.id))));
    resultEventFilter.value = events.some(event => String(event.id) === current) ? current : '';
    resultEventFilter.title = events.length ? 'Eventos activos de la fase seleccionada' : 'No hay eventos activos para esta fase. La opción Todos permite consultar el historial.';
  }

  function resultLeagueDivisionLabel(division, event, includeLeague = false) {
    const originalName = event?.name || '';
    let name = String(division?.name || 'Sin división registrada');
    if (originalName && name.startsWith(originalName)) name = name.slice(originalName.length).replace(/^[\s\u2014\u2013-]+/u, '');
    const leagueName = window.StationXLabels?.text(originalName) || originalName.replace(/^(.+) League$/, 'Liga $1');
    return includeLeague && leagueName ? `${leagueName} — ${name}` : name;
  }

  function syncResultLeagueDivisionOptions() {
    if (!resultLeagueDivisionFilter) return;
    const current = resultLeagueDivisionFilter.value;
    const phase = resultPhase?.value || '';
    const eventId = resultEventFilter?.value || '';
    const selectedEvent = (data.competitions || []).find(e => String(e.id) === eventId);
    const applicable = (!phase || phase === 'league') && (!selectedEvent || selectedEvent.type === 'league');
    const leagues = (data.competitions || []).filter(e => e.type === 'league' && ['active', 'activo', 'en_curso', 'in_progress'].includes(normalizeStatusKey(e.status || '')) && (!eventId || String(e.id) === eventId));
    const leagueById = new Map(leagues.map(e => [String(e.id), e]));
    const divisions = applicable ? Object.entries(data.divisions || {}).filter(([,d]) => Number(d.id) > 0 && d.active !== false && String(d.active) !== '0' && leagueById.has(String(d.league_competition_id))).sort((a,b) => Number(a[1].tier_order || 0) - Number(b[1].tier_order || 0) || String(a[0]).localeCompare(String(b[0]))) : [];
    resultLeagueDivisionFilter.replaceChildren(new Option(applicable ? 'Todas las divisiones' : 'No aplica a esta fase', ''), ...divisions.map(([key,d]) => new Option(resultLeagueDivisionLabel(d, leagueById.get(String(d.league_competition_id)), !eventId), key)));
    resultLeagueDivisionFilter.value = divisions.some(([key]) => key === current) ? current : '';
    resultLeagueDivisionFilter.disabled = !applicable;
  }

  function resultLeagueDivisionMatches(match) {
    const key = resultLeagueDivisionFilter?.value || '';
    if (!key) return true;
    const division = data.divisions?.[key];
    return Boolean(division) && String(match.competition_id) === String(division.league_competition_id)
      && (match.division === key || (Number(match.division_id) > 0 && Number(match.division_id) === Number(division.id)));
  }

  function resultMatchEventContext(match) {
    const event = (data.competitions || []).find(e => String(e.id) === String(match.competition_id));
    const league = event?.type === 'league' || match.phase === 'league';
    const name = event?.name || match.competition_name || ({league:'Liga',cup:'Copa',qualifier:'Clasificatorio',world:'Mundial',friendly:'Amistoso',events:'Evento'})[match.phase] || 'Evento';
    const label = window.StationXLabels?.text(name) || name.replace(/^(.+) League$/, 'Liga $1');
    const division = data.divisions?.[match.division] || Object.values(data.divisions || {}).find(d => Number(match.division_id) > 0 && Number(d.id) === Number(match.division_id));
    const ownDivision = league && division && String(division.league_competition_id) === String(match.competition_id);
    return `<span class="tag">${escapeHtml(label)}</span>${league ? `<span class="result-division-name">División: ${escapeHtml(ownDivision ? resultLeagueDivisionLabel(division, event) : 'Sin división registrada')}</span>` : ''}`;
  }

  function filteredResultMatches() {
    const phase = resultPhase ? resultPhase.value : '';
    const status = resultStatus ? resultStatus.value : '';
    const round = resultRoundFilter ? resultRoundFilter.value : '';
    const query = normalize(resultSearch ? resultSearch.value : '');
    const phaseMatches = (match) => {
      if (!phase) return true;
      const matchPhase = String(match.phase || '').toLowerCase();
      if (phase === 'events') {
        return ['event', 'events', 'special', 'eternal', 'tiebreaker', 'final'].includes(matchPhase);
      }
      return matchPhase === phase;
    };
    const statusMatches = (match) => {
      if (!status) return true;
      const matchStatus = String(match.estado || match.status || 'PENDIENTE').trim().toUpperCase();
      if (status === 'EN CURSO') return ['EN CURSO', 'REQUIERE D5'].includes(matchStatus);
      return matchStatus === status;
    };
    const filtered = resultBaseMatches().filter((match) => {
      const haystack = normalize(`${match.a} ${match.b} ${match.division} ${match.phase} ${match.competition_name} ${match.competition_status} ronda ${match.ronda} mesa ${match.mesa} ${match.scheduled_on} ${match.starts_at} ${match.estado} ${match.score} ${matchLabelWithTeam(match.a)} ${matchLabelWithTeam(match.b)}`);
      return phaseMatches(match)
        && statusMatches(match)
        && resultLeagueDivisionMatches(match)
        && (!resultEventFilter?.value || String(match.competition_id) === resultEventFilter.value)
        && (!round || String(match.ronda) === String(round))
        && resultPeriodMatches(match)
        && resultScheduleMatches(match)
        && (!query || haystack.includes(query));
    });
    const direction = resultSort?.value === 'asc' ? 1 : -1;
    return filtered.sort((a, b) => {
      const aHasDate = Boolean(a.scheduled_on);
      const bHasDate = Boolean(b.scheduled_on);
      if (aHasDate !== bHasDate) return aHasDate ? -1 : 1;
      const aDate = `${a.scheduled_on || ''}T${a.starts_at || '00:00:00'}`;
      const bDate = `${b.scheduled_on || ''}T${b.starts_at || '00:00:00'}`;
      return direction * aDate.localeCompare(bDate)
        || direction * (Number(a.ronda || 0) - Number(b.ronda || 0))
        || direction * (Number(a.mesa || 0) - Number(b.mesa || 0))
        || direction * (Number(a.id || 0) - Number(b.id || 0));
    });
  }

  function selectedMatch() {
    const matches = resultBaseMatches();
    const selectedId = resultMatch ? resultMatch.value : '';
    return matches.find((match) => String(match.id) === String(selectedId)) || filteredResultMatches()[0] || matches[0];
  }

  function syncSequentialDuelControls(selects, savedValues) {
    const values = Array.from({ length: 5 }, (_, index) => savedValues[index] || '');
    let firstOpen = values.findIndex((value) => !value);
    if (firstOpen < 0) firstOpen = values.length;
    selects.forEach((select, index) => {
      const enabled = index <= firstOpen;
      select.disabled = !enabled;
      select.title = enabled ? '' : 'Guarda el duelo anterior para habilitar este.';
      if (!enabled) select.value = '';
    });
  }

  function loadMatchOptions() {
    const matches = filteredResultMatches();
    if (resultMatch) {
      resultMatch.innerHTML = matches.map((match) => `<option value="${match.id}">${(match.phase || '').toUpperCase()} R${match.ronda} M${match.mesa}: ${matchVersusLabel(match)}</option>`).join('');
    }
    renderResultDashboard(matches);
    loadResult();
  }

  function loadResult() {
    const match = selectedMatch();
    if (!match) {
      duelSelects.forEach((select) => { select.value = ''; });
      syncSequentialDuelControls(duelSelects, []);
      renderResultDashboard([]);
      return;
    }
    if (resultMatchId) resultMatchId.value = match.id || '';
    resultA.textContent = matchLabelWithTeam(match.a);
    resultB.textContent = matchLabelWithTeam(match.b);
    const saved = getSavedResult(match);
    duelSelects.forEach((select, index) => { select.value = saved[index] || ''; });
    syncSequentialDuelControls(duelSelects, saved);
    renderResult();
  }

  function parseJsonValue(value, fallback) {
    try {
      return value ? JSON.parse(value) : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function setResultCaptureText(selector, value) {
    const node = resultCaptureModalNode ? resultCaptureModalNode.querySelector(selector) : null;
    if (node) node.textContent = value || '-';
  }

  function setResultCaptureLogo(selector, logoUrl, fallback, altText) {
    const node = resultCaptureModalNode ? resultCaptureModalNode.querySelector(selector) : null;
    if (!node) return;
    node.innerHTML = '';
    node.classList.toggle('has-image', Boolean(logoUrl));
    if (logoUrl) {
      const image = document.createElement('img');
      image.src = logoUrl;
      image.alt = altText || fallback || 'Equipo';
      node.appendChild(image);
      return;
    }
    node.textContent = fallback || '?';
  }

  let resultDice3d = null;

  function createDiceFaceTexture(value, tint = '#ffffff') {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 256, 256);
    gradient.addColorStop(0, tint);
    gradient.addColorStop(0.48, '#f8fbff');
    gradient.addColorStop(1, '#b8c2d0');
    ctx.fillStyle = gradient;
    ctx.roundRect(10, 10, 236, 236, 34);
    ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,.8)';
    ctx.lineWidth = 6;
    ctx.stroke();
    const pipMap = {
      1: [[128, 128]],
      2: [[82, 82], [174, 174]],
      3: [[78, 78], [128, 128], [178, 178]],
      4: [[78, 78], [178, 78], [78, 178], [178, 178]],
      5: [[78, 78], [178, 78], [128, 128], [78, 178], [178, 178]],
      6: [[78, 68], [178, 68], [78, 128], [178, 128], [78, 188], [178, 188]]
    };
    ctx.fillStyle = '#071126';
    (pipMap[value] || pipMap[1]).forEach(([x, y]) => {
      ctx.beginPath();
      ctx.arc(x, y, 18, 0, Math.PI * 2);
      ctx.fill();
    });
    const texture = new window.THREE.CanvasTexture(canvas);
    if (window.THREE.SRGBColorSpace) {
      texture.colorSpace = window.THREE.SRGBColorSpace;
    } else if (window.THREE.sRGBEncoding) {
      texture.encoding = window.THREE.sRGBEncoding;
    }
    return texture;
  }

  function createResultDie3d(tint) {
    const THREE = window.THREE;
    const geometry = new THREE.BoxGeometry(1.18, 1.18, 1.18, 8, 8, 8);
    const materials = [3, 4, 2, 5, 1, 6].map((value) => new THREE.MeshStandardMaterial({
      map: createDiceFaceTexture(value, tint),
      roughness: 0.38,
      metalness: 0.08
    }));
    const mesh = new THREE.Mesh(geometry, materials);
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
  }

  function resultDiceRotation(value, side = 'a') {
    const base = side === 'a' ? 0.24 : -0.24;
    const map = {
      1: [0.16, base, 0.08],
      2: [-Math.PI / 2 + 0.14, base, 0.08],
      3: [0.16, -Math.PI / 2 + base, 0.08],
      4: [0.16, Math.PI / 2 + base, 0.08],
      5: [Math.PI / 2 + 0.14, base, 0.08],
      6: [0.16, Math.PI + base, 0.08]
    };
    return map[Number(value)] || map[1];
  }

  function ensureResultDice3d() {
    if (resultDice3d || !resultCaptureModalNode || !window.THREE) return resultDice3d;
    const canvas = resultCaptureModalNode.querySelector('[data-result-dice-canvas]');
    const stage = resultCaptureModalNode.querySelector('[data-result-dice-stage]');
    if (!canvas || !stage) return null;
    const THREE = window.THREE;
    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, 2.5, 0.1, 100);
    camera.position.set(0, 1.2, 5.4);
    camera.lookAt(0, 0, 0);
    const ambient = new THREE.AmbientLight(0xffffff, 1.2);
    scene.add(ambient);
    const key = new THREE.DirectionalLight(0xffffff, 2.1);
    key.position.set(3, 5, 4);
    scene.add(key);
    const blue = new THREE.PointLight(0x00c6ff, 1.5, 8);
    blue.position.set(-2.8, 0.8, 2.2);
    scene.add(blue);
    const red = new THREE.PointLight(0xff4b67, 1.3, 8);
    red.position.set(2.8, 0.8, 2.2);
    scene.add(red);
    const dieA = createResultDie3d('#dff8ff');
    const dieB = createResultDie3d('#fff1f3');
    dieA.position.x = -1.15;
    dieB.position.x = 1.15;
    scene.add(dieA, dieB);
    const resize = () => {
      const rect = stage.getBoundingClientRect();
      const width = Math.max(260, Math.floor(rect.width));
      const height = Math.max(120, Math.floor(rect.height));
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
    };
    resultDice3d = { renderer, scene, camera, dieA, dieB, stage, resize, targetA: 1, targetB: 1, rollingUntil: 0 };
    stage.classList.add('has-real-3d');
    resize();
    window.addEventListener('resize', resize);
    const render = () => {
      if (!resultDice3d) return;
      const now = performance.now();
      if (now < resultDice3d.rollingUntil) {
        resultDice3d.dieA.rotation.x += 0.17;
        resultDice3d.dieA.rotation.y += 0.23;
        resultDice3d.dieA.rotation.z += 0.11;
        resultDice3d.dieB.rotation.x += 0.19;
        resultDice3d.dieB.rotation.y -= 0.21;
        resultDice3d.dieB.rotation.z -= 0.13;
      }
      resultDice3d.renderer.render(resultDice3d.scene, resultDice3d.camera);
      requestAnimationFrame(render);
    };
    render();
    return resultDice3d;
  }

  function setResultDie3dValue(side, value, rolling = false) {
    const dice3d = ensureResultDice3d();
    if (!dice3d) return;
    const die = side === 'a' ? dice3d.dieA : dice3d.dieB;
    if (rolling) {
      dice3d.rollingUntil = performance.now() + 1050;
      return;
    }
    const [x, y, z] = resultDiceRotation(value, side);
    die.rotation.set(x, y, z);
    dice3d.renderer.render(dice3d.scene, dice3d.camera);
  }

  function updateResultDie(selector, value, rolling = false) {
    const die = resultCaptureModalNode ? resultCaptureModalNode.querySelector(selector) : null;
    if (!die) return;
    die.dataset.value = String(value || 1);
    die.classList.toggle('is-rolling', rolling);
    setResultDie3dValue(selector.includes('die-b') ? 'b' : 'a', value || 1, rolling);
  }

  function rollSixSidedDie() {
    if (window.crypto && window.crypto.getRandomValues) {
      const value = new Uint32Array(1);
      window.crypto.getRandomValues(value);
      return (value[0] % 6) + 1;
    }
    return Math.floor(Math.random() * 6) + 1;
  }

  function rollOpeningDice() {
    const attempts = [];
    let a = 0;
    let b = 0;
    do {
      a = rollSixSidedDie();
      b = rollSixSidedDie();
      attempts.push({ a, b });
    } while (a === b && attempts.length < 20);
    if (a === b) {
      b = b === 6 ? 1 : b + 1;
      attempts.push({ a, b, forced_tiebreak: true });
    }
    return { a, b, winner: a > b ? 'A' : 'B', attempts };
  }

  function selectedCaptureMatch() {
    return allMatchList().find((match) => String(match.id || '') === String(activeResultCaptureMatchId || '')) || null;
  }

  function oppositePlayer(player) {
    return player === 'A' ? 'B' : player === 'B' ? 'A' : '';
  }

  function inferStartsPlayer(startsPlayer, turnHistory = []) {
    const explicit = String(startsPlayer || '').toUpperCase();
    if (['A', 'B'].includes(explicit)) return explicit;
    const firstTurn = (Array.isArray(turnHistory) ? turnHistory : [])
      .find((entry) => Number(entry?.turn || 0) === 1 && ['A', 'B'].includes(String(entry?.side || '').toUpperCase()));
    return firstTurn ? String(firstTurn.side).toUpperCase() : '';
  }

  function normalizeTurnChoice(choice) {
    return choice === 'second' || choice === 'segundo' ? 'segundo' : choice === 'first' || choice === 'primero' ? 'primero' : '';
  }

  function computeStartsPlayer(chosenBy, choice) {
    const normalized = normalizeTurnChoice(choice);
    if (!['A', 'B'].includes(chosenBy) || !normalized) return '';
    return normalized === 'primero' ? chosenBy : oppositePlayer(chosenBy);
  }

  function turnText(chosenBy, choice, starts) {
    if (!chosenBy || !choice || !starts) return '';
    return `${chosenBy} eligio ${choice}. Empieza ${starts}.`;
  }

  function setResultArenaArt(selector, url) {
    const node = resultCaptureModalNode?.querySelector(selector);
    if (!node) return;
    const cleanUrl = String(url || '').trim();
    if (cleanUrl) {
      node.style.setProperty('--result-team-art', `url("${cleanUrl.replace(/"/g, '%22')}")`);
      node.classList.add('has-art');
    } else {
      node.style.removeProperty('--result-team-art');
      node.classList.remove('has-art');
    }
  }

  function captureDuelDetails() {
    if (!resultCaptureModalNode) return [];
    return Array.from({ length: 5 }, (_, index) => {
      const n = index + 1;
      const turnChoice = parseJsonValue(resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`)?.value || '', {});
      const diceA = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${n}"]`)?.dataset.value || '';
      const diceB = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${n}"]`)?.dataset.value || '';
      const duelState = duelTimerState.get(n) || {};
      let lpState = {};
      try { lpState = JSON.parse(localStorage.getItem(resultLpStorageKey(n)) || '{}'); } catch (error) { lpState = {}; }
      return {
        duel: n,
        winner: resultCaptureModalNode.querySelector(`[data-result-capture-duel="${n}"]`)?.value || '',
        defeat_method: resultCaptureModalNode.querySelector(`[data-result-capture-method="${n}"]`)?.value || '',
        lp_a: resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${n}"]`)?.value || '',
        lp_b: resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${n}"]`)?.value || '',
        lp_start_a: Number(lpState.start_a || 8000),
        lp_start_b: Number(lpState.start_b || 8000),
        lp_history: Array.isArray(lpState.history) ? lpState.history : [],
        deck_a_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-deck-a="${n}"]`)?.value || 0),
        deck_b_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-deck-b="${n}"]`)?.value || 0),
        player_a_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-player-a="${n}"]`)?.value || 0),
        player_b_id: Number(resultCaptureModalNode.querySelector(`[data-result-capture-player-b="${n}"]`)?.value || 0),
        turn_count: Number(resultCaptureModalNode.querySelector(`[data-result-capture-turns="${n}"]`)?.value || 0),
        brick_a: Boolean(resultCaptureModalNode.querySelector(`[data-result-capture-brick-a="${n}"]`)?.checked),
        brick_b: Boolean(resultCaptureModalNode.querySelector(`[data-result-capture-brick-b="${n}"]`)?.checked),
        duration_seconds: duelTimerElapsed(Object.keys(duelState).length ? duelState : { baseElapsed: Number(resultCaptureModalNode.querySelector(`[data-result-capture-duration="${n}"]`)?.value || 0) }),
        timer_running: Boolean(duelState.running),
        timer_started_at: duelState.startedAt || null,
        timer_updated_at: duelState.updatedAt || Date.now(),
        duel_started: Boolean(duelState.duelStarted),
        duel_started_at: duelState.duelStartedAt || null,
        data_origin: resultCaptureModalNode.querySelector(`[data-result-capture-origin="${n}"]`)?.value || (duelState.duelStarted ? 'captured' : ''),
        timer_mode: duelState.timerMode || '',
        turn_history: Array.isArray(duelState.turnHistory) ? duelState.turnHistory : [],
        notes: resultCaptureModalNode.querySelector(`[data-result-capture-duel-note="${n}"]`)?.value || '',
        dice_a: diceA,
        dice_b: diceB,
        dice_winner: turnChoice.dice_winner || turnChoice.chosen_by || '',
        turn_chosen_by: turnChoice.chosen_by || '',
        turn_decision: turnChoice.choice || '',
        starts_player: turnChoice.starts || '',
        manual_correction_at_ms: Number((parseJsonValue(selectedCaptureMatch()?.duel_details||'[]',[]).find(d=>Number(d.duel)===n)||{}).manual_correction_at_ms||0),
        turn_reason: turnChoice.reason || '',
        surrendered_by: duelState.surrenderedBy || '',
        surrendered_at: duelState.surrenderedAt || ''
      };
    });
  }

  const duelTimerState = new Map();

  function currentLiveDuelNumber() {
    const match = selectedCaptureMatch();
    const persistedCount = Array.isArray(match?.duels) ? match.duels.filter((value) => ['A', 'B', 'E'].includes(String(value || ''))).length : 0;
    return Math.max(1, Math.min(5, persistedCount + 1));
  }

  function liveDuelUrl() {
    return `./?api=duel_session&match_id=${encodeURIComponent(activeResultCaptureMatchId || '')}`;
  }

  function setLiveDuelStatus(message, mode = '') {
    const status = resultCaptureModalNode?.querySelector('[data-result-live-sync]');
    if (!status) return;
    status.textContent = message;
    status.dataset.state = mode;
  }

  function judgeLiveDuelPayload() {
    const duel = currentLiveDuelNumber();
    const detail = captureDuelDetails().find((item) => Number(item.duel) === duel) || {};
    const runtime = duelTimerState.get(duel) || {};
    const starts = inferStartsPlayer(detail.starts_player, detail.turn_history);
    const turns = Math.max(0, Number(detail.turn_count || 0));
    const activeSide = starts ? (turns % 2 === 0 ? starts : oppositePlayer(starts)) : '';
    return {
      duel_number: duel,
      correction_at_ms: Math.max(0, ...parseJsonValue(selectedCaptureMatch()?.duel_details || '[]', []).map(d => Number(d.manual_correction_at_ms || 0))),
      status: runtime.duelStarted ? (runtime.timerMode === 'countdown' && !runtime.running ? 'paused' : 'running') : (starts ? 'ready' : 'setup'),
      starts_player: starts,
      active_side: activeSide,
      turn_count: turns,
      turn_history: Array.isArray(detail.turn_history) ? detail.turn_history : [],
      lp_a: detail.lp_a === '' ? 8000 : Number(detail.lp_a || 0),
      lp_b: detail.lp_b === '' ? 8000 : Number(detail.lp_b || 0),
      lp_start_a: Number(detail.lp_start_a || 8000),
      lp_start_b: Number(detail.lp_start_b || 8000),
      lp_history: Array.isArray(detail.lp_history) ? detail.lp_history : [],
      timer_mode: runtime.timerMode || '',
      timer_running: Boolean(runtime.running),
      timer_started_at: Number(runtime.startedAt || 0),
      elapsed_seconds: runtime.running ? Math.max(0, Number(runtime.baseElapsed || 0)) : duelTimerElapsed(runtime),
      dice_a: Number(detail.dice_a || 0),
      dice_b: Number(detail.dice_b || 0),
      dice_winner: detail.dice_winner || detail.turn_chosen_by || '',
      turn_decision: detail.turn_decision || '',
      surrendered_by: runtime.surrenderedBy || '',
      surrendered_at: runtime.surrenderedAt || ''
    };
  }

  function applyLiveDuelState(state) {
    if (!state || !resultCaptureModalNode) return;
    const loadedCorrection=Math.max(0,...parseJsonValue(selectedCaptureMatch()?.duel_details||'[]',[]).map(d=>Number(d.manual_correction_at_ms||0)));
    if(Number(state.correction_at_ms||0)>loadedCorrection){localStorage.setItem('dml_result_capture_match_id',String(activeResultCaptureMatchId));location.reload();return;}
    const duel = Number(state.duel_number || 0);
    if (!duel || duel !== currentLiveDuelNumber()) return;
    applyingLiveDuelState = true;
    try {
      const lpA = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${duel}"]`);
      const lpB = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${duel}"]`);
      const localTurnInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${duel}"]`);
      const localLpA = lpA?.value === '' ? 8000 : Number(lpA?.value || 0);
      const localLpB = lpB?.value === '' ? 8000 : Number(lpB?.value || 0);
      const localTurnCount = Math.max(0, Number(localTurnInput?.value || 0));
      const remoteChanged = Number(state.turn_count || 0) > localTurnCount
        || Number(state.lp_a ?? 8000) !== localLpA
        || Number(state.lp_b ?? 8000) !== localLpB;
      if (lpA) lpA.value = String(Math.max(0, Number(state.lp_a ?? 8000)));
      if (lpB) lpB.value = String(Math.max(0, Number(state.lp_b ?? 8000)));
      const runtime = duelTimerState.get(duel) || {};
      const localHistory = Array.isArray(runtime.turnHistory) ? runtime.turnHistory : [];
      const remoteHistory = Array.isArray(state.turn_history) ? state.turn_history : [];
      if (remoteHistory.length >= localHistory.length) runtime.turnHistory = remoteHistory;
      const recoveredStarts = inferStartsPlayer(state.starts_player, remoteHistory);
      const turnChoiceNode = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`);
      const turnChoice = parseJsonValue(turnChoiceNode?.value || '', {});
      if (turnChoiceNode && recoveredStarts && !turnChoice.starts) {
        turnChoiceNode.value = JSON.stringify({ ...turnChoice, starts: recoveredStarts, reason: turnChoice.reason || 'turn_history' });
      }
      runtime.turnBaseline = Math.max(Number(runtime.turnBaseline || 0), Number(state.turn_count || 0) - remoteHistory.length);
      runtime.baseElapsed = Math.max(Number(runtime.baseElapsed || 0), Number(state.elapsed_seconds || 0));
      runtime.running = Boolean(state.timer_running);
      runtime.startedAt = Number(state.timer_started_at || 0) || null;
      runtime.timerMode = state.timer_mode || runtime.timerMode || '';
      if (runtime.timerMode !== 'countdown') { runtime.running = false; runtime.startedAt = null; }
      runtime.surrenderedBy = ['A', 'B'].includes(String(state.surrendered_by || '')) ? String(state.surrendered_by) : '';
      runtime.surrenderedAt = state.surrendered_at || runtime.surrenderedAt || '';
      const remoteStarted = ['running', 'paused', 'completed'].includes(String(state.status || ''));
      const localStarts = inferStartsPlayer(turnChoice.starts, runtime.turnHistory);
      runtime.duelStarted = remoteStarted || (Boolean(runtime.duelStarted) && Boolean(localStarts));
      const origin = resultCaptureModalNode.querySelector(`[data-result-capture-origin="${duel}"]`);
      if (origin && remoteStarted) origin.value = 'captured';
      if (!runtime.duelStarted) {
        runtime.running = false;
        runtime.startedAt = null;
        runtime.timerMode = '';
        runtime.duelStartedAt = null;
      }
      duelTimerState.set(duel, runtime);
      if (runtime.surrenderedBy) {
        const winner = runtime.surrenderedBy === 'A' ? 'B' : 'A';
        const winnerControl = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${duel}"]`);
        const methodControl = resultCaptureModalNode.querySelector(`[data-result-capture-method="${duel}"]`);
        if (winnerControl) winnerControl.value = winner;
        if (methodControl) methodControl.value = 'surrender';
        runtime.running = false;
        runtime.startedAt = null;
      }
      persistDuelTimer(duel, true);
      if (localTurnInput) localTurnInput.value = String(Math.max(Number(localTurnInput.value || 0), Number(state.turn_count || 0)));
      localStorage.setItem(resultLpStorageKey(duel), JSON.stringify({
        a: Number(state.lp_a ?? 8000), b: Number(state.lp_b ?? 8000),
        start_a: Number(state.lp_start_a || 8000), start_b: Number(state.lp_start_b || 8000),
        history: Array.isArray(state.lp_history) ? state.lp_history : [], running: false
      }));
      if (remoteChanged) setResultSaveState('pending');
      syncResultCaptureSummary();
    } finally {
      applyingLiveDuelState = false;
    }
  }

  async function requestLiveDuel(operation = '', payload = {}) {
    if (!activeResultCaptureMatchId) return null;
    const requestedMatchId = String(activeResultCaptureMatchId);
    const options = { cache: 'no-store', headers: { Accept: 'application/json' } };
    if (operation) {
      const body = new FormData();
      body.set('match_id', activeResultCaptureMatchId);
      body.set('actor', 'judge');
      body.set('operation', operation);
      body.set('revision', String(liveDuelRevision || 0));
      body.set('payload', JSON.stringify(payload));
      options.method = 'POST';
      options.body = body;
    }
    const response = await fetch(liveDuelUrl(), options);
    const data = await response.json();
    if (String(activeResultCaptureMatchId) !== requestedMatchId) return null;
    if (response.status === 409 && data.state) {
      liveDuelRevision = Number(data.revision || liveDuelRevision);
      applyLiveDuelState(data.state);
      return data;
    }
    if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo sincronizar el duelo.');
    liveDuelRevision = Number(data.revision || liveDuelRevision);
    return data;
  }

  async function syncJudgeLiveDuel(force = false) {
    if (!activeResultCaptureMatchId || applyingLiveDuelState) return;
    if (liveDuelBusy) {
      if (force) window.setTimeout(() => syncJudgeLiveDuel(true), 260);
      return;
    }
    liveDuelBusy = true;
    try {
      if (force) {
        const saved = await requestLiveDuel('sync', judgeLiveDuelPayload());
        if (saved?.state) applyLiveDuelState(saved.state);
      } else {
        const current = await requestLiveDuel();
        if (current?.state) applyLiveDuelState(current.state);
      }
      setLiveDuelStatus(`Sincronizado · v${liveDuelRevision}`, 'online');
    } catch (error) {
      setLiveDuelStatus('Sin conexión con las pantallas', 'error');
    } finally {
      liveDuelBusy = false;
    }
  }

  function scheduleJudgeLiveSync() {
    if (applyingLiveDuelState || !activeResultCaptureMatchId) return;
    window.clearTimeout(liveDuelSyncTimer);
    liveDuelSyncTimer = window.setTimeout(() => syncJudgeLiveDuel(true), 180);
  }
  function duelTimerStorageKey(duel) {
    return `dml_duel_timer_v2_${activeResultCaptureMatchId || 'match'}_${duel}`;
  }
  function duelTimerElapsed(state) {
    const base = Math.max(0, Number(state?.baseElapsed || 0));
    if (!state?.running || !state.startedAt) return Math.min(1200, Math.floor(base));
    return Math.min(1200, Math.floor(base + ((Date.now() - Number(state.startedAt)) / 1000)));
  }
  function persistDuelTimer(duel, liveSynced = false) {
    const state = duelTimerState.get(duel);
    if (!state) return;
    state.updatedAt = Date.now();
    state.liveSynced = liveSynced;
    localStorage.setItem(duelTimerStorageKey(duel), JSON.stringify(state));
  }
  function closeDuelTimerConfirm() {
    if (!duelTimerConfirmNode) return;
    duelTimerConfirmNode.classList.remove('show');
    duelTimerConfirmNode.style.display = 'none';
    duelTimerConfirmNode.setAttribute('aria-hidden', 'true');
    pendingTimerDuel = 0;
  }
  function askDuelTimer(duel) {
    if (!duelTimerConfirmNode || !duel) return;
    const state = duelTimerState.get(duel);
    if (state?.duelStarted) return;
    pendingTimerDuel = duel;
    const title = duelTimerConfirmNode.querySelector('[data-duel-start-confirm-title]');
    const copy = duelTimerConfirmNode.querySelector('[data-duel-start-confirm-copy]');
    if (title) title.textContent = `¿Comenzar D${duel}?`;
    if (copy) copy.textContent = 'Al confirmar comenzara el registro de turnos. Elige ahora si usaras el limite de 20 minutos; esta decision no podra cambiarse durante el duelo.';
    duelTimerConfirmNode.classList.add('show');
    duelTimerConfirmNode.style.display = 'block';
    duelTimerConfirmNode.removeAttribute('aria-hidden');
  }
  function startDuelTimer(duel) {
    const state = duelTimerState.get(duel) || { baseElapsed: 0, running: false, warned: false, startedAt: null };
    if (!state.duelStarted || state.timerMode !== 'countdown') return;
    state.duelStarted = true;
    state.timerMode = 'countdown';
    state.duelStartedAt = state.duelStartedAt || Date.now();
    state.turnHistory = Array.isArray(state.turnHistory) ? state.turnHistory : [];
    state.baseElapsed = duelTimerElapsed(state);
    state.running = state.baseElapsed < 1200;
    state.startedAt = state.running ? Date.now() : null;
    duelTimerState.set(duel, state);
    persistDuelTimer(duel);
    renderDuelTimer(duel);
  }
  function beginDuel(duel, timerMode) {
    const state = duelTimerState.get(duel) || { baseElapsed: 0, running: false, warned: false, startedAt: null };
    if (state.duelStarted) return;
    const turnChoice = parseJsonValue(resultCaptureModalNode?.querySelector(`[data-result-duel-turn-choice="${duel}"]`)?.value || '', {});
    const starts = inferStartsPlayer(turnChoice.starts, state.turnHistory);
    if (!['A', 'B'].includes(String(turnChoice.chosen_by || '').toUpperCase())
      || !['primero', 'segundo'].includes(normalizeTurnChoice(turnChoice.choice || ''))
      || !['A', 'B'].includes(starts)) {
      state.duelStarted = false;
      state.running = false;
      state.startedAt = null;
      state.timerMode = '';
      duelTimerState.set(duel, state);
      persistDuelTimer(duel);
      syncResultCaptureSummary();
      return;
    }
    state.duelStarted = true;
    state.duelStartedAt = Date.now();
    state.timerMode = timerMode === 'countdown' ? 'countdown' : 'none';
    state.turnHistory = [];
    state.turnBaseline = 0;
    const turnCountInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${duel}"]`);
    if (turnCountInput) turnCountInput.value = '0';
    state.baseElapsed = 0;
    state.running = state.timerMode === 'countdown';
    state.startedAt = state.running ? Date.now() : null;
    duelTimerState.set(duel, state);
    persistDuelTimer(duel);
    const origin = resultCaptureModalNode?.querySelector(`[data-result-capture-origin="${duel}"]`);
    if (origin) origin.value = 'captured';
    setResultSaveState('pending');
    syncResultCaptureSummary();
    scheduleJudgeLiveSync();
  }
  function soundDuelAlarm(repeats = 1) {
    try {
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      const context = new AudioContextClass();
      for (let index = 0; index < repeats; index += 1) {
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.frequency.value = index % 2 ? 880 : 660;
        gain.gain.value = 0.12;
        oscillator.connect(gain); gain.connect(context.destination);
        oscillator.start(context.currentTime + index * 0.22);
        oscillator.stop(context.currentTime + index * 0.22 + 0.15);
      }
    } catch (error) {}
  }
  function renderDuelTimer(duel) {
    const duration = resultCaptureModalNode?.querySelector(`[data-result-capture-duration="${duel}"]`);
    const state = duelTimerState.get(duel);
    const elapsed = state ? duelTimerElapsed(state) : Math.max(0, Number(duration?.value || 0));
    if (duration) duration.value = String(elapsed);
    const remaining = Math.max(0, 1200 - elapsed);
    const clock = resultCaptureModalNode?.querySelector(`[data-result-duel-clock="${duel}"]`);
    const alarm = resultCaptureModalNode?.querySelector(`[data-result-duel-alarm="${duel}"]`);
    const toggle = resultCaptureModalNode?.querySelector(`[data-result-timer-toggle="${duel}"]`);
    const timerMode = state?.timerMode || '';
    const timerPanel = resultCaptureModalNode?.querySelector(`[data-result-duel-timer="${duel}"]`);
    if (timerPanel) timerPanel.classList.toggle('is-disabled', timerMode === 'none');
    if (timerMode === 'none') {
      if (clock) clock.textContent = 'Sin reloj';
      if (alarm) alarm.textContent = 'El duelo comenzo sin temporizador.';
      if (toggle) toggle.hidden = true;
      return;
    }
    if (toggle) toggle.hidden = false;
    if (clock) clock.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    if (clock) clock.classList.toggle('is-warning', remaining <= 300 && remaining > 0);
    if (clock) clock.classList.toggle('is-expired', remaining === 0);
    if (alarm && remaining === 0) alarm.textContent = 'Tiempo finalizado. Si ambos conservan LP, el duelo termina en empate.';
    if (toggle) toggle.innerHTML = state?.running ? '<i class="bi bi-pause-fill"></i> Pausar reloj' : '<i class="bi bi-play-fill"></i> Reanudar reloj';
  }

  function renderDuelTurnTracker(duel, startsPlayer, editable) {
    const state = duelTimerState.get(duel) || {};
    const history = Array.isArray(state.turnHistory) ? state.turnHistory : [];
    const legacyCount = Math.max(0, Number(resultCaptureModalNode?.querySelector(`[data-result-capture-turns="${duel}"]`)?.value || 0));
    const baseline = Number.isFinite(Number(state.turnBaseline)) ? Math.max(0, Number(state.turnBaseline)) : Math.max(0, legacyCount - history.length);
    const completed = Math.max(legacyCount, baseline + history.length);
    const recoveredStarts = inferStartsPlayer(startsPlayer, history);
    const currentSide = recoveredStarts ? (completed % 2 === 0 ? recoveredStarts : oppositePlayer(recoveredStarts)) : '';
    const match = selectedCaptureMatch();
    const current = resultCaptureModalNode?.querySelector(`[data-result-turn-current="${duel}"]`);
    const player = resultCaptureModalNode?.querySelector(`[data-result-turn-player="${duel}"]`);
    const total = resultCaptureModalNode?.querySelector(`[data-result-turn-total="${duel}"]`);
    const input = resultCaptureModalNode?.querySelector(`[data-result-capture-turns="${duel}"]`);
    const endButton = resultCaptureModalNode?.querySelector(`[data-result-turn-end="${duel}"]`);
    const historyNode = resultCaptureModalNode?.querySelector(`[data-result-turn-history="${duel}"]`);
    if (current) current.textContent = `Turno ${completed + 1}`;
    if (player) player.textContent = currentSide ? `${currentSide === 'A' ? matchLabelWithTeam(match?.a || 'Equipo A') : matchLabelWithTeam(match?.b || 'Equipo B')} (${currentSide})` : 'Esperando inicio';
    if (total) total.textContent = `${completed} ${completed === 1 ? 'turno completado' : 'turnos completados'}`;
    if (input) input.value = String(completed);
    if (endButton) {
      endButton.disabled = !editable || !state.duelStarted || !currentSide;
      endButton.innerHTML = `<i class="bi bi-check2-circle"></i> Terminar turno de ${currentSide || '-'}`;
    }
    if (historyNode) {
      historyNode.innerHTML = history.length
        ? history.slice(-6).map((entry) => `<span>T${escapeHtml(entry.turn)} · ${escapeHtml(entry.side)}</span>`).join('')
        : '<small>Aun no hay turnos completados.</small>';
    }
  }
  function finishDuelByTime(duel, automatic = false) {
    const state = duelTimerState.get(duel) || {};
    if (automatic && (!state.duelStarted || state.timerMode !== 'countdown')) return;
    state.baseElapsed = duelTimerElapsed(state);
    state.running = false;
    state.startedAt = null;
    duelTimerState.set(duel, state);
    persistDuelTimer(duel);
    // Reaching the time limit never decides a winner or a draw.
    const method = resultCaptureModalNode?.querySelector(`[data-result-capture-method="${duel}"]`);
    if (method && !automatic) method.value = 'time_limit';
    const alarm = resultCaptureModalNode?.querySelector(`[data-result-duel-alarm="${duel}"]`);
    if (alarm) alarm.textContent = 'Tiempo finalizado. Confirma el resultado en Registrar resultado; no se asigna empate automaticamente.';
    if (automatic) soundDuelAlarm(3);
    syncResultCaptureSummary(); renderDuelTimer(duel);
  }
  window.setInterval(() => {
    duelTimerState.forEach((state, duel) => {
      if (!state.duelStarted || state.timerMode !== 'countdown' || !state.running) return;
      const duration = resultCaptureModalNode?.querySelector(`[data-result-capture-duration="${duel}"]`);
      if (!duration) return;
      const elapsed = duelTimerElapsed(state);
      duration.value = String(elapsed);
      const remaining = 1200 - elapsed;
      if (remaining <= 300 && remaining > 0 && !state.warned) { state.warned = true; persistDuelTimer(duel); soundDuelAlarm(2); const alarm = resultCaptureModalNode.querySelector(`[data-result-duel-alarm="${duel}"]`); if (alarm) alarm.textContent = 'Alerta: faltan 5 minutos.'; }
      if (remaining <= 0) finishDuelByTime(duel, true);
      renderDuelTimer(duel);
    });
  }, 1000);

  function validateSurrenderTurn(duel, showMessage = false) {
    const method = resultCaptureModalNode?.querySelector(`[data-result-capture-method="${duel}"]`);
    if (!method || method.value !== 'surrender') return true;
    const winner = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${duel}"]`)?.value || '';
    const detail = captureDuelDetails().find((item) => Number(item.duel) === Number(duel)) || {};
    const starts = inferStartsPlayer(detail.starts_player, detail.turn_history);
    const completedTurns = Math.max(Number(detail.turn_count || 0), Array.isArray(detail.turn_history) ? detail.turn_history.length : 0);
    const activeSide = starts ? (completedTurns % 2 === 0 ? starts : oppositePlayer(starts)) : '';
    const surrenderingSide = winner === 'A' ? 'B' : winner === 'B' ? 'A' : '';
    const valid = Boolean(detail.duel_started && activeSide && surrenderingSide && activeSide === surrenderingSide);
    if (!valid && showMessage) {
      window.alert('Solo puede rendirse el lado que tiene el turno. Selecciona al ganador correcto o espera el turno del jugador que se rinde.');
    }
    return valid;
  }

  function syncResultCaptureSummary() {
    if (!resultCaptureModalNode) return;
    const match = selectedCaptureMatch();
    window.StationXParticipants?.set(resultCaptureModalNode, match);
    const details = captureDuelDetails();
    const values = details.map((item) => item.winner);
    const result = evaluateDuel(values, match);
    let closingIndex = -1;
    for (let i = 0; i < values.length; i += 1) {
      const partial = values.slice(0, i + 1);
      if (evaluateDuel(partial, match).state === 'FINALIZADA') {
        closingIndex = i;
        break;
      }
    }
    const persistedFinalized = ['FINALIZADA', 'completed', 'finalizada'].includes(String(match?.estado || match?.status || ''));
    const matchClosed = result.state === 'FINALIZADA' || persistedFinalized;
    resultCaptureModalNode.classList.toggle('match-closed', matchClosed);
    const currentDuelLabel = resultCaptureModalNode.querySelector('[data-result-current-duel]');
    if (currentDuelLabel) currentDuelLabel.textContent = matchClosed ? 'Completado' : 'D1';
    const summary = resultCaptureModalNode.querySelector('[data-result-capture-summary]');
    if (summary) summary.textContent = `${result.state} | Marcador ${result.score}${result.winner ? ` | Resultado ${result.winner}` : ''} | Validacion ${result.validation}`;
    const scorePill = resultCaptureModalNode.querySelector('[data-result-capture-score-pill]');
    if (scorePill) scorePill.textContent = String(result.score || '0-0').replace('-', ' - ');
    const [scoreA = '0', scoreB = '0'] = String(result.score || '0-0').split('-');
    setResultCaptureText('[data-result-capture-score-a]', scoreA);
    setResultCaptureText('[data-result-capture-score-b]', scoreB);
    const validationTitle = resultCaptureModalNode.querySelector('[data-result-capture-validation-title]');
    if (validationTitle) validationTitle.textContent = result.validation === 'OK' ? 'Validacion OK' : 'Revisar validacion';
    const validationPill = resultCaptureModalNode.querySelector('[data-result-validation-pill]');
    if (validationPill) validationPill.classList.toggle('has-warning', result.validation !== 'OK');
    const dice = parseJsonValue(resultCaptureModalNode.querySelector('[data-result-capture-dice-json]')?.value || '', null);
    const openingTurnResolved = Boolean((dice?.a && dice?.b && dice?.winner && dice?.chosen_by && dice?.choice && dice?.starts)||(details[0]?.manual_correction_at_ms&&details[0]?.starts_player));
    resultCaptureModalNode.classList.toggle('opening-resolved', openingTurnResolved);
    const persistedDuelCount = Array.isArray(match?.duels)
      ? match.duels.filter((value) => ['A', 'B', 'E'].includes(String(value || ''))).length
      : 0;
    let completed = persistedDuelCount;
    details.forEach((detail, index) => {
      const n = index + 1;
      const card = resultCaptureModalNode.querySelector(`[data-result-duel-card="${n}"]`);
      const status = resultCaptureModalNode.querySelector(`[data-result-duel-status="${n}"]`);
      const cardSummary = resultCaptureModalNode.querySelector(`[data-result-duel-card-summary="${n}"]`);
      const lpCalculatorButton = resultCaptureModalNode.querySelector(`[data-result-lp-open="${n}"]`);
      const outcomeButton = resultCaptureModalNode.querySelector(`[data-result-outcome-open="${n}"]`);
      const ledger = resultCaptureModalNode.querySelector(`[data-result-duel-turn-ledger="${n}"]`);
      const ledgerOwner = resultCaptureModalNode.querySelector(`[data-result-duel-turn-owner="${n}"]`);
      const launch = resultCaptureModalNode.querySelector(`[data-result-duel-launch="${n}"]`);
      const beginButton = resultCaptureModalNode.querySelector(`[data-result-duel-begin="${n}"]`);
      const livePanel = resultCaptureModalNode.querySelector(`[data-result-duel-live="${n}"]`);
      const setupPanel = card?.querySelector('.result-duel-setup');
      const turnHidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`);
      const diceANode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${n}"]`);
      const diceBNode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${n}"]`);
      const rerollButton = resultCaptureModalNode.querySelector(`[data-result-duel-reroll="${n}"]`);
      const firstNode = resultCaptureModalNode.querySelector(`[data-result-duel-first="${n}"]`);
      const secondNode = resultCaptureModalNode.querySelector(`[data-result-duel-second="${n}"]`);
      const hasWinner = Boolean(detail.winner);
      const isPersistedDuel = index < persistedDuelCount;
      const isAfterClose = closingIndex >= 0 && index > closingIndex;
      const isNext = index === persistedDuelCount && !isAfterClose && !persistedFinalized;
      const canPrepareDuel = isNext;
      const winnerLabel = detail.winner === 'A' ? 'Victoria de A' : detail.winner === 'B' ? 'Victoria de B' : detail.winner === 'E' ? 'Empate' : 'Pendiente de registrar';
      const methodLabel = detail.defeat_method === 'life_points' ? 'Derrota por LP' : detail.defeat_method === 'surrender' ? 'Rendicion' : '';
      const previousWinner = n > 1 ? details[n - 2]?.winner || '' : '';
      const drawDiceReady = previousWinner === 'E' && detail.dice_a && detail.dice_b && detail.turn_reason === 'draw_reroll';
      const chooser = n === 1 ? (dice?.chosen_by || dice?.winner || '') : (previousWinner === 'A' ? 'B' : previousWinner === 'B' ? 'A' : drawDiceReady ? (detail.dice_winner || detail.turn_chosen_by || '') : '');
      const reason = n === 1 ? 'dice' : (previousWinner === 'E' ? 'draw_reroll' : 'previous_duel_loss');
      let selectedTurn = parseJsonValue(turnHidden?.value || '', {});
      const manualStart=Boolean(detail.manual_correction_at_ms&&detail.starts_player);
      if (n === 1 && dice?.chosen_by && dice?.choice && dice?.starts && !manualStart) {
        selectedTurn = { chosen_by: dice.chosen_by, choice: dice.choice, starts: dice.starts, reason };
        if (turnHidden) turnHidden.value = JSON.stringify(selectedTurn);
      }
      const invalidTurnOwner = selectedTurn.chosen_by && chooser && selectedTurn.chosen_by !== chooser;
      if ((invalidTurnOwner || (!chooser && n > 1)) && turnHidden && !manualStart) {
        turnHidden.value = '';
        selectedTurn = {};
      }
      const starts = inferStartsPlayer(
        selectedTurn.starts || computeStartsPlayer(selectedTurn.chosen_by || chooser, selectedTurn.choice || ''),
        detail.turn_history
      );
      const choiceText = manualStart?'Inicio corregido: empieza '+starts:(selectedTurn.choice && starts ? turnText(selectedTurn.chosen_by || chooser, selectedTurn.choice, starts) : '');
      const runtimeState = duelTimerState.get(n) || {};
      const requiresDrawReroll = previousWinner === 'E' && !drawDiceReady;
      const hasRecordedTurns = Array.isArray(detail.turn_history) && detail.turn_history.length > 0;
      let duelStarted = Boolean(runtimeState.duelStarted || hasWinner) && !requiresDrawReroll;
      if (duelStarted && !hasWinner && !starts && !hasRecordedTurns) {
        runtimeState.duelStarted = false;
        runtimeState.duelStartedAt = null;
        runtimeState.running = false;
        runtimeState.startedAt = null;
        runtimeState.timerMode = '';
        duelTimerState.set(n, runtimeState);
        persistDuelTimer(n);
        duelStarted = false;
      }
      const canEditDuel = canPrepareDuel && Boolean(choiceText || (starts && hasRecordedTurns)) && duelStarted;
      const methodControl = resultCaptureModalNode.querySelector(`[data-result-capture-method="${n}"]`);
      const surrenderOption = methodControl?.querySelector('option[value="surrender"]');
      const completedTurns = Math.max(Number(detail.turn_count || 0), Array.isArray(detail.turn_history) ? detail.turn_history.length : 0);
      const activeSide = starts ? (completedTurns % 2 === 0 ? starts : oppositePlayer(starts)) : '';
      const brickFieldset = resultCaptureModalNode.querySelector(`[data-result-brick-fieldset="${n}"]`);
      const brickSummary = resultCaptureModalNode.querySelector(`[data-result-brick-summary="${n}"]`);
      const brickSummaryText = resultCaptureModalNode.querySelector(`[data-result-brick-summary-text="${n}"]`);
      const brickAInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-a="${n}"]`);
      const brickBInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-b="${n}"]`);
      const brickOptionVisible = canEditDuel && !hasWinner && completedTurns < 2 && ['A', 'B'].includes(activeSide);
      const visitedBrickSides = [];
      if (duelStarted && starts) visitedBrickSides.push(starts);
      if (completedTurns >= 1 && starts) visitedBrickSides.push(oppositePlayer(starts));
      const brickSummarySides = [...new Set(visitedBrickSides)].filter((side) => hasWinner || side !== activeSide || completedTurns >= 2);
      if (brickFieldset) {
        brickFieldset.hidden = !brickOptionVisible;
        brickFieldset.querySelectorAll('[data-result-brick-side]').forEach((label) => {
          label.hidden = label.dataset.resultBrickSide !== activeSide;
        });
      }
      if (brickAInput) brickAInput.disabled = !brickOptionVisible || activeSide !== 'A';
      if (brickBInput) brickBInput.disabled = !brickOptionVisible || activeSide !== 'B';
      if (brickSummary) brickSummary.hidden = brickSummarySides.length === 0;
      if (brickSummaryText) {
        brickSummaryText.textContent = brickSummarySides.map((side) => {
          const checked = side === 'A' ? Boolean(brickAInput?.checked) : Boolean(brickBInput?.checked);
          return `${side}: ${checked ? 'Si' : 'No'}`;
        }).join(' | ');
      }
      const surrenderingSide = detail.winner === 'A' ? 'B' : detail.winner === 'B' ? 'A' : '';
      if (surrenderOption) {
        surrenderOption.disabled = !canEditDuel || !surrenderingSide || surrenderingSide !== activeSide;
        surrenderOption.textContent = surrenderOption.disabled
          ? 'Rendicion (solo en el turno del jugador)'
          : `Rendicion del lado ${surrenderingSide}`;
      }
      const detailDiceA = detail.dice_a || (n === 1 ? dice?.a || '' : '');
      const detailDiceB = detail.dice_b || (n === 1 ? dice?.b || '' : '');
      if (diceANode) {
        diceANode.textContent = detailDiceA || '-';
        diceANode.dataset.value = detailDiceA || '';
      }
      if (diceBNode) {
        diceBNode.textContent = detailDiceB || '-';
        diceBNode.dataset.value = detailDiceB || '';
      }
      if (firstNode) firstNode.textContent = starts ? `${starts === 'A' ? matchLabelWithTeam(match?.a || 'A') : matchLabelWithTeam(match?.b || 'B')} (${starts})` : 'Sin definir';
      if (secondNode) {
        const second = starts ? oppositePlayer(starts) : '';
        secondNode.textContent = second ? `${second === 'A' ? matchLabelWithTeam(match?.a || 'A') : matchLabelWithTeam(match?.b || 'B')} (${second})` : 'Sin definir';
      }
      if (card) {
        card.classList.toggle('is-complete', isPersistedDuel && hasWinner && !isAfterClose);
        card.classList.toggle('is-active', isNext);
        card.classList.toggle('is-pending', !hasWinner && !isNext && !isAfterClose && !persistedFinalized);
        card.classList.toggle('is-locked', !canPrepareDuel);
        card.dataset.duelResult = detail.winner || '';
        if (isNext) {
          const currentDuelLabel = resultCaptureModalNode.querySelector('[data-result-current-duel]');
          if (currentDuelLabel) currentDuelLabel.textContent = `D${n}`;
        }
      }
      if (status) {
        status.textContent = hasWinner
          ? (isNext ? 'Listo para guardar' : 'Completado')
          : (isAfterClose ? 'No necesario' : (persistedFinalized ? 'Cerrado' : (isNext ? (duelStarted ? 'En curso' : (choiceText ? 'Listo para comenzar' : 'Define el inicio')) : 'Pendiente')));
      }
      if (cardSummary) {
        const lpText = detail.lp_a || detail.lp_b ? ` | LP ${detail.lp_a || '0'}-${detail.lp_b || '0'}` : '';
        cardSummary.textContent = isAfterClose ? 'Match ya finalizado; duelo no necesario' : `${winnerLabel}${methodLabel ? ` | ${methodLabel}` : ''}${lpText}${choiceText ? ` | ${choiceText}` : ''}`;
      }
      if (ledger) {
        const canChoose = canPrepareDuel && Boolean(chooser) && !duelStarted && (n === 1 || Boolean(previousWinner));
        ledger.hidden = !canPrepareDuel || duelStarted || !chooser;
        ledger.classList.toggle('is-complete', Boolean(choiceText));
        ledger.classList.toggle('is-pending', canChoose && !choiceText);
        ledger.querySelectorAll('[data-result-duel-turn-select]').forEach((button) => {
          button.disabled = !canChoose;
          button.classList.toggle('active', normalizeTurnChoice(button.dataset.choice || '') === normalizeTurnChoice(selectedTurn.choice || ''));
        });
      }
      if (rerollButton) {
        rerollButton.hidden = !(canPrepareDuel && previousWinner === 'E' && !drawDiceReady);
        rerollButton.disabled = !canPrepareDuel || duelStarted;
      }
      if (launch) launch.hidden = !canPrepareDuel || !choiceText || duelStarted;
      if (beginButton) beginButton.disabled = !canPrepareDuel || !choiceText || duelStarted;
      if (livePanel) livePanel.hidden = !duelStarted;
      if (setupPanel) setupPanel.hidden = duelStarted;
      renderDuelTurnTracker(n, starts, canEditDuel);
      if (ledgerOwner) {
        if (choiceText) {
          ledgerOwner.textContent = `${n === 1 || previousWinner === 'E' ? 'Ganador del dado' : 'Perdedor del duelo anterior'}: ${choiceText}`;
        } else if (chooser) {
          ledgerOwner.textContent = `${n === 1 || previousWinner === 'E' ? 'Ganador del dado' : 'Perdedor del duelo anterior'} ${chooser}: elige si empieza o va segundo.`;
        } else {
          ledgerOwner.textContent = n === 1 ? 'Lanza el dado para definir quien elige.' : (previousWinner === 'E' ? 'El duelo anterior terminó empatado. Deben lanzar dados nuevamente.' : 'Se habilita cuando el duelo anterior tenga ganador.');
        }
      }
      ['duel', 'method', 'lp-a', 'lp-b', 'deck-a', 'deck-b', 'player-a', 'player-b', 'turns', 'brick-a', 'brick-b', 'note'].forEach((kind) => {
        const selector = kind === 'duel'
          ? `[data-result-capture-duel="${n}"]`
          : kind === 'method'
            ? `[data-result-capture-method="${n}"]`
            : kind === 'lp-a'
              ? `[data-result-capture-lp-a="${n}"]`
              : kind === 'lp-b'
                ? `[data-result-capture-lp-b="${n}"]`
                : kind === 'deck-a'
                  ? `[data-result-capture-deck-a="${n}"]`
                  : kind === 'deck-b'
                    ? `[data-result-capture-deck-b="${n}"]`
                    : kind === 'player-a'
                      ? `[data-result-capture-player-a="${n}"]`
                    : kind === 'player-b'
                      ? `[data-result-capture-player-b="${n}"]`
                      : kind === 'turns'
                        ? `[data-result-capture-turns="${n}"]`
                        : kind === 'brick-a'
                          ? `[data-result-capture-brick-a="${n}"]`
                          : kind === 'brick-b'
                            ? `[data-result-capture-brick-b="${n}"]`
                        : `[data-result-capture-duel-note="${n}"]`;
        const control = resultCaptureModalNode.querySelector(selector);
        if (control) control.disabled = !canEditDuel;
      });
      if (brickAInput) brickAInput.disabled = !brickOptionVisible || activeSide !== 'A';
      if (brickBInput) brickBInput.disabled = !brickOptionVisible || activeSide !== 'B';
      if (lpCalculatorButton) {
        lpCalculatorButton.disabled = !canEditDuel;
        lpCalculatorButton.title = canEditDuel ? `Abrir calculadora para D${n}` : (hasWinner ? 'Este duelo ya fue completado.' : 'Este duelo aun no esta habilitado.');
      }
      if (outcomeButton) {
        outcomeButton.disabled = !canEditDuel;
        outcomeButton.title = canEditDuel ? `Registrar resultado de D${n}` : 'El duelo debe estar en curso.';
      }
      resultCaptureModalNode.querySelectorAll(`[data-result-timer-toggle="${n}"]`).forEach((button) => { button.disabled = !canEditDuel || runtimeState.timerMode !== 'countdown'; });
      renderDuelTimer(n);
    });
    const rollButton = resultCaptureModalNode.querySelector('[data-result-roll-dice]');
    if (rollButton) {
      rollButton.disabled = persistedFinalized || matchClosed || openingTurnResolved;
      rollButton.title = openingTurnResolved
        ? 'La tirada inicial ya esta registrada con eleccion de turno.'
        : (persistedFinalized || matchClosed ? 'El match ya esta cerrado.' : '');
      rollButton.innerHTML = openingTurnResolved
        ? '<i class="bi bi-lock-fill"></i> Dado registrado'
        : '<i class="bi bi-dice-5"></i> Lanzar dado';
    }
    const progressText = resultCaptureModalNode.querySelector('[data-result-duel-progress-text]');
    const configuredBestOf = [1, 3, 5].includes(Number(match?.best_of)) ? Number(match.best_of) : 3;
    // Draws can extend a series under its saved rule. Never show fewer slots
    // than duels already played, and consider a closed series complete.
    const progressTotal = Math.max(configuredBestOf, completed);
    if (progressText) {
      progressText.textContent = `${completed} / ${progressTotal}`;
      progressText.title = `Duelos jugados | Mejor de ${configuredBestOf}${matchClosed ? ' | Combate finalizado' : ''}`;
    }
    const progressBar = resultCaptureModalNode.querySelector('[data-result-duel-progress-bar]');
    if (progressBar) progressBar.style.width = `${matchClosed ? 100 : Math.min(100, (completed / progressTotal) * 100)}%`;
    const notesInput = resultCaptureModalNode.querySelector('[data-result-capture-notes]');
    const notesCount = resultCaptureModalNode.querySelector('[data-result-notes-count]');
    if (notesCount) notesCount.textContent = String((notesInput?.value || '').length);
    const detailsInput = resultCaptureModalNode.querySelector('[data-result-capture-details-json]');
    if (detailsInput) detailsInput.value = JSON.stringify(closingIndex >= 0 ? details.slice(0, closingIndex + 1) : details);
    const miniList = resultCaptureModalNode.querySelector('[data-result-capture-duel-list]');
    if (miniList) {
      const firstPendingIndex = details.findIndex((detail, index) => !detail.winner && !(closingIndex >= 0 && index > closingIndex));
      miniList.innerHTML = details.map((detail, index) => {
        const n = index + 1;
        const value = detail.winner || '';
        const isPersistedDuel = index < persistedDuelCount;
        const isCurrentDraft = index === persistedDuelCount && Boolean(value) && !persistedFinalized;
        const state = isCurrentDraft ? 'Listo para guardar' : (value ? (value === 'E' ? 'Empate' : 'Finalizado') : (closingIndex >= 0 && index > closingIndex ? 'No necesario' : 'Pendiente'));
        const scoreText = value === 'A' ? 'A gana' : value === 'B' ? 'B gana' : value === 'E' ? 'Empate' : '-';
        const active = index === persistedDuelCount && !persistedFinalized;
        const locked = closingIndex >= 0 && index > closingIndex;
        const className = [isPersistedDuel && value ? 'is-complete' : '', active ? 'is-active' : '', locked ? 'is-locked' : ''].filter(Boolean).join(' ');
        return `<span class="${className}"><b>D${n}</b><i>${state}</i><strong>${scoreText}</strong>${value ? `<button class="btn btn-outline-info btn-sm" type="button" data-result-duel-read="${n}" title="Ver informacion completa de D${n}"><i class="bi bi-eye"></i> Ver</button>` : ''}</span>`;
      }).join('');
      miniList.querySelectorAll('[data-result-duel-read]').forEach((button) => button.addEventListener('click', () => openResultDuelRead(Number(button.dataset.resultDuelRead))));
    }
    const saveButton = resultCaptureModalNode.querySelector('[data-result-capture-save]');
    if (saveButton) {
      saveButton.disabled = persistedFinalized || result.validation !== 'OK';
      saveButton.innerHTML = persistedFinalized
        ? '<i class="bi bi-lock-fill"></i> Resultado cerrado'
        : '<i class="bi bi-database-check"></i> Guardar cambios';
    }
  }

  function openResultCapture(matchId) {
    if (!resultCaptureModalNode || !resultCaptureModal) return;
    const match = allMatchList().find((item) => String(item.id || '') === String(matchId || ''));
    if (!match) return;
    closeResultDuelRead();
    if(window.StationXTeams?.isTeamMatch(match)){window.StationXTeams.openResult(match);return;}
    window.clearTimeout(liveDuelSyncTimer);
    duelTimerState.clear();
    liveDuelRevision = 0;
    activeResultCaptureMatchId = String(match.id || '');
    localStorage.setItem('dml_result_capture_match_id', activeResultCaptureMatchId);
    localStorage.setItem('dml_result_capture_route', (location.hash || '').replace('#', '') || localStorage.getItem('dml_screen') || 'calendario');
    setResultSaveState('saved');
    ['A', 'B'].forEach((side) => {
      const url = new URL('./', window.location.href);
      url.searchParams.set('duel_player', side);
      url.searchParams.set('match_id', activeResultCaptureMatchId);
      url.hash = '';
      const link = resultCaptureModalNode.querySelector(`[data-result-player-link="${side}"]`);
      const copy = resultCaptureModalNode.querySelector(`[data-result-player-copy="${side}"]`);
      if (link) link.href = url.toString();
      if (copy) copy.dataset.url = url.toString();
    });
    const saved = getSavedResult(match);
    const details = parseJsonValue(match.duel_details || '', []);
    const correctionInput=resultCaptureModalNode.querySelector('[data-result-correction-version]');if(correctionInput)correctionInput.value=String(Math.max(0,...details.map(d=>Number(d.manual_correction_at_ms||0))));
    const dice = parseJsonValue(match.dice_roll || '', null);
    const matchIdInput = resultCaptureModalNode.querySelector('[data-result-capture-match-id]');
    const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
    const turnInput = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
    const notesInput = resultCaptureModalNode.querySelector('[data-result-capture-notes]');
    if (matchIdInput) matchIdInput.value = match.id || '';
    if (diceInput) diceInput.value = match.dice_roll || '';
    if (turnInput) turnInput.value = match.turn_choice || '';
    if (notesInput) notesInput.value = match.result_notes || '';
    setResultCaptureText('[data-result-capture-phase]', match.phase || 'Combate');
    setResultCaptureText('[data-result-capture-title]', `R${match.ronda || '-'} M${match.mesa || '-'} | ${match.competition_name || match.division || ''}`);
    setResultCaptureText('[data-result-capture-meta]', `${match.scheduled_on || 'Sin fecha'} ${match.starts_at ? match.starts_at.slice(0, 5) : ''} | ${String(match.match_format || '1v1').toUpperCase()} | BO${match.best_of || 5}`);
    const captureIdentity=resultCaptureModalNode.querySelector('[data-result-capture-identity]');
    if(captureIdentity) captureIdentity.innerHTML=calendarEventIdentity({competitionId:match.competition_id,matches:[match],division:match.division,type:match.phase},false);
    setResultCaptureText('[data-result-capture-bestof]', `Mejor de ${match.best_of || 5}`);
    const teamA = match.team_a || 'Equipo A';
    const teamB = match.team_b || 'Equipo B';
    renderMatchLeaguePositions(match, 'result-capture');
    setResultCaptureText('[data-result-capture-team-a]', teamA);
    setResultCaptureText('[data-result-capture-team-b]', teamB);
    const logoA = matchSideLogoUrl(match, 'a');
    const logoB = matchSideLogoUrl(match, 'b');
    setResultCaptureLogo('[data-result-capture-logo-a]', logoA, matchSideFallback(match, 'a') || 'A', teamA);
    setResultCaptureLogo('[data-result-capture-logo-b]', logoB, matchSideFallback(match, 'b') || 'B', teamB);
    setResultArenaArt('[data-result-capture-side-a]', logoA);
    setResultArenaArt('[data-result-capture-side-b]', logoB);
    setResultCaptureText('[data-result-capture-a]', matchLabelWithTeam(match.a));
    setResultCaptureText('[data-result-capture-b]', matchLabelWithTeam(match.b));
    const shareMatch = resultCaptureModalNode.querySelector('[data-share-match-result]');
    if (shareMatch) shareMatch.onclick = () => {
      const result = evaluateDuel(getSavedResult(match).filter(Boolean), match);
      shareCanvas('Resultado de partida', [match.competition_name || match.division || 'Evento', `R${match.ronda || '-'} | Mesa ${match.mesa || '-'}`, match.a || 'Deck A', result.score || '0-0', match.b || 'Deck B', result.state || 'Pendiente', String(match.scheduled_on || '')], 'resultado-partida-' + String(match.id || '') + '.png');
    };
    Array.from({ length: 5 }, (_, index) => index + 1).forEach((n) => {
      const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === n) || {}) : {};
      const duel = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${n}"]`);
      const method = resultCaptureModalNode.querySelector(`[data-result-capture-method="${n}"]`);
      const lpA = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${n}"]`);
      const lpB = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${n}"]`);
      const note = resultCaptureModalNode.querySelector(`[data-result-capture-duel-note="${n}"]`);
      const deckASelect = resultCaptureModalNode.querySelector(`[data-result-capture-deck-a="${n}"]`);
      const deckBSelect = resultCaptureModalNode.querySelector(`[data-result-capture-deck-b="${n}"]`);
      const playerASelect = resultCaptureModalNode.querySelector(`[data-result-capture-player-a="${n}"]`);
      const playerBSelect = resultCaptureModalNode.querySelector(`[data-result-capture-player-b="${n}"]`);
      const turnsInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${n}"]`);
      const brickAInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-a="${n}"]`);
      const brickBInput = resultCaptureModalNode.querySelector(`[data-result-capture-brick-b="${n}"]`);
      const durationInput = resultCaptureModalNode.querySelector(`[data-result-capture-duration="${n}"]`);
      const originSelect = resultCaptureModalNode.querySelector(`[data-result-capture-origin="${n}"]`);
      if (detail.lp_start_a || detail.lp_start_b || Array.isArray(detail.lp_history)) {
        let storedLp = {};
        try { storedLp = JSON.parse(localStorage.getItem(resultLpStorageKey(n)) || '{}'); } catch (error) { storedLp = {}; }
        localStorage.setItem(resultLpStorageKey(n), JSON.stringify({
          ...storedLp,
          a: detail.lp_a !== '' && detail.lp_a !== undefined ? Number(detail.lp_a) : Number(detail.lp_start_a || storedLp.a || 8000),
          b: detail.lp_b !== '' && detail.lp_b !== undefined ? Number(detail.lp_b) : Number(detail.lp_start_b || storedLp.b || 8000),
          start_a: Number(detail.lp_start_a || storedLp.start_a || 8000),
          start_b: Number(detail.lp_start_b || storedLp.start_b || 8000),
          history: Array.isArray(detail.lp_history) ? detail.lp_history : (storedLp.history || []),
          running: false
        }));
      }
      const turnHidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`);
      const diceANode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${n}"]`);
      const diceBNode = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${n}"]`);
      if (duel) duel.value = detail.winner || saved[n - 1] || '';
      if (method) method.value = detail.defeat_method || '';
      if (lpA) lpA.value = detail.lp_a ?? '';
      if (lpB) lpB.value = detail.lp_b ?? '';
      if (note) note.value = detail.notes || '';
      if (turnsInput) turnsInput.value = detail.turn_count || '';
      if (brickAInput) brickAInput.checked = Boolean(detail.brick_a);
      if (brickBInput) brickBInput.checked = Boolean(detail.brick_b);
      if (originSelect) {
        const origin = String(detail.data_origin || '').toLowerCase();
        originSelect.value = ['captured', 'added', 'inferred'].includes(origin)
          ? origin
          : (detail.duel_started_at || detail.turn_history?.length ? 'captured' : 'added');
      }
      let localTimer = null;
      try { localTimer = JSON.parse(localStorage.getItem(duelTimerStorageKey(n)) || 'null'); } catch (error) { localTimer = null; }
      const serverTimer = {
        baseElapsed: Number(detail.duration_seconds || 0),
        running: Boolean(detail.timer_running),
        startedAt: Number(detail.timer_started_at || 0) || null,
        updatedAt: Number(detail.timer_updated_at || 0),
        correctionVersion: Number(detail.manual_correction_at_ms || 0),
        warned: Number(detail.duration_seconds || 0) >= 900,
        duelStarted: Boolean(detail.duel_started || detail.winner || saved[n - 1]),
        duelStartedAt: Number(detail.duel_started_at || 0) || null,
        timerMode: ['none', 'countdown'].includes(detail.timer_mode) ? detail.timer_mode : (detail.duel_started || detail.winner || saved[n - 1] ? 'none' : ''),
        turnHistory: Array.isArray(detail.turn_history) ? detail.turn_history : [],
        turnBaseline: Math.max(0, Number(detail.turn_count || 0) - (Array.isArray(detail.turn_history) ? detail.turn_history.length : 0))
      };
      const hasLocalChanges = localTimer && !localTimer.liveSynced && Number(localTimer.correctionVersion || 0) === serverTimer.correctionVersion && Number(localTimer.updatedAt || 0) > serverTimer.updatedAt;
      const timerState = hasLocalChanges ? localTimer : serverTimer;
      timerState.duelStarted = Boolean(timerState.duelStarted || timerState.running || Number(timerState.baseElapsed || 0) > 0 || detail.winner || saved[n - 1]);
      timerState.duelStartedAt = Number(timerState.duelStartedAt || detail.duel_started_at || 0) || null;
      timerState.timerMode = timerState.timerMode || (timerState.duelStarted ? 'none' : '');
      if (detail.duel_started && detail.timer_mode === 'none') timerState.timerMode = 'none';
      if (timerState.timerMode !== 'countdown') { timerState.running = false; timerState.startedAt = null; }
      timerState.turnHistory = Array.isArray(timerState.turnHistory) ? timerState.turnHistory : (Array.isArray(detail.turn_history) ? detail.turn_history : []);
      timerState.turnBaseline = Number.isFinite(Number(timerState.turnBaseline)) ? Math.max(0, Number(timerState.turnBaseline)) : Math.max(0, Number(detail.turn_count || 0) - timerState.turnHistory.length);
      if (detail.winner) {
        timerState.baseElapsed = duelTimerElapsed(timerState);
        timerState.running = false;
        timerState.startedAt = null;
      }
      timerState.warned = Boolean(timerState.warned || duelTimerElapsed(timerState) >= 900);
      duelTimerState.set(n, timerState);
      if (durationInput) durationInput.value = String(duelTimerElapsed(timerState));
      localStorage.setItem(duelTimerStorageKey(n), JSON.stringify(timerState));
      if (hasLocalChanges) setResultSaveState('pending');
      renderDuelTimer(n);
      const sideADecks = matchSideDecks(match, 'a').map(deckInfoByName).filter((deck) => deck.id);
      const sideBDecks = matchSideDecks(match, 'b').map(deckInfoByName).filter((deck) => deck.id);
      const fillDeckSelect = (select, decks, selectedId) => {
        if (!select) return;
        select.innerHTML = decks.map((deck) => `<option value="${escapeHtml(deck.id)}">${escapeHtml(deck.name)}</option>`).join('');
        select.value = String(selectedId || decks[0]?.id || '');
        const label = select.closest('label');
        if (label) {
          label.hidden = decks.length <= 1;
          label.style.display = label.hidden ? 'none' : '';
        }
      };
      const fillPlayerSelect = (select, deckId, selectedId) => {
        if (!select) return;
        const playerIds = new Set((data.playerDecks || []).filter((link) => Number(link.deck_id) === Number(deckId)).map((link) => Number(link.player_id)));
        const players = (data.players || []).filter((player) => playerIds.has(Number(player.id)));
        select.innerHTML = '<option value="">Sin asignar</option>' + players.map((player) => `<option value="${escapeHtml(player.id)}">${escapeHtml(player.alias || player.display_name)}</option>`).join('');
        select.value = String(selectedId || '');
        const label = select.closest('label');
        if (label) label.hidden = players.length === 0;
      };
      fillDeckSelect(deckASelect, sideADecks, detail.deck_a_id);
      fillDeckSelect(deckBSelect, sideBDecks, detail.deck_b_id);
      fillPlayerSelect(playerASelect, deckASelect?.value, detail.player_a_id);
      fillPlayerSelect(playerBSelect, deckBSelect?.value, detail.player_b_id);
      const participantGrid = resultCaptureModalNode.querySelector(`[data-result-participant-grid="${n}"]`);
      if (participantGrid) {
        participantGrid.style.display = Array.from(participantGrid.querySelectorAll('label')).some((label) => !label.hidden) ? '' : 'none';
      }
      deckASelect?.addEventListener('change', () => fillPlayerSelect(playerASelect, deckASelect.value, ''));
      deckBSelect?.addEventListener('change', () => fillPlayerSelect(playerBSelect, deckBSelect.value, ''));
      if (diceANode) {
        const value = detail.dice_a || (n === 1 ? dice?.a || '' : '');
        diceANode.textContent = value || '-';
        diceANode.dataset.value = value || '';
      }
      if (diceBNode) {
        const value = detail.dice_b || (n === 1 ? dice?.b || '' : '');
        diceBNode.textContent = value || '-';
        diceBNode.dataset.value = value || '';
      }
      if (turnHidden) {
        const turnPayload = {
          chosen_by: detail.turn_chosen_by || (n === 1 ? (match.turn_chosen_by || dice?.chosen_by || dice?.winner || '') : ''),
          choice: detail.turn_decision || (n === 1 ? (match.turn_decision || dice?.choice || '') : ''),
          starts: detail.starts_player || (n === 1 ? (match.starts_player || dice?.starts || '') : ''),
          reason: detail.turn_reason || (n === 1 ? 'dice' : 'previous_duel_loss')
        };
        turnHidden.value = (turnPayload.chosen_by && turnPayload.choice && turnPayload.starts)||(detail.manual_correction_at_ms&&turnPayload.starts) ? JSON.stringify(turnPayload) : '';
      }
    });
    const diceSummary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
    if (diceSummary) {
      const persistedStarts = match.starts_player || dice?.starts || '';
      const persistedDecision = match.turn_decision || dice?.choice || '';
      const persistedChosenBy = match.turn_chosen_by || dice?.chosen_by || dice?.winner || '';
      diceSummary.textContent = dice && dice.winner
        ? `Dado ${dice.a}-${dice.b}. Gano ${dice.winner}. ${match.turn_choice || (persistedChosenBy && persistedDecision && persistedStarts ? `${persistedChosenBy} eligio ${persistedDecision}. Empieza ${persistedStarts}.` : '')}`
        : 'Sin lanzamiento registrado.';
    }
    updateResultDie('[data-result-die-a]', dice?.a || 1, false);
    updateResultDie('[data-result-die-b]', dice?.b || 1, false);
    const choicePanel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
    if (choicePanel) choicePanel.hidden = Boolean((dice?.chosen_by && dice?.choice && dice?.starts) || match.turn_choice || !dice?.winner);
    syncResultCaptureSummary();
    resultCaptureModal.show();
    liveDuelRevision = 0;
    window.clearInterval(liveDuelPollTimer);
    syncJudgeLiveDuel(false);
    liveDuelPollTimer = window.setInterval(() => syncJudgeLiveDuel(false), 1800);
    window.setTimeout(() => {
      const body = resultCaptureModalNode.querySelector('.result-console-body');
      if (body) body.scrollTop = 0;
    }, 80);
  }

  function renderResult() {
    const match = selectedMatch();
    const values = duelSelects.map((select) => select.value);
    if (match && !match.id) localStorage.setItem(resultKey(match), JSON.stringify(values));
    const result = evaluateDuel(values, match);
    const timing = matchTimingStatus(match);
    if (resultSaveButton) {
      resultSaveButton.disabled = !timing.canCapture;
      resultSaveButton.title = timing.canCapture ? '' : `Bloqueado: ${timing.label}`;
    }
    const timingText = timing.canCapture ? timing.label : `${timing.label} | no ejecutable`;
    resultSummary.textContent = `${timingText} | ${result.state} | Marcador ${result.score}${result.winner ? ` | Resultado ${result.winner}` : ''} | Validacion ${result.validation}`;
    renderStandings();
    renderStats();
    renderProfile();
    renderResultDashboard(filteredResultMatches());
  }

  if (resultDivision) resultDivision.addEventListener('change', () => { syncResultRoundOptions(); loadMatchOptions(); });
  if (resultMatch) resultMatch.addEventListener('change', loadResult);
  [resultPhase, resultEventFilter, resultLeagueDivisionFilter].forEach((control) => {
    if (control) control.addEventListener('change', () => { syncResultRoundOptions(); loadMatchOptions(); });
  });
  resultPeriod?.addEventListener('change', () => {
    if (resultPeriod.value === 'day' && !resultDate.value) resultDate.value = serverToday;
    if (resultPeriod.value === 'month' && !resultMonth.value) resultMonth.value = serverToday.slice(0, 7);
    syncResultPeriodFields();
    loadMatchOptions();
  });
  [resultStatus, resultScheduleFilter, resultRoundFilter, resultSort, resultDate, resultMonth, resultDateFrom, resultDateTo, resultSearch].forEach((control) => {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', () => { syncResultPeriodFields(); loadMatchOptions(); });
  });
  resultClearFilters?.addEventListener('click', () => {
    if (resultPhase) resultPhase.value = '';
    if (resultEventFilter) resultEventFilter.value = '';
    if (resultLeagueDivisionFilter) resultLeagueDivisionFilter.value = '';
    if (resultStatus) resultStatus.value = '';
    if (resultScheduleFilter) resultScheduleFilter.value = 'all';
    if (resultRoundFilter) resultRoundFilter.value = '';
    if (resultSort) resultSort.value = 'desc';
    if (resultDate) resultDate.value = '';
    if (resultPeriod) resultPeriod.value = 'all';
    [resultMonth, resultDateFrom, resultDateTo].forEach((control) => { if (control) control.value = ''; });
    syncResultPeriodFields();
    if (resultSearch) resultSearch.value = '';
    syncResultRoundOptions();
    loadMatchOptions();
  });
  duelSelects.forEach((select) => select.addEventListener('change', renderResult));
  resultCaptureModalNode?.addEventListener('click', (event) => {
    const reroll = event.target.closest('[data-result-duel-reroll]');
    if (reroll) {
      event.preventDefault();
      const duel = Number(reroll.dataset.resultDuelReroll || 0);
      if (duel <= 1) return;
      const details = captureDuelDetails();
      if (details[duel - 2]?.winner !== 'E') return;
      const roll = rollOpeningDice();
      const diceA = resultCaptureModalNode.querySelector(`[data-result-duel-dice-a="${duel}"]`);
      const diceB = resultCaptureModalNode.querySelector(`[data-result-duel-dice-b="${duel}"]`);
      const hidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`);
      if (diceA) { diceA.textContent = String(roll.a); diceA.dataset.value = String(roll.a); }
      if (diceB) { diceB.textContent = String(roll.b); diceB.dataset.value = String(roll.b); }
      if (hidden) hidden.value = JSON.stringify({ chosen_by: roll.winner, dice_winner: roll.winner, choice: '', starts: '', reason: 'draw_reroll', rolled_at: new Date().toISOString() });
      const runtime = duelTimerState.get(duel) || {};
      runtime.duelStarted = false;
      runtime.running = false;
      runtime.startedAt = null;
      runtime.timerMode = '';
      duelTimerState.set(duel, runtime);
      persistDuelTimer(duel);
      setResultSaveState('pending');
      syncResultCaptureSummary();
      scheduleJudgeLiveSync();
      return;
    }
    const begin = event.target.closest('[data-result-duel-begin]');
    if (begin) {
      event.preventDefault();
      const duel = Number(begin.dataset.resultDuelBegin || 0);
      if (duel) askDuelTimer(duel);
      return;
    }
    const endTurn = event.target.closest('[data-result-turn-end]');
    if (endTurn) {
      event.preventDefault();
      const duel = Number(endTurn.dataset.resultTurnEnd || 0);
      const state = duelTimerState.get(duel);
      const turnChoice = parseJsonValue(resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`)?.value || '', {});
      const recoveredStarts = inferStartsPlayer(turnChoice.starts, state?.turnHistory);
      if (!state?.duelStarted || !recoveredStarts) return;
      if (!turnChoice.starts) {
        turnChoice.starts = recoveredStarts;
        turnChoice.reason = turnChoice.reason || 'turn_history';
        const turnChoiceNode = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${duel}"]`);
        if (turnChoiceNode) turnChoiceNode.value = JSON.stringify(turnChoice);
      }
      state.turnHistory = Array.isArray(state.turnHistory) ? state.turnHistory : [];
      const turnInput = resultCaptureModalNode.querySelector(`[data-result-capture-turns="${duel}"]`);
      const persistedCount = Math.max(0, Number(turnInput?.value || 0));
      const completed = Math.max(persistedCount, Math.max(0, Number(state.turnBaseline || 0)) + state.turnHistory.length);
      const side = completed % 2 === 0 ? recoveredStarts : oppositePlayer(recoveredStarts);
      state.turnHistory.push({ turn: completed + 1, side, ended_at: new Date().toISOString(), elapsed_seconds: duelTimerElapsed(state) });
      if (turnInput) turnInput.value = String(completed + 1);
      duelTimerState.set(duel, state);
      persistDuelTimer(duel);
      setResultSaveState('pending');
      syncResultCaptureSummary();
      scheduleJudgeLiveSync();
      return;
    }
    const toggle = event.target.closest('[data-result-timer-toggle]');
    if (toggle) {
      event.preventDefault();
      const duel = Number(toggle.dataset.resultTimerToggle || 0);
      const state = duelTimerState.get(duel) || { baseElapsed: 0, running: false, warned: false, startedAt: null };
      if (state.running) {
        state.baseElapsed = duelTimerElapsed(state);
        state.running = false;
        state.startedAt = null;
        duelTimerState.set(duel, state);
        persistDuelTimer(duel);
        renderDuelTimer(duel);
      } else {
        startDuelTimer(duel);
      }
      setResultSaveState('pending');
      scheduleJudgeLiveSync();
    }
  });
  const clearResult = document.querySelector('[data-clear-result]');
  if (clearResult) clearResult.addEventListener('click', () => {
    duelSelects.forEach((select) => { select.value = ''; });
    syncSequentialDuelControls(duelSelects, []);
    renderResult();
  });

  function syncResultRoundOptions() {
    syncResultEventOptions();
    syncResultLeagueDivisionOptions();
    if (!resultRoundFilter) return;
    const current = resultRoundFilter.value;
    const phase = resultPhase ? resultPhase.value : '';
    const rounds = Array.from(new Set(resultBaseMatches()
      .filter(resultLeagueDivisionMatches)
      .filter(match => !resultEventFilter?.value || String(match.competition_id) === resultEventFilter.value)
      .filter((match) => {
        if (!phase) return true;
        const matchPhase = String(match.phase || '').toLowerCase();
        if (phase === 'events') return ['event', 'events', 'special', 'eternal', 'tiebreaker', 'final'].includes(matchPhase);
        return matchPhase === phase;
      })
      .map((match) => Number(match.ronda || 0))
      .filter(Boolean))).sort((a, b) => a - b);
    resultRoundFilter.innerHTML = '<option value="">Todas las rondas</option>' + rounds.map((round) => `<option value="${round}">Ronda ${round}</option>`).join('');
    if (rounds.some((round) => String(round) === String(current))) {
      resultRoundFilter.value = current;
    }
  }

  function renderResultDashboard(matches) {
    if (resultKpiTotal) resultKpiTotal.textContent = String(matches.length);
    if (resultKpiPending) resultKpiPending.textContent = String(matches.filter((match) => match.estado === 'PENDIENTE').length);
    if (resultKpiProgress) resultKpiProgress.textContent = String(matches.filter((match) => ['EN CURSO', 'REQUIERE D5'].includes(match.estado)).length);
    if (resultKpiDone) resultKpiDone.textContent = String(matches.filter((match) => match.estado === 'FINALIZADA').length);
    if (resultKpiOverdue) resultKpiOverdue.textContent = String(matches.filter((match) => matchTimingStatus(match).key === 'overdue').length);
    if (resultKpiToday) resultKpiToday.textContent = String(matches.filter((match) => matchTimingStatus(match).key === 'today').length);
    if (resultKpiFuture) resultKpiFuture.textContent = String(matches.filter((match) => matchTimingStatus(match).key === 'future').length);
    if (resultFilterCount) resultFilterCount.textContent = `${matches.length} visibles`;
    renderOperationalAlerts();
    if (!resultQueue) return;
    if (!matches.length) {
      resultQueue.innerHTML = '<tr><td colspan="5"><div class="empty-state">No hay partidas con los filtros actuales.</div></td></tr>';
      return;
    }
    resultQueue.innerHTML = matches.map((match) => {
      const timing = matchTimingStatus(match);
      const state = String(match.estado || 'PENDIENTE').toUpperCase();
      const actionLabel = state === 'FINALIZADA' ? 'Revisar' : (state.includes('CURSO') ? 'Continuar' : 'Capturar');
      const actionIcon = state === 'FINALIZADA' ? 'bi-eye' : (state.includes('CURSO') ? 'bi-play-circle' : 'bi-pencil-square');
      return `
      <tr class="${String(resultMatch?.value || '') === String(match.id) ? 'selected' : ''} timing-${timing.key}">
        <td data-label="Programacion"><div class="result-schedule-cell"><strong>${match.scheduled_on || 'Sin fecha'}<span>${match.starts_at ? match.starts_at.slice(0, 5) : 'Sin hora'}</span></strong><div class="result-event-context">${resultMatchEventContext(match)}<small>Ronda ${match.ronda} · Mesa ${match.mesa} | ${String(match.match_format || '1v1').toUpperCase()} | BO${match.best_of || 5}</small></div><span class="status-chip ${timing.className}">${timing.label}</span></div></td>
        <td data-label="Enfrentamiento"><div class="result-versus-cell"><div>${resultTeamLogoCell(match, 'a')}</div><b>VS</b><div>${resultTeamLogoCell(match, 'b')}</div></div></td>
        <td data-label="Estado"><span class="status-chip">${match.estado || 'PENDIENTE'}</span></td>
        <td data-label="Marcador"><strong class="result-score-value">${match.score || '0-0'}</strong></td>
        <td data-label="Accion" class="result-queue-action"><button class="btn btn-outline-info btn-sm" type="button" data-result-pick="${match.id}"><i class="bi ${actionIcon}"></i> ${actionLabel}</button></td>
      </tr>`;
    }).join('');
    Array.from(resultQueue.querySelectorAll('[data-result-pick]')).forEach((button) => {
      button.addEventListener('click', () => {
        if (resultMatch) resultMatch.value = button.dataset.resultPick;
        loadResult();
        openResultCapture(button.dataset.resultPick);
      });
    });
  }

  function formatResultLp(value) {
    return new Intl.NumberFormat('es-CO').format(Math.max(0, Math.round(Number(value) || 0)));
  }

  function calculateStandardStep(state, key) {
    return window.StationXLpWorkspace.step(state, key);
  }

  function renderResultStandardCalculator() {
    const display = resultCaptureModalNode?.querySelector('[data-result-standard-display]');
    if (display) display.textContent = resultStandardState.display;
    const expression = resultCaptureModalNode?.querySelector('[data-result-standard-expression]');
    if (expression) expression.textContent = resultStandardState.expression || '0';
  }

  function formatResultLpTimer(seconds) {
    const value = Math.max(0, Math.floor(Number(seconds) || 0));
    return [Math.floor(value / 3600), Math.floor((value % 3600) / 60), value % 60].map((part) => String(part).padStart(2, '0')).join(':');
  }

  function resultLpStorageKey(duel) {
    return `dml_result_lp_${activeResultCaptureMatchId || 'match'}_${duel || 1}`;
  }

  function stopResultLpTimer() {
    if (resultLpTimerTick) window.clearInterval(resultLpTimerTick);
    resultLpTimerTick = null;
  }

  function saveResultLpState() {
    if (!resultLpState) return;
    localStorage.setItem(resultLpStorageKey(resultLpState.duel), JSON.stringify({ ...resultLpState, running: false }));
  }

  function applyResultLpStateToDuel(syncLive = true) {
    if (!resultLpState || !resultCaptureModalNode) return;
    const lpA = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${resultLpState.duel}"]`);
    const lpB = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${resultLpState.duel}"]`);
    const beforeA = Number(lpA?.value), beforeB = Number(lpB?.value);
    if (lpA) lpA.value = String(Math.max(0, Math.round(resultLpState.a)));
    if (lpB) lpB.value = String(Math.max(0, Math.round(resultLpState.b)));
    void window.StationXLpSound?.change(beforeA, beforeB, Number(lpA?.value), Number(lpB?.value));
    saveResultLpState();
    setResultSaveState('pending');
    syncResultCaptureSummary();
    if (syncLive) scheduleJudgeLiveSync();
  }

  function renderResultLpCalculator() {
    const calculator = resultCaptureModalNode?.querySelector('[data-result-lp-calculator]');
    if (!calculator || !resultLpState) return;
    ['a', 'b'].forEach((side) => {
      const value = calculator.querySelector(`[data-result-lp-value="${side}"]`);
      const sideButton = calculator.querySelector(`[data-result-lp-side="${side}"]`);
      if (value) value.textContent = formatResultLp(resultLpState[side]);
      if (sideButton) sideButton.classList.toggle('active', resultLpState.side === side);
    });
    const entry = calculator.querySelector('[data-result-lp-entry]');
    if (entry) entry.textContent = formatResultLp(Number(resultLpState.entry || 0));
    const timer = calculator.querySelector('[data-result-lp-timer]');
    if (timer) timer.textContent = formatResultLpTimer(resultLpState.timer);
    const timerButton = calculator.querySelector('[data-result-lp-timer-toggle]');
    if (timerButton) timerButton.innerHTML = resultLpState.running ? '<i class="bi bi-pause-fill"></i>' : '<i class="bi bi-play-fill"></i>';
    ['a', 'b'].forEach((side) => {
      const start = calculator.querySelector(`[data-result-lp-start="${side}"]`);
      if (start && document.activeElement !== start) start.value = String(resultLpState[`start_${side}`] || 8000);
    });
    const history = calculator.querySelector('[data-result-lp-history]');
    if (history) {
      history.innerHTML = resultLpState.history.length
        ? resultLpState.history.slice(0, 6).map((item) => `<span><b>${escapeHtml(item.name)}</b> ${escapeHtml(item.action)} <strong>${formatResultLp(item.before)} â†’ ${formatResultLp(item.after)}</strong></span>`).join('')
        : 'Sin movimientos registrados.';
    }
    saveResultLpState();
  }

  function openResultLpCalculator(duel) {
    const calculator = resultCaptureModalNode?.querySelector('[data-result-lp-calculator]');
    const match = selectedCaptureMatch();
    const openButton = resultCaptureModalNode?.querySelector(`[data-result-lp-open="${duel}"]`);
    if (!calculator || !match || openButton?.disabled) return;
    const lpAInput = resultCaptureModalNode.querySelector(`[data-result-capture-lp-a="${duel}"]`);
    const lpBInput = resultCaptureModalNode.querySelector(`[data-result-capture-lp-b="${duel}"]`);
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(resultLpStorageKey(duel)) || '{}'); } catch (error) { saved = {}; }
    resultLpState = {
      duel,
      a: lpAInput?.value !== '' ? Math.max(0, Number(lpAInput.value)) : (Number.isFinite(Number(saved.a)) ? Math.max(0, Number(saved.a)) : 8000),
      b: lpBInput?.value !== '' ? Math.max(0, Number(lpBInput.value)) : (Number.isFinite(Number(saved.b)) ? Math.max(0, Number(saved.b)) : 8000),
      side: ['a', 'b'].includes(saved.side) ? saved.side : 'a',
      entry: '',
      timer: Math.max(0, Number(saved.timer || 0)),
      running: false,
      history: Array.isArray(saved.history) ? saved.history.slice(0, 100) : []
      ,start_a: Math.max(0, Number(saved.start_a || 8000))
      ,start_b: Math.max(0, Number(saved.start_b || 8000))
    };
    resultStandardState = { display: '0', accumulator: null, operator: '', replace: true };
    const duelLabel = calculator.querySelector('[data-result-lp-duel]');
    const nameA = calculator.querySelector('[data-result-lp-name="a"]');
    const nameB = calculator.querySelector('[data-result-lp-name="b"]');
    if (duelLabel) duelLabel.textContent = `D${duel}`;
    if (nameA) nameA.textContent = match.team_a || match.a || 'Equipo A';
    if (nameB) nameB.textContent = match.team_b || match.b || 'Equipo B';
    calculator.hidden = false;
    calculator.scrollTop = 0;
    renderResultLpCalculator();
    renderResultStandardCalculator();
  }

  function closeResultLpCalculator() {
    window.StationXLpWorkspace?.close();
    const calculator = resultCaptureModalNode?.querySelector('[data-result-lp-calculator]');
    if (resultLpState?.running) resultLpState.running = false;
    stopResultLpTimer();
    saveResultLpState();
    if (calculator) calculator.hidden = true;
  }

  async function offerResultLpVictory() {
    const match = selectedCaptureMatch();
    if (!match || !resultLpState) return;
    const duel = resultLpState.duel, matchId = String(match.id);
    const lp = side => Number(resultCaptureModalNode.querySelector(`[data-result-capture-lp-${side}="${duel}"]`)?.value);
    const winner = lp('a') === 0 && lp('b') > 0 ? 'B' : lp('b') === 0 && lp('a') > 0 ? 'A' : '';
    const control = resultCaptureModalNode.querySelector(`[data-result-capture-duel="${duel}"]`);
    const runtime = duelTimerState.get(duel);
    if (!winner || !control || control.disabled || control.value || !runtime?.duelStarted) return;
    const name = window.StationXParticipants?.participant(match, winner) || matchSideLabel(match, winner.toLowerCase());
    const loser = winner === 'A' ? 'B' : 'A';
    const loserName = window.StationXParticipants?.participant(match, loser) || matchSideLabel(match, loser.toLowerCase());
    const accepted = await window.StationXLpWorkspace.confirmVictory({text: `${loserName} quedó en 0 LP. ¿Deseas otorgar D${duel} a ${name} y guardar esta partida por puntos de vida?`});
    if (!accepted || String(activeResultCaptureMatchId) !== matchId || resultLpState?.duel !== duel || control.disabled || control.value) return;
    const stillWinner = lp('a') === 0 && lp('b') > 0 ? 'B' : lp('b') === 0 && lp('a') > 0 ? 'A' : '';
    if (stillWinner !== winner) return;
    runtime.baseElapsed = duelTimerElapsed(runtime); runtime.running = false; runtime.startedAt = null;
    duelTimerState.set(duel, runtime); persistDuelTimer(duel);
    control.value = winner;
    resultCaptureModalNode.querySelector(`[data-result-capture-method="${duel}"]`).value = 'life_points';
    setResultSaveState('pending'); syncResultCaptureSummary(); closeResultLpCalculator();
    const form = resultCaptureModalNode.querySelector('[data-result-capture-form]');
    const submit = form?.querySelector('[data-result-capture-save]');
    if (form && submit && !submit.disabled) form.requestSubmit(submit);
  }

  function changeResultLp(operation) {
    if (!resultLpState) return;
    const side = resultLpState.side;
    const before = Math.max(0, Number(resultLpState[side]) || 0);
    const amount = Math.max(0, Number(resultLpState.entry || 0));
    let after = before;
    let action = '';
    if (operation === 'subtract' && amount) { after = Math.max(0, before - amount); action = `-${formatResultLp(amount)}`; }
    if (operation === 'add' && amount) { after = before + amount; action = `+${formatResultLp(amount)}`; }
    if (operation === 'half') { after = Math.floor(before / 2); action = 'Mitad'; }
    if (operation === 'reset') { after = Number(resultLpState[`start_${side}`] || 8000); action = 'Reiniciar'; }
    if (!action) return;
    const match = selectedCaptureMatch();
    resultLpState[side] = after;
    resultLpState.history.unshift({ side, name: side === 'a' ? (match?.team_a || 'Equipo A') : (match?.team_b || 'Equipo B'), operation, amount, action, before, after });
    resultLpState.history = resultLpState.history.slice(0, 100);
    resultLpState.entry = '';
    renderResultLpCalculator();
    applyResultLpStateToDuel();
    if (before > 0 && after === 0 && ['subtract', 'half'].includes(operation)) void offerResultLpVictory();
  }

  if (resultCaptureModalNode) {
    resultCaptureModalNode.querySelector('[data-modify-captured-match]')?.addEventListener('click', () => {
      window.clearTimeout(liveDuelSyncTimer);
      window.dispatchEvent(new CustomEvent('dml:modify-match', {detail:{matchId:activeResultCaptureMatchId,mode:'full',parentId:'resultCaptureModal',draft:{duels:captureDuelDetails(),values:Array.from({length:5},(_,i)=>resultCaptureModalNode.querySelector(`[data-result-capture-duel="${i+1}"]`)?.value||''),notes:resultCaptureModalNode.querySelector('[data-result-capture-notes]')?.value||''}}}));
    });
    window.addEventListener('dml:resume-result', () => {
      resultCaptureModal.show();
      localStorage.setItem('dml_result_capture_match_id',String(activeResultCaptureMatchId));
      window.clearInterval(liveDuelPollTimer);syncJudgeLiveDuel(false);liveDuelPollTimer=window.setInterval(()=>syncJudgeLiveDuel(false),1800);
    });
    resultCaptureModalNode.addEventListener('shown.bs.modal', () => {
      resultCaptureModalNode.scrollTop = 0;
      const dialog = resultCaptureModalNode.querySelector('.result-capture-dialog');
      const body = resultCaptureModalNode.querySelector('.result-console-body');
      if (dialog) dialog.scrollTop = 0;
      if (body) body.scrollTop = 0;
    });
    resultCaptureModalNode.addEventListener('hidden.bs.modal', () => {
      closeResultLpCalculator();
      const terminals = resultCaptureModalNode.querySelector('[data-result-terminals-sheet]');
      if (terminals) {
        terminals.classList.remove('is-open');
        terminals.setAttribute('aria-hidden', 'true');
      }
      resultCaptureModalNode.querySelectorAll('[data-result-outcome-sheet]').forEach((sheet) => {
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
      });
      const historySheet = resultCaptureModalNode.querySelector('[data-result-history-sheet]');
      if (historySheet) {
        historySheet.classList.remove('is-open');
        historySheet.setAttribute('aria-hidden', 'true');
      }
      window.clearInterval(liveDuelPollTimer);
      window.clearTimeout(liveDuelSyncTimer);
      liveDuelPollTimer = null;
      localStorage.removeItem('dml_result_capture_match_id');
      localStorage.removeItem('dml_result_capture_route');
    });
    resultCaptureModalNode.querySelectorAll('[data-result-player-copy]').forEach((button) => button.addEventListener('click', async () => {
      const url = button.dataset.url || '';
      if (!url) return;
      try {
        await navigator.clipboard.writeText(url);
        setLiveDuelStatus(`Enlace del lado ${button.dataset.resultPlayerCopy} copiado`, 'online');
      } catch (error) {
        window.prompt('Copia este enlace:', url);
      }
    }));
    const terminalsSheet = resultCaptureModalNode.querySelector('[data-result-terminals-sheet]');
    const setTerminalsOpen = (open) => {
      if (!terminalsSheet) return;
      terminalsSheet.classList.toggle('is-open', open);
      terminalsSheet.setAttribute('aria-hidden', open ? 'false' : 'true');
    };
    resultCaptureModalNode.querySelector('[data-result-terminals-open]')?.addEventListener('click', () => setTerminalsOpen(true));
    resultCaptureModalNode.querySelectorAll('[data-result-terminals-close]').forEach((button) => button.addEventListener('click', () => setTerminalsOpen(false)));
    const setOutcomeOpen = (duel, open) => {
      resultCaptureModalNode.querySelectorAll('[data-result-outcome-sheet]').forEach((sheet) => {
        const selected = open && Number(sheet.dataset.resultOutcomeSheet || 0) === Number(duel);
        sheet.classList.toggle('is-open', selected);
        sheet.setAttribute('aria-hidden', selected ? 'false' : 'true');
      });
    };
    resultCaptureModalNode.querySelectorAll('[data-result-outcome-open]').forEach((button) => button.addEventListener('click', () => {
      if (!button.disabled) setOutcomeOpen(Number(button.dataset.resultOutcomeOpen || 0), true);
    }));
    resultCaptureModalNode.querySelectorAll('[data-result-outcome-close]').forEach((button) => button.addEventListener('click', () => setOutcomeOpen(Number(button.dataset.resultOutcomeClose || 0), false)));
    const historySheet = resultCaptureModalNode.querySelector('[data-result-history-sheet]');
    const setHistoryOpen = (open) => {
      if (!historySheet) return;
      historySheet.classList.toggle('is-open', open);
      historySheet.setAttribute('aria-hidden', open ? 'false' : 'true');
    };
    resultCaptureModalNode.querySelector('[data-result-history-open]')?.addEventListener('click', () => setHistoryOpen(true));
    resultCaptureModalNode.querySelectorAll('[data-result-history-close]').forEach((button) => button.addEventListener('click', () => setHistoryOpen(false)));
    resultCaptureModalNode.querySelectorAll('[data-result-capture-duel], [data-result-capture-method], [data-result-capture-lp-a], [data-result-capture-lp-b], [data-result-capture-deck-a], [data-result-capture-deck-b], [data-result-capture-player-a], [data-result-capture-player-b], [data-result-capture-turns], [data-result-capture-brick-a], [data-result-capture-brick-b], [data-result-capture-origin], [data-result-capture-duel-note], [data-result-capture-notes]').forEach((control) => {
      const changed = () => {
        setResultSaveState('pending');
        syncResultCaptureSummary();
        scheduleJudgeLiveSync();
      };
      control.addEventListener('input', changed);
      control.addEventListener('change', changed);
    });
    resultCaptureModalNode.querySelectorAll('[data-result-capture-method]').forEach((control) => control.addEventListener('change', () => {
      const duel = Number(control.dataset.resultCaptureMethod || 0);
      if (control.value === 'surrender' && !validateSurrenderTurn(duel, true)) {
        control.value = '';
        syncResultCaptureSummary();
        return;
      }
      if (control.value !== 'time_limit') return;
      finishDuelByTime(duel);
    }));
    resultCaptureModalNode.querySelectorAll('[data-result-lp-open]').forEach((button) => {
      button.addEventListener('click', () => openResultLpCalculator(Number(button.dataset.resultLpOpen || 1)));
    });
    resultCaptureModalNode.querySelectorAll('[data-result-lp-close]').forEach((button) => button.addEventListener('click', closeResultLpCalculator));
    resultCaptureModalNode.querySelectorAll('[data-result-lp-side]').forEach((button) => {
      button.addEventListener('click', () => {
        if (!resultLpState) return;
        resultLpState.side = button.dataset.resultLpSide || 'a';
        resultLpState.entry = '';
        renderResultLpCalculator();
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-lp-key]').forEach((button) => {
      button.addEventListener('click', () => {
        if (!resultLpState) return;
        resultLpState.entry = `${resultLpState.entry || ''}${button.dataset.resultLpKey || ''}`.replace(/^0+(?=\d)/, '').slice(0, 6);
        renderResultLpCalculator();
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-lp-op]').forEach((button) => {
      button.addEventListener('click', () => {
        const operation = button.dataset.resultLpOp || '';
        if (!resultLpState) return;
        if (operation === 'clear') {
          resultLpState.entry = '';
          renderResultLpCalculator();
          return;
        }
        if (operation === 'undo') {
          const previous = resultLpState.history.shift();
          if (previous && ['a', 'b'].includes(previous.side)) resultLpState[previous.side] = previous.before;
          renderResultLpCalculator();
          applyResultLpStateToDuel();
          return;
        }
        changeResultLp(operation);
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-standard-key]').forEach((button) => {
      button.addEventListener('click', () => {
        resultStandardState = calculateStandardStep(resultStandardState, button.dataset.resultStandardKey || '');
        renderResultStandardCalculator();
      });
    });
    const resultStandardUse = resultCaptureModalNode.querySelector('[data-result-standard-use]');
    if (resultStandardUse) resultStandardUse.addEventListener('click', () => {
      if (!resultLpState) return;
      const value = Number(resultStandardState.display);
      if (!Number.isFinite(value)) {
        window.alert('La operacion no tiene un resultado valido.');
        return;
      }
      resultLpState.entry = String(Math.max(0, Math.min(99999, Math.round(Math.abs(value)))));
      renderResultLpCalculator();
      window.StationXLpWorkspace.close();
    });
    const resultLpApply = resultCaptureModalNode.querySelector('[data-result-lp-apply]');
    if (resultLpApply) resultLpApply.addEventListener('click', () => {
      if (!resultLpState) return;
      applyResultLpStateToDuel();
      closeResultLpCalculator();
    });
    const resultLpStartApply = resultCaptureModalNode.querySelector('[data-result-lp-start-apply]');
    if (resultLpStartApply) resultLpStartApply.addEventListener('click', () => {
      if (!resultLpState || resultLpState.history.length) return;
      ['a', 'b'].forEach((side) => {
        const input = resultCaptureModalNode.querySelector(`[data-result-lp-start="${side}"]`);
        const start = Math.max(1, Number(input?.value || 8000));
        resultLpState[`start_${side}`] = start;
        resultLpState[side] = start;
      });
      renderResultLpCalculator();
    });
    const resultLpTimerToggle = resultCaptureModalNode.querySelector('[data-result-lp-timer-toggle]');
    if (resultLpTimerToggle) resultLpTimerToggle.addEventListener('click', () => {
      if (!resultLpState) return;
      resultLpState.running = !resultLpState.running;
      stopResultLpTimer();
      if (resultLpState.running) {
        resultLpTimerTick = window.setInterval(() => {
          if (!resultLpState?.running) return;
          resultLpState.timer += 1;
          renderResultLpCalculator();
        }, 1000);
      }
      renderResultLpCalculator();
    });
    const resultLpTimerReset = resultCaptureModalNode.querySelector('[data-result-lp-timer-reset]');
    if (resultLpTimerReset) resultLpTimerReset.addEventListener('click', () => {
      if (!resultLpState) return;
      resultLpState.timer = 0;
      resultLpState.running = false;
      stopResultLpTimer();
      renderResultLpCalculator();
    });
    const rollButton = resultCaptureModalNode.querySelector('[data-result-roll-dice]');
    if (rollButton) rollButton.addEventListener('click', () => {
      setResultSaveState('pending');
      const openingRoll = rollOpeningDice();
      const { a, b, winner, attempts } = openingRoll;
      const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
      const diceSummary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
      const panel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
      const question = resultCaptureModalNode.querySelector('[data-result-turn-choice-question]');
      const roll = { a, b, winner, attempts, rerolls: Math.max(0, attempts.length - 1), rolled_at: new Date().toISOString() };
      const turnInput = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
      if (turnInput) turnInput.value = '';
      if (diceInput) diceInput.value = JSON.stringify(roll);
      if (diceSummary) diceSummary.textContent = 'Lanzando dados...';
      if (panel) panel.hidden = true;
      const d1DiceA = resultCaptureModalNode.querySelector('[data-result-duel-dice-a="1"]');
      const d1DiceB = resultCaptureModalNode.querySelector('[data-result-duel-dice-b="1"]');
      if (d1DiceA) {
        d1DiceA.textContent = '-';
        d1DiceA.dataset.value = '';
      }
      if (d1DiceB) {
        d1DiceB.textContent = '-';
        d1DiceB.dataset.value = '';
      }
      updateResultDie('[data-result-die-a]', 1, true);
      updateResultDie('[data-result-die-b]', 1, true);
      window.setTimeout(() => {
        updateResultDie('[data-result-die-a]', a, false);
        updateResultDie('[data-result-die-b]', b, false);
        if (d1DiceA) {
          d1DiceA.textContent = String(a);
          d1DiceA.dataset.value = String(a);
        }
        if (d1DiceB) {
          d1DiceB.textContent = String(b);
          d1DiceB.dataset.value = String(b);
        }
        if (diceSummary) diceSummary.textContent = `A saco ${a}, B saco ${b}. Gano ${winner}. Falta elegir primero o segundo.`;
        if (question) question.textContent = `${winner} gano el dado. Que elige?`;
        if (panel) panel.hidden = false;
        scheduleJudgeLiveSync();
      }, 1250);
    });
    resultCaptureModalNode.querySelectorAll('[data-result-turn-select]').forEach((button) => {
      button.addEventListener('click', () => {
        const dice = parseJsonValue(resultCaptureModalNode.querySelector('[data-result-capture-dice-json]')?.value || '', null);
        if (!dice || !dice.winner) return;
        const choice = button.dataset.resultTurnSelect === 'second' ? 'segundo' : 'primero';
        const starts = choice === 'primero' ? dice.winner : (dice.winner === 'A' ? 'B' : 'A');
        const text = `${dice.winner} eligio ${choice}. Empieza ${starts}.`;
        const input = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
        const summary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
        const panel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
        const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
        const updatedDice = { ...dice, choice, starts, chosen_by: dice.winner, choice_recorded_at: new Date().toISOString() };
        if (input) input.value = text;
        if (diceInput) diceInput.value = JSON.stringify(updatedDice);
        const firstTurnInput = resultCaptureModalNode.querySelector('[data-result-duel-turn-choice="1"]');
        if (firstTurnInput) firstTurnInput.value = JSON.stringify({ chosen_by: dice.winner, choice, starts, reason: 'dice' });
        if (summary) summary.textContent = `A saco ${dice.a}, B saco ${dice.b}. ${text}`;
        if (panel) panel.hidden = true;
        setResultSaveState('pending');
        syncResultCaptureSummary();
        scheduleJudgeLiveSync();
      });
    });
    resultCaptureModalNode.querySelectorAll('[data-result-duel-turn-select]').forEach((button) => {
      button.addEventListener('click', () => {
        const n = Number(button.dataset.resultDuelTurnSelect || 0);
        if (!n) return;
        const details = captureDuelDetails();
        const previousWinner = n > 1 ? details[n - 2]?.winner || '' : '';
        const dice = parseJsonValue(resultCaptureModalNode.querySelector('[data-result-capture-dice-json]')?.value || '', null);
        const chosenBy = n === 1
          ? (dice?.chosen_by || dice?.winner || '')
          : (previousWinner === 'A' ? 'B' : previousWinner === 'B' ? 'A' : previousWinner === 'E' ? (details[n - 1]?.dice_winner || details[n - 1]?.turn_chosen_by || '') : '');
        if (!chosenBy) return;
        const choice = normalizeTurnChoice(button.dataset.choice || '');
        const starts = computeStartsPlayer(chosenBy, choice);
        const hidden = resultCaptureModalNode.querySelector(`[data-result-duel-turn-choice="${n}"]`);
        if (hidden) hidden.value = JSON.stringify({ chosen_by: chosenBy, dice_winner: previousWinner === 'E' ? chosenBy : '', choice, starts, reason: n === 1 ? 'dice' : (previousWinner === 'E' ? 'draw_reroll' : 'previous_duel_loss'), recorded_at: new Date().toISOString() });
        setResultSaveState('pending');
        if (n === 1) {
          const diceInput = resultCaptureModalNode.querySelector('[data-result-capture-dice-json]');
          const turnInput = resultCaptureModalNode.querySelector('[data-result-capture-turn-choice]');
          const summary = resultCaptureModalNode.querySelector('[data-result-capture-dice-summary]');
          const panel = resultCaptureModalNode.querySelector('[data-result-turn-choice-panel]');
          const updatedDice = { ...(dice || {}), chosen_by: chosenBy, choice, starts, choice_recorded_at: new Date().toISOString() };
          if (diceInput) diceInput.value = JSON.stringify(updatedDice);
          if (turnInput) turnInput.value = turnText(chosenBy, choice, starts);
          if (summary && dice) summary.textContent = `A saco ${dice.a}, B saco ${dice.b}. ${turnText(chosenBy, choice, starts)}`;
          if (panel) panel.hidden = true;
        }
        syncResultCaptureSummary();
        scheduleJudgeLiveSync();
      });
    });
    duelTimerConfirmNode?.querySelector('[data-duel-start-without-timer]')?.addEventListener('click', () => {
      const duel = pendingTimerDuel;
      closeDuelTimerConfirm();
      if (duel) beginDuel(duel, 'none');
    });
    duelTimerConfirmNode?.querySelector('[data-duel-start-with-timer]')?.addEventListener('click', () => {
      const duel = pendingTimerDuel;
      closeDuelTimerConfirm();
      if (duel) beginDuel(duel, 'countdown');
    });
    duelTimerConfirmNode?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => button.addEventListener('click', closeDuelTimerConfirm));
    const form = resultCaptureModalNode.querySelector('[data-result-capture-form]');
    if (form) form.addEventListener('submit', (event) => {
      const invalidSurrender = Array.from({ length: 5 }, (_, index) => index + 1).some((duel) => !validateSurrenderTurn(duel, false));
      if (invalidSurrender) {
        event.preventDefault();
        window.alert('No se guardo: una rendicion fue registrada fuera del turno del jugador que se rinde.');
        setResultSaveState('pending');
        return;
      }
      syncResultCaptureSummary();
      setResultSaveState('saving');
      // Los resultados cerrados permanecen bloqueados en pantalla, pero deben viajar en el POST.
      form.querySelectorAll('[data-result-capture-duel]:disabled').forEach((control) => {
        control.disabled = false;
      });
      if (!navigator.onLine) {
        event.preventDefault();
        const requestId = makeRequestId();
        const formData = new FormData(form);
        formData.set('offline_request_id', requestId);
        const payload = Array.from(formData.entries()).map(([key, value]) => [key, String(value)]);
        const queue = readOfflineQueue();
        queue.push({ id: requestId, payload, queuedAt: new Date().toISOString(), attempts: 0 });
        writeOfflineQueue(queue);
        setResultSaveState('pending');
        window.alert(`Sin conexion. El cambio quedo guardado en este dispositivo. Hay ${queue.length} ${queue.length === 1 ? 'envio pendiente' : 'envios pendientes'}.`);
      }
    });
  }

  syncResultRoundOptions();

  const qualifierMatchSearch = document.querySelector('[data-qualifier-match-search]');
  const qualifierMatchStatus = document.querySelector('[data-qualifier-match-status]');
  const qualifierMatchRound = document.querySelector('[data-qualifier-match-round]');
  const qualifierMatchOrder = document.querySelector('[data-qualifier-match-order]');
  const qualifierMatchList = document.querySelector('[data-qualifier-match-list]');
  const qualifierClearFilters = document.querySelector('[data-qualifier-clear-filters]');
  const qualifierRankingOrder = document.querySelector('[data-qualifier-ranking-order]');
  const qualifierRankingBody = document.querySelector('[data-qualifier-ranking-body]');
  const qualifierScreen = document.querySelector('[data-screen="clasificatorio"]');
  const selectedQualifierEventId = String(qualifierScreen?.dataset.qualifierEventId || '');
  const matchSheetModalNode = document.querySelector('[data-match-sheet-modal]');
  const matchSheetModal = matchSheetModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(matchSheetModalNode) : null;
  const qualifierDeckSheetModalNode = document.querySelector('[data-qualifier-deck-sheet-modal]');
  const qualifierDeckSheetModal = qualifierDeckSheetModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(qualifierDeckSheetModalNode) : null;
  let activeSheetMatchId = '';
  let activeQualifierDeckName = '';

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
    return `<span class="qualifier-match-side"><span class="team-inline-badge${logo ? ' has-image' : ''}">${logo ? `<img loading="lazy" decoding="async" src="${escapeHtml(logo)}" alt="${escapeHtml(team)}">` : escapeHtml(fallback)}</span><span><strong>${escapeHtml(deck)}</strong><small>${escapeHtml(team)}</small></span></span>`;
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
      ? `<img loading="lazy" decoding="async" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(altText || fallback)}">`
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

  function fillDeckSelect() {
    if (!deckSelect) return;
    deckSelect.innerHTML = data.allDecks.map((deck) => `<option value="${deck.name}">${deck.name} - ${deck.division}</option>`).join('');
    const requestedId = new URLSearchParams(window.location.search).get('deck_id') || '';
    const requestedDeck = data.allDecks.find((deck) => String(deck.id || '') === requestedId);
    const deckWithResults = data.allDecks.find((deck) => allMatchList().some((match) => {
      const participates = [match.deck_a_id, match.deck_b_id, match.deck_c_id, match.deck_d_id, match.deck_e_id, match.deck_f_id].some((id) => String(id || '') === String(deck.id || ''));
      return participates && evaluateDuel(getSavedResult(match), match).state === 'FINALIZADA';
    }));
    if (requestedDeck) deckSelect.value = requestedDeck.name;
    else if (deckWithResults) deckSelect.value = deckWithResults.name;
    deckSelect.addEventListener('change', renderProfile);
  }

  function deckProfileCompetitiveStats(deck) {
    if(data.statisticsV8&&window.YgoStatistics)return window.YgoStatistics.compatProfile(deck);
    const summary = { points: 0, matches: 0, wins: 0, draws: 0, losses: 0, duelWins: 0, duelDraws: 0, duelLosses: 0, damageDealt: 0, damageReceived: 0, lpCovered: 0, lpTotal: 0, firstStarts: 0, firstWins: 0, secondStarts: 0, secondWins: 0, brickHands: 0, brickWins: 0, turnsRecorded: 0, totalTurns: 0, fastestWinTurns: 0, durationRecorded: 0, totalDuration: 0, fastestWinSeconds: 0, surrenderWins: 0, surrenderLosses: 0, lifePointWins: 0, lifePointLosses: 0, deckOutWins: 0, timeWins: 0, effectWins: 0, flawlessWins: 0, perfectMatches: 0, dicePlayed: 0, diceWins: 0, d5Played: 0, d5Wins: 0, comebackWins: 0, reverseSweeps: 0, bestMatchStreak: 0, opponents: new Map(), formats: new Set() };
    (deck.format_codes || []).forEach((format) => summary.formats.add(String(format).replaceAll('_', ' ').toUpperCase()));
    (data.competitions || []).forEach((competition) => {
      if(competition.teamConfig)return;
      const row = (competition.ranking || []).find((item) => String(item.id || item.deck_id || '') === String(deck.id || '') || normalize(item.name || '') === normalize(deck.name));
      if (row) summary.points += Number(row.pts || row.points || 0);
    });
    individualMatchList().forEach((match) => {
      const onA = matchSideDecks(match, 'a').includes(deck.name) || [match.deck_a_id, match.deck_c_id, match.deck_e_id].some((id) => String(id || '') === String(deck.id || ''));
      const onB = matchSideDecks(match, 'b').includes(deck.name) || [match.deck_b_id, match.deck_d_id, match.deck_f_id].some((id) => String(id || '') === String(deck.id || ''));
      if (!onA && !onB) return;
      const competition = (data.competitions || []).find((item) => String(item.id || '') === String(match.competition_id || '')) || {};
      const format = match.competition_ruleset || competition.event_ruleset || '';
      if (format) summary.formats.add(String(format).replaceAll('_', ' ').toUpperCase());
      const values = getSavedResult(match).filter(Boolean);
      const result = evaluateDuel(values, match);
      if (result.state !== 'FINALIZADA') return;
      const side = onA ? 'A' : 'B';
      summary.matches += 1;
      if (result.winner === side) summary.wins += 1;
      else if (result.winner === 'E') summary.draws += 1;
      else summary.losses += 1;
      if (result.winner === side && !values.some((winner) => winner !== side)) summary.perfectMatches += 1;
      const rivalSide = side === 'A' ? 'B' : 'A';
      if (values[4]) { summary.d5Played += 1; if (values[4] === side) summary.d5Wins += 1; }
      if (result.winner === side && values[0] === rivalSide) summary.comebackWins += 1;
      if (result.winner === side && values[0] === rivalSide && values[1] === rivalSide && values.slice(2).filter(Boolean).every((winner) => winner === side)) summary.reverseSweeps += 1;
      const rivalNameForMatch = matchSideDecks(match, side === 'A' ? 'b' : 'a')[0] || 'Rival sin identificar';
      const matchRival = summary.opponents.get(rivalNameForMatch) || { name: rivalNameForMatch, matchWins: 0, matchLosses: 0, matchDraws: 0, wins: 0, losses: 0, draws: 0, duels: 0 };
      if (result.winner === side) matchRival.matchWins += 1;
      else if (result.winner === 'E') matchRival.matchDraws += 1;
      else matchRival.matchLosses += 1;
      summary.opponents.set(rivalNameForMatch, matchRival);
      const details = parseJsonValue(match.duel_details || '', []);
      values.forEach((winner, index) => {
        const detail = Array.isArray(details) ? (details.find((item) => Number(item.duel) === index + 1) || {}) : {};
        const attributedId = Number(side === 'A' ? detail.deck_a_id : detail.deck_b_id);
        if (attributedId && attributedId !== Number(deck.id)) return;
        summary.lpTotal += 1;
        if (winner === side) summary.duelWins += 1;
        else if (winner === 'E') summary.duelDraws += 1;
        else summary.duelLosses += 1;
        const brick = side === 'A' ? Boolean(detail.brick_a) : Boolean(detail.brick_b);
        if (brick) summary.brickHands += 1;
        if (brick && winner === side) summary.brickWins += 1;
        const turnCount = Math.max(0, Number(detail.turn_count || 0));
        if (turnCount) {
          summary.turnsRecorded += 1;
          summary.totalTurns += turnCount;
          if (winner === side && (!summary.fastestWinTurns || turnCount < summary.fastestWinTurns)) summary.fastestWinTurns = turnCount;
        }
        const duration = Math.max(0, Number(detail.duration_seconds || 0));
        if (duration) {
          summary.durationRecorded += 1;
          summary.totalDuration += duration;
          if (winner === side && (!summary.fastestWinSeconds || duration < summary.fastestWinSeconds)) summary.fastestWinSeconds = duration;
        }
        const method = String(detail.defeat_method || '');
        if (method === 'surrender') winner === side ? summary.surrenderWins += 1 : (winner !== 'E' ? summary.surrenderLosses += 1 : 0);
        if (method === 'life_points') winner === side ? summary.lifePointWins += 1 : (winner !== 'E' ? summary.lifePointLosses += 1 : 0);
        if (method === 'deck_out' && winner === side) summary.deckOutWins += 1;
        if (method === 'time_limit' && winner === side) summary.timeWins += 1;
        if (method === 'effect_win' && winner === side) summary.effectWins += 1;
        const diceOwn = Number(side === 'A' ? detail.dice_a : detail.dice_b);
        const diceRival = Number(side === 'A' ? detail.dice_b : detail.dice_a);
        if (diceOwn > 0 && diceRival > 0) { summary.dicePlayed += 1; if (diceOwn > diceRival) summary.diceWins += 1; }
        const rivalId = Number(side === 'A' ? detail.deck_b_id : detail.deck_a_id);
        const rivalDeck = (data.allDecks || []).find((item) => Number(item.id) === rivalId);
        const rivalName = rivalDeck?.name || matchSideDecks(match, side === 'A' ? 'b' : 'a')[0] || 'Rival sin identificar';
        const rivalStat = summary.opponents.get(rivalName) || { name: rivalName, matchWins: 0, matchLosses: 0, matchDraws: 0, wins: 0, losses: 0, draws: 0, duels: 0 };
        rivalStat.duels += 1;
        if (winner === side) rivalStat.wins += 1;
        else if (winner === 'E') rivalStat.draws += 1;
        else rivalStat.losses += 1;
        summary.opponents.set(rivalName, rivalStat);
        const starts = String(detail.starts_player || (index === 0 ? match.starts_player || '' : '')).toUpperCase();
        if (starts) {
          const first = starts === side;
          if (first) { summary.firstStarts += 1; if (winner === side) summary.firstWins += 1; }
          else { summary.secondStarts += 1; if (winner === side) summary.secondWins += 1; }
        }
        const ownLpKey = side === 'A' ? 'lp_a' : 'lp_b';
        const rivalLpKey = side === 'A' ? 'lp_b' : 'lp_a';
        const ownStartKey = side === 'A' ? 'lp_start_a' : 'lp_start_b';
        const rivalStartKey = side === 'A' ? 'lp_start_b' : 'lp_start_a';
        const ownLp = Number(detail[ownLpKey]);
        const rivalLp = Number(detail[rivalLpKey]);
        const hasOwnLp = detail[ownLpKey] !== '' && detail[ownLpKey] !== null && detail[ownLpKey] !== undefined && Number.isFinite(ownLp);
        const hasRivalLp = detail[rivalLpKey] !== '' && detail[rivalLpKey] !== null && detail[rivalLpKey] !== undefined && Number.isFinite(rivalLp);
        if (hasOwnLp && hasRivalLp) summary.lpCovered += 1;
        const history = Array.isArray(detail.lp_history) ? detail.lp_history : [];
        const gross = (historySide) => history.filter((item) => item.side === historySide && item.operation !== 'reset').reduce((sum, item) => sum + Math.max(0, Number(item.before || 0) - Number(item.after || 0)), 0);
        const ownHistorySide = side === 'A' ? 'a' : 'b';
        const rivalHistorySide = side === 'A' ? 'b' : 'a';
        if (hasOwnLp) summary.damageReceived += gross(ownHistorySide) || Math.max(0, Number(detail[ownStartKey] || 8000) - ownLp);
        else if (detail.defeat_method === 'life_points' && winner !== side && winner !== 'E') summary.damageReceived += Number(detail[ownStartKey] || 8000);
        if (hasRivalLp) summary.damageDealt += gross(rivalHistorySide) || Math.max(0, Number(detail[rivalStartKey] || 8000) - rivalLp);
        else if (detail.defeat_method === 'life_points' && winner === side) summary.damageDealt += Number(detail[rivalStartKey] || 8000);
        if (winner === side && hasOwnLp && ownLp === Number(detail[ownStartKey] || 8000)) summary.flawlessWins += 1;
      });
    });
    summary.winRate = summary.matches ? Math.round((summary.wins / summary.matches) * 100) : 0;
    if(data.statisticsV8)Object.assign(summary,window.YgoStatistics?.profileMetric(deck)||{});
    const playedDuels = summary.duelWins + summary.duelDraws + summary.duelLosses;
    summary.duelWinRate = playedDuels ? Math.round((summary.duelWins / playedDuels) * 100) : 0;
    summary.firstWinRate = summary.firstStarts ? Math.round((summary.firstWins / summary.firstStarts) * 100) : 0;
    summary.secondWinRate = summary.secondStarts ? Math.round((summary.secondWins / summary.secondStarts) * 100) : 0;
    summary.brickRate = playedDuels ? Math.round((summary.brickHands / playedDuels) * 100) : 0;
    summary.averageTurns = summary.turnsRecorded ? Math.round((summary.totalTurns / summary.turnsRecorded) * 10) / 10 : 0;
    summary.averageDuration = summary.durationRecorded ? Math.round(summary.totalDuration / summary.durationRecorded) : 0;
    let currentStreak = 0;
    individualMatchList().filter((match) => {
      return matchSideDecks(match, 'a').includes(deck.name) || matchSideDecks(match, 'b').includes(deck.name)
        || [match.deck_a_id, match.deck_b_id, match.deck_c_id, match.deck_d_id, match.deck_e_id, match.deck_f_id].some((id) => String(id || '') === String(deck.id || ''));
    }).sort((a, b) => String(a.scheduled_on || '').localeCompare(String(b.scheduled_on || '')) || Number(a.id || 0) - Number(b.id || 0)).forEach((match) => {
      const result = evaluateDuel(getSavedResult(match).filter(Boolean), match);
      if (result.state !== 'FINALIZADA') return;
      const side = matchSideDecks(match, 'a').includes(deck.name) || [match.deck_a_id, match.deck_c_id, match.deck_e_id].some((id) => String(id || '') === String(deck.id || '')) ? 'A' : 'B';
      if (result.winner === side) { currentStreak += 1; summary.bestMatchStreak = Math.max(summary.bestMatchStreak, currentStreak); }
      else currentStreak = 0;
    });
    const rivals = Array.from(summary.opponents.values());
    summary.rivalsBeaten = rivals.filter((row) => row.matchWins > 0).length;
    summary.rivalsFaced = rivals.filter((row) => row.duels > 0 || row.matchWins > 0 || row.matchLosses > 0 || row.matchDraws > 0).length;
    summary.topBeaten = rivals.filter((row) => row.matchWins).sort((a, b) => b.matchWins - a.matchWins || b.wins - a.wins || a.name.localeCompare(b.name)).slice(0, 3);
    summary.topLostTo = rivals.filter((row) => row.matchLosses).sort((a, b) => b.matchLosses - a.matchLosses || b.losses - a.losses || a.name.localeCompare(b.name)).slice(0, 3);
    summary.formatLabel = Array.from(summary.formats).join(', ') || 'Sin registros';
    return summary;
  }

  function globalDeckRanking() {
    return (data.allDecks || []).map((deck) => ({ deck, stat: deckProfileCompetitiveStats(deck) }))
      .sort((left, right) =>
        right.stat.points - left.stat.points
        || right.stat.wins - left.stat.wins
        || right.stat.winRate - left.stat.winRate
        || right.stat.duelWins - left.stat.duelWins
        || (right.stat.duelWins - right.stat.duelLosses) - (left.stat.duelWins - left.stat.duelLosses)
        || String(left.deck.name || '').localeCompare(String(right.deck.name || ''))
      );
  }

  const deckProfileTabs = Array.from(document.querySelectorAll('[data-deck-profile-tab]'));
  const deckProfileViews = Array.from(document.querySelectorAll('[data-deck-profile-view]'));
  const matchupOpponent = document.querySelector('[data-matchup-opponent]');
  const matchupPeriod = document.querySelector('[data-matchup-period]');
  const matchupEvent = document.querySelector('[data-matchup-event]');
  const matchupDivision = document.querySelector('[data-matchup-division]');
  const matchupRulesetVersion = document.querySelector('[data-matchup-ruleset-version]');
  const matchupVersion = document.querySelector('[data-matchup-version]');
  const matchupOpponentVersion = document.querySelector('[data-matchup-opponent-version]');
  const matchupPosition = document.querySelector('[data-matchup-position]');
  let deckAnalysisFiltersReady = false;

  const deckCategoryOrder = ['performance', 'history', 'events', 'decklist', 'ranking', 'achievements', 'strategy', 'created-combos', 'play-calculator', 'matchups', 'edit'];
  const deckCategoryMeta = {
    performance: ['Rendimiento competitivo', 'Partidas, duelos, ritmo, puntos de vida y formas de cierre.'],
    history: ['Historial y agenda', 'Programacion, rivales, resultados y acceso a cada partida.'],
    events: ['Historial de eventos', 'Eventos, temporadas, participación y acceso a la ficha de cada evento.'],
    decklist: ['Decklist y multimedia', 'Main Deck, Extra Deck, Side Deck, cartas clave, variaciones y archivos.'],
    ranking: ['Ranking y tier', 'Posicion actual, tier, mejor registro historico y cambios persistidos.'],
    achievements: ['Logros', 'Titulos, subcampeonatos, medallas y progreso competitivo.'],
    strategy: ['Guía de juego', 'Aprende las cartas, recorre los combos y prepara tus enfrentamientos.'],
    'created-combos': ['Mis combos y estrategias', 'Gu\u00edas visuales, combos documentados y creaciones de este deck.'],
    'play-calculator': ['Calculador de jugadas', 'Reproduce el estado del duelo y consulta opciones documentadas, con cálculo local.'],
    matchups: ['Matchups', 'Comparacion entre teoria estrategica y resultados observados por rival.'],
    edit: ['Editar deck', 'Datos principales, division, arquetipo, logo y notas administrativas.']
  };
  const deckCategoryModalNode = document.querySelector('[data-deck-category-modal]');
  const deckCategoryModal = deckCategoryModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(deckCategoryModalNode) : null;
  const deckCategoryBody = document.querySelector('[data-deck-category-body]');
  const deckCategoryStaging = document.querySelector('[data-deck-category-staging]');
  const deckCategoryTitle = document.querySelector('[data-deck-category-title]');
  const deckCategorySubtitle = document.querySelector('[data-deck-category-subtitle]');
  const deckCategoryPosition = document.querySelector('[data-deck-category-position]');
  let activeDeckCategory = '';
  const deckDetailPanel = document.querySelector('.deck-profile-detail-panel');
  const deckAnalysisScreen = document.querySelector('.deck-analysis-screen');
  const deckStrategyProfile = deckAnalysisScreen?.querySelector('.deck-strategy-profile');
  const deckAnalysisHeader = deckAnalysisScreen?.querySelector('.deck-analysis-header');
  const deckCategoryNodes = {
    performance: [deckDetailPanel?.querySelector(':scope > .panel-head'), deckDetailPanel?.querySelector('.deck-record-overview'), deckDetailPanel?.querySelector('.profile-stats'), deckDetailPanel?.querySelector('[data-profile-rivals]')].filter(Boolean),
    history: [deckDetailPanel?.querySelector('.deck-history-section')].filter(Boolean),
    events: [deckDetailPanel?.querySelector('[data-profile-events]')].filter(Boolean),
    decklist: [deckDetailPanel?.querySelector('[data-card-gallery]'), deckDetailPanel?.querySelector('.deck-detail-grid')].filter(Boolean),
    ranking: [deckDetailPanel?.querySelector('.tier-history-summary')].filter(Boolean),
    achievements: [deckDetailPanel?.querySelector('.deck-achievement-layout')].filter(Boolean),
    strategy: [deckAnalysisHeader, deckStrategyProfile].filter(Boolean),
    'created-combos': [document.querySelector('.deck-created-combos-panel')].filter(Boolean),
    'play-calculator': [document.querySelector('.play-calculator-panel')].filter(Boolean),
    matchups: deckAnalysisScreen ? Array.from(deckAnalysisScreen.children).filter((node) => node !== deckAnalysisHeader && node !== deckStrategyProfile) : [],
    edit: [document.querySelector('.deck-editor-panel')].filter(Boolean)
  };

  if (deckCategoryStaging && deckCategoryBody) {
    deckCategoryOrder.flatMap((key) => deckCategoryNodes[key]).forEach((node) => deckCategoryStaging.appendChild(node));
    if (deckDetailPanel) deckDetailPanel.hidden = true;
  }

  window.addEventListener('stationx:combo-edit', () => deckCategoryModal?.hide());

  function setDeckCategoryUrl(category) {
    const url = new URL(window.location.href);
    if (category) url.searchParams.set('deck_section', category);
    else url.searchParams.delete('deck_section');
    url.searchParams.delete('deck_view');
    history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function openDeckCategory(category, updateUrl = true) {
    // The duel board needs the whole page; never place it in a scrollable modal.
    if (category === 'play-calculator') {
      const selected = document.querySelector('[data-deck-select]');
      const current = (data.allDecks || []).find(d => d.name === selected?.value);
      if (current) sessionStorage.setItem('stationx:calculator-deck', String(current.id));
      deckCategoryModal?.hide();
      routeTo('calculadora_jugadas');
      return;
    }
    if (!deckCategoryModal || !deckCategoryBody || !deckCategoryNodes[category]) return;
    Array.from(deckCategoryBody.children).forEach((node) => deckCategoryStaging?.appendChild(node));
    deckCategoryNodes[category].forEach((node) => deckCategoryBody.appendChild(node));
    activeDeckCategory = category;
    // Prepare only the category the player actually opened, using its current deck.
    const deck = (data.allDecks || []).find(row => row.name === deckSelect?.value) || data.allDecks?.[0];
    if (deck && category === 'decklist') window.YgoDeckBuilder?.mount(document.querySelector('[data-card-gallery]'), deck, data);
    if (deck && category === 'created-combos') window.YgoComboCreator?.mountViewer(document.querySelector('[data-deck-created-combos]'), deck);
    if (deck && ['strategy', 'matchups'].includes(category)) renderDeckAnalysis(deck);
    const meta = deckCategoryMeta[category];
    if (deckCategoryTitle) deckCategoryTitle.textContent = meta[0];
    if (deckCategorySubtitle) deckCategorySubtitle.textContent = meta[1];
    if (deckCategoryPosition) deckCategoryPosition.textContent = `${deckCategoryOrder.indexOf(category) + 1} de ${deckCategoryOrder.length}`;
    if (updateUrl) setDeckCategoryUrl(category);
    deckCategoryModal.show();
  }

  document.querySelectorAll('[data-deck-category-open]').forEach((button) => button.addEventListener('click', () => openDeckCategory(button.dataset.deckCategoryOpen)));
  document.querySelector('[data-deck-category-prev]')?.addEventListener('click', () => {
    const index = deckCategoryOrder.indexOf(activeDeckCategory);
    openDeckCategory(deckCategoryOrder[(index - 1 + deckCategoryOrder.length) % deckCategoryOrder.length]);
  });
  document.querySelector('[data-deck-category-next]')?.addEventListener('click', () => {
    const index = deckCategoryOrder.indexOf(activeDeckCategory);
    openDeckCategory(deckCategoryOrder[(index + 1) % deckCategoryOrder.length]);
  });
  deckCategoryModalNode?.addEventListener('hidden.bs.modal', () => {
    Array.from(deckCategoryBody?.children || []).forEach((node) => deckCategoryStaging?.appendChild(node));
    activeDeckCategory = '';
    setDeckCategoryUrl('');
  });

  function openDeckProfileView(view) {
    const target = 'summary';
    deckProfileTabs.forEach((button) => button.classList.toggle('active', button.dataset.deckProfileTab === target));
    deckProfileViews.forEach((panel) => { panel.hidden = panel.dataset.deckProfileView !== target; });
    const url = new URL(window.location.href);
    url.searchParams.delete('deck_view');
    history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  }

  deckProfileTabs.forEach((button) => button.addEventListener('click', () => openDeckProfileView(button.dataset.deckProfileTab)));

  function matchIncludesDeck(match, deck, side) {
    const ids = side === 'A' ? [match.deck_a_id, match.deck_c_id, match.deck_e_id] : [match.deck_b_id, match.deck_d_id, match.deck_f_id];
    return ids.some((id) => String(id || '') === String(deck.id || '')) || matchSideDecks(match, side.toLowerCase()).includes(deck.name);
  }

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
        ? `<span class="profile-team-logo"><img loading="lazy" decoding="async" src="${escapeHtml(teamLogo)}" alt="${escapeHtml(deck.team || 'Equipo')}" onerror="this.parentElement.replaceWith(Object.assign(document.createElement('span'),{className:'team-inline-badge',textContent:'${escapeHtml(teamCode)}'}))"></span><span>${escapeHtml(deck.team || 'Sin equipo')}</span>`
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
    if (cardGallery && window.YgoDeckBuilder && activeDeckCategory === 'decklist') {
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
    if (activeDeckCategory === 'created-combos') window.YgoComboCreator?.mountViewer(document.querySelector('[data-deck-created-combos]'), deck);
    if (['strategy', 'matchups'].includes(activeDeckCategory)) renderDeckAnalysis(deck);
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

  function renderStatsV2() {
    if (!statsBody) return;
    populateStatsFilters();
    const model = buildStatsModel();
    renderDataQuality(model);
    const context = model.context;
    const matches = model.matches;
    const decks = model.decks;
    const teams = model.teams;
    const finalized = matches.filter((match) => evaluateDuel(getSavedResult(match), match).state === 'FINALIZADA');
    const playedDuels = finalized.reduce((total, match) => total + getSavedResult(match).filter(Boolean).length, 0);
    const setText = (selector, value) => { const node = document.querySelector(selector); if (node) node.textContent = String(value); };
    setText('[data-stats-kpi="events"]', new Set(matches.map((match) => String(match.competition_id || 'general'))).size);
    setText('[data-stats-kpi="matches"]', matches.length);
    setText('[data-stats-kpi="finalized"]', finalized.length);
    setText('[data-stats-kpi="duels"]', playedDuels);
    setText('[data-stats-kpi="decks"]', decks.length);
    setText('[data-stats-kpi="teams"]', teams.length);
    const lpCoverage = finalized.reduce((coverage, match) => {
      const details = parseJsonValue(match.duel_details || '', []);
      getSavedResult(match).filter(Boolean).forEach((winner, index) => {
        coverage.total += 1;
        const detail = Array.isArray(details) ? details.find((item) => Number(item.duel) === index + 1) : null;
        const hasA = detail && detail.lp_a !== '' && detail.lp_a !== null && detail.lp_a !== undefined;
        const hasB = detail && detail.lp_b !== '' && detail.lp_b !== null && detail.lp_b !== undefined;
        if (hasA && hasB) coverage.covered += 1;
      });
      return coverage;
    }, { covered: 0, total: 0 });
    setText('[data-stats-kpi="lpCoverage"]', `${lpCoverage.covered}/${lpCoverage.total}`);
    const scope = [];
    if (context.event) scope.push(((data.competitions || []).find((item) => String(item.id) === context.event) || {}).name || 'Evento');
    if (context.ruleset) scope.push(context.ruleset.replaceAll('_', ' ').toUpperCase());
    if (context.type) scope.push(context.type.replaceAll('_', ' ').toUpperCase());
    if (context.season) scope.push(((data.seasons || []).find((season) => String(season.id) === context.season) || {}).name || ('Temporada ' + context.season));
    if (context.deck) scope.push(context.deck);
    if (context.team) scope.push(context.team);
    if (context.player) scope.push(((data.players || []).find((player) => String(player.id) === context.player) || {}).alias || 'Jugador');
    if (context.from || context.to) scope.push((context.from || 'Inicio') + ' a ' + (context.to || 'hoy'));
    setText('[data-stats-scope]', scope.join(' | ') || 'Todos los eventos competitivos');
    setText('[data-stats-sample-note]', 'Minimo ' + context.sample + ' matches finalizados.');

    const rankingKey = (analyticsRanking && analyticsRanking.value) || 'points';
    const sorters = {
      points: (a, b) => b.points - a.points || b.matchWins - a.matchWins || b.winRate - a.winRate || b.duelWins - a.duelWins || b.duelDiff - a.duelDiff || a.name.localeCompare(b.name),
      matchWins: (a, b) => b.matchWins - a.matchWins || b.winRate - a.winRate,
      winRate: (a, b) => b.winRate - a.winRate || b.matches - a.matches,
      duelWins: (a, b) => b.duelWins - a.duelWins || b.duelDiff - a.duelDiff,
      duelDiff: (a, b) => b.duelDiff - a.duelDiff || b.duelWins - a.duelWins,
      firstWinRate: (a, b) => b.firstWinRate - a.firstWinRate || b.firstStarts - a.firstStarts,
      secondWinRate: (a, b) => b.secondWinRate - a.secondWinRate || b.secondStarts - a.secondStarts,
      fastestTurns: (a, b) => (a.fastestTurns || Number.MAX_SAFE_INTEGER) - (b.fastestTurns || Number.MAX_SAFE_INTEGER) || b.duelWins - a.duelWins,
      fastestSeconds: (a, b) => (a.fastestSeconds || Number.MAX_SAFE_INTEGER) - (b.fastestSeconds || Number.MAX_SAFE_INTEGER) || b.duelWins - a.duelWins,
      sweeps: (a, b) => b.sweeps - a.sweeps || b.matchWins - a.matchWins,
      d5Wins: (a, b) => b.d5Wins - a.d5Wins || b.d5Played - a.d5Played
      ,brickHands: (a, b) => b.brickHands - a.brickHands || b.brickRate - a.brickRate
      ,averageTurns: (a, b) => (a.averageTurns || Number.MAX_SAFE_INTEGER) - (b.averageTurns || Number.MAX_SAFE_INTEGER)
      ,elo: (a, b) => b.elo - a.elo || b.matches - a.matches
      ,power5: (a, b) => b.power5 - a.power5 || b.matches - a.matches
      ,power10: (a, b) => b.power10 - a.power10 || b.matches - a.matches
      ,consistency: (a, b) => b.consistency - a.consistency || b.matches - a.matches
      ,strength: (a, b) => b.strength - a.strength || b.matches - a.matches
    };
    const sampleRankings = ['winRate', 'consistency', 'power5', 'power10', 'strength'];
    const ranked = (sampleRankings.includes(rankingKey) ? decks.filter((deck) => deck.matches >= context.sample) : decks).slice().sort(sorters[rankingKey] || sorters.points);
    const rankingValue = (row) => {
      if (rankingKey === 'elo' || rankingKey === 'strength') return Number(row[rankingKey] || 0);
      if (['power5', 'power10', 'consistency', 'winRate', 'firstWinRate', 'secondWinRate'].includes(rankingKey)) return Number(row[rankingKey] || 0) + '%';
      if (rankingKey === 'fastestSeconds') return row.fastestSeconds ? Math.floor(row.fastestSeconds / 60) + ':' + String(row.fastestSeconds % 60).padStart(2, '0') : 'N/D';
      if (rankingKey === 'fastestTurns' || rankingKey === 'averageTurns') return Number(row[rankingKey] || 0) || 'N/D';
      return Number(row[rankingKey] ?? row.points ?? 0);
    };
    const globalRanked = decks.slice().sort(sorters.points);
    if (statsGlobalPreview) {
      statsGlobalPreview.innerHTML = globalRanked.length ? globalRanked.slice(0, 10).map((row, index) => {
        const logo = row.logo ? '<img loading="lazy" decoding="async" src="' + escapeHtml(row.logo) + '" alt="">' : '<span><i class="bi bi-collection"></i></span>';
        return '<tr><td data-label="#"><b>' + (index + 1) + '</b></td><td data-label="Deck / equipo"><div class="analytics-deck-cell">' + logo + '<div><b>' + escapeHtml(row.name) + '</b><small>' + escapeHtml(row.team) + '</small></div></div></td>'
          + '<td data-label="Formato">' + escapeHtml(row.ruleset) + '</td><td data-label="PTS"><b>' + row.points + '</b></td><td data-label="Partidas G/E/P">' + row.matchWins + ' / ' + row.matchDraws + ' / ' + row.matchLosses + '</td><td data-label="WR">' + row.winRate + '%</td>'
          + '<td data-label="Duelos G/E/P">' + row.duelWins + ' / ' + row.duelDraws + ' / ' + row.duelLosses + '</td><td data-label="DIF">' + (row.duelDiff > 0 ? '+' : '') + row.duelDiff + '</td></tr>';
      }).join('') : statsEmpty('No hay decks dentro del alcance seleccionado.', 8);
    }
    statsBody.innerHTML = ranked.length ? ranked.map((row, index) => {
      const logo = row.logo ? '<img loading="lazy" decoding="async" src="' + escapeHtml(row.logo) + '" alt="">' : '<span><i class="bi bi-collection"></i></span>';
      const winRate = row.matches >= context.sample ? row.winRate + '%' : '<span title="Requiere ' + context.sample + ' matches">N/M</span>';
      return '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Equipo / Deck"><div class="analytics-deck-cell">' + logo + '<div><b>' + escapeHtml(row.name) + '</b><small>' + escapeHtml(row.team) + '</small></div></div></td>'
        + '<td data-label="Formato">' + escapeHtml(row.ruleset) + '</td><td data-label="PTS">' + row.points + '</td><td data-label="Valor ranking"><b>' + rankingValue(row) + '</b></td><td data-label="MJ">' + row.matches + '</td><td data-label="MG">' + row.matchWins + '</td><td data-label="ME">' + row.matchDraws + '</td><td data-label="MP">' + row.matchLosses + '</td>'
        + '<td data-label="WR">' + winRate + '</td><td data-label="WR 1ro">' + (row.firstStarts ? row.firstWinRate + '% (' + row.firstWins + '/' + row.firstStarts + ')' : 'N/D') + '</td><td data-label="WR 2do">' + (row.secondStarts ? row.secondWinRate + '% (' + row.secondWins + '/' + row.secondStarts + ')' : 'N/D') + '</td><td data-label="DJ">' + (row.duelWins + row.duelLosses + row.duelDraws) + '</td><td data-label="DG">' + row.duelWins + '</td><td data-label="DP">' + row.duelLosses + '</td><td data-label="DIF">' + row.duelDiff + '</td><td data-label="Brick">' + row.brickHands + ' (' + row.brickRate + '%)</td><td data-label="Prom. turnos">' + (row.averageTurns || 'N/D') + '</td><td data-label="2-0">' + row.sweeps + '</td><td data-label="D5">' + row.d5Wins + '</td><td data-label="Racha">' + (row.currentStreak ? 'W' + row.currentStreak : row.lastResult) + '</td></tr>';
    }).join('') : statsEmpty(sampleRankings.includes(rankingKey) ? 'No hay decks con la muestra minima de ' + context.sample + ' partidas.' : 'No hay resultados para el alcance seleccionado.', 21);

    const teamRankingBody = document.querySelector('[data-stats-team-ranking]');
    if (teamRankingBody) {
      const teamRanked = (sampleRankings.includes(rankingKey) ? teams.filter((team) => team.matches >= context.sample) : teams).slice().sort(sorters[rankingKey] || sorters.points);
      teamRankingBody.innerHTML = teamRanked.length ? teamRanked.map((team, index) => '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Equipo"><b>' + escapeHtml(team.name) + '</b></td><td data-label="PTS">' + team.points + '</td><td data-label="MJ">' + team.matches + '</td><td data-label="G/E/P">' + team.matchWins + ' / ' + team.matchDraws + ' / ' + team.matchLosses + '</td><td data-label="WR">' + team.winRate + '%</td><td data-label="Duelos G/E/P">' + team.duelWins + ' / ' + team.duelDraws + ' / ' + team.duelLosses + '</td><td data-label="Elo">' + team.elo + '</td><td data-label="Power 5">' + team.power5 + '%</td><td data-label="Consistencia">' + team.consistency + '%</td></tr>').join('') : statsEmpty('No hay equipos con la muestra requerida.', 10);
    }
    const playerRankingBody = document.querySelector('[data-stats-player-ranking]');
    if (playerRankingBody) {
      const playerRanked = model.playerStats.slice().sort((a, b) => b.points - a.points || b.wins - a.wins || b.matches - a.matches);
      playerRankingBody.innerHTML = playerRanked.length ? playerRanked.map((row, index) => {
        const linked = Array.from(row.deckIds).map((id) => (data.allDecks || []).find((deck) => Number(deck.id) === Number(id))).filter(Boolean);
        const rate = row.matches ? Math.round((row.wins / row.matches) * 100) : 0;
        const medals = (data.medalUnlocks || []).filter((unlock) => unlock.entity_type === 'player' && String(unlock.entity_id) === String(row.player.id)).length;
        return '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Jugador"><b>' + escapeHtml(row.player.alias || row.player.display_name) + '</b><small class="d-block">' + medals + ' medallas</small></td><td data-label="Equipo">' + escapeHtml(row.player.team_name || 'Sin equipo') + '</td><td data-label="Decks">' + (linked.map((deck) => escapeHtml(deck.name)).join(', ') || 'Sin deck') + '</td><td data-label="MJ">' + row.matches + '</td><td data-label="G/E/P">' + row.wins + ' / ' + row.draws + ' / ' + row.losses + '</td><td data-label="WR">' + rate + '%</td><td data-label="PTS">' + row.points + '</td></tr>';
      }).join('') : statsEmpty('No hay jugadores atribuidos directamente a los duelos de este alcance.', 8);
    }

    const byRate = decks.filter((deck) => deck.matches >= context.sample).sort((a, b) => b.winRate - a.winRate || b.matches - a.matches);
    const bySweep = decks.slice().sort((a, b) => b.sweeps - a.sweeps || b.matchWins - a.matchWins);
    const byD5 = decks.slice().sort((a, b) => b.d5Wins - a.d5Wins || b.d5Played - a.d5Played);
    const teamRows = teams.slice().sort((a, b) => b.points - a.points || b.duelDiff - a.duelDiff);
    if (statsBestEfficiency) statsBestEfficiency.textContent = byRate[0] ? byRate[0].team + ' / ' + byRate[0].name + ' (' + byRate[0].winRate + '%)' : 'Muestra insuficiente';
    if (statsBestSweep) statsBestSweep.textContent = bySweep[0] && bySweep[0].sweeps ? bySweep[0].team + ' / ' + bySweep[0].name + ' (' + bySweep[0].sweeps + ')' : 'Sin cierres perfectos';
    if (statsD5Pressure) statsD5Pressure.textContent = byD5[0] && byD5[0].d5Wins ? byD5[0].team + ' / ' + byD5[0].name + ' (' + byD5[0].d5Wins + ')' : 'Sin D5 finalizados';
    if (statsBestTeam) statsBestTeam.textContent = teamRows[0] ? teamRows[0].name + ' (' + teamRows[0].points + ' pts)' : 'Sin datos';
    renderCompactBars(statsTopBars, decks.slice().sort((a, b) => b.points - a.points).slice(0, 8).map((row) => ({ ...row, pts: row.points, division: row.ruleset })), 'pts');
    renderCompactBars(statsTeamBars, teamRows.slice(0, 8).map((row) => ({ ...row, pts: row.points, division: 'Equipo' })), 'pts');
    renderCompactBars(statsOffenseBars, decks.slice().sort((a, b) => b.duelWins - a.duelWins).slice(0, 8).map((row) => ({ ...row, cg: row.duelWins, division: row.ruleset })), 'cg');
    renderCompactBars(statsDefenseBars, decks.filter((row) => row.matches).sort((a, b) => a.duelLosses - b.duelLosses || b.matches - a.matches).slice(0, 8).map((row) => ({ ...row, cp: row.duelLosses, division: row.ruleset })), 'cp');
    if (statsTopSummary) statsTopSummary.textContent = Math.max(0, ...decks.map((row) => row.points)) + ' pts';
    if (statsMatchSummary) statsMatchSummary.textContent = String(matches.length);
    const formatCounts = matches.reduce((map, match) => {
      const competition = statsCompetition(match);
      const key = String(match.competition_ruleset || (competition && competition.event_ruleset) || 'general');
      map.set(key, (map.get(key) || 0) + 1);
      return map;
    }, new Map());
    const formatRows = Array.from(formatCounts.entries()).map((entry) => ({ name: entry[0].replaceAll('_', ' ').toUpperCase(), value: entry[1], division: 'Matches' })).sort((a, b) => b.value - a.value);
    renderCompactBars(statsDivisionBars, formatRows, 'value');
    if (statsDivisionSummary) statsDivisionSummary.textContent = formatRows.length + ' formatos';
    renderStatsComparator(decks);

    const statuses = matches.reduce((map, match) => {
      const state = evaluateDuel(getSavedResult(match), match).state;
      const key = state === 'FINALIZADA' ? 'FINALIZADA' : (state === 'PENDIENTE' ? 'PENDIENTE' : 'EN CURSO');
      map[key] = (map[key] || 0) + 1;
      return map;
    }, {});
    const total = Math.max(1, matches.length);
    const finalPct = Math.round(((statuses.FINALIZADA || 0) / total) * 100);
    const progressPct = Math.round(((statuses['EN CURSO'] || 0) / total) * 100);
    if (statsDonut) statsDonut.style.background = 'radial-gradient(circle, #07101f 0 48%, transparent 49%), conic-gradient(#35d27f 0 ' + finalPct + '%, #ffd25f ' + finalPct + '% ' + (finalPct + progressPct) + '%, #4f86ff ' + (finalPct + progressPct) + '% 100%)';
    if (statsDonutLegend) statsDonutLegend.innerHTML = '<span><i style="--accent:#35d27f"></i>Finalizados ' + (statuses.FINALIZADA || 0) + '</span><span><i style="--accent:#ffd25f"></i>En curso ' + (statuses['EN CURSO'] || 0) + '</span><span><i style="--accent:#4f86ff"></i>Pendientes ' + (statuses.PENDIENTE || 0) + '</span>';

    const matchupsBody = document.querySelector('[data-stats-matchups]');
    if (matchupsBody) matchupsBody.innerHTML = model.matchups.length ? model.matchups.sort((a, b) => b.matches - a.matches).map((row) => '<tr><td data-label="Deck A"><b>' + escapeHtml(row.a) + '</b></td><td data-label="Deck B"><b>' + escapeHtml(row.b) + '</b></td><td data-label="MJ">' + row.matches + '</td><td data-label="A gana">' + row.aWins + '</td><td data-label="B gana">' + row.bWins + '</td><td data-label="Empates">' + row.draws + '</td><td data-label="Duelos A-B">' + row.aDuels + '-' + row.bDuels + '</td><td data-label="WR A">' + Math.round((row.aWins / row.matches) * 100) + '%</td></tr>').join('') : statsEmpty('No hay enfrentamientos finalizados para estos filtros.', 8);
    const matchupMatrix = document.querySelector('[data-stats-matchup-matrix]');
    if (matchupMatrix) {
      const matrixDecks = model.decks.filter((deck) => deck.matches).sort((a, b) => a.name.localeCompare(b.name));
      matchupMatrix.innerHTML = matrixDecks.length ? '<table class="matchup-matrix"><thead><tr><th>Deck</th>' + matrixDecks.map((deck) => '<th title="' + escapeHtml(deck.name) + '">' + escapeHtml(deck.name) + '</th>').join('') + '</tr></thead><tbody>' + matrixDecks.map((rowDeck) => '<tr><th>' + escapeHtml(rowDeck.name) + '</th>' + matrixDecks.map((columnDeck) => {
        if (rowDeck.name === columnDeck.name) return '<td class="is-self">-</td>';
        const matchup = model.matchups.find((item) => [item.a, item.b].includes(rowDeck.name) && [item.a, item.b].includes(columnDeck.name));
        if (!matchup) return '<td class="is-empty">N/D</td>';
        const wins = matchup.a === rowDeck.name ? matchup.aWins : matchup.bWins;
        const rate = Math.round(((wins + matchup.draws * .5) / matchup.matches) * 100);
        const heat = rate >= 67 ? 'is-good' : rate <= 33 ? 'is-bad' : 'is-even';
        return '<td class="' + heat + '" title="' + escapeHtml(rowDeck.name + ' vs ' + columnDeck.name) + '"><b>' + rate + '%</b><small>' + matchup.matches + ' PJ</small></td>';
      }).join('') + '</tr>').join('') + '</tbody></table>' : '<div class="empty-state">Sin enfrentamientos suficientes para construir la matriz.</div>';
    }
    const duelBreakdown = document.querySelector('[data-stats-duel-breakdown]');
    if (duelBreakdown) duelBreakdown.innerHTML = model.duelPositions.map((slot) => '<article><span>D' + slot.position + '</span><b>' + slot.total + '</b><small>A ' + slot.a + ' | B ' + slot.b + (slot.draws ? ' | E ' + slot.draws : '') + '</small><div><i style="width:' + (slot.total ? Math.round((slot.a / slot.total) * 100) : 0) + '%"></i></div></article>').join('');
    const rivalRecords = document.querySelector('[data-stats-rival-records]');
    if (rivalRecords) {
      rivalRecords.innerHTML = decks.length ? decks.map((deck) => {
        const list = (rows, key, empty) => rows.length ? rows.map((row, index) => '<li><b>#' + (index + 1) + ' ' + escapeHtml(row.name) + '</b><span>' + row[key] + ' duelos</span></li>').join('') : '<li class="note">' + empty + '</li>';
        return '<article><h3>' + escapeHtml(deck.name) + '</h3><div><section><h4>Mas vencidos</h4><ol>' + list(deck.topBeaten, 'wins', 'Sin victorias') + '</ol></section><section><h4>Mas derrotas recibidas</h4><ol>' + list(deck.topLostTo, 'losses', 'Sin derrotas') + '</ol></section></div></article>';
      }).join('') : '<div class="empty-state">No hay duelos finalizados para calcular rivales frecuentes.</div>';
    }

    const availableMetaTypes = new Map();
    model.matches.forEach((match) => {
      const competition = statsCompetition(match);
      const code = String(match.competition_type || competition?.type || match.phase || 'general');
      availableMetaTypes.set(code, code.replaceAll('_', ' ').toUpperCase());
    });
    if (analyticsMetaEventType) {
      const previousType = analyticsMetaEventType.value;
      const typeCatalog = data.competitionTypeOptions || {};
      const configuredTypes = Object.entries(typeCatalog).map(([code, meta]) => [code, String(meta.label || code)]);
      availableMetaTypes.forEach((label, code) => {
        if (!configuredTypes.some((entry) => entry[0] === code)) configuredTypes.push([code, label]);
      });
      const withResults = configuredTypes.filter(([code]) => availableMetaTypes.has(code));
      const withoutResults = configuredTypes.filter(([code]) => !availableMetaTypes.has(code));
      analyticsMetaEventType.innerHTML = '<option value="">Todos los tipos con resultados</option>'
        + (withResults.length ? '<optgroup label="Con resultados guardados">' + withResults.map(([code, label]) => '<option value="' + escapeHtml(code) + '">' + escapeHtml(label) + '</option>').join('') + '</optgroup>' : '')
        + (withoutResults.length ? '<optgroup label="Sin resultados en este alcance">' + withoutResults.map(([code, label]) => '<option value="' + escapeHtml(code) + '" disabled>' + escapeHtml(label) + ' - sin datos</option>').join('') + '</optgroup>' : '');
      analyticsMetaEventType.value = availableMetaTypes.has(previousType) ? previousType : '';
    }
    const metaEventType = analyticsMetaEventType?.value || '';
    const metaSourceMatches = metaEventType ? model.matches.filter((match) => {
      const competition = statsCompetition(match);
      return String(match.competition_type || competition?.type || match.phase || 'general') === metaEventType;
    }) : model.matches;
    const metaGroup = analyticsMetaGroup?.value || 'deck';
    const meta = buildMetaPerformance(metaSourceMatches, metaGroup);
    const metaSample = Math.max(1, Math.min(context.sample, meta.maxMatches || 1));
    const tieredRows = meta.rows.map((row) => {
      let tier = 'Tier 3';
      if (row.matches >= metaSample && row.share >= Number(settings.tier_zero_presence || 35) && row.winRate >= Number(settings.tier_zero_winrate || 65)) tier = 'Tier Zero';
      else if (row.matches >= metaSample && row.metaScore >= Number(settings.tier_one_score || 70)) tier = 'Tier 1';
      else if (row.metaScore >= Number(settings.tier_two_score || 45)) tier = 'Tier 2';
      return { ...row, tier };
    }).sort((a, b) => b.metaScore - a.metaScore || b.appearances - a.appearances || b.winRate - a.winRate || a.name.localeCompare(b.name));
    const leader = tieredRows[0];
    setText('[data-stats-meta-kpi="matches"]', meta.finalizedMatches.length);
    setText('[data-stats-meta-kpi="entries"]', meta.totalAppearances);
    setText('[data-stats-meta-kpi="decks"]', tieredRows.length);
    setText('[data-stats-meta-kpi="leader"]', leader ? leader.name : '-');
    setText('[data-stats-meta-kpi="share"]', leader ? leader.tier + ' | ' + leader.share + '% presencia | indice ' + leader.metaScore : 'Sin resultados finalizados');
    renderMetaEvolution(metaSourceMatches, metaGroup, analyticsMetaPeriod?.value || 'week', context);
    renderAdvancedAnalytics(model, tieredRows);
    renderOperations(model, tieredRows);
    renderCompactBars(document.querySelector('[data-stats-meta-share]'), tieredRows.slice().sort((a, b) => b.appearances - a.appearances || b.metaScore - a.metaScore).slice(0, 15).map((row) => ({ name: row.name, team: row.team, value: row.appearances, division: row.share + '% | ' + row.matches + ' partidas' })), 'value');
    renderCompactBars(document.querySelector('[data-stats-meta-performance]'), tieredRows.slice().sort((a, b) => b.winRate - a.winRate || b.matches - a.matches).slice(0, 10).map((row) => ({ name: row.name, team: row.team, value: row.winRate, division: row.wins + '/' + row.matches + ' partidas | ' + row.duelWinRate + '% duelos' })), 'value', '%');
    const tierBoard = document.querySelector('[data-stats-meta-tiers]');
    const presenceWeight = Math.max(0, Math.min(100, Number(settings.meta_presence_weight || 50)));
    const metaFormula = document.querySelector('[data-meta-formula]');
    if (metaFormula) metaFormula.textContent = `${presenceWeight}% presencia relativa + ${100 - presenceWeight}% winrate de partidas.`;
    const tierRules = document.querySelector('[data-meta-tier-rules]');
    if (tierRules) tierRules.innerHTML = `<li><b>Tier Zero:</b> minimo ${Number(settings.tier_zero_presence || 35)}% de presencia y ${Number(settings.tier_zero_winrate || 65)}% de victorias.</li><li><b>Tier 1:</b> indice minimo ${Number(settings.tier_one_score || 70)}.</li><li><b>Tier 2:</b> indice minimo ${Number(settings.tier_two_score || 45)}.</li><li><b>Tier 3:</b> evidencia inicial o indice inferior.</li>`;
    if (tierBoard) {
      const tierDescriptions = { 'Tier Zero': 'Dominio excepcional', 'Tier 1': 'Alta presencia y rendimiento', 'Tier 2': 'Competitivo y relevante', 'Tier 3': 'Emergente o muestra inicial' };
      tierBoard.innerHTML = ['Tier Zero', 'Tier 1', 'Tier 2', 'Tier 3'].map((tier) => {
        const rows = tieredRows.filter((row) => row.tier === tier);
        return '<article class="panel" data-meta-tier="' + escapeHtml(tier.toLowerCase().replace(' ', '-')) + '"><header><span>' + escapeHtml(tier) + '</span><b>' + rows.length + '</b><small>' + tierDescriptions[tier] + '</small></header><ol>' + (rows.length ? rows.map((row) => '<li><strong>' + escapeHtml(row.name) + '</strong><span>Indice ' + row.metaScore + '</span><small>' + row.share + '% presencia | ' + row.winRate + '% WR | ' + row.matches + ' PJ</small></li>').join('') : '<li class="empty">Sin decks en este nivel</li>') + '</ol></article>';
      }).join('');
      tierBoard.insertAdjacentHTML('beforeend', '<p class="analytics-tier-sample">Muestra aplicada: ' + metaSample + ' ' + (metaSample === 1 ? 'partida finalizada' : 'partidas finalizadas') + ' por deck, ajustada a los datos disponibles.</p>');
    }
    const metaHistoryBody = document.querySelector('[data-stats-meta-history]');
    if (metaHistoryBody) {
      const historyRows = (data.metaHistory || []).filter((row) => String(row.group_type || 'deck') === metaGroup)
        .filter((row) => !context.season || String(row.season_id || '') === context.season)
        .filter((row) => !context.ruleset || String(row.format_code || '') === context.ruleset)
        .sort((a, b) => String(b.snapshot_on || '').localeCompare(String(a.snapshot_on || '')) || Number(b.meta_score || 0) - Number(a.meta_score || 0)).slice(0, 120);
      metaHistoryBody.innerHTML = historyRows.length ? historyRows.map((row) => `<tr><td data-label="Fecha">${escapeHtml(row.snapshot_on || '')}</td><td data-label="Deck / grupo"><b>${escapeHtml(row.group_key || '')}</b></td><td data-label="Formato">${escapeHtml(String(row.format_code || 'general').replaceAll('_', ' ').toUpperCase())}</td><td data-label="Tier"><span class="status-chip">${escapeHtml(row.tier || 'Tier 3')}</span></td><td data-label="Indice">${Number(row.meta_score || 0).toFixed(1)}</td><td data-label="Presencia">${Number(row.presence_share || 0).toFixed(1)}%</td><td data-label="WR">${Number(row.win_rate || 0).toFixed(1)}%</td><td data-label="Muestra">${Number(row.matches || 0)} PJ</td></tr>`).join('') : statsEmpty('Todavia no hay cortes historicos para este alcance.', 8);
    }
    renderCompactBars(statsEventBars, model.events.filter((event) => event.finalized).sort((a, b) => b.finalized - a.finalized).map((event) => ({ name: event.name, value: event.finalized, division: event.duels + ' duelos guardados' })), 'value');
    const eventRankingBody = document.querySelector('[data-stats-meta-event-ranking]');
    if (eventRankingBody) {
      const typeLabel = metaEventType ? (availableMetaTypes.get(metaEventType) || metaEventType) : 'TODOS';
      eventRankingBody.innerHTML = tieredRows.length ? tieredRows.slice().sort((a, b) => b.points - a.points || b.wins - a.wins || b.winRate - a.winRate || b.duelDiff - a.duelDiff).map((row, index) => '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Deck / equipo"><div class="analytics-deck-cell">' + (row.logo ? '<img loading="lazy" decoding="async" src="' + escapeHtml(row.logo) + '" alt="">' : '<span><i class="bi bi-collection"></i></span>') + '<div><b>' + escapeHtml(row.name) + '</b><small>' + escapeHtml(row.team) + '</small></div></div></td><td data-label="Tipo">' + escapeHtml(typeLabel) + '</td><td data-label="PTS oficiales">' + row.points + '</td><td data-label="Partidas G/E/P">' + row.wins + ' / ' + row.draws + ' / ' + row.losses + '</td><td data-label="WR">' + row.winRate + '%</td><td data-label="Duelos G/E/P">' + row.duelWins + ' / ' + row.duelDraws + ' / ' + row.duelLosses + '</td><td data-label="DIF">' + (row.duelDiff > 0 ? '+' : '') + row.duelDiff + '</td></tr>').join('') : statsEmpty('No hay resultados finalizados para este tipo de evento.', 8);
    }
    const timeline = document.querySelector('[data-stats-timeline]');
    if (timeline) timeline.innerHTML = model.chronology.length ? model.chronology.slice(-12).reverse().map((row) => '<div><time>' + escapeHtml(row.date || 'Sin fecha') + '</time><span>' + escapeHtml(row.event) + '</span><b>' + escapeHtml(row.a) + ' ' + (row.winner === 'A' ? 'gano a' : row.winner === 'B' ? 'perdio con' : 'empato con') + ' ' + escapeHtml(row.b) + '</b></div>').join('') : '<div class="empty-state">Sin resultados historicos para estos filtros.</div>';

    const recordRows = [
      ['bi-fire', 'Mayor racha', decks.slice().sort((a, b) => b.bestStreak - a.bestStreak)[0], 'bestStreak', ' victorias'],
      ['bi-controller', 'Mas matches ganados', decks.slice().sort((a, b) => b.matchWins - a.matchWins)[0], 'matchWins', ' ganados'],
      ['bi-grid-3x3-gap', 'Mas duelos ganados', decks.slice().sort((a, b) => b.duelWins - a.duelWins)[0], 'duelWins', ' duelos'],
      ['bi-shield-check', 'Mas victorias perfectas', bySweep[0], 'sweeps', ' cierres'],
      ['bi-5-circle', 'Mas victorias D5', byD5[0], 'd5Wins', ' victorias'],
      ['bi-arrow-repeat', 'Mas remontadas', decks.slice().sort((a, b) => b.comebacks - a.comebacks)[0], 'comebacks', ' remontadas']
      ,['bi-lightning-charge', 'Victoria en menos turnos', decks.filter((deck) => deck.fastestTurns).sort((a, b) => a.fastestTurns - b.fastestTurns)[0], 'fastestTurns', ' turnos']
      ,['bi-stopwatch', 'Victoria en menor tiempo', decks.filter((deck) => deck.fastestSeconds).sort((a, b) => a.fastestSeconds - b.fastestSeconds)[0], 'fastestSeconds', ' segundos']
      ,['bi-exclamation-diamond', 'Mas manos brick', decks.slice().sort((a, b) => b.brickHands - a.brickHands)[0], 'brickHands', ' manos']
    ];
    const records = document.querySelector('[data-stats-records]');
    if (records) records.innerHTML = recordRows.map((record) => {
      const hasRecord = record[2] && Number(record[2][record[3]]);
      return '<article class="panel"><i class="bi ' + record[0] + '"></i><span>' + record[1] + '</span><b>' + (hasRecord ? escapeHtml(record[2].name) : 'Sin registro') + '</b><small>' + (hasRecord ? record[2][record[3]] + record[4] : 'Se mostrara al existir resultados') + '</small></article>';
    }).join('');
    renderCompactBars(document.querySelector('[data-stats-top-cut]'), decks.filter((deck) => deck.topCutMatches).sort((a, b) => b.topCutWins - a.topCutWins || b.topCutMatches - a.topCutMatches).map((deck) => ({ name: deck.name, value: deck.topCutWins, division: deck.topCutMatches + ' matches Top Cut' })), 'value');
    const honors = document.querySelector('[data-stats-honors]');
    const visibleHonors = (data.honors || []).filter((honor) => !context.event || String(honor.competition_id || '') === context.event).filter((honor) => !context.season || String(honor.season_id || '') === context.season);
    if (honors) honors.innerHTML = visibleHonors.length ? visibleHonors.map((honor) => '<div><time><i class="bi bi-trophy"></i></time><span>' + escapeHtml(honor.title || honor.competition_name || 'Titulo') + '</span><b>' + escapeHtml((honor.champion_team_name || '') + (honor.champion_name ? ' / ' + honor.champion_name : '')) + '<small> Subcampeon: ' + escapeHtml((honor.runner_team_name || '') + (honor.runner_name ? ' / ' + honor.runner_name : '')) + '</small></b></div>').join('') : '<div class="empty-state">No hay titulos registrados en este alcance.</div>';
    const movements = document.querySelector('[data-stats-movements]');
    const movementRows = (data.audit || []).filter((row) => ['division_promotion', 'division_relegation'].includes(String(row.action || '')));
    if (movements) movements.innerHTML = movementRows.length ? movementRows.slice(0, 30).map((row) => '<div><time>' + escapeHtml(String(row.created_at || '').slice(0, 10)) + '</time><span>' + (row.action === 'division_promotion' ? 'Ascenso' : 'Descenso') + '</span><b>Deck #' + escapeHtml(row.entity_id || '') + '</b></div>').join('') : '<div class="empty-state">No hay movimientos de division auditados.</div>';
    const eloHistoryBody = document.querySelector('[data-stats-elo-history]');
    if (eloHistoryBody) {
      const eloRows = (data.eloHistory || []).filter((row) => !context.deck || String(row.deck_name || '') === context.deck).slice(0, 150);
      eloHistoryBody.innerHTML = eloRows.length ? eloRows.map((row) => {
        const delta = Number(row.rating_delta || 0);
        const result = delta > 0 ? 'Victoria' : delta < 0 ? 'Derrota' : 'Empate';
        return `<tr><td data-label="Fecha">${escapeHtml(row.played_on || '')}</td><td data-label="Deck"><b>${escapeHtml(row.deck_name || `Deck #${row.deck_id}`)}</b></td><td data-label="Rival">${escapeHtml(row.opponent_name || `Deck #${row.opponent_deck_id}`)}</td><td data-label="Resultado">${result}</td><td data-label="Antes">${Math.round(Number(row.rating_before || 1500))}</td><td data-label="Cambio"><b class="${delta >= 0 ? 'text-success' : 'text-danger'}">${delta > 0 ? '+' : ''}${delta.toFixed(1)}</b></td><td data-label="Despues">${Math.round(Number(row.rating_after || 1500))}</td></tr>`;
      }).join('') : statsEmpty('No hay movimientos Elo persistidos para este alcance.', 7);
    }
    const rankingHistoryBody = document.querySelector('[data-stats-ranking-history]');
    if (rankingHistoryBody) {
      const historyRows = (data.rankingHistory || []).filter((row) => String(row.scope_key || 'global') === (context.season ? 'season' : 'global')).filter((row) => !context.season || String(row.season_id || '') === context.season).filter((row) => !context.deck || String(row.deck_name || '') === context.deck).slice(0, 250);
      rankingHistoryBody.innerHTML = historyRows.length ? historyRows.map((row) => {
        const delta = Number(row.position_delta || 0); const movement = delta > 0 ? `+${delta}` : String(delta);
        return `<tr><td data-label="Fecha">${escapeHtml(row.snapshot_on || '')}</td><td data-label="Deck / equipo"><b>${escapeHtml(row.deck_name || `Deck #${row.deck_id}`)}</b><small>${escapeHtml(row.team_name || 'Sin equipo')}</small></td><td data-label="Posicion"><b>#${Number(row.position || 0)}</b></td><td data-label="Anterior">${row.previous_position ? '#' + Number(row.previous_position) : 'Debut'}</td><td data-label="Movimiento"><b class="${delta > 0 ? 'text-success' : delta < 0 ? 'text-danger' : ''}">${movement}</b></td><td data-label="PTS">${Number(row.points || 0)}</td><td data-label="Partidas G/E/P">${Number(row.wins || 0)} / ${Number(row.draws || 0)} / ${Number(row.losses || 0)}</td></tr>`;
      }).join('') : statsEmpty('Todavia no hay cortes historicos de posiciones para este alcance.', 7);
    }
    const playerBody = document.querySelector('[data-stats-players]');
    if (playerBody) {
      const playerRows = (model.playerStats || []).sort((a, b) => b.points - a.points || b.wins - a.wins || b.duels - a.duels);
      playerBody.innerHTML = playerRows.length ? playerRows.map((row, index) => {
        const linked = Array.from(row.deckIds).map((id) => (data.allDecks || []).find((deck) => Number(deck.id) === Number(id))).filter(Boolean);
        const medals = (data.medalUnlocks || []).filter((unlock) => unlock.entity_type === 'player' && String(unlock.entity_id) === String(row.player.id)).length;
        return '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="Jugador"><b>' + escapeHtml(row.player.alias || row.player.display_name) + '</b><small class="d-block">' + medals + ' medallas</small></td><td data-label="Equipo">' + escapeHtml(row.player.team_name || 'Sin equipo') + '</td><td data-label="Decks">' + (linked.map((deck) => escapeHtml(deck.name)).join(', ') || 'Sin deck atribuido') + '</td><td data-label="MJ">' + row.matches + '</td><td data-label="MG">' + row.wins + '</td><td data-label="DG">' + row.duels + '</td><td data-label="PTS">' + row.points + '</td></tr>';
      }).join('') : statsEmpty('No hay jugadores asignados directamente a duelos dentro de este alcance.', 8);
    }
    const history = document.querySelector('[data-stats-history]');
    if (history) history.innerHTML = model.events.length ? model.events.sort((a, b) => String(b.startsOn).localeCompare(String(a.startsOn))).map((event) => {
      const leader = Array.from(event.leaders.entries()).sort((a, b) => b[1] - a[1])[0];
      return '<tr><td data-label="Evento"><b>' + escapeHtml(event.name) + '</b></td><td data-label="Formato">' + escapeHtml(String(event.ruleset).replaceAll('_', ' ').toUpperCase()) + '</td><td data-label="Inicio">' + escapeHtml(event.startsOn || 'Sin fecha') + '</td><td data-label="Matches">' + event.matches + '</td><td data-label="Finalizados">' + event.finalized + '</td><td data-label="Duelos">' + event.duels + '</td><td data-label="Lider">' + (leader ? escapeHtml(leader[0]) + ' (' + leader[1] + ' pts)' : 'Sin resultados') + '</td></tr>';
    }).join('') : statsEmpty('No hay eventos dentro del alcance seleccionado.', 7);
  }

  function setStatsCategoryUrl(category) {
    const url = new URL(window.location.href);
    if (category) url.searchParams.set('stats_section', category);
    else url.searchParams.delete('stats_section');
    history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function openStatsTab(tabName, showModal = true, updateUrl = true) {
    const target = analyticsViews.some((view) => view.dataset.statsView === tabName) ? tabName : 'summary';
    analyticsTabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.statsTab === target));
    analyticsViews.forEach((view) => { view.hidden = true; });
    localStorage.setItem('dml_stats_tab', target);
    activeStatsCategory = target;
    if (!showModal || !statsCategoryModal || !statsCategoryBody) return;
    Array.from(statsCategoryBody.children).forEach((node) => statsCategoryStaging?.appendChild(node));
    const view = analyticsViews.find((item) => item.dataset.statsView === target);
    if (!view) return;
    view.hidden = false;
    statsCategoryBody.appendChild(view);
    const meta = statsCategoryMeta[target];
    const title = document.querySelector('[data-stats-category-title]');
    const subtitle = document.querySelector('[data-stats-category-subtitle]');
    const position = document.querySelector('[data-stats-category-position]');
    if (title) title.textContent = meta[0];
    if (subtitle) subtitle.textContent = meta[1];
    if (position) position.textContent = `${statsCategoryOrder.indexOf(target) + 1} de ${statsCategoryOrder.length}`;
    if (updateUrl) setStatsCategoryUrl(target);
    statsCategoryModal.show();
  }

  renderStats = renderStatsV2;
  populateStatsFilters();
  try {
    const savedFilters = JSON.parse(localStorage.getItem('dml_stats_filters') || '{}');
    analyticsFilters.forEach((control) => {
      const key = control.dataset.statsFilter;
      if (!(key in savedFilters)) return;
      if (control.type === 'checkbox') control.checked = Boolean(savedFilters[key]);
      else if (Array.from(control.options || []).some((option) => option.value === String(savedFilters[key]))) control.value = String(savedFilters[key]);
      else if (!control.options) control.value = String(savedFilters[key]);
    });
  } catch {}
  const publicSeason = new URLSearchParams(window.location.search).get('public_season') || new URLSearchParams(window.location.search).get('season_id') || '';
  const publicSeasonControl = document.querySelector('[data-stats-filter="season"]');
  if (publicSeason && publicSeasonControl && Array.from(publicSeasonControl.options).some((option) => option.value === publicSeason)) {
    publicSeasonControl.value = publicSeason;
  }
  analyticsFilters.forEach((control) => control.addEventListener('change', () => {
    const state = {};
    analyticsFilters.forEach((item) => { state[item.dataset.statsFilter] = item.type === 'checkbox' ? item.checked : item.value; });
    localStorage.setItem('dml_stats_filters', JSON.stringify(state));
    renderStats();
  }));
  analyticsTabs.forEach((tab) => tab.addEventListener('click', () => openStatsTab(tab.dataset.statsTab)));
  document.querySelector('[data-stats-category-prev]')?.addEventListener('click', () => {
    const index = statsCategoryOrder.indexOf(activeStatsCategory);
    openStatsTab(statsCategoryOrder[(index - 1 + statsCategoryOrder.length) % statsCategoryOrder.length]);
  });
  document.querySelector('[data-stats-category-next]')?.addEventListener('click', () => {
    const index = statsCategoryOrder.indexOf(activeStatsCategory);
    openStatsTab(statsCategoryOrder[(index + 1) % statsCategoryOrder.length]);
  });
  statsCategoryModalNode?.addEventListener('hidden.bs.modal', () => {
    Array.from(statsCategoryBody?.children || []).forEach((node) => {
      node.hidden = true;
      statsCategoryStaging?.appendChild(node);
    });
    activeStatsCategory = '';
    setStatsCategoryUrl('');
  });
  document.querySelector('[data-stats-open-filters]')?.addEventListener('click', () => {
    if (!statsFilterModal || !statsFilterBody) return;
    statsFilterNodes.forEach((node) => statsFilterBody.appendChild(node));
    statsFilterModal.show();
  });
  statsFilterModalNode?.addEventListener('hidden.bs.modal', () => {
    statsFilterNodes.forEach((node) => statsFilterStaging?.appendChild(node));
  });
  const statsGoRanking = document.querySelector('[data-stats-go-ranking]');
  if (statsGoRanking) statsGoRanking.addEventListener('click', () => {
    openStatsTab('rankings');
  });
  const statsClear = document.querySelector('[data-stats-clear]');
  if (statsClear) statsClear.addEventListener('click', () => {
    analyticsFilters.forEach((control) => {
      if (control.type === 'checkbox') control.checked = false;
      else control.value = control.dataset.statsFilter === 'sample' ? String(settings.stats_minimum_sample || 3) : '';
    });
    localStorage.removeItem('dml_stats_filters');
    renderStats();
  });
  if (analyticsRanking) analyticsRanking.addEventListener('change', renderStats);
  if (analyticsMetaEventType) analyticsMetaEventType.addEventListener('change', renderStats);
  if (analyticsMetaGroup) analyticsMetaGroup.addEventListener('change', renderStats);
  if (analyticsMetaPeriod) analyticsMetaPeriod.addEventListener('change', renderStats);
  if (analyticsAdvancedTop) analyticsAdvancedTop.addEventListener('change', renderStats);
  analyticsRankingEntities.forEach((button) => button.addEventListener('click', () => {
    analyticsRankingEntities.forEach((item) => item.classList.toggle('active', item === button));
    analyticsRankingPanels.forEach((panel) => { panel.hidden = panel.dataset.statsRankingPanel !== button.dataset.statsRankingEntity; });
  }));
  document.querySelectorAll('[data-quality-filter]').forEach((control) => control.addEventListener('change', renderStats));
  const statsExport = document.querySelector('[data-stats-export]');
  if (statsExport) statsExport.addEventListener('click', () => {
    const model = buildStatsModel();
    const rows = [['Deck', 'Equipo', 'Formato', 'PTS', 'MJ', 'MG', 'ME', 'MP', 'WR', 'WR primero', 'Muestra primero', 'WR segundo', 'Muestra segundo', 'DJ', 'DG', 'DP', 'DIF', 'Perfectas', 'D5', 'Top Cut', 'Top Cut ganados']];
    model.decks.forEach((deck) => rows.push([deck.name, deck.team, deck.ruleset, deck.points, deck.matches, deck.matchWins, deck.matchDraws, deck.matchLosses, deck.winRate + '%', deck.firstWinRate + '%', deck.firstStarts, deck.secondWinRate + '%', deck.secondStarts, deck.duelWins + deck.duelLosses + deck.duelDraws, deck.duelWins, deck.duelLosses, deck.duelDiff, deck.sweeps, deck.d5Wins, deck.topCutMatches, deck.topCutWins]));
    const csv = rows.map((row) => row.map((value) => '"' + String(value ?? '').replaceAll('"', '""') + '"').join(',')).join('\r\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'estadisticas-filtradas-' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
  });
  openStatsTab(localStorage.getItem('dml_stats_tab') || 'summary', false, false);
  renderStats();
  [compareA, compareB].forEach((select) => {
    if (select) select.addEventListener('change', renderStats);
  });

  const dashboardFormatButtons = Array.from(document.querySelectorAll('[data-dashboard-format-filter]'));
  const dashboardEventCards = Array.from(document.querySelectorAll('[data-dashboard-event-card]'));
  function applyDashboardFormatFilter(format = '') {
    const target = normalize(format);
    dashboardFormatButtons.forEach((button) => {
      button.classList.toggle('active', normalize(button.dataset.dashboardFormatFilter || '') === target);
    });
    dashboardEventCards.forEach((card) => {
      const cardFormat = normalize(card.dataset.dashboardFormat || '');
      card.hidden = Boolean(target && cardFormat !== target);
    });
  }
  dashboardFormatButtons.forEach((button) => {
    button.addEventListener('click', () => applyDashboardFormatFilter(button.dataset.dashboardFormatFilter || ''));
  });
  applyDashboardFormatFilter('');

  const rankingScopes = window.YgoDashboardRankingScopes;
  const dashboardEventScopes = rankingScopes.options(data);
  const dashboardRankingEvent = document.querySelector('[data-dashboard-ranking-event]');
  const dashboardRankingMetric = document.querySelector('[data-dashboard-ranking-metric]');
  const dashboardRankingSource = document.querySelector('[data-dashboard-ranking-source]');
  const dashboardRankingTitle = document.querySelector('[data-dashboard-ranking-title]');
  const dashboardRankingList = document.querySelector('[data-dashboard-ranking-list]');
  const dashboardLpCoverage = document.querySelector('[data-dashboard-lp-coverage]');

  function dashboardRankingLogo(url, fallback, className) {
    const cleanUrl = String(url || '').trim();
    return cleanUrl
      ? `<span class="${className} has-media"><img loading="lazy" decoding="async" src="${escapeHtml(cleanUrl)}" alt="${escapeHtml(fallback)}"></span>`
      : `<span class="${className}">${escapeHtml(String(fallback || 'EQ').replace(/[^a-z0-9]/gi, '').slice(0, 3).toUpperCase() || 'EQ')}</span>`;
  }

  function renderDashboardEventRanking(competitionId) {
    if (!dashboardRankingList) return;
    const scope = dashboardEventScopes.find(item => item.value === String(competitionId || ''));
    const competition = (data.competitions || []).find(item => String(item.id) === String(scope?.competitionId));
    if (!competition) {
      dashboardRankingList.innerHTML = '<p class="note">No hay un evento activo para clasificar.</p>';
      return;
    }

    if (dashboardRankingSource) dashboardRankingSource.textContent = scope.label;
    const rows = rankingScopes.ranking(scope, Array.isArray(competition.ranking) ? competition.ranking : []);
    const deckRows = rows.map((row) => {
      const deck = (data.allDecks || []).find((item) => String(item.id || '') === String(row.id || row.deck_id || ''))
        || (data.allDecks || []).find((item) => normalize(item.name) === normalize(row.name))
        || {};
      return {
        ...row,
        name: row.name || deck.name || 'Deck',
        team: row.team || deck.team || 'Sin equipo',
        logo: deck.cover_url || deck.logo_url || deck.team_logo || ''
      };
    });

    const teamMap = new Map();
    deckRows.forEach((row) => {
      const teamName = row.team || 'Sin equipo';
      const current = teamMap.get(teamName) || { name: teamName, pts: 0, pj: 0, pg: 0, pp: 0, cg: 0, cp: 0, dp: 0, logo: '' };
      const team = (data.teams || []).find((item) => normalize(item.name) === normalize(teamName)) || {};
      current.pts += Number(row.pts || 0);
      current.pj += Number(row.pj || 0);
      current.pg += Number(row.pg || row.wins || 0);
      current.pp += Number(row.pp || row.losses || 0);
      current.cg += Number(row.cg || row.duels_won || 0);
      current.cp += Number(row.cp || row.duels_lost || 0);
      current.dp += Number(row.dp || row.difference || 0);
      current.logo = current.logo || team.logo_url || row.team_logo || '';
      teamMap.set(teamName, current);
    });

    const teams = Array.from(teamMap.values()).sort((left, right) =>
      right.pts - left.pts || right.pg - left.pg || right.dp - left.dp || right.cg - left.cg || left.name.localeCompare(right.name)
    );
    deckRows.sort((left, right) =>
      Number(right.cg || right.duels_won || 0) - Number(left.cg || left.duels_won || 0)
      || Number(right.pts || 0) - Number(left.pts || 0)
      || Number(right.pj || 0) - Number(left.pj || 0)
      || String(left.name || '').localeCompare(String(right.name || ''))
    );
    const metric = dashboardRankingMetric?.value || 'points';
    const eventMatches = rankingScopes.matches(scope, allMatchList());
    const teamMetrics = new Map();
    const ensureTeamMetric = (name, logo = '') => {
      const key = normalize(name || 'Sin equipo');
      if (!teamMetrics.has(key)) teamMetrics.set(key, { name: name || 'Sin equipo', logo, damage_dealt: 0, damage_received: 0, damage_dealt_missing: 0, damage_received_missing: 0, surrenders: 0, lp_losses: 0 });
      const row = teamMetrics.get(key);
      row.logo = row.logo || logo;
      return row;
    };
    teams.forEach((team) => ensureTeamMetric(team.name, team.logo));
    let lpCoveredDuels = 0;
    let lpTotalDuels = 0;
    let playedMatches = 0;
    let drawnMatches = 0;
    let firstStartDuels = 0;
    let firstStartWins = 0;
    let secondStartDuels = 0;
    let secondStartWins = 0;
    eventMatches.forEach((match) => {
      const sideA = ensureTeamMetric(match.team_a || 'Sin equipo', match.team_a_logo || '');
      const sideB = ensureTeamMetric(match.team_b || 'Sin equipo', match.team_b_logo || '');
      const savedDuels = getSavedResult(match).filter(Boolean);
      const matchResult = evaluateDuel(savedDuels, match);
      if (matchResult.state === 'FINALIZADA') {
        playedMatches += 1;
        if (matchResult.winner === 'E') drawnMatches += 1;
      }
      const persistedDetails = parseJsonValue(match.duel_details || '', []);
      const details = savedDuels.map((winner, index) => {
        const persisted = Array.isArray(persistedDetails) ? persistedDetails.find((detail) => Number(detail.duel) === index + 1) : null;
        return persisted || { duel: index + 1, winner };
      });
      details.forEach((detail, detailIndex) => {
        lpTotalDuels += 1;
        const winner = String(detail.winner || '').toUpperCase();
        const starts = String(detail.starts_player || (detailIndex === 0 ? match.starts_player || '' : '')).toUpperCase();
        if (starts === 'A' || starts === 'B') {
          firstStartDuels += 1;
          secondStartDuels += 1;
          if (winner === starts) firstStartWins += 1;
          if ((winner === 'A' || winner === 'B') && winner !== starts) secondStartWins += 1;
        }
        const lpA = Number(detail.lp_a);
        const lpB = Number(detail.lp_b);
        const hasLpA = detail.lp_a !== '' && detail.lp_a !== null && detail.lp_a !== undefined && Number.isFinite(lpA);
        const hasLpB = detail.lp_b !== '' && detail.lp_b !== null && detail.lp_b !== undefined && Number.isFinite(lpB);
        if (hasLpA && hasLpB) lpCoveredDuels += 1;
        const history = Array.isArray(detail.lp_history) ? detail.lp_history : [];
        const grossLoss = (side) => history.filter((item) => item.side === side && item.operation !== 'reset').reduce((sum, item) => sum + Math.max(0, Number(item.before || 0) - Number(item.after || 0)), 0);
        const grossA = grossLoss('a');
        const grossB = grossLoss('b');
        const startA = Math.max(1, Number(detail.lp_start_a || 8000));
        const startB = Math.max(1, Number(detail.lp_start_b || 8000));
        if (hasLpA) {
          const damageToA = grossA || Math.max(0, startA - lpA);
          sideB.damage_dealt += damageToA;
          sideA.damage_received += damageToA;
        } else if (detail.defeat_method === 'life_points' && winner === 'B') {
          sideB.damage_dealt += startA;
          sideA.damage_received += startA;
        } else {
          sideB.damage_dealt_missing += 1;
          sideA.damage_received_missing += 1;
        }
        if (hasLpB) {
          const damageToB = grossB || Math.max(0, startB - lpB);
          sideA.damage_dealt += damageToB;
          sideB.damage_received += damageToB;
        } else if (detail.defeat_method === 'life_points' && winner === 'A') {
          sideA.damage_dealt += startB;
          sideB.damage_received += startB;
        } else {
          sideA.damage_dealt_missing += 1;
          sideB.damage_received_missing += 1;
        }
        if (detail.defeat_method === 'surrender') {
          if (winner === 'A') sideB.surrenders += 1;
          if (winner === 'B') sideA.surrenders += 1;
        }
        if (detail.defeat_method === 'life_points') {
          if (winner === 'A') sideB.lp_losses += 1;
          if (winner === 'B') sideA.lp_losses += 1;
        }
      });
    });

    const totalMatches = eventMatches.length;
    const pendingMatches = Math.max(0, totalMatches - playedMatches);
    const percentage = (wins, total) => total > 0 ? Math.round((wins / total) * 100) : 0;
    const generalStats = [
      { name: 'Partidas jugadas', value: playedMatches, label: `${playedMatches}` },
      { name: 'Partidas pendientes', value: pendingMatches, label: `${pendingMatches}` },
      { name: 'Total de partidas', value: totalMatches, label: `${totalMatches}` },
      { name: 'Victorias empezando primero', value: percentage(firstStartWins, firstStartDuels), label: `${percentage(firstStartWins, firstStartDuels)}% (${firstStartWins}/${firstStartDuels})` },
      { name: 'Victorias empezando segundo', value: percentage(secondStartWins, secondStartDuels), label: `${percentage(secondStartWins, secondStartDuels)}% (${secondStartWins}/${secondStartDuels})` },
      { name: 'Empates', value: drawnMatches, label: `${drawnMatches}` }
    ];

    const configurations = {
      general_stats: { title: 'Estadisticas generales del evento', rows: generalStats, value: (row) => row.value, label: (value, row) => row.label, type: 'summary' },
      points: { title: 'Equipos con mas puntos', rows: teams, value: (row) => Number(row.pts || 0), label: (value) => `${value} pts`, type: 'team' },
      duels: { title: 'Decks con mas duelos ganados', rows: deckRows, value: (row) => Number(row.cg || row.duels_won || 0), label: (value) => `${value} ${value === 1 ? 'duelo ganado' : 'duelos ganados'}`, type: 'deck' },
      damage_dealt: { title: 'LP quitados a oponentes en el evento', rows: Array.from(teamMetrics.values()), value: (row) => row.damage_dealt, label: (value, row) => `${value.toLocaleString('es-CO')} LP${row.damage_dealt_missing ? ` | ${row.damage_dealt_missing} sin LP` : ''}`, type: 'team' },
      damage_received: { title: 'LP perdidos por el equipo en el evento', rows: Array.from(teamMetrics.values()), value: (row) => row.damage_received, label: (value, row) => `${value.toLocaleString('es-CO')} LP${row.damage_received_missing ? ` | ${row.damage_received_missing} sin LP` : ''}`, type: 'team' },
      surrenders: { title: 'Equipos con mas rendiciones', rows: Array.from(teamMetrics.values()), value: (row) => row.surrenders, label: (value) => `${value} ${value === 1 ? 'rendicion' : 'rendiciones'}`, type: 'team' },
      lp_losses: { title: 'Equipos con mas derrotas por LP', rows: Array.from(teamMetrics.values()), value: (row) => row.lp_losses, label: (value) => `${value} ${value === 1 ? 'derrota' : 'derrotas'}`, type: 'team' }
    };
    const configuration = configurations[metric] || configurations.points;
    if (dashboardLpCoverage) {
      dashboardLpCoverage.hidden = !['damage_dealt', 'damage_received'].includes(metric);
      dashboardLpCoverage.textContent = `Cobertura LP: ${lpCoveredDuels}/${lpTotalDuels} duelos con valores completos. Los faltantes se identifican sin inventar montos.`;
    }
    const rankingRows = configuration.type === 'summary'
      ? configuration.rows.slice()
      : configuration.rows.slice().sort((left, right) =>
        configuration.value(right) - configuration.value(left) || String(left.name || '').localeCompare(String(right.name || ''))
      ).slice(0, 10);
    if (dashboardRankingTitle) dashboardRankingTitle.textContent = configuration.title;
    dashboardRankingList.classList.toggle('deck-ranks', configuration.type === 'deck');
    dashboardRankingList.classList.toggle('general-stats', configuration.type === 'summary');
    dashboardRankingList.innerHTML = rankingRows.length ? rankingRows.map((row, index) => configuration.type === 'summary' ? `
      <article class="dashboard-general-stat">
        <span class="dashboard-general-stat-icon"><i class="bi ${['bi-check2-circle', 'bi-hourglass-split', 'bi-calendar2-check', 'bi-arrow-up-circle', 'bi-arrow-down-circle', 'bi-dash-circle'][index] || 'bi-bar-chart'}"></i></span>
        <strong>${escapeHtml(row.name)}</strong>
        <em>${escapeHtml(configuration.label(configuration.value(row), row))}</em>
      </article>
    ` : `
      <article>
        <b>${index + 1}</b>
        ${dashboardRankingLogo(row.logo, row.name, configuration.type === 'deck' ? 'mini-card-art' : 'rank-avatar media-avatar')}
        <strong title="${escapeHtml(row.name)}">${escapeHtml(row.name)}</strong>
        <em>${configuration.label(configuration.value(row), row)}</em>
      </article>
    `).join('') : '<p class="note">Este evento aun no tiene datos para esta clasificacion.</p>';
  }

  if (dashboardRankingEvent) {
    dashboardRankingEvent.innerHTML = dashboardEventScopes.length
      ? dashboardEventScopes.map(scope => `<option value="${escapeHtml(scope.value)}">${escapeHtml(scope.label)}</option>`).join('')
      : '<option value="">Sin eventos activos</option>';
    const rankedScope = dashboardEventScopes.find(scope => {
      const competition = (data.competitions || []).find(c => String(c.id) === String(scope.competitionId));
      return rankingScopes.ranking(scope, competition?.ranking || []).some(row => Number(row.pj || 0) > 0);
    });
    dashboardRankingEvent.value = (rankedScope || dashboardEventScopes[0])?.value || '';
    dashboardRankingEvent.addEventListener('change', () => renderDashboardEventRanking(dashboardRankingEvent.value));
    if (dashboardRankingMetric) dashboardRankingMetric.addEventListener('change', () => renderDashboardEventRanking(dashboardRankingEvent.value));
    renderDashboardEventRanking(dashboardRankingEvent.value);
  }

  const auditSearch = document.querySelector('[data-audit-search]');
  const auditKind = document.querySelector('[data-audit-kind]');
  const auditEntity = document.querySelector('[data-audit-entity]');
  const auditAction = document.querySelector('[data-audit-action]');
  const auditRows = Array.from(document.querySelectorAll('[data-audit-row]'));

  function renderAuditFilter() {
    const query = normalize(auditSearch ? auditSearch.value : '');
    const kind = normalize(auditKind ? auditKind.value : '');
    const entity = normalize(auditEntity ? auditEntity.value : '');
    const action = normalize(auditAction ? auditAction.value : '');
    auditRows.forEach((row) => {
      const text = normalize(row.dataset.auditText || row.textContent || '');
      const rowEntity = normalize(row.dataset.auditEntity || '');
      const rowAction = normalize(row.dataset.auditAction || '');
      row.hidden = Boolean(
        (query && !text.includes(query))
        || (kind && !text.includes(kind))
        || (entity && rowEntity !== entity)
        || (action && rowAction !== action)
      );
    });
  }

  [auditSearch, auditKind, auditEntity, auditAction].forEach((control) => {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', renderAuditFilter);
  });

  function pairRows(names) {
    const rows = [];
    for (let i = 0; i < names.length; i += 2) rows.push([names[i], names[i + 1] || 'Pasa directo']);
    return rows;
  }

  document.querySelectorAll('[data-bracket]').forEach((node) => {
    const division = node.dataset.bracket;
    const status = competitionStatus('cup', division);
    if (!['active', 'completed', 'finalizada', 'finalizado'].includes(status)) {
      node.innerHTML = '<div class="match-pill muted">La llave se habilita cuando la copa este activa.</div>';
      return;
    }
    const names = data.divisions[division].decks.map((deck) => deck[1]);
    node.innerHTML = pairRows(names).map((pair, index) => `<div class="match-pill"><b>Llave ${index + 1}</b><br>${matchLabelWithTeam(pair[0])} vs ${matchLabelWithTeam(pair[1])}</div>`).join('');
  });

  const eternal = document.querySelector('[data-eternal-bracket]');
  if (eternal) {
    if (!officialDivisions.every((division) => hasPlayedMatches(division))) {
      eternal.innerHTML = '<div class="match-pill muted">Sin clasificacion a Copa Eternal hasta que existan tablas con resultados reales en todas las divisiones.</div>';
    } else {
    const names = officialDivisions.flatMap((division) => data.divisions[division].decks.slice(0, 3).map((deck) => deck[1]));
    eternal.innerHTML = pairRows(names).map((pair, index) => `<div class="match-pill"><b>${index === 0 ? 'Preliminar' : `Cuadro ${index}`}</b><br>${matchLabelWithTeam(pair[0])} vs ${matchLabelWithTeam(pair[1])}</div>`).join('');
    }
  }

  const worldGroups = document.querySelector('[data-world-groups]');
  if (worldGroups) {
    if (!officialDivisions.some((division) => hasPlayedMatches(division))) {
      worldGroups.innerHTML = '<article class="group-card"><h3>Sin clasificados</h3><p>El mundial no arma grupos temporales con semillas. Primero deben jugarse las fases que entregan cupos.</p></article>';
    } else {
    const pool = officialDivisions.flatMap((division) => data.divisions[division].decks.slice(0, 6).map((deck) => `${matchLabelWithTeam(deck[1])} (${division})`));
    worldGroups.innerHTML = Array.from({ length: 6 }, (_, group) => {
      const items = pool.slice(group * 4, group * 4 + 4).map((name) => `<li>${name}</li>`).join('');
      return `<article class="group-card"><h3>Grupo ${String.fromCharCode(65 + group)}</h3><ol>${items}</ol><span class="status-chip">Clasifican 2</span></article>`;
    }).join('');
    }
  }

  if (search) {
    search.addEventListener('input', () => renderSearchResults(searchResults, search.value, true));
    search.addEventListener('focus', () => renderSearchResults(searchResults, search.value, true));
    search.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const items = searchItems(search.value);
        if (items[0]) openSearchItem(items[0]);
        else {
          routeTo('buscar');
          if (searchPageInput) {
            searchPageInput.value = search.value;
            renderSearchResults(searchPageResults, search.value, false);
          }
        }
      }
      if (event.key === 'Escape' && searchResults) {
        searchResults.hidden = true;
      }
    });
    document.addEventListener('click', (event) => {
      if (searchResults && !event.target.closest('.searchbox')) searchResults.hidden = true;
    });
  }

  if (searchPageInput) {
    searchPageInput.addEventListener('input', () => renderSearchResults(searchPageResults, searchPageInput.value, false));
    renderSearchResults(searchPageResults, searchPageInput.value, false);
  }

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
        ? `<img loading="lazy" decoding="async" src="${escapeHtml(logo)}" alt="${escapeHtml(team?.name || 'Equipo')}">`
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

  function bindListFilter(inputSelector, rowSelector) {
    const input = document.querySelector(inputSelector);
    const rows = Array.from(document.querySelectorAll(rowSelector));
    if (!input || !rows.length) return;
    input.addEventListener('input', () => {
      const query = normalize(input.value);
      rows.forEach((row) => {
        const haystack = normalize(row.dataset.search || row.textContent || '');
        row.hidden = query ? !haystack.includes(query) : false;
      });
    });
  }

  const deckListSearch = document.querySelector('[data-deck-list-search]');
  const deckListFormat = document.querySelector('[data-deck-list-format-filter]');
  const deckListDivision = document.querySelector('[data-deck-list-division-filter]');
  const deckListTeam = document.querySelector('[data-deck-list-team-filter]');
  const deckListTier = document.querySelector('[data-deck-list-tier-filter]');
  const deckListActivity = document.querySelector('[data-deck-list-activity-filter]');
  const deckListOrder = document.querySelector('[data-deck-list-order]');
  const deckListCards = document.querySelector('[data-deck-list-cards-filter]');
  const deckListClear = document.querySelector('[data-deck-list-clear]');
  const deckListVisibleCount = document.querySelector('[data-deck-list-visible-count]');
  const deckListRows = Array.from(document.querySelectorAll('[data-deck-list-row]'));

  function applyDeckListFilters() {
    const query = normalize(deckListSearch?.value || '');
    const format = normalize(deckListFormat?.value || '');
    const division = normalize(deckListDivision?.value || '');
    const team = normalize(deckListTeam?.value || '');
    const tier = normalize(deckListTier?.value || '');
    const activity = deckListActivity?.value || '';
    const order = deckListOrder?.value || 'rank';
    const cards = deckListCards?.value || '';
    document.querySelectorAll('[data-deck-loaded-filter]').forEach(button=>button.setAttribute('aria-pressed',String(cards==='with_cards')));
    let visible = 0;
    deckListRows.forEach((row) => {
      const matches = Number(row.dataset.matches || 0);
      const show = (!query || normalize(row.dataset.search || row.textContent || '').includes(query))
        && (!format || normalize(row.dataset.format || '').includes(format))
        && (!division || normalize(row.dataset.division || '').includes(division))
        && (!team || normalize(row.dataset.team || '') === team)
        && (!tier || normalize(row.dataset.tier || 'sin tier') === tier)
        && (!activity || (activity === 'with_results' ? matches > 0 : matches === 0))
        && (!cards || (cards === 'with_cards' ? row.dataset.hasCards === '1' : row.dataset.hasCards !== '1'));
      row.hidden = !show;
      if (show) visible++;
    });
    const number = (row, key) => Number(row.dataset[key] || 0);
    deckListRows.slice().sort((left, right) => {
      if (order === 'name') return String(left.dataset.name || '').localeCompare(String(right.dataset.name || ''));
      if (order === 'matches') return number(right, 'matches') - number(left, 'matches') || number(left, 'rank') - number(right, 'rank');
      if (order === 'winrate') return number(right, 'winrate') - number(left, 'winrate') || number(right, 'matches') - number(left, 'matches');
      return number(left, 'rank') - number(right, 'rank');
    }).forEach((row) => row.parentElement?.appendChild(row));
    if (deckListVisibleCount) deckListVisibleCount.textContent = `${visible} de ${deckListRows.length} decks`;
  }

  [deckListSearch, deckListFormat, deckListDivision, deckListTeam, deckListTier, deckListActivity, deckListCards, deckListOrder].filter(Boolean).forEach((control) => {
    control.addEventListener(control === deckListSearch ? 'input' : 'change', applyDeckListFilters);
  });
  document.querySelectorAll('[data-deck-loaded-filter]').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();if(!deckListCards)return;deckListCards.value=deckListCards.value==='with_cards'?'':'with_cards';applyDeckListFilters();}));
  deckListClear?.addEventListener('click', () => {
    [deckListSearch, deckListFormat, deckListDivision, deckListTeam, deckListTier, deckListActivity, deckListCards].filter(Boolean).forEach((control) => { control.value = ''; });
    if (deckListOrder) deckListOrder.value = 'rank';
    applyDeckListFilters();
  });
  [search, deckListSearch].filter(Boolean).forEach((input) => {
    if (String(input.value || '').trim().toLowerCase() === 'null') input.value = '';
    if (String(input.placeholder || '').trim().toLowerCase() === 'null') input.placeholder = input === search ? 'Buscar decks, equipos o eventos' : 'Deck, equipo o arquetipo';
  });
  const teamListSearch = document.querySelector('[data-team-list-search]');
  const teamListFormat = document.querySelector('[data-team-list-format]');
  const teamListDivision = document.querySelector('[data-team-list-division]');
  const teamListRows = Array.from(document.querySelectorAll('[data-team-list-row]'));

  function teamListCompetitiveStats(team) {
    const decks = (data.allDecks || []).filter((deck) => String(deck.team_id || '') === String(team.id || ''));
    const ids = new Set(decks.map((deck) => String(deck.id || '')));
    const names = new Set(decks.map((deck) => normalize(deck.name || '')));
    const formats = new Set(decks.flatMap((deck) => Array.isArray(deck.format_codes) ? deck.format_codes : []));
    const divisions = new Set(decks.map((deck) => String(deck.division || '')).filter(Boolean));
    const totals = { matches: 0, wins: 0, draws: 0, losses: 0, duelWins: 0, duelDraws: 0, duelLosses: 0 };
    individualMatchList().forEach((match) => {
      const sideA = [match.deck_a_id, match.deck_c_id, match.deck_e_id].some((id) => ids.has(String(id || ''))) || matchSideDecks(match, 'a').some((name) => names.has(normalize(name)));
      const sideB = [match.deck_b_id, match.deck_d_id, match.deck_f_id].some((id) => ids.has(String(id || ''))) || matchSideDecks(match, 'b').some((name) => names.has(normalize(name)));
      if (!sideA && !sideB) return;
      const competition = statsCompetition(match) || {};
      const format = String(match.competition_ruleset || competition.event_ruleset || '').trim();
      if (format) formats.add(format);
      const result = evaluateDuel(getSavedResult(match).filter(Boolean), match);
      if (result.state !== 'FINALIZADA') return;
      const side = sideA ? 'A' : 'B';
      totals.matches += 1;
      if (result.winner === side) totals.wins += 1;
      else if (result.winner === 'E') totals.draws += 1;
      else totals.losses += 1;
      getSavedResult(match).filter(Boolean).forEach((winner) => {
        if (winner === side) totals.duelWins += 1;
        else if (winner === 'E') totals.duelDraws += 1;
        else totals.duelLosses += 1;
      });
    });
    totals.winRate = totals.matches ? Math.round((totals.wins / totals.matches) * 100) : 0;
    totals.formats = Array.from(formats);
    totals.divisions = Array.from(divisions);
    return totals;
  }

  function renderTeamList() {
    const query = normalize(teamListSearch?.value || '');
    const format = String(teamListFormat?.value || '');
    const division = String(teamListDivision?.value || '');
    teamListRows.forEach((row) => {
      const team = (data.teams || []).find((item) => String(item.id || '') === String(row.dataset.teamId || ''));
      if (!team) return;
      const stats = teamListCompetitiveStats(team);
      const set = (selector, value) => { const node = row.querySelector(selector); if (node) node.textContent = String(value); };
      set('[data-team-list-formats]', stats.formats.length ? stats.formats.map((value) => value.replaceAll('_', ' ').toUpperCase()).join(', ') : 'Sin formato');
      set('[data-team-list-divisions]', stats.divisions.join(', ') || 'Sin division');
      set('[data-team-list-matches]', stats.matches);
      set('[data-team-list-match-record]', `${stats.wins} / ${stats.draws} / ${stats.losses}`);
      set('[data-team-list-duel-record]', `${stats.duelWins} / ${stats.duelDraws} / ${stats.duelLosses}`);
      set('[data-team-list-winrate]', `${stats.winRate}%`);
      const haystack = normalize(`${row.dataset.search || ''} ${stats.formats.join(' ')} ${stats.divisions.join(' ')}`);
      row.hidden = Boolean((query && !haystack.includes(query)) || (format && !stats.formats.includes(format)) || (division && !stats.divisions.includes(division)));
    });
  }

  [teamListSearch, teamListFormat, teamListDivision].forEach((control) => control?.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', renderTeamList));
  document.querySelector('[data-team-list-clear]')?.addEventListener('click', () => {
    if (teamListSearch) teamListSearch.value = '';
    if (teamListFormat) teamListFormat.value = '';
    if (teamListDivision) teamListDivision.value = '';
    renderTeamList();
  });
  renderTeamList();

  const logoHistoryNode = document.querySelector('[data-logo-history-modal]');
  const logoHistoryModal = logoHistoryNode && window.bootstrap ? new bootstrap.Modal(logoHistoryNode) : null;
  document.querySelectorAll('[data-open-logo-history]').forEach((button) => {
    button.addEventListener('click', () => {
      const entityType = button.dataset.openLogoHistory || '';
      const entityId = entityType === 'deck'
        ? document.querySelector('[data-gestion-deck-id]')?.value
        : (entityType === 'team' ? document.querySelector('[data-gestion-team-id]')?.value : activeCompetitionConfigId);
      if (!entityId) {
        window.alert('Guarda o selecciona una ficha antes de consultar su historial de logos.');
        return;
      }
      const rows = (data.logoHistory || []).filter((row) => String(row.entity_type || '') === entityType && String(row.entity_id || '') === String(entityId));
      const title = logoHistoryNode?.querySelector('[data-logo-history-title]');
      const grid = logoHistoryNode?.querySelector('[data-logo-history-grid]');
      if (title) title.textContent = `Historial de logos de ${entityType === 'deck' ? 'deck' : (entityType === 'team' ? 'equipo' : 'evento')}`;
      if (grid) grid.innerHTML = rows.length ? rows.map((row) => `
        <form method="post" class="logo-history-item">
          <input type="hidden" name="action" value="select_logo_history">
          <input type="hidden" name="logo_entity_type" value="${escapeHtml(entityType)}">
          <input type="hidden" name="logo_entity_id" value="${escapeHtml(entityId)}">
          <input type="hidden" name="logo_history_url" value="${escapeHtml(row.logo_url || '')}">
          <img src="${escapeHtml(row.logo_url || '')}" alt="Logo guardado" loading="lazy">
          <span><b>${escapeHtml(row.created_at || '')}</b><small>${escapeHtml(row.source || 'guardado')}</small></span>
          <button class="btn btn-outline-info btn-sm" type="submit"><i class="bi bi-check2-circle"></i> Usar este logo</button>
        </form>`).join('') : '<p class="note">Esta ficha todavia no tiene logos guardados.</p>';
      logoHistoryModal?.show();
    });
  });

  const adminDeckSelect = document.querySelector('[data-gestion-deck-select]');
  const adminRemoveDeckId = document.querySelector('[data-gestion-remove-deck-id]');
  if (adminDeckSelect) {
    adminDeckSelect.addEventListener('change', () => {
      const deck = (data.allDecks || []).find((item) => String(item.id) === adminDeckSelect.value);
      setValue('[data-gestion-deck-id]', deck ? deck.id : '');
      setValue('[data-gestion-deck-name]', deck ? deck.name : '');
      setValue('[data-gestion-deck-team]', deck ? deck.team_id : '');
      setValue('[data-gestion-deck-team-label]', deck ? (deck.team || 'Sin equipo') : 'Sin equipo');
      setValue('[data-gestion-deck-division]', deck ? deck.division_id : '');
      setValue('[data-gestion-deck-rank]', deck ? deck.rank : '999');
      setValue('[data-gestion-deck-style]', deck ? deck.style : '');
      setValue('[data-gestion-deck-archetype]', deck ? deck.archetype : '');
      setValue('[data-gestion-deck-reason]', deck ? deck.reason : '');
      setValue('[data-gestion-deck-cards]', deck ? deck.key_cards : '');
      setValue('[data-gestion-deck-variations]', deck ? deck.variations : '');
      setValue('[data-gestion-deck-notes]', deck ? deck.notes : '');
      setValue('[data-gestion-deck-cover]', deck ? deck.cover_url : '');
      if (adminRemoveDeckId) adminRemoveDeckId.value = deck ? deck.id : '';
    });
  }

  const adminCompetitionSelect = document.querySelector('[data-gestion-competition-select]');
  const adminArchiveCompetitionId = document.querySelector('[data-gestion-archive-competition-id]');
  const adminCompetitionType = document.querySelector('[data-gestion-competition-type]');
  const adminCompetitionTemplate = document.querySelector('[data-gestion-competition-template]');
  const adminCompetitionDivision = document.querySelector('[data-gestion-competition-division]');
  const adminCompetitionRuleset = document.querySelector('[data-gestion-competition-ruleset]');
  const competitionNameInput = document.querySelector('[data-gestion-competition-name]');
  const competitionCodePreview = document.querySelector('[data-competition-code-preview]');
  const rulesetSelect = document.querySelector('[data-ruleset-select]');
  const rulesetByCode = new Map((data.rulesets || []).map((ruleset) => [String(ruleset.code || ''), ruleset]));
  function nextYuCompetitionCode() {
    const max = (data.competitions || []).reduce((current, competition) => {
      const match = String(competition.code || '').match(/^YU-(\d+)$/i);
      return match ? Math.max(current, Number(match[1] || 0)) : current;
    }, 0);
    return `YU-${String(max + 1).padStart(2, '0')}`;
  }
  function updateCompetitionCodePreview() {
    if (!competitionCodePreview) return;
    const existingCode = document.querySelector('[data-gestion-competition-code]')?.value || '';
    if (existingCode) {
      competitionCodePreview.value = existingCode;
      return;
    }
    competitionCodePreview.value = nextYuCompetitionCode();
  }
  function syncParticipantsFromMatchFormat() {
    const select = document.querySelector('[data-gestion-competition-match-format]');
    const matchFormat = select?.value || '1v1';
    const participants = document.querySelector('[data-gestion-competition-participants]');
    const configured = select?.selectedOptions?.[0]?.dataset?.participants || '';
    if (participants) participants.value = configured || (matchFormat.startsWith('3') ? '3' : (matchFormat.startsWith('2') ? '2' : '1'));
    setValue('[data-gestion-competition-participation]', matchFormat === 'ffa'?'FREE_FOR_ALL':matchFormat === '3v3' ? 'TEAM_3V3' : (matchFormat === '2v2' ? 'TAG_2V2' : 'INDIVIDUAL_1V1'));
  }
  function selectCompetitionRulesetVersion(value) {
    const select = document.querySelector('[data-gestion-competition-ruleset-version]');
    if (!select) return;
    const wanted = String(value || '').trim();
    if (!wanted) {
      select.selectedIndex = 0;
      return;
    }
    const normalized = wanted.toLowerCase();
    const option = Array.from(select.options).find((item) => {
      const text = `${item.value} ${item.textContent || ''}`.toLowerCase();
      return item.value === wanted || text.includes(normalized) || normalized.includes(item.value.toLowerCase());
    });
    if (option) select.value = option.value;
  }
  function inferRulesetCodeFromVersionOption(option) {
    const raw = `${option?.dataset?.ruleset || ''} ${option?.textContent || ''} ${option?.value || ''}`.toLowerCase();
    if (raw.includes('master duel')) return 'master_duel';
    if (raw.includes('speed duel')) return 'speed_duel';
    if (raw.includes('edison')) return 'edison';
    if (raw.includes('goat')) return 'goat';
    if (raw.includes('genesys') || raw.includes('genesis')) return 'genesis';
    if (raw.includes('ocg') && !raw.includes('tcg')) return 'ocg';
    if (raw.includes('tcg')) return 'tcg_advanced';
    return '';
  }
  function syncRulesetFromVersion(versionSelect, rulesetSelect) {
    if (!versionSelect || !rulesetSelect) return;
    const inferred = inferRulesetCodeFromVersionOption(versionSelect.selectedOptions?.[0]);
    if (inferred && Array.from(rulesetSelect.options).some((option) => option.value === inferred)) {
      rulesetSelect.value = inferred;
    }
  }
  function syncRulesetFromDivision(divisionSelect, rulesetSelect) {
    if (!divisionSelect || !rulesetSelect) return;
    const option = divisionSelect.selectedOptions?.[0];
    const direct = String(option?.dataset?.ruleset || '').trim();
    const raw = `${option?.textContent || ''} ${divisionSelect.value || ''}`.toLowerCase();
    const inferred = direct || (raw.includes('rush') ? 'rush_duel'
      : raw.includes('duel link') || raw.includes('duel_links') ? 'duel_links'
        : raw.includes('speed') ? 'speed_duel'
          : raw.includes('edison') ? 'edison'
            : raw.includes('goat') ? 'goat'
              : raw.includes('genesis') ? 'genesis'
                : raw.includes('ocg') ? 'ocg'
                  : raw.includes('tcg') || raw.includes('avanzado') ? 'tcg_advanced'
                    : '');
    if (inferred && Array.from(rulesetSelect.options).some((option) => option.value === inferred)) {
      rulesetSelect.value = inferred;
    }
  }
  function applyRulesetToCompetition(force = false) {
    const ruleset = rulesetByCode.get(String(adminCompetitionRuleset?.value || '')) || {};
    const fill = (selector, value) => {
      const node = document.querySelector(selector);
      if (node && (force || !String(node.value || '').trim())) node.value = value == null ? '' : value;
    };
    fill('[data-gestion-competition-best-of]', eventTemplateByCode(adminCompetitionTemplate?.value || '')?.best_of || ruleset.best_of || '3');
    fill('[data-gestion-competition-schedule]', ruleset.schedule_strategy || 'manual');
    fill('[data-gestion-competition-deck-pool]', ['division', 'all_active'].includes(String(ruleset.deck_pool || '')) ? ruleset.deck_pool : 'all_active');
    const setText = (selector, value) => {
      const node = document.querySelector(selector);
      if (node && 'value' in node) node.value = value || 'Por definir';
      else if (node) node.textContent = value || 'Por definir';
    };
    setText('[data-ruleset-version]', ruleset.banlist_version || 'Por definir');
    if (force) selectCompetitionRulesetVersion(ruleset.banlist_version || ruleset.banlist_name || '');
    updateCompetitionCodePreview();
  }
  const competitionTypePresets = {
    qualifier: { format: 'suizo clasificatorio', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'auto_swiss', execution: 'swiss_top_cut', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'QUALIFIES_TO_EVENT', source: 'MANUAL', access: 'QUALIFIER', phases: 'TOP_CUT', advancement: 'TOP_N', drawPolicy: 'USE_TIEBREAKER', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Dos victorias consecutivas o ventaja al cierre D4/D5', defeat: 'Menor marcador al cierre del match', draw: 'D5 empatado o dos empates consecutivos' },
    tournament: { format: 'torneo competitivo', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'manual', execution: 'manual', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'STANDALONE', source: 'MANUAL', access: 'OPEN', phases: 'NONE', advancement: 'MANUAL_SELECTION', drawPolicy: 'USE_TIEBREAKER', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Victoria por match segun reglamento', defeat: 'Derrota por match segun reglamento', draw: 'Empate segun reglamento' },
    league: { format: 'liga todos contra todos', ruleset: 'tcg_advanced', deckPool: 'division', schedule: 'auto_round_robin', execution: 'round_robin', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'SEASON', source: 'DIVISION', access: 'OPEN', phases: 'SEASON_PLAYOFF_FINAL', advancement: 'RANKING_THRESHOLD', drawPolicy: 'ALLOWED', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Victoria por match segun D1-D5', defeat: 'Derrota por match segun D1-D5', draw: 'Empate valido solo cuando el reglamento lo permita' },
    cup: { format: 'eliminacion directa', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'auto_knockout', execution: 'single_elimination', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'COMPETITIVE', flow: 'STANDALONE', source: 'MANUAL', access: 'OPEN', phases: 'FINAL', advancement: 'WINNER_ONLY', drawPolicy: 'NOT_ALLOWED', rankingImpact: 'FULL', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Ganar el match para avanzar de ronda', defeat: 'Perder el match elimina o envia a llave secundaria', draw: 'Empate genera desempate completo' },
    world: { format: 'grupos y eliminacion directa', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'manual', execution: 'groups_knockout', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'WORLD', flow: 'CHAMPIONSHIP_PATH', source: 'PREVIOUS_EVENT', access: 'INVITATIONAL', phases: 'GROUPS_TOP_CUT_FINAL', advancement: 'TOP_N', drawPolicy: 'NOT_ALLOWED', rankingImpact: 'BONUS', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Victoria suma en grupos o avanza en llave', defeat: 'Derrota suma en grupos o elimina en llave', draw: 'Empate permitido en grupos; desempate en eliminatoria' },
    final: { format: 'final unica', ruleset: 'tcg_advanced', deckPool: 'all_active', schedule: 'manual', execution: 'series', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'CHAMPIONSHIP', flow: 'RECEIVES_FROM_EVENT', source: 'PREVIOUS_EVENT', access: 'INVITATIONAL', phases: 'FINAL', advancement: 'WINNER_ONLY', drawPolicy: 'NOT_ALLOWED', rankingImpact: 'BONUS', deckPolicy: 'OPTIONAL_REGISTRATION', victory: 'Ganar la serie final', defeat: 'Perder la serie final', draw: 'Empate fuerza desempate completo' },
    friendly: { format: 'amistoso rotativo', ruleset: 'custom', deckPool: 'all_active', schedule: 'friendly_rotation', execution: 'manual', participation: 'INDIVIDUAL_1V1', construction: 'CONSTRUCTED', environment: 'PRESENCIAL', level: 'CASUAL', flow: 'STANDALONE', source: 'MANUAL', access: 'OPEN', phases: 'NONE', advancement: 'MANUAL_SELECTION', drawPolicy: 'ALLOWED', rankingImpact: 'NONE', deckPolicy: 'NO_REGISTRATION', victory: 'Resultado amistoso registrado en historial', defeat: 'Resultado amistoso registrado en historial', draw: 'Empate amistoso permitido' },
    special: { format: 'evento especial editable', ruleset: 'custom', deckPool: 'all_active', schedule: 'manual', execution: 'custom', participation: 'INDIVIDUAL_1V1', construction: 'CUSTOM', environment: 'PRESENCIAL', level: 'SPECIAL', flow: 'CUSTOM', source: 'CUSTOM', access: 'CUSTOM', phases: 'CUSTOM', advancement: 'CUSTOM', drawPolicy: 'CUSTOM', rankingImpact: 'CUSTOM', deckPolicy: 'CUSTOM', victory: 'Condicion definida por el evento', defeat: 'Condicion definida por el evento', draw: 'Condicion definida por el evento' },
  };
  const competitionTypeDescriptions = {
    qualifier: 'Clasificatorio: ordena participantes, habilita ranking de entrada y suele alimentar ligas, copas o fases posteriores.',
    tournament: 'Torneo competitivo: evento flexible para rondas manuales, suizo o estructuras configurables sin ascensos ni descensos propios de liga.',
    league: 'Liga: usa tablas, calendario por jornadas, pool por formato/division y puede manejar ascensos, descensos o bloqueo de movimientos.',
    cup: 'Copa: orientada a eliminacion, grupos con llave final o rondas programadas donde el ganador avanza.',
    world: 'Mundial: evento global o cruzado, normalmente con grupos, eliminacion y reglas mas amplias que una copa normal.',
    final: 'Final: fase decisiva o cierre de evento. Pensada para series, top cut, playoffs o combate unico por titulo.',
    friendly: 'Amistoso: combate/evento de practica que registra historial sin afectar necesariamente tablas principales.',
    special: 'Especial: evento editable para reglas propias, exhibiciones, formatos alternativos o casos que no encajan en los tipos deportivos base.'
  };
  const executionByCompetitionType = data.executionModelsByType || {
    qualifier: ['manual', 'swiss', 'swiss_top_cut', 'top_cut', 'flights', 'ladder', 'custom'],
    tournament: ['manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'groups', 'groups_knockout', 'top_cut', 'bracket', 'flights', 'custom'],
    league: ['manual', 'round_robin', 'double_round_robin', 'league_points', 'season_playoffs', 'playoffs', 'custom'],
    cup: ['manual', 'single_elimination', 'double_elimination', 'groups_knockout', 'top_cut', 'bracket', 'playoffs', 'custom'],
    world: ['manual', 'groups', 'groups_knockout', 'swiss_top_cut', 'top_cut', 'bracket', 'single_elimination', 'custom'],
    final: ['manual', 'series', 'single_elimination', 'top_cut', 'playoffs', 'custom'],
    friendly: ['manual', 'swiss', 'round_robin', 'single_elimination', 'king_of_the_hill', 'gauntlet', 'custom'],
    special: ['manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'round_robin', 'double_round_robin', 'groups', 'groups_knockout', 'league_points', 'playoffs', 'top_cut', 'bracket', 'flights', 'ladder', 'king_of_the_hill', 'gauntlet', 'series', 'season_playoffs', 'custom']
  };
  const executionDescriptions = {
    manual: 'Manual: el organizador controla emparejamientos, calendario y avance sin generacion automatica estricta.',
    swiss: 'Suizo: rondas por rendimiento, sin eliminacion inmediata. Sirve para clasificar por puntos o desempates.',
    swiss_top_cut: 'Suizo + corte: primero se juegan rondas suizas y luego los mejores pasan a eliminacion directa.',
    single_elimination: 'Eliminacion directa: perder elimina o deja fuera de la llave principal.',
    double_elimination: 'Doble eliminacion: el participante necesita dos derrotas para quedar eliminado.',
    round_robin: 'Round robin: todos contra todos, ideal para liga, grupos o tabla por puntos.',
    double_round_robin: 'Ida y vuelta: todos se enfrentan dos veces, alternando localia/lado o segunda vuelta.',
    groups: 'Grupos: separa participantes en grupos antes de ordenar o clasificar.',
    groups_knockout: 'Grupos + eliminacion: fase de grupos y luego llave final con clasificados.',
    season_playoffs: 'Temporada + playoffs: tabla regular y fase final para definir campeon.',
    ladder: 'Ladder interno: se programan dos rondas por inscrito. Se alternan retos entre posiciones vecinas; si gana el de abajo, intercambian posicion. No se admiten empates; la primera posicion al cierre gana.',
    king_of_the_hill: 'King of the Hill: el primer inscrito defiende. Los demas retan una vez en orden de inscripcion; el ganador permanece y el ultimo defensor gana el evento.',
    gauntlet: 'Gauntlet: el primer inscrito debe vencer a todos los demas, uno a uno y en orden. La primera derrota termina el intento sin campeon global; si supera todos los retos, gana el evento.',
    flights: 'Flights: grupos de hasta cuatro por orden inicial, eliminacion directa dentro de cada grupo y llave final entre sus ganadores. Los descansos no crean combates ni XP.',
    series: 'Serie: enfrentamiento unico, best of X o conjunto corto definido por el organizador.'
  };
  function eventCreateGuideText(selector, value) {
    const node = document.querySelector(selector);
    if (node) node.textContent = value || '-';
  }
  function optionValueOrDefault(selector, value, fallback) {
    const node = document.querySelector(selector);
    if (!node) return value || fallback || '';
    const raw = String(value || '').trim();
    if (raw && Array.from(node.options || []).some((option) => option.value === raw)) return raw;
    const normalized = normalize(raw).replace(/\s+/g, '_').toUpperCase();
    if (normalized && Array.from(node.options || []).some((option) => option.value === normalized)) return normalized;
    return fallback;
  }
  function eventModelWarnings(config) {
    const cap = (data.executionCapabilities || {})[config.execution_model || 'manual'];
    const warnings = cap?.reason ? [cap.reason] : [];
    const template = eventTemplateByCode(config.prompt_template || '');
    if (template) {
      const originalEngineReason = (data.executionCapabilities || {})[template.execution_model]?.reason;
      warnings.push(...(template.activation_restrictions || []).filter(message => message !== originalEngineReason && !(config.teamConfig && message.startsWith('Configura Equipos y alineaciones'))));
    }
    warnings.push(...(window.YgoEventModels?.contractErrors(config,template)||[]));
    return [...new Set(warnings)];
  }
  function filterCompetitionVersionOptions() {
    const select = document.querySelector('[data-gestion-competition-ruleset-version]');
    if (!select) return;
    const family = adminCompetitionRuleset?.value || 'custom';
    const words = {tcg_advanced: 'tcg', ocg: 'ocg', goat: 'goat', edison: 'edison', genesis: 'genesys', speed_duel: 'speed duel', master_duel: 'master duel', rush_duel: 'rush', duel_links: 'duel links'};
    for (const option of select.options) {
      const compatible = !option.value || family === 'custom' || String(option.dataset.system || '').toLowerCase().includes(words[family] || family);
      option.hidden = !compatible; option.disabled = !compatible;
    }
    if (select.selectedOptions[0]?.disabled) select.value = '';
  }
  function workflowPolicyDescription(model, state = null) {
    const base = 'Reglamento interno Station X 2026 v1. Orden inicial: orden de inscripcion, conservado al programar. ';
    const descriptions = {
      swiss: 'Rondas: ceil(log2 N), maximo N-1. Victoria 3, empate 1, derrota 0. BYE: 3 puntos sin match ni XP; al ultimo elegible sin BYE anterior. Sin repetir rival; se bloquea si no hay ronda completa. Desempates: puntos, Buchholz, diferencia de juegos y orden inicial.',
      swiss_top_cut: 'Suizo progresivo; despues, Top N de eliminacion directa. N es la mayor potencia de 2 hasta la mitad de participantes y maximo 8 (minimo 2). Sin sustituciones ni duplicados. Desempates: puntos, Buchholz, diferencia de juegos y orden inicial.',
      groups: 'Grupos de hasta 4 por siembra en serpiente. Cada grupo juega todos contra todos a una vuelta. Tabla y ganador independientes por grupo. Puntos 3/1/0; desempates por Buchholz, diferencia de juegos y orden inicial.',
      groups_knockout: 'Grupos de hasta 4 por siembra en serpiente; una vuelta. Clasifican los dos primeros de cada grupo. Cruces iniciales: primero contra segundo de otro grupo; siguientes rondas por eliminacion directa. Los BYE de llave no cuentan como combates ni XP.',
      series: 'Exactamente 2 decks. Gana la serie el primero en ganar 2 matches (maximo 3). El mejor de de cada match se configura por separado. Un empate se resuelve en el mismo match antes de avanzar.',
      double_elimination: 'Cuadros superior e inferior por orden inicial; dos derrotas eliminan. BYE sin derrota, combate ni XP. Final entre invicto y ganador del inferior; si el invicto pierde, se juega una final de reset.',
      season_playoffs: 'Temporada regular a una vuelta y despues playoffs. Top N: mayor potencia de 2 hasta la mitad de participantes y maximo 8 (minimo 2). Puntos 3/1/0; desempates: Buchholz, diferencia de juegos y orden inicial.'
    };
    if (!descriptions[model]) return '';
    const actual = state ? ` Participantes fijados: ${state.roster.length}. Rondas suizas: ${state.policy.swiss_rounds}. Corte: ${state.policy.top_n}. Etapa actual: ${state.round}.` : '';
    const impact=document.querySelector('[data-gestion-competition-ranking-impact]')?.value||'FULL';
    const points=state?.policy?.points||{win:impact==='BONUS'?4:impact==='REDUCED'?1:3,draw:impact==='REDUCED'?0:1,loss:0,bye:3};
    const overrides=[...document.querySelectorAll('[data-parameter]')].filter(input=>!input.disabled&&input.value!=='').map(input=>input.closest('label').firstChild.textContent.trim()+': '+input.value).join('; ');
    return base + 'Valores base cuando no hay ajustes manuales: '+descriptions[model]+actual+' Escala aplicada a la tabla: victoria '+points.win+', empate '+points.draw+', derrota '+points.loss+'; BYE '+points.bye+' cuando corresponde.'+(overrides?' Ajustes elegidos: '+overrides+'.':'')+' Las fases se crean al completar los resultados de la anterior. Estas reglas no certifican un torneo oficial.';
  }
  function renderEventModelDiagnostic() {
    const box = document.querySelector('[data-event-model-diagnostic]');
    if (!box) return;
    const config={prompt_template:adminCompetitionTemplate?.value};
    for(const field of ['type','execution_model','phase_structure','advancement_rule','best_of','participant_source','match_format'])config[field]=document.querySelector('[data-gestion-competition-'+field.replaceAll('_','-')+']')?.value;
    const warnings = eventModelWarnings(config);
    const structural=window.YgoEventModels?.contractErrors(config,eventTemplateByCode(config.prompt_template))||[];
    document.querySelector('[data-gestion-competition-execution-model]')?.setCustomValidity(structural.join(' '));
    box.hidden = warnings.length === 0;
    box.textContent = warnings.length ? 'Revisa la configuracion antes de guardar o programar: ' + warnings.join(' ') : '';
  }
  document.querySelectorAll('[data-parameter]').forEach(input=>input.addEventListener('input',()=>updateEventCreateGuide()));
  function updateEventCreateGuide() {
    const execution=document.querySelector('[data-gestion-competition-execution-model]')?.value;
    const parameterFields={swiss:['swiss_rounds'],swiss_top_cut:['swiss_rounds','top_n'],groups:['group_max_size'],groups_knockout:['group_max_size','group_qualifiers'],season_playoffs:['top_n'],series:['series_matches'],flights:['group_max_size']}[execution]||[];
    document.querySelectorAll('[data-parameter]').forEach(input=>{const applies=parameterFields.includes(input.dataset.parameter);input.disabled=!applies;input.closest('label').hidden=!applies;});
    filterCompetitionVersionOptions();
    renderEventModelDiagnostic();
    const oldPolicy = document.querySelector('[data-event-workflow-policy]');
    if (oldPolicy) { oldPolicy.hidden = true; oldPolicy.textContent = ''; }
    renderEventModelImpact();
  }
  function selectedOptionText(selector) {
    const node = document.querySelector(selector);
    return node?.selectedOptions?.[0]?.textContent?.trim() || node?.value || '';
  }
  function renderEventModelImpact(saved = null) {
    const guide = document.querySelector(saved ? '[data-event-saved-reading]' : '[data-event-guide-content]');
    const sourceForm = document.querySelector('.event-create-form');
    if (!sourceForm) return;
    const form = saved ? sourceForm.cloneNode(true) : sourceForm;
    if (saved) {
      for (const node of form.querySelectorAll('input[name], select[name], textarea[name]')) {
        if (node.type === 'file') continue;
        const key = node.name.replace(/^competition_/, '').replace(/\[\]$/, '');
        const stored = saved[key];
        if (node.multiple) {
          const ids = (saved.division_ids || []).map(String);
          for (const option of node.options) option.selected = ids.includes(option.value);
        } else if (node.type === 'checkbox') node.checked = Number(stored) === 1;
        else {
          const raw = stored == null ? '' : String(stored);
          if (node.tagName === 'SELECT' && raw && !Array.from(node.options).some(o => o.value === raw)) {
            const option = new Option(raw, raw); node.append(option);
          }
          node.value = raw;
        }
      }
      const season = form.querySelector('[data-event-season-name]');
      if (season) season.value = saved.season_name || (data.seasons || []).find(s => String(s.id) === String(saved.season_id))?.name || 'Sin temporada registrada';
    }
    if (!guide || !form) return;
    const field = key => form.querySelector(`[data-gestion-competition-${key}]`);
    const value = key => field(key)?.value || '';
    const label = key => {
      const node = field(key);
      return node?.multiple ? Array.from(node.selectedOptions).map(o => o.textContent.trim()).join(', ')
        : node?.selectedOptions?.[0]?.textContent?.trim() || node?.value || 'Sin seleccionar';
    };
    const cards = [];
    const add = (title, chosen, explanation) => cards.push(`<article class="event-guide-card"><span>${escapeHtml(title)}</span><strong>${escapeHtml(chosen || 'Sin seleccionar')}</strong><p>${escapeHtml(explanation)}</p></article>`);
    const explanations = {
      advancement: {
        FINAL_STANDINGS: 'El evento termina con su tabla. No se crea una eliminatoria ni otra fase interna.',
        SOURCE_ADMISSION: 'La tabla sirve de fuente para otro evento. Las plazas y los criterios se guardan en Fuentes y clasificados del destino; no se adjudican cupos implicitos.',
        MANUAL_ENTRIES: 'La seleccion manual fija los participantes de entrada. No genera emparejamientos ni avances automaticos; los titulos manuales requieren adjudicacion expresa en Palmares.',
        GROUP_TOP_K: 'Clasifican los cupos configurados de cada grupo; despues avanza el ganador de cada cruce de la llave.',
        MANUAL_SELECTION: 'El organizador elige los clasificados. Esta opcion no calcula por si sola un corte automatico.',
        TOP_N: 'Clasifican los primeros puestos hasta completar el cupo. La cantidad depende del sistema competitivo y de sus parametros.',
        TOP_PERCENTAGE: 'En Fuentes y clasificados, distribuye el total de plazas entre fuentes mediante porcentajes que sumen 100. Los cupos se llenan con los primeros puestos; no calcula un porcentaje de todos los inscritos de cada fuente.',
        MINIMUM_POINTS: 'En Fuentes y clasificados, define puntos minimos y plazas exactas. Solo se admiten puestos con al menos esos puntos; si no hay suficientes, se rechaza la carga sin sustitutos.',
        GROUP_POSITION: 'La clasificacion depende del puesto dentro de cada grupo; no de una tabla que mezcle todos los grupos.',
        WINNER_ONLY: 'El ganador es quien avanza. En una eliminatoria, el resultado debe quedar resuelto antes de crear el siguiente cruce.',
        RUNNER_UP: 'El criterio selecciona al segundo puesto del evento de origen, en lugar del campeon.',
        RANKING_THRESHOLD: 'La clasificacion se decide por una posicion de corte en la tabla. Debes indicar hasta que puesto se acepta.',
        BEST_RECORD: 'En Fuentes y clasificados, se ordena por proporcion de victorias sobre encuentros disputados; despues por menor numero de derrotas y por el orden verificado de la fuente. Se carga el numero exacto de plazas configurado.',
        CUSTOM: 'El avance requiere una regla propia y parametros definidos; elegir Personalizado no crea esa regla automaticamente.'
      },
      draw: {
        ALLOWED: 'La politica admite un resultado empatado. Las rondas de eliminacion necesitan un ganador aunque la fase de tabla admita empates.',
        NOT_ALLOWED: 'No se acepta cerrar el enfrentamiento con empate; debe registrarse un ganador valido.',
        EXTRA_DUEL: 'Un empate queda pendiente de un duelo de desempate antes de cerrar el resultado.',
        EXTRA_MATCH: 'Un empate queda pendiente de desempate. Esta seleccion no crea automaticamente otro enfrentamiento en el calendario.',
        SUDDEN_DEATH: 'Un empate requiere resolverse con muerte subita segun el reglamento; no se sortea un ganador.',
        USE_TIEBREAKER: 'Si el resultado termina igualado, se debe completar el desempate antes de cerrarlo. El orden de una tabla usa los criterios del sistema competitivo.',
        MANUAL_DECISION: 'El organizador revisa el empate. Las restricciones de una fase eliminatoria siguen exigiendo un ganador.',
        CUSTOM: 'La politica de empate requiere una definicion propia. Las reglas de resultado y de la fase siguen vigentes.'
      }
    };
    const type = value('type'), model = value('execution-model');
    const template = eventTemplateByCode(value('template'));
    if(template?.purpose)add('Finalidad de la plantilla',template.label,template.purpose);
    if(template?.difference&&!saved)add('Qué distingue este modelo',template.label,template.difference);
    add('Tipo de evento', label('type'), competitionTypeDescriptions[type] || 'Determina la categoria con la que se organiza y consulta el evento.');
    add('Modelo preconfigurado', template?.label || 'Personalizado / sin plantilla', template
      ? (saved ? 'Es la plantilla con la que se configuro el evento. Los valores de esta ficha corresponden a lo guardado, aunque difieran de los valores iniciales de la plantilla.' : 'Este modelo rellena valores iniciales. La lectura de abajo muestra la configuracion actual, incluidos los cambios que hagas despues de elegirlo.')
      : 'No hay una plantilla registrada. Se utiliza la configuracion particular del evento.');
    let systemText = executionDescriptions[model] || {
      league_points: 'Los resultados alimentan una tabla por puntos. Esta opcion no significa ida y vuelta: eso se elige con el sistema Ida y vuelta.',
      top_cut: 'Los clasificados disputan un corte final. Deben quedar definidos los cupos y la forma de resolver sus cruces.',
      bracket: 'Los enfrentamientos se organizan en una llave. Deben quedar definidos el acceso y el avance de los participantes.',
      playoffs: 'Fase decisiva entre clasificados de una etapa anterior. Deben definirse los cupos y los cruces.',
      custom: 'Requiere definir como se empareja, se puntua y se avanza. No activa automaticamente un sistema nuevo.'
    }[model] || 'Consulta los requisitos de este sistema antes de programar el evento.';
    const practical = {
      swiss: ' Cada ronda se crea al completar la anterior. Con 8 inscritos se calculan 3 rondas; con 10, 4 rondas. Se empareja por rendimiento sin repetir rival y se bloquea la siguiente ronda si no hay cruces validos para completarla. Victoria: 3 puntos; empate: 1; derrota: 0. Si alguien descansa por numero impar, recibe 3 puntos sin combate ni XP. A igualdad de puntos se compara la puntuacion de sus rivales, despues la diferencia de juegos y el orden inicial.',
      swiss_top_cut: ' Se completa primero el suizo; despues juegan los mejores en eliminacion directa. El corte admite 2, 4 u 8 participantes segun el total inscrito.',
      groups: ' Se forman grupos de hasta 4; dentro de cada grupo todos se enfrentan una vez. Cada grupo tiene su propia tabla.',
      groups_knockout: ' Se forman grupos de hasta 4. Pasan los dos primeros de cada grupo y se cruzan primeros contra segundos de otro grupo.',
      series: ' Se inscriben dos participantes. Gana la serie quien gana dos enfrentamientos, con un maximo de tres. El formato de partida de abajo se aplica a cada enfrentamiento.',
      double_elimination: ' Hay una llave principal y otra de recuperacion. Si el finalista invicto pierde la primera final, se disputa una segunda final.',
      season_playoffs: ' Primero se juega todos contra todos a una vuelta. Despues pasan 2, 4 u 8 a la fase final, segun el total inscrito.'
    };
    systemText += practical[model] || '';
    add('Sistema competitivo', label('execution-model'), systemText);
    add('Participacion del enfrentamiento', label('match-format'), template?.code === 'tag_duel'
      ? 'Dos decks por lado comparten el resultado de Tag Duel. No se registran como dos victorias individuales.'
      : value('match-format') === '3v3' ? 'Tres decks por lado. En los modelos por equipos se guarda una alineacion fija y tres resultados individuales; el resultado conjunto decide el ganador.'
      : value('match-format') === '2v2' ? 'Dos decks por lado. El modelo y la regla elegidos determinan como se registra el resultado conjunto.'
      : 'Un deck se enfrenta a otro. El resultado corresponde a esos dos participantes.');
    add('Formato o division principal', label('division'), value('division').startsWith('format:')
      ? 'Selecciona la familia de decks del evento. No permite mezclar decks de formatos incompatibles.'
      : value('division') ? 'Es la division de referencia. El modo de participantes indica si se limita la seleccion a esta division.'
      : 'No se ha elegido una division. Revisa el formato base y el modo de participantes antes de inscribir decks.');
    const linked = form.querySelector('[data-gestion-league-divisions-select]');
    if (type === 'league' && linked) add('Divisiones de la liga', Array.from(linked.selectedOptions).map(o=>o.textContent.trim()).join(', ') || 'Sin divisiones seleccionadas', 'Los ascensos y descensos solo tienen sentido entre divisiones de esta misma liga. Una liga de una division no tiene movimientos.');
    add('Regla de avance', label('advancement-rule'), explanations.advancement[value('advancement-rule')] || 'Debes definir el criterio de clasificacion.');
    add('Politica de empate', label('draw-policy'), explanations.draw[value('draw-policy')] || 'Revisa como se resuelve un resultado igualado.');
    const best = Number(value('best-of'));
    add('Formato de partida', best > 0 ? `Mejor de ${best} (BO${best})` : 'Sin definir', best === 1
      ? 'Se disputa un solo juego por enfrentamiento. La regla de resultado seleccionada determina como se registra.'
      : `BO${best} indica el formato de cada enfrentamiento; no es el numero de rondas del evento. La regla de resultado elegida determina las condiciones de cierre y desempate.`);
    const rule = (data.gameRules || []).find(r=>String(r.id) === value('game-rule'));
    add('Regla del evento', label('game-rule'), rule
      ? `Esta regla evalua el resultado y sus condiciones de victoria, derrota o empate.${rule.description ? ' '+rule.description : ''} No sustituye al sistema que organiza las rondas.`
      : 'Se buscara una regla compatible al guardar. Aun no has elegido una regla concreta en este selector.');
    add('Formato base de los decks', label('ruleset'), 'Define la familia de reglas y los decks compatibles, por ejemplo TCG Avanzado o GOAT. Los participantes no pueden mezclar formatos incompatibles.');
    add('Reglamento y lista de cartas', label('ruleset-version'), value('ruleset-version')
      ? 'Es la version de reglamento o lista de cartas asociada. Debe corresponder al formato base; no cambia el sistema de rondas.'
      : 'Todavia no hay una version seleccionada. El formato base por si solo no identifica una lista concreta de cartas permitidas.');
    add('Modo de participantes', label('deck-pool'), value('deck-pool') === 'division'
      ? 'La seleccion toma los decks de la division elegida. Se mantienen las restricciones de formato y de pertenencia a una sola liga.'
      : 'Permite seleccionar decks activos sin limitarse a una division. Se mantienen las restricciones de formato y, en ligas, no se admiten decks inscritos en otra liga.');
    const season = form.querySelector('[data-event-season-name]');
    if (season) add('Temporada', season.value || 'Sin temporada', 'El evento pertenece a esta temporada. Al finalizarla se cierran los eventos asociados y se conserva su historial.');
    for (const [key,title,description] of [
      ['promotion-slots','Cupos de ascenso','Cantidad de decks que subirian a la division superior de esta liga. Debe coincidir con los descensos de esa division.'],
      ['promotion-to','Destino del ascenso','Division superior que recibira los decks que asciendan.'],
      ['relegation-slots','Cupos de descenso','Cantidad de decks que bajarian a la division inferior. Debe coincidir con los ascensos de esa division.'],
      ['relegation-to','Destino del descenso','Division inferior que recibira los decks que desciendan.']
    ]) if (field(key)?.getClientRects().length || (saved && type === 'league' && (saved.division_ids || []).length > 1)) add(title,label(key),description);
    const lock = form.querySelector('[data-gestion-competition-lock-moves]');
    if (lock?.getClientRects().length || (saved && type === 'league')) add('Movimientos entre divisiones', lock.checked ? 'Bloqueados' : 'Sin bloqueo manual', 'Este control bloquea los movimientos; no crea ascensos o descensos cuando solo hay una division.');
    const name = value('name');
    const notes = value('rules');
    if (name) add('Nombre del evento', name, 'Nombre con el que aparecera en la lista de eventos y en su ficha.');
    if (notes) add('Notas del organizador', notes, 'Estas notas documentan aclaraciones; no crean validaciones automaticas.');
    if (saved) {
      add('Inicio y cierre registrados', `${saved.starts_on || 'Inicio sin definir'} / ${saved.ends_on || 'Cierre sin definir'}`, 'Fechas guardadas del evento. La programacion de cada combate se consulta en su calendario.');
      const diagnostic = eventModelWarnings({...saved, teamConfig:saved.teamConfig});
      if (diagnostic.length) add('Requisitos pendientes', 'Configuracion con restricciones', diagnostic.join(' '));
      for (const [key,title,description] of [
        ['phase-structure','Fases registradas','Estructura declarada del evento. El sistema competitivo implementado determina las fases que se generan realmente.'],
        ['participant-source','Origen de participantes','Procedencia registrada. Cuando procede de otros eventos, las fuentes y los cupos se configuran y verifican en Fuentes y clasificados.'],
        ['event-flow','Relacion con otros eventos','Indica si es independiente o forma parte de un recorrido de clasificacion. Declararlo no crea automaticamente eventos de destino.'],
        ['ranking-impact','Impacto en la tabla','Politica guardada para la puntuacion. Las fases por etapas aplican su propio reglamento de puntos y desempates.'],
        ['deck-policy','Registro de decks','Politica registrada para los decks. Las restricciones de formato, inscripcion y alineaciones siguen siendo obligatorias.'],
        ['construction','Construccion de los decks','Modalidad declarada del evento. La plataforma organiza participantes, combates y resultados; no verifica el contenido de los decks.']
      ]) if (value(key)) add(title,label(key),description);
    }
    guide.innerHTML = cards.join('');
  }
  function eventTemplateByCode(code) {
    return (data.eventPromptTemplates || []).find((template) => String(template.code || '') === String(code || '')) || null;
  }
  function syncEventRuleSelection(force = false) {
    const select = document.querySelector('[data-gestion-competition-game-rule]');
    if (!select) return;
    const currentRule = (data.gameRules || []).find((item) => String(item.id || '') === String(select.value || ''));
    if (!force && String(select.value || '').trim() && currentRule) return;
    const template = eventTemplateByCode(adminCompetitionTemplate?.value || '');
    const code = template?.game_rule_code || '';
    if (!code) return;
    const rule = (data.gameRules || []).find((item) => String(item.code || '') === code && String(item.active ?? '1') !== '0');
    if (rule && Array.from(select.options || []).some((option) => String(option.value) === String(rule.id))) {
      select.value = String(rule.id);
    }
  }
  function enforceEventModelConstraints() {
    const type=adminCompetitionType?.value||'tournament';
    const execution=document.querySelector('[data-gestion-competition-execution-model]');
    if(execution)execution.setCustomValidity((executionByCompetitionType[type]||[]).includes(execution.value)?'':'El sistema no es compatible con este tipo. Selecciona uno compatible.');
    const advance=document.querySelector('[data-gestion-competition-advancement-rule]');
    if(advance)for(const option of advance.options)option.disabled=!['FINAL_STANDINGS','SOURCE_ADMISSION','MANUAL_ENTRIES','TOP_N','GROUP_TOP_K','WINNER_ONLY'].includes(option.value);
  }
  function syncCompetitionTypeCompatibility(resetInvalid = false) {
    const typeCode = adminCompetitionType?.value || 'tournament';
    const allowedExecutions = executionByCompetitionType[typeCode] || executionByCompetitionType.special;
    const executionSelect = document.querySelector('[data-gestion-competition-execution-model]');
    if (adminCompetitionDivision) {
      Array.from(adminCompetitionDivision.options || []).forEach((option) => {
        const ruleset = String(option.dataset.ruleset || '').toLowerCase();
        const text = String(option.textContent || '').toLowerCase();
        const isFormatOnly = String(option.value || '').startsWith('format:');
        const isGoat = ruleset === 'goat' || text.includes('goat');
        const compatible = !(typeCode === 'league' && (!option.value || isFormatOnly)) && !(typeCode === 'qualifier' && isGoat);
        option.hidden = !compatible;
        option.disabled = !compatible;
      });
      adminCompetitionDivision.required = typeCode === 'league';
      if (adminCompetitionDivision.selectedOptions[0]?.disabled) adminCompetitionDivision.value = '';
    }
    if (adminCompetitionRuleset) {
      Array.from(adminCompetitionRuleset.options || []).forEach((option) => {
        const compatible = !(typeCode === 'qualifier' && String(option.value || '').toLowerCase() === 'goat');
        option.hidden = !compatible;
        option.disabled = !compatible;
      });
      if (adminCompetitionRuleset.selectedOptions[0]?.disabled) adminCompetitionRuleset.value = 'tcg_advanced';
    }
    if (adminCompetitionTemplate) {
      Array.from(adminCompetitionTemplate.options || []).forEach((option) => {
        if (!option.value) {
          option.hidden = false;
          option.disabled = false;
          return;
        }
        const template = eventTemplateByCode(option.value);
        const templateType = option.dataset.templateType || template?.type || '';
        const compatible = !templateType || templateType === typeCode || typeCode === 'special';
        option.hidden = !compatible;
        option.disabled = !compatible;
      });
      const selectedTemplate = eventTemplateByCode(adminCompetitionTemplate.value || '');
      if (selectedTemplate && selectedTemplate.type && selectedTemplate.type !== typeCode && typeCode !== 'special') {
        adminCompetitionTemplate.value = '';
        if (resetInvalid) applyEventPromptTemplate('', false);
      }
    }
    if (executionSelect) {
      Array.from(executionSelect.options || []).forEach((option) => {
        const compatible = allowedExecutions.includes(option.value);
        option.hidden = !compatible;
        option.disabled = !compatible && !option.selected;
      });
      executionSelect.setCustomValidity(allowedExecutions.includes(executionSelect.value) ? '' : 'El sistema no es compatible con este tipo.');
    }
    enforceEventModelConstraints();
    syncEventRuleSelection(false);
  }
  function applyEventPromptTemplate(code, forceName = false) {
    document.querySelectorAll('[data-parameter]').forEach(input=>{input.value='';});
    const template = eventTemplateByCode(code);
    if (!template) {
      setValue('[data-gestion-competition-event-system]', '');
      setValue('[data-gestion-competition-event-system-preview]', 'Selecciona tipo');
      setValue('[data-gestion-competition-execution-model]', 'manual');
      setValue('[data-gestion-competition-execution-preview]', 'Manual');
      setValue('[data-gestion-competition-participation]', 'INDIVIDUAL_1V1');
      setValue('[data-gestion-competition-construction]', 'CONSTRUCTED');
      setValue('[data-gestion-competition-environment]', 'PRESENCIAL');
      setValue('[data-gestion-competition-level]', 'COMPETITIVE');
      setValue('[data-gestion-competition-event-flow]', 'STANDALONE');
      setValue('[data-gestion-competition-participant-source]', 'MANUAL');
      setValue('[data-gestion-competition-access-type]', 'OPEN');
      setValue('[data-gestion-competition-phase-structure]', 'NONE');
      setValue('[data-gestion-competition-advancement-rule]', 'MANUAL_SELECTION');
      setValue('[data-gestion-competition-draw-policy]', 'USE_TIEBREAKER');
      setValue('[data-gestion-competition-ranking-impact]', 'FULL');
    setValue('[data-gestion-competition-deck-policy]', 'OPTIONAL_REGISTRATION');
      syncEventRuleSelection(true);
      updateEventCreateGuide();
      return;
    }
    setValue('[data-gestion-competition-format]', template.format || template.system || 'editable');
    setValue('[data-gestion-competition-match-format]', template.match_format ?? '1v1');
    setValue('[data-gestion-competition-participants]', template.participants_per_side || '1');
    setValue('[data-gestion-competition-best-of]', template.best_of || '3');
    setValue('[data-gestion-competition-qualifier-duels]', template.qualifier_duels_per_deck || '3');
    setValue('[data-gestion-competition-matches-per-day]', template.qualifier_matches_per_day || '1');
    setValue('[data-competition-config-qualifier-duels]', template.qualifier_duels_per_deck || '3');
    setValue('[data-gestion-competition-schedule]', template.schedule || 'manual');
    setValue('[data-gestion-competition-ruleset]', template.ruleset || 'custom');
    setValue('[data-gestion-competition-deck-pool]', template.deck_pool || 'all_active');
    setValue('[data-gestion-competition-event-system]', template.system || '');
    setValue('[data-gestion-competition-event-system-preview]', template.system || '');
    setValue('[data-gestion-competition-execution-model]', template.execution_model || 'manual');
    setValue('[data-gestion-competition-execution-preview]', template.execution_model || 'manual');
    setValue('[data-gestion-competition-participation]', template.participation_code || 'INDIVIDUAL_1V1');
    setValue('[data-gestion-competition-construction]', template.construction_code || 'CONSTRUCTED');
    setValue('[data-gestion-competition-environment]', template.environment_code || 'PRESENCIAL');
    setValue('[data-gestion-competition-level]', template.level_code || 'COMPETITIVE');
    setValue('[data-gestion-competition-event-flow]', template.event_flow || 'STANDALONE');
    setValue('[data-gestion-competition-participant-source]', template.participant_source || 'MANUAL');
    setValue('[data-gestion-competition-access-type]', template.access_type || 'OPEN');
    setValue('[data-gestion-competition-phase-structure]', template.phase_structure || 'NONE');
    setValue('[data-gestion-competition-advancement-rule]', template.advancement_rule || 'MANUAL_SELECTION');
    setValue('[data-gestion-competition-draw-policy]', template.draw_policy || 'USE_TIEBREAKER');
    setValue('[data-gestion-competition-ranking-impact]', template.ranking_impact || 'FULL');
    setValue('[data-gestion-competition-deck-policy]', template.deck_policy || 'OPTIONAL_REGISTRATION');
    syncEventRuleSelection(true);
    const nameNode = document.querySelector('[data-gestion-competition-name]');
    if (nameNode && (forceName || !String(nameNode.value || '').trim() || nameNode.value === nameNode.dataset.templateName)) { nameNode.value = template.label || ''; nameNode.dataset.templateName = nameNode.value; }
    const notesNode = document.querySelector('[data-gestion-competition-rules]');
    if (notesNode && (!String(notesNode.value || '').trim() || notesNode.value === notesNode.dataset.templateNotes)) { notesNode.value = template.notes || ''; notesNode.dataset.templateNotes = notesNode.value; }
    if (Number(template.best_of || 3) === 1) setValue('[data-gestion-competition-game-rule]', '');
    syncParticipantsFromMatchFormat();
    applyRulesetToCompetition(false);
    syncGestionLeagueMovement();
    renderGestionLogoGallery('');
    updateCompetitionCodePreview();
    updateEventCreateGuide();
  }
  function renderEventLogoGallery(container, type, currentLogo = '') {
    if (!container) return;
    const grid = container.querySelector('[data-event-logo-gallery-grid]');
    const input = container.querySelector('[data-event-logo-gallery-input]');
    const count = container.querySelector('[data-event-logo-gallery-count]');
    if (!grid || !input) return;
    const logos = (data.eventLogoGallery || {})[type] || [];
    const selected = String(input.value || currentLogo || '');
    if (count) count.textContent = logos.length ? `${logos.length} disponibles` : 'Sin logos para este tipo';
    if (!logos.length) {
      input.value = '';
      grid.innerHTML = '<p class="note">No hay logos disponibles para este tipo.</p>';
      return;
    }
    grid.innerHTML = logos.map((logo) => {
      const url = String(logo.url || '');
      const active = selected === url;
      return `
        <button class="event-logo-choice${active ? ' active' : ''}" type="button" data-event-logo-choice="${escapeHtml(url)}" aria-pressed="${active ? 'true' : 'false'}">
          <img loading="lazy" decoding="async" src="${escapeHtml(url)}" alt="${escapeHtml(logo.label || 'Logo')}">
          <span>${escapeHtml(logo.label || 'Logo')}</span>
        </button>
      `;
    }).join('');
    if (selected && logos.some((logo) => String(logo.url || '') === selected)) {
      input.value = selected;
    } else if (!logos.some((logo) => String(logo.url || '') === input.value)) {
      input.value = '';
    }
    grid.querySelectorAll('[data-event-logo-choice]').forEach((button) => {
      button.addEventListener('click', () => {
        const value = button.dataset.eventLogoChoice || '';
        input.value = input.value === value ? '' : value;
        renderEventLogoGallery(container, type, input.value);
      });
    });
  }
  function renderGestionLogoGallery(currentLogo = '') {
    const form = document.querySelector('.event-create-form');
    if (!form) return;
    renderEventLogoGallery(form.querySelector('[data-event-logo-gallery]'), adminCompetitionType?.value || 'tournament', currentLogo);
  }
  function syncGestionLeagueMovement() {
    const box = document.querySelector('[data-gestion-league-movement]');
    if (box) {
      const form = box.closest('form');
      const type = form?.querySelector('[data-gestion-competition-type]')?.value || adminCompetitionType?.value || '';
      const divisionCount = form?.querySelector('[data-gestion-league-divisions-select]')?.selectedOptions?.length || 0;
      box.hidden = type !== 'league' || divisionCount < 2;
    }
    document.querySelectorAll('[data-gestion-league-divisions]').forEach((field) => {
      const form = field.closest('form');
      const type = form?.querySelector('[data-gestion-competition-type]')?.value || '';
      field.hidden = type !== 'league';
      const select = field.querySelector('[data-gestion-league-divisions-select]');
      if (select) select.required = type === 'league';
    });
  }
  function syncMovementTargetOptions(divisionValue, promotionSelector, relegationSelector) {
    const current = String(divisionValue || '');
    document.querySelectorAll(`${promotionSelector} option, ${relegationSelector} option`).forEach((option) => {
      option.hidden = !!current && option.value === current;
      if (option.hidden && option.selected) option.selected = false;
    });
  }
  function applyCompetitionPreset(force = false) {
    if (!adminCompetitionType) return;
    const preset = competitionTypePresets[adminCompetitionType.value] || competitionTypePresets.special;
    const fill = (selector, value) => {
      const node = document.querySelector(selector);
      if (node && (force || !String(node.value || '').trim())) node.value = value;
    };
    fill('[data-gestion-competition-format]', preset.format);
    fill('[data-gestion-competition-ruleset]', preset.ruleset);
    fill('[data-gestion-competition-deck-pool]', preset.deckPool);
    fill('[data-gestion-competition-schedule]', preset.schedule);
    fill('[data-gestion-competition-execution-model]', preset.execution);
    fill('[data-gestion-competition-execution-preview]', preset.execution);
    fill('[data-gestion-competition-participation]', preset.participation);
    fill('[data-gestion-competition-construction]', preset.construction);
    fill('[data-gestion-competition-environment]', preset.environment);
    fill('[data-gestion-competition-level]', preset.level);
    fill('[data-gestion-competition-event-flow]', preset.flow);
    fill('[data-gestion-competition-participant-source]', preset.source);
    fill('[data-gestion-competition-access-type]', preset.access);
    fill('[data-gestion-competition-phase-structure]', preset.phases);
    fill('[data-gestion-competition-advancement-rule]', preset.advancement);
    fill('[data-gestion-competition-draw-policy]', preset.drawPolicy);
    fill('[data-gestion-competition-ranking-impact]', preset.rankingImpact);
    fill('[data-gestion-competition-deck-policy]', preset.deckPolicy);
    syncEventRuleSelection(force);
    applyRulesetToCompetition(force);
    syncParticipantsFromMatchFormat();
    const conditionField = document.querySelector('[data-competition-condition-field]');
    if (conditionField) conditionField.hidden = !['league', 'tournament', 'world'].includes(adminCompetitionType.value || '');
    syncGestionLeagueMovement();
    syncCompetitionTypeCompatibility(false);
  }
  function pendingEventQualifiers() {
    return (data.competitions || []).filter((competition) =>
      competition.type === 'qualifier'
      && (
        ['pending_event', 'pendiente_evento'].includes(normalizeStatusKey(competition.status))
        || (normalizeStatusKey(competition.status) === 'pending_configuration' && Math.max(Number(competition.deck_pool_count || 0), (competition.selected_deck_ids || []).length) >= 4)
      )
      && normalize(competition.active ?? '1') !== '0'
    );
  }
  function populateConditionQualifierSelect(select, selectedValue = '') {
    if (!select) return;
    const qualifiers = pendingEventQualifiers();
    const current = selectedValue || select.value || '';
    select.innerHTML = `<option value="">Sin condicionante</option>${qualifiers.map((qualifier) => (
      `<option value="${escapeHtml(qualifier.id)}">${escapeHtml(qualifier.name || 'Clasificatorio')} - debe finalizar</option>`
    )).join('')}`;
    if (current && Array.from(select.options).some((option) => option.value === String(current))) {
      select.value = String(current);
    }
  }
  populateConditionQualifierSelect(document.querySelector('[data-competition-condition-select]'));
  const eventReadingForm = document.querySelector('.event-create-form');
  ['input', 'change'].forEach(eventName => eventReadingForm?.addEventListener(eventName, () => {
    queueMicrotask(updateEventCreateGuide);
  }));
  if (adminCompetitionType) adminCompetitionType.addEventListener('change', () => {
    applyCompetitionPreset(true);
    syncCompetitionTypeCompatibility(true);
    renderGestionLogoGallery('');
    updateEventCreateGuide();
  });
  if (adminCompetitionTemplate) adminCompetitionTemplate.addEventListener('change', () => {
    syncCompetitionTypeCompatibility(true);
    applyEventPromptTemplate(adminCompetitionTemplate.value, false);
  });
  if (adminCompetitionDivision) adminCompetitionDivision.addEventListener('change', () => {
    syncRulesetFromDivision(adminCompetitionDivision, adminCompetitionRuleset);
    syncMovementTargetOptions(adminCompetitionDivision.value, '[data-gestion-competition-promotion-to]', '[data-gestion-competition-relegation-to]');
    const form = adminCompetitionDivision.closest('form');
    const linkedSelect = form?.querySelector('[data-gestion-league-divisions-select]');
    if (linkedSelect && adminCompetitionDivision.value && !adminCompetitionDivision.value.startsWith('format:')) {
      const primaryOption = Array.from(linkedSelect.options).find((option) => option.value === adminCompetitionDivision.value);
      if (primaryOption) primaryOption.selected = true;
    }
    syncGestionLeagueMovement();
    updateCompetitionCodePreview();
  });
  document.querySelectorAll('[data-gestion-league-divisions-select]').forEach((select) => {
    select.addEventListener('change', () => {
      const form = select.closest('form');
      const primary = form?.querySelector('[data-gestion-competition-division]');
      const selectedIds = Array.from(select.selectedOptions).map((option) => option.value);
      if (primary && primary.value && !primary.value.startsWith('format:') && !selectedIds.includes(primary.value)) {
        const primaryOption = Array.from(select.options).find((option) => option.value === primary.value);
        if (primaryOption) primaryOption.selected = true;
      }
      if (primary && (!primary.value || primary.value.startsWith('format:')) && selectedIds.length) primary.value = selectedIds[0];
      syncGestionLeagueMovement();
    });
  });
  if (adminCompetitionRuleset) adminCompetitionRuleset.addEventListener('change', () => applyRulesetToCompetition(true));
  document.querySelectorAll('[data-gestion-competition-execution-model], [data-gestion-competition-participation], [data-gestion-competition-construction], [data-gestion-competition-environment], [data-gestion-competition-level], [data-gestion-competition-event-flow], [data-gestion-competition-participant-source], [data-gestion-competition-access-type], [data-gestion-competition-phase-structure], [data-gestion-competition-advancement-rule], [data-gestion-competition-draw-policy], [data-gestion-competition-ranking-impact], [data-gestion-competition-deck-policy], [data-gestion-competition-best-of], [data-gestion-competition-game-rule]').forEach((node) => {
    node.addEventListener('change', () => {
      enforceEventModelConstraints();
      if (node.matches('[data-gestion-competition-execution-model]')) syncEventRuleSelection(false);
      if(node.matches('[data-gestion-competition-execution-model]')&&adminCompetitionTemplate?.value==='pegasus_challenge'){const ko=node.value==='single_elimination';setValue('[data-gestion-competition-advancement-rule]',ko?'WINNER_ONLY':'FINAL_STANDINGS');setValue('[data-gestion-competition-phase-structure]','NONE');}
      updateEventCreateGuide();
    });
  });
  const adminCompetitionRulesetVersion = document.querySelector('[data-gestion-competition-ruleset-version]');
  if (adminCompetitionRulesetVersion) adminCompetitionRulesetVersion.addEventListener('change', () => {
    syncRulesetFromVersion(adminCompetitionRulesetVersion, adminCompetitionRuleset);
    updateCompetitionCodePreview();
  });
  document.querySelectorAll('[data-gestion-competition-match-format]').forEach((node) => {
    node.addEventListener('change', () => {
      syncParticipantsFromMatchFormat();
      updateCompetitionCodePreview();
      updateEventCreateGuide();
    });
  });
  if (competitionNameInput) competitionNameInput.addEventListener('input', updateCompetitionCodePreview);
  if (adminCompetitionSelect) {
    adminCompetitionSelect.addEventListener('change', () => {
      const competition = (data.competitions || []).find((item) => String(item.id) === adminCompetitionSelect.value);
      setValue('[data-gestion-competition-id]', competition ? competition.id : '');
      setValue('[data-gestion-competition-code]', competition ? competition.code : '');
      setValue('[data-gestion-competition-name]', competition ? competition.name : '');
      setValue('[data-gestion-competition-template]', competition ? competition.prompt_template : '');
      setValue('[data-gestion-competition-type]', competition ? competition.type : 'tournament');
      setValue('[data-gestion-competition-scope]', competition ? competition.scope : 'division');
      setValue('[data-gestion-competition-division]', competition ? (competition.division_id || (competition.event_ruleset ? `format:${competition.event_ruleset}` : '')) : '');
      document.querySelectorAll('[data-gestion-league-divisions-select]').forEach((select) => {
        const selectedIds = new Set((competition?.division_ids || []).map((id) => String(id)));
        Array.from(select.options).forEach((option) => { option.selected = selectedIds.has(option.value); });
      });
      setValue('[data-gestion-competition-format]', competition ? competition.format : '');
      setValue('[data-gestion-competition-match-format]', competition ? competition.match_format : '1v1');
      setValue('[data-gestion-competition-best-of]', competition ? competition.best_of : '5');
      setValue('[data-gestion-competition-participants]', competition ? competition.participants_per_side : '1');
      setValue('[data-gestion-competition-schedule]', competition ? competition.schedule_strategy : 'manual');
      setValue('[data-gestion-competition-ruleset]', competition ? competition.event_ruleset : 'tcg_advanced');
      setValue('[data-gestion-competition-season]', competition ? competition.season_id : data.currentSeasonId);
      document.querySelector('[data-gestion-competition-season]')?.dispatchEvent(new Event('change'));
      selectCompetitionRulesetVersion(competition ? (competition.ruleset_version || competition.banlist_version || '') : '');
      setValue('[data-gestion-competition-game-rule]', competition ? (competition.game_rule_id || '') : '');
      setValue('[data-gestion-competition-deck-pool]', competition ? competition.deck_pool : 'all_active');
      setValue('[data-gestion-competition-promotion-slots]', competition ? competition.promotion_slots : '0');
      setValue('[data-gestion-competition-relegation-slots]', competition ? competition.relegation_slots : '0');
      setValue('[data-gestion-competition-promotion-to]', competition ? competition.promotion_to_division_id : '');
      setValue('[data-gestion-competition-relegation-to]', competition ? competition.relegation_to_division_id : '');
      syncMovementTargetOptions(adminCompetitionDivision?.value || '', '[data-gestion-competition-promotion-to]', '[data-gestion-competition-relegation-to]');
      setScopedChecked(document, '[data-gestion-competition-lock-moves]', competition && (competition.lock_division_moves === 1 || competition.lock_division_moves === '1'));
      setValue('[data-gestion-competition-status]', competition ? competition.status : 'pending_configuration');
      setValue('[data-gestion-competition-start]', competition ? competition.starts_on : '');
      setValue('[data-gestion-competition-end]', competition ? competition.ends_on : '');
      setValue('[data-gestion-competition-accent]', competition ? competition.accent : '#36d8ff');
      setValue('[data-gestion-competition-rules]', competition ? competition.rules : '');
      setValue('[data-gestion-competition-event-system]', competition ? competition.event_system : '');
      setValue('[data-gestion-competition-event-system-preview]', competition ? competition.event_system : '');
      const selectedTypePreset = competitionTypePresets[competition?.type || 'tournament'] || competitionTypePresets.tournament;
      const executionModel = optionValueOrDefault('[data-gestion-competition-execution-model]', competition ? competition.execution_model : '', selectedTypePreset.execution || 'manual');
      setValue('[data-gestion-competition-execution-model]', executionModel);
      setValue('[data-gestion-competition-execution-preview]', executionModel);
      setValue('[data-gestion-competition-participation]', optionValueOrDefault('[data-gestion-competition-participation]', competition ? competition.participation : '', selectedTypePreset.participation || 'INDIVIDUAL_1V1'));
      setValue('[data-gestion-competition-construction]', optionValueOrDefault('[data-gestion-competition-construction]', competition ? competition.construction : '', selectedTypePreset.construction || 'CONSTRUCTED'));
      setValue('[data-gestion-competition-environment]', optionValueOrDefault('[data-gestion-competition-environment]', competition ? competition.environment : '', selectedTypePreset.environment || 'PRESENCIAL'));
      setValue('[data-gestion-competition-level]', optionValueOrDefault('[data-gestion-competition-level]', competition ? competition.level : '', selectedTypePreset.level || 'COMPETITIVE'));
      setValue('[data-gestion-competition-event-flow]', optionValueOrDefault('[data-gestion-competition-event-flow]', competition ? competition.event_flow : '', selectedTypePreset.flow || 'STANDALONE'));
      setValue('[data-gestion-competition-participant-source]', optionValueOrDefault('[data-gestion-competition-participant-source]', competition ? competition.participant_source : '', selectedTypePreset.source || 'MANUAL'));
      setValue('[data-gestion-competition-access-type]', optionValueOrDefault('[data-gestion-competition-access-type]', competition ? competition.access_type : '', selectedTypePreset.access || 'OPEN'));
      setValue('[data-gestion-competition-phase-structure]', optionValueOrDefault('[data-gestion-competition-phase-structure]', competition ? competition.phase_structure : '', selectedTypePreset.phases || 'NONE'));
      setValue('[data-gestion-competition-advancement-rule]', optionValueOrDefault('[data-gestion-competition-advancement-rule]', competition ? competition.advancement_rule : '', selectedTypePreset.advancement || 'MANUAL_SELECTION'));
      setValue('[data-gestion-competition-draw-policy]', optionValueOrDefault('[data-gestion-competition-draw-policy]', competition ? competition.draw_policy : '', selectedTypePreset.drawPolicy || 'USE_TIEBREAKER'));
      setValue('[data-gestion-competition-ranking-impact]', optionValueOrDefault('[data-gestion-competition-ranking-impact]', competition ? competition.ranking_impact : '', selectedTypePreset.rankingImpact || 'FULL'));
      setValue('[data-gestion-competition-deck-policy]', optionValueOrDefault('[data-gestion-competition-deck-policy]', competition ? competition.deck_policy : '', selectedTypePreset.deckPolicy || 'OPTIONAL_REGISTRATION'));
      renderGestionLogoGallery(competition ? (competition.logo_url || '') : '');
      if (adminArchiveCompetitionId) adminArchiveCompetitionId.value = competition ? competition.id : '';
      if (!competition) applyCompetitionPreset(true);
      applyRulesetToCompetition(false);
      syncParticipantsFromMatchFormat();
      syncGestionLeagueMovement();
      syncCompetitionTypeCompatibility(true);
      updateCompetitionCodePreview();
      updateEventCreateGuide();
    });
  }
  syncGestionLeagueMovement();
  syncCompetitionTypeCompatibility(true);
  renderGestionLogoGallery('');
  updateEventCreateGuide();
  document.querySelectorAll('[data-event-logo-upload]').forEach((input) => {
    input.addEventListener('change', () => {
      if (!input.files || !input.files.length) return;
      const form = input.closest('form');
      const galleryInput = form ? form.querySelector('[data-event-logo-gallery-input]') : null;
      if (galleryInput) galleryInput.value = '';
      const gallery = form ? form.querySelector('[data-event-logo-gallery]') : null;
      const typeSelect = form ? (form.querySelector('[data-gestion-competition-type]') || form.querySelector('[data-competition-config-type]')) : null;
      renderEventLogoGallery(gallery, typeSelect?.value || 'special', '');
    });
  });
  syncMovementTargetOptions(adminCompetitionDivision?.value || '', '[data-gestion-competition-promotion-to]', '[data-gestion-competition-relegation-to]');
  if (rulesetSelect) {
    rulesetSelect.addEventListener('change', () => {
      const ruleset = rulesetByCode.get(String(rulesetSelect.value || '')) || {};
      setValue('[data-ruleset-code]', ruleset.code || '');
      setValue('[data-ruleset-label]', ruleset.label || '');
      setValue('[data-ruleset-family]', ruleset.format_family || '');
      setValue('[data-ruleset-banlist-name]', ruleset.banlist_name || '');
      setValue('[data-ruleset-banlist-version]', ruleset.banlist_version || '');
      setValue('[data-ruleset-banlist-date]', ruleset.banlist_effective_on || '');
      setValue('[data-ruleset-match-format]', ruleset.match_format || '1v1');
      setValue('[data-ruleset-best-of]', ruleset.best_of || '3');
      setValue('[data-ruleset-participants]', ruleset.participants_per_side || '1');
      setValue('[data-ruleset-schedule]', ruleset.schedule_strategy || 'manual');
      setValue('[data-ruleset-deck-pool]', ruleset.deck_pool || 'all_active');
      setValue('[data-ruleset-victory-condition]', ruleset.victory_condition || '');
      setValue('[data-ruleset-defeat-condition]', ruleset.defeat_condition || '');
      setValue('[data-ruleset-draw-condition]', ruleset.draw_condition || '');
      setValue('[data-ruleset-notes]', ruleset.notes || '');
    });
  }
  const gameRuleSelect = document.querySelector('[data-game-rule-select]');
  const gameRuleArchiveId = document.querySelector('[data-game-rule-archive-id]');
  const gameRulesById = new Map((data.gameRules || []).map((rule) => [String(rule.id || ''), rule]));
  const ruleDetailScreen = document.querySelector('[data-screen="regla_ficha"]');
  const ruleConditionList = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-list]') : null;
  const ruleConditionTemplate = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-template]') : null;
  const ruleSearchInput = document.querySelector('[data-rule-search]');
  const ruleTypeFilter = document.querySelector('[data-rule-type-filter]');
  const ruleStatusFilter = document.querySelector('[data-rule-status-filter]');
  const ruleVisibleCount = document.querySelector('[data-rule-visible-count]');
  const ruleDetailForm = document.querySelector('[data-game-rule-form]');
  const ruleArchiveForm = document.querySelector('[data-game-rule-archive-form]');
  const conditionFieldNames = {
    id: 'condition_id',
    type: 'condition_type',
    label: 'condition_label',
    operator: 'condition_operator',
    target: 'condition_target',
    message: 'condition_failure_message',
    active: 'condition_active'
  };
  const ruleConditionModalNode = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-modal]') : null;
  const ruleConditionModal = ruleConditionModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(ruleConditionModalNode) : null;
  const conditionModalFields = {
    row: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-row]') : null,
    title: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-title]') : null,
    type: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-type]') : null,
    label: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-label]') : null,
    operator: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-operator]') : null,
    target: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-target]') : null,
    result: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-result]') : null,
    status: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-status]') : null,
    score: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-score]') : null,
    message: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-message]') : null,
    active: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-active]') : null,
    save: ruleConditionModalNode ? ruleConditionModalNode.querySelector('[data-condition-modal-save]') : null
  };

  function setScopedValue(root, selector, value) {
    const node = root ? root.querySelector(selector) : null;
    if (node) node.value = value == null ? '' : value;
  }

  function setScopedChecked(root, selector, checked) {
    const node = root ? root.querySelector(selector) : null;
    if (node) node.checked = Boolean(checked);
  }

  function renumberRuleConditions() {
    if (!ruleConditionList) return;
    Array.from(ruleConditionList.querySelectorAll('.rule-condition-row')).forEach((row, index) => {
      [
        ['[data-condition-id]', conditionFieldNames.id],
        ['[data-condition-type]', conditionFieldNames.type],
        ['[data-condition-label]', conditionFieldNames.label],
        ['[data-condition-operator]', conditionFieldNames.operator],
        ['[data-condition-target]', conditionFieldNames.target],
        ['[data-condition-result]', 'condition_result'],
        ['[data-condition-status]', 'condition_status'],
        ['[data-condition-score]', 'condition_score'],
        ['[data-condition-message]', conditionFieldNames.message],
        ['[data-condition-active]', conditionFieldNames.active]
      ].forEach(([selector, name]) => {
        const node = row.querySelector(selector);
        if (node) node.name = `${name}[${index}]`;
      });
    });
  }

  function conditionTypeLabel(value) {
    return {
      result_case: 'Caso D1-D5',
      victory: 'Victoria',
      defeat: 'Derrota',
      draw: 'Empate',
      validation: 'Validacion',
      score: 'Puntaje / XP',
      availability: 'Disponibilidad',
      custom: 'Custom'
    }[value] || value || 'Validacion';
  }

  function conditionOperatorLabel(value) {
    return {
      sequence_equals: 'Secuencia exacta',
      defined: 'Debe estar definido',
      winner_in: 'Ganador en lista',
      winner_equals: 'Ganador igual a',
      score_min: 'Marcador minimo',
      score_max: 'Marcador maximo',
      points_value: 'Valor de puntos',
      requires_previous: 'Requiere resultado previo',
      blocks_when: 'Bloquea cuando',
      custom: 'Custom'
    }[value] || value || 'Definido';
  }

  function conditionResultLabel(value) {
    return { A: 'Gana A', B: 'Gana B', E: 'Empate' }[value] || 'Sin ganador';
  }

  function getConditionRowData(row) {
    return {
      id: row.querySelector('[data-condition-id]')?.value || '',
      condition_type: row.querySelector('[data-condition-type]')?.value || 'validation',
      condition_label: row.querySelector('[data-condition-label]')?.value || '',
      operator: row.querySelector('[data-condition-operator]')?.value || 'sequence_equals',
      target_value: row.querySelector('[data-condition-target]')?.value || '',
      result_value: row.querySelector('[data-condition-result]')?.value || '',
      status_value: row.querySelector('[data-condition-status]')?.value || 'FINALIZADA',
      score_value: row.querySelector('[data-condition-score]')?.value || '',
      failure_message: row.querySelector('[data-condition-message]')?.value || '',
      active: row.querySelector('[data-condition-active]')?.value === '1' ? '1' : '0'
    };
  }

  function renderConditionRow(row) {
    const condition = getConditionRowData(row);
    const labelText = row.querySelector('[data-condition-label-text]');
    const typeText = row.querySelector('[data-condition-type-text]');
    const operatorText = row.querySelector('[data-condition-operator-text]');
    const targetText = row.querySelector('[data-condition-target-text]');
    const resultText = row.querySelector('[data-condition-result-text]');
    const statusText = row.querySelector('[data-condition-status-text]');
    const messageText = row.querySelector('[data-condition-message-text]');
    if (labelText) labelText.textContent = condition.condition_label || 'Sin nombre';
    if (typeText) typeText.textContent = conditionTypeLabel(condition.condition_type);
    if (operatorText) operatorText.textContent = conditionOperatorLabel(condition.operator);
    if (targetText) targetText.textContent = condition.target_value || '-';
    if (resultText) resultText.textContent = conditionResultLabel(condition.result_value);
    if (statusText) {
      statusText.textContent = condition.active === '1' ? (condition.status_value || 'FINALIZADA') : 'Inactiva';
      statusText.classList.toggle('state-cancelled', condition.active !== '1');
      statusText.classList.toggle('state-active', condition.active === '1' && condition.status_value === 'FINALIZADA');
      statusText.classList.toggle('danger', condition.active === '1' && condition.status_value === 'INVALID');
      statusText.classList.toggle('warn', condition.active === '1' && condition.status_value === 'REQUIERE D5');
    }
    if (messageText) messageText.textContent = condition.failure_message || condition.condition_label || '-';
    row.classList.toggle('is-inactive', condition.active !== '1');
    row.classList.toggle('is-invalid-condition', condition.active === '1' && condition.status_value === 'INVALID');
  }

  function setConditionRowData(row, condition = {}) {
    setScopedValue(row, '[data-condition-id]', condition.id || '');
    setScopedValue(row, '[data-condition-type]', condition.condition_type || 'validation');
    setScopedValue(row, '[data-condition-label]', condition.condition_label || '');
    setScopedValue(row, '[data-condition-operator]', condition.operator || 'sequence_equals');
    setScopedValue(row, '[data-condition-target]', condition.target_value || '');
    setScopedValue(row, '[data-condition-result]', condition.result_value || '');
    setScopedValue(row, '[data-condition-status]', condition.status_value || 'FINALIZADA');
    setScopedValue(row, '[data-condition-score]', condition.score_value || '');
    setScopedValue(row, '[data-condition-message]', condition.failure_message || '');
    setScopedValue(row, '[data-condition-active]', condition.active === undefined || condition.active === 1 || condition.active === '1' ? '1' : '0');
    renderConditionRow(row);
  }

  function addRuleCondition(condition = {}) {
    if (!ruleConditionList || !ruleConditionTemplate) return;
    const fragment = ruleConditionTemplate.content.cloneNode(true);
    const row = fragment.querySelector('.rule-condition-row');
    if (!row) return;
    row.dataset.conditionUid = `condition_${Date.now()}_${Math.random().toString(16).slice(2)}`;
    setConditionRowData(row, condition);
    const edit = row.querySelector('[data-condition-edit]');
    if (edit) edit.addEventListener('click', () => openConditionModal(row));
    const remove = row.querySelector('[data-condition-remove]');
    if (remove) {
      remove.addEventListener('click', () => {
        row.remove();
        renumberRuleConditions();
      });
    }
    ruleConditionList.appendChild(row);
    renumberRuleConditions();
    return row;
  }

  function fillConditionModal(condition = {}, row = null) {
    if (!ruleConditionModalNode) return;
    if (conditionModalFields.row) conditionModalFields.row.value = row ? (row.dataset.conditionUid || '') : '';
    if (conditionModalFields.title) conditionModalFields.title.textContent = row ? 'Editar condicion' : 'Nueva condicion';
    if (conditionModalFields.type) conditionModalFields.type.value = condition.condition_type || 'result_case';
    if (conditionModalFields.label) conditionModalFields.label.value = condition.condition_label || '';
    if (conditionModalFields.operator) conditionModalFields.operator.value = condition.operator || 'sequence_equals';
    if (conditionModalFields.target) conditionModalFields.target.value = condition.target_value || '';
    if (conditionModalFields.result) conditionModalFields.result.value = condition.result_value || '';
    if (conditionModalFields.status) conditionModalFields.status.value = condition.status_value || 'FINALIZADA';
    if (conditionModalFields.score) conditionModalFields.score.value = condition.score_value || '';
    if (conditionModalFields.message) conditionModalFields.message.value = condition.failure_message || '';
    if (conditionModalFields.active) conditionModalFields.active.checked = condition.active === undefined || condition.active === 1 || condition.active === '1';
  }

  function openConditionModal(row = null) {
    fillConditionModal(row ? getConditionRowData(row) : {}, row);
    if (ruleConditionModal) {
      ruleConditionModal.show();
    }
  }

  function saveConditionFromModal() {
    if (!ruleConditionList) return;
    const label = (conditionModalFields.label?.value || '').trim();
    if (!label) {
      alert('La condicion necesita un nombre.');
      conditionModalFields.label?.focus();
      return;
    }
    const uid = conditionModalFields.row?.value || '';
    const row = uid
      ? Array.from(ruleConditionList.querySelectorAll('.rule-condition-row')).find((item) => item.dataset.conditionUid === uid)
      : addRuleCondition();
    if (!row) return;
    setConditionRowData(row, {
      id: row.querySelector('[data-condition-id]')?.value || '',
      condition_type: conditionModalFields.type?.value || 'validation',
      condition_label: label,
      operator: conditionModalFields.operator?.value || 'sequence_equals',
      target_value: conditionModalFields.target?.value || '',
      result_value: conditionModalFields.result?.value || '',
      status_value: conditionModalFields.status?.value || 'FINALIZADA',
      score_value: conditionModalFields.score?.value || '',
      failure_message: conditionModalFields.message?.value || '',
      active: conditionModalFields.active?.checked ? '1' : '0'
    });
    renumberRuleConditions();
    if (ruleConditionModal) ruleConditionModal.hide();
  }

  function fillRuleDetail(ruleId = '') {
    if (!ruleDetailScreen) return;
    const rule = gameRulesById.get(String(ruleId || '')) || {};
    const isNew = !rule.id;
    setScopedValue(ruleDetailScreen, '[data-game-rule-id]', rule.id || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-code]', rule.code || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-name]', rule.name || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-type]', rule.rule_type || 'xp');
    setScopedValue(ruleDetailScreen, '[data-game-rule-scope]', rule.scope || 'event');
    setScopedValue(ruleDetailScreen, '[data-game-rule-priority]', rule.priority_order || '100');
    setScopedValue(ruleDetailScreen, '[data-game-rule-trigger]', rule.trigger_event || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-effect]', rule.effect || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-points]', rule.points_value || '0');
    setScopedValue(ruleDetailScreen, '[data-game-rule-notes]', rule.notes || '');
    setScopedChecked(ruleDetailScreen, '[data-game-rule-active]', isNew || rule.active === 1 || rule.active === '1');
    const archiveId = ruleDetailScreen.querySelector('[data-game-rule-archive-id]');
    if (archiveId) archiveId.value = rule.id || '';
    if (ruleArchiveForm) ruleArchiveForm.hidden = isNew;
    const heading = ruleDetailScreen.querySelector('[data-rule-detail-heading]');
    if (heading) heading.textContent = isNew ? 'Nueva regla' : `Regla: ${rule.name || rule.code}`;
    if (ruleConditionList) {
      ruleConditionList.innerHTML = '';
      const conditions = Array.isArray(rule.conditions) ? rule.conditions : [];
      if (conditions.length) {
        conditions.forEach((condition) => addRuleCondition(condition));
      }
    }
  }

  function applyRuleFilters() {
    const rows = Array.from(document.querySelectorAll('[data-rule-row]'));
    const query = (ruleSearchInput?.value || '').trim().toLowerCase();
    const type = ruleTypeFilter?.value || '';
    const status = ruleStatusFilter?.value || '';
    let visible = 0;
    rows.forEach((row) => {
      const show = (!query || (row.dataset.ruleSearch || '').includes(query))
        && (!type || row.dataset.ruleType === type)
        && (!status || row.dataset.ruleStatus === status);
      row.hidden = !show;
      if (show) visible += 1;
    });
    if (ruleVisibleCount) ruleVisibleCount.textContent = `${visible} reglas`;
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-rule-open]');
    if (!button) return;
    event.preventDefault();
    fillRuleDetail(button.dataset.ruleOpen || '');
    routeTo('regla_ficha');
  });

  const ruleConditionAdd = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-add]') : null;
  if (ruleConditionAdd) ruleConditionAdd.addEventListener('click', () => openConditionModal());
  if (conditionModalFields.save) conditionModalFields.save.addEventListener('click', saveConditionFromModal);
  [ruleSearchInput, ruleTypeFilter, ruleStatusFilter].forEach((node) => {
    if (node) node.addEventListener('input', applyRuleFilters);
    if (node) node.addEventListener('change', applyRuleFilters);
  });
  const ruleFilterClear = document.querySelector('[data-rule-filter-clear]');
  if (ruleFilterClear) {
    ruleFilterClear.addEventListener('click', () => {
      if (ruleSearchInput) ruleSearchInput.value = '';
      if (ruleTypeFilter) ruleTypeFilter.value = '';
      if (ruleStatusFilter) ruleStatusFilter.value = '';
      applyRuleFilters();
    });
  }
  if (ruleDetailForm) {
    ruleDetailForm.addEventListener('submit', (event) => {
      const labels = Array.from(ruleDetailForm.querySelectorAll('[data-condition-label]')).map((input) => input.value.trim()).filter(Boolean);
      if (!labels.length) {
        event.preventDefault();
        alert('Agrega al menos una condicion para guardar la regla.');
      }
      renumberRuleConditions();
    });
  }
  applyRuleFilters();
  if (gameRuleSelect) {
    gameRuleSelect.addEventListener('change', () => {
      const rule = gameRulesById.get(String(gameRuleSelect.value || '')) || {};
      setValue('[data-game-rule-id]', rule.id || '');
      setValue('[data-game-rule-code]', rule.code || '');
      setValue('[data-game-rule-name]', rule.name || '');
      setValue('[data-game-rule-type]', rule.rule_type || 'xp');
      setValue('[data-game-rule-scope]', rule.scope || 'event');
      setValue('[data-game-rule-priority]', rule.priority_order || '100');
      setValue('[data-game-rule-trigger]', rule.trigger_event || '');
      setValue('[data-game-rule-effect]', rule.effect || '');
      setValue('[data-game-rule-points]', rule.points_value || '0');
      setValue('[data-game-rule-notes]', rule.notes || '');
      const active = document.querySelector('[data-game-rule-active]');
      if (active) active.checked = !rule.id || rule.active === 1 || rule.active === '1';
      if (gameRuleArchiveId) gameRuleArchiveId.value = rule.id || '';
    });
  }
  applyRulesetToCompetition(false);
  selectCompetitionRulesetVersion('');
  syncParticipantsFromMatchFormat();
  updateCompetitionCodePreview();

  const eventFilterState = { query: '', type: '', ruleset: '', status: '', division: '', season: new URLSearchParams(location.search).get('season_context') || '' };
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
  function eventTypeLabel(type) {
    return {
      qualifier: 'Clasificatorio',
      tournament: 'Torneo',
      league: 'Liga',
      cup: 'Copa',
      world: 'Mundial',
      final: 'Final',
      friendly: 'Amistoso',
      special: 'Evento especial'
    }[type] || 'Evento';
  }
  function rulesetLabel(ruleset) {
    return {
      tcg_advanced: 'TCG Avanzado',
      ocg: 'OCG',
      goat: 'Goat',
      edison: 'Edison',
      speed_duel: 'Speed Duel',
      genesis: 'Genesis',
      custom: 'Personalizado'
    }[ruleset] || ruleset || 'Personalizado';
  }
  function poolLabel(pool) {
    return {
      all_active: 'Todos los decks activos',
      division: 'Formato asociado',
      qualified: 'Clasificados por evento previo',
      top_cut: 'Top cut por ranking',
      manual: 'Seleccion manual'
    }[pool] || pool || 'Manual';
  }
  function setEventDetailText(selector, value) {
    const node = eventDetail ? eventDetail.querySelector(selector) : null;
    if (node) node.textContent = value == null || value === '' ? '-' : String(value);
  }
  function schedulePatternLabel(pattern) {
    return {
      consecutive: 'Dias seguidos',
      every_other_day: '1 dia de por medio',
      every_2_days: '2 dias de por medio',
      every_3_days: '3 dias de por medio',
      weekend: 'Fin de semana'
    }[pattern] || pattern || 'Manual';
  }
  function gameRuleLabel(ruleId) {
    const rule = (data.gameRules || []).find((item) => String(item.id || '') === String(ruleId || ''));
    return rule ? (rule.name || rule.code || 'Regla') : 'Sin regla especial';
  }
  function gameRuleForId(ruleId) {
    return (data.gameRules || []).find((item) => String(item.id || '') === String(ruleId || '')) || null;
  }
  function currentCompetitionConfig() {
    return (data.competitions || []).find((item) => String(item.id || '') === String(activeCompetitionConfigId || '')) || null;
  }
  function activeCompetitionRule(competition) {
    const selectedRuleId = competitionConfigModal?.querySelector('[data-competition-config-rule]')?.value || competition?.game_rule_id || '';
    return gameRuleForId(selectedRuleId);
  }
  function ruleConditionLabel(condition) {
    const label = condition.condition_label || condition.label || '';
    const type = condition.condition_type || '';
    const operator = condition.operator || '';
    const target = condition.target_value || condition.result_value || condition.status_value || condition.score_value || '';
    return label || [type, operator, target].filter(Boolean).join(' ') || 'Condicion';
  }
  function renderCompetitionRuleSummary(competition) {
    const rule = activeCompetitionRule(competition);
    const card = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-rule-card]') : null;
    const nameNode = card ? card.querySelector('[data-competition-rule-name]') : null;
    const codeNode = card ? card.querySelector('[data-competition-rule-code]') : null;
    const effectNode = card ? card.querySelector('[data-competition-rule-effect]') : null;
    if (nameNode) nameNode.textContent = rule ? (rule.name || rule.code || 'Regla') : 'Regla compatible por defecto';
    if (codeNode) {
      codeNode.textContent = rule
        ? `${rule.code || 'SIN_CODIGO'} | ${rule.rule_type || 'general'} | ${rule.scope || 'general'}`
        : 'Sin regla especial seleccionada en el evento.';
    }
    if (effectNode) {
      effectNode.textContent = rule
        ? (rule.effect || rule.notes || 'Esta regla se aplicara al validar y guardar los resultados de los duelos.')
        : 'El sistema usara la validacion compatible segun el tipo de evento y el reglamento elegido.';
    }
    const title = eventRuleModalNode ? eventRuleModalNode.querySelector('[data-event-rule-modal-title]') : null;
    const meta = eventRuleModalNode ? eventRuleModalNode.querySelector('[data-event-rule-modal-meta]') : null;
    const body = eventRuleModalNode ? eventRuleModalNode.querySelector('[data-event-rule-modal-body]') : null;
    if (title) title.textContent = rule ? (rule.name || rule.code || 'Regla del evento') : 'Regla compatible por defecto';
    if (meta) {
      meta.textContent = rule
        ? `${eventTypeLabel(competition?.type)} | ${competition?.code || 'Evento'} | ${rule.code || 'SIN_CODIGO'}`
        : `${eventTypeLabel(competition?.type)} | ${competition?.code || 'Evento'} | Sin regla especial`;
    }
    if (!body) return;
    const policyText = (data.executionCapabilities || {})[competition?.execution_model]?.policy ? workflowPolicyDescription(competition.execution_model, competition.workflow || null) : '';
    const policyHtml = policyText ? `<article class="event-rule-effect"><h3>Organizacion del evento</h3><p>${escapeHtml(policyText)}</p></article>` : '';
    if (!rule) {
      body.innerHTML = '<p class="note">Este evento no tiene una regla custom asignada. Se aplicara la validacion compatible por defecto del tipo de evento.</p>';
      body.insertAdjacentHTML('afterbegin', policyHtml);
      return;
    }
    const conditions = Array.isArray(rule.conditions) ? rule.conditions.filter((condition) => String(condition.active ?? '1') !== '0') : [];
    body.innerHTML = `
      ${policyHtml}
      <div class="event-rule-detail-head">
        <article><span>Codigo</span><b>${escapeHtml(rule.code || '-')}</b></article>
        <article><span>Tipo</span><b>${escapeHtml(rule.rule_type || 'general')}</b></article>
        <article><span>Alcance</span><b>${escapeHtml(rule.scope || 'general')}</b></article>
        <article><span>Condiciones</span><b>${conditions.length}</b></article>
      </div>
      <article class="event-rule-effect">
        <span>Efecto en los duelos</span>
        <p>${escapeHtml(rule.effect || rule.notes || 'Sin descripcion registrada.')}</p>
      </article>
      <div class="event-rule-condition-list">
        ${conditions.length ? conditions.map((condition, index) => `
          <article>
            <b>${index + 1}</b>
            <span>${escapeHtml(ruleConditionLabel(condition))}</span>
            <small>${escapeHtml(condition.failure_message || condition.description || 'Validacion activa')}</small>
          </article>
        `).join('') : '<p class="note">Esta regla no tiene condiciones activas registradas.</p>'}
      </div>
    `;
  }
  function normalizeCompetitionStatus(status) {
    const normalized = normalizeStatusKey(status);
    if (['draft', ''].includes(normalized)) return 'pending_configuration';
    if (['cancelled', 'canceled', 'anulado', 'anulada'].includes(normalized)) return 'cancelled';
    if (['finalizada', 'finalizado', 'finalized'].includes(normalized)) return 'completed';
    if (['pendiente_evento'].includes(normalized)) return 'pending_event';
    if (['pendiente_configuracion'].includes(normalized)) return 'pending_configuration';
    return String(status || 'pending_configuration');
  }
  function competitionStatusLabel(status) {
    return {
      pending_configuration: 'Pendiente configuracion',
      pending_event: 'Programado',
      active: 'Activo',
      completed: 'Finalizado',
      cancelled: 'Anulado'
    }[normalizeCompetitionStatus(status)] || status || 'Pendiente configuracion';
  }
  function competitionIsCancelled(status) {
    return normalizeCompetitionStatus(status) === 'cancelled';
  }
  function competitionIsTerminal(status) {
    const normalized = normalizeCompetitionStatus(status);
    return ['completed', 'cancelled'].includes(normalized);
  }

  const competitionConfigModal = document.querySelector('[data-competition-config-modal]');
  const eventLogoModalFields = competitionConfigModal?.querySelector('[data-event-logo-modal-fields]');
  const eventLogoInput = competitionConfigModal?.querySelector('[data-competition-config-logo-input]');
  const eventRemoveLogo = competitionConfigModal?.querySelector('[data-competition-config-remove-logo]');
  const eventLogoGallery = competitionConfigModal?.querySelector('[data-event-logo-gallery]');
  [eventLogoInput?.closest('label'), eventRemoveLogo?.closest('label'), eventLogoGallery].filter(Boolean).forEach((node) => eventLogoModalFields?.appendChild(node));
  const eventLogoModalNode = document.getElementById('eventLogoModal');
  const eventLogoSave = eventLogoModalNode?.querySelector('[data-event-logo-save]');
  const eventLogoMessage = eventLogoModalNode?.querySelector('[data-event-logo-message]');
  let eventLogoSession = null, eventLogoSaving = false, eventLogoRequest = 0;
  function eventLogoUrl(id) {
    const url = new URL('./', location.href);
    url.searchParams.set('api', 'event_logo'); url.searchParams.set('event_id', id);
    return url;
  }
  function resetEventLogoSelection(competition) {
    if (!competition) return;
    if (eventLogoInput) eventLogoInput.value = '';
    if (eventRemoveLogo) eventRemoveLogo.checked = false;
    const galleryInput = eventLogoGallery?.querySelector('[data-event-logo-gallery-input]');
    if (galleryInput) galleryInput.value = '';
    renderEventLogoGallery(eventLogoGallery, competition.type || 'special', competition.logo_url || '');
    const image = competitionConfigModal?.querySelector('[data-competition-config-logo]');
    const wrap = competitionConfigModal?.querySelector('[data-competition-config-logo-wrap]');
    if (image) image.src = competition.logo_url || '';
    if (wrap) wrap.hidden = !competition.logo_url;
  }
  eventLogoModalNode?.addEventListener('show.bs.modal', async () => {
    const competition = currentCompetitionConfig(), request = ++eventLogoRequest;
    eventLogoSession = null; eventLogoSave.disabled = true;
    eventLogoMessage.textContent = 'Consultando logo guardado…';
    resetEventLogoSelection(competition);
    if (!competition) return;
    try {
      const response = await fetch(eventLogoUrl(competition.id), {cache: 'no-store'}), result = await response.json();
      if (request !== eventLogoRequest) return;
      if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo consultar el logo.');
      competition.logo_url = result.logo_url;
      eventLogoSession = {...result, eventId: competition.id};
      eventLogoSave.disabled = false; eventLogoMessage.textContent = '';
    } catch (error) { if (request === eventLogoRequest) eventLogoMessage.textContent = error.message; }
  });
  eventLogoModalNode?.addEventListener('hide.bs.modal', event => { if (eventLogoSaving) event.preventDefault(); });
  eventLogoModalNode?.addEventListener('hidden.bs.modal', () => {
    ++eventLogoRequest; eventLogoSession = null;
    resetEventLogoSelection(currentCompetitionConfig());
  });
  eventLogoGallery?.addEventListener('click', event => {
    if (!event.target.closest('[data-event-logo-choice]')) return;
    if (eventLogoInput) eventLogoInput.value = '';
    if (eventRemoveLogo) eventRemoveLogo.checked = false;
  });
  eventLogoSave?.addEventListener('click', async () => {
    if (!eventLogoSession || eventLogoSaving) return;
    const competition = currentCompetitionConfig();
    if (String(competition?.id) !== String(eventLogoSession.eventId)) return;
    eventLogoSaving = true; eventLogoSave.disabled = true; eventLogoMessage.textContent = 'Guardando logo…';
    try {
      const body = new FormData(); body.set('token', eventLogoSession.token); body.set('version', eventLogoSession.version);
      body.set('competition_gallery_logo', eventLogoGallery?.querySelector('[data-event-logo-gallery-input]')?.value || '');
      if (eventRemoveLogo?.checked) body.set('competition_remove_logo', '1');
      if (eventLogoInput?.files?.[0]) body.set('competition_logo', eventLogoInput.files[0]);
      const response = await fetch(eventLogoUrl(competition.id), {method:'POST', body, cache:'no-store'}), result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo guardar el logo.');
      competition.logo_url = result.logo_url;
      resetEventLogoSelection(competition);
      eventLogoSaving = false;
      bootstrap.Modal.getInstance(eventLogoModalNode)?.hide();
    } catch (error) { eventLogoMessage.textContent = error.message; }
    finally { eventLogoSaving = false; eventLogoSave.disabled = !eventLogoSession; }
  });
  const eventProgrammingPanel = competitionConfigModal?.querySelector('[data-event-programming-panel]');
  const eventProgrammingBody = competitionConfigModal?.querySelector('[data-event-programming-modal-body]');
  if (eventProgrammingPanel && eventProgrammingBody) eventProgrammingBody.appendChild(eventProgrammingPanel);
  const eventDataActions = competitionConfigModal?.querySelector('.event-data-actions');
  [competitionConfigModal?.querySelector('[data-open-event-ranking-modal]'), competitionConfigModal?.querySelector('[data-open-event-schedule-modal]')]
    .filter(Boolean).forEach((button) => eventDataActions?.appendChild(button));
  const eventRankingModalNode = document.querySelector('[data-event-ranking-modal]');
  const eventRankingModal = eventRankingModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventRankingModalNode) : null;
  const eventRuleModalNode = document.querySelector('[data-event-rule-modal]');
  const eventRuleModal = eventRuleModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventRuleModalNode) : null;
  const eventScheduleModalNode = document.querySelector('[data-event-schedule-modal]');
  const eventScheduleModal = eventScheduleModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventScheduleModalNode) : null;
  const eventProgrammingModalNode = document.getElementById('eventProgrammingModal');
  const eventProgrammingModal = eventProgrammingModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventProgrammingModalNode) : null;
  const confirmStartQualifierModalNode = document.querySelector('[data-confirm-start-qualifier-modal]');
  const confirmStartQualifierModal = confirmStartQualifierModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(confirmStartQualifierModalNode) : null;
  const confirmScheduleQualifierModalNode = document.querySelector('[data-confirm-schedule-qualifier-modal]');
  const confirmScheduleQualifierModal = confirmScheduleQualifierModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(confirmScheduleQualifierModalNode) : null;
  const competitionDecksModalNode = document.querySelector('[data-competition-decks-modal]');
  const competitionDecksModal = competitionDecksModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(competitionDecksModalNode) : null;
  const deleteCompetitionModalNode = document.querySelector('[data-delete-competition-modal]');
  const deleteCompetitionModal = deleteCompetitionModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(deleteCompetitionModalNode) : null;
  const qualifierLoadModalNode = document.querySelector('[data-qualifier-load-modal]');
  const qualifierLoadModal = qualifierLoadModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(qualifierLoadModalNode) : null;
  const competitionConfigCount = document.querySelector('[data-competition-config-count]');
  let competitionSelectedDeckIds = new Set();
  let participantDivisionView = "";
  let participantDivisionEvent = "";
  let activeCompetitionConfigId = '';
  let activeQualifierLoadId = '';
  function deckLabel(deck) {
    return `${deck.name || 'Deck'}${deck.team ? ` | ${deck.team}` : ''}${deck.division ? ` | ${deck.division}` : ''}`;
  }
  function selectedDeckListNode() {
    return competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-selected-decks]') : null;
  }
  function deckPickerNode() {
    return competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-picker]') : null;
  }
  function deckSearchNode() {
    return competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-search]') : null;
  }
  function competitionDeckRosterLocked() {
    if (!competitionConfigModal) return false;
    return competitionConfigModal.dataset.deckRosterLocked === '1';
  }
  function competitionCanEditRoster(competition) {
    const type = String(competition?.type || currentCompetitionType() || '');
    const status = normalizeStatusKey(competition?.status || '');
    if (['completed', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'].includes(status)) return false;
    if (['cup', 'tournament', 'final'].includes(type)) return true;
    return !(competition?.configuration_locked === 1 || competition?.configuration_locked === '1' || ['active', 'activo'].includes(status));
  }
  function syncCompetitionStatusOptions(statusSelect, currentStatus) {
    if (!statusSelect) return;
    const normalized = normalizeCompetitionStatus(currentStatus);
    Array.from(statusSelect.options).forEach((option) => {
      const value = option.value;
      const allowed = normalized === 'cancelled'
        ? ['cancelled', 'resume'].includes(value)
        : [normalized, 'cancelled'].includes(value);
      option.hidden = !allowed;
      option.disabled = !allowed;
    });
    if (normalized === 'cancelled') {
      statusSelect.value = 'cancelled';
    } else if (statusSelect.value === 'resume') {
      statusSelect.value = normalized;
    } else {
      statusSelect.value = normalized;
    }
  }
  function updateCompetitionCancelledLock(currentStatus) {
    if (!competitionConfigModal) return;
    const cancelled = competitionIsCancelled(currentStatus);
    const statusSelect = competitionConfigModal.querySelector('[data-competition-config-status]');
    const statusNote = competitionConfigModal.querySelector('[data-competition-status-note]');
    competitionConfigModal.dataset.eventCancelled = cancelled ? '1' : '0';
    if (statusNote) {
      statusNote.textContent = cancelled
        ? 'Evento anulado: no se puede editar. Selecciona Reanudar y guarda para volver a Pendiente configuracion.'
        : '';
    }
    competitionConfigModal.querySelectorAll('.modal-body input, .modal-body select, .modal-body textarea').forEach((control) => {
      if (control.type === 'hidden' || control === statusSelect) return;
      control.disabled = control.dataset.lockedAlways === '1' || cancelled || control.dataset.lockedByStart === '1';
    });
    if (statusSelect) statusSelect.disabled = false;
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="save_schedule"], [data-competition-config-submit="schedule_qualifier"], [data-competition-config-submit="schedule_cup"], [data-competition-config-submit="start"]').forEach((button) => {
      button.disabled = cancelled || button.disabled;
    });
  }
  function syncCompetitionDeckInputs() {
    if (!competitionConfigModal) return;
    competitionConfigModal.querySelectorAll('[data-competition-config-deck]').forEach((checkbox) => {
      checkbox.checked = competitionSelectedDeckIds.has(String(checkbox.value));
    });
  }
  function selectedCompetitionDivisionId() {
    const value = String(competitionConfigModal?.querySelector('[data-competition-config-division]')?.value || '');
    return value.startsWith('format:') ? '' : value;
  }
  function selectedCompetitionDeckPool() {
    return String(competitionConfigModal?.querySelector('[data-competition-config-deck-pool]')?.value || 'all_active');
  }
  function selectedCompetitionParticipantsPerSide() {
    return competitionParticipantsPerSide(competitionConfigModal?.querySelector('[data-competition-config-match]'));
  }
  function selectedCompetitionDecks() {
    return (data.allDecks || []).filter((deck) => competitionSelectedDeckIds.has(String(deck.id || '')));
  }
  function effectiveSelectedCompetitionDeckIds() {
    const ids = new Set(Array.from(competitionSelectedDeckIds).map((id) => String(id)));
    if (!competitionConfigModal) return ids;
    competitionConfigModal.querySelectorAll('[data-competition-config-deck]:checked').forEach((checkbox) => {
      ids.add(String(checkbox.value || ''));
    });
    competitionConfigModal.querySelectorAll('[data-selected-competition-deck-id]').forEach((row) => {
      ids.add(String(row.dataset.selectedCompetitionDeckId || ''));
    });
    ids.delete('');
    return ids;
  }
  function deckById(deckId) {
    return (data.allDecks || []).find((deck) => String(deck.id || '') === String(deckId || '')) || null;
  }
  function participantFormatCodes(deck) {
    return (deck?.format_codes || []).filter(code => code && code !== 'custom');
  }
  function participantFormatLabel(deck) {
    return participantFormatCodes(deck).map(code => rulesetLabel(code)).join(' / ') || 'Sin formato';
  }
  function compatibleParticipantRoster(decks) {
    const eventFormat = competitionConfigModal?.querySelector('[data-competition-config-ruleset]')?.value || '';
    let formats = eventFormat && eventFormat !== 'custom' ? [eventFormat] : null;
    for (const deck of decks) {
      if (!deck?.id || !deck.team_id) return false;
      const own = participantFormatCodes(deck);
      formats = formats === null ? own : formats.filter(code => own.includes(code));
      if (!formats.length) return false;
    }
    return true;
  }
  function deckMatchesCompetitionFormat(deck) {
    return compatibleParticipantRoster([deck]);
  }
  function showParticipantRestriction(message = '') {
    const note = competitionConfigModal?.querySelector('[data-competition-format-restriction]');
    if (!note) return;
    const eventFormat = competitionConfigModal.querySelector('[data-competition-config-ruleset]')?.value || '';
    const selected = selectedCompetitionDecks();
    const selectedFormats = selected.length ? participantFormatLabel(selected[0]) : '';
    const allowed = eventFormat && eventFormat !== 'custom' ? rulesetLabel(eventFormat) : selectedFormats;
    note.textContent = message || `No se pueden mezclar decks de distintos formatos.${allowed ? ' Formato permitido: ' + allowed + '.' : ' El primer deck define el formato de los participantes.'} Solo se muestran decks compatibles y disponibles.`;
    if (!message && currentCompetitionConfig()?.type === 'league') note.textContent += ' Un deck no puede estar en dos ligas de la misma temporada; si puede participar en copas y otros eventos.';
    note.classList.toggle('text-warning', Boolean(message));
  }
  function participantOtherLeague(deck) {
    const competition = currentCompetitionConfig();
    if (!competition || competition.type !== 'league' || !deck) return null;
    return (data.leagueDeckAssignments || []).find(row => String(row.deck_id) === String(deck.id) && String(row.season_id) === String(competition.season_id) && String(row.competition_id) !== String(competition.id)) || null;
  }
  function canAddParticipant(deck) {
    return participantIsQualified(deck) && !participantOtherLeague(deck) && compatibleParticipantRoster([...selectedCompetitionDecks(), deck]);
  }
  function participantIsQualified(deck) {
    const competition = currentCompetitionConfig();
    if (!competition || (!competition.admission?.config && competition.participant_source !== 'PREVIOUS_EVENT' && !['top_cut','playoffs'].includes(competition.execution_model))) return true;
    return !!competition.admission?.ok && (competition.admission.selection?.ids || []).some(id => String(id) === String(deck?.id));
  }
  function participantAddRestriction(deck) {
    if (!participantIsQualified(deck)) return 'Este deck no esta clasificado por las fuentes del evento. Configura Fuentes y clasificados en la ficha.';
    const other = participantOtherLeague(deck);
    return other ? `No se puede agregar: este deck pertenece a ${other.competition_name}. Un deck solo puede estar en una liga por temporada; puede participar en otros eventos.` : 'No se puede agregar este deck: no se permite mezclar formatos.';
  }
  function leagueMovementDivisions(competition = currentCompetitionConfig()) {
    if (!competition || competition.type !== 'league') return [];
    return Object.values(data.divisions || {}).filter((division) => String(division.league_competition_id || '') === String(competition.id)).sort((a,b) => Number(a.tier_order || 0)-Number(b.tier_order || 0) || Number(a.id)-Number(b.id));
  }
  function renderLeagueMovementSheet(competition) {
    const modal = document.querySelector('[data-league-movement-modal]');
    if (!modal) return;
    const divisions = leagueMovementDivisions(competition);
    modal.querySelector('[data-league-movement-summary]').textContent = competition.name || '';
    const rows = modal.querySelector('[data-league-movement-rows]');
    if (divisions.length < 2) { rows.innerHTML = ''; return; }
    const fields = divisions.map(division => ['promotion','relegation'].map(kind => `<input type="hidden" name="division_movements[${escapeHtml(division.id)}][${kind}_slots]" value="${Number(division[kind+'_slots'] || 0)}"><input type="hidden" name="division_movements[${escapeHtml(division.id)}][${kind}_to_division_id]" value="${escapeHtml(division[kind+'_to_division_id'] || '')}">`).join('')).join('');
    rows.innerHTML = fields + divisions.slice(0,-1).map((upper,index) => {
      const lower=divisions[index+1];
      const down=Number(upper.relegation_slots || 0),up=Number(lower.promotion_slots || 0);
      const balanced=down===up;
      const max=Math.min((upper.decks || []).length,(lower.decks || []).length);
      return `<section class="league-movement-division"><h3>${escapeHtml(upper.name)} &harr; ${escapeHtml(lower.name)}</h3><label>Decks que bajan y suben (misma cantidad)<input class="form-control" type="number" min="0" max="${max}" step="1" required value="${balanced ? down : ''}" data-division-exchange="${index}" aria-label="Cupos de intercambio entre ${escapeHtml(upper.name)} y ${escapeHtml(lower.name)}"></label><p class="note mt-2" data-exchange-description="${index}"></p>${balanced ? '' : '<p class="text-warning">La configuracion anterior tenia cantidades distintas. Indica una cantidad igual para ambos movimientos.</p>'}</section>`;
    }).join('');
    const setField=(id,key,value) => { const input=rows.querySelector(`[name="division_movements[${id}][${key}]"]`); if(input) input.value=String(value); };
    // End divisions have no destination beyond their boundary.
    setField(divisions[0].id,'promotion_slots',0);setField(divisions[0].id,'promotion_to_division_id','');
    setField(divisions.at(-1).id,'relegation_slots',0);setField(divisions.at(-1).id,'relegation_to_division_id','');
    rows.querySelectorAll('[data-division-exchange]').forEach(input => {
      const sync=() => {
        const index=Number(input.dataset.divisionExchange),upper=divisions[index],lower=divisions[index+1];
        const value=Number(input.value);
        rows.querySelector(`[data-exchange-description="${index}"]`).textContent=input.value==='' ? 'Define cuantos decks intercambian estas divisiones.' : `Bajan ${value} de ${upper.name} y suben ${value} de ${lower.name}.`;
        if(input.value==='' || !input.validity.valid) return;
        setField(upper.id,'relegation_slots',value);setField(upper.id,'relegation_to_division_id',value ? lower.id : '');
        setField(lower.id,'promotion_slots',value);setField(lower.id,'promotion_to_division_id',value ? upper.id : '');
      };
      input.addEventListener('input',sync);sync();
    });
  }

  function useManualFormatSelection() {
    const pool = competitionConfigModal?.querySelector('[data-competition-config-deck-pool]');
    if (pool) pool.value = 'all_active';
    syncCompetitionDeckPoolNote();
  }
  function refreshFormatParticipants() {
    if (selectedCompetitionDeckPool() === 'division' && !competitionDeckRosterLocked()) {
      const candidates = (data.allDecks || []).filter((deck) => deckMatchesCompetitionFormat(deck) && !participantOtherLeague(deck));
      if (!compatibleParticipantRoster(candidates)) {
        useManualFormatSelection();
        showParticipantRestriction('Elige un formato especifico antes de incluir todos sus decks. No se permite mezclar formatos.');
        return;
      }
      competitionSelectedDeckIds = new Set(candidates.map((deck) => String(deck.id)));
    }
    syncCompetitionDeckInputs();
    syncCompetitionDeckPoolNote();
    renderCompetitionSelectedDecks();
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
  }
  document.querySelector('[data-league-movement-open]')?.addEventListener('click', () => {
    if (leagueMovementDivisions().length < 2) return;
    bootstrap.Modal.getOrCreateInstance(document.querySelector('[data-league-movement-modal]')).show();
  });
  function currentCompetitionType() {
    return competitionConfigModal ? String(competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '') : '';
  }
  function eligibleTeamIdsForCompetition(divisionId, participantsPerSide) {
    if (participantsPerSide <= 1) return null;
    const counts = {};
    (data.allDecks || []).forEach((deck) => {
      if (!deckMatchesCompetitionFormat(deck, divisionId)) return;
      const teamId = String(deck.team_id || '');
      if (!teamId) return;
      counts[teamId] = (counts[teamId] || 0) + 1;
    });
    return new Set(Object.keys(counts).filter((teamId) => counts[teamId] >= participantsPerSide));
  }
  function renderCompetitionDeckPicker() {
    const picker = deckPickerNode();
    if (!picker) return;
    showParticipantRestriction();
    const locked = competitionDeckRosterLocked();
    const search = deckSearchNode();
    const addButton = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-add]') : null;
    if (search) search.disabled = locked;
    if (addButton) addButton.disabled = locked;
    if (locked) {
      picker.disabled = true;
      picker.innerHTML = '<option value="">Roster cerrado por evento iniciado</option>';
      return;
    }
    picker.disabled = false;
    const searchValue = (deckSearchNode()?.value || '').trim().toLowerCase();
    const divisionId = selectedCompetitionDivisionId();
    const participantsPerSide = selectedCompetitionParticipantsPerSide();
    const eligibleTeamIds = eligibleTeamIdsForCompetition(divisionId, participantsPerSide);
    const selectedDeckIds = effectiveSelectedCompetitionDeckIds();
    const availableDecks = (data.allDecks || []).filter((deck) => {
      const id = String(deck.id || '');
      if (!id || !deck.team_id || selectedDeckIds.has(id) || !canAddParticipant(deck)) return false;
      return !searchValue || `${deckLabel(deck)} ${participantFormatLabel(deck)}`.toLowerCase().includes(searchValue);
    });
    const allowed = deck => canAddParticipant(deck) && (!eligibleTeamIds || eligibleTeamIds.has(String(deck.team_id || '')));
    const groups = new Map();
    availableDecks.forEach(deck => {
      const label = participantFormatLabel(deck);
      if (!groups.has(label)) groups.set(label, []);
      groups.get(label).push(deck);
    });
    const hasAvailable = availableDecks.some(allowed);
    picker.innerHTML = (hasAvailable ? '' : '<option value="">No hay mas decks compatibles disponibles</option>') + [...groups].map(([label, decks]) => `<optgroup label="${escapeHtml(label)}">${decks.map(deck => `<option value="${escapeHtml(deck.id)}" ${allowed(deck) ? '' : 'disabled'}>${escapeHtml(deckLabel(deck))} - ${escapeHtml(label)}${canAddParticipant(deck) ? (allowed(deck) ? '' : ' - Equipo incompleto para este match') : ' - No permitido: formato diferente'}</option>`).join('')}</optgroup>`).join('');
    if (hasAvailable) picker.value = String(availableDecks.find(allowed).id);
    if (addButton) addButton.disabled = !hasAvailable;
    showParticipantRestriction();
  }

  function renderCompetitionSelectedDecks() {
    const list = selectedDeckListNode();
    if (!list) return;
    const locked = competitionDeckRosterLocked();
    const selectedDecks = selectedCompetitionDecks();
    const divisionId = selectedCompetitionDivisionId();
    const competition = currentCompetitionConfig();
    const isLeague = competition?.type === 'league';
    const groups = new Map();
    if (isLeague) leagueMovementDivisions(competition).forEach(division => groups.set(String(division.id), { name: division.name, decks: [] }));
    selectedDecks.forEach(deck => {
      const key = isLeague ? String(deck.division_id || 'none') : 'all';
      if (!groups.has(key)) {
        const division = Object.values(data.divisions || {}).find(division => String(division.id) === key);
        groups.set(key, { name: division?.name || (isLeague ? 'Sin division asignada' : 'Participantes'), decks: [] });
      }
      groups.get(key).decks.push(deck);
    });
    const registered = new Set(selectedDecks.map(deck => String(deck.id)));
    const viewGroups = new Map([...groups].map(([id, group]) => [id, { name: group.name, decks: [...group.decks] }]));
    const ownedIds = new Set(leagueMovementDivisions(competition).map(division => String(division.id)));
    (data.allDecks || []).forEach(deck => {
      const key = String(deck.division_id || 'none');
      if (!isLeague || !ownedIds.has(key)) return;
      const group = viewGroups.get(key);
      if (!group.decks.some(item => String(item.id) === String(deck.id))) group.decks.push(deck);
    });
    if (participantDivisionEvent !== String(competition?.id || '')) {
      participantDivisionEvent = String(competition?.id || '');
      participantDivisionView = '';
    }
    if (!viewGroups.has(participantDivisionView)) participantDivisionView = viewGroups.keys().next().value || '';
    const navigation = competitionConfigModal.querySelector('[data-participant-division-buttons]');
    if (navigation) {
      navigation.hidden = !isLeague;
      navigation.innerHTML = isLeague ? [...viewGroups].map(([id, group]) => `<button type="button" class="participant-division-button ${id === participantDivisionView ? 'is-active' : ''}" aria-pressed="${id === participantDivisionView}" data-participant-division-open="${escapeHtml(id)}"><strong>${escapeHtml(group.name)}</strong><span>${group.decks.length} decks - ${group.decks.filter(deck => registered.has(String(deck.id))).length} inscritos</span></button>`).join('') : '';
      navigation.querySelectorAll('[data-participant-division-open]').forEach(button => button.addEventListener('click', () => {
        participantDivisionView = button.dataset.participantDivisionOpen;
        renderCompetitionSelectedDecks();
        list.closest('.selected-deck-table-wrap')?.scrollTo({ top: 0 });
      }));
    }
    const modalTitle = competitionConfigModal.querySelector('[data-participant-modal-title]');
    if (modalTitle) modalTitle.textContent = isLeague ? 'Divisiones y decks' : 'Decks participantes';
    const openButton = competitionConfigModal.querySelector('[data-competition-decks-open]');
    if (openButton) openButton.innerHTML = `<i class="bi bi-collection"></i> ${isLeague ? 'Divisiones y decks' : 'Decks participantes'}`;
    const visibleGroups = isLeague ? [...viewGroups].filter(([id]) => id === participantDivisionView) : [...groups];
    const renderDeckRow = (deck, index) => {
      const enrolled = registered.has(String(deck.id));
      const allowed = canAddParticipant(deck);
      return `<tr class="${deckMatchesCompetitionFormat(deck, divisionId) ? '' : 'is-invalid'}" data-division-deck-id="${escapeHtml(deck.id)}" ${enrolled ? `data-selected-competition-deck-id="${escapeHtml(deck.id)}"` : ''}>
        <td data-label="#">${index + 1}</td>
        <td data-label="Deck"><b>${escapeHtml(deck.name || 'Deck')}</b>${isLeague ? `<small class="d-block">${enrolled ? 'Inscrito' : 'Sin inscribir'}</small>` : ''}</td>
        <td data-label="Equipo">${escapeHtml(deck.team || 'Sin equipo')}</td>
        <td data-label="Formato">${escapeHtml(participantFormatLabel(deck))}</td>
        <td data-label="Accion">${locked ? '<span class="status-chip">Roster cerrado</span>' : enrolled ? `<button type="button" class="btn btn-outline-light btn-sm" data-competition-deck-remove="${escapeHtml(deck.id)}"><i class="bi bi-x-lg"></i> Quitar</button>` : `<button type="button" class="btn btn-outline-info btn-sm" data-division-deck-enroll="${escapeHtml(deck.id)}" ${allowed ? '' : 'disabled title="No se permite mezclar formatos"'}><i class="bi bi-plus-circle"></i> Inscribir</button>`}</td>
      </tr>`;
    };
    list.innerHTML = visibleGroups.map(([, group]) => group.decks.length ? group.decks.map(renderDeckRow).join('') : '<tr><td colspan="5">Sin decks asignados a esta division.</td></tr>').join('') || '<tr><td colspan="5"><div class="empty-state">No hay decks seleccionados para este evento.</div></td></tr>';
    list.querySelectorAll('[data-division-deck-enroll]').forEach(button => button.addEventListener('click', () => {
      if (competitionDeckRosterLocked()) return;
      const id = String(button.dataset.divisionDeckEnroll);
      if (!canAddParticipant(deckById(id))) { showParticipantRestriction(participantAddRestriction(deckById(id))); return; }
      useManualFormatSelection();
      competitionSelectedDeckIds.add(id);
      syncCompetitionDeckInputs();
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
    }));
    list.querySelectorAll('[data-competition-deck-remove]').forEach((button) => {
      button.addEventListener('click', () => {
        if (competitionDeckRosterLocked()) return;
        useManualFormatSelection();
        competitionSelectedDeckIds.delete(String(button.dataset.competitionDeckRemove || ''));
        syncCompetitionDeckInputs();
        renderCompetitionSelectedDecks();
        renderCompetitionDeckPicker();
        updateCompetitionConfigCount();
      });
    });
  }
  function updateCompetitionConfigCount() {
    const checked = competitionSelectedDeckIds.size || document.querySelectorAll('[data-competition-config-deck]:checked').length;
    if (competitionConfigCount) competitionConfigCount.textContent = `${checked} seleccionados`;
  }
  function syncCompetitionDeckPoolNote() {
    if (!competitionConfigModal) return;
    const note = competitionConfigModal.querySelector('[data-competition-deck-pool-note]');
    if (!note) return;
    const deckPool = selectedCompetitionDeckPool();
    const format = competitionConfigModal.querySelector('[data-competition-config-ruleset]');
    const label = format?.selectedOptions?.[0]?.textContent?.trim() || 'seleccionado';
    const count = (data.allDecks || []).filter((deck) => deckMatchesCompetitionFormat(deck) && !participantOtherLeague(deck)).length;
    note.textContent = deckPool === 'division'
      ? `Se incluiran los ${count} decks del formato ${label}.`
      : `Selecciona participantes entre los ${count} decks del formato ${label}.`;
  }
  function currentCompetitionId() {
    return competitionConfigModal ? String(competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '') : '';
  }
  function competitionScheduledMatchCount(competitionId = currentCompetitionId()) {
    if (!competitionId) return 0;
    return allMatchList().filter((match) =>
      String(match.competition_id || '') === String(competitionId)
      && match.scheduled_on
      && !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''))
    ).length;
  }
  function competitionPersistedMatchCount(competitionId = currentCompetitionId()) {
    if (!competitionId) return 0;
    return allMatchList().filter((match) => String(match.competition_id || '') === String(competitionId)).length;
  }
  function competitionUsesWorkflow() {
    const competition = (data.competitions || []).find(c => String(c.id) === String(currentCompetitionId()));
    return !!(data.executionCapabilities || {})[competition?.execution_model]?.policy;
  }
  function expectedQualifierMatchCount(deckCount, participantsPerSide, duelsPerDeck) {
    if (competitionUsesWorkflow()) return Math.floor(deckCount / 2);
    const groups = participantsPerSide > 0 ? Math.floor(deckCount / participantsPerSide) : 0;
    if (groups < 2 || duelsPerDeck < 2 || duelsPerDeck > groups - 1) return 0;
    const roundLimit = Math.min(Math.max(2, Math.min(5, duelsPerDeck)), groups - 1);
    return Math.floor(groups / 2) * roundLimit;
  }
  function currentEligibleGroupCount(participantsPerSide = selectedCompetitionParticipantsPerSide()) {
    const divisionId = selectedCompetitionDivisionId();
    const validDecks = selectedCompetitionDecks().filter((deck) => deckMatchesCompetitionFormat(deck, divisionId));
    if (participantsPerSide <= 1) return validDecks.length;
    const selectedTeamCounts = validDecks.reduce((map, deck) => {
      const teamId = String(deck.team_id || '');
      if (!teamId) return map;
      map[teamId] = (map[teamId] || 0) + 1;
      return map;
    }, {});
    return Object.values(selectedTeamCounts).filter((count) => count >= participantsPerSide).length;
  }
  function competitionParticipantsPerSide(matchSelect) {
    const selected = matchSelect?.selectedOptions?.[0];
    const configured = Number(selected?.dataset?.participants || 0);
    if (configured > 0) return Math.min(3, Math.max(1, configured));
    return Math.min(3, Math.max(1, Number.parseInt(String(matchSelect?.value || '1v1'), 10) || 1));
  }
  function updateCompetitionDeckConstraints() {
    if (!competitionConfigModal) return true;
    const currentStatus = normalizeCompetitionStatus(competitionConfigModal.querySelector('[data-competition-config-status]')?.value || '');
    const isActiveEvent = currentStatus === 'active';
    if (competitionConfigModal.dataset.eventCancelled === '1') {
      competitionConfigModal.querySelectorAll('[data-competition-config-submit="schedule_qualifier"], [data-competition-config-submit="schedule_cup"], [data-competition-config-submit="start"]').forEach((button) => {
        button.disabled = true;
      });
      competitionConfigModal.querySelectorAll('[data-requires-active-event]').forEach((button) => {
        button.disabled = true;
      });
      return false;
    }
    const typeSelect = competitionConfigModal.querySelector('[data-competition-config-type]');
    const matchSelect = competitionConfigModal.querySelector('[data-competition-config-match]');
    const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
    const qualifierDuelsSelect = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]');
    const qualifierWrap = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels-wrap]');
    const isQualifier = (typeSelect?.value || '') === 'qualifier';
    const isCup = ['cup', 'final'].includes(typeSelect?.value || '');
    const isLeague = (typeSelect?.value || '') === 'league';
    const selectedDeckCount = competitionSelectedDeckIds.size;
    const divisionId = selectedCompetitionDivisionId();
    const selectedValidDecks = selectedCompetitionDecks().filter((deck) => deckMatchesCompetitionFormat(deck, divisionId));
    if (startInput) {
      const savedStartDate = String(startInput.dataset.savedValue || '');
      const keepsSavedPastDate = !!savedStartDate && startInput.value === savedStartDate && savedStartDate < serverToday;
      startInput.min = keepsSavedPastDate ? savedStartDate : serverToday;
      const invalidPastDate = !!startInput.value && startInput.value < serverToday && !keepsSavedPastDate;
      startInput.setCustomValidity(invalidPastDate ? 'La fecha de inicio no puede ser menor a la fecha actual.' : '');
    }
    if (matchSelect) {
      Array.from(matchSelect.options).forEach((option) => {
        const participants = Number(option.dataset.participants || Number.parseInt(option.value || '1', 10) || 1);
        const minimumDecksForOption = Math.max(4, participants * 2);
        const eligibleTeams = eligibleTeamIdsForCompetition(divisionId, participants);
        const eligibleGroupCount = participants > 1 ? (eligibleTeams ? eligibleTeams.size : 0) : selectedValidDecks.length;
        option.disabled = (isQualifier || isCup) && selectedDeckCount > 0 && (selectedValidDecks.length < minimumDecksForOption || eligibleGroupCount < 2);
      });
      if (matchSelect.selectedOptions[0]?.disabled) {
        const fallback = Array.from(matchSelect.options).find((option) => !option.disabled);
        if (fallback) matchSelect.value = fallback.value;
      }
    }
    const participantsPerSide = competitionParticipantsPerSide(matchSelect);
    const selectedTeamCounts = selectedValidDecks.reduce((map, deck) => {
      const teamId = String(deck.team_id || '');
      if (!teamId) return map;
      map[teamId] = (map[teamId] || 0) + 1;
      return map;
    }, {});
    const explicitTeamConfig=(data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))?.teamConfig;
    const groupCount = explicitTeamConfig ? explicitTeamConfig.lineups.length : participantsPerSide > 1
      ? Object.values(selectedTeamCounts).filter((count) => count >= participantsPerSide).length
      : selectedValidDecks.length;
    const maxDuels = Math.max(0, groupCount - 1);
    const minimumQualifierDecks = Math.max(4, participantsPerSide * 2);
    const selectedDuelsPerDeck = Number(qualifierDuelsSelect?.value || 0);
    const expectedMatches = expectedQualifierMatchCount(groupCount * participantsPerSide, participantsPerSide, selectedDuelsPerDeck);
    const scheduledMatches = competitionScheduledMatchCount();
    const persistedMatches = competitionPersistedMatchCount();
    const programmingButton = competitionConfigModal.querySelector('[data-event-programming-open]');
    if (programmingButton) {
      programmingButton.hidden = persistedMatches > 0;
      programmingButton.innerHTML = '<i class="bi bi-calendar2-week"></i> Programar';
      programmingButton.title = 'Define la fecha y genera los duelos del evento.';
    }
    const activeWithoutMatches = isActiveEvent && scheduledMatches === 0;
    if (qualifierDuelsSelect) {
      Array.from(qualifierDuelsSelect.options).forEach((option) => {
        const value = Number(option.value || 0);
        option.disabled = isQualifier && selectedDeckCount > 0 && (maxDuels < 2 || value > maxDuels);
      });
      if (qualifierDuelsSelect.selectedOptions[0]?.disabled) {
        const fallback = Array.from(qualifierDuelsSelect.options).filter((option) => !option.disabled).pop();
        if (fallback) qualifierDuelsSelect.value = fallback.value;
      }
    }
    const note = qualifierWrap ? qualifierWrap.querySelector('small') : null;
    if (qualifierWrap && competitionUsesWorkflow()) qualifierWrap.hidden = true;
    if (note && isQualifier) {
      note.textContent = selectedDeckCount
        ? `Decks validos: ${selectedValidDecks.length}/${selectedDeckCount}. Equipos aptos: ${groupCount}. Minimo requerido: ${minimumQualifierDecks}. Maximo posible: ${maxDuels >= 2 ? `${maxDuels} duelos por deck` : 'faltan equipos/decks para clasificar'}.`
        : 'Agrega minimo 4 decks para habilitar match y numero de duelos.';
    }
    const savedStartDate = String(startInput?.dataset?.savedValue || '');
    const scheduleDateValid = !startInput || !startInput.value || startInput.value >= serverToday || startInput.value === savedStartDate;
    const startDateReached = !startInput || !startInput.value || startInput.value <= serverToday;
    const workflowModel = (data.competitions || []).find(c => String(c.id) === String(currentCompetitionId()))?.execution_model;
    const savedTeamConfig=(data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))?.teamConfig;
    const needsTeamLineup=Number((data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))?.participants_per_side||1)>1 && ['tag_duel','team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship','all_star_event'].includes(((c)=>(data.eventPromptTemplates||[]).find(t=>t.code===c?.prompt_template)?.base_template||c?.prompt_template)((data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))));
    const hasValidWorkflowRoster = needsTeamLineup ? !!savedTeamConfig && savedTeamConfig.lineups.length>=2 && savedTeamConfig.lineups.length<=64 : !competitionUsesWorkflow() || (participantsPerSide === 1 && selectedValidDecks.length <= 64 && (workflowModel !== 'series' || selectedValidDecks.length === 2));
    const validSchedule = hasValidWorkflowRoster && scheduleDateValid && (!isQualifier || (selectedValidDecks.length >= minimumQualifierDecks && groupCount >= 2 && maxDuels >= 2 && selectedDuelsPerDeck <= maxDuels && expectedMatches > 0));
    const needsEvenRoster = ['cup', 'tournament'].includes(typeSelect?.value || '');
    const hasEvenRoster = savedTeamConfig ? savedTeamConfig.lineups.length>=2 : selectedValidDecks.length >= 4 && selectedValidDecks.length % 2 === 0;
    const hasRequiredDivision = !isLeague || !!divisionId;
    const validStart = hasValidWorkflowRoster && startDateReached && hasRequiredDivision && (!isQualifier || (selectedValidDecks.length >= minimumQualifierDecks && groupCount >= 2 && maxDuels >= 2 && selectedDuelsPerDeck <= maxDuels && expectedMatches > 0)) && (!needsEvenRoster || hasEvenRoster) && (!isQualifier || scheduledMatches >= expectedMatches);
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="save_schedule"]').forEach((button) => {
      button.hidden = persistedMatches > 0;
      button.disabled = persistedMatches > 0 || isActiveEvent || !scheduleDateValid;
      button.title = scheduleDateValid ? 'Guardar fecha, tipo de match, frecuencia y regla.' : 'Selecciona una fecha valida.';
    });
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="schedule_qualifier"]').forEach((button) => {
      button.hidden = !isQualifier || persistedMatches > 0;
      button.disabled = persistedMatches > 0 || isActiveEvent || !isQualifier || !validSchedule;
    });
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="schedule_cup"]').forEach((button) => {
      button.hidden = !isCup || persistedMatches > 0;
      button.disabled = !hasValidWorkflowRoster || !isCup || isActiveEvent || scheduledMatches > 0 || !scheduleDateValid || !hasEvenRoster || groupCount < 2;
      button.title = scheduledMatches > 0
        ? 'La copa ya fue programada y no puede volver a generar su llave.'
        : (isCup && !hasEvenRoster
          ? `La copa necesita cantidad par de decks. Ahora tiene ${selectedValidDecks.length}; quita 1 deck para programarla.`
          : '');
      button.innerHTML = scheduledMatches > 0
        ? '<i class="bi bi-lock"></i> Copa programada'
        : '<i class="bi bi-trophy"></i> Programar copa';
    });
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="start"]').forEach((button) => {
      button.disabled = isActiveEvent || !validStart;
      button.title = !startDateReached
        ? `Este evento se podra comenzar el ${startInput?.value || 'dia programado'}.`
        : (!hasRequiredDivision
          ? 'Selecciona la division asociada a esta liga.'
          : (isCup && !hasEvenRoster
          ? `No se puede comenzar con ${selectedValidDecks.length} decks. Quita 1 deck desde "Decks participantes".`
          : ''));
    });
    const competitionId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
    const competition = (data.competitions || []).find((item) => String(item.id || '') === String(competitionId || '')) || {};
    const statusAllowsRead = !!competitionId && !['cancelled', 'archived', 'anulado'].includes(currentStatus);
    const isCompletedEvent = ['completed', 'finalizado', 'finalizada'].includes(currentStatus);
    const executionModel = String(competition.execution_model || '').toLowerCase();
    const phaseStructure = String(competition.phase_structure || '').toUpperCase();
    const simpleDivisionQualifier = isQualifier
      && !competitionUsesWorkflow()
      && executionModel === 'swiss'
      && (!phaseStructure || phaseStructure === 'NONE')
      && String(competition.event_flow || '') === 'QUALIFIES_TO_EVENT'
      && String(competition.advancement_rule || '') === 'RANKING_THRESHOLD';
    const linkedDivisions = Object.values(data.divisions || {}).filter((division) => String(division.source_qualifier_id || '') === String(competitionId || ''));
    const supportsAdvancePhase = (
      !!(data.executionCapabilities || {})[executionModel]?.policy
      ||
      ['single_elimination', 'double_elimination', 'bracket', 'series'].includes(executionModel)
      || ['cup', 'final', 'world'].includes(typeSelect?.value || '')
      || ['swiss_top_cut', 'top_cut'].includes(executionModel)
      || phaseStructure.includes('TOP_CUT')
      || executionModel === 'groups_knockout'
      || phaseStructure.includes('GROUPS')
      || ((typeSelect?.value || '') === 'league' && ['season_playoffs', 'playoffs'].includes(executionModel))
    );
    const canAdvancePhase = isActiveEvent
      && !simpleDivisionQualifier
      && supportsAdvancePhase;
    const canApplyDivisions = isCompletedEvent && isQualifier && linkedDivisions.length > 0;
    const canApplyLeagueMoves = isCompletedEvent
      && (typeSelect?.value || '') === 'league'
      && Number(competition.division_count || 0) > 1
      && String(competition.lock_division_moves || '0') !== '1'
      && leagueMovementDivisions(competition).some((division) => Number(division.promotion_slots || 0) > 0 || Number(division.relegation_slots || 0) > 0);
    const teamMode = Number(competition.participants_per_side||1)>1 && ['tag_duel','team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship','all_star_event'].includes((data.eventPromptTemplates||[]).find(t=>t.code===competition.prompt_template)?.base_template||competition.prompt_template);
    competitionConfigModal.querySelectorAll('[data-open-team-lineups], [data-open-team-matches]').forEach(button => {button.hidden=!teamMode;});
    competitionConfigModal.querySelectorAll('[data-competition-decks-open]').forEach(button => {button.hidden=teamMode;});
    competitionConfigModal.querySelectorAll('[data-open-event-recovery]').forEach((button) => {
      const workflow = competition.workflow;
      button.hidden = !(workflow && !workflow.complete && workflow.round > 1 && workflow.history?.length && workflow.pending?.length && !isCompletedEvent);
      button.disabled = button.hidden;
    });
    competitionConfigModal.querySelectorAll('[data-competition-action-submit]').forEach((button) => {
      const action = button.dataset.competitionActionSubmit || '';
      const competitionType = typeSelect?.value || '';
      const visibleForType = action === 'apply_qualifier_divisions'
        ? isQualifier
        : (action === 'apply_league_movements'
          ? competitionType === 'league' && Number(competition.division_count || 0) > 1
          : (action === 'advance_competition'
            ? supportsAdvancePhase && !simpleDivisionQualifier
            : (action === 'recalculate_competition_standings' ? !['cup', 'final'].includes(competitionType) : true)));
      button.hidden = !visibleForType;
      if (!visibleForType) {
        button.disabled = true;
        return;
      }
      if (action === 'recalculate_competition_standings') {
        button.disabled = !statusAllowsRead;
      } else if (action === 'advance_competition') {
        button.disabled = !canAdvancePhase;
      } else if (action === 'apply_qualifier_divisions') {
        button.disabled = !canApplyDivisions;
      } else if (action === 'apply_league_movements') {
        button.disabled = !canApplyLeagueMoves;
      } else {
        button.disabled = !isActiveEvent;
      }
    });
    competitionConfigModal.querySelectorAll('[data-requires-active-event]:not([data-competition-action-submit])').forEach((button) => {
      button.disabled = !isActiveEvent;
    });
    competitionConfigModal.querySelectorAll('[data-open-event-schedule-modal]').forEach((button) => {
      button.disabled = persistedMatches <= 0;
      button.hidden = persistedMatches <= 0;
    });
    competitionConfigModal.querySelectorAll('[data-open-event-ranking-modal]').forEach((button) => {
      button.disabled = scheduledMatches <= 0 && persistedMatches <= 0;
    });
    return validSchedule;
  }
  function renderDivisionSchedule(matches, competition, success) {
    if(!matches.length)return '<p class="note">No hay duelos programados para este evento.</p>';
    const groups=new Map();
    matches.forEach(match=>{
      const entry=Object.entries(data.divisions||{}).find(([code,division])=>(match.division===code||(Number(match.division_id)>0&&Number(match.division_id)===Number(division.id)))&&(competition.type!=='league'||String(division.league_competition_id)===String(competition.id)));
      const key=entry?.[0]||'unassigned';const division=entry?.[1];
      if(!groups.has(key))groups.set(key,{key,division,matches:[]});groups.get(key).matches.push(match);
    });
    const divisions=[...groups.values()].sort((a,b)=>a.key.localeCompare(b.key,'es',{numeric:true}));
    return `${success}<div class="schedule-division-browser"><nav class="schedule-division-nav" aria-label="Divisiones del evento">${divisions.map((group,i)=>{
      let name=group.division?.name||(competition.type==='league'?'Sin división registrada':'Programación del evento');if(name.startsWith(competition.name))name=name.slice(competition.name.length).replace(/^[\s\u2014\u2013-]+/u,'');
      return `<button type="button" class="btn btn-outline-info" data-schedule-division="${i}" aria-pressed="${i===0?'true':'false'}" aria-controls="schedule-division-panel-${i}"><strong>${escapeHtml(name)}</strong><small>${group.matches.length} combates</small></button>`;
    }).join('')}</nav>${divisions.map((group,i)=>{
      const done=group.matches.filter(competitionMatchIsFinalized).length,name=group.division?.name||(competition.type==='league'?'Sin división registrada':competition.name);
      return `<section id="schedule-division-panel-${i}" class="schedule-division-panel" data-schedule-division-panel="${i}" ${i?'hidden':''}><header><div><h3>${escapeHtml(name)}</h3><p>${done} / ${group.matches.length} combates completados</p></div></header><div class="schedule-match-cards">${group.matches.map(match=>{
        const raw=String(match.estado||match.status||'PENDIENTE').toUpperCase(),done=competitionMatchIsFinalized(match),active=['EN CURSO','REQUIERE D5'].includes(raw);
        const state=done?'Finalizado':active?'En curso':raw==='PENDIENTE'?'Pendiente':raw;
        return `<div class="schedule-match-card" data-schedule-match-id="${Number(match.id)}"><div class="schedule-match-card-meta"><strong>${escapeHtml(match.scheduled_on||'Sin fecha')} ${escapeHtml(String(match.starts_at||'').slice(0,5))}</strong><span class="status-chip ${done?'state-completed':active?'ok':'state-pending-config'}">${escapeHtml(state)}</span></div><p class="schedule-match-round">Ronda ${escapeHtml(match.ronda||match.round_number||'-')} · Mesa ${escapeHtml(match.mesa||match.table_number||'-')}</p><h4>${escapeHtml(matchVersusLabel(match))}</h4>${match.score?`<p>Marcador: <strong>${escapeHtml(match.score)}</strong></p>`:''}<button class="btn btn-outline-info" type="button" data-schedule-open-match="${Number(match.id)}"><i class="bi bi-eye"></i> Ver ficha</button></div>`;
      }).join('')}</div></section>`;
    }).join('')}</div>`;
  }

  function competitionMatchesFor(competitionId) {
    return allMatchList().filter((match) => String(match.competition_id || '') === String(competitionId || ''));
  }
  function competitionMatchIsFinalized(match) {
    return ['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''));
  }
  function latestCompetitionPhase(matches) {
    const phaseOrder = { qualifier: 1, swiss: 2, groups: 3, league: 4, cup: 5, top_cut: 6, playoff: 7, final: 8 };
    return matches.slice().sort((left, right) =>
      (phaseOrder[String(right.phase || '').toLowerCase()] || 0) - (phaseOrder[String(left.phase || '').toLowerCase()] || 0)
      || Number(right.ronda || right.round_number || 0) - Number(left.ronda || left.round_number || 0)
    )[0]?.phase || '';
  }
  function renderCompetitionActionPreview(competition) {
    const node = document.querySelector('[data-competition-action-preview]');
    if (!node) return;
    if (!competition?.id) {
      node.innerHTML = '<strong>Lectura preventiva</strong><span>Selecciona un evento activo para ver si puede avanzar, aplicar divisiones o mover equipos.</span>';
      return;
    }
    const matches = competitionMatchesFor(competition.id);
    const pending = matches.filter((match) => !competitionMatchIsFinalized(match)).length;
    const completed = matches.length - pending;
    const phase = latestCompetitionPhase(matches) || competition.phase_structure || competition.type || 'evento';
    const divisions = Object.values(data.divisions || {}).filter((division) => String(division.source_qualifier_id || '') === String(competition.id || ''));
    const standings = Array.isArray(competition.ranking) ? competition.ranking : [];
    const lines = [];
    if (!matches.length) {
      lines.push('No hay combates programados todavia; primero genera calendario o guarda participantes.');
    } else if (pending > 0) {
      lines.push(`Faltan ${pending} de ${matches.length} combates por cerrar en ${phase}; no conviene avanzar fase todavia.`);
    } else {
      const normalizedPhase = String(phase).toLowerCase();
      const winners = matches.filter((match) => ['A', 'B'].includes(String(match.result || '').toUpperCase())).length;
      if (['cup', 'final', 'single_elimination', 'top_cut', 'playoff'].includes(normalizedPhase)) {
        const nextMatches = Math.floor(winners / 2);
        lines.push(nextMatches > 0
          ? `Fase actual cerrada: puede crear exactamente ${nextMatches} combate(s) de la siguiente llave.`
          : 'Fase actual cerrada: no hay suficientes ganadores para crear otra ronda.');
      } else if (competition.type === 'league' && ['season_playoffs', 'playoffs'].includes(String(competition.execution_model || ''))) {
        const nextMatches = Math.max(1, Math.floor(Math.min(standings.length || winners || completed, 8) / 2));
        lines.push(`Liga cerrada: puede crear hasta ${nextMatches} combate(s) de playoff segun ranking.`);
      } else if (competition.type === 'league') {
        lines.push('Liga de fase unica: al cerrar todos los combates se finaliza la tabla; no existe una fase siguiente.');
      } else {
        const nextMatches = Math.max(1, Math.min(8, Math.ceil((standings.length || winners || completed) / 4)));
        lines.push(`Fase actual cerrada: puede crear ${nextMatches} combate(s) segun modelo y ranking.`);
      }
    }
    if (['cup', 'tournament'].includes(String(competition.type || ''))) {
      const deckCount = Number(competition.deck_pool_count || (competition.selected_deck_ids || []).length || 0);
      if (deckCount > 0 && deckCount % 2 !== 0) {
        lines.push(`Roster impar: hay ${deckCount} decks. Quita 1 deck desde Decks participantes para poder programar la copa.`);
      }
    }
    if (competition.type === 'qualifier') {
      lines.push(divisions.length
        ? `Divisiones conectadas: ${divisions.length}. Aplicar divisiones movera decks segun rangos guardados.`
        : 'No hay divisiones conectadas a este clasificatorio; configura rangos antes de aplicar divisiones.');
    }
    if (competition.type === 'league' && Number(competition.division_count || 0) > 1) {
      const lock = String(competition.lock_division_moves || '0') === '1';
      const up = Number(competition.promotion_slots || 0);
      const down = Number(competition.relegation_slots || 0);
      lines.push(lock
        ? 'Ascensos/descensos bloqueados para esta liga: la accion no movera decks.'
        : 'Configura los cupos por division en Ascenso y descenso. Requiere liga finalizada y ranking calculado.');
    }
    node.innerHTML = `<strong>Lectura preventiva</strong>${lines.map((line) => `<span>${escapeHtml(line)}</span>`).join('')}`;
  }
  function renderEventMainChoices(competition) {
    const root = document.querySelector('[data-competition-config-modal]');
    if (!root) return;
    const optionLabel = (selector,value) => Array.from(document.querySelector(selector)?.options || []).find(o=>String(o.value)===String(value??''))?.textContent?.trim() || (value ? String(value) : 'Sin definir');
    const template = eventTemplateByCode(competition.prompt_template);
    const rule = (data.gameRules || []).find(r=>String(r.id)===String(competition.game_rule_id));
    const fields = {
      template: template?.label || (competition.prompt_template ? String(competition.prompt_template) : 'Personalizado / sin plantilla'),
      execution: optionLabel('[data-gestion-competition-execution-model]',competition.execution_model),
      division: optionLabel('[data-gestion-competition-division]',competition.division_id),
      divisions: (competition.division_ids || []).map(id=>optionLabel('[data-gestion-league-divisions-select]',id)).join(' / ') || 'Sin divisiones asociadas',
      match: optionLabel('[data-gestion-competition-match-format]',competition.match_format),
      'best-of': competition.best_of ? `${competition.best_of} (BO${competition.best_of})` : 'Sin definir',
      advancement: optionLabel('[data-gestion-competition-advancement-rule]',competition.advancement_rule),
      draw: optionLabel('[data-gestion-competition-draw-policy]',competition.draw_policy),
      rule: rule?.name || (competition.game_rule_id ? `Regla registrada ${competition.game_rule_id}` : 'Regla compatible por defecto'),
      parameters: (()=>{let params=competition.execution_parameters||{};if(typeof params==='string'){try{params=JSON.parse(params);}catch{return 'Parametros no legibles: requiere revision';}}const labels={swiss_rounds:'Rondas suizas',top_n:'Clasificados al corte',group_max_size:'Maximo por grupo',group_qualifiers:'Clasificados por grupo',series_matches:'Matches de la serie',series_wins:'Victorias para ganar la serie'};return Object.entries(params).map(([key,value])=>(labels[key]||key)+': '+value).join(' | ')||'Politica interna Station X 2026 v1, sin ajustes manuales';})()
    };
    for (const [key,value] of Object.entries(fields)) {
      const input=root.querySelector(`[data-event-main-choice="${key}"]`);
      if(input){input.value=value;input.title=value;}
    }
    const divisions=root.querySelector('[data-event-main-field="divisions"]');
    if(divisions)divisions.hidden=competition.type!=='league';
  }
  function fillCompetitionConfig(competitionId) {
    const competitions = data.competitions || [];
    let competition = competitions.find((item) => String(item.id || '') === String(competitionId || ''));
    if (!competitionConfigModal || !competition) return false;
    const notice = document.querySelector('[data-event-open-error]');
    if (notice) notice.hidden = true;
    activeCompetitionConfigId = String(competition.id || '');
    localStorage.setItem('dml_event_ficha_id', activeCompetitionConfigId);
    setEventFichaUrl(activeCompetitionConfigId);
    const selectedIds = new Set((competition.selected_deck_ids || []).map((id) => String(id)));
    competitionSelectedDeckIds = selectedIds;
    const title = competitionConfigModal.querySelector('[data-competition-config-title]');
    const meta = competitionConfigModal.querySelector('[data-competition-config-meta]');
    const idInput = competitionConfigModal.querySelector('[data-competition-config-id]');
    const codeInput = competitionConfigModal.querySelector('[data-competition-config-code]');
    const nameInput = competitionConfigModal.querySelector('[data-competition-config-name]');
    const typeSelect = competitionConfigModal.querySelector('[data-competition-config-type]');
    const statusSelect = competitionConfigModal.querySelector('[data-competition-config-status]');
    const divisionSelect = competitionConfigModal.querySelector('[data-competition-config-division]');
    const divisionsSelect = competitionConfigModal.querySelector('[data-competition-config-divisions]');
    const formatLabel = competitionConfigModal.querySelector('[data-competition-config-format-label]');
    const deckPoolSelect = competitionConfigModal.querySelector('[data-competition-config-deck-pool]');
    const rulesetSelect = competitionConfigModal.querySelector('[data-competition-config-ruleset]');
    const rulesetVersionSelect = competitionConfigModal.querySelector('[data-competition-config-ruleset-version]');
    const logoWrap = competitionConfigModal.querySelector('[data-competition-config-logo-wrap]');
    const logoImg = competitionConfigModal.querySelector('[data-competition-config-logo]');
    const logoInput = competitionConfigModal.querySelector('[data-competition-config-logo-input]');
    const logoGallery = competitionConfigModal.querySelector('[data-event-logo-gallery]');
    const accentInput = competitionConfigModal.querySelector('[data-competition-config-accent]');
    const rulesInput = competitionConfigModal.querySelector('[data-competition-config-rules]');
    const conditionSelect = competitionConfigModal.querySelector('[data-competition-config-condition]');
    const deleteButton = competitionConfigModal.querySelector('[data-competition-delete-open]');
    const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
    const matchSelect = competitionConfigModal.querySelector('[data-competition-config-match]');
    const patternSelect = competitionConfigModal.querySelector('[data-competition-config-pattern]');
    const matchesPerDaySelect = competitionConfigModal.querySelector('[data-competition-config-matches-per-day]');
    const ruleSelect = competitionConfigModal.querySelector('[data-competition-config-rule]');
    const qualifierDuelsSelect = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]');
    const finishButton = competitionConfigModal.querySelector('[data-competition-finalize-submit]');
    const isLocked = competition.configuration_locked === 1 || competition.configuration_locked === '1';
    const isCancelled = competitionIsCancelled(competition.status);
    competitionConfigModal.dataset.eventCancelled = isCancelled ? '1' : '0';
    const rosterLocked = !competitionCanEditRoster(competition);
    competitionConfigModal.dataset.deckRosterLocked = rosterLocked ? '1' : '0';
    const normalizedStatus = normalizeCompetitionStatus(competition.status);
    const divisionLabel = competition.division || competition.division_code || 'General';
    const startLabel = competition.starts_on || 'Sin fecha';
    const patternLabel = schedulePatternLabel(competition.schedule_pattern) || competition.schedule_pattern || 'Sin programar';
    renderCompetitionActionPreview(competition);

    renderEventMainChoices(competition);
    if (title) title.textContent = competition.name || 'Evento';
    if (meta) meta.textContent = `${eventTypeLabel(competition.type)} | ${divisionLabel} | ${rulesetLabel(competition.event_ruleset)} | ${competitionStatusLabel(normalizedStatus)}`;
    const eventExport = competitionConfigModal.querySelector('[data-event-export-pdf]');
    if (eventExport) eventExport.href = './?export=entity_report&entity_type=event&entity_id=' + encodeURIComponent(competition.id || '') + '&format=pdf';
    const shareEvent = competitionConfigModal.querySelector('[data-share-event-profile]');
    if (shareEvent) shareEvent.onclick = () => {
      const summary = (data.eventSummaries || []).find((row) => String(row.competition_id) === String(competition.id));
      shareCanvas('Perfil de evento', [competition.name || 'Evento', eventTypeLabel(competition.type), rulesetLabel(competition.event_ruleset), competitionStatusLabel(normalizedStatus), summary?.champion_name ? 'Campeon: ' + summary.champion_name : 'En progreso', startLabel], 'evento-' + normalize(competition.name || 'evento').replaceAll(' ', '-') + '.png');
    };
    const statusChip = competitionConfigModal.querySelector('[data-competition-config-status-chip]');
    if (statusChip) {
      statusChip.textContent = competitionStatusLabel(normalizedStatus);
      statusChip.className = `tag ${isCancelled ? 'state-cancelled' : ''}`.trim();
    }
    const setSummary = (selector, value) => {
      const node = competitionConfigModal.querySelector(selector);
      if (node) node.textContent = value;
    };
    setSummary('[data-competition-config-summary-type]', eventTypeLabel(competition.type));
    setSummary('[data-competition-config-summary-division]', divisionLabel);
    setSummary('[data-competition-config-summary-match]', String(competition.match_format || '1v1').toUpperCase());
    setSummary('[data-competition-config-summary-start]', startLabel);
    setSummary('[data-competition-config-summary-pattern]', patternLabel);
    setSummary('[data-competition-config-summary-decks]', `${selectedIds.size || Number(competition.deck_pool_count || 0)} decks`);
    if (idInput) idInput.value = competition.id || '';
    if (codeInput) codeInput.value = competition.code || '';
    if (nameInput) nameInput.value = competition.name || '';
    const removeLogoInput = competitionConfigModal.querySelector('[data-competition-config-remove-logo]');
    if (logoInput) logoInput.value = '';
    if (removeLogoInput) removeLogoInput.checked = false;
    if (logoImg && logoWrap) {
      const logoUrl = String(competition.logo_url || '').trim();
      logoImg.src = logoUrl || '';
      logoWrap.hidden = !logoUrl;
    }
    if (typeSelect) typeSelect.value = competition.type || 'special';
    if (formatLabel) formatLabel.value = competition.event_ruleset || 'custom';
    setValue('[data-competition-season]', competition.season_id);
    document.querySelector('[data-competition-season]')?.dispatchEvent(new Event('change'));
    renderEventLogoGallery(logoGallery, competition.type || 'special', competition.logo_url || '');
    if (statusSelect) {
      syncCompetitionStatusOptions(statusSelect, competition.status);
      statusSelect.value = normalizeCompetitionStatus(competition.status);
    }
    if (deleteButton) {
      const deleteAllowed = !['active', 'completed', 'cancelled'].includes(normalizedStatus);
      deleteButton.disabled = !deleteAllowed;
      deleteButton.dataset.competitionId = String(competition.id || '');
      deleteButton.dataset.competitionName = competition.name || 'Evento';
      deleteButton.title = deleteAllowed ? 'Eliminar evento' : 'Solo se eliminan eventos pendientes o en configuracion';
    }
    if (finishButton) {
      const competitionMatches = allMatchList().filter((match) => String(match.competition_id || '') === String(competition.id || ''));
      const pendingMatches = competitionMatches.filter((match) => !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')));
      const canFinish = normalizeCompetitionStatus(competition.status) === 'active' && competitionMatches.length > 0 && pendingMatches.length === 0;
      finishButton.hidden = !canFinish;
      finishButton.disabled = !canFinish;
      finishButton.title = canFinish ? 'Todos los duelos estan registrados. Puedes finalizar el evento.' : 'Disponible cuando todos los duelos esten finalizados.';
    }
    if (divisionSelect) {
      const isLeague = competition.type === 'league';
      divisionSelect.value = competition.division_id || '';
      divisionSelect.required = isLeague;
      divisionSelect.dataset.lockedAlways = isLeague ? '0' : '1';
      divisionSelect.dataset.lockedByStart = isLocked ? '1' : '0';
      divisionSelect.disabled = !isLeague || isLocked || isCancelled;
      divisionSelect.title = isLeague ? 'Toda liga debe estar asociada a una division.' : 'La division solo aplica a eventos de tipo liga.';
    }
    if (divisionsSelect) {
      const divisionIds = new Set((competition.division_ids || []).map((id) => String(id)));
      Array.from(divisionsSelect.options).forEach((option) => { option.selected = divisionIds.has(option.value); });
      divisionsSelect.required = competition.type === 'league';
      divisionsSelect.dataset.lockedByStart = isLocked ? '1' : '0';
      divisionsSelect.disabled = competition.type !== 'league' || isLocked || isCancelled;
    }
    if (deckPoolSelect) deckPoolSelect.value = competition.deck_pool || 'all_active';
    if (rulesetSelect) rulesetSelect.value = competition.event_ruleset || 'custom';
    if (rulesetVersionSelect) {
      rulesetVersionSelect.querySelectorAll('[data-saved-version]').forEach(option=>option.remove());
      const savedVersion=String(competition.ruleset_version || '');
      if(savedVersion && !Array.from(rulesetVersionSelect.options).some(option=>option.value===savedVersion)){
        const option=new Option(savedVersion,savedVersion);option.dataset.savedVersion='1';option.dataset.ruleset=competition.event_ruleset || '';rulesetVersionSelect.append(option);
      }
      rulesetVersionSelect.value=savedVersion;
    }
    if (accentInput) accentInput.value = competition.accent || '#36d8ff';
    if (rulesInput) rulesInput.value = competition.rules || '';
    if (conditionSelect) {
      const dependency = Array.isArray(competition.dependencies) ? competition.dependencies[0] : null;
      populateConditionQualifierSelect(conditionSelect, dependency ? String(dependency.depends_on_competition_id || '') : '');
      conditionSelect.value = dependency ? String(dependency.depends_on_competition_id || '') : '';
    }
    if (startInput) {
      startInput.value = competition.starts_on || data.serverToday || '';
      startInput.dataset.savedValue = competition.starts_on || '';
    }
    if (matchSelect) matchSelect.value = competition.match_format || '1v1';
    if (patternSelect) patternSelect.value = competition.schedule_pattern || 'consecutive';
    if (matchesPerDaySelect) matchesPerDaySelect.value = String(Math.max(1, Math.min(5, Number(competition.qualifier_matches_per_day || 1))));
    if (ruleSelect) ruleSelect.value = competition.game_rule_id || '';
    renderCompetitionRuleSummary(competition);
    if (qualifierDuelsSelect) qualifierDuelsSelect.value = String(competition.qualifier_duels_per_deck || 3);
    setScopedValue(competitionConfigModal, '[data-competition-config-promotion-slots]', competition.promotion_slots || '0');
    setScopedValue(competitionConfigModal, '[data-competition-config-relegation-slots]', competition.relegation_slots || '0');
    setScopedValue(competitionConfigModal, '[data-competition-config-promotion-to]', competition.promotion_to_division_id || '');
    setScopedValue(competitionConfigModal, '[data-competition-config-relegation-to]', competition.relegation_to_division_id || '');
    syncMovementTargetOptions(competition.division_id || '', '[data-competition-config-promotion-to]', '[data-competition-config-relegation-to]');
    setScopedChecked(competitionConfigModal, '[data-competition-config-lock-moves]', competition.lock_division_moves === 1 || competition.lock_division_moves === '1');
    if (startInput) {
      startInput.dataset.lockedByStart = isLocked ? '1' : '0';
      startInput.disabled = isLocked || isCancelled;
    }
    if (patternSelect) {
      patternSelect.dataset.lockedByStart = isLocked ? '1' : '0';
      patternSelect.disabled = isLocked || isCancelled;
    }
    if (matchesPerDaySelect) {
      matchesPerDaySelect.dataset.lockedByStart = isLocked ? '1' : '0';
      matchesPerDaySelect.disabled = isLocked || isCancelled;
    }
    renderLeagueMovementSheet(competition);
    syncCompetitionDeckInputs();
    syncCompetitionDeckPoolNote();
    renderCompetitionSelectedDecks();
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
    const rankingModalTitle = eventRankingModalNode ? eventRankingModalNode.querySelector('[data-event-ranking-modal-title]') : null;
    const rankingModalMeta = eventRankingModalNode ? eventRankingModalNode.querySelector('[data-event-ranking-modal-meta]') : null;
    if (rankingModalTitle) rankingModalTitle.textContent = competition.name || 'Tabla del evento';
    const rankingTag=eventRankingModalNode?.querySelector('[data-event-ranking-label]');if(rankingTag)rankingTag.textContent=competition.type==='league'?'Clasificación por divisiones':'Tabla general';
    const rankingOpen=competitionConfigModal.querySelector('[data-open-event-ranking-modal]');if(rankingOpen)rankingOpen.innerHTML='<i class="bi bi-table"></i> '+(competition.type==='league'?'Ver tablas por división':'Ver tabla general');
    if (rankingModalMeta) rankingModalMeta.textContent = `${eventTypeLabel(competition.type)} | ${rulesetLabel(competition.event_ruleset)} | ${competitionStatusLabel(competition.status)}`;
    const rankingNode = document.querySelector('[data-competition-config-ranking]');
    if (rankingNode) {
      const tabsNode = document.querySelector('[data-event-ranking-phase-tabs]');
      const byDivision = competition.type === 'league';
      const divisions = byDivision ? leagueMovementDivisions(competition) : [];
      const divisionKey = division => 'division:' + division.id;
      const snapshots = (data.standingSnapshots || []).filter((row) => String(row.competition_id || '') === String(competition.id || ''));
      const snapshotKeys = Array.from(new Set(snapshots.map((row) => row.snapshot_key || 'current')));
      const preferredKeys = ['current', 'phase:qualifier', 'phase:swiss', 'phase:groups', 'phase:league', 'phase:top_cut', 'phase:playoff', 'phase:cup', 'phase:final'];
      const orderedKeys = byDivision ? divisions.map(divisionKey) : [...preferredKeys.filter((key) => snapshotKeys.includes(key)), ...snapshotKeys.filter((key) => !preferredKeys.includes(key))];
      const activeKey = orderedKeys.includes(rankingNode.dataset.activeRankingKey || '') ? rankingNode.dataset.activeRankingKey : (orderedKeys[0] || 'current');
      const labelForSnapshot = (key) => { if(byDivision){let name=divisions.find(d=>divisionKey(d)===key)?.name||'Division';if(name.startsWith(competition.name))name=name.slice(competition.name.length).replace(/^[\s\u2014\u2013-]+/u,'');return name;}return key === 'current' ? 'General' : key.replace('phase:', '').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); };
      if (tabsNode) {
        tabsNode.innerHTML = orderedKeys.length ? orderedKeys.map((key) => `<button class="${key === activeKey ? 'active' : ''}" type="button" data-ranking-phase-key="${escapeHtml(key)}" aria-pressed="${key === activeKey}">${escapeHtml(labelForSnapshot(key))}</button>`).join('') : '';
        tabsNode.querySelectorAll('[data-ranking-phase-key]').forEach((button) => {
          button.addEventListener('click', () => {
            rankingNode.dataset.activeRankingKey = button.dataset.rankingPhaseKey || 'current';
            fillCompetitionConfig(competition.id);
          });
        });
      }
      const sourceKey = byDivision ? (snapshotKeys.includes('current') ? 'current' : 'phase:league') : activeKey;
      const snapshotRows = snapshots
        .filter((row) => (row.snapshot_key || 'current') === sourceKey)
        .sort((a, b) => Number(a.position || 0) - Number(b.position || 0));
      let ranking = snapshotRows.length ? snapshotRows.map((row) => {
        const deck = (data.allDecks || []).find((item) => String(item.id || '') === String(row.deck_id || '')) || {};
        const workflowRow = parseJsonValue(row.payload, {})?.row || {};
        return {
          id: Number(row.deck_id),
          division_id: Number(deck.division_id || 0),
          name: deck.name || `Deck #${row.deck_id}`,
          team: deck.team || 'Sin equipo',
          logo: deck.team_logo || deck.logo_url || deck.cover_url || '',
          pts: row.points,
          pj: row.played,
          pg: row.wins,
          pe: row.draws,
          pp: row.losses,
          dp: row.difference,
          cg: row.duels_won,
          cp: row.duels_lost,
          position: Number(row.position || 0),
          group: workflowRow.group,
          group_position: workflowRow.group_position,
          buchholz: workflowRow.buchholz
        };
      }) : ((byDivision || activeKey === 'current') && Array.isArray(competition.ranking) ? competition.ranking : []);
      const activeDivision = byDivision ? divisions.find(d=>divisionKey(d)===activeKey) : null;
      if(byDivision)ranking=ranking.filter(row=>{const deck=(data.allDecks||[]).find(d=>String(d.id)===String(row.id||row.deck_id||''));return activeDivision&&String(row.division_id||deck?.division_id||'')===String(activeDivision.id);}).map((row,index)=>({...row,position:index+1}));
      rankingNode.innerHTML = ranking.length ? ranking.map((row, index) => {
        const teamName = row.team || 'Sin equipo';
        const deckName = row.name || 'Deck';
        const logoUrl = row.logo || row.team_logo || row.logo_url || row.cover_url || '';
        const initials = teamName.split(/\s+/).filter(Boolean).slice(0, 3).map((part) => part[0]).join('').toUpperCase();
        const emblem = logoUrl
          ? `<img loading="lazy" decoding="async" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(teamName)}">`
          : `<span>${escapeHtml(initials || 'EQ')}</span>`;
        return `
        <article class="event-ranking-row" data-ranking-deck-id="${Number(row.id||row.deck_id||0)}">
          <b class="event-ranking-position">#${row.position || index + 1}</b>
          <span class="event-ranking-emblem">${emblem}</span>
          <span class="event-ranking-identity"><strong>${escapeHtml(teamName)}</strong><small>${escapeHtml(deckName)}</small>${row.group ? `<small>Grupo ${Number(row.group)} · puesto ${Number(row.group_position)}</small>` : ''}</span>
          <strong class="event-ranking-points">${row.pts || 0}<small>pts</small></strong>
          <span class="event-ranking-stats">
            <small><b>${row.pj || 0}</b>PJ</small><small><b>${row.pg || row.wins || 0}</b>PG</small><small><b>${row.pp || row.losses || 0}</b>PP</small><small><b>${row.cg || 0}</b>CG</small><small><b>${row.cp || 0}</b>CP</small><small><b>${row.pe || row.draws || 0}</b>E</small><small><b>${row.dp || 0}</b>Dif</small>
            ${row.buchholz !== undefined ? `<small><b>${Number(row.buchholz)}</b>Buchholz</small>` : ''}
          </span>
        </article>
      `;
      }).join('') : '<p class="note">Todavia no hay resultados persistidos para esta fase.</p>';
      if(byDivision){rankingNode.insertAdjacentHTML('afterbegin',`<div class="event-ranking-division-title"><h3>${escapeHtml(activeDivision?.name||'Sin divisiones registradas')}</h3><p>${ranking.length} equipos · Posiciones de esta división</p></div>`);}
    }
    const scheduleModalTitle = eventScheduleModalNode ? eventScheduleModalNode.querySelector('[data-event-schedule-modal-title]') : null;
    const scheduleModalMeta = eventScheduleModalNode ? eventScheduleModalNode.querySelector('[data-event-schedule-modal-meta]') : null;
    if (scheduleModalTitle) scheduleModalTitle.textContent = ['cup', 'final'].includes(String(competition.type || '')) ? `Llave de ${competition.name || 'copa'}` : `Duelos de ${competition.name || 'evento'}`;
    if (scheduleModalMeta) scheduleModalMeta.textContent = `${competition.code || ''} | ${competition.starts_on || 'Sin fecha'} | ${schedulePatternLabel(competition.schedule_pattern) || 'Sin programar'}`;
    const pendingNode = document.querySelector('[data-competition-config-pending-matches]');
    if (pendingNode) {
      const competitionMatches = allMatchList().filter((match) => String(match.competition_id || '') === String(competition.id || ''));
      const pending = competitionMatches.filter((match) => !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')));
      const params = new URLSearchParams(window.location.search);
      const saved = params.get('saved') || '';
      const savedMatch = saved.match(/^evento_configurado_(\d+)$/);
      const isCurrentSavedEvent = savedMatch && String(savedMatch[1] || '') === String(competition.id || '');
      const sortedPending = pending.slice().sort((left, right) =>
        String(left.scheduled_on || '9999-12-31').localeCompare(String(right.scheduled_on || '9999-12-31'))
        || Number(left.ronda || 0) - Number(right.ronda || 0)
        || Number(left.mesa || 0) - Number(right.mesa || 0)
      );
      const sortedMatches = competitionMatches.slice().sort((left, right) =>
        String(left.scheduled_on || '9999-12-31').localeCompare(String(right.scheduled_on || '9999-12-31'))
        || Number(left.ronda || 0) - Number(right.ronda || 0)
        || Number(left.mesa || 0) - Number(right.mesa || 0)
      );
      const success = isCurrentSavedEvent && competitionMatches.length > 0
        ? `<div class="schedule-programmed-alert"><i class="bi bi-check-circle"></i><div><strong>Programacion guardada: ${competitionMatches.length} combates.</strong><span>Estos cruces quedaron persistidos en base de datos y se muestran como fechas tentativas hasta comenzar el evento.</span></div></div>`
        : '';
      if (['cup', 'final'].includes(String(competition.type || ''))) {
        pendingNode.innerHTML = renderCupBracket(competitionMatches, competition, success);
        window.StationXCupTree?.mount(pendingNode);
      } else {
        pendingNode.innerHTML = renderDivisionSchedule(sortedMatches, competition, success);
      }
      pendingNode.querySelectorAll('[data-schedule-division]').forEach(button=>button.addEventListener('click',()=>{
        pendingNode.querySelectorAll('[data-schedule-division]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));
        pendingNode.querySelectorAll('[data-schedule-division-panel]').forEach(panel=>panel.hidden=panel.dataset.scheduleDivisionPanel!==button.dataset.scheduleDivision);
        const scroll=eventScheduleModalNode?.querySelector('.modal-body'); if(scroll)scroll.scrollTop=0;
      }));
      pendingNode.querySelectorAll('[data-schedule-open-match]:not(.cup-tree-node)').forEach((button) => {
        button.addEventListener('click', () => {
          if (eventScheduleModal) eventScheduleModal.hide();
          window.setTimeout(() => openMatchSheet(button.dataset.scheduleOpenMatch), 180);
        });
      });
    }
    const dependenciesNode = competitionConfigModal.querySelector('[data-competition-config-dependencies]');
    if (dependenciesNode) {
      const dependencies = Array.isArray(competition.dependencies) ? competition.dependencies : [];
      dependenciesNode.innerHTML = dependencies.length ? dependencies.map((dependency) => `
        <article class="${dependency.ok ? 'ready' : 'blocked'}">
          <span>${dependency.name || dependency.depends_on_name || 'Evento previo'}</span>
          <b>${dependency.ok ? 'OK' : 'Pendiente'}</b>
          <small>${dependency.condition_type || 'completed_with_results'} | ${dependency.status || dependency.depends_on_status || 'pending_configuration'}</small>
        </article>
      `).join('') : '<p class="note">Sin prerequisitos activos.</p>';
    }
    updateCompetitionConfigVisibility();
    updateCompetitionConfigCount();
    updateCompetitionDeckConstraints();
    updateCompetitionCancelledLock(competition.status);
    return true;
  }
  function updateCompetitionConfigVisibility() {
    if (!competitionConfigModal) return;
    const typeSelect = competitionConfigModal.querySelector('[data-competition-config-type]');
    const type = typeSelect ? typeSelect.value : '';
    const conditionWrap = competitionConfigModal.querySelector('[data-competition-config-condition-wrap]');
    const conditionSelect = competitionConfigModal.querySelector('[data-competition-config-condition]');
    const qualifierAction = competitionConfigModal.querySelector('[data-competition-config-qualifier-action]');
    const cupAction = competitionConfigModal.querySelector('[data-competition-config-cup-action]');
    const qualifierDuelsWrap = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels-wrap]');
    const matchesPerDayWrap = competitionConfigModal.querySelector('[data-competition-config-matches-per-day-wrap]');
    const leagueMovement = competitionConfigModal.querySelector('[data-competition-league-movement]');
    const divisionWrap = competitionConfigModal.querySelector('[data-competition-config-division-wrap]');
    const divisionsWrap = competitionConfigModal.querySelector('[data-competition-config-divisions-wrap]');
    const qualifierLoadButton = competitionConfigModal.querySelector('[data-qualifier-load-open]');
    const needsCondition = ['league', 'tournament', 'world'].includes(type);
    if (conditionWrap) conditionWrap.hidden = !needsCondition;
    if (conditionSelect && !needsCondition) conditionSelect.value = '';
    if (qualifierAction) qualifierAction.hidden = type !== 'qualifier';
    if (cupAction) cupAction.hidden = !['cup', 'final'].includes(type);
    if (qualifierDuelsWrap) qualifierDuelsWrap.hidden = type !== 'qualifier';
    if (matchesPerDayWrap) matchesPerDayWrap.hidden = !['qualifier', 'cup', 'final'].includes(type);
    const competition = currentCompetitionConfig() || {};
    const selectedDivisionCount = leagueMovementDivisions(competition).length;
    const movementOpen = competitionConfigModal.querySelector('[data-league-movement-open]');
    if (movementOpen) movementOpen.hidden = type !== 'league' || selectedDivisionCount < 2;
    if (leagueMovement) leagueMovement.hidden = type !== 'league' || selectedDivisionCount < 2;
    if (divisionWrap) divisionWrap.hidden = type !== 'league';
    if (divisionsWrap) divisionsWrap.hidden = type !== 'league';
    if (qualifierLoadButton) qualifierLoadButton.hidden = !['cup', 'tournament'].includes(type);
    updateCompetitionDeckConstraints();
  }
  document.querySelectorAll('[data-competition-config]').forEach((button) => {
    button.addEventListener('click', () => fillCompetitionConfig(button.dataset.competitionConfig));
  });
  const competitionConfigType = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-type]') : null;
  if (competitionConfigType) competitionConfigType.addEventListener('change', updateCompetitionConfigVisibility);
  const competitionConfigRulesetVersion = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-ruleset-version]') : null;
  const competitionConfigRuleset = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-ruleset]') : null;
  const competitionConfigDivision = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-division]') : null;
  if (competitionConfigDivision) competitionConfigDivision.addEventListener('change', () => {
    syncRulesetFromDivision(competitionConfigDivision, competitionConfigRuleset);
    syncMovementTargetOptions(competitionConfigDivision.value, '[data-competition-config-promotion-to]', '[data-competition-config-relegation-to]');
    syncCompetitionDeckPoolNote();
    renderCompetitionSelectedDecks();
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
  });
  const competitionConfigDivisions = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-divisions]') : null;
  if (competitionConfigDivisions) competitionConfigDivisions.addEventListener('change', () => {
    const selectedIds = Array.from(competitionConfigDivisions.selectedOptions).map((option) => option.value);
    if (competitionConfigDivision && competitionConfigDivision.value && !selectedIds.includes(competitionConfigDivision.value)) {
      const primaryOption = Array.from(competitionConfigDivisions.options).find((option) => option.value === competitionConfigDivision.value);
      if (primaryOption) primaryOption.selected = true;
    }
    if (competitionConfigDivision && !competitionConfigDivision.value && selectedIds.length) {
      competitionConfigDivision.value = selectedIds[0];
    }
    updateCompetitionConfigVisibility();
    updateCompetitionDeckConstraints();
  });
  if (competitionConfigRuleset) competitionConfigRuleset.addEventListener('change', () => { refreshFormatParticipants(); updateCompetitionConfigVisibility(); });
  const competitionConfigDeckPool = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-deck-pool]') : null;
  if (competitionConfigDeckPool) competitionConfigDeckPool.addEventListener('change', refreshFormatParticipants);
  const competitionConfigMatch = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-match]') : null;
  const competitionConfigQualifierDuels = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]') : null;
  const competitionConfigStart = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-start]') : null;
  const competitionConfigStatus = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-status]') : null;
  if (competitionConfigMatch) competitionConfigMatch.addEventListener('change', () => {
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
  });
  if (competitionConfigQualifierDuels) competitionConfigQualifierDuels.addEventListener('change', updateCompetitionDeckConstraints);
  if (competitionConfigStart) competitionConfigStart.addEventListener('change', updateCompetitionDeckConstraints);
  if (competitionConfigStatus) competitionConfigStatus.addEventListener('change', () => {
    if (competitionConfigStatus.value === 'cancelled') {
      const statusNote = competitionConfigModal?.querySelector('[data-competition-status-note]');
      if (statusNote) statusNote.textContent = 'El evento quedara anulado al guardar la ficha.';
    } else {
      updateCompetitionCancelledLock(competitionConfigStatus.value);
    }
    updateCompetitionDeckConstraints();
  });
  const competitionConfigLogoInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-logo-input]') : null;
  if (competitionConfigLogoInput) {
    competitionConfigLogoInput.addEventListener('change', () => {
      const file = competitionConfigLogoInput.files && competitionConfigLogoInput.files[0] ? competitionConfigLogoInput.files[0] : null;
      const logoWrap = competitionConfigModal.querySelector('[data-competition-config-logo-wrap]');
      const logoImg = competitionConfigModal.querySelector('[data-competition-config-logo]');
      const removeLogoInput = competitionConfigModal.querySelector('[data-competition-config-remove-logo]');
      if (!logoWrap || !logoImg || !file) return;
      if (removeLogoInput) removeLogoInput.checked = false;
      logoImg.src = URL.createObjectURL(file);
      logoWrap.hidden = false;
    });
  }
  const competitionRemoveLogoInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-remove-logo]') : null;
  if (competitionRemoveLogoInput) {
    competitionRemoveLogoInput.addEventListener('change', () => {
      if (!competitionRemoveLogoInput.checked) return;
      const logoWrap = competitionConfigModal.querySelector('[data-competition-config-logo-wrap]');
      const logoImg = competitionConfigModal.querySelector('[data-competition-config-logo]');
      const logoInput = competitionConfigModal.querySelector('[data-competition-config-logo-input]');
      if (logoInput) logoInput.value = '';
      if (logoImg) logoImg.src = '';
      if (logoWrap) logoWrap.hidden = true;
    });
  }
  if (competitionConfigRulesetVersion) competitionConfigRulesetVersion.addEventListener('change', () => {
    syncRulesetFromVersion(competitionConfigRulesetVersion, competitionConfigRuleset);
    updateCompetitionConfigVisibility();
  });
  const competitionDeckSearch = deckSearchNode();
  if (competitionDeckSearch) competitionDeckSearch.addEventListener('input', renderCompetitionDeckPicker);
  const competitionDeckAdd = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-add]') : null;
  if (competitionDeckAdd) {
    competitionDeckAdd.addEventListener('click', () => {
      if (competitionDeckRosterLocked()) return;
      const picker = deckPickerNode();
      const deckId = picker ? String(picker.value || '') : '';
      if (!deckId) return;
      if (!canAddParticipant(deckById(deckId))) {
        showParticipantRestriction(participantAddRestriction(deckById(deckId)));
        return;
      }
      if (effectiveSelectedCompetitionDeckIds().has(deckId)) {
        renderCompetitionDeckPicker();
        return;
      }
      useManualFormatSelection();
      competitionSelectedDeckIds.add(deckId);
      if (competitionDeckSearch) competitionDeckSearch.value = '';
      syncCompetitionDeckInputs();
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
    });
  }
  const competitionDecksOpen = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-decks-open]') : null;
  if (competitionDecksOpen) {
    competitionDecksOpen.addEventListener('click', () => {
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      if (competitionDecksModal) competitionDecksModal.show();
    });
  }
  function completedQualifierOptions() {
    const targetDivisionId = selectedCompetitionDivisionId();
    return (data.competitions || [])
      .filter((competition) => {
        if (String(competition.type || '') !== 'qualifier') return false;
        if (normalizeCompetitionStatus(competition.status) !== 'completed') return false;
        const ranking = Array.isArray(competition.ranking) ? competition.ranking : [];
        if (!ranking.length) return false;
        return !targetDivisionId || String(competition.division_id || '') === String(targetDivisionId);
      })
      .sort((a, b) => String(b.ends_on || b.starts_on || b.created_at || '').localeCompare(String(a.ends_on || a.starts_on || a.created_at || '')) || Number(b.id || 0) - Number(a.id || 0));
  }
  function selectedQualifierLoad() {
    return completedQualifierOptions().find((competition) => String(competition.id || '') === String(activeQualifierLoadId || '')) || null;
  }
  function renderQualifierLoadList() {
    if (!qualifierLoadModalNode) return;
    const body = qualifierLoadModalNode.querySelector('[data-qualifier-load-list]');
    if (!body) return;
    const query = String(qualifierLoadModalNode.querySelector('[data-qualifier-load-search]')?.value || '').trim().toLowerCase();
    const rows = completedQualifierOptions().filter((competition) => {
      const text = `${competition.name || ''} ${competition.code || ''} ${competition.division_code || ''} ${competition.ends_on || ''} ${competition.starts_on || ''}`.toLowerCase();
      return !query || text.includes(query);
    });
    body.innerHTML = rows.length ? rows.map((competition) => {
      const rankingCount = Array.isArray(competition.ranking) ? competition.ranking.length : 0;
      const selected = String(competition.id || '') === String(activeQualifierLoadId || '');
      return `
        <tr class="${selected ? 'active-row' : ''}">
          <td><b>${escapeHtml(competition.name || 'Clasificatorio')}</b><br><small>${escapeHtml(competition.code || '')}</small></td>
          <td>${escapeHtml(competition.division_code || competition.division || 'General')}</td>
          <td>${escapeHtml(competition.ends_on || competition.starts_on || 'Sin fecha')}</td>
          <td>${Number(competition.deck_pool_count || (competition.selected_deck_ids || []).length || rankingCount)}</td>
          <td>${rankingCount}</td>
          <td><button class="btn btn-outline-info btn-sm" type="button" data-qualifier-load-select="${escapeHtml(competition.id)}">Elegir</button></td>
        </tr>
      `;
    }).join('') : '<tr><td colspan="6">No hay clasificatorios finalizados con ranking para este formato.</td></tr>';
    body.querySelectorAll('[data-qualifier-load-select]').forEach((button) => {
      button.addEventListener('click', () => {
        activeQualifierLoadId = String(button.dataset.qualifierLoadSelect || '');
        const qualifier = selectedQualifierLoad();
        const fromInput = qualifierLoadModalNode.querySelector('[data-qualifier-load-from]');
        const toInput = qualifierLoadModalNode.querySelector('[data-qualifier-load-to]');
        if (fromInput) fromInput.value = '1';
        if (toInput) toInput.value = String(Math.min(4, Math.max(0, qualifier?.ranking?.length || 0)));
        renderQualifierLoadList();
        renderQualifierLoadPreview();
      });
    });
  }
  function qualifierLoadRange() {
    const qualifier = selectedQualifierLoad();
    const ranking = Array.isArray(qualifier?.ranking) ? qualifier.ranking : [];
    const fromInput = qualifierLoadModalNode?.querySelector('[data-qualifier-load-from]');
    const toInput = qualifierLoadModalNode?.querySelector('[data-qualifier-load-to]');
    const from = Math.max(1, Number.parseInt(String(fromInput?.value || '1'), 10) || 1);
    const to = Math.max(1, Number.parseInt(String(toInput?.value || '1'), 10) || 1);
    const start = Math.min(from, to);
    const end = Math.max(from, to);
    const selectedRows = ranking.slice(start - 1, end);
    return { qualifier, ranking, from: start, to: end, selectedRows };
  }
  function renderQualifierLoadPreview() {
    if (!qualifierLoadModalNode) return;
    const { qualifier, ranking, from, to, selectedRows } = qualifierLoadRange();
    const title = qualifierLoadModalNode.querySelector('[data-qualifier-load-title]');
    const meta = qualifierLoadModalNode.querySelector('[data-qualifier-load-meta]');
    const note = qualifierLoadModalNode.querySelector('[data-qualifier-load-note]');
    const preview = qualifierLoadModalNode.querySelector('[data-qualifier-load-preview]');
    const apply = qualifierLoadModalNode.querySelector('[data-qualifier-load-apply]');
    const rankingTable = qualifierLoadModalNode.querySelector('[data-qualifier-load-ranking]');
    if (title) title.textContent = qualifier ? qualifier.name || 'Clasificatorio' : 'Selecciona un clasificatorio';
    if (meta) meta.textContent = qualifier ? `${qualifier.division_code || qualifier.division || 'General'} | ${ranking.length} posiciones disponibles` : 'Solo se listan clasificatorios finalizados con ranking calculado.';
    const total = selectedRows.length;
    const deckIds = selectedRows.map((row) => String(row.id || '')).filter(Boolean);
    const missingFormat = deckIds.some((id) => !deckMatchesCompetitionFormat(deckById(id)));
    const valid = !!qualifier && total >= 4 && total % 2 === 0 && to <= ranking.length && !missingFormat;
    if (note) {
      note.textContent = !qualifier
        ? 'Elige un clasificatorio de la tabla.'
        : missingFormat
          ? 'El rango contiene decks de otro formato. Elige un clasificatorio del mismo formato.'
          : valid
            ? `Rango valido: ${from}-${to}, ${total} decks.`
            : `Rango invalido: debe ser par, minimo 4 y no superar ${ranking.length} posiciones.`;
      note.classList.toggle('text-warning', !!qualifier && !valid);
    }
    if (preview) {
      preview.innerHTML = selectedRows.length
        ? `<strong>${total} decks listos para cargar</strong><span>Posiciones ${from} a ${to}. Al cargar se reemplaza la seleccion actual y luego puedes agregar o quitar manualmente.</span>`
        : '<p class="note">Sin decks en el rango seleccionado.</p>';
    }
    if (rankingTable) {
      rankingTable.innerHTML = ranking.length ? ranking.map((row, index) => {
        const position = index + 1;
        const deck = deckById(row.id);
        const inRange = position >= from && position <= to;
        return `
          <tr class="${inRange ? 'active-row' : ''}">
            <td>#${position}</td>
            <td><b>${escapeHtml(row.name || deck?.name || 'Deck')}</b></td>
            <td>${escapeHtml(row.team || deck?.team || 'Sin equipo')}</td>
            <td>${escapeHtml(deck?.division || qualifier?.division_code || 'Formato')}</td>
            <td>${escapeHtml(row.pj ?? row.played ?? '-')}</td>
            <td>${escapeHtml(row.pts ?? row.points ?? '-')}</td>
          </tr>
        `;
      }).join('') : '<tr><td colspan="6"><div class="empty-state">Elige un clasificatorio para ver todas las posiciones.</div></td></tr>';
    }
    if (apply) apply.disabled = !valid;
  }
  const qualifierLoadOpen = competitionConfigModal ? competitionConfigModal.querySelector('[data-qualifier-load-open]') : null;
  if (qualifierLoadOpen) {
    qualifierLoadOpen.addEventListener('click', () => {
      if (!qualifierLoadModal) return;
      activeQualifierLoadId = '';
      const search = qualifierLoadModalNode.querySelector('[data-qualifier-load-search]');
      if (search) search.value = '';
      renderQualifierLoadList();
      renderQualifierLoadPreview();
      qualifierLoadModal.show();
    });
  }
  if (qualifierLoadModalNode) {
    qualifierLoadModalNode.querySelector('[data-qualifier-load-search]')?.addEventListener('input', renderQualifierLoadList);
    qualifierLoadModalNode.querySelector('[data-qualifier-load-from]')?.addEventListener('input', renderQualifierLoadPreview);
    qualifierLoadModalNode.querySelector('[data-qualifier-load-to]')?.addEventListener('input', renderQualifierLoadPreview);
    qualifierLoadModalNode.querySelector('[data-qualifier-load-apply]')?.addEventListener('click', () => {
      const { selectedRows } = qualifierLoadRange();
      if (selectedRows.some(row => participantOtherLeague(deckById(row.id))) || !compatibleParticipantRoster(selectedRows.map(row => deckById(row.id)))) {
        window.alert('No se pueden cargar decks de otro formato o asignados a otra liga.');
        return;
      }
      useManualFormatSelection();
      competitionSelectedDeckIds.clear();
      selectedRows.forEach((row) => {
        const id = String(row.id || '');
        if (id) competitionSelectedDeckIds.add(id);
      });
      syncCompetitionDeckInputs();
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
      if (qualifierLoadModal) qualifierLoadModal.hide();
      if (competitionDecksModal) competitionDecksModal.show();
    });
  }
  const competitionDeleteOpen = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-delete-open]') : null;
  if (competitionDeleteOpen) {
    competitionDeleteOpen.addEventListener('click', () => {
      if (competitionDeleteOpen.disabled || !deleteCompetitionModalNode) return;
      const idInput = deleteCompetitionModalNode.querySelector('[data-delete-competition-id]');
      const confirmInput = deleteCompetitionModalNode.querySelector('[data-delete-competition-confirmation]');
      const submitButton = deleteCompetitionModalNode.querySelector('[data-delete-competition-submit]');
      const meta = deleteCompetitionModalNode.querySelector('[data-delete-competition-meta]');
      if (idInput) idInput.value = competitionDeleteOpen.dataset.competitionId || '';
      if (confirmInput) confirmInput.value = '';
      if (submitButton) submitButton.disabled = true;
      if (meta) meta.textContent = `Eliminaras "${competitionDeleteOpen.dataset.competitionName || 'este evento'}" y sus datos asociados de la base de datos.`;
      if (deleteCompetitionModal) deleteCompetitionModal.show();
    });
  }
  const deleteCompetitionConfirmation = deleteCompetitionModalNode ? deleteCompetitionModalNode.querySelector('[data-delete-competition-confirmation]') : null;
  if (deleteCompetitionConfirmation) {
    deleteCompetitionConfirmation.addEventListener('input', () => {
      const submitButton = deleteCompetitionModalNode.querySelector('[data-delete-competition-submit]');
      if (submitButton) submitButton.disabled = deleteCompetitionConfirmation.value.trim().toLowerCase() !== 'eliminar';
    });
  }
  document.querySelectorAll('[data-competition-config-deck]').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      if (competitionDeckRosterLocked()) {
        syncCompetitionDeckInputs();
        return;
      }
      if (checkbox.checked && !canAddParticipant(deckById(checkbox.value))) {
        checkbox.checked = false;
        showParticipantRestriction(participantAddRestriction(deckById(checkbox.value)));
        return;
      }
      if (checkbox.checked) {
        competitionSelectedDeckIds.add(String(checkbox.value));
      } else {
        competitionSelectedDeckIds.delete(String(checkbox.value));
      }
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
    });
  });
  function submitCompetitionConfig(mode) {
    if (!competitionConfigModal) return;
    const model = (data.competitions || []).find(c => String(c.id) === String(activeCompetitionConfigId));
    if (model && ['start','schedule_qualifier','schedule_cup','save_schedule'].includes(mode) && competitionPersistedMatchCount(model.id) === 0) {
      const warnings = eventModelWarnings(model);
      if (warnings.length) { window.alert('Modelo pendiente de implementacion/configuracion: ' + warnings.join(' ')); return; }
    }
    const invalidExchange = competitionConfigModal.querySelector('[data-division-exchange]:invalid');
    if (invalidExchange) {
      bootstrap.Modal.getOrCreateInstance(document.querySelector('[data-league-movement-modal]')).show();
      invalidExchange.reportValidity();
      return;
    }
    const occupiedDeck = selectedCompetitionDecks().find(deck => participantOtherLeague(deck));
    if (occupiedDeck) { window.alert(participantAddRestriction(occupiedDeck)); return; }
    if (!compatibleParticipantRoster(selectedCompetitionDecks())) {
      window.alert('No se pueden guardar participantes de otro formato ni mezclar formatos. Retira los decks incompatibles.');
      return;
    }
    const form = competitionConfigModal.querySelector('form');
    const modeInput = competitionConfigModal.querySelector('[data-competition-config-mode]');
    if (!form || !modeInput) return;
    const normalizedMode = mode === 'status' ? 'save' : mode;
    const type = competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '';
    const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
    modeInput.value = normalizedMode;
    syncCompetitionDeckInputs();
    updateCompetitionDeckConstraints();
    if (['schedule_qualifier', 'schedule_cup'].includes(normalizedMode) && startInput && startInput.value && startInput.value < serverToday) {
      startInput.reportValidity();
      window.alert('La fecha de inicio no puede ser menor a la fecha actual.');
      return;
    }
    if (normalizedMode === 'start' && startInput && startInput.value && startInput.value > serverToday) {
      window.alert(`Este evento se podra comenzar el ${startInput.value}.`);
      return;
    }
    if (!['save', 'save_schedule', 'start'].includes(normalizedMode) && !updateCompetitionDeckConstraints()) {
      window.alert(type === 'qualifier'
        ? 'Configuracion invalida: el clasificatorio necesita minimo 4 decks y combinaciones coherentes entre match y duelos a clasificar.'
        : 'Configuracion invalida: agrega decks suficientes para organizar los combates del evento.');
      return;
    }
    const participantsPerSide = competitionParticipantsPerSide(competitionConfigModal.querySelector('[data-competition-config-match]'));
    const eligibleGroups = currentEligibleGroupCount(participantsPerSide);
    const expectedMatches = expectedQualifierMatchCount(eligibleGroups * participantsPerSide, participantsPerSide, Number(competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]')?.value || 0));
    if (normalizedMode === 'start' && type === 'qualifier' && competitionScheduledMatchCount() < expectedMatches) {
      window.alert('Primero debes programar todos los duelos y fechas tentativas del clasificatorio.');
      return;
    }
    if (normalizedMode === 'schedule_qualifier' && type === 'qualifier' && confirmScheduleQualifierModal) {
      const competitionId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
      confirmScheduleQualifierModalNode.dataset.pendingScheduleMode = 'schedule_qualifier';
      const existingMatches = allMatchList().filter((match) =>
        String(match.competition_id || '') === String(competitionId)
        && !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''))
      );
      const message = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-message]');
      const meta = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-meta]');
      if (meta) meta.textContent = existingMatches.length
        ? `Este evento ya tiene ${existingMatches.length} duelos programados.`
        : 'Este evento aun no tiene duelos programados.';
      if (message) message.textContent = existingMatches.length
        ? 'Ya se genero un calendario de duelos y fechas para este evento. Si continuas, se reemplazara el calendario anterior no finalizado y se actualizara en base de datos.'
        : 'Si continuas, se generaran los cruces y fechas tentativas del clasificatorio y quedaran guardados en base de datos.';
      confirmScheduleQualifierModal.show();
      return;
    }
    if (normalizedMode === 'schedule_cup' && ['cup', 'final'].includes(type) && confirmScheduleQualifierModal) {
      const competitionId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
      confirmScheduleQualifierModalNode.dataset.pendingScheduleMode = 'schedule_cup';
      const existingMatches = allMatchList().filter((match) =>
        String(match.competition_id || '') === String(competitionId)
        && !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''))
      );
      if (existingMatches.length) {
        window.alert('Esta copa ya fue programada. La llave existente no se puede volver a generar.');
        return;
      }
      const message = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-message]');
      const meta = confirmScheduleQualifierModalNode.querySelector('[data-confirm-schedule-qualifier-meta]');
      if (meta) meta.textContent = 'Esta copa aun no tiene llave programada.';
      if (message) message.textContent = 'Si continuas, se generara una sola vez la llave inicial de copa, sus cruces y fechas tentativas en base de datos.';
      confirmScheduleQualifierModal.show();
      return;
    }
    if (normalizedMode === 'start' && confirmStartQualifierModal) {
      const scheduledMatches = competitionScheduledMatchCount();
      const title = confirmStartQualifierModalNode.querySelector('[data-confirm-start-title]');
      const subtitle = confirmStartQualifierModalNode.querySelector('[data-confirm-start-subtitle]');
      const message = confirmStartQualifierModalNode.querySelector('[data-confirm-start-message]');
      if (title) title.textContent = `Comenzar ${eventTypeLabel(type).toLowerCase()}`;
      if (subtitle) subtitle.textContent = 'El evento cambiara a estado activo y su configuracion quedara bloqueada.';
      if (message) message.textContent = type === 'league' && startInput?.value > serverToday && scheduledMatches > 0
        ? `La liga esta programada para el ${startInput.value}. Si confirmas, quedara activa desde ahora sin cambiar las fechas de sus combates. Esta seguro de continuar?`
        : (['cup', 'final'].includes(type) && scheduledMatches === 0
        ? 'Esta copa aun no ha sido programada. Al confirmar, el sistema generara automaticamente la llave y los combates antes de comenzar. Esta seguro de continuar?'
        : 'El evento ya tiene su programacion preparada. Esta seguro de comenzar?');
      confirmStartQualifierModal.show();
      return;
    }
    form.requestSubmit();
  }
  document.querySelectorAll('[data-competition-config-submit]').forEach((button) => {
    button.addEventListener('click', () => submitCompetitionConfig(button.dataset.competitionConfigSubmit || 'save'));
  });
  const finalizeCompetitionButton = document.querySelector('[data-competition-finalize-submit]');
  if (finalizeCompetitionButton) {
    finalizeCompetitionButton.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const actionInput = form ? form.querySelector('input[name="action"]') : null;
      if (!form || !actionInput || finalizeCompetitionButton.disabled) return;
      actionInput.value = 'finalize_competition';
      syncCompetitionDeckInputs();
      form.requestSubmit();
    });
  }
  document.querySelectorAll('[data-competition-action-submit]').forEach((button) => {
    button.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const actionInput = form ? form.querySelector('input[name="action"]') : null;
      const competitionIdInput = form ? form.querySelector('[data-competition-config-id]') : null;
      if (!form || !actionInput || !competitionIdInput?.value || button.disabled) return;
      const previewText = document.querySelector('[data-competition-action-preview]')?.innerText?.replace(/\s+/g, ' ').trim() || '';
      const actionLabel = button.textContent.trim() || 'ejecutar accion';
      if (!window.confirm(`${actionLabel}\n\n${previewText}\n\nEsta accion se guardara en base de datos. Deseas continuar?`)) return;
      actionInput.value = button.dataset.competitionActionSubmit || 'configure_competition';
      syncCompetitionDeckInputs();
      form.requestSubmit();
    });
  });
  const confirmStartQualifierSubmit = document.querySelector('[data-confirm-start-qualifier-submit]');
  if (confirmStartQualifierSubmit) {
    confirmStartQualifierSubmit.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const modeInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-mode]') : null;
      if (!form || !modeInput) return;
      const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
      modeInput.value = 'start';
      syncCompetitionDeckInputs();
      updateCompetitionDeckConstraints();
      const type = competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '';
      if (startInput && startInput.value && startInput.value > serverToday) {
        window.alert(`Este evento se podra comenzar el ${startInput.value}.`);
        return;
      }
      updateCompetitionDeckConstraints();
      const participantsPerSide = competitionParticipantsPerSide(competitionConfigModal.querySelector('[data-competition-config-match]'));
      const eligibleGroups = currentEligibleGroupCount(participantsPerSide);
      const expectedMatches = expectedQualifierMatchCount(eligibleGroups * participantsPerSide, participantsPerSide, Number(competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]')?.value || 0));
      if (type === 'qualifier' && competitionScheduledMatchCount() < expectedMatches) {
        window.alert('Primero debes programar todos los duelos y fechas tentativas del clasificatorio.');
        return;
      }
      form.requestSubmit();
    });
  }
  const confirmScheduleQualifierSubmit = document.querySelector('[data-confirm-schedule-qualifier-submit]');
  if (confirmScheduleQualifierSubmit) {
    confirmScheduleQualifierSubmit.addEventListener('click', () => {
      const form = competitionConfigModal ? competitionConfigModal.querySelector('form') : null;
      const modeInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-mode]') : null;
      if (!form || !modeInput) return;
      modeInput.value = confirmScheduleQualifierModalNode?.dataset?.pendingScheduleMode || 'schedule_qualifier';
      syncCompetitionDeckInputs();
      if (!updateCompetitionDeckConstraints()) {
        const currentType = competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '';
        window.alert(currentType === 'qualifier'
          ? 'Configuracion invalida: el clasificatorio necesita minimo 4 decks y combinaciones coherentes entre match y duelos a clasificar.'
          : 'Configuracion invalida: agrega decks suficientes para organizar los combates del evento.');
        return;
      }
      form.requestSubmit();
    });
  }
  function renderEventDetail(competitionId) {
    if (!eventDetail) return;
    const competition = (data.competitions || []).find((item) => String(item.id) === String(competitionId));
    if (!competition) return;
    const ranking = Array.isArray(competition.ranking) ? competition.ranking : [];
    const topCut = Array.isArray(competition.top_cut) ? competition.top_cut : ranking.slice(0, competition.type === 'world' ? 24 : 16);
    setEventDetailText('[data-event-detail-title]', competition.name || 'Ficha del evento');
    setEventDetailText('[data-event-detail-meta]', `${eventTypeLabel(competition.type)} | ${rulesetLabel(competition.event_ruleset)} | ${competition.format || 'Sistema editable'} | ${String(competition.match_format || '1v1').toUpperCase()} BO${competition.best_of || 5}`);
    setEventDetailText('[data-event-detail-pool]', poolLabel(competition.deck_pool));
    setEventDetailText('[data-event-detail-pool-count]', `${competition.deck_pool_count || 0} decks habilitados`);
    setEventDetailText('[data-event-detail-ranking-state]', competition.ranking_ready ? `${ranking.length} con resultados` : 'Pendiente');
    setEventDetailText('[data-event-detail-gate]', competition.can_execute === false || competition.can_execute === '0' ? 'Bloqueado' : 'Habilitado');
    setEventDetailText('[data-event-detail-gate-note]', competition.blocked_reason || 'Sin bloqueo activo');
    setEventDetailText('[data-event-detail-dates]', `${competition.starts_on || 'Sin inicio'} -> ${competition.ends_on || 'Sin fin'}`);
    setEventDetailText('[data-event-detail-schedule]', schedulePatternLabel(competition.schedule_pattern) || competition.schedule_strategy || 'manual');
    setEventDetailText('[data-event-detail-rule]', gameRuleLabel(competition.game_rule_id));
    setEventDetailText('[data-event-detail-lock]', competition.configuration_locked === 1 || competition.configuration_locked === '1' ? 'Fecha y frecuencia bloqueadas' : 'Pendiente comenzar');
    setEventDetailText('[data-event-detail-victory]', competition.victory_condition || 'Configurable');
    setEventDetailText('[data-event-detail-defeat]', competition.defeat_condition || 'Configurable');
    setEventDetailText('[data-event-detail-draw]', competition.draw_condition || 'Configurable');
    const rankingNode = eventDetail.querySelector('[data-event-detail-ranking]');
    if (rankingNode) {
      const rows = topCut.length ? topCut : [];
      rankingNode.innerHTML = rows.length ? rows.slice(0, 12).map((row, index) => `
        <article>
          <b>#${index + 1}</b>
          <span>${row.team || 'Sin equipo'} / ${row.name}</span>
          <small>${row.pts || 0} pts | DP ${row.dp || 0} | CG ${row.cg || 0}</small>
        </article>
      `).join('') : '<p class="note">Todavia no hay ranking deportivo para este evento. Se usara el pool configurado como preparacion.</p>';
    }
    const dependenciesNode = eventDetail.querySelector('[data-event-detail-dependencies]');
    if (dependenciesNode) {
      const dependencies = Array.isArray(competition.dependencies) ? competition.dependencies : [];
      dependenciesNode.innerHTML = dependencies.length ? dependencies.map((dependency) => `
        <article class="${dependency.ok ? 'ready' : 'blocked'}">
          <span>${dependency.name}</span>
          <b>${dependency.ok ? 'OK' : 'Pendiente'}</b>
          <small>${dependency.condition_type || 'completed_with_results'} | ${dependency.status || 'draft'} | ${dependency.result_count || 0} resultados</small>
        </article>
      `).join('') : '<p class="note">Sin prerequisitos activos.</p>';
    }
    const pendingNode = eventDetail.querySelector('[data-event-detail-pending-matches]');
    if (pendingNode) {
      const competitionMatches = allMatchList().filter((match) => String(match.competition_id || '') === String(competition.id || ''));
      const pending = competitionMatches.filter((match) => !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || '')));
      if (['cup', 'final'].includes(String(competition.type || ''))) {
        pendingNode.innerHTML = renderCupBracket(competitionMatches, competition);
        window.StationXCupTree?.mount(pendingNode);
        return;
      }
      const byDate = pending.reduce((map, match) => {
        const key = match.scheduled_on || 'Sin fecha';
        map[key] = map[key] || [];
        map[key].push(match);
        return map;
      }, {});
      const days = Object.keys(byDate).sort();
      pendingNode.innerHTML = days.length ? days.map((day) => `
        <article>
          <span>${day}</span>
          <b>${byDate[day].length} pendientes</b>
          <small>${byDate[day].slice(0, 3).map((match) => `Mesa ${match.mesa}: ${matchVersusLabel(match)}`).join(' | ')}</small>
        </article>
      `).join('') : '<p class="note">No hay duelos pendientes programados para este evento.</p>';
    }
    eventDetail.hidden = false;
    eventDetail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  document.querySelectorAll('[data-event-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.eventOpen;
      if (!(data.competitions || []).some((event) => String(event.id) === String(id))) return;
      setEventFichaUrl(id);
      if (fillCompetitionConfig(id)) routeTo('evento_ficha');
    });
  });
  const openEventRankingModal = document.querySelector('[data-open-event-ranking-modal]');
  if (openEventRankingModal) {
    openEventRankingModal.addEventListener('click', () => {
      if (!activeCompetitionConfigId && (data.competitions || []).length) fillCompetitionConfig(data.competitions[0].id);
      if (eventRankingModal) eventRankingModal.show();
    });
  }
  const savedReadingModalNode = document.getElementById('eventSavedReadingModal');
  const savedReadingModal = savedReadingModalNode && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(savedReadingModalNode) : null;
  document.querySelectorAll('[data-open-event-saved-reading]').forEach(button => button.addEventListener('click', () => {
    const competition = currentCompetitionConfig();
    if (!competition || !savedReadingModal) return;
    renderEventModelImpact(competition);
    savedReadingModalNode.querySelector('[data-event-saved-reading-name]').textContent = competition.name || '';
    savedReadingModal.show();
  }));
  document.querySelectorAll('[data-open-event-rule-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!activeCompetitionConfigId && (data.competitions || []).length) fillCompetitionConfig(data.competitions[0].id);
      renderCompetitionRuleSummary(currentCompetitionConfig());
      if (eventRuleModal) eventRuleModal.show();
    });
  });
  const competitionConfigRuleSelect = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-rule]') : null;
  if (competitionConfigRuleSelect) {
    competitionConfigRuleSelect.addEventListener('change', () => renderCompetitionRuleSummary(currentCompetitionConfig()));
  }
  const openEventScheduleModal = document.querySelector('[data-open-event-schedule-modal]');
  if (openEventScheduleModal) {
    openEventScheduleModal.addEventListener('click', () => {
      if (!activeCompetitionConfigId && (data.competitions || []).length) fillCompetitionConfig(data.competitions[0].id);
      if (eventScheduleModal) eventScheduleModal.show();
    });
  }
  const eventProgrammingOpen = document.querySelector('[data-event-programming-open]');
  if (eventProgrammingOpen) {
    eventProgrammingOpen.addEventListener('click', () => {
      if (eventProgrammingModal) eventProgrammingModal.show();
    });
  }
  window.addEventListener('dml:routechange', (event) => {
    if (event.detail?.target !== 'evento_ficha' || !competitionConfigModal) return;
    const currentId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
    const requestedId = requestedEventFichaId();
    if (requestedId) {
      if (!fillCompetitionConfig(requestedId)) {
        routeTo('torneos');
        const notice = document.querySelector('[data-event-open-error]');
        if (notice) { notice.hidden = false; notice.textContent = 'El evento solicitado no esta disponible. Selecciona Ver en el evento que deseas consultar.'; }
      }
    } else if (!currentId) {
      routeTo('torneos');
    } else {
      renderCompetitionDeckPicker();
    }
  });
  const eventDetailClose = document.querySelector('[data-event-detail-close]');
  if (eventDetailClose && eventDetail) eventDetailClose.addEventListener('click', () => { eventDetail.hidden = true; });

  const manualCompetitionSelect = document.querySelector('[data-manual-competition-select]');
  const manualDivisionSelect = document.querySelector('select[name="manual_division_id"]');
  const manualPhaseSelect = document.querySelector('select[name="manual_phase"]');
  const manualMatchFormatSelect = document.querySelector('select[name="manual_match_format"]');
  const manualBestOfInput = document.querySelector('input[name="manual_best_of"]');
  const manualParticipantsInput = document.querySelector('input[name="manual_participants_per_side"]');
  const manualDeckSelects = Array.from(document.querySelectorAll('[data-manual-deck-select]'));
  const manualDeckPoolNote = document.querySelector('[data-manual-deck-pool-note]');
  function manualCompetitionOption() {
    return manualCompetitionSelect ? manualCompetitionSelect.selectedOptions[0] : null;
  }
  function manualParticipantsPerSide() {
    const value = manualMatchFormatSelect?.value || manualParticipantsInput?.value || '1';
    const fromMatch = Number.parseInt(String(value), 10);
    return Math.max(1, Math.min(3, Number(manualParticipantsInput?.value || fromMatch || 1)));
  }
  function manualTargetDivisionId(competitionOption) {
    return String(competitionOption?.dataset?.divisionId || manualDivisionSelect?.value || '');
  }
  function manualEligibleTeamIds(targetDivisionId, participantsPerSide) {
    if (participantsPerSide <= 1) return null;
    const counts = {};
    manualDeckSelects[0]?.querySelectorAll('option[value]').forEach((option) => {
      if (!option.value || (targetDivisionId && option.dataset.divisionId !== targetDivisionId)) return;
      const teamId = option.dataset.teamId || '';
      if (!teamId) return;
      counts[teamId] = (counts[teamId] || 0) + 1;
    });
    return new Set(Object.keys(counts).filter((teamId) => counts[teamId] >= participantsPerSide));
  }
  function manualDeckAllowed(option, competitionOption) {
    if (!option || option.value === '') return true;
    const participantsPerSide = manualParticipantsPerSide();
    const targetDivisionId = manualTargetDivisionId(competitionOption);
    if (targetDivisionId && option.dataset.divisionId !== targetDivisionId) return false;
    if (!option.dataset.teamId) return false;
    const eligibleTeams = manualEligibleTeamIds(targetDivisionId, participantsPerSide);
    if (eligibleTeams && !eligibleTeams.has(option.dataset.teamId || '')) return false;
    if (!competitionOption || !competitionOption.value) return true;
    const pool = competitionOption.dataset.deckPool || 'all_active';
    if (pool === 'all_active' || pool === 'manual') return true;
    if (['division', 'qualified', 'top_cut'].includes(pool) && targetDivisionId) {
      return option.dataset.divisionId === targetDivisionId;
    }
    return true;
  }
  function applyManualCompetition() {
    const option = manualCompetitionOption();
    if (option && option.value) {
      if (manualPhaseSelect && option.dataset.type) manualPhaseSelect.value = option.dataset.type === 'special' ? 'friendly' : option.dataset.type;
      if (manualDivisionSelect && option.dataset.divisionId) manualDivisionSelect.value = option.dataset.divisionId;
      if (manualMatchFormatSelect && option.dataset.matchFormat) manualMatchFormatSelect.value = option.dataset.matchFormat;
      if (manualBestOfInput && option.dataset.bestOf) manualBestOfInput.value = option.dataset.bestOf;
      if (manualParticipantsInput && option.dataset.participants) manualParticipantsInput.value = option.dataset.participants;
    }
    let visibleDecks = 0;
    manualDeckSelects.forEach((select) => {
      Array.from(select.options).forEach((deckOption) => {
        const allowed = manualDeckAllowed(deckOption, option);
        deckOption.hidden = !allowed;
        deckOption.disabled = !allowed;
        if (allowed && deckOption.value) visibleDecks += 1;
      });
      const current = select.selectedOptions[0];
      if (current && current.disabled) {
        const next = Array.from(select.options).find((deckOption) => !deckOption.disabled);
        if (next) select.value = next.value;
      }
    });
    if (manualDeckPoolNote) {
      const pool = option && option.value ? (option.dataset.deckPool || 'all_active') : 'all_active';
      const ruleset = option && option.value ? (option.dataset.ruleset || 'custom') : 'general';
      manualDeckPoolNote.textContent = `Pool aplicado: ${pool} | Reglamento: ${ruleset} | Opciones visibles: ${visibleDecks}`;
    }
  }
  if (manualCompetitionSelect) manualCompetitionSelect.addEventListener('change', applyManualCompetition);
  if (manualDivisionSelect) manualDivisionSelect.addEventListener('change', applyManualCompetition);
  if (manualMatchFormatSelect) manualMatchFormatSelect.addEventListener('change', () => {
    if (manualParticipantsInput) manualParticipantsInput.value = String(Math.max(1, Math.min(3, Number.parseInt(manualMatchFormatSelect.value, 10) || 1)));
    applyManualCompetition();
  });
  if (manualParticipantsInput) manualParticipantsInput.addEventListener('input', applyManualCompetition);
  applyManualCompetition();

  const adminDivisionSelect = document.querySelector('[data-gestion-division-select]');
  const divisionDeckSearch = document.querySelector('[data-division-deck-search]');
  const divisionDeckPicker = document.querySelector('[data-division-deck-picker]');
  const divisionDeckAdd = document.querySelector('[data-division-deck-add]');
  const divisionSelectedDecks = document.querySelector('[data-division-selected-decks]');
  const divisionHiddenDecks = document.querySelector('[data-division-hidden-decks]');
  const divisionSelectedCount = document.querySelector('[data-division-selected-count]');
  let selectedDivisionDeckIds = new Set();
  function syncDivisionDeckSelection(divisionId = adminDivisionSelect?.value || '') {
    selectedDivisionDeckIds = new Set((data.allDecks || [])
      .filter((deck) => !!divisionId && String(deck.division_id || '') === String(divisionId))
      .map((deck) => String(deck.id || ''))
      .filter(Boolean));
    renderDivisionDeckPicker();
  }
  function divisionDeckLabel(deck) {
    return `${deck.team || 'Sin equipo'} - ${deck.name || 'Deck'} | ${deck.division || 'Sin division'}`;
  }
  function renderDivisionDeckPicker() {
    const query = (divisionDeckSearch?.value || '').trim().toLowerCase();
    if (divisionDeckPicker) {
      const available = (data.allDecks || []).filter((deck) => {
        if (selectedDivisionDeckIds.has(String(deck.id || ''))) return false;
        const text = `${deck.name || ''} ${deck.team || ''} ${deck.division || ''}`.toLowerCase();
        return !query || text.includes(query);
      });
      divisionDeckPicker.innerHTML = available.length
        ? '<option value="">Selecciona un deck</option>' + available.map((deck) => `<option value="${escapeHtml(deck.id)}">${escapeHtml(divisionDeckLabel(deck))}</option>`).join('')
        : '<option value="">Sin decks disponibles</option>';
      if (available.length) divisionDeckPicker.value = String(available[0].id || '');
    }
    if (divisionSelectedDecks) {
      const selected = (data.allDecks || []).filter((deck) => selectedDivisionDeckIds.has(String(deck.id || '')));
      divisionSelectedDecks.innerHTML = selected.length ? selected.map((deck) => `
        <article class="selected-deck-pill">
          <span><b>${escapeHtml(deck.name || 'Deck')}</b><small>${escapeHtml(deck.team || 'Sin equipo')} | ${escapeHtml(deck.division || 'Sin division')}</small></span>
          <button class="btn btn-outline-danger btn-sm" type="button" data-division-deck-remove="${escapeHtml(deck.id)}"><i class="bi bi-x-circle"></i> Quitar</button>
        </article>
      `).join('') : '<p class="note">No hay decks seleccionados para esta division.</p>';
    }
    if (divisionHiddenDecks) {
      divisionHiddenDecks.innerHTML = Array.from(selectedDivisionDeckIds)
        .map((id) => `<input type="hidden" name="division_deck_ids[]" value="${escapeHtml(id)}">`)
        .join('');
    }
    if (divisionSelectedCount) divisionSelectedCount.textContent = `${selectedDivisionDeckIds.size} seleccionados`;
  }
  if (divisionDeckAdd) {
    divisionDeckAdd.addEventListener('click', () => {
      const value = String(divisionDeckPicker?.value || '');
      if (!value) return;
      selectedDivisionDeckIds.add(value);
      renderDivisionDeckPicker();
    });
  }
  if (divisionSelectedDecks) {
    divisionSelectedDecks.addEventListener('click', (event) => {
      const button = event.target.closest('[data-division-deck-remove]');
      if (!button) return;
      selectedDivisionDeckIds.delete(String(button.dataset.divisionDeckRemove || ''));
      renderDivisionDeckPicker();
    });
  }
  if (adminDivisionSelect) {
    const syncDivisionTargetOptions = () => {
      const current = String(adminDivisionSelect.value || '');
      document.querySelectorAll('[data-gestion-division-promotion-to] option, [data-gestion-division-relegation-to] option').forEach((option) => {
        option.hidden = !!current && option.value === current;
        if (option.hidden && option.selected) option.selected = false;
      });
    };
    const fillDivision = () => {
      const division = Object.values(data.divisions || {}).find((item) => String(item.id) === adminDivisionSelect.value);
      setValue('[data-gestion-division-code]', division ? division.short : '');
      setValue('[data-gestion-division-name]', division ? division.name : '');
      setValue('[data-gestion-division-cup]', division ? division.cup : '');
      setValue('[data-gestion-division-league]', division ? division.league_competition_id : '');
      setValue('[data-gestion-division-source]', division ? division.source_qualifier_id : '');
      setValue('[data-gestion-division-rank-from]', division ? division.rank_from : '');
      setValue('[data-gestion-division-rank-to]', division ? division.rank_to : '');
      setValue('[data-gestion-division-tier]', division ? officialDivisions.indexOf(division.short) + 1 : '1');
      setValue('[data-gestion-division-size]', division ? (division.deck_count || (division.decks || []).length || 0) : '0');
      setValue('[data-gestion-division-accent]', division ? division.accent : '#36d8ff');
      setValue('[data-gestion-division-description]', division ? division.description : '');
      setValue('[data-gestion-division-start]', division ? division.starts_on : '');
      setValue('[data-gestion-division-end]', division ? division.ends_on : '');
      setValue('[data-gestion-division-promotion-slots]', division ? division.promotion_slots : '2');
      setValue('[data-gestion-division-relegation-slots]', division ? division.relegation_slots : '2');
      setValue('[data-gestion-division-promotion-to]', division ? division.promotion_to_division_id : '');
      setValue('[data-gestion-division-relegation-to]', division ? division.relegation_to_division_id : '');
      syncDivisionTargetOptions();
      syncDivisionDeckSelection(division ? division.id : '');
    };
    adminDivisionSelect.addEventListener('change', fillDivision);
    fillDivision();
  }
  if (divisionDeckSearch) divisionDeckSearch.addEventListener('input', renderDivisionDeckPicker);

  const divisionSearch = document.querySelector('[data-division-search]');
  const divisionMinFilter = document.querySelector('[data-division-min-filter]');
  const divisionVisibleCount = document.querySelector('[data-division-visible-count]');
  function applyDivisionFilters() {
    const query = (divisionSearch?.value || '').trim().toLowerCase();
    const min = divisionMinFilter?.value || '';
    let visible = 0;
    document.querySelectorAll('[data-division-row]').forEach((row) => {
      const show = (!query || (row.dataset.divisionSearch || '').includes(query))
        && (!min || row.dataset.divisionMin === min);
      row.hidden = !show;
      if (show) visible += 1;
    });
    if (divisionVisibleCount) divisionVisibleCount.textContent = `${visible} divisiones`;
  }
  [divisionSearch, divisionMinFilter].forEach((node) => {
    if (node) node.addEventListener('input', applyDivisionFilters);
    if (node) node.addEventListener('change', applyDivisionFilters);
  });
  const divisionFilterClear = document.querySelector('[data-division-filter-clear]');
  if (divisionFilterClear) {
    divisionFilterClear.addEventListener('click', () => {
      if (divisionSearch) divisionSearch.value = '';
      if (divisionMinFilter) divisionMinFilter.value = '';
      applyDivisionFilters();
    });
  }
  applyDivisionFilters();

  const adminMatchSelect = document.querySelector('[data-gestion-match-select]');
  if (adminMatchSelect) {
    const fillMatch = () => {
      const match = allMatchList().find((item) => String(item.id) === adminMatchSelect.value);
      setValue('[data-gestion-match-date]', match ? match.scheduled_on : '');
      setValue('[data-gestion-match-time]', match ? match.starts_at : '');
      setValue('[data-gestion-match-venue]', match ? match.venue : '');
      setValue('[data-gestion-match-judge]', match ? match.judge : '');
      setValue('[data-gestion-match-notes]', match ? match.notes : '');
    };
    adminMatchSelect.addEventListener('change', fillMatch);
    fillMatch();
  }

  function initLifePointTool() {
    const tool = document.querySelector('[data-screen="herramienta_lp"]');
    if (!tool) return;
    const storageKey = 'dml_lp_tool_state';
    const defaultState = {
      a: 8000,
      b: 8000,
      startA: 8000,
      startB: 8000,
      nameA: 'Duelista 1',
      nameB: 'Duelista 2',
      timer: 0,
      running: false,
      turn: 1,
      coin: '',
      dice: '',
      history: []
    };
    let state = { ...defaultState };
    let timerStartedAt = 0;
    let timerTick = null;

    try {
      const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
      state = { ...state, ...saved };
      state.a = Number.isFinite(Number(state.a)) ? Math.max(0, Number(state.a)) : 8000;
      state.b = Number.isFinite(Number(state.b)) ? Math.max(0, Number(state.b)) : 8000;
      state.startA = Number.isFinite(Number(state.startA)) ? Math.max(0, Number(state.startA)) : 8000;
      state.startB = Number.isFinite(Number(state.startB)) ? Math.max(0, Number(state.startB)) : 8000;
      state.timer = Number.isFinite(Number(state.timer)) ? Math.max(0, Number(state.timer)) : 0;
      state.turn = Number.isFinite(Number(state.turn)) ? Math.max(1, Number(state.turn)) : 1;
      state.coin = String(state.coin || '');
      state.dice = String(state.dice || '');
      state.history = Array.isArray(state.history) ? state.history.slice(0, 12) : [];
      state.running = false;
    } catch (error) {
      state = { ...defaultState };
    }

    const formatLp = (value) => new Intl.NumberFormat('es-CO').format(Math.max(0, Math.round(Number(value) || 0)));
    const formatTimer = (seconds) => {
      const value = Math.max(0, Math.floor(seconds || 0));
      const h = String(Math.floor(value / 3600)).padStart(2, '0');
      const m = String(Math.floor((value % 3600) / 60)).padStart(2, '0');
      const s = String(value % 60).padStart(2, '0');
      return `${h}:${m}:${s}`;
    };
    const save = () => {
      localStorage.setItem(storageKey, JSON.stringify({ ...state, running: false }));
    };
    const setStatus = (text) => {
      const node = tool.querySelector('[data-lp-status]');
      if (node) node.textContent = text || 'Listo';
    };
    const renderHistory = () => {
      const list = tool.querySelector('[data-lp-history]');
      if (!list) return;
      list.innerHTML = state.history.length
        ? state.history.map((item) => `<article><b>${escapeHtml(item.player)}</b><span>${escapeHtml(item.text)}</span><small>${escapeHtml(item.score)}</small></article>`).join('')
        : '<p class="note">Sin cambios registrados todavia.</p>';
    };
    const render = () => {
      ['a', 'b'].forEach((player) => {
        const value = Math.max(0, Math.round(Number(state[player]) || 0));
        const display = tool.querySelector(`[data-lp-display="${player}"]`);
        const bar = tool.querySelector(`[data-lp-bar="${player}"]`);
        if (display) display.textContent = formatLp(value);
        const startValue = player === 'a' ? state.startA : state.startB;
        const base = Math.max(1, Number(startValue) || 8000);
        if (bar) bar.style.width = `${Math.min(100, (value / base) * 100)}%`;
      });
      const nameA = tool.querySelector('[data-lp-name="a"]');
      const nameB = tool.querySelector('[data-lp-name="b"]');
      if (nameA && nameA.value !== state.nameA) nameA.value = state.nameA || 'Duelista 1';
      if (nameB && nameB.value !== state.nameB) nameB.value = state.nameB || 'Duelista 2';
      const startA = tool.querySelector('[data-lp-start="a"]');
      const startB = tool.querySelector('[data-lp-start="b"]');
      if (startA && startA.value !== String(state.startA)) startA.value = String(state.startA);
      if (startB && startB.value !== String(state.startB)) startB.value = String(state.startB);
      const timer = tool.querySelector('[data-lp-timer-display]');
      if (timer) timer.textContent = formatTimer(state.timer);
      const timerButton = tool.querySelector('[data-lp-timer-toggle]');
      if (timerButton) timerButton.innerHTML = state.running ? '<i class="bi bi-pause-fill"></i> Pausar' : '<i class="bi bi-play-fill"></i> Iniciar';
      const resetLabel = tool.querySelector('[data-lp-action="reset"] span');
      if (resetLabel) resetLabel.textContent = state.startA === state.startB ? formatLp(state.startA) : 'Reset';
      const turnNode = tool.querySelector('[data-lp-turn]');
      if (turnNode) turnNode.textContent = String(state.turn || 1);
      const coinNode = tool.querySelector('[data-lp-coin]');
      if (coinNode) coinNode.textContent = state.coin || 'Moneda';
      const diceNode = tool.querySelector('[data-lp-dice]');
      if (diceNode) diceNode.textContent = state.dice || '-';
      renderHistory();
      save();
    };
    const pushHistory = (player, text, previous) => {
      const label = player === 'a' ? (state.nameA || 'Duelista 1') : (state.nameB || 'Duelista 2');
      state.history.unshift({
        player: label,
        text,
        score: `${formatLp(previous)} > ${formatLp(state[player])}`,
        prevA: player === 'a' ? previous : state.a,
        prevB: player === 'b' ? previous : state.b
      });
      state.history = state.history.slice(0, 12);
    };
    const changeLp = (player, delta, label) => {
      if (!['a', 'b'].includes(player)) return;
      const previous = Math.max(0, Math.round(Number(state[player]) || 0));
      state[player] = Math.max(0, previous + delta);
      pushHistory(player, label || `${delta > 0 ? '+' : ''}${delta}`, previous);
      setStatus(`${player === 'a' ? state.nameA : state.nameB}: ${label || delta}`);
      render();
    };
    const setLp = (player, value, label) => {
      if (!['a', 'b'].includes(player)) return;
      const previous = Math.max(0, Math.round(Number(state[player]) || 0));
      state[player] = Math.max(0, Math.round(Number(value) || 0));
      pushHistory(player, label || `LP fijado en ${formatLp(state[player])}`, previous);
      setStatus(`${player === 'a' ? state.nameA : state.nameB}: ${formatLp(state[player])} LP`);
      render();
    };
    const halfLp = (player) => {
      if (!['a', 'b'].includes(player)) return;
      const previous = Math.max(0, Math.round(Number(state[player]) || 0));
      state[player] = Math.floor(previous / 2);
      pushHistory(player, 'Mitad de LP', previous);
      setStatus('LP dividido a la mitad');
      render();
    };
    const resetDuel = () => {
      state.a = Math.max(0, Math.round(Number(state.startA) || 8000));
      state.b = Math.max(0, Math.round(Number(state.startB) || 8000));
      state.timer = 0;
      state.running = false;
      timerStartedAt = 0;
      state.turn = 1;
      state.coin = '';
      state.dice = '';
      state.history = [];
      setStatus('Duelo reiniciado');
      render();
    };
    const undo = () => {
      const last = state.history.shift();
      if (!last) return;
      state.a = Number(last.prevA || state.a);
      state.b = Number(last.prevB || state.b);
      setStatus('Ultimo cambio revertido');
      render();
    };
    const stopTimerInterval = () => {
      if (timerTick) window.clearInterval(timerTick);
      timerTick = null;
    };
    const syncTimer = () => {
      if (!state.running) return;
      const now = Date.now();
      if (timerStartedAt) {
        state.timer += Math.floor((now - timerStartedAt) / 1000);
      }
      timerStartedAt = now;
      render();
    };
    const startTimer = () => {
      if (state.running) return;
      state.running = true;
      timerStartedAt = Date.now();
      stopTimerInterval();
      timerTick = window.setInterval(syncTimer, 1000);
      render();
    };
    const pauseTimer = () => {
      syncTimer();
      state.running = false;
      timerStartedAt = 0;
      stopTimerInterval();
      render();
    };

    tool.querySelectorAll('[data-lp-delta]').forEach((button) => {
      button.addEventListener('click', () => changeLp(button.dataset.lpTarget, Number(button.dataset.lpDelta || 0), button.textContent.trim()));
    });
    tool.querySelectorAll('[data-lp-custom-apply]').forEach((button) => {
      button.addEventListener('click', () => {
        const player = button.dataset.lpTarget;
        const input = tool.querySelector(`[data-lp-custom="${player}"]`);
        const amount = Math.max(0, Number(input?.value || 0));
        if (!amount) return;
        if (button.dataset.lpCustomApply === 'set') {
          setLp(player, amount, `LP fijado en ${formatLp(amount)}`);
        } else {
          const sign = button.dataset.lpCustomApply === 'add' ? 1 : -1;
          changeLp(player, amount * sign, `${sign > 0 ? '+' : '-'}${amount}`);
        }
        if (input) input.value = '';
      });
    });
    tool.querySelectorAll('[data-lp-action="half"]').forEach((button) => {
      button.addEventListener('click', () => halfLp(button.dataset.lpTarget));
    });
    const resetButton = tool.querySelector('[data-lp-action="reset"]');
    if (resetButton) resetButton.addEventListener('click', resetDuel);
    const fullResetButton = tool.querySelector('[data-lp-full-reset]');
    if (fullResetButton) fullResetButton.addEventListener('click', resetDuel);
    const undoButton = tool.querySelector('[data-lp-action="undo"]');
    if (undoButton) undoButton.addEventListener('click', undo);
    const swapButton = tool.querySelector('[data-lp-action="swap"]');
    if (swapButton) {
      swapButton.addEventListener('click', () => {
        [state.a, state.b] = [state.b, state.a];
        [state.startA, state.startB] = [state.startB, state.startA];
        [state.nameA, state.nameB] = [state.nameB, state.nameA];
        setStatus('Duelistas intercambiados');
        render();
      });
    }
    tool.querySelectorAll('[data-lp-action="zero"]').forEach((button) => {
      button.addEventListener('click', () => setLp(button.dataset.lpTarget, 0, 'LP en 0'));
    });
    tool.querySelectorAll('[data-lp-start-preset]').forEach((button) => {
      button.addEventListener('click', () => {
        const value = Math.max(0, Number(button.dataset.lpStartPreset || 8000));
        state.startA = value;
        state.startB = value;
        state.a = value;
        state.b = value;
        state.history = [];
        setStatus(`LP inicial ${formatLp(value)}`);
        render();
      });
    });
    const applyStartButton = tool.querySelector('[data-lp-apply-start]');
    if (applyStartButton) {
      applyStartButton.addEventListener('click', () => {
        const startA = Math.max(0, Number(tool.querySelector('[data-lp-start="a"]')?.value || 8000));
        const startB = Math.max(0, Number(tool.querySelector('[data-lp-start="b"]')?.value || 8000));
        state.startA = Math.round(startA);
        state.startB = Math.round(startB);
        state.a = state.startA;
        state.b = state.startB;
        state.history = [];
        setStatus('LP inicial aplicado');
        render();
      });
    }
    const turnButton = tool.querySelector('[data-lp-action="turn"]');
    if (turnButton) {
      turnButton.addEventListener('click', () => {
        state.turn = Math.max(1, Number(state.turn || 1) + 1);
        setStatus(`Turno ${state.turn}`);
        render();
      });
    }
    const coinButton = tool.querySelector('[data-lp-action="coin"]');
    if (coinButton) {
      coinButton.addEventListener('click', () => {
        state.coin = Math.random() >= 0.5 ? 'Cara' : 'Sello';
        setStatus(`Moneda: ${state.coin}`);
        render();
      });
    }
    const diceButton = tool.querySelector('[data-lp-action="dice"]');
    if (diceButton) {
      diceButton.addEventListener('click', () => {
        state.dice = String(Math.floor(Math.random() * 6) + 1);
        setStatus(`Dado: ${state.dice}`);
        render();
      });
    }
    tool.querySelectorAll('[data-lp-name]').forEach((input) => {
      input.addEventListener('input', () => {
        if (input.dataset.lpName === 'a') state.nameA = input.value || 'Duelista 1';
        if (input.dataset.lpName === 'b') state.nameB = input.value || 'Duelista 2';
        save();
      });
    });
    const timerToggle = tool.querySelector('[data-lp-timer-toggle]');
    if (timerToggle) timerToggle.addEventListener('click', () => (state.running ? pauseTimer() : startTimer()));
    const timerReset = tool.querySelector('[data-lp-timer-reset]');
    if (timerReset) {
      timerReset.addEventListener('click', () => {
        state.running = false;
        state.timer = 0;
        timerStartedAt = 0;
        stopTimerInterval();
        render();
      });
    }
    window.addEventListener('beforeunload', () => {
      if (state.running) pauseTimer();
    });
    render();
  }

  const savedParam = new URLSearchParams(window.location.search).get('saved') || '';
  const entityParams = new URLSearchParams(window.location.search);
  const requestedDeckId = entityParams.get('deck_id') || '';
  const requestedTeamId = entityParams.get('team_id') || '';
  if (requestedDeckId && adminDeckSelect) {
    adminDeckSelect.value = requestedDeckId;
    adminDeckSelect.dispatchEvent(new Event('change'));
    const requestedDeck = (data.allDecks || []).find((item) => String(item.id || '') === requestedDeckId);
    if (requestedDeck && deckSelect) deckSelect.value = requestedDeck.name || '';
  }
  if (requestedTeamId && adminTeamSelect) {
    adminTeamSelect.value = requestedTeamId;
    adminTeamSelect.dispatchEvent(new Event('change'));
  }
  const initialGestionModule = savedParam.startsWith('division') ? 'divisiones' : (localStorage.getItem('dml_gestion_module') || 'resumen');
  openGestionModule(initialGestionModule, { noRoute: true });

  let deferredInstallPrompt = null;
  let offlineFlushRunning = false;
  const updateOfflineQueueCount = () => {
    const count = readOfflineQueue().length;
    document.querySelectorAll('[data-offline-queue-count]').forEach((node) => { node.textContent = `${count} ${count === 1 ? 'cambio pendiente' : 'cambios pendientes'}`; });
  };
  const updateConnectivity = () => {
    const online = navigator.onLine;
    document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = online ? 'En linea. Los cambios pueden guardarse en servidor.' : 'Sin conexion. La captura quedara pendiente en este dispositivo.'; });
    document.querySelectorAll('[data-pwa-icon]').forEach((node) => { node.className = 'bi ' + (online ? 'bi-wifi' : 'bi-wifi-off'); });
    updateOfflineQueueCount();
  };
  window.addEventListener('beforeinstallprompt', (event) => { event.preventDefault(); deferredInstallPrompt = event; });
  document.querySelectorAll('[data-install-pwa]').forEach((button) => {
    button.addEventListener('click', async () => {
      if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
      } else {
        window.alert('La aplicacion ya puede estar instalada o el navegador mostrara la opcion Instalar en su menu.');
      }
    });
  });
  const offlineFormActions = new Set(['save_deck', 'save_deck_analysis_profile', 'save_matchup_analysis', 'save_team', 'save_competition', 'configure_competition', 'save_match_schedule', 'save_division', 'save_settings']);
  const offlineEntityBaseline = (action, payload) => {
    const source = action === 'save_deck' ? (data.allDecks || []).find((row) => String(row.id) === String(payload.deck_id || ''))
      : action === 'save_team' ? (data.teams || []).find((row) => String(row.id) === String(payload.team_id || ''))
      : ['save_competition', 'configure_competition'].includes(action) ? (data.competitions || []).find((row) => String(row.id) === String(payload.competition_id || ''))
      : null;
    return source?.updated_at || '';
  };
  document.addEventListener('submit', (event) => {
    if (navigator.onLine || !(event.target instanceof HTMLFormElement)) return;
    const form = event.target; const formData = new FormData(form); const action = String(formData.get('action') || '');
    if (!offlineFormActions.has(action)) return;
    const hasFile = Array.from(formData.values()).some((value) => value instanceof File && value.size > 0);
    event.preventDefault();
    if (hasFile) { window.alert('Este formulario contiene un archivo. Los datos no se perderan en pantalla, pero la imagen debe enviarse cuando vuelva la conexion.'); return; }
    const payload = {};
    formData.forEach((value, key) => {
      const text = String(value);
      if (Object.prototype.hasOwnProperty.call(payload, key)) payload[key] = Array.isArray(payload[key]) ? [...payload[key], text] : [payload[key], text];
      else payload[key] = text;
    });
    payload.offline_entity_updated_at = offlineEntityBaseline(action, payload);
    const requestId = makeRequestId(); const queue = readOfflineQueue();
    queue.push({ id: requestId, kind: 'form', action, payload, queuedAt: new Date().toISOString(), attempts: 0 });
    writeOfflineQueue(queue); updateOfflineQueueCount();
    window.alert(`Sin conexion. ${action.replaceAll('_', ' ')} quedo en la cola de este dispositivo.`);
  }, true);
  const flushOfflineResult = async () => {
    updateConnectivity();
    if (!navigator.onLine || offlineFlushRunning) return;
    const queue = readOfflineQueue();
    if (!queue.length) return;
    offlineFlushRunning = true;
    try {
      while (queue.length && navigator.onLine) {
        const queued = queue[0];
        const body = new URLSearchParams();
        if (queued.kind === 'form') {
          body.set('action', 'sync_offline_form');
          body.set('offline_request_id', queued.id || makeRequestId());
          body.set('offline_payload', JSON.stringify(queued.payload || {}));
        } else {
          (queued.payload || []).forEach(([key, value]) => body.append(key, value));
          if (!body.has('offline_request_id')) body.set('offline_request_id', queued.id || makeRequestId());
        }
        const response = await fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body.toString(), credentials: 'same-origin', redirect: 'follow' });
        const responseUrl = String(response.url || '');
        if (responseUrl.includes('saved=offline_conflicto')) {
          document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = 'Conflicto detectado: el registro cambio en otro dispositivo. Revisa antes de reemplazarlo.'; });
          throw new Error('Conflicto de sincronizacion.');
        }
        if (!response.ok || !responseUrl.includes('saved=')) throw new Error('El servidor no confirmo el guardado.');
        queue.shift();
        writeOfflineQueue(queue);
        updateOfflineQueueCount();
      }
      if (!queue.length) {
        localStorage.removeItem(OFFLINE_QUEUE_KEY);
        document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = 'Todos los cambios pendientes fueron sincronizados.'; });
      }
    } catch (error) {
      if (queue[0]) queue[0].attempts = Number(queue[0].attempts || 0) + 1;
      writeOfflineQueue(queue);
      document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = `Hay ${queue.length} cambios pendientes por sincronizar.`; });
    } finally {
      offlineFlushRunning = false;
      updateOfflineQueueCount();
    }
  };
  window.addEventListener('online', flushOfflineResult);
  window.addEventListener('offline', updateConnectivity);
  updateConnectivity();
  flushOfflineResult();
  if ('serviceWorker' in navigator) {
    const registerOffline = () => navigator.serviceWorker.register('./service-worker.js').catch(() => {});
    if (document.readyState === 'complete') registerOffline();
    else window.addEventListener('load', registerOffline, { once: true });
  }

  const showAppNotice = (message, tone = 'info') => {
    let host = document.querySelector('[data-app-notices]');
    if (!host) {
      host = document.createElement('div'); host.dataset.appNotices = ''; host.className = 'app-notices'; document.body.appendChild(host);
    }
    const notice = document.createElement('div'); notice.className = `app-notice is-${tone}`; notice.innerHTML = `<i class="bi ${tone === 'success' ? 'bi-check2-circle' : 'bi-bell'}"></i><span>${escapeHtml(message)}</span><button type="button" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>`;
    notice.querySelector('button')?.addEventListener('click', () => notice.remove()); host.appendChild(notice);
    window.setTimeout(() => notice.remove(), 8000);
  };
  const notifyUpcomingMatches = () => {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const threshold = Math.max(1, Number(settings.notification_minutes_before || 30));
    const now = Date.now();
    allMatchList().filter((match) => !['FINALIZADA', 'CANCELADA'].includes(String(match.estado || '').toUpperCase()) && match.scheduled_on && match.starts_at).forEach((match) => {
      const starts = new Date(`${match.scheduled_on}T${String(match.starts_at).slice(0, 5)}:00`).getTime();
      const minutes = Math.ceil((starts - now) / 60000);
      const key = `dml-notified-${match.id}-${match.scheduled_on}-${match.starts_at}`;
      if (minutes >= 0 && minutes <= threshold && !localStorage.getItem(key)) {
        const label = `Mesa ${match.mesa || '-'}: ${match.a || 'Deck A'} vs ${match.b || 'Deck B'}`;
        new Notification('Proxima partida', { body: `${label}. Comienza en ${minutes} min.`, icon: './assets/logo.png' });
        showAppNotice(`${label}. Comienza en ${minutes} min.`);
        localStorage.setItem(key, new Date().toISOString());
      }
    });
  };
  document.querySelectorAll('[data-enable-notifications]').forEach((button) => button.addEventListener('click', async () => {
    if (!('Notification' in window)) return showAppNotice('Este navegador no admite notificaciones.', 'warning');
    const permission = await Notification.requestPermission();
    button.innerHTML = `<i class="bi bi-bell${permission === 'granted' ? '-fill' : '-slash'}"></i> ${permission === 'granted' ? 'Avisos activos' : 'Avisos bloqueados'}`;
    if (permission === 'granted') notifyUpcomingMatches();
  }));
  notifyUpcomingMatches();
  window.setInterval(notifyUpcomingMatches, 60000);

  let liveRevision = Math.max(0, ...(data.audit || []).map((row) => Number(row.id || 0)));
  let livePolling = false;
  const pollLiveCenter = async () => {
    if (!navigator.onLine || livePolling || readOfflineQueue().length) return;
    livePolling = true;
    try {
      const response = await fetch('./?api=live', { cache: 'no-store', credentials: 'same-origin' });
      if (!response.ok) throw new Error('live');
      const payload = await response.json();
      if (Number(payload.revision || 0) <= liveRevision) return;
      liveRevision = Number(payload.revision || 0);
      const captureOpen = resultCaptureModalNode && !resultCaptureModalNode.hidden && resultCaptureModalNode.classList.contains('show');
      if (captureOpen) {
        document.querySelectorAll('[data-pwa-status]').forEach((node) => { node.textContent = 'Hay una actualizacion en servidor. Se aplicara al cerrar la captura actual.'; });
        return;
      }
      const byId = new Map((payload.matches || []).map((row) => [String(row.id), row]));
      allMatchList().forEach((match) => { const fresh = byId.get(String(match.id)); if (fresh) Object.assign(match, fresh); });
      const knownUnlockIds = new Set((data.medalUnlocks || []).map((row) => String(row.id)));
      const newUnlocks = (payload.medal_unlocks || []).filter((row) => !knownUnlockIds.has(String(row.id)));
      data.medalUnlocks = payload.medal_unlocks || data.medalUnlocks;
      newUnlocks.forEach((unlock) => showAppNotice(`Medalla desbloqueada: ${unlock.medal_name}`, 'success'));
      renderStats(); renderProfile();
      showAppNotice('Resultados y centro en vivo actualizados.', 'success');
    } catch {}
    finally { livePolling = false; }
  };
  window.setInterval(pollLiveCenter, Math.max(5, Number(settings.live_refresh_seconds || 15)) * 1000);

  initLifePointTool();
  fillDeckSelect();
  renderProfile();
  const requestedDeckCategory = entityParams.get('deck_section') || (entityParams.get('deck_view') === 'analysis' ? 'strategy' : '');
  const requestedDivision = entityParams.get('division') || '';
  const requestedDivisionCategory = entityParams.get('division_section') || '';
  const requestedStatsCategory = entityParams.get('stats_section') || '';
  openDeckProfileView('summary');
  renderStats();
  if (resultDivision) loadMatchOptions();
  routeTo(publicSeason ? 'estadisticas' : ((location.hash || '').replace('#', '') || localStorage.getItem('dml_screen') || 'dashboard'), 'replace');
  if (deckCategoryOrder.includes(requestedDeckCategory) && (location.hash || '').replace('#', '') === 'deck_ficha') {
    window.setTimeout(() => openDeckCategory(requestedDeckCategory, false), 0);
  }
  if (requestedDivision && divisionDetailPanels.some((panel) => panel.dataset.divisionDetail === requestedDivision)) {
    openDivisionDetail(requestedDivision);
  }
  if (divisionCategoryOrder.includes(requestedDivisionCategory) && (location.hash || '').replace('#', '') === 'divisiones') {
    window.setTimeout(() => openDivisionCategory(requestedDivision, requestedDivisionCategory, false), 0);
  }
  if (statsCategoryOrder.includes(requestedStatsCategory) && (location.hash || '').replace('#', '') === 'estadisticas') {
    window.setTimeout(() => openStatsTab(requestedStatsCategory, true, false), 0);
  }
  if (publicSeason) openStatsTab('summary');
  if (entityParams.get('checkin_match')) {
    routeTo('estadisticas');
    openStatsTab('operations');
    const checkinSelect = document.querySelector('[data-checkin-match]');
    if (checkinSelect && Array.from(checkinSelect.options).some((option) => option.value === entityParams.get('checkin_match'))) {
      checkinSelect.value = entityParams.get('checkin_match');
      checkinSelect.dispatchEvent(new Event('change'));
    }
    const checkinCode = document.querySelector('[data-checkin-code]');
    if (checkinCode && entityParams.get('checkin_code')) checkinCode.value = entityParams.get('checkin_code');
  }
  const persistedResultMatchId = localStorage.getItem('dml_result_capture_match_id') || '';
  if (persistedResultMatchId && allMatchList().some((match) => String(match.id || '') === String(persistedResultMatchId))) {
    const persistedRoute = localStorage.getItem('dml_result_capture_route') || 'calendario';
    if (screens.some((screen) => screen.dataset.screen === persistedRoute)) routeTo(persistedRoute);
    window.setTimeout(() => openResultCapture(persistedResultMatchId), 250);
  }
  window.YgoCupBracketNavigation?.init(openMatchSheet);
  if (!publicSeason) window.YgoDailyDuels?.init(data, allMatchList, openMatchSheet);
}());
