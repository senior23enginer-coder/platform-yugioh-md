const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict'),{spawn}=require('node:child_process');
const app=path.resolve(__dirname,'..'),out=path.resolve(__dirname,'../../outputs/deck-worlds-20261114');
(async()=>{
 fs.mkdirSync(out,{recursive:true});const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const origin=`http://127.0.0.1:${port}`,server=spawn(process.env.PHP_BINARY||'php',['-S',`127.0.0.1:${port}`,'-t',app,path.join(__dirname,'deck_worlds_router.php')],{env:{...process.env,WORLD_TEST_DB:path.join(out,`fixture-${Date.now()}.sqlite`)},stdio:'ignore'});let browser;
 try{
  for(let i=0;i<60;i++){try{if((await fetch(origin)).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({headless:true});const ctx=await browser.newContext({viewport:{width:1440,height:1000}}),page=await ctx.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
  await page.goto(origin+'/#mundos',{waitUntil:'networkidle'});await page.waitForFunction(()=>document.querySelectorAll('[data-world-open]').length===6);
  assert.equal(await page.locator('[data-world-rows] tr').count(),6);
  const api=async()=> (await ctx.request.get(origin+'/?api=deck_attributes')).json();
  const get=async id=>(await api()).records.find(r=>r.deck_id===id);
  const open=async code=>{await page.evaluate(()=>location.hash='mundos');await page.locator(`[data-world-open="${code}"]`).click();await page.waitForFunction(c=>document.querySelector('[data-world-title]').textContent.endsWith(c),({dark:'OSCURIDAD',water:'AGUA',fire:'FUEGO',earth:'TIERRA'})[code]);};
  const mutate=async click=>{const wait=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('api=deck_attributes'));await click();const res=await wait;const body=await res.json();assert(res.ok(),JSON.stringify(body));await page.waitForFunction(()=>!document.querySelector('[data-attribute-refresh]').disabled);return body;};
  for(const code of ['dark','water','fire']){await open(code);await page.locator('[data-world-candidate]').selectOption('650');await mutate(()=>page.locator('[data-world-add]').click());assert.equal(await page.locator('[data-world-members] tr').count(),1);}
  assert.deepEqual((await get(650)).attributes,['dark','water','fire']);
  await open('earth');assert(await page.locator('[data-world-candidate] option[value="650"]').evaluate(n=>n.disabled),await page.locator('[data-world-candidate]').innerHTML());assert.equal(await page.locator('[data-world-candidate] option[value="704"]').count(),0);assert.equal(await page.locator('[data-world-candidate] option[value="902"]').count(),0);
  const initial=await api(),post=body=>ctx.request.post(origin+'/?api=deck_attributes',{headers:{'X-CSRF-Token':initial.token},data:body});
  assert.equal((await post({deck_id:650,expected_revision:3,attributes:['dark','water','fire','earth']})).status(),422);
  assert.equal((await post({deck_id:704,expected_revision:0,attributes:['fire']})).status(),422);assert.equal((await post({deck_id:902,expected_revision:0,attributes:['dark']})).status(),422);
  assert.equal((await ctx.request.post(origin+'/?api=deck_attributes',{data:{deck_id:650,expected_revision:3,attributes:['earth']}})).status(),403);
  await open('water');await mutate(()=>page.locator('[data-world-primary="650"]').click());assert.equal((await get(650)).primary,'water');
  await mutate(()=>page.locator('[data-world-remove="650"]').click());assert.deepEqual((await get(650)).attributes,['dark','fire']);
  await open('dark');await page.locator('[data-attribute-edit="650"]').click();await page.locator('[data-attribute-add="wind"]').click();assert(await page.locator('[data-attribute-add="earth"]').isDisabled());await page.locator('[data-attribute-promote="fire"]').click();await mutate(()=>page.locator('[data-attribute-save]').click());
  await page.reload({waitUntil:'networkidle'});await page.waitForFunction(()=>document.querySelectorAll('[data-attribute-selected] li').length===3);assert((await page.locator('[data-attribute-selected] li').first().innerText()).includes('FUEGO'));
  const rev=(await get(650)).revision;assert((await post({deck_id:650,expected_revision:rev,attributes:['earth']})).ok());await page.locator('[data-attribute-promote="wind"]').click();
  const conflict=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('api=deck_attributes'));await page.locator('[data-attribute-save]').click();assert.equal((await conflict).status(),409);await page.waitForFunction(()=>document.querySelector('[data-attribute-editor-message]').textContent.includes('Otra edición'));assert((await page.locator('[data-attribute-selected] li').first().innerText()).includes('VIENTO'));
  await page.locator('[data-attribute-back]').first().click();await page.locator('[data-screen="mundo_decks"] [data-attribute-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-world-message]').textContent.includes('Solo decks TCG.'));
  await open('dark');await page.locator('[data-world-candidate-search]').fill('sin cartas');await page.locator('[data-world-candidate]').selectOption('900');await mutate(()=>page.locator('[data-world-add]').click());assert.equal((await get(900)).primary,'dark');
  await page.locator('[data-world-member-search]').fill('Equipo de prueba');assert((await page.locator('[data-world-members]').innerText()).includes('TCG sin cartas'));
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));await page.screenshot({path:path.join(out,`members-${width}.png`),fullPage:true});
   await page.locator('[data-world-back]').click();assert.equal(await page.locator('[data-world-rows] tr').count(),6);const button=await page.locator('[data-attribute-new]').boundingBox();assert(button.width<220&&button.height<65,`Oversized action at ${width}`);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));await page.screenshot({path:path.join(out,`worlds-${width}.png`),fullPage:true});
   await page.locator('[data-world-open="dark"]').click();
  }
  assert(await page.locator('[data-world-remove="900"]').isDisabled());assert.equal((await post({action:'remove',deck_id:900,expected_revision:(await get(900)).revision})).status(),422);assert.equal((await post({action:'save',deck_id:900,expected_revision:(await get(900)).revision,attributes:[]})).status(),422);assert.deepEqual((await get(900)).attributes,['dark']);
  const history=await(await ctx.request.get(origin+'/?api=deck_attributes&deck_id=900')).json();assert.equal(history.history.length,1);
  const readonly=await(await ctx.request.get(origin+'/?api=deck_attributes&public_live=1')).json();assert.equal(readonly.can_edit,false);assert.equal(readonly.token,null);
  await page.goto(origin+'/?attribute_deck=650#atributos_deck_editar',{waitUntil:'networkidle'});assert.equal(new URL(page.url()).hash,'#mundos_deck_editar');await page.waitForFunction(()=>document.querySelector('[data-attribute-deck]').value==='650');
  assert.deepEqual(errors,[]);const report={ok:true,worlds:6,tcgOnly:true,minimumOne:true,maxThree:true,secondaryMembership:true,inlineAddRemove:true,primary:true,editorSaveReload:true,conflictRetainsDraft:true,csrf:true,history:true,deckWithoutCards:true,legacyLinks:true,desktop:true,mobile:true,productionWrites:0,errors};fs.writeFileSync(path.join(out,'local-verification.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
