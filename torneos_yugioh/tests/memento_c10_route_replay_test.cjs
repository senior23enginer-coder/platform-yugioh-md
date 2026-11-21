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
const route=deck.routes.find(r=>r.id==='671:A09:C10');
assert(route&&route.steps.length===5,'C10 keeps its five documented steps');
assert(!route.opening.includes('No se acreditó mediante simulación.'),
 'C10 replay certification must not coexist with a stale pending-simulation claim');
assert.equal(route.replay_certification?.status,'REPLAY_CERTIFIED');
assert.equal(route.replay_certification?.scope,'exact-opening-no-opponent-response');
assert.equal(route.replay_certification?.test,'torneos_yugioh/tests/memento_c10_route_replay_test.cjs');
assert.equal(route.replay_certification?.executed_transitions,11);
const rows=deck.cards.flatMap(card=>card.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:card.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
for(const id of ['671:bone-party:main','671:engraver:link-sequence',
 '671:duke:immediate-normal','671:sequence:duke-fusion'])
 assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
  Verified.forDeck(671).filter(r=>r.id===id),'Portable/fallback differs: '+id);
const key=(cid,section='main')=>{
 const c=catalog.cards.find(c=>db.bindings[c.key]===cid && c.section===section);
 assert(c,'Physical card not found '+cid+' '+section);return c.key;
};
const at=(s,cid,z)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===z&&t.owner==='self');
const mons=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const actions=(s,id,o={})=>R.actions(s,db,o).actions.filter(a=>a.event?.ruleId===id);
function take(s,id,predicate,label){
 const before=JSON.stringify(s),possible=actions(s,id);
 const selected=possible.find(a=>predicate(a.state));
 assert(selected,label+': no matching transition of '+possible.length);
 assert.equal(JSON.stringify(s),before,'Do not mutate current duel state');
 assert(selected.source&&selected.event.ruleId===id,'Only sourced transitions may run');
 return selected.state;
}
let s=E.create(catalog);
for(const cid of [19364,19368,19366])s=E.place(s,{key:key(cid),owner:'self',zone:'hand'});
assert.equal(s.tokens.length,3,'C10 starts from exactly Angwitch + Bone Party + Goblin');
// Step 1: Angwitch normal, Tatsun search/special, link Moon, link Requiem, tribute for Engraver.
s=take(s,'671:19364:normal',n=>at(n,19364,'m1').length===1,'C10-1a Normal Witch');
s=take(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,'C10-1b Find Tatsun');
s=take(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,'C10-1c Special Tatsun');
s=take(s,'671:moon:link-from-angwitch-tatsun',n=>
 mons(n).length===1&&mons(n).some(t=>db.bindings[t.key]===19932),
 'C10-1d Link Moon');
s=take(s,'671:moon:link-requiem',n=>
 mons(n).length===1&&mons(n).some(t=>db.bindings[t.key]===20225),
 'C10-1e Link Requiem');
s=take(s,'671:requiem:tribute',n=>
 mons(n).length===1&&mons(n).some(t=>db.bindings[t.key]===20196)&&
 at(n,20225,'graveyard').length===1,'C10-1f Requiem tribute -> Engraver');
// Step 2: Party destroys physical Goblin in hand, Special Ghattic in Defense, recover original Witch.
s=take(s,'671:bone-party:main',n=>
 at(n,19366,'graveyard').length===1&&
 mons(n).some(t=>db.bindings[t.key]===19472&&t.position==='defense')&&
 at(n,19368,'graveyard').length===1,'C10-2a Bone Party destruction + Ghattic DEF');
s=take(s,'671:ghattic:recover',n=>at(n,19364,'hand').length===1,
 'C10-2b Ghattic returns physical Witch from GY');
// Step 3: Engraver LIGHT Fiend + Ghattic DARK Fiend become Sequence Link-2.
s=take(s,'671:engraver:link-sequence',n=>
 mons(n).length===1&&mons(n).some(t=>db.bindings[t.key]===20226)&&
 at(n,20196,'graveyard').length===1&&at(n,19472,'graveyard').length===1,
 'C10-3 Sequence Link from 2 exact Fiends');
// Step 4: Sequence uses Engraver and Goblin GY to Fusion Summon Duke.
s=take(s,'671:sequence:duke-fusion',n=>
 mons(n).length===2&&[20226,20389].every(cid=>mons(n).some(t=>db.bindings[t.key]===cid))&&
 [20196,19366].every(cid=>n.tokens.every(t=>db.bindings[t.key]!==cid)),
 'C10-4 Duke from Sequence Fusion of real graveyard Fiends');
assert.equal(s.normalAvailable,false,'Original Normal was spent in step one');
// Step 5: Duke effect allows exactly one immediate extra Normal using retrieved Witch.
s=take(s,'671:duke:immediate-normal',n=>
 mons(n).length===3&&[20226,20389,19364].every(cid=>
 mons(n).some(t=>db.bindings[t.key]===cid))&&
 at(n,19364,'hand').length===0,
 'C10-5 Duke immediate additional Normal of returned Witch');
assert.equal(s.normalAvailable,false,
 'The Duke does not reset the previously used standard Normal Summon');
assert.equal(actions(s,'671:angwitch:search').length,0,
 'Angwitch once-per-name search remains spent even after return and new Summon');
assert.equal(actions(s,'671:duke:immediate-normal').length,0,
 'Duke normal effect cannot be activated a second time');
assert(s._ruleEvents.some(e=>e.kind==='summon'&&e.fromDuke&&e.cid===19364),
 'Additional Normal must have recorded Duke as its source');
assert.equal(at(s,19368,'graveyard').length,1);
assert.equal(at(s,19472,'graveyard').length,1);
assert.equal(s.lp.self,8000);assert.equal(s.lp.opponent,8000);
const noWitch=E.place(E.create(catalog),{key:key(20389,'extra'),owner:'self',zone:'m1'});
assert.equal(actions(noWitch,'671:duke:immediate-normal').length,0,
 'Duke cannot summon a fabricated Witch absent from hand');
const zeroParty=E.place(E.create(catalog),{key:key(19368),owner:'self',zone:'hand'});
assert.equal(actions(zeroParty,'671:bone-party:main').length,0,
 'Bone Party cannot destroy nonexistent physical Goblin');
const blocked=actions(s,'671:duke:immediate-normal',{noMoreSummons:true});
assert.equal(blocked.length,0,'Summon restriction must prevent additional Duke Normal');
console.log(JSON.stringify({ok:true,route:'C10',steps:5,transitions:11,
 opening:[19364,19368,19366],field:[20226,20389,19364],
 originalNormalSpent:true,dukeAdditionalNormal:true,witchSearchRepeated:false,
 opponentResponse:'none',universalChainLegalityCertified:false}));
