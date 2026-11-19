const assert = require("node:assert/strict");
const Integrity = require("../assets/modules/play-calculator/integrity.js");
const E = require("../assets/modules/play-calculator/engine.js");
const P = require("../assets/modules/play-calculator/planner.js");
const C = require("../assets/modules/play-calculator/chain.js");
const fixture = require("./fixtures/calculator_inventory.json");
const db = require("../assets/modules/play-calculator/card-facts.json");
const self = E.catalog(fixture.allDecks.find(d => d.id === 665), fixture.cards);
const opponent = E.catalog(fixture.allDecks.find(d => d.id === 656), fixture.cards);
const scenario = E.create(self, opponent);
assert.equal(Integrity.version, 1);
assert.equal(Integrity.validate(scenario), scenario);
const extended = { ...scenario, turn: 2, _ruleFlags: { noMoreSummons: true },
  _ruleFlagTurns: { noMoreSummons: 1 }, _ruleUseTurns: { "rule:sample": 1 },
  _ruleEvents: [{ kind: "destroy", turn: 1, uid: 1 }],
  _rulePhaseActions: { turn: 1, phase: "main1", side: "self", count: 2 } };
assert.equal(Integrity.validate(extended), extended);
const cases = [
  [{ _ruleFlags: { noExtra: "yes" } }, /Banderas/],
  [{ _ruleFlagTurns: { noMoreSummons: 3 } }, /Caducidad/],
  [{ _ruleFlagTurns: { invented: 1 } }, /Caducidad/],
  [{ _ruleUseTurns: { "rule:sample": 3 } }, /Historial de uso/],
  [{ _ruleEvents: [{ kind: "destroy", turn: 3 }] }, /Historial causal/],
  [{ _ruleEvents: [{ kind: "destroy", uid: -1 }] }, /Historial causal/],
  [{ _rulePhaseActions: { turn: 2, phase: "invented", side: "self", count: 1 } }, /Conteo/],
];
for (const [change, message] of cases)
  assert.throws(() => Integrity.validate({ ...extended, ...change }), message);
assert.throws(() => P.solve({ ...scenario, _ruleUseTurns: { "rule:x": 2 } }, db), /Historial de uso/);
assert.throws(() => C.resolve({ ...scenario, _ruleEvents: [{ kind: "test", turn: 9 }] }, db), /Historial causal/);
assert.equal(extended._ruleFlagTurns.noMoreSummons, 1);
console.log(JSON.stringify({ ok: true, validLegacy: true, metadata: true, boundaries: true }));
