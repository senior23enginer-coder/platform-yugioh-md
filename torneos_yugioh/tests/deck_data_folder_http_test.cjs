const assert=require('assert/strict'),fs=require('fs'),os=require('os'),path=require('path'),net=require('net'),{spawn,spawnSync}=require('child_process');
(async()=>{
 const php=process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',app=path.resolve(__dirname,'..'),scratch=fs.mkdtempSync(path.join(os.tmpdir(),'ygo-folder-cycle-'));
 const state=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/strategy-content-state.json'))),entry=state.checked_studies['665'];
 function copy(relative){const source=path.join(app,relative),dest=path.join(scratch,relative);if(!fs.existsSync(source))return;fs.mkdirSync(path.dirname(dest),{recursive:true});fs.copyFileSync(source,dest);}
 copy('assets/editorial/strategy-content-state.json');copy(entry.path.slice(2));copy('assets/editorial/deck-data-sources/index.json');copy('assets/editorial/deck-data-sources/665.zip');
 const record=JSON.parse(fs.readFileSync(path.join(app,entry.path)));function assets(v){if(!v||typeof v!=='object')return;for(const [k,x]of Object.entries(v)){if(['image','full_image'].includes(k)&&typeof x==='string'&&x.startsWith('./assets/'))copy(x.slice(2));else assets(x);}}assets(record);
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const n=s.address().port;s.close(()=>resolve(n));});});
 const server=spawn(php,['-d','extension=zip','-d','upload_max_filesize=48M','-d','post_max_size=50M','-S',`127.0.0.1:${port}`,path.join(__dirname,'deck_data_router.php')],{env:{...process.env,YGO_DATA_TEST_APP:scratch},stdio:['ignore','ignore','pipe']});let logs='';server.stderr.on('data',b=>logs+=b);
 try{
  const url=`http://127.0.0.1:${port}/?api=deck_data&deck_id=665&op=`;let res;
  for(let i=0;i<80;i++){try{res=await fetch(url+'status');if(res.ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  assert(res?.ok,logs);const cookie=res.headers.get('set-cookie').split(';')[0],status=await res.json();
  res=await fetch(url+'export',{headers:{cookie}});assert.equal(res.status,200);const bytes=await res.arrayBuffer();
  const zipfile=path.join(scratch,'Kashtira.zip');fs.writeFileSync(zipfile,Buffer.from(bytes));
  const inspection=spawnSync(php,['-d','extension=zip','-r','$z=new ZipArchive();$z->open($argv[1]);$n=[];for($i=0;$i<$z->numFiles;$i++)$n[]=$z->getNameIndex($i);echo json_encode($n);',zipfile],{encoding:'utf8'});assert.equal(inspection.status,0,inspection.stderr);
  const names=JSON.parse(inspection.stdout);assert(names.length>0);assert(names.every(n=>n.startsWith('DECK_665_KASHTIRA_PURE/')));assert(names.includes('DECK_665_KASHTIRA_PURE/datos/stationx-deck.json'));
  const form=new FormData();form.append('package',new Blob([bytes],{type:'application/zip'}),'Kashtira.zip');
  res=await fetch(url+'preview',{method:'POST',headers:{cookie,'X-CSRF-Token':status.token},body:form});const preview=await res.json();assert.equal(preview.ok,true,JSON.stringify(preview));assert.equal(preview.cards,38);
  const commit=new FormData();commit.append('ticket',preview.ticket);res=await fetch(url+'commit',{method:'POST',headers:{cookie,'X-CSRF-Token':status.token},body:commit});const published=await res.json();assert.equal(published.ok,true,JSON.stringify(published));
  const active=JSON.parse(fs.readFileSync(path.join(scratch,'assets/editorial/strategy-content-state.json')));assert.equal(active.checked_studies['650'].path,state.checked_studies['650'].path);assert.notEqual(active.checked_studies['665'].path,entry.path);
  res=await fetch(url+'export',{headers:{cookie}});assert.equal(res.status,200);fs.writeFileSync(zipfile,Buffer.from(await res.arrayBuffer()));
  const again=spawnSync(php,['-d','extension=zip','-r','$z=new ZipArchive();$z->open($argv[1]);$n=[];for($i=0;$i<$z->numFiles;$i++)$n[]=$z->getNameIndex($i);echo json_encode($n);',zipfile],{encoding:'utf8'});assert.equal(again.status,0,again.stderr);assert(JSON.parse(again.stdout).every(n=>n.startsWith('DECK_665_KASHTIRA_PURE/')));
  console.log(JSON.stringify({ok:true,folder:'DECK_665_KASHTIRA_PURE',exportImportReexport:true,automaticServerPublication:true,otherDecksUnchanged:true,productionWrites:0}));
 }finally{server.kill();await new Promise(r=>server.once('exit',r));fs.rmSync(scratch,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
