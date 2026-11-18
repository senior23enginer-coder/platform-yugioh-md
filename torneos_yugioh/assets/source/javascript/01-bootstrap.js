/**
 * bootstrap: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
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

  function routeTo(route) {
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
    if (location.hash !== `#${target}`) history.replaceState(null, '', `#${target}`);
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
  window.addEventListener('hashchange', () => {
    routeTo((location.hash || '').replace('#', '') || 'dashboard');
  });

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

