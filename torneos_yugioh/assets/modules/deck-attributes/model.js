/** Pure world-selection and directory rules. Operations return new selections; no DOM or HTTP. */
(function (root, factory) {
  const api = factory();
  if (typeof module === "object" && module.exports) module.exports = api;
  else root.YgoDeckAttributeModel = api;
})(typeof globalThis === "undefined" ? window : globalThis, function () {
  "use strict";
  const catalog = {
    dark: "OSCURIDAD",
    earth: "TIERRA",
    light: "LUZ",
    water: "AGUA",
    wind: "VIENTO",
    fire: "FUEGO",
  };
  const norm = (s) =>
    String(s || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  function validate(list, empty = false) {
    if (
      !Array.isArray(list) ||
      list.length > 3 ||
      (!empty && !list.length) ||
      new Set(list).size !== list.length ||
      list.some((c) => !Object.hasOwn(catalog, c))
    )
      throw Error("Selecciona de uno a tres mundos diferentes.");
    return [...list];
  }
  function add(list, code) {
    return validate([...list, code]);
  }
  function remove(list, code) {
    const next = list.filter((c) => c !== code);
    if (!next.length)
      throw Error(
        "El deck debe conservar al menos un mundo. Añade otro antes de quitar el último.",
      );
    return validate(next);
  }
  /** Promotion changes order only; it cannot add an unselected world. */
  function primary(list, code) {
    if (!list.includes(code))
      throw Error("El principal debe estar entre los seleccionados.");
    return validate([code, ...list.filter((c) => c !== code)]);
  }
  /** Project native deck identities with current assignments without mutating either source. */
  function rows(decks, records) {
    const byId = new Map(records.map((r) => [String(r.deck_id), r]));
    return decks.map((d) => ({
      ...d,
      attributeRecord: byId.get(String(d.id)) || {
        deck_id: d.id,
        attributes: [],
        primary: null,
        revision: 0,
        updated_at: null,
      },
    }));
  }
  function filter(rows, f = {}) {
    return rows.filter((d) => {
      const a = d.attributeRecord.attributes;
      const words = norm(f.search).split(/\s+/).filter(Boolean),
        hay = norm([d.name, d.team, ...a.map((c) => catalog[c])].join(" "));
      return (
        words.every((w) => hay.includes(w)) &&
        (!f.primary || a[0] === f.primary) &&
        (!f.any || a.includes(f.any)) &&
        (!f.format || (d.format_codes || []).includes(f.format)) &&
        (!f.state || (f.state === "assigned" ? a.length > 0 : a.length === 0))
      );
    });
  }
  function counts(rows) {
    const result = {
      ...Object.fromEntries(Object.keys(catalog).map((c) => [c, 0])),
      empty: 0,
    };
    rows.forEach((r) => {
      result[r.attributeRecord.attributes[0] || "empty"]++;
    });
    return result;
  }
  function isTcg(deck) {
    return (deck.format_codes || []).some((c) =>
      ["tcg_advanced", "tcg_traditional"].includes(c),
    );
  }
  function worlds(rows) {
    return Object.entries(catalog).map(([code, name]) => ({
      code,
      name,
      members: rows.filter((d) => d.attributeRecord.attributes.includes(code)),
      primary: rows.filter((d) => d.attributeRecord.attributes[0] === code)
        .length,
    }));
  }
  return {
    catalog,
    validate,
    add,
    remove,
    primary,
    rows,
    filter,
    counts,
    isTcg,
    worlds,
  };
});
