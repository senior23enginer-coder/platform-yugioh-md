'use strict';
// Single reusable golden-state regression runner for trusted pre-existing
// Memento route tests. This DOES NOT replace independent paper semantics.
// It verifies physical state hashes selected by the old tested implementation,
// without executing A01-A10 or creating route-specific JavaScript files.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');
const E=require('../torneos_yugioh/assets/modules/play-calculator/engine.js');
const R=require('../torneos_yugioh/assets/modules/play-calculator/rules.js');
const Study=require('../torneos_yugioh/assets/modules/play-calculator/study.js');
const facts=require('../torneos_yugioh/assets/modules/play-calculator/card-facts.json');
const root=path.resolve(__dirname,'..'),file=process.argv[2];
assert(file,'Provide a reviewed GPT P06 fixture; never discover or analyze decks');
const fixture=JSON.parse(fs.readFileSync(path.resolve(file),'utf8'));
assert.equal(fixture.schema,'station-x-p06-golden-route-states/1');
assert.equal(fixture.deck_id,671);
assert.equal(fixture.format,'tcg_advanced');
assert.equal(fixture.provenance,
  'historical trusted tests; regression-only, not independent oracle');
assert.equal(fixture.legacy_tests_preserved,true);
assert.equal(fixture.requires_independent_semantic_parity,true);
assert.equal(fixture.routes.length,12);
const study=JSON.parse(fs.readFileSync(path.join(root,
 'torneos_yugioh/assets/editorial/20261117-folder-delivery-v40/671.json'),'utf8'));
assert.equal(study.deck_id,671);
const rows=study.cards.flatMap(c=>c.slots.map(slot=>({
 id:slot.card_id,deck_id:671,card_name:c.name,
 section:slot.section,quantity:slot.quantity
})));
const cat=E.catalog({id:671,name:study.name,format_codes:[study.format]},rows);
const db=Study.bind(facts,[{catalog:cat,study}]);
assert.equal(db.ruleImports[0].status,'accepted');
const digest=x=>crypto.createHash('sha256').update(JSON.stringify(x)).digest('hex');
const blobHash=bytes=>crypto.createHash('sha1').update(
 Buffer.concat([Buffer.from('blob '+bytes.length+'\0'),bytes])).digest('hex');
