/** Timer policy: no browser clock or storage dependency. */
// @runtime-start
(function (root) {
  "use strict";

  function storageKey(matchId, duel) {
    return `dml_duel_timer_v2_${matchId || "match"}_${duel}`;
  }

  function elapsed(state, now) {
    const base = Math.max(0, Number(state?.baseElapsed || 0));
    if (!state?.running || !state.startedAt)
      return Math.min(1200, Math.floor(base));
    return Math.min(
      1200,
      Math.floor(base + (now() - Number(state.startedAt)) / 1000),
    );
  }

  function persist(state, key, liveSynced, now, writeValue) {
    if (!state) return;
    state.updatedAt = now();
    state.liveSynced = liveSynced;
    writeValue(key(), JSON.stringify(state));
  }

  const api = Object.freeze({ storageKey, elapsed, persist });
  root.StationXDuelTimerPolicy = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
