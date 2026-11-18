const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const Contract = require("../assets/modules/play-calculator/contract.js");

function setup(deckId, cid) {
  const rows = [{ deck_id: deckId, id: 1, card_name: "Synthetic actor", quantity: 1, section: "main" }];
  const catalog = E.catalog({ id: deckId, name: "Synthetic " + deckId, format_codes: ["tcg_advanced"] }, rows);
  const rule = {
    version: 1, id: `${deckId}:synthetic:recover`, deckId, format: "tcg_advanced",
    source: `https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=${cid}`,
    scope: "single-effect", label: "Recuperación sintética de LP",
    assumptions: ["Solo valida el contrato; no representa un efecto real."],
    actor: { side: "self", zones: ["monsters"], cid, faceUp: true },
    guards: [{ kind: "state", field: "phase", values: ["main1"] }],
    select: [], costs: [], resolve: [{ op: "recoverLP", side: "self", amount: 200 }],
    activation: true, oncePerTurn: true, tags: ["monsterEffect"],
  };
  const contract = { schema: Contract.SCHEMA, deck_id: deckId, format: "tcg_advanced", rules: [rule] };
  return { catalog, contract, cid };
}

for (const { catalog, contract, cid } of [setup(9001, 17768), setup(9002, 17769)]) {
  const before = JSON.stringify(contract);
  assert.equal(Contract.validate(contract, { deckId: catalog.id, format: "tcg_advanced", actorCids: [cid] }).rules, 1);
  let state = E.create(catalog);
  state = E.place(state, { key: catalog.cards[0].key, owner: "self", zone: "m1" });
  const db = { bindings: { [catalog.cards[0].key]: cid }, rules: contract.rules, facts: {} };
  assert.equal(R.actions(state, db).actions.length, 1, "Rule imported as data is executable");
  assert.equal(R.actions(state, db).actions[0].state.lp.self, 8200);
  assert.equal(JSON.stringify(contract), before, "Validation must not mutate the imported data");
  assert.throws(() => Contract.validate({ ...contract, schema: "station-x-calculator/2" }), /Versión/);
  assert.throws(() => Contract.validate({ ...contract, deck_id: 3 }), /otro deck/);
  assert.throws(() => Contract.validate({ ...contract, extra: true }), /desconocido/);
  assert.throws(() => Contract.validate({ ...contract, rules: [contract.rules[0], contract.rules[0]] }), /duplicado/);
  assert.throws(() => Contract.validate(contract, { actorCids: [999] }), /Actor sin identidad/);
  assert.throws(() => Contract.validate({ ...contract, rules: [{ ...contract.rules[0], resolve: [{ op: "unknown" }] }] }),
    /implementada|desconocida|Operaci/i);
}
console.log(JSON.stringify({ ok: true, syntheticDecks: 2, contract: Contract.SCHEMA, noDeckSpecificCode: true }));
