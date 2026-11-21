'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+'/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(r=>r.id==='671:A09:C08');
assert.equal(route?.steps.length,4);
assert.equal(route.replay_certification?.status,'REPLAY_CERTIFIED');
assert.equal(route.replay_certification?.scope,'exact-opening-observed-opponent-activation-no-further-response');
assert.equal(route.replay_certification?.test,'torneos_yugioh/tests/memento_c08_route_replay_test.cjs');
const rows=deck.cards.flatMap(c=>c.slots.map(s=>({id:s.card_id,deck_id:671,card_name:c.name,section:s.section,quantity:s.quantity})));
const cat=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog:cat,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
assert.deepEqual(deck.calculator.rules.filter(r=>r.id==='671:sp:temporary-banish'),Verified.forDeck(671).filter(r=>r.id==='671:sp:temporary-banish'));
const key=cid=>{const c=cat.cards.find(c=>db.bindings[c.key]===cid&&c.section==='main');assert(c,'Missing main CID '+cid);return c.key;};
const zone=(s,cid,z,side='self')=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===z&&t.owner===side);
const monsters=(s,side='self')=>s.tokens.filter(t=>t.controller===side&&E.isMonster(t.zone));
const actions=(s,id)=>R.actions(s,db).actions.filter(a=>a.event?.ruleId===id);
function step(s,id,fn,label){const before=JSON.stringify(s),a=actions(s,id).find(a=>fn(a.state));assert(a,label);assert.equal(JSON.stringify(s),before);assert(a.source&&a.event.ruleId===id);return a.state;}
let s=E.create(cat,cat);s=E.place(s,{key:key(19364),owner:'self',zone:'hand'});
s=step(s,'671:19364:normal',n=>zone(n,19364,'m1').length===1,'C08 Normal');
s=step(s,'671:angwitch:search',n=>zone(n,19362,'hand').length===1,'C08 search');
s=step(s,'671:tatsun:special',n=>zone(n,19362,'m2').length===1,'C08 special');
s=step(s,'671:sp:link-procedure',n=>monsters(n).length===1&&monsters(n).every(t=>db.bindings[t.key]===19188),'C08 Link');
assert.equal(actions(s,'671:sp:banish-link-extra').length,0,'Main-only Link cannot fire first S:P banish');
assert.equal(actions(s,'671:sp:temporary-banish').length,0,'No activation observed');
const uid=monsters(s)[0].uid;
for(const ph of ['battle','main2','end']){s=E.advancePhase(s);assert.equal(s.phase,ph);}
s=E.advanceTurn(s);assert.equal(s.active,'opponent');
for(const ph of ['standby','main1']){s=E.advancePhase(s);assert.equal(s.phase,ph);}
s=E.place(s,{key:key(19364),owner:'opponent',zone:'m3'});
const enemy=monsters(s,'opponent')[0];
assert.throws(()=>E.observeOpponentActivation(s,{uid,effect:'activación rival'}),/copia rival/);
s=E.observeOpponentActivation(s,{uid:enemy.uid,effect:'Angbruja rival activó un efecto observado'});
assert.equal(actions(s,'671:sp:temporary-banish').length,1);
const stale=E.patch(s,{phase:'main2'});
assert.equal(actions(stale,'671:sp:temporary-banish').length,0,'Capture edit expires window');
s=step(s,'671:sp:temporary-banish',n=>
 zone(n,19188,'banished').length===1&&zone(n,19364,'banished','opponent').length===1,'C08 temporary banish');
assert.equal(s.tokens.filter(t=>t.temporaryReturn).length,2);
assert.equal(actions(s,'671:sp:temporary-banish').length,0,'HOPT on response');
for(const ph of ['battle','main2','end']){s=E.advancePhase(s);assert.equal(s.phase,ph);}
assert.equal(monsters(s).length,1);assert.equal(monsters(s,'opponent').length,1);
assert(s.tokens.find(t=>t.uid===uid).zone.startsWith('m'),'S:P returns to Main Monster Zone');
assert(s.tokens.find(t=>t.uid===enemy.uid).zone.startsWith('m'),'Enemy returns to Main Monster Zone');
assert.equal(s.tokens.filter(t=>t.temporaryReturn).length,0);
assert.equal(s._ruleEvents.filter(e=>e.kind==='summon'&&e.turn===s.turn).length,0,'Return is not Special Summon');
assert.equal(zone(s,19364,'graveyard').length,1);assert.equal(zone(s,19362,'graveyard').length,1);
assert.equal(s.lp.self,8000);assert.equal(s.lp.opponent,8000);
const fake=JSON.parse(JSON.stringify(deck.calculator.rules.find(r=>r.id==='671:sp:temporary-banish')));
fake.resolve[0].sourceCid=100;
assert.throws(()=>R.validate(fake),/Destierro temporal/);
const ui=fs.readFileSync(__dirname+'/../assets/modules/play-calculator/ui.js','utf8');
assert(ui.includes('data-play-observe-opponent-activation'));
console.log(JSON.stringify({ok:true,route:'C08',documentedSteps:4,executedTransitions:5,
 actor:19188,observedOpponent:true,temporaryBanish:true,endReturn:true,
 originalOpponentEffectNotResolved:true}));
