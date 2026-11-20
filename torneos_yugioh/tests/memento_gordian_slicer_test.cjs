'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const E = require('../assets/modules/play-calculator/engine.js');
const R = require('../assets/modules/play-calculator/rules.js');
const Study = require('../assets/modules/play-calculator/study.js');
const Chain = require('../assets/modules/play-calculator/chain.js');
const Facts = require('../assets/modules/play-calculator/card-facts.json');
const raw = JSON.parse(fs.readFileSync(
  __dirname + '/../assets/editorial/20261117-folder-delivery-v40/671.json', 'utf8'));
const id = '671:gordian:one-monster-six-extra';
const variants = raw.calculator.rules.filter(r => r.id === id);
assert.equal(variants.length, 4, 'Single target / each owner and source section');
const materialCids = [20216,19878,16845,20389,19188,20225];
const card = cid => {
  const item = raw.cards.find(c => c.cid === cid);
  assert(item, 'Missing verified CID '+cid);
  return item;
};
const originalRows = raw.cards.flatMap(c => c.slots
  .filter(x => x.section === 'main' || x.section === 'extra')
  .map(x => ({id:x.card_id,deck_id:671,card_name:c.name,section:x.section,quantity:x.quantity})));
const original = E.catalog({id:671,name:'Memento',format_codes:[raw.format]},originalRows);
const originalDb = Study.bind(Facts,[{catalog:original,study:raw}]);
assert.equal(originalDb.ruleImports[0].status,'accepted');
assert(!original.cards.some(c => originalDb.bindings[c.key] === 21203),
  'Gordian Slicer is in Side and must not be available in Main before swapping');
const swappedRows = originalRows.map(row => {
  if(row.id === card(9139).slots[0].card_id)
    return {...row,quantity:row.quantity-1};
  return row;
});
swappedRows.push({id:card(21203).slots[0].card_id,deck_id:671,
  card_name:card(21203).name,section:'main',quantity:1});
const catalog = E.catalog({id:671,name:'Memento con intercambio de Side',
  format_codes:[raw.format]},swappedRows);
