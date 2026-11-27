/** Life-point state normalization and serialization with injected storage. */
// @runtime-start
(function (root) {
  "use strict";

  function restore(defaultState, readValue) {
    let state = { ...defaultState };
    try {
      const saved = JSON.parse(readValue() || "{}");
      state = { ...state, ...saved };
      state.a = Number.isFinite(Number(state.a))
        ? Math.max(0, Number(state.a))
        : 8000;
      state.b = Number.isFinite(Number(state.b))
        ? Math.max(0, Number(state.b))
        : 8000;
      state.startA = Number.isFinite(Number(state.startA))
        ? Math.max(0, Number(state.startA))
        : 8000;
      state.startB = Number.isFinite(Number(state.startB))
        ? Math.max(0, Number(state.startB))
        : 8000;
      state.timer = Number.isFinite(Number(state.timer))
        ? Math.max(0, Number(state.timer))
        : 0;
      state.turn = Number.isFinite(Number(state.turn))
        ? Math.max(1, Number(state.turn))
        : 1;
      state.coin = String(state.coin || "");
      state.dice = String(state.dice || "");
      state.history = Array.isArray(state.history)
        ? state.history.slice(0, 12)
        : [];
      state.running = false;
    } catch (error) {
      state = { ...defaultState };
    }
    return state;
  }

  function resultKey(matchId, duel) {
    return `dml_result_lp_${matchId || "match"}_${duel || 1}`;
  }

  function persist(state, key, writeValue) {
    writeValue(key, JSON.stringify({ ...state, running: false }));
  }

  const api = Object.freeze({ restore, resultKey, persist });
  root.StationXLpStatePolicy = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
