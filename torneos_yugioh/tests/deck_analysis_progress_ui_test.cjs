'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.resolve(__dirname,'..');
const src=fs.readFileSync(path.join(root,'assets/modules/deck-guide/analysis-progress.js'),'utf8');
const footer=fs.readFileSync(path.join(root,'includes/presentation/layout/footer.php'),'utf8');
const screen=fs.readFileSync(path.join(root,'includes/presentation/screens/deck_ficha.php'),'utf8');
const dependencies=fs.readFileSync(path.join(root,'includes/bootstrap/dependencies.php'),'utf8');
const php=fs.readFileSync(path.join(root,'includes/decks/analysis-progress-http.php'),'utf8');
assert(footer.includes('assets/modules/deck-guide/analysis-progress.js'));
assert(footer.includes('assets/modules/deck-guide/analysis-progress.css'));
assert(screen.includes('data-deck-analysis-progress'));
assert(dependencies.includes("api'] ?? '') === 'deck_analysis_progress'"));
for(const term of ['ygo_can_edit()', "HTTP_X_CSRF_TOKEN", 'explicit_user_assignment',
   'explicit_a10b_request', 'inventory_sha256','revision']){
  assert(php.includes(term),'Backend must enforce '+term);
}
for(const bad of ['fetch("https://api.openai.com','issueNewTurn','gpt-4o','Codex']){
  assert(!src.includes(bad),'The grid must not start AI analysis.');
}
let polling;
let assignedStatus=false;
const listeners=new Map();
const calls=[];
const contents={innerHTML:'',textContent:'',addEventListener(name,fn){listeners.set('content:'+name,fn);},
 querySelector(){return null},insertAdjacentHTML(){}};
const headline={textContent:''};
const panel={hidden:true,querySelector(sel){
 if(sel==='[data-deck-progress-content]')return contents;
 if(sel==='[data-deck-progress-headline]')return headline;
 throw Error('unknown panel node '+sel);
}};
const picker={value:'',addEventListener(name,fn){listeners.set('picker:'+name,fn);}};
const doc={querySelector(sel){
 if(sel==='[data-deck-analysis-progress]')return panel;
 if(sel==='[data-deck-select]')return picker;
 return null;
}};
const windowStub={setInterval(fn){polling=fn;}};
async function fetchStub(url,options){
 calls.push({url,options});
 const fixture=assignedStatus ? {
   ok:true,token:'synthetic-token',assigned:true,eligible:true,
   inventory_counts:{main:40,extra:15,side:15,reserve:0},
   ledger:{revision:1,calculator_requested:false,tasks:[
     {task_id:'A01-T1',stage_id:'A01',mandatory:true,description:'Tarea sintética',
       status:'PAUSED',attempts:1,elapsed_seconds:30}
   ]},
   manifest:{stages:[{stage_id:'A01',mandatory:true,tasks:[{task_id:'A01-T1'}]}]},
   summary:{total:1,complete:0,blocked:0,elapsed_seconds:30,eta_seconds:null,
     stages:[{stage_id:'A01',elapsed_seconds:30,eta_seconds:null}]}
 } : {
   ok:true,token:'synthetic-token',assigned:false,eligible:true,
   inventory_counts:{main:40,extra:15,side:15,reserve:0}
 };
 return {ok:true,json:async()=>fixture};
}
vm.runInNewContext(src,{window:windowStub,document:doc,fetch:fetchStub});
assert.equal(calls.length,0,'No deck chosen means no API requests.');
assert.equal(typeof polling,'function');
picker.value='98765';polling();
setImmediate(()=>{
 assert.equal(calls.length,1,'A selected deck may load READ-ONLY progress status.');
 assert(calls[0].url.includes('deck_id=98765&op=status'));
 assert.equal(calls[0].options.method,undefined,'A deck selection may not assign it.');
 assert(contents.innerHTML.includes('Confirmo que asigno expresamente'));
 assert.equal(panel.hidden,false,'The selected deck shows progress admission controls.');
 assert.equal(calls.filter(x=>x.options.method==='POST').length,0,
   'Selecting a deck must not start or authorize its analysis.');
 assignedStatus=true;
 picker.value='98766';
 polling();
 setImmediate(()=>{
   assert(contents.innerHTML.includes('Reanudar tarea sin repetir análisis'),
     'Persisted PAUSED checkpoint must render a safe resume control.');
   assert(contents.innerHTML.includes('Pausada, reanudable'));
   assert.equal(calls.filter(x=>x.options.method==='POST').length,0,
     'Viewing a paused task must not implicitly resume or invoke AI.');
   assert(!contents.innerHTML.includes('Iniciar tarea</button>'),
     'A paused checkpoint must not start over with a new task.');
   console.log(JSON.stringify({ok:true,syntheticUI:true,analysisStarted:false,
     inferredAssignments:0,pausedRecoveryRendered:true}));
 });

});
