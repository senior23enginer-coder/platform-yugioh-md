'use strict';
const assert=require('node:assert/strict');
const L=require('./deck-analysis-ledger.cjs');
const stages=['A01','A02','A03','A04','A05','A06','A07','A08','A09','A10A','A10B'];
const counts=[3,3,3,3,4,4,4,4,4,3,3];
const manifest={schema:'station-x-a01-a10-task-manifest/1',
 required_sections:['main','extra','side'],optional_sections:['reserve'],
 stages:stages.map((stage_id,i)=>({stage_id,mandatory:stage_id!=='A10B',
  tasks:Array.from({length:counts[i]},(_,j)=>({
    task_id:stage_id+'-T'+(stage_id==='A10B'?j+4:j+1),
    description:'Synthetic generic task '+j
  }))}))};
const inventoryCounts={main:40,extra:15,side:15};
const at='2026-11-21T00:00:00.000Z';
const args={deckId:123456,explicitlyAssigned:true,inventoryCounts,manifest,at};
L.validateManifest(manifest);
assert.throws(()=>L.createLedger({...args,explicitlyAssigned:false}),/EXPLICIT_USER/);
assert.throws(()=>L.createLedger({...args,deckId:null}),/EXPLICIT_USER/);
for(const section of ['main','extra','side'])
 assert.throws(()=>L.createLedger({...args,inventoryCounts:{...inventoryCounts,[section]:0}}),
   new RegExp('REQUIRED_'+section.toUpperCase()));
assert(L.createLedger({...args,inventoryCounts:{...inventoryCounts,reserve:0}}));
assert(L.createLedger({...args,inventoryCounts:{...inventoryCounts,reserve:3}}));
assert.throws(()=>L.createLedger({...args,inventoryCounts:{...inventoryCounts,reserve:-1}}),
  /INVALID_OPTIONAL_RESERVE/);
let ledger=L.createLedger(args);
assert.equal(ledger.tasks.length,38);
assert.equal(L.summary(ledger).total,35,'A10B must not be counted unless requested');
assert.equal(L.summary(ledger).eta_seconds,null,'Never manufacture an ETA without measurements');
assert.throws(()=>L.transition(ledger,'A01-T1',L.STATUS.COMPLETE,
 {at:'2026-11-21T01:00:00.000Z',evidence:'citation'}),/ONLY_RUNNING/);
assert.throws(()=>L.transition(ledger,'A10B-T4',L.STATUS.RUNNING,
 {at:'2026-11-21T01:00:00.000Z'}),/A10B_NOT_REQUESTED/);
ledger=L.transition(ledger,'A01-T1',L.STATUS.RUNNING,
 {at:'2026-11-21T01:00:00.000Z'});
assert.throws(()=>L.transition(ledger,'A01-T1',L.STATUS.COMPLETE,
 {at:'2026-11-21T01:02:00.000Z'}),/EVIDENCE_REQUIRED/);
const before=JSON.stringify(ledger);
ledger=L.transition(ledger,'A01-T1',L.STATUS.COMPLETE,
 {at:'2026-11-21T01:02:00.000Z',evidence:'verified source/case and output'});
assert.notEqual(JSON.stringify(ledger),before);
assert.equal(ledger.tasks[0].elapsed_seconds,120);
assert.equal(ledger.tasks[0].status,L.STATUS.COMPLETE);
assert.throws(()=>L.transition(ledger,'A01-T1',L.STATUS.RUNNING,
 {at:'2026-11-21T01:02:01.000Z'}),/TASK_NOT_READY/);
ledger=L.transition(ledger,'A01-T2',L.STATUS.RUNNING,{at:'2026-11-21T01:03:00.000Z'});
assert.throws(()=>L.transition(ledger,'A01-T2',L.STATUS.BLOCKED,
 {at:'2026-11-21T01:03:30.000Z'}),/BLOCK_REASON_REQUIRED/);
ledger=L.transition(ledger,'A01-T2',L.STATUS.BLOCKED,
 {at:'2026-11-21T01:03:30.000Z',reason:'No official source'});
assert.equal(L.summary(ledger).blocked,1);
ledger=L.transition(ledger,'A01-T2',L.STATUS.RUNNING,{at:'2026-11-21T01:04:00.000Z'});
ledger=L.transition(ledger,'A01-T2',L.STATUS.COMPLETE,
 {at:'2026-11-21T01:05:00.000Z',evidence:'official source now checked'});
assert.equal(ledger.tasks[1].elapsed_seconds,90,'Measure both real work sessions');
assert.equal(L.summary(ledger).complete,2);
assert.equal(L.summary(ledger).eta_status,'INSUFFICIENT_REAL_SAMPLES');
const history=ledger.tasks.filter(x=>x.mandatory&&x.status!==L.STATUS.COMPLETE)
 .flatMap(t=>[100,110,120].map(seconds=>({task_id:t.task_id,elapsed_seconds:seconds})));
const summary=L.summary(ledger,history);
assert.equal(summary.eta_status,'CALIBRATED_FROM_REAL_HISTORY');
assert.equal(summary.eta_seconds,33*110);
assert.equal(L.summary(L.createLedger({...args,enableCalculator:true})).total,38);
const duplicate=JSON.parse(JSON.stringify(manifest));
duplicate.stages[0].tasks[1].task_id=duplicate.stages[0].tasks[0].task_id;
assert.throws(()=>L.validateManifest(duplicate),/DUPLICATE_OR_INVALID_TASK/);
if(process.argv[2]){
 const fs=require('node:fs');
 const native=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
 L.validateManifest(native);
 const bundled=JSON.parse(fs.readFileSync(
   require('node:path').join(__dirname,'../torneos_yugioh/assets/editorial/a01-a10-task-manifest.json'),'utf8'));
 assert.deepEqual(bundled,native,
   'The deployed Platform task manifest must be identical to the canonical GPT manifest');
 assert.deepEqual(native.stages.map(x=>x.stage_id),manifest.stages.map(x=>x.stage_id),
   'Platform and GPT must understand the same canonical stages');
 assert.deepEqual(native.stages.map(x=>x.tasks.map(t=>t.task_id)),
   manifest.stages.map(x=>x.tasks.map(t=>t.task_id)),
   'Platform and GPT must preserve the same 38 task IDs');
}
console.log(JSON.stringify({ok:true,syntheticFixtures:true,deckAnalysesStarted:0,
 mandatoryTasks:35,optionalCalculatorTasks:3,measuredSeconds:210,etaRequiresRealSamples:true}));
