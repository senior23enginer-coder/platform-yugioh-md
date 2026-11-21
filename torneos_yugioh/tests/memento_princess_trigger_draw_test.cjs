'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const S=require('../assets/modules/play-calculator/study.js');
const F=require('../assets/modules/play-calculator/card-facts.json');
const source=JSON.parse(fs.readFileSync(__dirname+
  '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const rows=source.cards.flatMap(c=>c.slots.map(slot=>({
  id:slot.card_id,deck_id:671,card_name:c.name,quantity:slot.quantity,section:slot.section
})));
const cat=E.catalog({id:671,name:source.name,format_codes:[source.format]},rows);
const db=S.bind(F,[{catalog:cat,study:source}]);
assert.equal(db.ruleImports[0].status,'accepted');
assert.equal(db.rules.length,353);
const princessId='671:princess:trigger-draw';
assert.equal(db.rules.filter(r=>r.id===princessId).length,1);
const key=(cid,section)=>{const c=cat.cards.find(x=>db.bindings[x.key]===cid&&x.section===section);
  assert(c,'Physical CID missing '+cid);return c.key;};
const actions=(s,id)=>R.actions(s,db).actions.filter(a=>a.event?.ruleId===id);
let state=E.create(cat,cat);
state=E.patch(state,{turn:2,active:'opponent',phase:'main1'});
state=E.place(state,{key:key(20423,'extra'),owner:'self',zone:'m1'});
state=E.place(state,{key:key(9279,'main'),owner:'self',zone:'hand'});
assert.equal(actions(state,princessId).length,0,
  'Princess cannot draw from a monster merely held in hand');
assert.equal(actions(state,'671:droll:main-deck-lock').length,0,
  'Droll requires an actual opponent add observation');
const observed=E.observeOpponentMainDeckAdd(state);
const droll=actions(observed,'671:droll:main-deck-lock');
assert.equal(droll.length,1,'Observed opponent add allows real Droll activation');
const afterCost=droll[0].state;
assert(afterCost.tokens.some(t=>db.bindings[t.key]===9279&&t.zone==='graveyard'));
assert((afterCost._ruleEvents||[]).some(e=>e.kind==='monster_sent_from_hand_activation'&&
  e.cid===9279&&e.captureRevision===afterCost._captureRevision),
  'The physical monster cost must leave a current-revision causal event');
assert.equal(actions(afterCost,princessId).length,1,
  'A real monster sent from hand for activation unlocks the Princess draw');
const before=JSON.stringify(afterCost);
const drawn=actions(afterCost,princessId)[0].state;
assert.equal(JSON.stringify(afterCost),before,'Preview must not mutate original state');
assert.equal(drawn.selfUnknownHand,1,'Exactly one unidentified own draw');
assert.equal(actions(drawn,princessId).length,0,'Princess effect once per turn');
assert.equal(actions(E.patch(afterCost,{notes:'New unrelated action'}),princessId).length,0,
  'Old activation cost cannot be reused after capture is edited');
// A Spell sent as its own activation cost does not count as a MONSTER sent
// from hand; do not invent the Princess trigger.
// Bone Party is a Spell: sending it from hand while activating it is NOT
// a monster being sent from hand and must never grant a Princess draw.
let spell=E.create(cat,cat);
spell=E.place(spell,{key:key(20423,'extra'),owner:'self',zone:'m1'});
spell=E.place(spell,{key:key(19361,'main'),owner:'self',zone:'m2'});
spell=E.place(spell,{key:key(19368,'main'),owner:'self',zone:'hand'});
const spellActions=actions(spell,'671:bone-party:main');
assert(spellActions.length>0,'Actual Bone Party activation must be executable');
const afterSpell=spellActions[0].state;
assert(!afterSpell._ruleEvents?.some(e=>e.kind==='monster_sent_from_hand_activation'),
  'A Spell cost is never a monster cost');
assert.equal(actions(afterSpell,princessId).length,0,
  'Princess cannot draw after sending only a Spell as an activation cost');
// Negating the effect does not refund the already paid monster cost.
const denied=R.actions(observed,db,{
  negateEffectRuleIds:['671:droll:main-deck-lock']
}).actions.find(a=>a.event?.ruleId==='671:droll:main-deck-lock');
assert(denied,'Droll effect-negated branch must remain a legal paid activation');
assert.equal(actions(denied.state,princessId).length,1,
  'Princess sees an activation cost even if the original effect is negated');
const fake=JSON.parse(JSON.stringify(db.rules.find(r=>r.id===princessId)));
fake.resolve[0].count=2;
assert.throws(()=>R.validate(fake),/Robo desconocido/);
let noPrincess=E.create(cat,cat);
noPrincess=E.patch(noPrincess,{turn:2,active:'opponent',phase:'main1'});
noPrincess=E.place(noPrincess,{key:key(9279,'main'),owner:'self',zone:'hand'});
const noPrincessResult=actions(E.observeOpponentMainDeckAdd(noPrincess),'671:droll:main-deck-lock')[0].state;
assert.equal(actions(noPrincessResult,princessId).length,0,
  'Princess cannot draw unless present face-up on the field');
console.log(JSON.stringify({ok:true,actorCid:20423,costMonsterCid:9279,
  unknownDraws:drawn.selfUnknownHand,source:'official',scope:'one draw choice only',
  alternateEquipCertified:false,chainCertified:false}));
