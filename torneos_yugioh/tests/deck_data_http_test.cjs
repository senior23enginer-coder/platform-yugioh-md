const assert=require('assert/strict'),net=require('net'),path=require('path'),{spawn}=require('child_process');
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const server=spawn(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',['-d','extension=zip','-d','upload_max_filesize=48M','-d','post_max_size=50M','-S',`127.0.0.1:${port}`,path.join(__dirname,'deck_data_router.php')],{stdio:['ignore','ignore','pipe']});let logs='';server.stderr.on('data',b=>logs+=b);
 try{
  const base=`http://127.0.0.1:${port}/?api=deck_data&deck_id=665&op=`;let response;
  for(let i=0;i<80;i++){try{response=await fetch(base+'status');if(response.ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  assert(response?.ok,logs);const cookie=response.headers.get('set-cookie').split(';')[0],status=await response.json();assert.equal(status.ok,true);
  response=await fetch(base+'export',{headers:{cookie}});assert.equal(response.status,200,logs);assert.match(response.headers.get('content-type'),/application\/zip/);const bytes=await response.arrayBuffer();assert.equal(Buffer.from(bytes).subarray(0,2).toString(),'PK');
  const data=new FormData();data.append('package',new Blob([bytes],{type:'application/zip'}),'Kashtira.zip');
  response=await fetch(base+'preview',{method:'POST',headers:{cookie,'X-CSRF-Token':status.token},body:data});const preview=await response.json();assert.equal(preview.ok,true,JSON.stringify(preview));assert.equal(preview.cards,38);assert.equal(preview.routes,16);assert.equal(preview.removed_routes,0);assert.equal(preview.inventory_changed,false);
  response=await fetch(base+'preview',{method:'POST',headers:{cookie},body:data});assert.equal(response.status,422);assert.match((await response.json()).message,/sesión/);
  console.log(JSON.stringify({ok:true,realZipExportBytes:bytes.byteLength,realMultipartPreview:true,csrf:true,productionWrites:0}));
 }finally{server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
