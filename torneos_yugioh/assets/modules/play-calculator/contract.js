/* Strict, deck-independent boundary for calculator/1 data. No evaluation. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./rules.js"));
  else root.YgoCalculatorContract = factory(root.YgoPlayRules);
})(typeof globalThis !== "undefined" ? globalThis : this, function (Rules) {
  "use strict";
  const SCHEMA = "station-x-calculator/1";
  const FORMATS = ["tcg_advanced", "goat", "rush_duel"];
  function assert(ok, message) { if (!ok) throw Error(message); }
  function validate(value, context = {}) {
    assert(value && typeof value === "object" && !Array.isArray(value), "Contrato de calculadora inválido.");
    assert(value.schema === SCHEMA, "Versión del contrato no soportada.");
    assert(Number.isInteger(value.deck_id) && value.deck_id > 0, "ID de deck inválido.");
    assert(FORMATS.includes(value.format), "Formato de calculadora inválido.");
    assert(Object.keys(value).every(k => ["schema", "deck_id", "format", "rules"].includes(k)),
      "Campo raíz desconocido en el contrato.");
    assert(Array.isArray(value.rules) && value.rules.length <= 1000, "Listado de reglas inválido.");
    if (context.deckId !== undefined)
      assert(value.deck_id === context.deckId, "Contrato asociado a otro deck.");
    if (context.format !== undefined)
      assert(value.format === context.format, "Contrato asociado a otro formato.");
    const known = context.actorCids === undefined ? null : new Set(context.actorCids);
    const seen = new Set();
    for (const rule of value.rules) {
      assert(rule.deckId === value.deck_id && rule.format === value.format,
        "Regla asociada a otro deck o formato.");
      Rules.validate(rule);
      assert(!seen.has(rule.id), "ID de regla duplicado: " + rule.id);
      seen.add(rule.id);
      if (known && rule.actor.cid !== undefined)
        assert(known.has(rule.actor.cid), "Actor sin identidad acreditada en el deck: " + rule.id);
    }
    return { schema: SCHEMA, deckId: value.deck_id, format: value.format,
      rules: value.rules.length, ids: [...seen] };
  }
  return { SCHEMA, FORMATS, validate };
});
