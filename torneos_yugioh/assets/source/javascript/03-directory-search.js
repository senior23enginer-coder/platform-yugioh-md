/**
 * directory-search: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
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

