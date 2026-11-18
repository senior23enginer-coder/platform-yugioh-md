/**
 * division-editor: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
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

