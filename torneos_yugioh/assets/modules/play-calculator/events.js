/* Read-only event evidence queries; no inference from unobserved actions. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorEvents = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  function events(state) { return Array.isArray(state?._ruleEvents) ? state._ruleEvents : []; }
  function observed(state, predicate) { return events(state).some(predicate); }
  function matchesGuard(state, guard) {
    if (guard.event === "owned_monster_destroyed_by_effect_this_turn")
      return observed(state, e => e.kind === "destroy" && e.by === "effect" &&
        e.owner === "self" && e.turn === state.turn);
    return observed(state, e => e.kind === guard.event && e.turn === state.turn &&
      e.side === guard.side && (guard.by === undefined || e.by === guard.by));
  }
  function matchingTrigger(state, rule, uid, isMonsterZone) {
    if (!rule.trigger) return true;
    return observed(state, e => {
      if (e.uid !== uid || e.turn !== state.turn || e.cid !== rule.trigger.actorCid) return false;
      const token = state.tokens.some(t => t.uid === uid &&
        (rule.trigger.kind === "sent_by_monster_effect" || rule.trigger.kind === "destroyed_by_effect"
          ? t.zone === "graveyard" : isMonsterZone(t.zone)));
      if (!token) return false;
      if (rule.trigger.kind === "sent_by_monster_effect")
        return e.kind === "sent_to_graveyard" && e.sourceCid === rule.trigger.sourceCid;
      if (rule.trigger.kind === "destroyed_by_effect")
        return e.kind === "destroy" && e.by === "effect" &&
          (!rule.trigger.fromMonsters || isMonsterZone(e.from));
      return rule.trigger.kind === "fusion_summoned" ? e.kind === "fusion_summon"
        : e.kind === "summon" && rule.trigger.methods.includes(e.method);
    });
  }
  function observedExtraSummonWindow(state) {
    return [state.turn, state.turn - 1].some(turn =>
      events(state).filter(e => e.kind === "observed_extra_summon" &&
        e.side === "opponent" && e.from === "extra" && e.turn === turn &&
        typeof e.key === "string" && Number.isInteger(e.uid) &&
        state.opponent.cards.some(c => c.key === e.key && c.section === "extra")).length >= 2);
  }
  return Object.freeze({ version: 1, events, observed, matchesGuard,
    matchingTrigger, observedExtraSummonWindow });
});
