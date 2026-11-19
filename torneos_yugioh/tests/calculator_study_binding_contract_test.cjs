const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const S = require("../assets/modules/play-calculator/study.js");
const R = require("../assets/modules/play-calculator/rules.js");
const Contract = require("../assets/modules/play-calculator/contract.js");

function setup(id, format = "tcg_advanced", legacy = false) {
  const cid = id + 10000;
  const catalog = E.catalog({ id, name: "Prueba " + id, format_codes: [format] }, [
    { deck_id: id, id: 1, card_name: "Actor de prueba", section: "main", quantity: 1 },
  ]);
  const rule = {
    version: 1, id: `${id}:portable`, deckId: id, format,
    scope: "single-effect", label: "Efecto de prueba",
    source: `https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=${cid}`,
    actor: { side: "self", zones: ["monsters"], cid, faceUp: true },
    guards: [{ kind: "state", field: "phase", values: ["main1"] }],
    select: [], costs: [], resolve: [{ op: "damage", side: "opponent", amount: 100 }],
    tags: [], activation: true, oncePerTurn: true,
    assumptions: ["No representa un efecto real"],
  };
  const study = { deck_id: id, format, cards: [{ name: "Actor de prueba", cid,
    slots: [{ card_id: 1, quantity: 1, section: "main" }] }],
    calculator: legacy ? { rules: [rule] } : {
      schema: Contract.SCHEMA, deck_id: id, format, rules: [rule] } };
  return { catalog, study, rule };
}
const first = setup(9201);
const second = setup(9202, "goat", true);
const base = { bindings: {}, facts: {} };
for (const entry of [first, second]) {
  const original = JSON.stringify(entry.study);
  const db = S.bind(base, [entry]);
  assert.equal(db.ruleImports[0].status, "accepted");
  assert.equal(db.ruleImports[0].legacy, entry === second);
  assert.equal(db.rules.length, 1);
  let state = E.place(E.create(entry.catalog), { key: entry.catalog.cards[0].key,
    owner: "self", zone: "m1" });
  const result = R.actions(state, db);
  assert.equal(result.actions.length, 1, JSON.stringify(result.rejected));
  assert.equal(result.actions[0].state.lp.opponent, 7900);
  assert.equal(JSON.stringify(entry.study), original);
}
const wrongDeck = S.bind(base, [{ ...first, study: { ...first.study,
  calculator: { ...first.study.calculator, deck_id: 9999 } } }]);
assert.equal(wrongDeck.ruleImports[0].status, "rejected");
assert.equal(wrongDeck.rules.length, 0);
const invalidOperation = S.bind(base, [{ ...first, study: { ...first.study,
  calculator: { ...first.study.calculator,
    rules: [{ ...first.rule, resolve: [{ op: "unknown" }] }] } } }]);
assert.equal(invalidOperation.ruleImports[0].status, "rejected");
assert.match(invalidOperation.ruleImports[0].message, /Operacion/);
assert.equal(invalidOperation.rules.length, 0);
const wrongFormat = S.bind(base, [{ ...first, study: { ...first.study, format: "rush_duel" } }]);
assert.equal(wrongFormat.ruleImports[0].status, "rejected");
assert.equal(wrongFormat.rules.length, 0);
const unknownActor = S.bind(base, [{ ...first, study: { ...first.study,
  calculator: { ...first.study.calculator,
    rules: [{ ...first.rule, actor: { ...first.rule.actor, cid: 999999 } }] } } }]);
assert.equal(unknownActor.ruleImports[0].status, "rejected");
assert.match(unknownActor.ruleImports[0].message, /Actor sin identidad/);
assert.equal(unknownActor.rules.length, 0);
const foreignActor = setup(9203);
const foreign = S.bind(base, [first, { ...foreignActor,
  study: { ...foreignActor.study, calculator: { ...foreignActor.study.calculator,
    rules: [{ ...foreignActor.rule, actor: { ...foreignActor.rule.actor,
      cid: first.study.cards[0].cid } }] } } }]);
assert.equal(foreign.ruleImports[0].status, "accepted");
assert.equal(foreign.ruleImports[1].status, "rejected");
assert.match(foreign.ruleImports[1].message, /Actor sin identidad/);
assert.equal(foreign.rules.length, 1);
const reversed = S.bind(base, [{ ...foreignActor,
  study: { ...foreignActor.study, calculator: { ...foreignActor.study.calculator,
    rules: [{ ...foreignActor.rule, actor: { ...foreignActor.rule.actor,
      cid: first.study.cards[0].cid } }] } } }, first]);
assert.equal(reversed.ruleImports[0].status, "rejected");
assert.equal(reversed.ruleImports[1].status, "accepted");
assert.equal(reversed.rules.length, 1);
const reserved = setup(665);
const protectedStudy = { ...reserved.study,
  calculator: { ...reserved.study.calculator,
    rules: [{ ...reserved.rule, id: "665:fenrir:search" }] } };
const collision = S.bind(base, [{ ...reserved, study: protectedStudy }]);
assert.equal(collision.ruleImports[0].status, "rejected");
assert.match(collision.ruleImports[0].message, /colisiona con respaldo verificado/);
assert.equal(collision.rules.length, 1);
assert.equal(collision.rules[0].actor.cid, 17768);
assert.equal(collision.rules[0].id, "665:fenrir:search");
for (const malformed of [
  { ...first.study, cards: { invalid: true } },
  { ...first.study, cards: [{ ...first.study.cards[0], source_aliases: "cadena" }] },
  { ...first.study, cards: [{ ...first.study.cards[0], slots: [null] }] },
]) {
  const combined = S.bind(base, [{ ...first, study: malformed }, second]);
  assert.equal(combined.ruleImports[0].status, "rejected");
  assert.match(combined.ruleImports[0].message, /identidad|Inventario/);
  assert.equal(combined.ruleImports[1].status, "accepted");
  assert.equal(combined.rules.length, 1);
}
console.log(JSON.stringify({ ok: true, accepted: 2, rejected: 10, legacyCompatible: true,
  crossDeckActorIsolation: true, verifiedFallbackProtected: true, malformedStudyIsolation: true }));
