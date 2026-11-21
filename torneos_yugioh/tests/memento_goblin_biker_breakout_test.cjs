'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const S=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const Battle=require('../assets/modules/play-calculator/battle.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const study=JSON.parse(fs.readFileSync(path.join(__dirname,
  '../assets/editorial/20261117-folder-delivery-v40/671.json'),'utf8'));
const id='671:goblin-biker-grand-breakout:deck-summon';
const variants=study.calculator.rules.filter(r=>r.id===id);
const fallback=Verified.forDeck(671).filter(r=>r.id===id);
assert.equal(variants.length,2,'Hand and previously Set Quick-Play modes must be represented');
assert.deepEqual(variants,fallback,'Portable definitions must match independently maintained runtime');
assert(variants.every(r=>r.actor.cid===20533&&r.oncePerTurn&&r.chainSpeed===2&&
  r.costs[0].op==='tribute'&&r.costs[0].allowSet===true&&
  r.resolve[0].cid===19366&&r.resolve[0].cannotAttackThisTurn===true),
  'Grand Breakout must tribute a real monster and summon an attack-locked Goblin');
const rows=study.cards.flatMap(c=>(c.slots||[]).map(slot=>({
  id:slot.card_id,deck_id:671,card_name:c.name,section:slot.section,quantity:slot.quantity
})));
const native=E.catalog({id:671,name:'Memento',format_codes:['tcg_advanced']},rows);
const db=S.bind(facts,[{catalog:native,study}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.filter(r=>r.id===id).length,2,'No duplicated fallback rules');
const key=cid=>{
  const c=native.cards.find(c=>db.bindings[c.key]===cid&&c.section==='main');
  assert(c,'Missing authentic Memento Main Deck identity '+cid);return c.key;
};
const add=(s,cid,zone)=>E.place(s,{key:key(cid),owner:'self',zone});
const actions=(s,options={})=>R.actions(s,db,options).actions.filter(a=>a.event?.ruleId===id);
let hand=E.patch(E.create(native),{turn:2,goesFirst:false,active:'self',phase:'main1'});
hand=add(hand,20533,'hand');
hand=add(hand,19364,'m1');
const before=JSON.stringify(hand);
const moves=actions(hand);
assert.equal(moves.length,1,'One own monster may be tributed to summon Goblin');
assert.equal(JSON.stringify(hand),before,'Scenario cannot be mutated by Analyze');
const result=moves[0].state;
assert(result.tokens.some(t=>db.bindings[t.key]===19364&&t.zone==='graveyard'),
  'Tributed Angbruja goes to owner graveyard as a cost');
assert(result.tokens.some(t=>db.bindings[t.key]===20533&&t.zone==='graveyard'),
  'Quick-Play Spell goes to GY after resolution, never stays in hand');
const goblin=result.tokens.find(t=>db.bindings[t.key]===19366&&/^m[1-5]$/.test(t.zone));
assert(goblin,'Authentic Goblin from Main Deck must be Special Summoned');
assert.equal(goblin.attackLockedTurn,result.turn,
  'Summoned Goblin cannot attack for the rest of this turn');
assert.equal(actions(result).length,0,'Once-per-turn use shared across all copies/windows');
const battle=Battle.inspect(E.patch(result,{phase:'battle'}),db);
assert.equal(battle.available,true);
assert(!battle.attacks.some(a=>a.attacker===goblin.uid),
  'Battle analyzer must never recommend an attack with a locked Goblin');
assert(/no puede atacar/.test(battle.reason),'Battle explains the restriction');
assert(battle.assumptions.some(text=>/Gran Fuga/.test(text)),
  'Conditional battle panel must disclose the excluded attacker');
const plannerSource=fs.readFileSync(path.join(__dirname,
  '../assets/modules/play-calculator/planner.js'),'utf8');
assert(plannerSource.includes('t.attackLockedTurn === s.turn'),
  'Planner state deduplication must not merge otherwise identical locked/unlocked boards');
const next=E.patch(result,{turn:3,active:'self',phase:'battle'});
assert(Battle.inspect(next,db).attacks.some(a=>a.attacker===goblin.uid),
  'Attack lock must expire on the following turn');
const gone=E.place(result,{uid:goblin.uid,zone:'graveyard',controller:'self'});
assert(!gone.tokens.find(t=>t.uid===goblin.uid).attackLockedTurn,
  'A monster leaving field must not retain a stale attack lock');
const negated=actions(hand,{negateEffectRuleIds:[id]});
assert.equal(negated.length,1);
assert(negated[0].state.tokens.some(t=>db.bindings[t.key]===19364&&t.zone==='graveyard'));
assert(negated[0].state.tokens.some(t=>db.bindings[t.key]===20533&&t.zone==='graveyard'));
assert(!negated[0].state.tokens.some(t=>db.bindings[t.key]===19366&&/^m[1-5]$/.test(t.zone)),
  'Effect negation preserves tribute cost but cannot invent the Summon');
let set=E.patch(E.create(native),{turn:1,active:'self',phase:'main1'});
set=add(set,20533,'s1');
set=E.position(set,set.tokens.find(t=>db.bindings[t.key]===20533).uid,'set');
set=add(set,19364,'m1');
assert.equal(actions(set).length,0,'Set Quick-Play cannot activate during the Set turn');
set=E.patch(set,{turn:2,active:'opponent',phase:'main1'});
assert.equal(actions(set).length,1,'Previously Set copy is legal in opponent Main Phase');
const setResolved=actions(set)[0].state;
assert(setResolved.tokens.some(t=>db.bindings[t.key]===20533&&t.zone==='graveyard'));
let faceDown=E.clone(hand);
faceDown=E.position(faceDown,faceDown.tokens.find(t=>db.bindings[t.key]===19364).uid,'set');
assert.equal(actions(faceDown).length,1,'Tributing a face-down own monster is legal');
const closed=add(E.create(native),20533,'hand');
assert.equal(actions(closed).length,0,'A monster must exist to pay tribute');
const depleted=add(add(hand,19366,'graveyard'),19366,'graveyard');
assert.equal(actions(depleted).length,0,'Cannot Summon Goblin when no copy remains in Deck');
assert.equal(actions(hand,{specialBlocked:true}).length,0,'Special Summon lock prevents the action');
assert.equal(actions(hand,{graveyardBlocked:true}).length,0,'Cannot tribute if the GY is blocked');
assert.throws(()=>R.validate({...variants[0],resolve:[{...variants[0].resolve[0],cid:19364}]}),
  /Recurso incorrecto/,'Cannot attach Grand Breakout attack lock to an unrelated actor');
console.log(JSON.stringify({ok:true,deck:671,cid:20533,variants:2,
  tributeCost:true,faceDownTribute:true,lockedAttack:true,negation:true,
  setTurnWindow:true,inventoryStrict:true}));
