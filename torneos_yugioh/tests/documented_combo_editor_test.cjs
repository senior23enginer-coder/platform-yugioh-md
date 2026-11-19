const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict'),{spawn}=require('node:child_process');
const Import=require('../assets/modules/combo-creator/documented-import.js');
const app=path.resolve(__dirname,'..'),out=path.resolve(__dirname,'../../outputs/editor-import-20261113'),policy=require('../assets/editorial/strategy-content-state.json');
(async()=>{
 fs.mkdirSync(out,{recursive:true});const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const origin=`http://127.0.0.1:${port}`,db=path.join(out,`editor-${Date.now()}.sqlite`),server=spawn(process.env.PHP_BINARY||'php',['-S',`127.0.0.1:${port}`,'-t',app,path.join(__dirname,'combo_creator_router.php')],{env:{...process.env,COMBO_TEST_DB:db},stdio:['ignore','ignore','pipe']});let browser;
 try{
  for(let i=0;i<60;i++){try{if((await fetch(origin)).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({headless:true});const context=await browser.newContext({viewport:{width:1700,height:1000}}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
  await context.route('**/*',r=>r.request().url().startsWith(origin)?r.continue():r.abort());
  const load=async()=>{await page.goto(origin,{waitUntil:'networkidle'});await page.addScriptTag({path:path.join(app,'assets/modules/combo-creator/step-lesson.js')});await page.addStyleTag({path:path.join(app,'assets/modules/combo-creator/step-lesson.css')});await page.addScriptTag({path:path.join(app,'assets/modules/combo-creator/documented-import.js')});};await load();
  const availableDeckIds=new Set(require('./fixtures/calculator_inventory.json').allDecks.map(d=>String(d.id)));
  const supportedStudies=Object.entries(policy.checked_studies).filter(([id])=>availableDeckIds.has(id));
  assert(supportedStudies.length>0,'Fixture must provide decks with documented studies');
  let total=0;
  for(const [id,e] of supportedStudies){
   await page.locator('[data-cc-deck]').selectOption(id);await page.waitForFunction(n=>document.querySelectorAll('[data-cc-saved] optgroup option').length===n,e.routes);assert.equal(await page.locator('[data-cc-import-report]').count(),0);total+=e.routes;
  }
  assert.equal(total,supportedStudies.reduce((n,[,e])=>n+e.routes,0));
  const routeId='665:A09:FENRIR:BIRTH:SHANGRI05',comboId=Import.stableId(665,routeId);
  await page.locator('[data-cc-deck]').selectOption('665');await page.locator(`[data-cc-saved] option[value="${comboId}"]`).waitFor({state:'attached'});await page.locator('[data-cc-saved]').selectOption(comboId);
  assert((await page.locator('[data-cc-meta=title]').inputValue()).includes('Fenrir'));const originalNodes=await page.locator('.cc-node').count();assert(originalNodes>=8);assert.equal(await page.locator('.cc-node').first().locator('.cc-face img').count(),2,'Fenrir opening must keep its two documented card images');assert.equal(await page.locator('[data-cc-edge]').count(),originalNodes-1);
  await page.locator('[data-cc-meta=title]').fill('Mi versión editable de Fenrir');
  await page.locator('[data-cc-select]').first().click();const beforeCards=await page.locator('[data-cc-remove-card]').count();await page.locator('[data-cc-remove-card]').first().click();assert.equal(await page.locator('[data-cc-remove-card]').count(),beforeCards-1);await page.locator('[data-cc-palette] [data-cc-card]').first().click();await page.locator('[data-cc-group]').click();assert.equal(await page.locator('[data-cc-remove-card]').count(),beforeCards);
  await page.locator('[data-cc-prop=explanation]').fill('Mi explicación revisada de la apertura.');await page.locator('[data-cc-prop=explanation]').dispatchEvent('change');
  await page.locator('[data-cc-add=step]').click();await page.locator('[data-cc-prop=title]').fill('Paso que agregué');await page.locator('[data-cc-prop=title]').dispatchEvent('change');assert.equal(await page.locator('.cc-node').count(),originalNodes+1);
  await page.locator('[data-cc-remove-node]').click();assert.equal(await page.locator('.cc-node').count(),originalNodes);
  let response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('combo_creator'));await page.locator('[data-cc-save]').click();const saved=await(await response).json();assert(saved.ok,JSON.stringify(saved));assert.equal(saved.record.id,comboId);assert.equal(saved.record.revision,1);assert.equal(saved.record.document.nodes[0].cards.length,beforeCards);
  await load();await page.locator('[data-cc-deck]').selectOption('665');await page.locator(`[data-cc-saved] option[value="${comboId}"]`).waitFor({state:'attached'});await page.locator('[data-cc-saved]').selectOption(comboId);assert.equal(await page.locator('[data-cc-meta=title]').inputValue(),'Mi versión editable de Fenrir');assert.equal(await page.locator('[data-cc-meta=title]').inputValue(),saved.record.title);
  assert.equal(await page.locator(`[data-cc-saved] option[value="${comboId}"]`).count(),1,'No duplicate source after save');
  await page.locator('[data-cc-history]').click();await page.locator('[data-cc-history-panel]>details').first().waitFor();assert((await page.locator('[data-cc-history-panel]').innerText()).includes('1'));
  await page.evaluate(({sourceId})=>window.dispatchEvent(new CustomEvent('stationx:combo-edit',{detail:{deckId:665,sourceId}})),{sourceId:routeId});await page.waitForFunction(()=>document.querySelector('[data-cc-meta=title]').value==='Mi versión editable de Fenrir');
  await page.locator('[data-cc-view=guide]').click();assert(await page.locator('[data-cc-preview] .cc-guide').isVisible());
  assert(await page.locator('[data-cc-preview] .step-lesson').count()>1);assert((await page.locator('[data-cc-preview] .step-lesson').allTextContents()).some(t=>t.includes('Qué haces, en orden')));
  await page.locator('[data-cc-preview] .cc-guide-step:visible [data-cc-guide-follow]').first().click();
  assert((await page.locator('[data-cc-preview] .cc-guide-step:visible').innerText()).includes('Qué haces, en orden'));
  await page.locator('[data-cc-preview] .cc-guide-step:visible').screenshot({path:path.join(out,'clear-step.png')});
  for(const width of [1700,390]){await page.setViewportSize({width,height:1000});await page.screenshot({path:path.join(out,`native-guide-${width}.png`),fullPage:true});}
  const source=JSON.parse(fs.readFileSync(path.join(app,policy.checked_studies['665'].path)));assert.notEqual(source.routes.find(r=>r.id===routeId).title,saved.record.title);
  assert.deepEqual(errors,[]);console.log(JSON.stringify({ok:true,decks:supportedStudies.length,editableCombos:total,fixtureMissingDecks:Object.keys(policy.checked_studies).filter(id=>!availableDeckIds.has(id)),nativeSaveAndReopen:true,addRemoveCardsAndSteps:true,history:true,sourceUnchanged:true,productionWrites:0}));
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
