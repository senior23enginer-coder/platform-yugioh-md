/**
 * deck-categories: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
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

