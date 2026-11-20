'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const P=require('../assets/modules/play-calculator/planner.js');
const S=require('../assets/modules/play-calculator/study.js');
const F=require('../assets/modules/play-calculator/card-facts.json');
const T=require('../assets/modules/play-calculator/restrictions.js');
const study=JSON.parse(fs.readFileSync(__dirname+'/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const rows=study.cards.flatMap(c=>c.slots.map(slot=>({
  id:slot.card_id,deck_id:671,card_name:c.name,quantity:slot.quantity,section:slot.section,
})));
const catalog=E.catalog({id:671,name:study.name,format_codes:[study.format]},rows);
const db=S.bind(F,[{catalog,study}]);
const heatId='671:heat-wave:lock';
assert.equal(db.ruleImports[0].status,'accepted');
assert.equal(db.rules.filter(r=>r.id===heatId).length,1,'No duplicated Heat Wave fallback');
const key=cid=>{
  const card=catalog.cards.find(c=>db.bindings[c.key]===cid && c.section==='main');
  assert(card,'Expected Main Deck identity CID '+cid);
  return card.key;
};
let s=E.create(catalog);
s=E.place(s,{key:key(9139),owner:'self',zone:'hand'});
s=E.place(s,{key:key(19364),owner:'self',zone:'hand'});
const heat=state=>R.actions(state,db).actions.filter(a=>a.event.ruleId===heatId);
assert.equal(heat(s).length,1,'Heat Wave at the start of own Main Phase 1');
const original=JSON.stringify(s);
const locked=heat(s)[0].state;
assert.equal(JSON.stringify(s),original,'Analysis never mutates scenario');
assert(locked.tokens.some(t=>db.bindings[t.key]===9139&&t.zone==='graveyard'),
  'Resolved Normal Spell goes to GY, not as activation cost');
assert.equal(T.effective(locked).effectMonsterSummonBlocked,true);
assert.equal(R.actions(locked,db).actions.filter(a=>a.event.ruleId==='671:19364:normal').length,0,
  'Effect Monster Normal Summon must be blocked');
assert.equal(T.effective({...locked,turn:2,active:'opponent'}).effectMonsterSummonBlocked,true,
  'Neither player can summon Effect Monsters on opponent turn');
const nextDraw=E.patch(locked,{turn:3,active:'self',phase:'draw'});
assert.equal(T.effective(nextDraw).effectMonsterSummonBlocked,undefined);
const nextMain=E.patch(nextDraw,{phase:'main1'});
assert(R.actions(nextMain,db).actions.some(a=>a.event.ruleId==='671:19364:normal'),
  'Normal Summon becomes available once Heat Wave expires');
assert.equal(heat(E.patch(s,{phase:'main2'})).length,0,
  'Heat Wave cannot activate at start of Main Phase 2');
assert.equal(heat({...s,_rulePhaseActions:{turn:1,phase:'main1',side:'self',count:1}}).length,0,
  'Heat Wave cannot activate after another known action in Main Phase 1');
const negated=R.actions(s,db,{negateEffectRuleIds:[heatId]}).actions.filter(a=>a.event.ruleId===heatId);
assert.equal(negated.length,1);
assert(!negated[0].state._ruleFlags?.effectMonsterSummonBlocked,
  'A negated Heat Wave does not apply the restriction');
assert(negated[0].state.tokens.some(t=>db.bindings[t.key]===9139&&t.zone==='graveyard'),
  'A negated Normal Spell still goes to GY following resolution');
assert(R.actions(negated[0].state,db).actions.some(a=>a.event.ruleId==='671:19364:normal'),
  'Normal summon remains legal when the Heat Wave effect was negated');
const planner=P.solve({...locked,usedEffects:''},db,{});
assert(planner && Array.isArray(planner.plans),'Planner must still return a bounded answer');
assert(!planner.plans.some(plan=>plan.steps.some(step=>/Invocación Normal.*Angbruja/i.test(step.label))),
  'Legacy planner cannot recommend an Effect Monster Normal Summon under Heat Wave');
console.log('PASS M01 Heat Wave: phase start, both turns, next Draw, effect negation and planner restrictions');
