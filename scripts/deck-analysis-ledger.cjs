'use strict';
// Generic, side-effect-free tracking of ONE explicitly assigned physical deck.
// No AI calls, no database writes, no deck selection, no analysis execution.
const STATUS=Object.freeze({PENDING:'PENDING',RUNNING:'RUNNING',
  BLOCKED:'BLOCKED',COMPLETE:'COMPLETE'});
const SECTIONS=Object.freeze(['main','extra','side']);
function stamp(value){
  if(typeof value!=='string'||!Number.isFinite(Date.parse(value))||
     new Date(value).toISOString()!==value)
    throw Error('TIME_REQUIRES_UTC_ISO_TIMESTAMP');
  return Date.parse(value);
}
function assertAdmission({deckId,explicitlyAssigned,inventoryCounts}){
  if(explicitlyAssigned!==true||!Number.isInteger(deckId)||deckId<=0)
    throw Error('EXPLICIT_USER_DECK_SELECTION_REQUIRED');
  if(!inventoryCounts||typeof inventoryCounts!=='object')
    throw Error('DECK_INVENTORY_NOT_LOADED');
  for(const section of SECTIONS)
    if(!Number.isInteger(inventoryCounts[section])||inventoryCounts[section]<=0)
      throw Error('REQUIRED_'+section.toUpperCase()+'_NOT_LOADED');
  if(inventoryCounts.reserve!==undefined&&
    (!Number.isInteger(inventoryCounts.reserve)||inventoryCounts.reserve<0))
    throw Error('INVALID_OPTIONAL_RESERVE');
}
function validateManifest(manifest){
  if(manifest?.schema!=='station-x-a01-a10-task-manifest/1'||
    !Array.isArray(manifest.stages)||manifest.stages.length!==11||
    JSON.stringify(manifest.required_sections)!==JSON.stringify(SECTIONS)||
    JSON.stringify(manifest.optional_sections)!==JSON.stringify(['reserve']))
    throw Error('TASK_MANIFEST_INCOMPATIBLE');
  const ids=new Set();let mandatory=0,optional=0;
  for(const stage of manifest.stages){
    if(!/^(?:A0[1-9]|A10A|A10B)$/.test(stage.stage_id)||
      typeof stage.mandatory!=='boolean'||!Array.isArray(stage.tasks)||
      stage.tasks.length===0||stage.mandatory===(stage.stage_id==='A10B'))
      throw Error('INVALID_TASK_STAGE');
    for(const task of stage.tasks){
      if(!task||!/^A(?:0[1-9]|10[AB])-T\d+$/.test(task.task_id)||
        !task.task_id.startsWith(stage.stage_id+'-')||
        ids.has(task.task_id)||!String(task.description||'').trim())
        throw Error('DUPLICATE_OR_INVALID_TASK');
      ids.add(task.task_id);
      if(stage.mandatory)mandatory++;else optional++;
    }
  }
  if(ids.size!==38||mandatory!==35||optional!==3)throw Error('TASKS_MUST_BE_35_PLUS_3');
  return true;
}
function createLedger({deckId,explicitlyAssigned=false,inventoryCounts,manifest,at,
  enableCalculator=false}={}){
  assertAdmission({deckId,explicitlyAssigned,inventoryCounts});validateManifest(manifest);stamp(at);
  return {schema:'station-x-deck-analysis-ledger/1',deck_id:deckId,
    explicitly_user_assigned:true,inventory_counts:{...inventoryCounts},
    created_at:at,updated_at:at,calculator_requested:enableCalculator===true,
    tasks:manifest.stages.flatMap(stage=>stage.tasks.map(task=>({
      task_id:task.task_id,stage_id:stage.stage_id,mandatory:stage.mandatory,
      description:task.description,status:STATUS.PENDING,evidence:null,
      block_reason:null,started_at:null,finished_at:null,elapsed_seconds:0,
      attempts:0
    })))};
}
function transition(ledger,taskId,next,{at,evidence=null,reason=null}={}){
  if(ledger?.schema!=='station-x-deck-analysis-ledger/1'||
    !Object.values(STATUS).includes(next))throw Error('INVALID_LEDGER_OR_TRANSITION');
  const now=stamp(at),prev=stamp(ledger.updated_at);
  if(now<prev)throw Error('NON_MONOTONIC_LEDGER_TIME');
  const result=JSON.parse(JSON.stringify(ledger));
  const task=result.tasks.find(t=>t.task_id===taskId);
  if(!task)throw Error('UNKNOWN_TASK_ID');
  if(!task.mandatory&&!result.calculator_requested)
    throw Error('A10B_NOT_REQUESTED');
  const was=task.status;
  if(next===STATUS.RUNNING){
    if(was!==STATUS.PENDING&&was!==STATUS.BLOCKED)throw Error('TASK_NOT_READY_TO_START');
    task.status=next;task.started_at=at;task.finished_at=null;
    task.attempts++;task.block_reason=null;task.evidence=null;
  }else{
    if(was!==STATUS.RUNNING)throw Error('ONLY_RUNNING_TASK_CAN_FINISH');
    if(next!==STATUS.COMPLETE&&next!==STATUS.BLOCKED)throw Error('INVALID_FINISH_STATUS');
    const start=stamp(task.started_at);
    if(now<start)throw Error('TASK_TIME_BEFORE_START');
    if(next===STATUS.COMPLETE&&!(typeof evidence==='string'&&evidence.trim()))
      throw Error('EVIDENCE_REQUIRED_FOR_COMPLETE');
    if(next===STATUS.BLOCKED&&!(typeof reason==='string'&&reason.trim()))
      throw Error('BLOCK_REASON_REQUIRED');
    task.elapsed_seconds+=(now-start)/1000;
    task.status=next;task.finished_at=at;task.started_at=null;
    task.evidence=next===STATUS.COMPLETE?evidence.trim():null;
    task.block_reason=next===STATUS.BLOCKED?reason.trim():null;
  }
  result.updated_at=at;
  return result;
}
function summary(ledger,history=[]){
  if(ledger?.schema!=='station-x-deck-analysis-ledger/1')throw Error('INVALID_LEDGER');
  const relevant=ledger.tasks.filter(t=>t.mandatory||ledger.calculator_requested);
  const stages=[...new Set(relevant.map(t=>t.stage_id))].map(stage_id=>{
    const tasks=relevant.filter(t=>t.stage_id===stage_id);
    return {stage_id,total:tasks.length,complete:tasks.filter(t=>t.status===STATUS.COMPLETE).length,
      blocked:tasks.filter(t=>t.status===STATUS.BLOCKED).length,
      elapsed_seconds:tasks.reduce((sum,t)=>sum+t.elapsed_seconds,0)};
  });
  // ETA is *unavailable* until at least three actual observed, comparable
  // durations exist for every unfinished task. No fixed timing claims.
  let estimatedRemaining=0,calibrated=true;
  for(const task of relevant.filter(t=>t.status!==STATUS.COMPLETE)){
    // Require five distinct observed decks, never five synthetic attempts
    // for one deck or data copied from the deck under evaluation.
    const byDeck=new Map();
    for (const sample of history) if(sample?.task_id===task.task_id &&
      Number.isInteger(sample.deck_id)&&sample.deck_id>0 &&
      sample.deck_id!==ledger.deck_id &&
      Number.isFinite(sample.elapsed_seconds)&&sample.elapsed_seconds>0) {
      if(!byDeck.has(sample.deck_id))byDeck.set(sample.deck_id,sample.elapsed_seconds);
    }
    if(byDeck.size<5){calibrated=false;break;}
    const sorted=[...byDeck.values()].sort((a,b)=>a-b);
    estimatedRemaining+=sorted[Math.floor(sorted.length/2)];
  }
  return {deck_id:ledger.deck_id,mandatory_total:ledger.tasks.filter(t=>t.mandatory).length,
    total:relevant.length,complete:relevant.filter(t=>t.status===STATUS.COMPLETE).length,
    blocked:relevant.filter(t=>t.status===STATUS.BLOCKED).length,
    elapsed_seconds:relevant.reduce((n,t)=>n+t.elapsed_seconds,0),
    remaining:relevant.filter(t=>t.status!==STATUS.COMPLETE).length,
    eta_seconds:calibrated?estimatedRemaining:null,
    eta_status:calibrated?'CALIBRATED_FROM_REAL_HISTORY':'INSUFFICIENT_REAL_SAMPLES',
    stages};
}
module.exports=Object.freeze({STATUS,assertAdmission,validateManifest,createLedger,transition,summary});
