const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict'),{spawn,spawnSync}=require('node:child_process');
const C=require('../assets/modules/combo-creator/documented-import.js');
const app=path.resolve(__dirname,'..'),out=path.resolve(app,'../outputs/combo-source-sync-test'),policy=require('../assets/editorial/strategy-content-state.json');
(async()=>{
 fs.mkdirSync(out,{recursive:true});const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const origin=`http://127.0.0.1:${port}`,server=spawn(process.env.PHP_BINARY||'php',['-S',`127.0.0.1:${port}`,'-t',app,path.join(__dirname,'combo_creator_router.php')],{env:{...process.env,COMBO_TEST_DB:path.join(out,`source-${Date.now()}.sqlite`)},stdio:['ignore','ignore','pipe']});
 try{
  for(let i=0;i<60;i++){try{if((await fetch(origin)).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  const response=await fetch(origin+'/?api=combo_creator&deck_id=665'),cookie=response.headers.get('set-cookie').split(';')[0],inventory=await response.json();
  const data=JSON.parse(fs.readFileSync(path.join(app,policy.checked_studies['665'].path))),doc=C.convert(data,data.routes[4],inventory.cards,inventory.skills).document;
  doc.title='Combo revisado automáticamente';doc.nodes[0].explanation='Cambio de explicación conservado en el prompt.';
  const save=async(d,revision)=>{const r=await fetch(origin+'/?api=combo_creator',{method:'POST',headers:{Cookie:cookie,'Content-Type':'application/json','X-CSRF-Token':inventory.token},body:JSON.stringify({deck_id:665,revision,inventory_sha256:inventory.inventory_sha256,document:d})});return {status:r.status,body:await r.json()};};
  let saved=await save(doc,0);assert(saved.body.ok,JSON.stringify(saved));assert.equal(saved.body.record.document.source_route_id,doc.source_route_id);
  const feedResponse=await fetch(origin+'/?api=combo_creator&source_prompts=1'),etag=feedResponse.headers.get('etag'),feed=await feedResponse.json();assert(feed.ok);assert(!('token'in feed));assert.equal(feed.records.length,1);assert(feed.records[0].markdown.includes(doc.title));assert(feed.records[0].markdown.includes(doc.nodes[0].explanation));
  assert.equal((await fetch(origin+'/?api=combo_creator&source_prompts=1',{headers:{'If-None-Match':etag}})).status,304);
  const file=path.join(out,'feed.json');fs.writeFileSync(file,JSON.stringify(feed));const run=spawnSync(process.env.PYTHON_BINARY||'python',[path.join(__dirname,'combo_source_sync_test.py'),file],{encoding:'utf8'});assert.equal(run.status,0,run.stdout+run.stderr);
  doc.title='Segunda revisión';saved=await save(doc,1);assert(saved.body.ok);const changed=await fetch(origin+'/?api=combo_creator&source_prompts=1',{headers:{'If-None-Match':etag}});assert.equal(changed.status,200);assert.equal((await changed.json()).records[0].revision,2);
  assert.equal((await save({...doc,source_route_id:'otra-ruta'},2)).status,422);
  const duplicate={...doc,id:'manual-duplicate-12345678'};delete duplicate.source_route_id;assert((await save(duplicate,0)).body.ok);assert.equal((await(await fetch(origin+'/?api=combo_creator&source_prompts=1')).json()).records.length,1);
  console.log(JSON.stringify({ok:true,nativeSave:true,sourceMarkdown:true,etag:true,localSourceUpdate:true,reimportPrecedence:true,history:true,foreignRouteRejected:true,manualDuplicateIndependent:true,productionWrites:0}));
 }finally{server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
