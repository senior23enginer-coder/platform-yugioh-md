'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+'/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(r=>r.id==='671:A09:C09');
assert.equal(route?.steps.length,5);
assert(route.result.includes('Princesa Necroequipo')&&route.result.includes('NO roba'),
  'C09 final board and no-draw must be visible in canonical route');
assert(!route.opening.includes('No se acreditó mediante simulación.'),
  'Certified C09 cannot retain the obsolete pending-simulation note');
assert.equal(route.replay_certification?.status,'REPLAY_CERTIFIED');
assert.equal(route.replay_certification?.scope,'exact-opening-no-opponent-response');
assert.equal(route.replay_certification?.test,'torneos_yugioh/tests/memento_c09_route_replay_test.cjs');
const rows=deck.cards.flatMap(c=>c.slots.map(x=>({id:x.card_id,deck_id:671,card_name:c.name,section:x.section,quantity:x.quantity})));
const cat=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog:cat,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
assert.deepEqual(deck.calculator.rules.filter(r=>r.id==='671:princess:contact-summon'),
 Verified.forDeck(671).filter(r=>r.id==='671:princess:contact-summon'));
const key=(cid,section='main')=>{
 const c=cat.cards.find(c=>db.bindings[c.key]===cid&&c.section===section);
 assert(c,'Missing physical CID '+cid);return c.key;
};
const at=(s,cid,zone)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===zone);
const field=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const acts=(s,id,opt={})=>R.actions(s,db,opt).actions.filter(a=>a.event?.ruleId===id);
function step(s,id,pred,name){const before=JSON.stringify(s),a=acts(s,id).find(a=>pred(a.state));assert(a,name);assert.equal(JSON.stringify(s),before);assert(a.event.ruleId===id&&a.source);return a.state;}
let s=E.create(cat);for(const cid of [19364,19366])s=E.place(s,{key:key(cid),owner:'self',zone:'hand'});
assert.equal(s.tokens.length,2,'C09 starter requires Angwitch+Goblin only');
// 1 Normal Angwitch, search Tatsun, Special Tatsun.
s=step(s,'671:19364:normal',n=>at(n,19364,'m1').length===1,'C09-1a Normal');
s=step(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,'C09-1b Search');
s=step(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,'C09-1c Special');
// 2 Link Moon with two Main materials, then use one LIGHT Fiend Moon into Requiem.
s=step(s,'671:moon:link-from-angwitch-tatsun',n=>field(n).length===1&&field(n).every(t=>db.bindings[t.key]===19932),'C09-2a Moon Link');
s=step(s,'671:moon:link-requiem',n=>field(n).length===1&&field(n).every(t=>db.bindings[t.key]===20225),'C09-2b Requiem Link');
// 3 Requiem tribute cost, Special physical Engraver from Deck; equip Requiem from GY.
s=step(s,'671:requiem:tribute',n=>at(n,20225,'graveyard').length===1&&field(n).some(t=>db.bindings[t.key]===20196),'C09-3a Requiem Tribute');
s=step(s,'671:requiem:equip',n=>at(n,20225,'s1').length===1&&
 at(n,20225,'s1')[0].equippedToUid===field(n).find(t=>db.bindings[t.key]===20196)?.uid,'C09-3b Equip Requiem');
assert.equal(at(s,19366,'hand').length,1,'Goblin is still a physical hand card');
// 4 Contact procedure sends equipped Engraver, Requiem and hand Goblin to GY.
s=step(s,'671:princess:contact-summon',n=>field(n).length===1&&
 field(n).every(t=>db.bindings[t.key]===20423)&&
 [20196,20225,19366].every(cid=>at(n,cid,'graveyard').length===1),
 'C09-4 Necroquip contact summon');
assert(s._ruleEvents.some(e=>e.kind==='necroquip_contact_summon'&&e.cid===20423));
assert(!s._ruleEvents.some(e=>e.kind==='fusion_summon'&&e.cid===20423),
 'Princess was NOT Fusion Summoned');
assert(!s._ruleEvents.some(e=>e.kind==='sent_from_hand_to_activate'),
 'Goblin as summoning material must NOT trigger Princess draw');
assert.equal(s.selfUnknownHand,0,'Princess must not draw for summoning material');
// 5: verify exact final field and resources.
assert.equal(field(s).length,1);
assert.equal(at(s,20423,field(s)[0].zone).length,1);
assert.equal(at(s,20225,'s1').length,0,'Equipped monster card must leave equip zone');
for(const cid of [19364,19362,19932,20196,19366,20225])
 assert.equal(at(s,cid,'graveyard').length,1,'C09 missing real GY card '+cid);
assert.equal(s.normalAvailable,false);assert.equal(s.lp.self,8000);
const fake=JSON.parse(JSON.stringify(deck.calculator.rules.find(r=>r.id==='671:princess:contact-summon')));
fake.resolve[0].cid=20216;
assert.throws(()=>R.validate(fake),/Princesa requiere/);
const empty=E.create(cat);
assert.equal(acts(empty,'671:princess:contact-summon').length,0,'No equipped Engraver');
console.log(JSON.stringify({ok:true,route:'C09',documentedSteps:5,transitions:8,
 finalField:[20423],graveyard:[19364,19362,19932,20196,19366,20225],
 contactSummon:true,notFusionSummon:true,noPrincessDraw:true,opponentResponse:'none'}));
