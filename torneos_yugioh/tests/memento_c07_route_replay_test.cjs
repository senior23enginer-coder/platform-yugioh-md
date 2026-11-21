'use strict';
// C07 exact-state replay: own draw identity remains unknown, two real opponent
// cards are destroyed. No immunity or opponent chain is assumed.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const Study=require('../assets/modules/play-calculator/study.js');
const Verified=require('../assets/modules/play-calculator/verified-rules.js');
const facts=require('../assets/modules/play-calculator/card-facts.json');
const deck=JSON.parse(fs.readFileSync(__dirname+
 '/../assets/editorial/20261117-folder-delivery-v40/671.json','utf8'));
const route=deck.routes.find(r=>r.id==='671:A09:C07');
assert(route&&route.steps.length===4,'C07 keeps four documented steps');
const rows=deck.cards.flatMap(card=>card.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:card.name,section:slot.section,quantity:slot.quantity
})));
const catalog=E.catalog({id:671,name:deck.name,format_codes:[deck.format]},rows);
const db=Study.bind(facts,[{catalog,study:deck}]);
assert.equal(db.ruleImports[0].status,'accepted',JSON.stringify(db.ruleImports));
assert.equal(db.rules.length,deck.calculator.rules.length);
for(const id of ['671:memento-fusion:fuse','671:guardian-chimera:draw-destroy'])
 assert.deepEqual(deck.calculator.rules.filter(r=>r.id===id),
   Verified.forDeck(671).filter(r=>r.id===id),'Imported/fallback parity '+id);
const key=(cid,section='main')=>{
 const card=catalog.cards.find(c=>db.bindings[c.key]===cid&&c.section===section);
 assert(card,'Missing physical '+cid+' in '+section);return card.key;
};
const at=(s,cid,zone,owner='self')=>s.tokens.filter(t=>db.bindings[t.key]===cid&&t.zone===zone&&t.owner===owner);
const field=(s,controller='self')=>s.tokens.filter(t=>t.controller===controller&&E.isMonster(t.zone));
const actions=(s,id,o={})=>R.actions(s,db,o).actions.filter(a=>a.event?.ruleId===id);
function step(s,id,filter,label){
 const original=JSON.stringify(s),available=actions(s,id);
 const found=available.find(a=>filter(a.state));
 assert(found,label+': no matching state among '+available.length+' eligible choices');
 assert.equal(JSON.stringify(s),original,'Analysis cannot alter captured state');
 assert(found.source&&found.event.ruleId===id,'Only sourced actions accepted');
 return found.state;
}
let s=E.patch(E.create(catalog,catalog),{turn:2,goesFirst:false,active:'self',phase:'main1'});
for(const cid of [19364,19904])s=E.place(s,{key:key(cid),owner:'self',zone:'hand'});
// Opponent's known mirror cards remain real objects, NOT a guessed virtual field.
s=E.place(s,{key:key(19365),owner:'opponent',zone:'m3'});
s=E.place(s,{key:key(19366),owner:'opponent',zone:'m4'});
assert.equal(field(s,'opponent').length,2);
assert.equal(s.unknown.hand,0,'Opponent unknown hand must be independent');
// Step 1: Angwitch Normal, search Tatsun, Special Tatsun, mill exactly 2+3=5.
s=step(s,'671:19364:normal',n=>at(n,19364,'m1').length===1,'C07-1a Normal');
s=step(s,'671:angwitch:search',n=>at(n,19362,'hand').length===1,'C07-1b Search Tatsun');
s=step(s,'671:tatsun:special',n=>at(n,19362,'m2').length===1,'C07-1c Special Tatsun');
s=step(s,'671:tatsun:mill',n=>[19362,19472,20199].every(cid=>at(n,cid,'graveyard').length===1),
 'C07-1d Tatsun self-destruction and 2+3 mill');
// Step 2: Ghattic revives and retrieves the real Shleepy from graveyard.
s=step(s,'671:ghattic:revive',n=>field(n).some(t=>db.bindings[t.key]===19472),
 'C07-2a Ghattic revives');
