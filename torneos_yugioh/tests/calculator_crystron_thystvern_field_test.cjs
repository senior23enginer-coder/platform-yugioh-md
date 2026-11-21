'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const S=require('../assets/modules/play-calculator/study.js');
const P=require('../assets/modules/play-calculator/planner.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const study=JSON.parse(fs.readFileSync(path.join(__dirname,
  '../assets/editorial/20261117-folder-delivery-v40/677.json'),'utf8'));
const fieldId='677:thystvern:destroy-tuner',gyId='677:thystvern:graveyard-search';
const fieldRules=study.calculator.rules.filter(rule=>rule.id===fieldId);
const gyRules=study.calculator.rules.filter(rule=>rule.id===gyId);
assert.equal(fieldRules.length,2,'Only the real Citree and Tristaros Tuners may be deployed');
assert.deepEqual(fieldRules.map(r=>r.resolve[2].cid).sort((a,b)=>a-b),[12606,20758]);
assert.equal(gyRules.length,5,'Previously verified GY search choices must remain');
assert(fieldRules.every(rule=>rule.actor.cid===12609&&rule.oncePerName&&rule.oncePerTurn&&
  rule.select[0].allowActor===true&&rule.resolve[0].flag==='machineSynchroOnly'&&
  rule.resolve[0].duration==='turn'&&rule.resolve[1].op==='destroy'&&
  rule.resolve[2].summonMethod==='special'),
  'Field effect must destroy as effect, Special Summon Crystron Tuner and lock Extra');
const rows=study.cards.flatMap(c=>c.slots.map(s=>({deck_id:677,id:s.card_id,
  card_name:c.name,section:s.section,quantity:s.quantity})));
const catalog=E.catalog({id:677,name:'Crystron/Horus',format_codes:['tcg_advanced']},rows);
const db=S.bind(facts,[{catalog,study}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
const key=cid=>{
 const card=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section==='main');
 assert(card,'CID missing '+cid);return card.key;
};
const add=(state,cid,zone)=>E.place(state,{key:key(cid),owner:'self',zone});
const actions=(state,id,options={})=>R.actions(state,db,options).actions.filter(a=>a.event?.ruleId===id);
let state=add(E.create(catalog),12609,'m1');
const before=JSON.stringify(state);
const initial=actions(state,fieldId);
assert.equal(initial.length,2,'Thystvern can self-destroy and summon either available Crystron Tuner');
assert.equal(JSON.stringify(state),before,'Pure analyzer cannot mutate physical state');
for(const candidate of initial){
 const next=candidate.state;
 assert(next.tokens.some(t=>db.bindings[t.key]===12609&&t.zone==='graveyard'));
 assert(next.tokens.some(t=>[12606,20758].includes(db.bindings[t.key])&&/^m[1-5]$/.test(t.zone)));
 assert.equal(next._ruleUseTurns['rule:cid:12609'],next.turn,'Shared name/turn marker');
 assert.equal(next._ruleFlags.machineSynchroOnly,true);
 assert.equal(actions(next,gyId).length,0,
   'GY search cannot be used after field effect of Thystvern in same turn');
 assert.equal(actions(next,fieldId).length,0,'Field effect cannot be repeated');
}
const negated=actions(state,fieldId,{negateEffectRuleIds:[fieldId]});
assert.equal(negated.length,2,'Opponent may negate this activated field effect');
assert(negated.every(x=>x.state.tokens.some(t=>db.bindings[t.key]===12609&&t.zone==='m1')));
assert(negated.every(x=>!x.state._ruleFlags?.machineSynchroOnly),'No restriction from a negated effect');
const hidden=E.clone(state);hidden.tokens[0].position='set';
assert.equal(actions(hidden,fieldId).length,0,'Actor must be face up');
assert.equal(actions(state,fieldId,{specialBlocked:true}).length,0,'Special Summon lock rejects effect');
assert.equal(actions(state,fieldId,{blockedZones:['m1','m2','m3','m4','m5']}).length,0,
  'Must have a free physical monster zone after destruction');
let empty=add(state,12606,'hand');
empty=add(empty,20758,'hand');
empty=add(empty,20758,'hand');
assert.equal(actions(empty,fieldId).length,0,'Cannot conjure Tuners already out of Deck');
const extraTarget=add(state,12608,'m2');
assert(actions(extraTarget,fieldId).length>=4,
  'A different face-up owned target is also valid and offers both Tuner choices');
const gy=add(E.create(catalog),12609,'graveyard');
const searched=actions(gy,gyId);
assert.equal(searched.length,5);
const returned=E.clone(searched[0].state);
const consumed=returned.tokens.find(t=>db.bindings[t.key]===12609&&t.zone==='banished');
const again=E.place(returned,{uid:consumed.uid,zone:'m1',controller:'self'});
assert.equal(actions(again,fieldId).length,0,
  'Using GY search must also prevent the alternate field effect that turn');
const plans=P.solve(state,db,{depth:1,budget:300,width:70});
assert(plans.plans.some(p=>p.steps.some(step=>step.label.includes('Thystvern'))),
  'Analyze should enumerate the real field effect');
console.log(JSON.stringify({ok:true,deck_id:677,cid:12609,fieldVariants:2,gyVariants:5,
  effectNameShared:true,tunerInventoryGuards:true,planner:true}));
