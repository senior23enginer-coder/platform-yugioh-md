/* Turn/phase timing policies shared by every deck's declarative rules. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorTiming = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  function usedThisTurn(state, key) {
    if (state._ruleUseTurns?.[key] !== undefined)
      return state._ruleUseTurns[key] === state.turn;
    return (state._used || []).includes(key) ||
      (state._used || []).includes(key + ":turn:" + state.turn);
  }
  function isPhaseStart(state) {
    const history = state._rulePhaseActions;
    return !(history && history.turn === state.turn &&
      history.phase === state.phase && history.side === state.active && history.count > 0);
  }
  function countPhaseAction(state) {
    const previous = state._rulePhaseActions;
    const count = previous && previous.turn === state.turn &&
      previous.phase === state.phase && previous.side === state.active ? previous.count : 0;
    return { turn: state.turn, phase: state.phase, side: state.active, count: count + 1 };
  }
  function isOwnMainPhase(state) {
    return state.active === "self" && ["main1", "main2"].includes(state.phase);
  }
  function canActivateSetSpellTrap(state, token) {
    return !(/^s[1-5]$/.test(token.zone) && token.position === "set") ||
      (Number.isInteger(token.setTurn) && token.setTurn < state.turn);
  }
  return Object.freeze({ version: 1, usedThisTurn, isPhaseStart, countPhaseAction,
    isOwnMainPhase, canActivateSetSpellTrap });
});