s=step(s,'671:ghattic:recover',n=>at(n,20199,'hand').length===1,'C07-2b Recover Shleepy');
// Step 3: Three different physical materials, one from hand, two on field.
s=step(s,'671:memento-fusion:fuse',n=>field(n).length===1 &&
 field(n).every(t=>db.bindings[t.key]===16845) &&
 [19364,19472,20199,19362,19904].every(cid=>at(n,cid,'graveyard').length===1),
 'C07-3 Guardian Chimera fusion from exactly 1 hand + 2 field');
const summon=s._ruleEvents.find(e=>e.kind==='fusion_summon'&&e.cid===16845);
assert(summon&&summon.sourceCid===19904&&summon.fromHand===1&&summon.fromField===2,
 'Fusion event must record actual spell and material origins');
const chimera=actions(s,'671:guardian-chimera:draw-destroy');
assert.equal(chimera.length,1,'Two interchangeable opponent cards yield one unordered pair');
const negated=actions(s,'671:guardian-chimera:draw-destroy',
 {negateEffectRuleIds:['671:guardian-chimera:draw-destroy']})[0];
assert(negated&&negated.state.selfUnknownHand===0 &&
 field(negated.state,'opponent').length===2,
 'Negated effect draws and destroys nothing');
const before=JSON.stringify(s);
s=step(s,'671:guardian-chimera:draw-destroy',n=>
 n.selfUnknownHand===1 && field(n,'opponent').length===0 &&
 at(n,19365,'graveyard','opponent').length===1 &&
 at(n,19366,'graveyard','opponent').length===1,
 'C07-4 unknown own draw then 2 real opponent destructions');
assert.notEqual(JSON.stringify(s),before);
assert.equal(s.unknown.hand,0,'Never misattribute unknown own draw to opponent');
assert.equal(s.tokens.filter(t=>t.owner==='self'&&t.zone==='hand').length,0,
 'Unknown own draw MUST NOT be a fabricated known physical card');
assert.equal(field(s).length,1,'Guardian Chimera only own monster');
assert.equal(at(s,19904,'graveyard').length,1,'Mementotlan Fusion is not Polymerization');
assert.equal(s.lp.self,8000);assert.equal(s.lp.opponent,8000);
assert.equal(actions(s,'671:guardian-chimera:draw-destroy').length,0,'Once per turn');
const known=E.place(s,{key:key(19366),owner:'self',zone:'hand'});
assert.equal(known.selfUnknownHand,0,'Recording actual drawn CID reconciles prior unknown');
assert.equal(at(known,19366,'hand').length,1);
const noEvent=E.place(E.create(catalog,catalog),{key:key(16845,'extra'),owner:'self',zone:'m1'});
assert.equal(actions(noEvent,'671:guardian-chimera:draw-destroy').length,0,
 'Manually placed Chimera cannot forge Fusion trigger');
const oldEvent=E.patch(E.clone(negated.state),{phase:'main2'});
assert.equal(actions(oldEvent,'671:guardian-chimera:draw-destroy').length,0,
 'Changed captured state must close immediate summon window');
const onlyOne=E.clone(negated.state);
onlyOne.tokens=onlyOne.tokens.filter(t=>t.owner!=='opponent'||db.bindings[t.key]!==19366);
assert.equal(actions(onlyOne,'671:guardian-chimera:draw-destroy').length,0,
 'Two destructions cannot be claimed with one known opponent card');
const forged=JSON.parse(JSON.stringify(deck.calculator.rules.find(r=>r.id==='671:guardian-chimera:draw-destroy')));
forged.resolve[0].count=2;
assert.throws(()=>R.validate(forged),/Robo desconocido/);
assert.throws(()=>E.validate({...s,selfUnknownHand:99}),/Robo propio desconocido/);
console.log(JSON.stringify({ok:true,route:'C07',documentedSteps:4,
 executedTransitions:8,field:[16845],ownUnknownDraws:1,opponentCardsDestroyed:2,
 opponentPhysicalCids:[19365,19366],opponentResponse:'none',protectionFromPolymerization:false,
 generalChainsOrImmunityCertified:false}));
