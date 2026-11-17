(function (root) {
  'use strict';
  function render({modal, match, decks, competitions, onDeck, onEvent}) {
    let navigating = false;
    function navigate(action) {
      if (navigating) return;
      navigating = true;
      const instance = root.bootstrap?.Modal.getOrCreateInstance(modal);
      if (!instance || (!modal.classList.contains('show') && modal.getAttribute('aria-modal') !== 'true')) {
        action(); return;
      }
      modal.addEventListener('hidden.bs.modal', action, {once:true});
      if (instance._isTransitioning && instance._isShown) {
        modal.addEventListener('shown.bs.modal', () => instance.hide(), {once:true});
      } else instance.hide();
    }
    function link(label, action, kind, id) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'match-sheet-entity-link';
      button.textContent = label;
      button.dataset.matchSheetEntity = kind;
      button.dataset.entityId = String(id);
      button.setAttribute('aria-label', 'Abrir ficha: ' + label);
      button.title = 'Ver ficha';
      button.addEventListener('click', () => navigate(action));
      return button;
    }
    for (const side of ['a','b']) {
      const target = modal.querySelector('[data-match-sheet-' + side + ']');
      if (!target) continue;
      const extra = match['team_' + side + '_extra'];
      const names = [match[side], ...(Array.isArray(extra) ? extra : [])].filter(Boolean);
      const resolved = names.map((name,index) => {
        const id = index === 0 ? match['deck_' + side + '_id'] : null;
        if (id) return decks.find(deck => String(deck.id) === String(id));
        const candidates = decks.filter(deck => deck.name === name);
        return candidates.length === 1 ? candidates[0] : null;
      });
      target.replaceChildren();
      names.forEach((name,index) => {
        if (index) target.append(document.createTextNode(' + '));
        const deck = resolved[index];
        target.append(deck ? link(deck.name, () => onDeck(deck.id), 'deck', deck.id) : document.createTextNode(name));
      });
      target.closest('article')?.classList.toggle('has-deck-link', names.length === 1 && Boolean(resolved[0]));
    }
    const competition = competitions.find(event => String(event.id) === String(match.competition_id));
    const eventTarget = modal.querySelector('[data-match-sheet-identity] .calendar-event-identity dd');
    if (competition && eventTarget) {
      eventTarget.replaceChildren(link(competition.name, () => onEvent(competition.id), 'event', competition.id));
    }
  }
  root.YgoMatchSheetLinks = {render};
})(window);
