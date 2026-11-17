(function (root) {
  "use strict";
  const sections = {
    main: "Main Deck",
    extra: "Extra Deck",
    side: "Side Deck",
    reserve: "Reserva",
  };
  const clean = (v) =>
    String(v ?? "")
      .replace(/[\r\n\t]+/g, " ")
      .trim();
  const key = (v) => clean(v).normalize("NFC").toLowerCase();
  function pair(name) {
    const parts = clean(name).split(" / ");
    // The active editorial delivery documents bilingual names as ES / EN.
    return parts.length === 2 && parts.every(Boolean)
      ? { es: parts[0], en: parts[1] }
      : null;
  }
  function names(row, record) {
    const stored = clean(row.card_name);
    let result = pair(stored);
    if (!result) {
      const matches = (record?.cards || [])
        .map((c) => pair(c.name))
        .filter((p) => p && [p.es, p.en].some((n) => key(n) === key(stored)));
      const unique = [
        ...new Map(matches.map((p) => [p.es + "|" + p.en, p])).values(),
      ];
      if (unique.length === 1) result = unique[0];
    }
    return {
      stored,
      en: clean(
        row.card_name_en ||
          row.name_en ||
          result?.en ||
          (Number(row.ygoprodeck_id) > 0 && !/[^\u0000-\u024f]/.test(stored)
            ? stored
            : ""),
      ),
      es: clean(row.card_name_es || row.name_es || result?.es),
    };
  }
  function filename(name) {
    return (
      (clean(name)
        .replace(/[<>:"/\\|?*\u0000-\u001f]/g, "_")
        .replace(/[. ]+$/g, "") || "Deck") + ".txt"
    );
  }
  function build(data, decks, records = new Map()) {
    let missing = 0;
    const output = [
      "STATION X — LISTADO DE DECKS",
      `Decks: ${decks.length}`,
      "Cantidades de las listas guardadas. Reserva y Side Deck se mantienen separados.",
      "Los nombres no documentados se indican expresamente; no se traducen automáticamente.",
      "",
    ];
    for (const deck of decks) {
      const rows = (data.cards || []).filter(
        (r) => String(r.deck_id) === String(deck.id),
      );
      const formats = Array.isArray(deck.format_codes)
        ? deck.format_codes
        : [deck.format || deck.format_code].filter(Boolean);
      output.push(
        "=".repeat(64),
        `DECK: ${clean(deck.name)}`,
        `ID: ${clean(deck.id)}`,
        `FORMATO: ${formats.join(", ") || "Sin formato registrado"}`,
        "",
      );
      const groups = Object.keys(sections);
      for (const row of rows) {
        const section = String(row.section || "main").toLowerCase();
        if (!groups.includes(section)) groups.push(section);
      }
      for (const section of groups) {
        const cards = rows.filter(
          (r) => String(r.section || "main").toLowerCase() === section,
        );
        output.push(
          `${sections[section] || clean(section)} — ${cards.reduce((n, r) => n + Number(r.quantity || 0), 0)} copias`,
        );
        if (!cards.length) output.push("Sin cartas registradas.");
        for (const row of cards) {
          const n = names(row, records.get(Number(deck.id)));
          if (!n.en || !n.es) missing++;
          output.push(
            `${Number(row.quantity || 0)}x | EN: ${n.en || "No documentado"} | ES: ${n.es || "No documentado"}`,
            `    Nombre guardado: ${n.stored || "Sin nombre"}`,
          );
        }
        output.push("");
      }
    }
    output.push(`Filas con algún nombre pendiente de documentar: ${missing}`);
    return { text: "\ufeff" + output.join("\r\n") + "\r\n", missing };
  }
  const api = { build, names, filename };
  if (typeof module === "object" && module.exports) module.exports = api;
  if (!root.document) return;
  root.YgoDeckExport = api;
  root.document.addEventListener("click", async (event) => {
    const button = event.target.closest(
      "[data-deck-download], [data-deck-download-all]",
    );
    if (!button || button.disabled) return;
    event.preventDefault();
    event.stopPropagation();
    const data = root.DML_DATA || {};
    const all = button.hasAttribute("data-deck-download-all");
    const decks = (data.allDecks || []).filter(
      (d) => all || String(d.id) === button.dataset.deckDownload,
    );
    const status = root.document.querySelector("[data-deck-download-status]");
    const label = button.innerHTML;
    button.disabled = true;
    button.textContent = "Preparando…";
    try {
      if (!decks.length)
        throw Error("No hay decks disponibles para descargar.");
      const records = new Map();
      let unavailable = 0;
      // Documentation only enriches names; never quantities or section ownership.
      await Promise.all(
        decks.map(async (d) => {
          try {
            const record = await root.YgoDeckDeliveries?.load(d.id);
            if (record) records.set(Number(d.id), record);
          } catch (_) {
            unavailable++;
          }
        }),
      );
      const result = build(data, decks, records);
      const url = root.URL.createObjectURL(
        new root.Blob([result.text], { type: "text/plain;charset=utf-8" }),
      );
      const link = root.document.createElement("a");
      link.href = url;
      link.download = all
        ? "Station_X_Todos_los_decks.txt"
        : filename(decks[0].name);
      root.document.body.append(link);
      link.click();
      link.remove();
      root.setTimeout(() => root.URL.revokeObjectURL(url), 30000);
      if (status)
        status.textContent = `TXT preparado: ${decks.length} deck(s). ${result.missing} filas con algún nombre no documentado.${unavailable ? " Algunas fuentes de nombres no estuvieron disponibles." : ""}`;
    } catch (error) {
      if (status) status.textContent = error.message;
    } finally {
      button.disabled = false;
      button.innerHTML = label;
    }
  });
})(typeof window === "object" ? window : globalThis);
