'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const S=require('../assets/modules/play-calculator/study.js');
const base=require('../assets/modules/play-calculator/card-facts.json');
const study=JSON.parse(fs.readFileSync(
  __dirname+'/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const catalog=E.catalog({id:671,name:study.name,format_codes:[study.format]},
  study.cards.flatMap(c=>c.slots.map(slot=>({
    id:slot.card_id,deck_id:671,card_name:c.name,
    section:slot.section,quantity:slot.quantity,
  }))));
const db=S.bind(base,[{catalog,study}]);
const id='671:aero-shark:damage-per-banished';
assert.equal(db.ruleImports[0].status,'accepted');
assert.equal(db.rules.filter(r=>r.id===id).length,1,
  'Portable and fallback Aero Shark rules must never duplicate');
const key=cid=>{const c=catalog.cards.find(x=>db.bindings[x.key]===cid);
  assert(c,'Missing CID '+cid);return c.key;};
const actions=st=>R.actions(st,db).actions.filter(a=>a.event.ruleId===id);
let s=E.create(catalog);
s=E.place(s,{key:key(9657),owner:'self',zone:'m1'});
const aero=s.tokens.find(t=>db.bindings[t.key]===9657);
s=E.place(s,{key:key(19364),owner:'self',zone:'material:'+aero.uid});
s=E.place(s,{key:key(12950),owner:'self',zone:'banished'});
s=E.place(s,{key:key(9279),owner:'self',zone:'banished'});
s=E.place(s,{key:key(13619),owner:'self',zone:'banished'});
s=E.place(s,{key:key(19188),owner:'self',zone:'banished'});
const hidden=s.tokens.find(t=>db.bindings[t.key]===19188);
s=E.position(s,hidden.uid,'set');
assert.equal(actions(s).length,1,
  'Two face-up banished monsters and an attached material enable Aero Shark');
const before=JSON.stringify(s),action=actions(s)[0];
assert.equal(JSON.stringify(s),before,'Analysis may not alter the original scenario');
assert.equal(action.state.lp.opponent,7800,'Exactly 2 face-up banished monsters inflict 200 damage');
assert.equal(action.state.lp.self,8000);
assert(action.state.tokens.some(t=>db.bindings[t.key]===19364&&t.zone==='graveyard'),
  'Physical Xyz material must be detached as cost');
assert(action.state._ruleEvents.some(e=>e.kind==='aero_shark_damage'&&
  e.monsters===2&&e.damage===200&&e.stage==='resolve'),
  'Damage count belongs to resolution, not to activation');
assert.equal(actions(action.state).length,0,'Aero Shark effect may only be used once per turn');
const negated=R.actions(s,db,{negateEffectRuleIds:[id]}).actions.filter(a=>a.event.ruleId===id);
assert.equal(negated.length,1);
assert.equal(negated[0].state.lp.opponent,8000,'Negated Aero Shark deals zero damage');
assert(negated[0].state.tokens.some(t=>db.bindings[t.key]===19364&&t.zone==='graveyard'),
  'Negating the effect does not restore detached cost');
const noMaterial=E.clone(s);
noMaterial.tokens=noMaterial.tokens.filter(t=>t.zone!=='material:'+aero.uid);
assert.equal(actions(noMaterial).length,0,'No Xyz material means no activation');
const unknown=E.place(s,{key:key(19365),owner:'self',zone:'banished'});
assert.equal(actions(unknown).length,0,
  'Unknown face-up banished card classification must not result in made-up damage');
assert(R.actions(unknown,db).rejected.some(e=>e.id===id&&/tipo|desterrada/i.test(e.message)),
  'Rejected action explains insufficient monster-type evidence');
const onlyHidden=E.clone(s);
onlyHidden.tokens=onlyHidden.tokens.filter(t=>!([12950,9279].includes(db.bindings[t.key])&&
  t.zone==='banished'));
const zero=actions(onlyHidden);
assert.equal(zero.length,1,'Zero identified banished monsters still permits cost and activation');
assert.equal(zero[0].state.lp.opponent,8000,'Face-down banished Extra and face-up Spell cannot add damage');
console.log(JSON.stringify({ok:true,deck:671,cid:9657,materialCost:1,
  visibleBanishCount:2,damage:200,negationAndUnknownTypesFailClosed:true}));
