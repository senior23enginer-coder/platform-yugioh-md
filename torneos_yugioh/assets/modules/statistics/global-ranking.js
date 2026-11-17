(function (root) {
  'use strict';
  const constructed = new Set(['tcg', 'tcg_advanced', 'tcg_traditional', 'ocg']);
  const isConstructed = format => constructed.has(String(format || '').trim().toLowerCase());
  function build(data) {
    const metrics = (data.statisticsV8?.global || []).filter(row => isConstructed(row.format_id));
    const minimum = Number(data.statisticsV8?.policy?.minimum_matches) || 2;
    const seen = new Set();
    const rows = (data.allDecks || []).filter(deck => {
      if (!Number(deck.id) || seen.has(Number(deck.id)) || !(deck.format_codes || []).some(isConstructed)) return false;
      seen.add(Number(deck.id));
      return true;
    }).map(deck => {
      // Prefer the deck's declared format. Never add or double-count ratings across formats.
      const formats = (deck.format_codes || []).filter(isConstructed);
      const metric = formats.map(format => metrics.find(row => row.format_id === format && Number(row.deck_id) === Number(deck.id)))
        .find(Boolean) || formats.map(format => metrics.find(row => row.format_id === format && !row.deck_id && deck.team_id && Number(row.team_id) === Number(deck.team_id))).find(Boolean);
      const qualified = Boolean(metric && metric.state === 'CLASIFICADO' && Number(metric.J) >= minimum);
      return {...metric, deck_id: Number(deck.id), team_id: deck.team_id || metric?.team_id || null,
        format_id: metric?.format_id || formats[0], J: Number(metric?.J || 0),
        total: metric?.total ?? null, rating: metric?.rating ?? null,
        state: metric?.state || 'PROVISIONAL', qualified, position: null};
    });
    rows.sort((a, b) => Number(b.qualified) - Number(a.qualified) ||
      Number(b.total ?? -Infinity) - Number(a.total ?? -Infinity) ||
      Number(b.rating ?? -Infinity) - Number(a.rating ?? -Infinity) ||
      Number(b.championships || 0) - Number(a.championships || 0) ||
      Number(a.team_id || 0) - Number(b.team_id || 0) || a.deck_id - b.deck_id);
    let position = 0;
    for (const row of rows) if (row.qualified) row.position = ++position;
    return {rows, total: rows.length, classified: position};
  }
  root.YgoGlobalRanking = {build, isConstructed};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.YgoGlobalRanking;
})(typeof window === 'undefined' ? globalThis : window);