const validKeys=new Set(cat.cards.map(x=>x.key));
const outcomes=[];
let passedTransitions=0;
const routeIds=new Set();
for(const route of fixture.routes){
 assert(/^671:A09:C(?:0[1-9]|1[0-2])$/.test(route.route_id));
 assert(!routeIds.has(route.route_id),'Duplicate golden route');
 routeIds.add(route.route_id);
 const official=study.routes.find(r=>r.id===route.route_id);
 assert(official,'Canonical route must exist');
 assert.equal(official.steps.length,route.source_documented_steps);
 assert.equal(official.replay_certification?.test,route.source_test);
 assert.equal(official.replay_certification?.executed_transitions,
   route.source_declared_transitions);
 const sourcePath=path.join(root,route.source_test);
 const buf=fs.readFileSync(sourcePath);
 assert.equal(blobHash(buf),route.source_blob_sha,'Legacy source script changed');
 assert(Array.isArray(route.steps)&&route.steps.length>0);
 assert.equal(route.observed_actions,route.steps.length);
 const sourceEffects=[...buf.toString('utf8').matchAll(
  /\bs\s*=\s*(?:apply|choose|step|take|replay)\(\s*s\s*,\s*['"](671:[^'"]+)['"]/g
 )].map(m=>m[1]);
 assert.deepEqual(route.steps.map(s=>s.effect_id),sourceEffects);
 assert(route.initial_state&&Array.isArray(route.initial_state.tokens));
 const state=JSON.parse(JSON.stringify(route.initial_state));
 E.validate(state);
 for(const token of state.tokens)
   assert(validKeys.has(token.key),'Golden initial state has foreign physical card '+token.key);
 // Live capture may have no per-player card catalogue (free practice);
 // physical tokens must still belong to the approved native inventory.
 assert.equal(state.format,study.format);
 for(const side of ['self','opponent'])
   for(const card of state[side]?.cards||[])
     assert(validKeys.has(card.key),'Foreign inventory reference in golden state');
 let current=state,completed=0,reason='';
 for(let i=0;i<route.steps.length;i++){
   const gap=route.interruptions.find(g=>g.before_step===i+1);
   if(gap){
     assert.equal(digest(current),gap.previous_after,'Unexpected state before historical event gap');
     if(!Array.isArray(gap.operations)||gap.operations.length===0){
       reason='UNMODELED_DIRECT_TURN_OR_RIVAL_EVENT';
       break;
     }
     for(const op of gap.operations){
       switch(op.op){
       case 'advance_phase':
         current=E.advancePhase(current);
         assert.equal(current.phase,op.expected,'Unexpected captured phase change');
         break;
       case 'advance_turn':
         current=E.advanceTurn(current);
         assert.equal(current.active,op.expected,'Unexpected opponent turn');
         break;
       case 'place_known_opponent':{
         assert(Number.isInteger(op.cid)&&['m1','m2','m3','m4','m5'].includes(op.zone));
         const card=cat.cards.find(c=>db.bindings[c.key]===op.cid&&c.section==='main');
         assert(card,'Opponent observation must reference existing physical CID');
         current=E.place(current,{key:card.key,owner:'opponent',zone:op.zone});
         break;
       }
       case 'observe_opponent_activation':{
         assert(typeof op.description==='string'&&op.description.length>8);
         const candidates=current.tokens.filter(t=>t.owner==='opponent'&&
           db.bindings[t.key]===op.cid&&t.zone===op.zone);
         assert.equal(candidates.length,1,'Opponent activation requires one observed physical card');
         current=E.observeOpponentActivation(current,{
           uid:candidates[0].uid,effect:op.description
         });
         break;
       }
       default:
         throw Error('Unsupported declarative golden-state operation: '+String(op.op));
       }
       E.validate(current);
     }
     if(digest(current)!==gap.next_before){
       reason='EXPLICIT_EVENT_SEQUENCE_DID_NOT_MATCH_LEGACY_GAP';
       break;
     }
   }
   const step=route.steps[i];
   assert(/^671:[a-z0-9:-]+$/.test(step.effect_id));
   assert(/^[0-9a-f]{64}$/.test(step.after_state_sha256));
   const before=JSON.stringify(current);
   const available=R.actions(current,db).actions.filter(a=>
     a.event?.ruleId===step.effect_id);
   assert.equal(JSON.stringify(current),before,'A read-only regression changed input');
   const matching=available.filter(a=>digest(a.state)===step.after_state_sha256);
   if(matching.length===0){
     reason='GOLDEN_STATE_NOT_REPRODUCED_AT_STEP_'+(i+1);
     break;
   }
   const exact=JSON.stringify(matching[0].state);
   assert(matching.every(a=>JSON.stringify(a.state)===exact),
     'Distinct states cannot share the same accepted trace');
   current=matching[0].state;
   E.validate(current);
   completed++;
   passedTransitions++;
 }
 outcomes.push({route_id:route.route_id,passedTransitions:completed,
   expectedTransitions:route.steps.length,fullGoldenReplay:!reason,
   reason,metadataMismatch:route.known_metadata_discrepancy===true});
}
assert.equal(outcomes.length,12);
assert.deepEqual(fixture.known_mismatches,['671:A09:C02']);
assert.equal(fixture.direct_golden_routes,11);
assert.equal(passedTransitions,105,'All historical transition hashes must be verified');
const complete=outcomes.filter(x=>x.fullGoldenReplay);
assert.equal(complete.length,12,'All 12 historical routes must replay byte-for-byte');
assert.deepEqual(outcomes.filter(x=>x.reason).map(x=>x.route_id),[]);
const mismatch=outcomes.find(x=>x.route_id==='671:A09:C02');
assert(mismatch.fullGoldenReplay&&mismatch.metadataMismatch,
  'C02 is replayed but its documented transition count remains discrepant');
console.log(JSON.stringify({ok:true,routes:12,completeGoldenReplays:11,
 passedTransitions,knownGaps:['671:A09:C08'],
 knownMetadataDiscrepancies:['671:A09:C02'],
 independentSemanticParity:false,legacyTestsDeleted:0,deckAnalysesExecuted:0,
 details:outcomes}));
