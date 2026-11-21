'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const S=require('../assets/modules/play-calculator/study.js');
const P=require('../assets/modules/play-calculator/planner.js');
const Contract=require('../assets/modules/play-calculator/contract.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const study=JSON.parse(fs.readFileSync(path.join(__dirname,
  '../assets/editorial/20261117-folder-delivery-v40/677.json'),'utf8'));
const id='677:sarcophagus:send-horus',actorCid=19200,horus=[19153,19155];
const rules=study.calculator.rules.filter(rule=>rule.id===id);
assert.equal(rules.length,2,'Only two verified Horus targets in this inventory');
assert.deepEqual(rules.map(r=>r.resolve[0].cid).sort(),horus);
assert(rules.every(rule=>rule.maxUsesPerTurn===4&&rule.oncePerName&&
  rule.actor.cid===actorCid&&rule.activation&&rule.costs[0].op==='move'&&
  rule.costs[0].ref==='sent'&&rule.costs[0].to==='graveyard'&&
  rule.resolve[0].from==='deck'&&rule.resolve[0].to==='graveyard'),
  'All variants share the precise four-use, hand-cost and Deck-to-GY contract');
assert.equal(Contract.validate(study.calculator,{deckId:677,format:'tcg_advanced',
  actorCids:study.cards.map(c=>c.cid)}).rules,study.calculator.rules.length);
const rows=study.cards.flatMap(c=>c.slots.map(slot=>({
  deck_id:677,id:slot.card_id,card_name:c.name,quantity:slot.quantity,section:slot.section,
})));
const catalog=E.catalog({id:677,name:'Crystron/Horus',format_codes:['tcg_advanced']},rows);
const db=S.bind(facts,[{catalog,study}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.filter(rule=>rule.id===id).length,2,
  'Imported effect must not be duplicated with a fallback');
const key=cid=>{
  const card=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section==='main');
  assert(card,'CID absent from native Main Deck: '+cid);return card.key;
};
const add=(state,cid,zone)=>E.place(state,{key:key(cid),owner:'self',zone});
const actions=(state,options={})=>R.actions(state,db,options).actions.filter(a=>a.event?.ruleId===id);
let state=add(E.create(catalog),actorCid,'s1');
assert.equal(actions(state).length,0,'Hand-cost cannot be fabricated');
for(const cid of [12608,12608,12608,20759,20759])state=add(state,cid,'hand');
const untouched=JSON.stringify(state);
assert(actions(state).length>=2,'Two Horus alternatives need to be offered when both remain in Deck');
assert.equal(JSON.stringify(state),untouched,'Analyzer must not mutate the captured state');
const hidden=E.clone(state);
hidden.tokens.find(t=>db.bindings[t.key]===actorCid).position='set';
assert.equal(actions(hidden).length,0,'A Set Sarcophagus is not active');
assert.equal(actions(E.patch(state,{phase:'battle'})).length,0,'Sarcophagus ignition is Main Phase only');
assert.equal(actions(state,{graveyardBlocked:true}).length,0,
  'Cannot send hand and Deck cards if the GY is explicitly blocked');
// A negated effect still pays its true hand cost, but sends no Horus.
const negated=actions(state,{negateEffectRuleIds:[id]});
assert(negated.length>0,'Sarcophagus activation may be negated by a supported response');
const negState=negated[0].state;
assert.equal(negState.tokens.filter(t=>t.zone==='hand').length,4);
assert.equal(negState.tokens.filter(t=>t.zone==='graveyard'&&
  horus.includes(db.bindings[t.key])).length,0,'Negated effect cannot fabricate a Horus');
let last=state;
for(let usage=1;usage<=4;usage++){
  const candidates=actions(last);
  assert(candidates.length>0,'Horus send is available for use '+usage);
  const next=candidates[0].state;
  assert.equal(next._ruleUseCounts?.['rule:cid:19200']?.turn,next.turn);
  assert.equal(next._ruleUseCounts?.['rule:cid:19200']?.count,usage,
    'Four-use counter is shared across effect variants and copies');
  assert.equal(next.tokens.filter(t=>t.zone==='hand').length,5-usage,
    'Exactly one hand card pays the activation cost');
  assert.equal(next.tokens.filter(t=>t.zone==='graveyard'&&
    horus.includes(db.bindings[t.key])).length,usage,
    'Exactly one identified Horus sent from the Deck per resolved use');
  last=next;
}
const returned=E.clone(last);
const freed=returned.tokens.find(t=>t.zone==='graveyard'&&horus.includes(db.bindings[t.key]));
assert(freed,'A Horus must have been sent to the Graveyard');
const recycled=E.place(returned,{uid:freed.uid,zone:'deck',controller:'self'});
assert.equal(actions(recycled).length,0,
  'A fifth use must be forbidden even if a legal Horus becomes available in Deck');
const secondCopy=add(recycled,actorCid,'s2');
assert.equal(actions(secondCopy).length,0,'A second Sarcophagus must not reset the name-wide cap');
const nextTurn=E.patch(recycled,{turn:recycled.turn+1});
assert(actions(nextTurn).length>0,'Four-use limit resets on a new turn');
const revive=R.actions(recycled,db).actions.filter(a=>
  a.event?.ruleId==='677:horus-gy:'+db.bindings[recycled.tokens.find(t=>t.zone==='graveyard'&&
    horus.includes(db.bindings[t.key]))?.key]);
assert(revive.length>0,'The Horus GY procedure must remain independently available while Sarcophagus is face-up');
// Planners must keep counts distinct even when the board otherwise appears unchanged.
const plans=P.solve(state,db,{depth:2,budget:500,width:80});
assert(plans.plans.some(plan=>plan.steps.some(s=>s.label.includes('Sarcófago'))),
  'Analyze should propose the verified Sarcophagus setup, not a handtrap Normal Summon');
const malformed=JSON.parse(JSON.stringify(rules[0]));malformed.maxUsesPerTurn=5;
assert.throws(()=>R.validate(malformed),/contador de cuatro usos/);
console.log(JSON.stringify({ok:true,deck_id:677,rule:id,variants:2,
  maxUses:4,sharedName:true,noFifthUse:true,independentHorusRevive:true,planner:true}));
