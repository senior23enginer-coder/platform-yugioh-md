const assert = require("node:assert/strict");
const Resources = require("../assets/modules/play-calculator/resources.js");
const E = require("../assets/modules/play-calculator/engine.js");
const Rules = require("../assets/modules/play-calculator/rules.js");
const fixture = require("./fixtures/calculator_inventory.json");
const self = E.catalog(fixture.allDecks.find(d => d.id === 665), fixture.cards);
const opponent = E.catalog(fixture.allDecks.find(d => d.id === 656), fixture.cards);
const db = require("../assets/modules/play-calculator/card-facts.json");
const first = self.cards.find(c => db.bindings[c.key] && db.facts[db.bindings[c.key]]?.kind === "monster");
assert(first);
const cid = db.bindings[first.key];
let state = E.place(E.create(self, opponent), { key: first.key, owner: "self", zone: "m1" });
const initial = Object.freeze({ self: 8000, opponent: 8000 });
assert.deepEqual(Resources.applyLP(initial, { op: "payLP", amount: 500 }), { self: 7500, opponent: 8000 });
assert.deepEqual(initial, { self: 8000, opponent: 8000 });
assert.deepEqual(Resources.applyLP(initial, { op: "damage", side: "opponent", amount: 99999 }), { self: 8000, opponent: 0 });
assert.deepEqual(Resources.applyLP(initial, { op: "recoverLP", side: "self", amount: 500 }), { self: 8500, opponent: 8000 });
assert.throws(() => Resources.applyLP(initial, { op: "payLP", amount: 8000 }), /insuficientes/);
assert.throws(() => Resources.applyLP(initial, { op: "payLP", amount: 8001 }), /insuficientes/);
assert.throws(() => Resources.applyLP({ self: 999999, opponent: 8000 }, { op: "recoverLP", side: "self", amount: 1 }), /exceden/);
assert.throws(() => Resources.applyLP(initial, { op: "damage", side: "unknown", amount: 1 }), /Jugador/);
assert.throws(() => Resources.applyLP(initial, { op: "damage", side: "self", amount: -1 }), /Cantidad/);
assert.throws(() => Resources.applyLP({ self: -1, opponent: 0 }, { op: "damage", side: "self", amount: 1 }), /Estado/);
const cards = { tokens: [
  { uid: 1, owner: "self", controller: "self", zone: "hand" },
  { uid: 2, owner: "self", controller: "self", zone: "graveyard" },
  { uid: 3, owner: "opponent", controller: "opponent", zone: "hand" },
] };
const refs = { first: 1, second: 2, rival: 3, alias: 1 };
const own = t => t.owner === "self" && ["hand", "graveyard"].includes(t.zone);
assert.deepEqual(Resources.requireTokens(cards, refs, ["first", "second"], own).map(t => t.uid), [1, 2]);
assert.throws(() => Resources.requireTokens(cards, refs, ["first", "alias"], own), /Recursos físicos/);
assert.throws(() => Resources.requireTokens(cards, refs, ["first", "rival"], own), /Recursos físicos/);
assert.throws(() => Resources.requireTokens(cards, refs, ["missing"], own), /identificados/);
assert.throws(() => Resources.requireTokens(cards, { first: 42 }, ["first"], own), /no disponibles/);
assert.equal(cards.tokens[0].zone, "hand", "Resolver materiales no debe consumirlos");
const rule = {
  version: 1, id: "resources-fixture", deckId: self.id, format: "tcg_advanced",
  scope: "single-effect", label: "Prueba sintética de LP",
  source: "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=" + cid,
  actor: { side: "self", zones: ["monsters"], cid, faceUp: true },
  guards: [{ kind: "state", field: "phase", values: ["main1"] }],
  select: [], costs: [{ op: "payLP", amount: 500 }],
  resolve: [{ op: "damage", side: "opponent", amount: 1200 }, { op: "recoverLP", side: "self", amount: 200 }],
  assumptions: ["Efecto sintético para verificar recursos"],
  tags: [], activation: true, oncePerTurn: true,
};
const before = JSON.stringify(state);
const calculated = Rules.actions(state, { ...db, rules: [rule] });
assert.equal(calculated.actions.length, 1, JSON.stringify(calculated.rejected));
assert.deepEqual(calculated.actions[0].state.lp, { self: 7700, opponent: 6800 });
assert.equal(JSON.stringify(state), before, "La simulación no puede consumir LP en el estado original");
console.log(JSON.stringify({ ok: true, pure: true, integration: true, physicalTokens: true, failClosed: true }));
