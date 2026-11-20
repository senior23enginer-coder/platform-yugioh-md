'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const C=require('../assets/modules/play-calculator/contract.js');
const P=require('../assets/modules/play-calculator/planner.js');
const base=require('../assets/modules/play-calculator/card-facts.json');
const editorial=JSON.parse(fs.readFileSync(path.join(__dirname,'../assets/editorial/20261117-folder-delivery-v40/677.json'),'utf8'));
assert.equal(editorial.deck_id,677);
assert.equal(editorial.routes.length,14,'Never delete historical Crystron routes');
assert.equal(editorial.calculator?.schema,C.SCHEMA);
assert.deepEqual(editorial.calculator.rules.map(r=>r.actor.cid).sort(),[19153,19155]);
const rows=editorial.cards.flatMap(c=>(c.slots||[]).map(slot=>({
  deck_id:677,id:slot.card_id,card_name:c.name,quantity:slot.quantity,section:slot.section
})));
const native=E.catalog({id:677,name:'Crystron Horus',format_codes:['tcg_advanced']},rows);
const db=Study.bind(base,[{catalog:native,study:editorial}]);
assert.equal(db.ruleImports.length,1);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.ruleImports[0].count,2);
const add=(s,cid,zone,position='attack')=>{
 const c=native.cards.find(x=>db.bindings[x.key]===cid);
 assert(c,'Missing native slot for '+cid);
 const uid=s.nextId;
 return E.position(E.place(s,{key:c.key,owner:'self',controller:'self',zone}),uid,position);
};
const action=(s,cid,opts={})=>R.actions(s,db,opts).actions.filter(a=>a.used==='rule:677:horus-gy:'+cid);
let state=E.create(native);
state=add(state,19200,'s3');
state=add(state,19153,'graveyard');
state=add(state,19155,'graveyard');
const original=JSON.stringify(state);
for(const cid of [19153,19155]){
 const actions=action(state,cid);
 assert.equal(actions.length,1,'One portable rule per Horus name: '+cid);
 const summoned=actions[0].state.tokens.find(t=>db.bindings[t.key]===cid);
 assert(/^m[1-5]$/.test(summoned.zone),'Horus must reach a real monster zone');
 assert.equal(action(actions[0].state,cid).length,0,'Once per name/turn');
}
assert.equal(JSON.stringify(state),original,'Do not mutate captured duel state');
assert.equal(action(state,19153,{specialBlocked:true}).length,0);
assert.equal(action(state,19153,{graveyardBlocked:true}).length,0);
let covered=E.clone(state);
const fillers=native.cards.filter(card=>card.section==='main' &&
  ![19153,19155,19200].includes(db.bindings[card.key])).slice(0,5);
assert.equal(fillers.length,5,'At least five native Main Deck identities for full-board test');
for(let i=1;i<=5;i++) {
 covered=E.place(covered,{key:fillers[i-1].key,owner:'self',controller:'self',zone:'m'+i});
}
assert.equal(action(covered,19155).length,0,'No field slot means no summon');
let blocked=E.clone(state);
blocked.tokens.find(t=>db.bindings[t.key]===19200).position='set';
assert.equal(action(blocked,19153).length,0,'Face-down Sarcophagus does not count');
let absent=E.clone(state);
absent.tokens=absent.tokens.filter(t=>db.bindings[t.key]!==19200);
assert.equal(action(absent,19153).length,0,'Sarcophagus must be controlled');
const plans=P.solve(state,db,{depth:2,budget:200,width:30});
assert(plans.plans.some(p=>p.steps.some(s=>s.label.includes('Amset'))),'Planner must incorporate portable rule');
assert(!plans.plans.some(p=>p.steps.filter(s=>s.label.includes('Amset')&&s.label.includes('Cementerio')).length>1),'No duplicate hardcoded and portable summon');
console.log('Portable Crystron Horus rules: accepted, state-dependent, once-per-name and immutable.');
