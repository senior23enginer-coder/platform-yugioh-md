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
let s=E.create(catalog,catalog);
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
let faceDown=E.create(catalog,catalog);
faceDown=E.place(faceDown,{key:key(9139),owner:'self',zone:'s1'});
faceDown=E.position(faceDown,faceDown.nextId-1,'set');
assert.equal(heat(faceDown).length,0,
  'A Spell Set during this Main Phase cannot also have been activated at the start');
const olderSet=E.patch(faceDown,{turn:3,active:'self',phase:'main1'});
const olderActions=heat(olderSet);
assert.equal(olderActions.length,1,'A Normal Spell Set on an earlier turn may activate at start Main1');
assert(olderActions[0].state.tokens.some(t=>db.bindings[t.key]===9139&&t.zone==='graveyard'),
  'The old Set Normal Spell is sent from its S/T zone to the GY after activation');

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
let practice=E.create(catalog);
practice=E.place(practice,{key:key(9139),owner:'self',zone:'hand'});
const practiceLocked=heat(practice)[0].state;
assert.equal(T.effective(practiceLocked).effectMonsterSummonBlocked,true);
assert.equal(T.effective(E.advanceTurn(practiceLocked)).effectMonsterSummonBlocked,undefined,
  'Practice mode has no intervening opponent turn; expire on next own Draw');
// Generic safeguard: Heat Wave must not indiscriminately ban a known Normal Monster.
const sample=E.catalog({id:9002,name:'Fixture Normal/Efecto',format_codes:['tcg_advanced']},[
  {deck_id:9002,id:1,card_name:'Blue-Eyes White Dragon',quantity:1,section:'main'},
  {deck_id:9002,id:2,card_name:'Effect test subject',quantity:1,section:'main'}
]);
const normal=sample.cards[0], effect=sample.cards[1];
const fixtureDb={bindings:{[normal.key]:4007,[effect.key]:999001},
  facts:{4007:{species:'Normal Monster'},999001:{species:'Effect Monster'}},rules:[]};
for(const [id,actor,idx] of [['normal',normal,4007],['effect',effect,999001]]) {
  fixtureDb.rules.push({version:1,id:'9002:'+id+':summon',deckId:9002,
    format:'tcg_advanced',scope:'single-effect',label:'Synthetic '+id+' summon',
    source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=4007&ope=2',
    actor:{side:'self',zones:['hand'],cid:idx},
    guards:[{kind:'state',field:'active',values:['self']},
      {kind:'state',field:'phase',values:['main1']}],
    select:[],costs:[],resolve:[{op:'summon',ref:'actor',from:'hand',
      method:'normal',to:'m1',position:'attack'}],
    tags:[],assumptions:['Synthetic engine guard fixture only'],
    activation:false,oncePerTurn:false});
}
let normalState=E.create(sample);
normalState=E.place(normalState,{key:normal.key,owner:'self',zone:'hand'});
normalState=T.apply(normalState,'effectMonsterSummonBlocked',true,'next-own-draw');
assert(R.actions(normalState,fixtureDb).actions.some(a=>a.event.ruleId==='9002:normal:summon'),
  'Heat Wave must allow the Normal Summon of an actual Normal Monster');
let effectState=E.create(sample);
effectState=E.place(effectState,{key:effect.key,owner:'self',zone:'hand'});
effectState=T.apply(effectState,'effectMonsterSummonBlocked',true,'next-own-draw');
assert(!R.actions(effectState,fixtureDb).actions.some(a=>a.event.ruleId==='9002:effect:summon'),
  'Heat Wave must reject Effect Monster Normal Summon');
console.log('PASS M01 Heat Wave: phase start, both turns, next Draw, effect negation and planner restrictions');
