const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const P = require("../assets/modules/play-calculator/planner.js");
const Contract = require("../assets/modules/play-calculator/contract.js");
const Diagnostics = require("../assets/modules/play-calculator/diagnostics.js");
const formats = ["tcg_advanced", "goat", "rush_duel"];
let scenarios = 0;
const results = [];
for (const format of formats) {
  for (const deckId of [9101, 9102]) {
    const cid = deckId + (format === "rush_duel" ? 100 : format === "goat" ? 200 : 300);
    const rows = [
      { deck_id: deckId, id: 1, card_name: "Actor sintético", quantity: 1, section: "main" },
      { deck_id: deckId, id: 2, card_name: "Recurso sintético", quantity: 1, section: "main" },
    ];
    const catalog = E.catalog({ id: deckId, name: "Prueba " + deckId, format_codes: [format] }, rows);
    const opponent = E.catalog({ id: deckId + 100, name: "Rival de prueba", format_codes: [format] }, [
      { deck_id: deckId + 100, id: 1, card_name: "Carta rival", quantity: 1, section: "main" },
    ]);
    const rule = {
      version: 1, id: `${deckId}:${format}:shared`, deckId, format,
      scope: "single-effect", label: "Efecto universal sintético",
      source: `https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=${cid}`,
      actor: { side: "self", zones: ["monsters"], cid, faceUp: true },
      guards: [{ kind: "state", field: "phase", values: ["main1"] }],
      select: [], costs: [{ op: "payLP", amount: 100 }],
      resolve: [{ op: "damage", side: "opponent", amount: 200 },
        { op: "recoverLP", side: "self", amount: 50 }],
      tags: [], activation: true, oncePerTurn: true,
      assumptions: ["Solo prueba la portabilidad; no representa una carta oficial."],
    };
    const contract = { schema: Contract.SCHEMA, deck_id: deckId, format, rules: [rule] };
    const db = { rules: [rule], bindings: { [catalog.cards[0].key]: cid }, facts: {} };
    assert.equal(Contract.validate(contract, { deckId, format, actorCids: [cid] }).rules, 1);
    let scenario = E.create(catalog, opponent);
    scenario = E.place(scenario, { key: catalog.cards[0].key, owner: "self", zone: "m1" });
    const original = JSON.stringify(scenario);
    const actions = R.actions(scenario, db);
    assert.equal(actions.rejected.length, 0, JSON.stringify(actions.rejected));
    assert.equal(actions.actions.length, 1, `No se ejecutó ${deckId}/${format}`);
    assert.deepEqual(actions.actions[0].state.lp, { self: 7950, opponent: 7800 });
    assert.equal(R.actions(actions.actions[0].state, db).actions.length, 0,
      "El límite por turno no depende del deck ni del formato");
    assert.equal(JSON.stringify(scenario), original, "La ejecución no puede mutar el escenario inicial");
    assert.throws(() => Contract.validate({ ...contract, deck_id: deckId + 1 }), /otro deck/);
    assert.throws(() => Contract.validate({ ...contract, format: "tcg_advanced" === format ? "goat" : "tcg_advanced" }), /otro deck o formato|otro formato/);
    const plan = P.solve(scenario, db, { depth: 2, budget: 60 });
    assert.equal(plan.assessment.status, Diagnostics.planner(plan).status);
    assert.equal(plan.assessment.opponentResponsesVerified, false);
    scenarios++;
    results.push(`${format}:${deckId}`);
  }
}
console.log(JSON.stringify({ ok: true, scenarios, formats: formats.length, sameInterpreter: true, results }));
