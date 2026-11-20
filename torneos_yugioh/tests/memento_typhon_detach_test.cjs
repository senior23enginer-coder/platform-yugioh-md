'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const S=require('../assets/modules/play-calculator/study.js');
const Chain=require('../assets/modules/play-calculator/chain.js');
const F=require('../assets/modules/play-calculator/card-facts.json');
const study=JSON.parse(fs.readFileSync(__dirname+'/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const catalog=E.catalog({id:671,name:study.name,format_codes:[study.format]},
  study.cards.flatMap(c=>c.slots.map(x=>({
    id:x.card_id,deck_id:671,card_name:c.name,quantity:x.quantity,section:x.section,
  }))));
const db=S.bind(F,[{catalog,study}]);
const effectId='671:typhon:detach-bounce';
const effects=db.rules.filter(r=>r.id===effectId);
assert.equal(effects.length,4,'Four exact side/section destinations, one effect ID');
assert.equal(db.ruleImports[0].status,'accepted');
const key=(cid,section)=> {
  const c=catalog.cards.find(c=>db.bindings[c.key]===cid && (!section||c.section===section));
  assert(c,'Missing exact approved CID '+cid); return c.key;
};
function scenario({opponentCid=19364,opponentExtra=false,stolen=false,material=true}={}) {
  let s=E.create(catalog,catalog);
  s=E.place(s,{key:key(19184,'extra'),owner:'self',zone:'m1'});
  const typhon=s.tokens.find(t=>db.bindings[t.key]===19184&&t.owner==='self');
  if(material)s=E.place(s,{key:key(19364,'main'),owner:'self',zone:'material:'+typhon.uid});
  const side=stolen?'self':'opponent',section=opponentExtra?'extra':'main';
  s=E.place(s,{key:key(opponentCid,section),owner:'opponent',
    controller:side,zone:opponentExtra?'emz2':'m2'});
  return {s,typhon};
}
const actions=s=>R.actions(s,db).actions.filter(a=>a.event.ruleId===effectId);
const {s,typhon}=scenario();
const original=JSON.stringify(s);
const chosen=actions(s).filter(a=>a.state.tokens.some(t=>t.owner==='opponent'&&
  db.bindings[t.key]===19364&&t.zone==='hand'));
assert.equal(chosen.length,1,'Opponent Main monster has one correctly routed bounce');
const result=chosen[0].state;
assert.equal(JSON.stringify(s),original,'Calculation cannot mutate captured duel');
assert(result.tokens.some(t=>t.owner==='self'&&db.bindings[t.key]===19364&&t.zone==='graveyard'),
  "Exactly one TY-PHON material must go to owner's GY as cost");
assert(result.tokens.some(t=>t.owner==='opponent'&&db.bindings[t.key]===19364&&
  t.zone==='hand'&&t.controller==='opponent'),
  'Opponent Main monster returns to its owner hand, never to Deck');
assert.equal(actions(result).length,0,'TY-PHON effect is once per turn regardless of target');
assert.equal(actions(scenario({material:false}).s).length,0,'A TY-PHON with no attached material cannot pay cost');
const denied=R.actions(s,db,{negateEffectRuleIds:[effectId]}).actions
  .filter(a=>a.event.ruleId===effectId);
assert(denied.length>=1,'A negated effect still detaches the cost');
assert(denied[0].state.tokens.some(t=>t.owner==='self'&&db.bindings[t.key]===19364&&t.zone==='graveyard'),
  'The material remains detached even when the effect is negated');
assert(denied[0].state.tokens.some(t=>t.owner==='opponent'&&db.bindings[t.key]===19364&&t.zone==='m2'),
  'The opponent monster stays in place if the effect was negated');
const extra=scenario({opponentCid:19188,opponentExtra:true});
const extras=actions(extra.s).filter(a=>a.state.tokens.some(t=>t.owner==='opponent'&&
  db.bindings[t.key]===19188&&t.zone==='extra'));
assert.equal(extras.length,1);
assert(extras[0].state.tokens.some(t=>t.owner==='opponent'&&db.bindings[t.key]===19188&&t.zone==='extra'),
  'An Extra Deck monster returns to its original Extra Deck, not the hand');
const stolen=scenario({stolen:true});
const stolenActions=actions(stolen.s).filter(a=>a.state.tokens.some(t=>t.owner==='opponent'&&
  db.bindings[t.key]===19364&&t.zone==='hand'));
assert.equal(stolenActions.length,1);
assert(stolenActions[0].state.tokens.some(t=>t.owner==='opponent'&&db.bindings[t.key]===19364&&
  t.zone==='hand'&&t.controller==='opponent'),
  'Returning a stolen monster must return it to its owner, not its current controller');
// TY-PHON may legally return itself to the Extra; remaining Xyz materials go to GY.
let selfScenario=scenario();
const secondMaterial=key(19364,'main');
selfScenario.s=E.place(selfScenario.s,{key:secondMaterial,owner:'self',
  zone:'material:'+selfScenario.typhon.uid});
const selfBounce=actions(selfScenario.s).find(a=>a.state.tokens.some(t=>
  t.owner==='self'&&db.bindings[t.key]===19184&&t.zone==='extra'));
assert(selfBounce,'A TY-PHON with two materials may return itself to its Extra');
assert.equal(selfBounce.state.tokens.filter(t=>t.owner==='self'&&
  db.bindings[t.key]===19364&&t.zone==='graveyard').length,2,
  'One material is detached as cost and the remaining material falls to GY on return');
const chain=E.appendChain(s,{side:'self',cardName:'TY-PHON',
  effect:'Desacoplar un material y devolver un monstruo',ruleId:effectId,actorUid:typhon.uid});
const preview=Chain.resolve(chain,db);
assert.equal(preview.status,'unresolved','Non-targeting TY-PHON effect must not invent a chain replay');
assert(/TY-PHON/.test(preview.reason));
console.log('PASS M01 TY-PHON: 1 material, once per turn, main/extra ownership, negate-cost and fail-closed chains');
