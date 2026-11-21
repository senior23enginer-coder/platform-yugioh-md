'use strict';
// Read-only characterization of the 12 TRUSTED, existing local legacy tests.
// Does not analyze a deck, import data, modify source or invoke an LLM.
// Optional --emit writes a gzipped JSON golden trace to stdout for review.
// No arbitrary user-provided code paths are accepted.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const Module=require('node:module');
const crypto=require('node:crypto');
const zlib=require('node:zlib');
const {spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'..');
const testRoot=path.join(root,'torneos_yugioh/tests');
const hash=state=>crypto.createHash('sha256').update(JSON.stringify(state)).digest('hex');
const sourceSha=buffer=>crypto.createHash('sha1').update(
  Buffer.concat([Buffer.from('blob '+buffer.length+'\0'),buffer])).digest('hex');
function capture(n){
  assert(Number.isInteger(n)&&n>=1&&n<=12,'Only C01-C12 historical tests are allowed');
  const id='C'+String(n).padStart(2,'0');
  const file=path.join(testRoot,'memento_'+id.toLowerCase()+'_route_replay_test.cjs');
  const raw=fs.readFileSync(file);
  const src=raw.toString('utf8');
  const pattern=/return (a|found|chosen|selected)\.state;/g;
  assert.equal([...src.matchAll(pattern)].length,1,'Unsafe helper transformation: '+id);
  const records=[];
  global.__p06capture=(effect,before,after)=>{
    assert(/^671:[a-z0-9:-]+$/.test(effect),'Unexpected legacy effect ID');
    records.push({effect_id:effect,before:JSON.parse(JSON.stringify(before)),
      after:JSON.parse(JSON.stringify(after))});
  };
  const transformed=src.replace(pattern,
    (match,varname)=>'global.__p06capture(id,s,'+varname+'.state);'+match);
  const m=new Module(file,module);
  m.filename=file;
  m.paths=Module._nodeModulePaths(path.dirname(file));
  const oldLog=console.log;
  try{
    console.log=()=>{};
    m._compile(transformed,file);
  }finally{
    console.log=oldLog;
    delete global.__p06capture;
  }
  assert(records.length>0,'No original actions captured for '+id);
  const interruptions=[];
  for(let i=1;i<records.length;i++)
    if(hash(records[i-1].after)!==hash(records[i].before))
      interruptions.push({before_step:i+1,
        previous_after:hash(records[i-1].after),
        next_before:hash(records[i].before)});
  return {route_id:'671:A09:'+id,source_test:path.relative(root,file),
    source_blob_sha:sourceSha(raw),initial_state:records[0].before,
    steps:records.map(r=>({effect_id:r.effect_id,
      after_state_sha256:hash(r.after)})),
    interruptions,observed_actions:records.length};
}
if(process.argv.includes('--single')){
  const number=Number(process.argv.find(x=>/^--route=\d+$/.test(x))?.slice(8));
  const trace=capture(number);
  process.stdout.write(JSON.stringify(trace)+'\n');
}else{
  const traces=[];
  for(let n=1;n<=12;n++){
    const out=spawnSync(process.execPath,[__filename,'--single','--route='+n],{
      cwd:root,encoding:'utf8',maxBuffer:16*1024*1024});
    if(out.status!==0)throw Error('Legacy characterization failed at C'+n+
      '\n'+(out.stderr||out.stdout));
    traces.push(JSON.parse(out.stdout));
  }
  const document={schema:'station-x-p06-golden-route-states/1',
    provenance:'historical trusted tests; regression-only, not independent oracle',
    deck_id:671,format:'tcg_advanced',routes:traces};
  if(process.argv.includes('--emit-json')){
    process.stdout.write('P06_JSON='+JSON.stringify(document)+'\n');
  }else if(process.argv.includes('--emit')){
    process.stdout.write('P06_GZIP_BASE64='+
      zlib.gzipSync(Buffer.from(JSON.stringify(document))).toString('base64')+'\n');
  }else{
    process.stdout.write(JSON.stringify({ok:true,routes:12,
      actionCounts:traces.map(t=>({id:t.route_id,count:t.observed_actions})),
      totalActions:traces.reduce((a,t)=>a+t.observed_actions,0),
      nonActionGaps:traces.filter(t=>t.interruptions.length)
        .map(t=>({route:t.route_id,gaps:t.interruptions.length})),
      analysesExecuted:0})+'\n');
  }
}
