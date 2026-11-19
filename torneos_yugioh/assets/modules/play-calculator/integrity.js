/* Validate calculator-specific scenario metadata beyond the board engine. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorIntegrity = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const plain = v => v && typeof v === "object" && !Array.isArray(v);
  const validTurn = (v, current) => Number.isInteger(v) && v >= 1 && v <= current;
  function validate(state) {
    if (!plain(state) || !Number.isInteger(state.turn) || state.turn < 1)
      throw Error("Escenario de calculadora incorrecto");
    if (state._ruleFlags !== undefined) {
      if (!plain(state._ruleFlags) || Object.values(state._ruleFlags).some(v => typeof v !== "boolean"))
        throw Error("Banderas de restricción inválidas");
    }
    if (state._ruleFlagTurns !== undefined) {
      if (!plain(state._ruleFlagTurns) || Object.entries(state._ruleFlagTurns).some(([k,v]) =>
        !(k in (state._ruleFlags || {})) || !validTurn(v, state.turn)))
        throw Error("Caducidad de restricción inválida");
    }
    if (state._ruleUseTurns !== undefined && (!plain(state._ruleUseTurns) ||
      Object.entries(state._ruleUseTurns).some(([k,v]) => !/^rule:[^\s]{1,120}$/.test(k) || !validTurn(v, state.turn))))
      throw Error("Historial de uso de efectos inválido");
    if (state._ruleEvents !== undefined && (!Array.isArray(state._ruleEvents) ||
      state._ruleEvents.length > 1000 || state._ruleEvents.some(e => !plain(e) ||
        typeof e.kind !== "string" || e.kind.length < 1 || e.kind.length > 80 ||
        (e.turn !== undefined && !validTurn(e.turn, state.turn)) ||
        (e.uid !== undefined && (!Number.isInteger(e.uid) || e.uid < 1)))))
      throw Error("Historial causal de eventos inválido");
    if (state._rulePhaseActions !== undefined) {
      const p = state._rulePhaseActions;
      if (!plain(p) || !validTurn(p.turn, state.turn) ||
        !["draw", "standby", "main1", "battle", "main2", "end"].includes(p.phase) ||
        !["self", "opponent"].includes(p.side) ||
        !Number.isInteger(p.count) || p.count < 0 || p.count > 10000)
        throw Error("Conteo de acciones por fase inválido");
    }
    return state;
  }
  return Object.freeze({ version: 1, validate });
});
