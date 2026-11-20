const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const P = require("../assets/modules/play-calculator/planner.js");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");

// Executable multi-step regression for one proven Goblin/Ghattic line.
// This does not certify any complete editorial A09 route.
const ids = [19366, 19363, 19472];
const names = ["Mementotlan Goblin", "Mementotlan Dark Blade", "Mementotlan Ghattic"];
const own = E.catalog({id:671,name:"Memento",format_codes:["tcg_advanced"]},
  names.map((name,i)=>({deck_id:671,id:i+1,card_name:name,section:"main",quantity:1})));
const bindings = Object.fromEntries(own.cards.map((c,i)=>[c.key,ids[i]]));
const levels = [1, 4, 2], attacks = [400, 1800, 550];
const facts = Object.fromEntries(ids.map((cid,i)=>[cid,{cid,name:names[i],
  kind:"monster",species:"Effect Monster",level:levels[i],atk:attacks[i],normalAllowed:true,
  source:`https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=${cid}`}]));
const db = {bindings,facts,rules:Verified.forDeck(671),
  names:Object.fromEntries(own.cards.map((c,i)=>[c.key,names[i]]))};
let state = E.create(own);
state = E.place(state,{key:own.cards[0].key,owner:"self",zone:"m1"});
const before = JSON.stringify(state);
const result = P.solve(state,db,{depth:3,width:80,budget:2000});
assert.equal(JSON.stringify(state),before,"Planner must not mutate the scenario");
const route = result.plans.find(plan =>
  plan.steps.length >= 3 &&
  plan.steps[0].label.includes("Goblin: destruirse y enviar Hoja Oscura y Desvantasma") &&
  plan.steps[1].label.includes("Desvantasma: Invocarse tras envío") &&
  plan.steps[2].label.includes("Desvantasma: recuperar carta Memento"));
assert(route,"Dynamic planner should connect Goblin send, Ghattic revival and recovery");
const final=route.final;
assert(final.some(t=>t.cid===19472&&/^m[1-5]$/.test(t.zone)),
  "Ghattic is Special Summoned to a legal main monster zone");
assert(final.some(t=>t.cid===19363&&t.zone==="hand") ||
       final.some(t=>t.cid===19366&&t.zone==="hand"),
  "Ghattic recovery must take an actually available Memento");
const blocked=P.solve(state,db,{depth:3,width:80,budget:2000,specialBlocked:true});
assert(!blocked.plans.some(p=>p.steps.some(x=>x.label.includes("Desvantasma: Invocarse"))),
  "A Special Summon blocker must prevent Ghattic revival");
assert(result.plans.every(plan=>plan.steps.every(step=>step.source&&step.after)),
  "Every suggested transition must retain its source and final snapshot");
console.log("Memento planner Goblin -> Ghattic -> recover: legal sequence and blocked case passed.");
