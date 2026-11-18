/* Resolve the active study registry for every loaded deck, not the historical eleven-deck index. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"), require("./verified-rules.js"));
  else root.YgoCalculatorStudy = factory(root.YgoPlayEngine, root.YgoVerifiedCalculatorRules);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, Verified) {
  "use strict";
  const requests = new Map();
  async function json(url) {
    const r = await fetch(url, { cache: "no-cache" });
    if (!r.ok) throw Error("No se pudo cargar el estudio del deck");
    return r.json();
  }
  async function load(id) {
    if (!requests.has(id))
      requests.set(
        id,
        (async () => {
          const index = await json(
            "./assets/editorial/strategy-content-state.json",
          );
          const entry = index.checked_studies?.[String(id)];
          if (!entry) return null;
          if (
            !/^\.\/assets\/editorial\/[A-Za-z0-9_/-]+\.json(?:\?[^#]*)?$/.test(
              entry.path,
            )
          )
            throw Error("Ruta de estudio no local");
          const study = await json(entry.path);
          if (Number(study.deck_id) !== Number(id))
            throw Error("Estudio asociado a otro deck");
          return study;
        })().catch((e) => {
          requests.delete(id);
          throw e;
        }),
      );
    return requests.get(id);
  }
  function bind(base, entries) {
    const db = E.clone(base);
    db.rules = [];
    db.identities = {};
    db.studyCoverage = [];
    for (const { catalog, study } of entries.filter((e) => e.catalog)) {
      db.studyCoverage.push({
        id: catalog.id,
        name: catalog.name,
        cards: catalog.cards.length,
        routes: study?.routes?.length || 0,
        study: !!study,
      });
      if (!study || Number(study.deck_id) !== catalog.id) continue;
      for (const c of catalog.cards) {
        delete db.bindings[c.key];
        delete db.names?.[c.key];
      }
      for (const card of study.cards || []) {
        const names = [
          card.name,
          card.identity?.official_name,
          ...(card.source_aliases || []),
        ]
          .filter(Boolean)
          .map(E.norm);
        for (const slot of card.slots || []) {
          const key = catalog.id + ":" + Number(slot.card_id),
            native = catalog.cards.find((c) => c.key === key);
          if (
            !native ||
            !Number.isInteger(card.cid) ||
            ![native.name, ...native.name.split(/\s+\/\s+/)].some((n) =>
              names.includes(E.norm(n)),
            )
          )
            continue;
          db.identities[key] = {
            cid: card.cid,
            name: native.name,
            source:
              card.identity?.source ||
              (card.sources || []).find((v) => typeof v === "string") ||
              "",
            format: study.format,
          };
          // A native slot and exact alias confirm identity; they do not invent monster metadata.
          if (!db.bindings[key] || db.bindings[key] === card.cid) {
            db.bindings[key] = card.cid;
            db.names ||= {};
            db.names[key] = native.name;
          }
        }
      }
      for (const rule of study.calculator?.rules || [])
        if (rule.deckId === catalog.id) db.rules.push(E.clone(rule));
      for (const rule of Verified?.forDeck(catalog.id) || [])
        if (!(study.calculator?.rules || []).some((r) => r.id === rule.id))
          db.rules.push(E.clone(rule));
    }
    return db;
  }
  function compile(study, catalog) {
    if (!study || Number(study.deck_id) !== catalog.id) return [];
    return (study.routes || []).map((r) => {
      const hand = (r.hand_photos || []).filter(
        (p) => E.norm(p.label) === "en mano",
      );
      return {
        id: r.id,
        title: r.title || r.name,
        conditions: r.opening || "",
        warning: r.notice || "",
        sourceStatus:
          r.editorial_status ||
          "Ruta documental; condiciones pendientes de comprobar",
        executable: !r.historical,
        steps: (r.steps || []).map((p) =>
          [p.action, p.detail].filter(Boolean).join(" ? "),
        ),
        result: r.result || "",
        requires: hand.map((p) => ({
          name: p.name,
          keys: catalog.cards
            .filter((c) =>
              [c.name, ...c.name.split(/\s+\/\s+/)]
                .map(E.norm)
                .includes(E.norm(p.name)),
            )
            .map((c) => c.key),
        })),
        normal: false,
        resources: [],
        documentedHand: hand.length > 0,
        binding: catalog.signature,
      };
    });
  }
  return { load, bind, compile };
});
