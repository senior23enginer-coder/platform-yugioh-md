/**
 * navigation: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
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

