const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net'),{spawn}=require('node:child_process');
const M=require('../assets/modules/combo-creator/model.js');
const app=path.resolve(__dirname,'..'),out=path.resolve(__dirname,'../../outputs/combo-creator-20261103');
const data=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/calculator_inventory.json'),'utf8'));
const php=process.env.PHP_BINARY||'php';
let doc=M.addNode(M.create(665),'opening');doc=M.addNode(doc,'step');doc=M.connect(doc,doc.nodes[0].id,doc.nodes[1].id);
assert.equal(M.ordered(doc).length,2);assert.throws(()=>M.connect(doc,doc.nodes[1].id,doc.nodes[0].id));
assert.throws(()=>M.connect(doc,doc.nodes[0].id,doc.nodes[1].id));assert.equal(M.removeNode(doc,doc.nodes[0].id).edges.length,0);
assert.throws(()=>M.addCard(doc,doc.nodes[0].id,123,[{id:123,section:'extra',quantity:1}]));
(async()=>{
 fs.mkdirSync(out,{recursive:true});const db=path.join(out,`browser-${Date.now()}.sqlite`);
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const origin=`http://127.0.0.1:${port}`,server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',app,path.join(__dirname,'combo_creator_router.php')],{env:{...process.env,COMBO_TEST_DB:db},stdio:['ignore','ignore','pipe']});
 let logs='';server.stderr.on('data',b=>logs+=b);let browser;
 try{
  for(let i=0;i<60;i++){try{if((await fetch(origin)).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({headless:true});const context=await browser.newContext({viewport:{width:1700,height:1100}}),page=await context.newPage(),errors=[],external=[];
  page.on('pageerror',e=>errors.push(e.message));await context.route('**/*',r=>{if(!r.request().url().startsWith(origin)){external.push(r.request().url());return r.abort();}return r.continue();});
  await page.goto(origin,{waitUntil:'networkidle'});await page.locator('[data-cc-deck]').selectOption('665');await page.locator('[data-cc-work]:visible').waitFor();
  await page.locator('.cc-details summary').click();await page.locator('[data-cc-meta="title"]').fill('Unicorn: apertura y continuación');await page.locator('[data-cc-meta="objective"]').fill('Explicar acceso, coste y campo final condicionado.');
  await page.locator('[data-cc-add="opening"]').click();await page.locator('[data-cc-section]').selectOption('main');
  const palette=page.locator('[data-cc-palette] [data-cc-card]');const cardId=await palette.first().getAttribute('data-cc-card');
  await palette.first().dragTo(page.locator('.cc-node-edit').first());assert.equal(await page.locator('.cc-node').first().locator('img').count(),1);
  assert((await page.locator('.cc-node img').first().getAttribute('src')).includes('assets/cards/originals/'));
  await palette.nth(1).click();await page.locator('[data-cc-insert-card]').click();assert.equal(await page.locator('.cc-node').count(),2);
  let ids=await page.locator('.cc-node').evaluateAll(nodes=>nodes.map(n=>n.dataset.ccNode));
  await page.locator(`[data-cc-source="${ids[0]}"]`).click();await page.locator(`[data-cc-target="${ids[1]}"]`).click();assert.equal(await page.locator('[data-cc-edge]').count(),1);
  await page.locator(`[data-cc-select="${ids[1]}"]`).click();await page.locator('[data-cc-prop="action"]').fill('Activar y buscar');await page.locator('[data-cc-prop="action"]').dispatchEvent('change');
  await page.locator('[data-cc-prop="explanation"]').fill('Paga el coste indicado y comprueba la restricción antes de continuar.');await page.locator('[data-cc-prop="explanation"]').dispatchEvent('change');
  await page.locator('[data-cc-add="result"]').click();await page.locator('[data-cc-group]').click();
  ids=await page.locator('.cc-node').evaluateAll(nodes=>nodes.map(n=>n.dataset.ccNode));await page.locator(`[data-cc-source="${ids[1]}"]`).click();await page.locator(`[data-cc-target="${ids[2]}"]`).click();
  await page.locator('[data-cc-edge]').first().locator('text').click();await page.locator('[data-cc-edge-label]').fill('Si resuelve la busqueda');await page.locator('[data-cc-edge-label]').dispatchEvent('change');
  // Move a node using the actual pointer handle, preserving coordinates in saved JSON.
  const handle=page.locator(`[data-cc-drag="${ids[0]}"]`);await handle.scrollIntoViewIfNeeded();const bounds=await handle.boundingBox();
  await page.mouse.move(bounds.x+30,bounds.y+15);await page.mouse.down();await page.mouse.move(bounds.x+50,bounds.y+45,{steps:5});await page.mouse.up();
  let response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('combo_creator'));await page.locator('[data-cc-save]').click();let saved=await(await response).json();assert(saved.ok,JSON.stringify(saved));const comboId=saved.record.id;assert.equal(saved.record.revision,1);assert.equal(saved.record.document.edges[0].label,'Si resuelve la busqueda');assert.equal(saved.record.document.nodes.length,3);assert(saved.record.document.nodes[0].y>40);
  await page.locator('[data-cc-view="guide"]').click();assert.equal(await page.locator('[data-cc-preview] .cc-guide-step').count(),3);
  assert((await page.locator('[data-cc-preview]').textContent()).includes('coste indicado'));
  await page.waitForFunction(()=>[...document.querySelectorAll('.combo-maker img')].every(i=>i.complete));await page.screenshot({path:path.join(out,'guide-desktop.png'),fullPage:true});
  await page.locator('[data-cc-view="canvas"]').click();await page.locator('[data-cc-new]').click();await page.locator('[data-cc-meta="title"]').fill('Plan alternativo ante una interrupción');
  await page.locator('[data-cc-meta="kind"]').selectOption('strategy');await page.locator('[data-cc-add="decision"]').click();
  response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('combo_creator'));await page.locator('[data-cc-save]').click();saved=await(await response).json();assert(saved.ok);assert.equal(saved.record.document.kind,'strategy');
  await page.locator('[data-cc-view="matrix"]').click();assert.equal(await page.locator('.cc-matrix tbody tr').count(),2);await page.waitForFunction(()=>[...document.querySelectorAll('.combo-maker img')].every(i=>i.complete));await page.screenshot({path:path.join(out,'matrix-desktop.png'),fullPage:true});
  await page.reload({waitUntil:'networkidle'});await page.locator('[data-cc-deck]').selectOption('665');await page.locator('[data-cc-work]:visible').waitFor();assert.equal(await page.locator('[data-cc-saved] option').count(),3);
  await page.locator('[data-cc-saved]').selectOption(comboId);assert.equal(await page.locator('[data-cc-meta="title"]').inputValue(),'Unicorn: apertura y continuación');assert.equal(await page.locator('.cc-node').count(),3);
  await page.locator('.cc-details summary').click();await page.locator('[data-cc-meta="objective"]').fill('Objetivo corregido por su autor.');await page.locator('[data-cc-add="step"]').click();await page.locator('[data-cc-meta="title"]').fill('Titulo que se conserva al deshacer');await page.locator('[data-cc-undo]').click();response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('combo_creator'));await page.locator('[data-cc-save]').click();saved=await(await response).json();assert.equal(saved.record.revision,2);assert.equal(saved.record.title,'Titulo que se conserva al deshacer');assert.equal(saved.record.document.nodes.length,3);
  const session=await(await context.request.get(origin+'/?api=combo_creator&deck_id=665')).json();
  const bad={deck_id:665,revision:2,inventory_sha256:session.inventory_sha256,document:saved.record.document};
  let r=await context.request.post(origin+'/?api=combo_creator&deck_id=665',{data:bad,headers:{'X-CSRF-Token':'wrong'}});assert.equal(r.status(),403);
  bad.document=M.clone(bad.document);bad.document.nodes[0].cards=[data.cards.find(c=>Number(c.deck_id)!==665).id];
  r=await context.request.post(origin+'/?api=combo_creator&deck_id=665',{data:bad,headers:{'X-CSRF-Token':session.token}});assert.equal(r.status(),422);
  const conflict={...bad,revision:1,document:{...saved.record.document,title:'Conflicto desde otra pestaña'}};
  r=await context.request.post(origin+'/?api=combo_creator&deck_id=665',{data:conflict,headers:{'X-CSRF-Token':session.token}});assert.equal(r.status(),409);
  await page.evaluate(()=>YgoComboCreator.mountViewer(document.querySelector('[data-deck-created-combos]'),DML_DATA.allDecks.find(d=>d.id===665)));
  await page.locator('[data-cc-read]').first().waitFor();assert.equal(await page.locator('[data-cc-read]').count(),2);await page.locator(`[data-cc-read="${comboId}"]`).click();assert.equal(await page.locator('[data-cc-reading] .cc-guide-step').count(),3);
  await page.locator('[data-cc-deck]').selectOption('650');await page.waitForFunction(()=>document.querySelector('[data-cc-saved]').disabled===false&&document.querySelector('[data-cc-saved]').options.length===1);
  const allowed=new Set(data.cards.filter(c=>Number(c.deck_id)===650).map(c=>String(c.id)));for(const id of await page.locator('[data-cc-card]').evaluateAll(n=>n.map(x=>x.dataset.ccCard)))assert(allowed.has(id));
  await page.locator('[data-cc-deck]').selectOption('665');await page.locator('[data-cc-saved] option[value="'+comboId+'"]').waitFor({state:'attached'});await page.locator('[data-cc-saved]').selectOption(comboId);
  await page.setViewportSize({width:412,height:915});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);await page.locator('[data-cc-view="guide"]').click();await page.waitForFunction(()=>[...document.querySelectorAll('.combo-maker img')].every(i=>i.complete));await page.screenshot({path:path.join(out,'guide-mobile.png'),fullPage:true});
  await page.locator('[data-cc-view="canvas"]').click();await page.setViewportSize({width:1700,height:1100});await page.waitForFunction(()=>[...document.querySelectorAll('.combo-maker img')].every(i=>i.complete));await page.screenshot({path:path.join(out,'editor-desktop.png'),fullPage:true});
  assert.equal(errors.length,0,errors.join('\n'));assert.equal(external.length,0,external.join('\n'));
  const result={ok:true,realPhpSqlitePersistence:true,dragDrop:true,nodeMovement:true,connections:true,stepGuide:true,comparisonMatrix:true,deckViewer:true,foreignCardsRejected:true,csrf:true,revisionConflict:true,mobile:true,externalRequests:external,pageErrors:errors};fs.writeFileSync(path.join(out,'browser-result.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }catch(e){console.error(logs.split('\n').filter(x=>/Fatal|Warning|Error/.test(x)).slice(-10).join('\n'));throw e;}
 finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
