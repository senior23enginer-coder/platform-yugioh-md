const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const E = require("../assets/modules/play-calculator/engine.js");
const Study = require("../assets/modules/play-calculator/study.js");
const Planner = require("../assets/modules/play-calculator/planner.js");
const base = require("../assets/modules/play-calculator/card-facts.json");

// Exercise actual ZIP-equivalent editorial rules, not a synthetic hand-built DB.
// This is a bounded three-step scenario, not certification of the 12 A09 routes.
const study = JSON.parse(fs.readFileSync(
  path.join(__dirname, "../assets/editorial/20261117-folder-delivery-v40/671.json"), "utf8"));
const rows = study.cards.flatMap(c => c.slots.map(s => ({
  id:s.card_id, deck_id:671, card_name:c.name, quantity:s.quantity, section:s.section,
})));
const catalog = E.catalog({id:671,name:study.name,format_codes:[study.format]},rows);
const db = Study.bind(base,[{catalog,study}]);
assert.equal(db.ruleImports[0].status,"accepted",JSON.stringify(db.ruleImports));
assert.equal(db.ruleImports[0].count,353);
assert.equal(db.rules.length,353);
const goblin = catalog.cards.find(c => db.bindings[c.key]===19366);
assert(goblin,"Memento Goblin must have an exactly bound native inventory slot");
let state = E.create(catalog);
state = E.place(state,{key:goblin.key,owner:"self",zone:"m1"});
const before = JSON.stringify(state);
const opts = {depth:3,width:80,budget:2500};
const result = Planner.solve(state,db,opts);
assert.equal(JSON.stringify(state),before,"Analysis must not modify the captured duel state");
const match = result.plans.find(plan => plan.steps.length>=3 &&
  plan.steps[0].label.includes("Goblin: destruirse y enviar Hoja Oscura y Desvantasma") &&
  plan.steps[1].label.includes("Desvantasma: Invocarse tras envío") &&
  plan.steps[2].label.includes("Desvantasma: recuperar carta Memento"));
assert(match,"Imported portable rules must calculate the causal three-step sequence");
assert(match.steps.every(step=>step.source&&Array.isArray(step.after)),
  "Each recommended effect must retain official source and a resulting snapshot");
const blocked=Planner.solve(state,db,{...opts,specialBlocked:true});
assert(!blocked.plans.some(plan=>plan.steps.some(step=>
  step.label.includes("Desvantasma: Invocarse tras envío"))),
  "Blocked Special Summons must reject the Ghattic continuation");
// Ash Blossom is a reactive hand trap, not a generic field-development line.
// The calculator may identify it as unsupported for this snapshot, but must
// never recommend consuming the Normal Summon just to place it on the field.
const ash=catalog.cards.find(c=>db.bindings[c.key]===12950&&c.section==="main");
assert(ash,"Approved Memento inventory must contain physical Ash Blossom");
let ashOnly=E.create(catalog);
ashOnly=E.place(ashOnly,{key:ash.key,owner:"self",zone:"hand"});
const ashBefore=JSON.stringify(ashOnly);
const ashResult=Planner.solve(ashOnly,db,{depth:3,width:30,budget:900});
assert.equal(JSON.stringify(ashOnly),ashBefore,"Ash-only analysis must not mutate captured hand");
assert(!ashResult.plans.some(plan=>plan.steps.some(step=>
  /Invoca Normalmente.*(?:Flor de Ceniza|Ash Blossom)/i.test(step.label))),
  "Ash Blossom must not be offered as a generic Normal Summon");
const mixed=E.place(ashOnly,{key:goblin.key,owner:"self",zone:"m1"});
const mixedResult=Planner.solve(mixed,db,{depth:3,width:50,budget:1600});
assert(!mixedResult.plans.some(plan=>plan.steps.some(step=>
  /Invoca Normalmente.*(?:Flor de Ceniza|Ash Blossom)/i.test(step.label))),
  "Adding an engine monster does not turn Ash into a useful Normal Summon");
console.log("Imported 353 Memento variants: Goblin -> Ghattic -> recovery planned and restriction checked.");
