const assert = require("node:assert/strict");
const Events = require("../assets/modules/play-calculator/events.js");
const Engine = require("../assets/modules/play-calculator/engine.js");
const Rules = require("../assets/modules/play-calculator/rules.js");
const state = { turn: 3, tokens: [{ uid: 7, zone: "graveyard" }],
  opponent: { cards: [{ key: "a", section: "extra" }, { key: "b", section: "extra" }] },
  _ruleEvents: [
    { kind: "destroy", uid: 7, cid: 123, side: "self", owner: "self", by: "effect", from: "m1", turn: 3 },
    { kind: "sent_to_graveyard", uid: 7, cid: 123, sourceCid: 456, turn: 3 },
    { kind: "observed_extra_summon", key: "a", uid: 20, side: "opponent", from: "extra", turn: 2 },
    { kind: "observed_extra_summon", key: "b", uid: 21, side: "opponent", from: "extra", turn: 2 },
  ] };
assert.equal(Events.version, 1);
assert.equal(Events.events({}).length, 0);
assert.equal(Events.matchesGuard(state, { event: "owned_monster_destroyed_by_effect_this_turn" }), true);
assert.equal(Events.matchesGuard(state, { event: "destroy", side: "self", by: "effect" }), true);
assert.equal(Events.matchesGuard(state, { event: "destroy", side: "opponent", by: "effect" }), false);
assert.equal(Events.matchesGuard({ ...state, turn: 4 }, { event: "destroy", side: "self" }), false);
const trigger = kind => ({ trigger: { actorCid: 123, kind, sourceCid: 456, fromMonsters: true } });
assert.equal(Events.matchingTrigger(state, trigger("destroyed_by_effect"), 7, Engine.isMonster), true);
assert.equal(Events.matchingTrigger(state, trigger("sent_by_monster_effect"), 7, Engine.isMonster), true);
assert.equal(Events.matchingTrigger({ ...state, turn: 4 }, trigger("sent_by_monster_effect"), 7, Engine.isMonster), false);
assert.equal(Events.matchingTrigger(state, trigger("destroyed_by_effect"), 8, Engine.isMonster), false);
assert.equal(Events.observedExtraSummonWindow(state), true);
assert.equal(Events.observedExtraSummonWindow({ ...state, turn: 4 }), false);
assert.equal(Events.observedExtraSummonWindow({ ...state, opponent: { cards: [] } }), false);
assert.equal(typeof Rules.actions, "function");
console.log(JSON.stringify({ ok: true, guardEvidence: true, causality: true, extraHistory: true }));
