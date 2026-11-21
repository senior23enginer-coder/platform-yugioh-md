'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const Battle=require('../assets/modules/play-calculator/battle.js');
const Restrictions=require('../assets/modules/play-calculator/restrictions.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const study=JSON.parse(fs.readFileSync(__dirname+
  '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const rows=study.cards.flatMap(c=>c.slots.map(slot=>({
  id:slot.card_id,deck_id:671,card_name:c.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:study.name,format_codes:[study.format]},rows);
const db=Study.bind(facts,[{catalog,study}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
const linkId='671:sp:link-with-moon',effectId='671:sp:banish-link-extra';
for(const id of [linkId,effectId]) {
  const imported=study.calculator.rules.filter(r=>r.id===id);
  const fallback=Verified.forDeck(671).filter(r=>r.id===id);
  assert.deepEqual(imported,fallback,'Imported and fallback S:P rules must match '+id);
  assert.equal(imported.length,id===linkId?2:4);
}
const key=cid=> {
  const card=catalog.cards.find(c=>db.bindings[c.key]===cid);
  assert(card,'Missing authentic CID '+cid);
  return card.key;
};
const put=(s,cid,zone)=>E.place(s,{key:key(cid),owner:'self',zone});
const actions=(s,id,options={})=>R.actions(s,db,options).actions
  .filter(a=>a.event?.ruleId===id);
let state=E.patch(E.create(catalog),{turn:2,goesFirst:false,phase:'main1'});
state=put(state,19932,'emz1');
state=put(state,19364,'m1');
const before=JSON.stringify(state);
const link=actions(state,linkId);
assert(link.length>0,'S:P Link recipe should use Moon plus Angwitch');
assert.equal(JSON.stringify(state),before,'Analyze cannot mutate captured duel');
const linked=link[0].state;
assert(linked.tokens.some(t=>db.bindings[t.key]===19188 && E.isMonster(t.zone)));
assert(linked._ruleEvents.some(e=>e.kind==='link_summon'&&
  e.cid===19188&&e.usedExtraDeckMaterial===true),
  'Link summon must record actual Extra Deck material, not a guessed condition');
const moves=actions(linked,effectId);
assert(moves.length>0,'S:P first effect opens after Extra Deck Link material');
const banish=moves.find(a=>a.state.tokens.some(t=>db.bindings[t.key]===19932&&t.zone==='banished'));
assert(banish,'S:P can banish the known Moon from GY');
const resolved=banish.state;
assert.equal(Restrictions.effective(resolved).directAttackBlocked,true);
assert.equal(actions(resolved,effectId).length,0,'S:P first effect once per turn');
const combat=Battle.inspect(E.patch(resolved,{phase:'battle'}),db);
assert.equal(combat.available,true);
assert(combat.attacks.some(a=>a.status==='blocked'&&/S:P/.test(a.reason)),
  'Direct attacks must not be recommended in the effect turn');
assert.equal(combat.directDamageCeiling,null);
const next=E.patch(resolved,{turn:3,phase:'battle',active:'self'});
assert.equal(Restrictions.effective(next).directAttackBlocked,undefined);
assert(Battle.inspect(next,db).attacks.some(a=>a.status==='conditional'&&a.target===null),
  'Direct attack restriction must expire on a later turn');
const denied=actions(linked,effectId,{negateEffectRuleIds:[effectId]});
assert(denied.length>0);
assert(!denied[0].state._ruleFlags?.directAttackBlocked,
  'Negated first effect cannot prohibit direct attacks');
assert.equal(actions(E.patch(linked,{phase:'battle'}),effectId).length,0,
  'An edit after the Link Summon expires its immediate trigger window');
let plain=E.create(catalog);
plain=put(plain,19188,'m1');
assert.equal(actions(plain,effectId).length,0,
  'Manually placed S:P cannot claim a Link Summon trigger');
let noExtra=E.patch(E.create(catalog),{turn:2,phase:'main1'});
noExtra=put(noExtra,19364,'m1');
noExtra=put(noExtra,19362,'m2');
const normal=actions(noExtra,'671:sp:link-procedure');
assert(normal.length>0,'Preserve documented Main-only S:P Link');
assert.equal(actions(normal[0].state,effectId).length,0,
  'Main-only materials cannot unlock Extra Deck material effect');
assert.equal(actions(state,linkId,{noExtra:true}).length,0,
  'Extra Deck restriction must block Link procedure');
console.log(JSON.stringify({ok:true,cid:19188,procedures:2,variants:4,
  extraMaterialRequired:true,triggerExpiresOnEdit:true,
  directAttackTurnLock:true,mainOnlyDenied:true,negation:true}));
