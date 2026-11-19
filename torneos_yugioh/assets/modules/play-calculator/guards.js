/* Declarative guard vocabulary: capabilities, not individual card rulings. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorGuards = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const definitions = [
    ["count", "generic"],
    ["distinctCount", "generic"],
    ["onlyCids", "generic"],
    ["event", "restricted"],
    ["phaseStart", "generic"],
    ["extraSummonHistory", "restricted"],
    ["inventoryTarget", "generic"],
    ["state", "generic"],
    ["flag", "generic"],
  ];
  const registry = Object.freeze(Object.fromEntries(definitions.map(([kind, coverage]) =>
    [kind, Object.freeze({ kind, coverage })])));
  function list() { return Object.values(registry); }
  function describe(kind) { return Object.prototype.hasOwnProperty.call(registry, kind) ? registry[kind] : null; }
  function assertSupported(guard) {
    const entry = guard && describe(guard.kind);
    if (!entry) throw Error("Condicion no implementada");
    return entry;
  }
  return Object.freeze({ version: 1, registry, list, describe, assertSupported });
});
