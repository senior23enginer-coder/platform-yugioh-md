'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
 '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(r=>r.id==='671:A09:C11');
assert(route&&route.steps.length===6,'C11 must preserve its six steps');
const rows=deck.cards.flatMap(card=>card.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:card.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
for(const id of ['671:memento-fusion:fuse','671:creation-king:fusion-send',
 '671:tecuhtlica:five-names','671:goblin-biker-grand-breakout:deck-summon'])
 assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
 Verified.forDeck(671).filter(r=>r.id===id),'Verified/imported mismatch '+id);
const key=(cid,section='main')=>{
 const c=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section===section);
 assert(c,'Missing physical card '+cid+' in '+section);return c.key;
};
const at=(s,cid,z)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===z&&t.owner==='self');
const mons=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const actions=(s,id,o={})=>R.actions(s,db,o).actions.filter(a=>a.event?.ruleId===id);
function choose(s,id,accept,label){
 const snapshot=JSON.stringify(s),options=actions(s,id);
 const found=options.find(x=>accept(x.state));
 assert(found,label+' not available among '+options.length+' actions');
 assert.equal(JSON.stringify(s),snapshot,'Do not mutate state in analysis');
 assert(found.source&&found.event?.ruleId===id,'Only official sourced actions');
 return found.state;
}
let s=E.create(catalog);
for(const cid of [19364,20533,19904])s=E.place(s,{key:key(cid),owner:'self',zone:'hand'});
assert.equal(s.tokens.length,3,'Exactly Angwitch + Breakout + Fusion in hand');
// Step 1: Normal Witch, search and Special Tatsun.
s=choose(s,'671:19364:normal',n=>at(n,19364,'m1').length===1,'C11-1a Witch Normal');
s=choose(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,'C11-1b Search Tatsun');
s=choose(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,'C11-1c Special Tatsun');
// Step 2: Grand Breakout tributes Witch as COST, then summons actual Goblin from Deck.
s=choose(s,'671:goblin-biker-grand-breakout:deck-summon',n=>
 at(n,19364,'graveyard').length===1&&
 mons(n).some(t=>db.bindings[t.key]===19366&&t.attackLockedTurn===n.turn)&&
 at(n,20533,'graveyard').length===1,'C11-2 Breakout tribute != destruction');
assert(s._ruleEvents.some(e=>e.kind==='tribute'&&e.cid===19364&&e.by==='cost')||
 s._ruleEvents.some(e=>e.kind==='tribute'&&e.cid===19364),
 'Grand Breakout must register Tribute as cost');
assert(!s._ruleEvents.some(e=>e.kind==='destroy'&&e.cid===19364),
 'Tribute must not be represented as destruction');
// Step 3: Tatsun self-destructs, sends Ghattic+Shleepy, Ghattic revives and recovers Shleepy.
s=choose(s,'671:tatsun:mill',n=>
 [19362,19472,20199].every(cid=>at(n,cid,'graveyard').length===1),
 'C11-3a Tatsun sends Level 2+3');
s=choose(s,'671:ghattic:revive',n=>mons(n).some(t=>db.bindings[t.key]===19472),
 'C11-3b Ghattic Special from GY');
s=choose(s,'671:ghattic:recover',n=>at(n,20199,'hand').length===1,
 'C11-3c retrieve Shleepy');
// Step 4: Fusion from real Goblin and Ghattic on field + Shleepy in hand.
s=choose(s,'671:memento-fusion:fuse',n=>
 mons(n).length===1&&mons(n).every(t=>db.bindings[t.key]===20216)&&
 [19364,19362,19366,19472,20199,19904].every(cid=>at(n,cid,'graveyard').length===1),
 'C11-4 Rey Creación from exact 3 distinct materials');
assert(s._ruleEvents.some(e=>e.kind==='fusion_summon'&&e.cid===20216));
assert.equal(mons(s).length,1);
// Step 5: King sends Combined Creation + Mace + Horn Dragon from physical Deck.
s=choose(s,'671:creation-king:fusion-send',n=>
 [19360,19365,19361].every(cid=>at(n,cid,'graveyard').length===1),
 'C11-5 King sends three known Memento');
// Step 6: Five names shuffled, preserving Mace and Shleepy; final two-boss board.
const five=[19364,19362,19366,19472,19361];
s=choose(s,'671:tecuhtlica:five-names',n=>
 mons(n).length===2&&[20216,19360].every(cid=>mons(n).some(t=>db.bindings[t.key]===cid))&&
 five.every(cid=>!n.tokens.some(t=>db.bindings[t.key]===cid)),
 'C11-6 boss with five distinct materials');
assert.equal(at(s,19365,'graveyard').length,1,'Mace should remain in GY');
assert.equal(at(s,20199,'graveyard').length,1,'Shleepy should remain in GY');
assert.equal(at(s,19904,'graveyard').length,1,'Fusion stays spent');
assert.equal(at(s,20533,'graveyard').length,1,'Grand Breakout stays spent');
assert.equal(s.lp.self,8000);assert.equal(s.lp.opponent,8000);
assert.equal(s.normalAvailable,false);
assert.equal(actions(s,'671:goblin-biker-grand-breakout:deck-summon').length,0);
const wrong=E.place(E.create(catalog),{key:key(20533),owner:'self',zone:'hand'});
assert.equal(actions(wrong,'671:goblin-biker-grand-breakout:deck-summon').length,0,
 'Grand Breakout without tribute does not invent Goblin');
const noMaterials=E.place(E.create(catalog),{key:key(19904),owner:'self',zone:'hand'});
assert.equal(actions(noMaterials,'671:memento-fusion:fuse').length,0,
 'Cannot Fusion with missing physical cards');
console.log(JSON.stringify({ok:true,route:'C11',documentedSteps:6,
 executedTransitions:10,starter:[19364,20533,19904],field:[20216,19360],
 graveyard:[19365,20199,19904,20533],tributeAsCost:true,
 opponentResponse:'none',generalChainLegalityCertified:false}));
