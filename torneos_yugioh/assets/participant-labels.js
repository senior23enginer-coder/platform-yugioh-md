(function () {
  'use strict';
  const contexts = new WeakMap(), originals = new WeakMap();
  function participant(match, side) {
    const key = side.toLowerCase(), decks = window.DML_DATA?.allDecks || [];
    const deck = decks.find(d => String(d.id) === String(match['deck_' + key + '_id']));
    const name = match[key] || deck?.name || '';
    const team = match['team_' + key] || deck?.team || '';
    const names = [...new Set([team, name].filter(Boolean))].join(' / ');
    return names ? `${names} (Lado ${side})` : `Lado ${side} — Participante por definir`;
  }
  function translate(text, match) {
    if (!/\b[AB]\b/.test(text)) return text;
    // Existing participant headings already carry their names; only clarify their side suffix.
    const names = ['a', 'b'].flatMap(key => [match[key], match['team_' + key]]).filter(Boolean);
    if (names.some(name => text.includes(name))) return text.replace(/\(([AB])\)/g, '(Lado $1)');
    return text.replace(/\b(?:[Ll]ado |[Ee]quipo )?([AB])\b/g, (_, side) => participant(match, side));
  }
  function refresh(root) {
    const context = contexts.get(root); if (!context) return;
    context.observer.disconnect();
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      const node = walker.currentNode, parent = node.parentElement;
      if (!parent || parent.closest('input,textarea,script,style,pre,code,[contenteditable],[data-raw-text]')) continue;
      const previous = originals.get(node);
      const original = previous && node.data === previous.rendered ? previous.original : node.data;
      const rendered = translate(original, context.match);
      if (parent.tagName === 'OPTION' && !parent.hasAttribute('value')) parent.setAttribute('value', parent.value);
      if (node.data !== rendered) node.data = rendered;
      originals.set(node, { original, rendered });
    }
    context.observer.observe(root, { subtree: true, childList: true, characterData: true });
  }
  function set(root, match) {
    if (!root || !match) return;
    let context = contexts.get(root);
    if (!context) {
      context = { match, queued: false, observer: new MutationObserver(() => {
        if (context.queued) return;
        context.queued = true;
        requestAnimationFrame(() => { context.queued = false; refresh(root); });
      }) };
      contexts.set(root, context);
    }
    context.match = match; refresh(root);
  }
  window.StationXParticipants = { set, participant };
})();
