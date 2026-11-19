const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const E = require("../assets/modules/play-calculator/engine.js");
const A = require("../assets/modules/play-calculator/scenario.js");
const db = require("../assets/modules/play-calculator/card-facts.json");
const fixture = require("./fixtures/calculator_inventory.json");
const own = E.catalog(fixture.allDecks.find((d) => d.id === 665), fixture.cards);
const rival = E.catalog(fixture.allDecks.find((d) => d.id === 656), fixture.cards);
function pick(catalog, cid) {
  const row = catalog.cards.find((c) => db.bindings[c.key] === cid);
  assert(row, `Missing ${cid}`);
  return row;
}
let scenario = E.create(own, rival);
for (const cid of [17768, 18203]) {
  const row = pick(own, cid);
  scenario = E.place(scenario, { key: row.key, owner: "self", zone: "hand" });
}
scenario = E.place(scenario, { key: pick(rival, 12950).key, owner: "opponent", zone: "hand" });
const original = JSON.stringify(scenario);
const goals = ["balanced", "removal", "board", "resources", "resilience"];
const ui = fs.readFileSync(path.resolve(__dirname, "../assets/modules/play-calculator/ui.js"), "utf8");
for (const goal of goals) {
  assert(ui.includes(`["${goal}",`), `Goal ${goal} missing from visual selector`);
  const options = { goal, depth: 4, width: 25, budget: 800 };
  const a = A.solve(scenario, db, options), b = A.solve(scenario, db, options);
  assert.equal(JSON.stringify(a), JSON.stringify(b), `${goal}: local calculation must be deterministic`);
  assert.equal(a.goal, goal);
  assert(a.goalLabels?.[goal]);
  assert(a.plans.length > 0 && a.plans.length <= 6);
  const key = goal === "resilience" ? "resilienceScore" : "priority";
  assert(a.plans.every((p, i, rows) => i === 0 || rows[i - 1][key] >= p[key]),
    `${goal}: rankings must be sorted`);
  for (const plan of a.plans) {
    assert(plan.steps.length > 0);
    assert.equal(plan.metrics.actions, plan.steps.length);
    assert(Number.isFinite(plan.priority));
    assert(plan.responseAudit);
    assert(plan.steps.every((step) => step.source && Array.isArray(step.after)),
      "Each executable action must explain its source and resulting state");
    assert(plan.final.every((token) => token.key !== "unknown" || token.owner === "opponent"),
      "No invented hidden card identity");
  }
}
assert.equal(JSON.stringify(scenario), original, "Rankings must not edit the user's captured state");
assert.equal(A.solve(scenario, db, { goal: "invalid", depth: 1 }).goal, "balanced");
console.log(JSON.stringify({ ok: true, strategicObjectives: goals.length, deterministic: true, immutable: true }));
