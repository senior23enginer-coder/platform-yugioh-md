'use strict';
// End-to-end C01: actual imported actions and physical card identities; no
// unspecified opponent response or fictitious chain is inserted.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
  '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(r=>r.id==='671:A09:C01');
assert(route && route.steps.length===7,'A09 C01 must retain exactly 7 steps');
assert.equal(route.replay_certification?.status,'REPLAY_CERTIFIED');
assert.equal(route.replay_certification?.test,'torneos_yugioh/tests/memento_c01_route_replay_test.cjs');
assert.equal(route.replay_certification?.scope,'exact-opening-no-opponent-response');
const rows=deck.cards.flatMap(c=>c.slots.map(x=>({
  id:x.card_id,deck_id:671,card_name:c.name,section:x.section,quantity:x.quantity
})));
const cat=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog:cat,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length, deck.calculator.rules.length);
for(const id of ['671:tatsun:mill','671:creation-king:fusion-send','671:tecuhtlica:five-names'])
  assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
    Verified.forDeck(671).filter(r=>r.id===id),
    'Source/runtime parity of '+id);
function key(cid,section='main') {
  const c=cat.cards.find(c=>db.bindings[c.key]===cid&&c.section===section);
  assert(c,'Missing actual deck card CID '+cid);
  return c.key;
}
const at=(s,cid,zone)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===zone);
const monsters=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const act=(s,id)=>R.actions(s,db).actions.filter(a=>a.event?.ruleId===id);
function apply(s,id,predicate,message){
  const snapshot=JSON.stringify(s);
  const candidates=act(s,id);
  const a=candidates.find(x=>predicate(x.state));
  assert(a,message+': expected state not found among '+candidates.length+' actions');
  assert.equal(JSON.stringify(s),snapshot,'Analysis must not mutate captured state');
  assert(a.event?.ruleId===id&&a.source,'Every action must be traceable');
  return a.state;
}
let s=E.create(cat);
s=E.place(s,{key:key(19364),owner:'self',zone:'hand'});
s=E.place(s,{key:key(19904),owner:'self',zone:'hand'});
// C01 step 1: normal Angwitch and use its summon trigger to find Tatsun.
s=apply(s,'671:19364:normal',n=>at(n,19364,'m1').length===1&&!n.normalAvailable,
  'C01-1a Angwitch Normal Summon');
s=apply(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,
  'C01-1b Angwitch searched Tatsun');
// Step 2: Tatsun from hand, with only Memento face-up.
s=apply(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,
  'C01-2 Tatsun Special Summon');
// Step 3: Tatsun destroys itself and sends two distinct names level 2+3=5.
s=apply(s,'671:tatsun:mill',n=>at(n,19362,'graveyard').length===1&&
  at(n,19472,'graveyard').length===1&&at(n,20199,'graveyard').length===1&&
  at(n,19364,'m1').length===1,
  'C01-3 Tatsun self-destruction sends Ghattic and Shleepy');
// Step 4: Ghattic revives after verified Tatsun send and recovers Shleepy.
s=apply(s,'671:ghattic:revive',n=>monsters(n).some(t=>db.bindings[t.key]===19472),
  'C01-4a Ghattic Special Summon');
s=apply(s,'671:ghattic:recover',n=>at(n,20199,'hand').length===1,
  'C01-4b Ghattic recovers Shleepy');
assert.equal(monsters(s).length,2,'Exactly Angwitch + Ghattic in field');
// Step 5: Fusion uses Angwitch and Ghattic from field, Shleepy from hand.
s=apply(s,'671:memento-fusion:fuse',n=>monsters(n).length===1&&
  monsters(n).every(t=>db.bindings[t.key]===20216)&&
  [19364,19472,20199,19904].every(cid=>at(n,cid,'graveyard').length===1),
  'C01-5 Fusion into Creation King using exact 3 materials');
assert(s._ruleEvents.some(e=>e.kind==='fusion_summon'&&e.cid===20216));
// Step 6: the physical Fusion Summon unlocks Creation King's 3-card send.
s=apply(s,'671:creation-king:fusion-send',n=>
  [19360,19365,19366].every(cid=>at(n,cid,'graveyard').length===1),
  'C01-6 Creation King sends boss, Mace and Goblin');
assert.equal(act(s,'671:creation-king:fusion-send').length,0,
  'The same King cannot send cards again this turn');
// Step 7: 5 other distinct names go back to Deck; Mace remains available.
s=apply(s,'671:tecuhtlica:five-names',n=>
  monsters(n).length===2&&
  [19360,20216].every(cid=>monsters(n).some(t=>db.bindings[t.key]===cid))&&
  [19362,19364,19472,20199,19366].every(cid=>
    !n.tokens.some(t=>db.bindings[t.key]===cid)),
  'C01-7 Combined Creation summons using five documented materials');
assert.equal(at(s,19365,'graveyard').length,1,'Mace remains in GY for future response');
assert.equal(at(s,19904,'graveyard').length,1,'Fusion Quick-Play Spell was consumed');
assert.equal(s.normalAvailable,false,'One Normal Summon consumed');
assert.equal(monsters(s).length,2,'Final field cannot add unsupported monsters');
assert.equal(s.lp.self,8000);
assert.equal(s.lp.opponent,8000);
const noKing=E.place(E.create(cat),{key:key(20216,'extra'),owner:'self',zone:'emz1'});
assert.equal(act(noKing,'671:creation-king:fusion-send').length,0,
  'Placing Creation King manually cannot forge the Fusion Summon trigger');
const withoutTatsun=E.place(E.create(cat),{key:key(19364),owner:'self',zone:'m1'});
assert.equal(act(withoutTatsun,'671:tatsun:mill').length,0,
  'Tatsun effect needs its real actor on field');
console.log(JSON.stringify({ok:true,route:'C01',documentedSteps:7,
  executedTransitions:9,field:[20216,19360],GYRemaining:[19365,19904],
  opponentResponse:'none',generalChainCertification:false}));
