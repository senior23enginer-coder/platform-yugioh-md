'use strict';
// C03 replay with an explicit legal opponent monster, never an invented unknown target.
// The mirror-match target demonstrates an actual selectable physical card.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
  '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(r=>r.id==='671:A09:C03');
assert(route&&route.steps.length===7,'C03 must preserve all 7 documented steps');
const rows=deck.cards.flatMap(c=>c.slots.map(x=>({
  id:x.card_id,deck_id:671,card_name:c.name,section:x.section,quantity:x.quantity
})));
const cat=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog:cat,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
assert.deepEqual(deck.calculator.rules.filter(r=>r.id==='671:moon:link-from-angwitch-tatsun'),
  Verified.forDeck(671).filter(r=>r.id==='671:moon:link-from-angwitch-tatsun'));
const key=(cid,section)=>{
 const c=cat.cards.find(x=>db.bindings[x.key]===cid&&x.section===(section||'main'));
 assert(c,'No real card CID '+cid+' in '+(section||'main'));
 return c.key;
};
const zone=(s,cid,z,owner='self')=>s.tokens.filter(t=>db.bindings[t.key]===cid&&
  t.zone===z&&t.owner===owner);
const field=(s,owner='self')=>s.tokens.filter(t=>t.controller===owner&&E.isMonster(t.zone));
const actions=(s,id,o={})=>R.actions(s,db,o).actions.filter(x=>x.event?.ruleId===id);
function take(s,id,test,label) {
 const original=JSON.stringify(s),choices=actions(s,id);
 const chosen=choices.find(x=>test(x.state));
 assert(chosen,label+' not available; candidates '+choices.length);
 assert.equal(JSON.stringify(s),original,'Calculator must not mutate captured field');
 assert(chosen.source&&chosen.event?.ruleId===id,'Unverified or fabricated action');
 return chosen.state;
}
let s=E.patch(E.create(cat,cat),{turn:2,goesFirst:false,active:'self',phase:'main1'});
s=E.place(s,{key:key(19364),owner:'self',zone:'hand'});
s=E.place(s,{key:key(19364),owner:'opponent',zone:'m3'});
assert.equal(field(s,'opponent').length,1,'Opponent physical mirror monster is mandatory');
// 1 Normal + summoned search.
s=take(s,'671:19364:normal',n=>zone(n,19364,'m1').length===1,'C03-1a Normal Angwitch');
s=take(s,'671:angwitch:search',n=>zone(n,19362,'hand').length===1,'C03-1b Search Tatsun');
// 2 Special Tatsun from hand.
s=take(s,'671:tatsun:special',n=>zone(n,19362,'m2').length===1,'C03-2 Special Tatsun');
// 3 Link-2 Moon using both effect monsters, no Link-5 effect.
s=take(s,'671:moon:link-from-angwitch-tatsun',n=>
  field(n).length===1&&field(n).every(t=>db.bindings[t.key]===19932)&&
  zone(n,19364,'graveyard').length===1&&zone(n,19362,'graveyard').length===1,
  'C03-3 Link Moon');
// 4 Link-1 Requiem using Moon as 1 LIGHT Fiend material.
s=take(s,'671:moon:link-requiem',n=>
  field(n).length===1&&field(n).every(t=>db.bindings[t.key]===20225)&&
  zone(n,19932,'graveyard').length===1,'C03-4 Link Requiem');
// 5 Tribute Requiem as cost; use one physical Engraver from Deck.
s=take(s,'671:requiem:tribute',n=>zone(n,20225,'graveyard').length===1&&
  field(n).some(t=>db.bindings[t.key]===20196),'C03-5 Requiem tribute into Engraver');
assert.equal(field(s).length,1);
// 6 Equip that same Requiem from GY to the LIGHT non-Link Fiend Engraver.
s=take(s,'671:requiem:equip',n=>
  zone(n,20225,'s1').length===1&&field(n).some(t=>db.bindings[t.key]===20196),
  'C03-6 Equip Requiem');
// 7 Engraver targets real opponent monster and equipped Requiem; sends both to GY.
s=take(s,'671:engraver:remove',n=>
  zone(n,20225,'graveyard').length===1&&
  zone(n,19364,'graveyard','opponent').length===1&&
  field(n).length===1&&field(n).every(t=>db.bindings[t.key]===20196),
  'C03-7 Engraver sends two actual cards to their owners GY');
assert.equal(field(s,'opponent').length,0);
assert.equal(field(s).length,1);
assert.equal(s.normalAvailable,false);
assert.equal(s.lp.self,8000);
assert.equal(s.lp.opponent,8000);
const noEnemy=E.clone(s);
noEnemy.tokens=noEnemy.tokens.filter(t=>t.owner!=='opponent');
assert.equal(actions(noEnemy,'671:engraver:remove').length,0,
  'Engraver removal cannot be recommended without opponent card');
let noExtra=E.create(cat);
noExtra=E.place(noExtra,{key:key(19364),owner:'self',zone:'m1'});
noExtra=E.place(noExtra,{key:key(19362),owner:'self',zone:'m2'});
assert.equal(actions(noExtra,'671:moon:link-from-angwitch-tatsun',{noExtra:true}).length,0,
  'Extra Deck block denies Moon Link');
console.log(JSON.stringify({ok:true,route:'C03',documentedSteps:7,
  executedTransitions:8,finalField:[20196],opponentField:[],
  opponentTarget:'physical Angwitch mirror',noOpponentResponse:true,
  generalDuelLegalityCertified:false}));
