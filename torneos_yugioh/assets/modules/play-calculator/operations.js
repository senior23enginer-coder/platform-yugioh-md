/* Versioned, deck-independent vocabulary for declarative calculator effects. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorOperations = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const definitions = [
    ["shuffle", "both", "restricted"],
    ["link", "both", "restricted"],
    ["xyz", "both", "generic"],
    ["xyzOverlay", "both", "restricted"],
    ["fusion", "both", "restricted"],
    ["destroy", "both", "generic"],
    ["tribute", "costs", "generic"],
    ["changeControl", "both", "generic"],
    ["equip", "both", "restricted"],
    ["counter", "both", "restricted"],
    ["banishExtraFaceDown", "both", "restricted"],
    ["move", "both", "generic"],
    ["take", "both", "generic"],
    ["payLP", "both", "generic"],
    ["damage", "both", "generic"],
    ["recoverLP", "both", "generic"],
    ["negate", "both", "generic"],
    ["position", "both", "generic"],
    ["flag", "both", "generic"],
    ["normal", "both", "generic"],
    ["summon", "resolve", "generic"],
  ];
  const registry = Object.freeze(Object.fromEntries(definitions.map(([id, phase, coverage]) =>
    [id, Object.freeze({ id, phase, coverage })])));
  function list() { return Object.values(registry); }
  function describe(id) { return Object.prototype.hasOwnProperty.call(registry, id) ? registry[id] : null; }
  function assertSupported(op, phase) {
    const entry = op && describe(op.op);
    if (!entry) throw Error("Operacion no implementada: " + (op && op.op));
    if (phase !== "costs" && phase !== "resolve") throw Error("Bloque de operacion incorrecto");
    if (entry.phase !== "both" && entry.phase !== phase)
      throw Error(entry.id === "tribute" && phase === "resolve"
        ? "Sacrificar como coste exige el bloque costs; tribute no está permitido en resolve"
        : "Operacion " + entry.id + " no permitida en " + phase);
    return entry;
  }
  return Object.freeze({ version: 1, registry, list, describe, assertSupported });
});
