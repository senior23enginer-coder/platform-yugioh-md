'use strict';
// C12: Horn Dragon's own destruction by Bone Party, three physical face-up
// targets including one of our Memento, and no invented opponent cards.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
 '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(x=>x.id==='671:A09:C12');
assert(route&&route.steps.length===5,'C12 source must have 5 documented steps');
const rows=deck.cards.flatMap(card=>card.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:card.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
for(const id of ['671:tatsun:mill','671:horned:special','671:bone-party:main','671:horned:destruction'])
 assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
  Verified.forDeck(671).filter(r=>r.id===id),'Fallback/portable parity: '+id);
const key=(cid,section='main')=>{
 const c=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section===section);
 assert(c,'Missing real CID '+cid);return c.key;
};
const at=(s,cid,z,owner='self')=>s.tokens.filter(t=>
 db.bindings[t.key]===cid&&t.zone===z&&t.owner===owner);
const field=(s,owner='self')=>s.tokens.filter(t=>t.controller===owner&&E.isMonster(t.zone));
const actions=(s,id,o={})=>R.actions(s,db,o).actions.filter(a=>a.event?.ruleId===id);
function replay(s,id,check,label){
 const previous=JSON.stringify(s),choices=actions(s,id),found=choices.find(a=>check(a.state));
 assert(found,label+': no state in '+choices.length+' candidates');
 assert.equal(JSON.stringify(s),previous,'Planner must not mutate C12 input');
 assert(found.event?.ruleId===id&&found.source,'Actions must be verified');
 return found.state;
}
let s=E.create(catalog);
for(const cid of [19364,19361,19368])s=E.place(s,{key:key(cid),owner:'self',zone:'hand'});
for(const [cid,z] of [[19365,'m3'],[19366,'m4']])
 s=E.place(s,{key:key(cid),owner:'opponent',zone:z});
assert.equal(field(s,'opponent').length,2,'Two actual face-up opponent monsters');
// 1 Witch Normal, find Tatsun and Special it from hand.
s=replay(s,'671:19364:normal',n=>at(n,19364,'m1').length===1,'C12-1a Normal Witch');
s=replay(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,'C12-1b Find Tatsun');
s=replay(s,'671:tatsun:special',n=>
 field(n).some(t=>db.bindings[t.key]===19362),'C12-1c Special Tatsun');
// 2 Tatsun destroys itself, sends Goblin+Mace+Ghattic, optional revive NOT used.
s=replay(s,'671:tatsun:mill',n=>
 [19362,19366,19365,19472].every(cid=>at(n,cid,'graveyard').length===1)&&
 field(n).length===1&&field(n).every(t=>db.bindings[t.key]===19364),
 'C12-2 Tatsun exact self-destruction and mills');
assert.equal(field(s).length,1,'Ghattic optional revival is not automatic');
// 3 Horn Dragon from hand with 4 physical distinct Memento names in GY.
s=replay(s,'671:horned:special',n=>field(n).length===2&&
 field(n).some(t=>db.bindings[t.key]===19361),'C12-3 Horn Dragon hand Special');
assert.equal(at(s,19361,'hand').length,0);
// 4 Bone Party destroys Horn, Special Dark Blade in DEF from Deck.
s=replay(s,'671:bone-party:main',n=>
 at(n,19361,'graveyard').length===1&&
 at(n,19368,'graveyard').length===1&&
 field(n).length===2&&field(n).some(t=>db.bindings[t.key]===19364)&&
 field(n).some(t=>db.bindings[t.key]===19363&&t.position==='defense'),
 'C12-4 Party destroys Horn and summons Dark Blade DEF');
// 5 Horn triggers from being destroyed on field; 3 real face-up targets.
s=replay(s,'671:horned:destruction',n=>
 field(n).length===1&&field(n).every(t=>
   db.bindings[t.key]===19363&&t.position==='defense')&&
 field(n,'opponent').length===0&&
 at(n,19364,'graveyard').length===1&&
 at(n,19365,'graveyard','opponent').length===1&&
 at(n,19366,'graveyard','opponent').length===1,
 'C12-5 Horn must destroy Witch plus two known opponent monsters');
for(const cid of [19364,19362,19366,19365,19472,19361,19368])
 assert.equal(at(s,cid,'graveyard').length,1,'Preserve real GY CID '+cid);
assert.equal(field(s).length,1,'Only Dark Blade survives');
assert.equal(s.normalAvailable,false);
assert.equal(s.lp.self,8000);assert.equal(s.lp.opponent,8000);
const noGY=E.place(E.create(catalog),{key:key(19361),owner:'self',zone:'hand'});
assert.equal(actions(noGY,'671:horned:special').length,0,
 'Horn Dragon cannot summon with fewer than 3 Memento names in GY');
const noTargets=E.place(E.create(catalog),{key:key(19361),owner:'self',zone:'graveyard'});
assert.equal(actions(noTargets,'671:horned:destruction').length,0,
 'No destruction event and three targets => no Horn effect');
const noParty=E.place(E.create(catalog),{key:key(19368),owner:'self',zone:'hand'});
assert.equal(actions(noParty,'671:bone-party:main').length,0,
 'Bone Party cannot destroy an absent Horn or other Memento');
console.log(JSON.stringify({ok:true,route:'C12',documentedSteps:5,
 executedTransitions:7,starter:[19364,19361,19368],finalOwnField:[19363],
 opponentCardsDestroyed:2,allFaceUp:true,optionalGhatticUnused:true,
 opponentResponse:'none',universalTargetingLegalityCertified:false}));
