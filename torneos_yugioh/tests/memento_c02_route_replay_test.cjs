'use strict';
// C02: actual no-opponent-response replay through the imported canonical calculator,
// not a check that a diagram or text includes the expected card names.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const study=JSON.parse(fs.readFileSync(__dirname+
  '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=study.routes.find(r=>r.id==='671:A09:C02');
assert(route&&route.steps.length===4,'C02 source must retain its 4 documented steps');
assert.equal(route.replay_certification?.test,
  'torneos_yugioh/tests/memento_c02_route_replay_test.cjs');
assert.equal(route.replay_certification?.scope,'exact-opening-no-opponent-response');
assert(/Angbruja/.test(route.opening)&&/Fiesta/.test(route.opening),
  'Opening hand must match the source route');
const rows=study.cards.flatMap(c=>c.slots.map(slot=>({
  id:slot.card_id,deck_id:671,card_name:c.name,section:slot.section,quantity:slot.quantity
})));
const cat=E.catalog({id:671,name:study.name,format_codes:[study.format]},rows);
const db=Study.bind(facts,[{catalog:cat,study}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length, study.calculator.rules.length);
const key=cid=>{
  const found=cat.cards.find(c=>db.bindings[c.key]===cid&&c.section==='main');
  assert(found,'Approved Main CID missing: '+cid);
  return found.key;
};
const field=s=>s.tokens.filter(t=>t.controller==='self'&&E.isMonster(t.zone));
const at=(s,cid,zone)=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===zone);
const move=(s,cid,zone)=>E.place(s,{key:key(cid),owner:'self',zone});
const actions=(s,id)=>R.actions(s,db).actions.filter(a=>a.event?.ruleId===id);
function choose(s,id,predicate,message) {
  const before=JSON.stringify(s);
  const candidates=actions(s,id);
  const found=candidates.find(a=>predicate(a.state));
  assert(found,message+': no eligible transition among '+candidates.length);
  assert.equal(JSON.stringify(s),before,'Analyzing C02 must never mutate prior state');
  assert(found.source&&found.event?.ruleId===id,'Every step must have a known source/effect');
  return found.state;
}
for(const id of ['671:bone-party:main','671:tatsun:mill','671:tecuhtlica:five-names']){
  const imported=study.calculator.rules.filter(r=>r.id===id);
  const fallback=Verified.forDeck(671).filter(r=>r.id===id);
  assert.deepEqual(imported,fallback,'Canonical and runtime rules must agree: '+id);
}
let s=E.create(cat);
s=move(s,19364,'hand');
s=move(s,19368,'hand');
const original=JSON.stringify(s);
// Step 1: Normal Angwitch, and resolve its summon-triggered search of Combined Creation.
s=choose(s,'671:19364:normal',n=>at(n,19364,'m1').length===1&&!n.normalAvailable,
  'C02-1a Normal Angbruja');
s=choose(s,'671:angwitch:search',n=>at(n,19360,'hand').length===1,
  'C02-1b Angbruja adds Combined Creation');
// Step 2: Bone Party destroys Angwitch and summons Tatsun in Defense.
s=choose(s,'671:bone-party:main',n=>at(n,19364,'graveyard').length===1&&
  field(n).some(t=>db.bindings[t.key]===19362&&t.position==='defense')&&
  at(n,19368,'graveyard').length===1,
  'C02-2 Party destroy + Defense Special Summon');
assert.equal(field(s).length,1,'Bone Party must not invent another monster');
// Step 3: Tatsun destroys itself, sends levels 1+1+2, leaves five names in GY.
// Do not fire optional Ghattic effects; preserve the exact route.
s=choose(s,'671:tatsun:mill',n=>field(n).length===0&&
  [19364,19362,19366,19365,19472].every(cid=>at(n,cid,'graveyard').length===1),
  'C02-3 Tatsun self-destruction and exactly three different Deck sends');
assert.equal(at(s,19360,'hand').length,1);
assert.equal(s.tokens.filter(t=>t.zone==='graveyard').length,6,
  'GY has exactly 5 Memento monsters plus Bone Party');
// Step 4: The 5 named Mementos return to Deck for a 5000-ATK boss.
s=choose(s,'671:tecuhtlica:five-names',n=>field(n).length===1&&
  field(n).every(t=>db.bindings[t.key]===19360)&&
  [19364,19362,19366,19365,19472].every(cid=>!n.tokens.some(t=>db.bindings[t.key]===cid)),
  'C02-4 Combined Creation with five distinct names');
assert.equal(at(s,19368,'graveyard').length,1,'Bone Party remains in GY');
assert.equal(s.tokens.length,2,'Only boss and spent Bone Party remain visible');
assert.equal(s.normalAvailable,false,'Normal Summon remains spent');
assert.equal(s.lp.self,8000);
assert.equal(s.lp.opponent,8000);
assert.notEqual(JSON.stringify(s),original,'Replay must evolve board state');
const noParty=E.place(E.create(cat),{key:key(19364),owner:'self',zone:'hand'});
assert.equal(actions(noParty,'671:bone-party:main').length,0,
  'No Bone Party in hand => no documented party branch');
const noFive=E.place(E.create(cat),{key:key(19360),owner:'self',zone:'hand'});
assert.equal(actions(noFive,'671:tecuhtlica:five-names').length,0,
  'Cannot Special Summon Combined Creation without all five physical materials');
console.log(JSON.stringify({ok:true,route:'C02',documentedSteps:4,
  executedTransitions:6,board:['Combined Creation'],graveyard:['Bone Party'],
  noOpponentResponse:true,otherRoutesCertified:false}));
