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
const ownTarget=scenario(19364,'main','self');
assert.equal(actions(ownTarget).length,1,'Gordian may target a face-up own monster');
const actorToken=start.tokens.find(t=>t.owner==='self'&&db.bindings[t.key]===21203);
const chain=E.appendChain(start,{side:'self',cardName:'Rebanador Gordiano',
  effect:'Devolver monstruo',ruleId:id,actorUid:actorToken.uid});
const preview=Chain.resolve(chain,db);
assert.equal(preview.status,'unresolved','Unverified chain must fail closed');
console.log(JSON.stringify({ok:true,deck:671,card:21203,
  portableVariants:4,oneTarget:true,extraPaidAt:'resolution',
  activationNegationImpossible:true,sideSwapRequired:true,
  entireCardCertified:false}));