const db = Study.bind(Facts,[{catalog,study:raw}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.filter(r=>r.id===id).length,4,'Do not duplicate portable/fallback variants');
const key=(cid,section) => {
  const found=catalog.cards.find(c=>db.bindings[c.key] === cid &&
    (!section || c.section === section));
  assert(found, 'Missing actual catalog CID '+cid);
  return found.key;
};
const actions=s=>R.actions(s,db).actions.filter(a=>a.event.ruleId===id);
function scenario(targetCid=19364,targetSection='main',side='opponent') {
  let s = E.create(catalog,catalog);
  s = E.patch(s,{active:'self',phase:'main1'});
  s = E.place(s,{key:key(21203,'main'),owner:'self',zone:'hand'});
  for(const cid of materialCids)
    s = E.place(s,{key:key(cid,'extra'),owner:'self',zone:'extra'});
  s = E.place(s,{key:key(targetCid,targetSection),owner:side,
    zone:targetSection==='extra'?'emz2':'m1'});
  return s;
}
const start=scenario(),immutable=JSON.stringify(start);
const candidates=actions(start);
assert.equal(candidates.length,1,'One opponent face-up Main target is legal');
const end=candidates[0].state;
assert.equal(JSON.stringify(start),immutable,'The simulator must not mutate the captured duel');
assert(end.tokens.some(t=>t.owner==='opponent'&&t.zone==='hand'&&
  db.bindings[t.key]===19364),'Main monster must return to its owner hand');
assert(end.tokens.some(t=>t.owner==='self'&&t.zone==='graveyard'&&
  db.bindings[t.key]===21203),'Normal Spell must go to GY after resolution');
const banished=end.tokens.filter(t=>t.owner==='self'&&t.zone==='banished'&&
  t.position==='set'&&materialCids.includes(db.bindings[t.key]));
assert.equal(banished.length,6,'Exactly six distinct Extra cards must be banished facedown');
assert.equal(new Set(banished.map(t=>t.uid)).size,6);
assert(end._ruleEvents.some(e=>e.kind==='banish_extra_face_down_cost'&&e.stage==='resolve'),
  'Six-card banishment occurs during resolution, never as activation cost');
const prepared=R.actions(start,db,{deferResolution:true}).actions.filter(a=>a.event.ruleId===id);
assert.equal(prepared.length,1);
assert.equal(prepared[0].state.tokens.filter(t=>t.zone==='banished').length,0,
  'Extra Deck is untouched when the Spell is activated and waiting to resolve');
for (const option of [
  {negateEffectRuleIds:[id]},{negateActivationRuleIds:[id]}
]) assert.equal(R.actions(start,db,option).actions.filter(a=>a.event.ruleId===id).length,0,
  'This official Spell cannot have its activation or effect negated');
const missing=E.clone(start);
const one=missing.tokens.find(t=>t.owner==='self'&&t.zone==='extra');
missing.tokens=missing.tokens.filter(t=>t.uid!==one.uid);
assert.equal(actions(missing).length,0,'Five physical Extra cards cannot pay for one target');
const facedown=E.clone(start);
const victim=facedown.tokens.find(t=>t.owner==='opponent'&&t.zone==='m1');
victim.position='set';
assert.equal(actions(facedown).length,0,'Cannot target a face-down monster');
assert.equal(actions({...start,_rulePhaseActions:{
  turn:start.turn,phase:'main1',side:'self',count:1}}).length,0,
  'Gordian Slicer cannot activate after another recorded Main Phase action');
assert.equal(actions(E.patch(start,{phase:'battle'})).length,0,
  'Only at start of Main Phase 1 or 2');
assert.equal(actions(E.patch(start,{phase:'main2'})).length,1,
  'Gordian Slicer also activates at the start of Main Phase 2');
assert.equal(R.actions(start,db,{banishBlocked:true}).actions.filter(a=>a.event.ruleId===id).length,0,
  'Cannot banish if a verified banishment lock is active');
const extraTarget=scenario(19188,'extra');
const extraPlans=actions(extraTarget);
assert.equal(extraPlans.length,1);
assert(extraPlans[0].state.tokens.some(t=>t.owner==='opponent'&&t.zone==='extra'&&
  db.bindings[t.key]===19188),
  'An Extra monster returns to its owner Extra Deck, not the hand');
// The official target is any FACE-UP card, including a Field Spell and
// face-up Spell/Trap in the Main Deck section, not just monsters.
function opponentSpellScenario(cid,zone) {
  let state=E.clone(start);
  const old=state.tokens.find(t=>t.owner==='opponent'&&t.zone==='m1');
  state=E.place(state,{uid:old.uid,zone:'deck',controller:'opponent'});
  state=E.place(state,{key:key(cid,'main'),owner:'opponent',zone});
  return state;
}
const fieldSpell=opponentSpellScenario(19367,'field');
const fieldPlans=actions(fieldSpell);
assert.equal(fieldPlans.length,1,'Gordian can target a face-up opponent Field Spell');
assert(fieldPlans[0].state.tokens.some(t=>t.owner==='opponent'&&
  db.bindings[t.key]===19367&&t.zone==='hand'),
  'Face-up Field Spell must return to owner hand');
const settable=opponentSpellScenario(19904,'s1');
assert.equal(actions(settable).length,1,'Gordian can return a face-up Quick-Play Spell');
const down=E.position(settable,settable.tokens.find(t=>t.owner==='opponent'&&t.zone==='s1').uid,'set');
assert.equal(actions(down).length,0,'A Set Spell or Trap is not a face-up target');
const ownTarget=scenario(19364,'main','self');
assert.equal(actions(ownTarget).length,1,'Gordian may target a face-up own monster');
const actorToken=start.tokens.find(t=>t.owner==='self'&&db.bindings[t.key]===21203);
const chain=E.appendChain(start,{side:'self',cardName:'Rebanador Gordiano',
  effect:'Devolver monstruo',ruleId:id,actorUid:actorToken.uid});
const preview=Chain.resolve(chain,db);
assert.equal(preview.status,'unresolved','Unverified chain must fail closed');
// Alternate official single-target payment: banish ONE other card in hand
// face down at resolution. Never banish Gordian itself to pay its own effect.
const oneHandId='671:gordian:one-monster-one-hand';
const handActions=st=>R.actions(st,db).actions.filter(a=>a.event.ruleId===oneHandId);
assert.equal(handActions(start).length,0,
  'Gordian alone in hand cannot pay a different card from hand');
let handStart=E.place(start,{key:key(19365,'main'),owner:'self',zone:'hand'});
const handField=E.place(fieldSpell,{key:key(19365,'main'),owner:'self',zone:'hand'});
assert.equal(handActions(handField).length,1,
  'Hand payment alternative also handles a face-up opponent Field Spell');
const handPlans=handActions(handStart);
assert.equal(handPlans.length,1,'Exactly one other hand payment and one face-up target');
const handPaid=handPlans[0].state;
assert(handPaid.tokens.some(t=>db.bindings[t.key]===19365&&
  t.owner==='self'&&t.zone==='banished'&&t.position==='set'),
  'Hand payment must be banished face down, not discarded to GY');
assert(handPaid.tokens.some(t=>db.bindings[t.key]===19364&&
  t.owner==='opponent'&&t.zone==='hand'),
  'Opponent Main target must return to original owner after hand banishment');
assert(handPaid.tokens.some(t=>db.bindings[t.key]===21203&&
  t.owner==='self'&&t.zone==='graveyard'),
  'Gordian Normal Spell goes to GY after its non-negatable resolution');
const handPrepared=R.actions(handStart,db,{deferResolution:true}).actions
  .filter(a=>a.event.ruleId===oneHandId);
assert.equal(handPrepared.length,1);
assert(!handPrepared[0].state.tokens.some(t=>t.key===key(19365,'main')&&
  t.zone==='banished'),'Banish from hand only at resolution');
let fiveExtra=E.clone(handStart);
const removal=fiveExtra.tokens.find(t=>t.owner==='self'&&t.zone==='extra');
fiveExtra.tokens=fiveExtra.tokens.filter(t=>t.uid!==removal.uid);
assert.equal(actions(fiveExtra).length,0,'Six Extra alternative needs all six');
assert.equal(handActions(fiveExtra).length,1,
  'Hand alternative remains legal even if Extra has only five cards');
assert.equal(R.actions(handStart,db,{banishBlocked:true}).actions
  .filter(a=>a.event.ruleId===oneHandId).length,0,
  'Banish lock also forbids the hand payment');
assert.equal(R.actions(handStart,db,{negateEffectRuleIds:[oneHandId]}).actions
  .filter(a=>a.event.ruleId===oneHandId).length,0,
  'Hand alternative cannot be negated either');
console.log(JSON.stringify({ok:true,deck:671,card:21203,
  portableVariants:8,oneTarget:true,extraPaidAt:'resolution',
  activationNegationImpossible:true,sideSwapRequired:true,
  entireCardCertified:false}));
