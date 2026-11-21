'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const E=require('../assets/modules/play-calculator/engine.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Contract=require('../assets/modules/play-calculator/contract.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const R=require('../assets/modules/play-calculator/rules.js');
const base=require('../assets/modules/play-calculator/card-facts.json');
const file=path.join(__dirname,'../assets/editorial/20261117-folder-delivery-v40/671.json');
const study=JSON.parse(fs.readFileSync(file,'utf8'));
assert.equal(study.deck_id,671);
assert.equal(study.cards.length,39);
assert.equal(study.routes.length,12);
assert.equal(study.calculator.schema,Contract.SCHEMA);
const ids=['671:mictlan:activate','671:creation-king:destroy','671:twin-dragon:fusion-search','671:duke:recover-engraver'];
assert.equal(study.calculator.rules.length,316);
const reference=Verified.forDeck(671);
assert.equal(reference.length,316);
assert.equal(new Set(study.calculator.rules.map(rule=>rule.id)).size,54);
assert.equal(study.calculator.rules.filter(rule=>rule.id==='671:bone-party:main').length,43);
assert.equal(new Set(study.calculator.rules.map(rule=>JSON.stringify(rule))).size,316);
assert(study.calculator.rules.every(rule=>reference.some(known=>
  JSON.stringify(known)===JSON.stringify(rule))),
  'Every portable variant must match an independently verified fallback definition');
for(const id of ids){
 const candidates=study.calculator.rules.filter(r=>r.id===id);
 const expected=id==='671:twin-dragon:fusion-search'?2:1;
 assert.equal(candidates.length,expected,'Portable effect variants must have the audited count: '+id);
 assert(candidates.every(rule=>reference.some(known=>JSON.stringify(known)===JSON.stringify(rule))),
  'Every effect variant must exactly match an already verified engine rule: '+id);
}
const rows=study.cards.flatMap(card=>(card.slots||[]).map(slot=>({
 id:slot.card_id,deck_id:671,card_name:card.name,section:slot.section,quantity:slot.quantity
})));
const native=E.catalog({id:671,name:'Memento',format_codes:['tcg_advanced']},rows);
const original=JSON.stringify(study);
const db=Study.bind(base,[{catalog:native,study}]);
assert.equal(JSON.stringify(study),original,'Binding must not mutate original study');
assert.equal(db.ruleImports.length,1);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.ruleImports[0].count,316);
assert.equal(db.rules.length,316,'Portable and fallback variants must not be duplicated');
assert.equal(db.rules.filter(rule=>rule.id==='671:lancea:banish-lock').length,1,
  'Verified Side-only effect must survive portable binding exactly once');
assert(!native.cards.some(card=>db.bindings[card.key]===11243),
  'Importing a verified Side-only rule does not move Lancea into Main');
for(const id of ids){
 const expected=id==='671:twin-dragon:fusion-search'?2:1;
 assert.equal(db.rules.filter(r=>r.id===id).length,expected,
  'Import must preserve each audited variant once, without duplicates: '+id);
}
const fallback=Study.bind(base,[{catalog:native,study:{...study,calculator:undefined}}]);
for(const id of ids)assert(fallback.rules.some(r=>r.id===id),'Fallback must survive when portable package absent');
const tampered=JSON.parse(JSON.stringify(study));
tampered.calculator.rules[0].resolve[0].to='banished';
const rejected=Study.bind(base,[{catalog:native,study:tampered}]);
assert.equal(rejected.ruleImports[0].status,'rejected',
 'Tampering a protected verified effect must fail closed');
assert(rejected.rules.some(r=>r.id===ids[0]),
 'Rejection must not remove verified fallback');
let state=E.create(native);
const mictlan=native.cards.find(card=>db.bindings[card.key]===19367);
assert(mictlan,'Mementomictlan must have an exact native slot');
state=E.place(state,{key:mictlan.key,owner:'self',zone:'hand'});
const before=JSON.stringify(state);
const activated=R.actions(state,db).actions.filter(a=>a.event.ruleId==='671:mictlan:activate');
assert.equal(activated.length,1,'Mementomictlan activation must be available from hand');
const after=activated[0].state;
assert(after.tokens.some(t=>t.key===mictlan.key&&t.zone==='field'),
 'Activation must place the Field Spell in field zone');
assert.equal(JSON.stringify(state),before,'Scenario must not mutate on analysis');
console.log('Memento portable 316 variants/54 effects, fallback parity, tamper protection and activation passed.');
