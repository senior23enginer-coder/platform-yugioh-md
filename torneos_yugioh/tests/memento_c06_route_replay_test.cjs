'use strict';
// C06: actual two-card opening, 5 documented steps, final Goblin kept in hand.
// This does not activate Goblin's separate discard protection or any opponent chain.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
 '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(x=>x.id==='671:A09:C06');
assert(route&&route.steps.length===5,'C06 must have exactly five documented steps');
assert(/Angbruja \+ Fusión/.test(route.opening),'C06 starts with two known cards');
const rows=deck.cards.flatMap(c=>c.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:c.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
for(const id of ['671:twin-dragon:fusion-search','671:shleepy:send',
 '671:memento-fusion:fuse','671:tatsun:mill','671:tecuhtlica:five-names']) {
 assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
  Verified.forDeck(671).filter(r=>r.id===id),'Runtime/imported effect mismatch: '+id);
}
const key=(cid,section='main')=>{
 const c=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section===section);
 assert(c,'Missing physical card '+cid+' in '+section);return c.key;
};
const at=(s,cid,zone)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===zone);
const monsters=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const action=(s,id,opts={})=>R.actions(s,db,opts).actions.filter(a=>a.event?.ruleId===id);
function take(s,id,eligible,label){
 const initial=JSON.stringify(s),candidates=action(s,id);
 const found=candidates.find(a=>eligible(a.state));
 assert(found,label+': no exact transition in '+candidates.length+' actions');
 assert.equal(JSON.stringify(s),initial,'Analysis mutated the captured board');
 assert(found.event?.ruleId===id&&found.source,'C06 requires sourced state transitions');
 return found.state;
}
let s=E.create(catalog);
s=E.place(s,{key:key(19364),owner:'self',zone:'hand'});
s=E.place(s,{key:key(19904),owner:'self',zone:'hand'});
assert.equal(s.tokens.length,2,'C06 starts with only Angwitch and Fusion');
// Step 1: Normal Angwitch, find Tatsun, and Special Tatsun.
s=take(s,'671:19364:normal',n=>at(n,19364,'m1').length===1,'C06-1a normal');
s=take(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,'C06-1b search Tatsun');
s=take(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,'C06-1c Special Tatsun');
// Step 2: Tatsun self-destruct sends level 2+3; Ghattic revives, recovers Shleepy.
s=take(s,'671:tatsun:mill',n=>
 [19362,19472,20199].every(cid=>at(n,cid,'graveyard').length===1),
 'C06-2a Tatsun destroys and sends Ghattic+Shleepy');
s=take(s,'671:ghattic:revive',n=>
 monsters(n).some(t=>db.bindings[t.key]===19472),'C06-2b Ghattic Special');
s=take(s,'671:ghattic:recover',n=>at(n,20199,'hand').length===1,
 'C06-2c Ghattic returns Shleepy');
// Step 3: Use field Angwitch+Ghattic, no GY shuffling for this Fusion.
s=take(s,'671:memento-fusion:fuse',n=>
 monsters(n).length===1&&monsters(n).every(t=>db.bindings[t.key]===19878)&&
 [19364,19472,19904,19362].every(cid=>at(n,cid,'graveyard').length===1)&&
 at(n,20199,'hand').length===1,'C06-3 Twin Dragon Fusion');
assert(s._ruleEvents.some(e=>e.kind==='fusion_summon'&&e.cid===19878));
// Step 4: Exact Twin effect destroys Shleepy in hand and adds two different names.
s=take(s,'671:twin-dragon:fusion-search',n=>
 at(n,20199,'graveyard').length===1&&at(n,19360,'hand').length===1&&
 at(n,19366,'hand').length===1,'C06-4a Twin searches Boss+Goblin');
s=take(s,'671:shleepy:send',n=>at(n,19365,'graveyard').length===1,
 'C06-4b destroyed Shleepy sends Mace');
assert.equal(action(s,'671:twin-dragon:fusion-search').length,0,
 'Twin Dragon effect must not repeat this turn');
// Step 5: Special boss from hand with five GY monsters, Goblin remains in hand.
const five=[19362,19364,19472,20199,19365];
assert(five.every(cid=>at(s,cid,'graveyard').length===1),'Five actual distinct materials required');
s=take(s,'671:tecuhtlica:five-names',n=>
 monsters(n).length===2&&[19878,19360].every(cid=>monsters(n).some(t=>db.bindings[t.key]===cid))&&
 five.every(cid=>!n.tokens.some(t=>db.bindings[t.key]===cid))&&
 at(n,19366,'hand').length===1,'C06-5 boss and saved Goblin');
assert.equal(at(s,19904,'graveyard').length,1,'Fusion Spell must remain in GY');
assert.equal(at(s,19366,'hand').length,1,'Goblin can be held for a later separate Quick Effect');
assert.equal(at(s,20199,'graveyard').length,0,'Destroyed Shleepy was shuffled, not discarded');
assert.equal(s.normalAvailable,false,'Normal already spent');
assert.equal(s.lp.self,8000);assert.equal(s.lp.opponent,8000);
const noShleepy=E.place(E.create(catalog),{key:key(19878,'extra'),owner:'self',zone:'m1'});
assert.equal(action(noShleepy,'671:twin-dragon:fusion-search').length,0,
 'Twin cannot claim Fusion trigger without a real Fusion Summon');
const noFive=E.place(E.create(catalog),{key:key(19360),owner:'self',zone:'hand'});
assert.equal(action(noFive,'671:tecuhtlica:five-names').length,0,'Cannot summon boss without five materials');
console.log(JSON.stringify({ok:true,route:'C06',steps:5,transitions:10,
 starter:[19364,19904],field:[19878,19360],hand:[19366],GY:[19904],
 goblinProtectionActivated:false,opponentResponse:'none'}));
