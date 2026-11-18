const assert = require("node:assert/strict");
const G = require("../assets/modules/play-calculator/guards.js");
const R = require("../assets/modules/play-calculator/rules.js");
assert.equal(G.version, 1);
assert.equal(G.list().length, 9);
assert.equal(new Set(G.list().map(row => row.kind)).size, 9);
assert.equal(G.describe("count").coverage, "generic");
assert.equal(G.describe("extraSummonHistory").coverage, "restricted");
assert.equal(G.describe("unknown"), null);
assert.throws(() => G.assertSupported({ kind: "unknown" }), /no implementada/);
assert.throws(() => G.assertSupported(null), /no implementada/);
assert.equal(G.assertSupported({ kind: "flag" }).kind, "flag");
assert(Object.isFrozen(G.registry) && Object.isFrozen(G.describe("state")));

const base = {
  version: 1, id: "guard-fixture", deckId: 9001, format: "tcg_advanced",
  source: "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=123",
  scope: "single-effect", label: "Prueba sintética de condiciones",
  actor: { side: "self", zones: ["monsters"], cid: 123 },
  guards: [{ kind: "state", field: "phase", values: ["main1"] }],
  select: [], costs: [], resolve: [{ op: "damage", side: "opponent", amount: 1 }],
  assumptions: ["Sólo prueba el contrato de guards"], tags: [],
  activation: true, oncePerTurn: false,
};
assert.equal(R.validate(base), base);
assert.throws(() => R.validate({ ...base, guards: [{ kind: "invented" }] }), /no implementada/);
assert.throws(() => R.validate({ ...base, guards: [{ kind: "flag", flag: "invented", value: true }] }), /Bloqueo/);
assert.throws(() => R.validate({ ...base, guards: [{ kind: "extraSummonHistory", side: "self", min: 2, turns: 2 }] }), /Historial/);
console.log(JSON.stringify({ ok: true, guards: G.list().length, restricted: G.list().filter(g => g.coverage === "restricted").length }));
