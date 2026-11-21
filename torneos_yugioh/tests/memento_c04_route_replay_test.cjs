'use strict';
// C04 uses a one-card starting hand; every later hand/GY resource comes from
// a recorded effect and the unchanged canonical deck (no supplemental draws).
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
  '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(x=>x.id==='671:A09:C04');
assert(route&&route.steps.length===8,'C04 must retain eight documentary steps');
assert.equal(route.replay_certification?.status,'REPLAY_CERTIFIED');
assert.equal(route.replay_certification?.test,'torneos_yugioh/tests/memento_c04_route_replay_test.cjs');
assert.equal(route.replay_certification?.scope,'exact-opening-no-opponent-response');
const rows=deck.cards.flatMap(c=>c.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:c.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
for(const id of ['671:angwitch:destroy-revive-mace','671:memento-fusion:fuse',
  '671:creation-king:fusion-send','671:tecuhtlica:five-names'])
 assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
   Verified.forDeck(671).filter(r=>r.id===id),'Runtime and canonical rules differ: '+id);
const key=cid=>{
 const c=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section==='main');
 assert(c,'Main CID is missing '+cid);return c.key;
};
const at=(s,cid,zone)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===zone);
const monsters=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const actions=(s,id,options={})=>R.actions(s,db,options).actions.filter(a=>a.event?.ruleId===id);
function step(s,id,test,label) {
 const before=JSON.stringify(s),candidates=actions(s,id);
 const chosen=candidates.find(a=>test(a.state));
 assert(chosen,label+' not achievable among '+candidates.length+' transitions');
 assert.equal(JSON.stringify(s),before,'Planner must not mutate duel capture');
 assert(chosen.event?.ruleId===id&&chosen.source,'Unverified C04 transition');
 return chosen.state;
}
let s=E.create(catalog);
s=E.place(s,{key:key(19364),owner:'self',zone:'hand'});
assert.equal(s.tokens.length,1,'C04 starts with Angwitch only');
// 1 Angwitch Normal into Tatsun hand.
s=step(s,'671:19364:normal',n=>at(n,19364,'m1').length===1,'C04-1a Normal Angwitch');
s=step(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,
 'C04-1b Search Tatsun');
// 2 Tatsun Special, self-destruct, sends Goblin+Mace+Ghattic (1+1+2 <= 5).
s=step(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,'C04-2a Tatsun special');
s=step(s,'671:tatsun:mill',n=>
 at(n,19362,'graveyard').length===1&&
 [19366,19365,19472].every(cid=>at(n,cid,'graveyard').length===1)&&
 at(n,19364,'m1').length===1,'C04-2b Tatsun mill known three');
// 3 Ghattic revives and recovers Goblin to hand.
s=step(s,'671:ghattic:revive',n=>monsters(n).length===2&&
 monsters(n).some(t=>db.bindings[t.key]===19472),'C04-3a Ghattic revival');
s=step(s,'671:ghattic:recover',n=>at(n,19366,'hand').length===1,
 'C04-3b Ghattic returns Goblin');
// 4 Angwitch destroys herself to special summon actual Mace in GY.
s=step(s,'671:angwitch:destroy-revive-mace',n=>at(n,19364,'graveyard').length===1&&
 monsters(n).some(t=>db.bindings[t.key]===19365)&&
 monsters(n).some(t=>db.bindings[t.key]===19472),'C04-4 Revive Mace');
// 5 Mace destroys itself and adds Fusion; Ghattic remains.
s=step(s,'671:mace:search',n=>at(n,19365,'graveyard').length===1&&
 at(n,19904,'hand').length===1&&monsters(n).length===1,'C04-5 Mace finds Memento Fusion');
assert.equal(at(s,19366,'hand').length,1,'Goblin must remain in hand');
// 6 Fusion: Ghattic field+Goblin hand+Tatsun GY, shuffle Tatsun into Deck.
s=step(s,'671:memento-fusion:fuse',n=>
 monsters(n).length===1&&monsters(n).every(t=>db.bindings[t.key]===20216)&&
 [19472,19366,19364,19365,19904].every(cid=>at(n,cid,'graveyard').length===1)&&
 n.tokens.every(t=>db.bindings[t.key]!==19362),'C04-6 Fusion must use GY Tatsun');
assert(s._ruleEvents.some(x=>x.kind==='fusion_summon'&&x.cid===20216));
// 7 Creation King sends Combined Creation + Shleepy + Horn Dragon from Deck.
s=step(s,'671:creation-king:fusion-send',n=>
 [19360,20199,20491].every(cid=>at(n,cid,'graveyard').length===1),
 'C04-7 King send exact three');
// 8 Shuffle five different GY names, summon 5000 boss alongside the King.
s=step(s,'671:tecuhtlica:five-names',n=>
 monsters(n).length===2&&
 [20216,19360].every(cid=>monsters(n).some(t=>db.bindings[t.key]===cid))&&
 [19364,19365,19472,19366,20491].every(cid=>
  !n.tokens.some(t=>db.bindings[t.key]===cid)),
 'C04-8 Combined Creation distinct five');
assert.equal(at(s,20199,'graveyard').length,1,'Shleepy must remain in GY');
assert.equal(at(s,19904,'graveyard').length,1,'Fusion Spell must remain in GY');
assert.equal(s.normalAvailable,false,'Original normal spent');
assert.equal(s.lp.self,8000);
assert.equal(s.lp.opponent,8000);
const noMace=E.create(catalog);
assert.equal(actions(noMace,'671:angwitch:destroy-revive-mace').length,0,
 'No graveyard target means no Angwitch recovery');
const noGY=E.place(E.create(catalog),{key:key(19904),owner:'self',zone:'hand'});
assert.equal(actions(noGY,'671:memento-fusion:fuse').length,0,
 'A known Fusion Spell without material cannot produce a Fusion Monster');
console.log(JSON.stringify({ok:true,route:'C04',documentedSteps:8,
 executedTransitions:11,opening:['Angwitch'],field:[20216,19360],
 graveyard:[20199,19904],opponentResponse:'none',fullyGeneral:false}));
