const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const db = require("../assets/modules/play-calculator/card-facts.json");
const fixture = require("./fixtures/calculator_inventory.json");
const self = E.catalog(fixture.allDecks.find((d) => d.id === 665), fixture.cards);
const opponent = E.catalog(fixture.allDecks.find((d) => d.id === 656), fixture.cards);
function find(catalog, test) {
  const row = catalog.cards.find((c) => test(db.facts[db.bindings[c.key]]));
  assert(row);
  return row;
}
const actor = find(self, (f) => f?.cid === 17768);
const target = find(opponent, (f) => f?.kind === "monster" && !f.species?.includes("Link"));
let s = E.create(self, opponent);
s = E.place(s, { key: actor.key, owner: "self", zone: "m1" });
s = E.place(s, { key: target.key, owner: "opponent", zone: "m1" });
const snapshot = JSON.stringify(s);
const rule = {
  version: 1, id: "verified-fixture", deckId: self.id, format: "tcg_advanced",
  scope: "single-effect", label: "Efecto declarado de prueba (no es el efecto de Fenrir)",
  source: "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=17768",
  actor: { side: "self", zones: ["monsters"], cid: 17768, faceUp: true },
  guards: [{ kind: "state", field: "phase", values: ["main1"] }],
  select: [{ ref: "target", selector: { side: "opponent", zones: ["monsters"], faceUp: true } }],
  costs: [{ op: "payLP", amount: 500 }],
  resolve: [
    { op: "negate", ref: "target", duration: "turn", reason: "Prueba" },
    { op: "position", ref: "target", position: "defense" },
    { op: "damage", side: "opponent", amount: 1000 },
    { op: "recoverLP", side: "self", amount: 250 },
  ],
  oncePerTurn: true, activation: true, tags: ["monsterEffect"],
  assumptions: ["Solo prueba la semantica del adaptador; no acredita una carta real."],
};
const available = R.actions(s, { ...db, rules: [rule] });
assert.equal(available.actions.length, 1);
assert.equal(JSON.stringify(s), snapshot, "El motor no modifica el escenario base");
const next = available.actions[0].state;
assert.equal(next.lp.self, 7750);
assert.equal(next.lp.opponent, 7000);
const changed = next.tokens.find((t) => t.owner === "opponent");
assert.equal(changed.position, "defense");
assert.equal(changed.negation.duration, "turn");
assert.equal(R.actions(next, { ...db, rules: [rule] }).actions.length, 0);
assert.equal(R.actions(E.patch(s, { turn: 2 }), { ...db, rules: [rule] }).actions.length, 1);
assert.throws(() => R.validate({ ...rule, resolve: [{ op: "negate", ref: "target", duration: "eternal", reason: "" }] }), /Negacion/);
assert.throws(() => R.validate({ ...rule, resolve: [{ op: "damage", side: "opponent", amount: -1 }] }), /LP/);
const search = {
  ...rule, select: [], costs: [],
  resolve: [{ op: "take", side: "self", cid: 18151, from: "deck", to: "hand", position: "attack" }],
};
assert.equal(R.actions(s, { ...db, rules: [search] }, { noDeckToHand: true }).actions.length, 0,
  "Droll blocked intrinsically even when a rule forgot a flag guard");
const extra = find(self, (f) => /xyz/i.test(f?.species || ""));
const unproven = { ...rule, select: [], costs: [], resolve: [
  { op: "take", side: "self", cid: db.bindings[extra.key], from: "extra", to: "m2", position: "attack" },
] };
assert.equal(R.actions(s, { ...db, rules: [unproven] }).actions.length, 0,
  "An arbitrary Extra summon without materials must fail closed");
console.log(JSON.stringify({ ok: true, lp: true, negate: true, position: true, locks: true, extraFailClosed: true }));
