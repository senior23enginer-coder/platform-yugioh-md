'use strict';
// C05 begins with only one Dark Blade; the two Fusion Spells are separate
// physically tracked copies, not a reused/fabricated card.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
 '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(r=>r.id==='671:A09:C05');
assert(route&&route.steps.length===9,'C05 retains nine documentary steps');
const rows=deck.cards.flatMap(c=>c.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:c.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
for(const id of ['671:dark-blade:goblin','671:angwitch:destroy-revive-mace',
 '671:goblin:send-two','671:memento-fusion:graveyard-search',
 '671:memento-fusion:fuse','671:creation-king:fusion-send','671:tecuhtlica:five-names'])
 assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
  Verified.forDeck(671).filter(r=>r.id===id),'Imported/fallback rule mismatch '+id);
const key=cid=>{
 const card=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section==='main');
 assert(card,'Missing actual Main card CID '+cid);return card.key;
};
const at=(s,cid,zone)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===zone);
const field=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const actions=(s,id,o={})=>R.actions(s,db,o).actions.filter(a=>a.event?.ruleId===id);
function apply(s,id,accept,label) {
 const before=JSON.stringify(s),candidates=actions(s,id);
 const a=candidates.find(t=>accept(t.state));
 assert(a,label+': no matching replay step from '+candidates.length+' candidates');
 assert.equal(JSON.stringify(s),before,'Capture state mutated during analysis');
 assert(a.event?.ruleId===id&&a.source,'Only verified source rules can advance route');
 return a.state;
}
let s=E.create(catalog);
s=E.place(s,{key:key(19363),owner:'self',zone:'hand'});
assert.equal(s.tokens.length,1,'One-card Dark Blade starting hand');
// Step 1 Normal Dark Blade; destroy itself and Special Angwitch from Deck.
s=apply(s,'671:19363:normal',n=>at(n,19363,'m1').length===1,'C05-1a Normal Dark Blade');
s=apply(s,'671:dark-blade:goblin',n=>at(n,19363,'graveyard').length===1&&
 field(n).some(t=>db.bindings[t.key]===19364),'C05-1b Summon Angwitch');
// Step 2 Angwitch search Tatsun and its Special Summon from hand.
s=apply(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,'C05-2a Search Tatsun');
s=apply(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,'C05-2b Special Tatsun');
// Step 3 Tatsun destroys itself, sends Goblin+Mace+Ghattic; Ghattic revives and recovers Mace.
s=apply(s,'671:tatsun:mill',n=>
 [19362,19366,19365,19472].every(cid=>at(n,cid,'graveyard').length===1),
 'C05-3a Self-destruct and mill 3');
s=apply(s,'671:ghattic:revive',n=>field(n).some(t=>db.bindings[t.key]===19472),'C05-3b Ghattic revive');
s=apply(s,'671:ghattic:recover',n=>at(n,19365,'hand').length===1,
 'C05-3c Return Mace');
// Step 4 Angwitch destroys self and revives Goblin from GY.
s=apply(s,'671:angwitch:destroy-revive-mace',n=>
 at(n,19364,'graveyard').length===1&&field(n).some(t=>db.bindings[t.key]===19366),
 'C05-4 Angwitch revives Goblin');
// Step 5 Goblin destroys self and sends physical Fusion + Combined Creation.
s=apply(s,'671:goblin:send-two',n=>at(n,19366,'graveyard').length===1&&
 at(n,19904,'graveyard').length===1&&at(n,19360,'graveyard').length===1,
 'C05-5 Goblin send two distinct Memento cards');
// Step 6 activate first Fusion in GY, banish it, destroy Ghattic to find second Fusion.
s=apply(s,'671:memento-fusion:graveyard-search',n=>at(n,19904,'banished').length===1&&
 at(n,19472,'graveyard').length===1&&at(n,19904,'hand').length===1,
 'C05-6 First Fusion GY banish and second Fusion search');
assert.equal(s.tokens.filter(t=>db.bindings[t.key]===19904).length,2,
 'Two real Fusion card copies must exist after the search');
// Step 7 second Fusion consumes Mace hand + Tatsun and Goblin GY.
s=apply(s,'671:memento-fusion:fuse',n=>field(n).length===1&&
 field(n).every(t=>db.bindings[t.key]===20216)&&
 [19362,19366].every(cid=>n.tokens.every(t=>db.bindings[t.key]!==cid))&&
 at(n,19365,'graveyard').length===1,'C05-7 Fusion with two GY materials');
assert(s._ruleEvents.some(e=>e.kind==='fusion_summon'&&e.cid===20216));
// Step 8 King sends Akihiron, Shleepy and actual Horn Dragon CID19361.
s=apply(s,'671:creation-king:fusion-send',n=>
 [20491,20199,19361].every(cid=>at(n,cid,'graveyard').length===1),
 'C05-8 King sends three distinct names');
// Step 9 Boss returns 5 different names, preserves Akihiron + Shleepy.
s=apply(s,'671:tecuhtlica:five-names',n=>
 field(n).length===2&&[20216,19360].every(cid=>field(n).some(t=>db.bindings[t.key]===cid))&&
 [19363,19364,19472,19365,19361].every(cid=>
 !n.tokens.some(t=>db.bindings[t.key]===cid)),
 'C05-9 Combined Creation returns 5 real materials');
assert.equal(at(s,20491,'graveyard').length,1,'Akhiron must stay as a GY resource');
assert.equal(at(s,20199,'graveyard').length,1,'Shleepy must stay in the GY');
assert.equal(at(s,19904,'banished').length,1,'First Fusion remains banished');
assert.equal(at(s,19904,'graveyard').length,1,'Second Fusion remains GY');
assert.equal(s.normalAvailable,false);
assert.equal(s.lp.self,8000);
assert.equal(s.lp.opponent,8000);
assert.equal(field(s).length,2,'No undocumented extra monster');
const blocked=E.place(E.create(catalog),{key:key(19904),owner:'self',zone:'hand'});
assert.equal(actions(blocked,'671:memento-fusion:fuse').length,0,
 'Do not propose Fusion without actual material');
console.log(JSON.stringify({ok:true,route:'C05',documentedSteps:9,
 executedTransitions:13,oneCardStarter:true,physicalFusionCopies:2,
 field:[20216,19360],GY:[20491,20199,19904],banished:[19904],
 opponentResponse:'none'}));
