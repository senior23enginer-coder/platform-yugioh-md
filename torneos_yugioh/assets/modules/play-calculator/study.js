/* Resolve the active study registry for every loaded deck, not the historical eleven-deck index. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"), require("./verified-rules.js"), require("./contract.js"));
  else root.YgoCalculatorStudy = factory(root.YgoPlayEngine, root.YgoVerifiedCalculatorRules, root.YgoCalculatorContract);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, Verified, Contract) {
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
    db.ruleImports = [];
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
      const deckActorCids = new Set();
      let identityError = null;
      const studyCards = study.cards === undefined ? [] : study.cards;
      if (!Array.isArray(studyCards))
        identityError = "Inventario de identidades del estudio inválido";
      for (const card of Array.isArray(studyCards) ? studyCards : []) {
        if (!card || typeof card !== "object" || Array.isArray(card) ||
            (card.source_aliases !== undefined && !Array.isArray(card.source_aliases)) ||
            (card.slots !== undefined && !Array.isArray(card.slots)) ||
            (card.sources !== undefined && !Array.isArray(card.sources))) {
          identityError ||= "Ficha de identidad del estudio inválida";
          continue;
        }
        const names = [
          card.name,
          card.identity?.official_name,
          ...(card.source_aliases || []),
        ]
          .filter(Boolean)
          .map(E.norm);
        for (const slot of card.slots || []) {
          if (!slot || typeof slot !== "object" || Array.isArray(slot)) {
            identityError ||= "Slot de identidad del estudio inválido";
            continue;
          }
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
          deckActorCids.add(card.cid);
          // A native slot and exact alias confirm identity; they do not invent monster metadata.
          if (!db.bindings[key] || db.bindings[key] === card.cid) {
            db.bindings[key] = card.cid;
            db.names ||= {};
            db.names[key] = native.name;
          }
        }
      }
      let imported = [];
      const verified = Verified?.forDeck(catalog.id) || [];
      if (study.calculator !== undefined) {
        const block = study.calculator;
        // Legacy studies without `schema` are validated through the same
        // calculator/1 boundary; they are not trusted just because they loaded.
        const packageData = block?.schema === undefined && block && Array.isArray(block.rules)
          ? { schema: Contract.SCHEMA, deck_id: Number(study.deck_id),
              format: study.format, rules: block.rules }
          : block;
        try {
          if (identityError) throw Error(identityError);
          if (!catalog.formats.includes(study.format))
            throw Error("Formato del estudio ajeno al catálogo nativo");
          // The active catalog omits Side Deck rows. Accept a Side-only
          // actor solely when its definition is already independently
          // verified; it cannot execute until the native Main includes it.
          const accreditedActors = new Set([
            ...deckActorCids,
            ...verified.map(rule => rule.actor.cid).filter(Number.isInteger),
          ]);
          Contract.validate(packageData, { deckId: catalog.id, format: study.format,
            actorCids: [...accreditedActors] });
          const protectedIds = new Set(verified.map(rule => rule.id));
          // A portable rule may replace a built-in fallback only when the
          // complete verified definition matches byte-for-byte as JSON.
          // A matching ID with different guards, costs or outcomes is rejected.
          const conflict = packageData.rules.find(rule =>
            protectedIds.has(rule.id) &&
            !verified.some(fallback => JSON.stringify(fallback) === JSON.stringify(rule)));
          if (conflict)
            throw Error("Regla importada colisiona con respaldo verificado: " + conflict.id);
          imported = packageData.rules;
          db.ruleImports.push({ deckId: catalog.id, status: "accepted", count: imported.length,
            schema: Contract.SCHEMA, legacy: block?.schema === undefined });
        } catch (error) {
          db.ruleImports.push({ deckId: catalog.id, status: "rejected", count: 0,
            message: error.message });
        }
      }
      for (const rule of imported) db.rules.push(E.clone(rule));
      // An effect ID can have multiple distinct legal target/zone variants.
      // Importing one verified variant must not erase its sibling procedures.
      // Deduplicate only the exact imported definition, not its shared effect ID.
      const importedDefinitions = new Set(imported.map(rule => JSON.stringify(rule)));
      for (const rule of verified)
        if (!importedDefinitions.has(JSON.stringify(rule))) db.rules.push(E.clone(rule));
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
        // Editorial steps are *not* executable transitions merely because
        // the route is current. Preserve legacy reading eligibility separately.
        executable: !r.historical,
        calculatorVerified: false,
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
