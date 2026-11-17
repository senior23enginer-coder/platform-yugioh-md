(function (root) {
  "use strict";
  const norm = (v) =>
    String(v ?? "")
      .normalize("NFD")
      .replace(/\p{Diacritic}/gu, "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim();
  const zones = {
    main: "Main Deck",
    extra: "Extra Deck",
    side: "Side Deck",
    reserve: "Reserva",
    reserva: "Reserva",
  };
  function catalog(data, studies = []) {
    const profiles = new Map();
    for (const study of studies)
      for (const card of study.cards || [])
        for (const slot of card.slots || [])
          profiles.set(String(slot.card_id), { ...card, format: study.format });
    const groups = new Map(),
      decks = new Map((data.allDecks || []).map((d) => [String(d.id), d]));
    for (const row of data.cards || []) {
      const deck = decks.get(String(row.deck_id));
      if (!deck) continue;
      const p = profiles.get(String(row.id)),
        provider = Number(row.ygoprodeck_id) || 0;
      // Konami CID and provider ID belong to different namespaces.
      const format = p?.format || (deck.format_codes || []).join(",");
      const key = provider
        ? "api:" + provider
        : p?.cid
          ? "cid:" + format + ":" + p.cid
          : "name:" + format + ":" + norm(row.card_name);
      let c = groups.get(key);
      if (!c) {
        c = {
          key,
          id: provider || null,
          cid: p?.cid || null,
          name: p?.name || row.card_name,
          names: [],
          image:
            p?.full_image ||
            p?.image ||
            (provider
              ? "./?api=deck_builder&op=image&size=full&local=1&id=" + provider
              : ""),
          // Editorial summaries and tactical advice are not the card's printed text.
          description: "",
          format,
          usages: [],
        };
        groups.set(key, c);
      }
      c.names = [
        ...new Set([...c.names, row.card_name, p?.name].filter(Boolean)),
      ];
      c.usages.push({
        deck_id: deck.id,
        deck: deck.name,
        team: deck.team || "",
        format: (deck.format_codes || []).join(" / "),
        section: String(row.section || "main").toLowerCase(),
        quantity: Number(row.quantity) || 0,
        role: row.role || "",
        notes: row.notes || "",
      });
    }
    return [...groups.values()].sort((a, b) =>
      a.name.localeCompare(b.name, "es"),
    );
  }
  function search(cards, query) {
    const words = norm(query).split(" ").filter(Boolean);
    return cards.filter((c) =>
      words.every((w) =>
        norm(
          [
            ...c.names,
            c.name,
            c.id,
            c.cid,
            ...c.usages.map((u) => u.role),
          ].join(" "),
        ).includes(w),
      ),
    );
  }
  function usages(card, local) {
    if (card.usages) return card.usages;
    // Only an explicit provider ID proves an API-to-local match. Never guess by artwork or archetype.
    return local
      .filter((c) => c.id && Number(c.id) === Number(card.id))
      .flatMap((c) => c.usages);
  }
  const api = { norm, zones, catalog, search, usages };
  root.YgoCardInspector = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window === "undefined" ? globalThis : window);
