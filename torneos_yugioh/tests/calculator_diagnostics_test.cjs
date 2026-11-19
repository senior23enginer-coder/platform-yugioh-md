const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const Diagnostics = require("../assets/modules/play-calculator/diagnostics.js");
const E = require("../assets/modules/play-calculator/engine.js");
const P = require("../assets/modules/play-calculator/planner.js");
const C = require("../assets/modules/play-calculator/chain.js");
const facts = require("../assets/modules/play-calculator/card-facts.json");
const fixture = require("./fixtures/calculator_inventory.json");
assert.deepEqual(Diagnostics.statuses, ["modelled", "conditional", "unresolved"]);
assert.equal(Diagnostics.planner({ plans: [{ steps: [] }], unsupported: [], limited: false }).status, "conditional");
assert.equal(Diagnostics.planner({ plans: [], reason: "Sin acciones", unsupported: [] }).status, "unresolved");
assert.equal(Diagnostics.chain({ status: "conditional", branches: [{}] }).opponentResponsesVerified, false);
assert.throws(() => Diagnostics.chain({ status: "verified", branches: [] }), /inválido/);
const self = E.catalog(fixture.allDecks.find(d => d.id === 665), fixture.cards);
const opponent = E.catalog(fixture.allDecks.find(d => d.id === 656), fixture.cards);
const scenario = E.create(self, opponent);
const plan = P.solve(scenario, facts, { depth: 1, budget: 10 });
assert.equal(plan.assessment.status, plan.plans.length ? "conditional" : "unresolved");
assert.equal(plan.assessment.opponentResponsesVerified, false);
const chain = C.resolve(scenario, facts);
assert.equal(chain.assessment.status, "unresolved");

// Execute the real worker import list in a clean browser-like VM.
const moduleDir = path.resolve(__dirname, "../assets/modules/play-calculator");
const ctx = vm.createContext({ console, setTimeout, clearTimeout });
ctx.self = ctx;
const loaded = [];
ctx.importScripts = (...urls) => {
  for (const url of urls) {
    const file = url.split("?")[0];
    loaded.push(file);
    vm.runInContext(fs.readFileSync(path.join(moduleDir, file), "utf8"), ctx, { filename: file });
  }
};
vm.runInContext(fs.readFileSync(path.join(moduleDir, "planner-worker.js"), "utf8"), ctx,
  { filename: "planner-worker.js" });
assert(loaded.indexOf("./diagnostics.js") < loaded.indexOf("./planner.js"));
assert(loaded.indexOf("./integrity.js") < loaded.indexOf("./rules.js"));
assert.equal(typeof ctx.YgoScenarioAnalysis?.solve, "function");
let workerMessage;
ctx.postMessage = message => { workerMessage = message; };
ctx.onmessage({ data: { id: 42, scenario: JSON.parse(JSON.stringify(scenario)),
  facts: JSON.parse(JSON.stringify(facts)), options: { depth: 1, budget: 10 } } });
assert.equal(workerMessage.id, 42);
assert.equal(workerMessage.error, undefined, workerMessage.error);
assert(["conditional", "unresolved"].includes(workerMessage.result.assessment.status));
console.log(JSON.stringify({ ok: true, assessment: true, workerImports: loaded.length, failClosed: true }));
