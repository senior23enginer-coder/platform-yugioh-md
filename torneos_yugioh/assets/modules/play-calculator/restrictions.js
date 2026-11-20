/* Deck-independent rule locks; duration is explicit and never inferred. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorRestrictions = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const names = Object.freeze(["specialBlocked", "noExtra", "xyzOnly",
    "graveyardBlocked", "effectsNegated", "noDeckToHand", "banishBlocked", "noMoreSummons", "machineSynchroOnly", "effectMonsterSummonBlocked"]);
  const turnScoped = Object.freeze(["noMoreSummons", "machineSynchroOnly", "banishBlocked", "noDeckToHand"]);
  function effective(state, options = {}) {
    const flags = { ...options, ...(state._ruleFlags || {}) };
    for (const key of turnScoped)
      if (state._ruleFlags?.[key] && state._ruleFlagTurns?.[key] !== state.turn)
        delete flags[key];
    // Heat Wave outlives the opponent's intervening turn, and expires at
    // the start of its controller's next Draw Phase (two duel turns later).
    if (state._ruleFlags?.effectMonsterSummonBlocked &&
        (state.turn >= (state._ruleFlagTurns?.effectMonsterSummonBlocked ?? Infinity) + 2))
      delete flags.effectMonsterSummonBlocked;
    return flags;
  }
  function apply(state, flag, value, duration = "persistent") {
    if (!names.includes(flag) || typeof value !== "boolean") throw Error("Restriccion incorrecta");
    if (!["persistent", "turn", "next-own-draw"].includes(duration)) throw Error("Duracion incorrecta");
    if (turnScoped.includes(flag) && duration !== "turn") throw Error("Duracion de bloqueo incorrecta");
    if (flag === "effectMonsterSummonBlocked" && duration !== "next-own-draw")
      throw Error("Oleada de Calor requiere vencimiento en siguiente Robo propio");
    if (!turnScoped.includes(flag) && flag !== "effectMonsterSummonBlocked" &&
        duration !== "persistent") throw Error("Duracion no acreditada");
    const next = { ...state, _ruleFlags: { ...(state._ruleFlags || {}), [flag]: value } };
    if (duration === "turn" || duration === "next-own-draw")
      next._ruleFlagTurns = { ...(state._ruleFlagTurns || {}), [flag]: state.turn };
    if (flag === "xyzOnly") next._xyz = value;
    return next;
  }
  return Object.freeze({ version: 1, names, turnScoped, effective, apply });
});
