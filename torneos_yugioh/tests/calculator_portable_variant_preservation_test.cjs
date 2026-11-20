const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const Study = require("../assets/modules/play-calculator/study.js");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");
const Contract = require("../assets/modules/play-calculator/contract.js");

const id = 671;
const all = Verified.forDeck(id);
const siblings = all.filter(rule => rule.id === "671:bone-party:main");
assert.equal(siblings.length, 42, "Expected exact Memento Bone Party fallback variants");
const chosen = siblings[0];
const catalog = E.catalog({id,name:"Memento",format_codes:["tcg_advanced"]},
  [{deck_id:id,id:1,card_name:"Mementotlan Bone Party",quantity:1,section:"main"}]);
const study = {
  deck_id:id,format:"tcg_advanced",
  cards:[{name:"Mementotlan Bone Party",cid:19368,
    slots:[{card_id:1,quantity:1,section:"main"}]}],
  calculator:{schema:Contract.SCHEMA,deck_id:id,format:"tcg_advanced",rules:[chosen]}
};
const db = Study.bind({bindings:{},facts:{}},[{catalog,study}]);
assert.equal(db.ruleImports[0].status,"accepted",JSON.stringify(db.ruleImports));
assert.equal(db.ruleImports[0].count,1);
const retained = db.rules.filter(rule=>rule.id===chosen.id);
assert.equal(retained.length,42,
  "Portable import of one variant must preserve all 41 remaining fallback variants");
assert.equal(retained.filter(rule=>JSON.stringify(rule)===JSON.stringify(chosen)).length,1,
  "Exact imported variant must not be duplicated");
const tampered = JSON.parse(JSON.stringify(study));
tampered.calculator.rules[0].label = "Not an official definition";
const rejected = Study.bind({bindings:{},facts:{}},[{catalog,study:tampered}]);
assert.equal(rejected.ruleImports[0].status,"rejected",
  "A conflicting imported variant must be rejected");
assert.equal(rejected.rules.filter(rule=>rule.id===chosen.id).length,42,
  "A rejected import must preserve all safe fallback variants");
console.log("Portable Memento variants: all 42 retained, exact duplicate avoided, tamper rejected.");
