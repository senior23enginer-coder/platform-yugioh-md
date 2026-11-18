// Local integration: original images, two participants only, ephemeral duel state.
const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const app=path.resolve(__dirname,'..'),out=path.resolve(__dirname,'../../outputs/play-calculator');
const data=JSON.parse(fs.readFileSync(path.join(app,'tests/fixtures/calculator_inventory.json'),'utf8'));
data.allDecks.push({id:999,name:'Fuera del duelo',format_codes:['tcg_advanced']});
data.cards.push({id:999,deck_id:999,card_name:'Carta ajena de prueba',quantity:1,section:'main'});
const release=process.env.CALCULATOR_RELEASE_DIR?path.resolve(process.env.CALCULATOR_RELEASE_DIR):app;
const asset=name=>fs.existsSync(path.join(release,name))?path.join(release,name):path.join(app,name);
const source=fs.readFileSync(asset('assets/modules/play-calculator/ui.js'),'utf8');
const imageIndex=JSON.parse(fs.readFileSync(path.join(app,'assets/cards/originals/index.json'),'utf8'));
const markup=`<!doctype html><html lang="es"><meta charset="utf-8"><link rel="stylesheet" href="./assets/modules/play-calculator/styles.css?v=20261108-planner-152"><style>body{margin:0;background:#06101f;color:#e4effa;font-family:system-ui}main{max-width:1500px;margin:auto;padding:20px}.btn{padding:8px;border:1px solid #286080;border-radius:6px;background:#0b2438;color:#9be6ff;cursor:pointer}.btn:disabled{opacity:.4}input,select,textarea{background:#091d2e;color:#e4effa;border:1px solid #345369;border-radius:5px;padding:8px}label{display:grid;gap:5px}h4{margin:5px 0}button{cursor:pointer}</style><main><div data-play-calculator></div></main><script>window.DML_DATA=${JSON.stringify(data)};</script><script src="./assets/modules/play-calculator/engine.js?v=20261103-board-129"></script><script src="./assets/modules/play-calculator/storage.js?v=20261103-board-129"></script><script>window.YgoDeckDeliveries={load:async()=>null};</script><script src="./assets/modules/play-calculator/operations.js"></script><script src="./assets/modules/play-calculator/rules.js"></script><script src="./assets/modules/play-calculator/study.js"></script><script src="./assets/modules/play-calculator/planner.js?v=20261108-planner-152" ></script><script src="./assets/modules/play-calculator/hand-practice.js?v=20261113-hand-practice-170"></script><script src="./assets/modules/play-calculator/card-menu.js"></script><script src="./assets/modules/play-calculator/ui.js?v=20261108-planner-152"></script><script>window.mountDeck=id=>YgoPlayCalculator.mount(document.querySelector('[data-play-calculator]'),DML_DATA.allDecks.find(d=>d.id===id),DML_DATA);mountDeck(650);navigator.serviceWorker?.register('./service-worker.js');</script></html>`;
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://localhost');
 if(url.pathname==='/'){res.writeHead(200,{'Content-Type':'text/html;charset=utf-8'});return res.end(markup);}
 const relative=decodeURIComponent(url.pathname).replace(/^\//,'');
 const file=asset(relative);
 if(![app,release].some(base=>file.startsWith(base+path.sep))||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);return res.end();}
 const mime={'.js':'application/javascript','.css':'text/css','.json':'application/json','.html':'text/html','.jpg':'image/jpeg','.png':'image/png'};
 res.writeHead(200,{'Content-Type':mime[path.extname(file)]||'application/octet-stream','Date':new Date().toUTCString()});fs.createReadStream(file).pipe(res);
});
(async()=>{
 fs.mkdirSync(out,{recursive:true});await new Promise(r=>server.listen(0,'127.0.0.1',r));
 const browser=await chromium.launch({headless:true,...(process.env.CALCULATOR_CHROMIUM_PATH?{executablePath:process.env.CALCULATOR_CHROMIUM_PATH}:{})}),context=await browser.newContext({viewport:{width:1600,height:1080}}),page=await context.newPage(),errors=[],external=[];
 const origin=`http://127.0.0.1:${server.address().port}`;
 page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
 await page.route('**/*',r=>{if(!r.request().url().startsWith(origin)){external.push(r.request().url());return r.abort();}return r.continue();});
 try{
  await page.goto(origin,{waitUntil:'networkidle'});
  if(await page.locator('[data-play-mode="duel"][aria-pressed="false"]').count())await page.locator('[data-play-mode="duel"]').click();await page.locator('[data-play-opponent]').selectOption('656');
  await page.waitForFunction(()=>[...document.querySelectorAll('.play-original-image')].every(i=>i.complete&&i.naturalWidth>0));
  assert.equal(await page.locator('[data-play-save],[data-play-export],[data-play-import]').count(),0);
  for(const term of ['Guardar en este navegador','Exportar escenario','Importar escenario'])assert(!source.includes(term));
  const allowed=new Set(data.cards.filter(r=>[650,656].includes(r.deck_id)&&['main','extra'].includes(r.section)).map(r=>`${r.deck_id}:${r.id}`));
  async function validateCatalog(){for(const k of await page.locator('.play-catalog [data-play-card]').evaluateAll(nodes=>nodes.map(n=>n.dataset.playCard)))assert(allowed.has(k),'Foreign deck card: '+k);}
  await validateCatalog();
  await page.locator('[data-play-search]').fill('Carta ajena de prueba');assert.equal(await page.locator('.play-catalog [data-play-card]').count(),0);
  await page.locator('[data-play-search]').fill('');await page.locator('[data-play-pool]').selectOption('opponent');await validateCatalog();
  assert((await page.locator('.play-catalog [data-play-card]').evaluateAll(n=>n.map(x=>x.dataset.playOwner))).every(x=>x==='opponent'));
  await page.locator('[data-play-pool]').selectOption('self');await page.locator('[data-play-section]').selectOption('main');
  const card=page.locator('.play-catalog [data-play-card]').first(),key=await card.getAttribute('data-play-card');
  const image=await card.locator('img').getAttribute('src');assert.equal(image,imageIndex.cards[key]);
  const field=page.locator('[data-play-zone="m1"][data-play-side="self"]');
  await field.scrollIntoViewIfNeeded();await page.locator(`[data-play-card="${key}"]`).first().dragTo(field);
  await field.locator('[data-play-token]').waitFor();assert.equal(await field.locator('img').getAttribute('src'),image);
  await field.locator('[data-play-token]').click();await page.locator('[data-play-position]').selectOption('defense');assert.equal(await field.locator('.is-defense').count(),1);
  await field.locator('[data-play-token]').click();
  await page.locator('[data-play-quick-position="set"]').click();
  assert.equal(await field.locator('.is-set.is-defense .play-card-back').count(),1);
  assert.equal(await field.locator('img').count(),0);
  await page.locator('[data-play-quick-position="attack"]').click();
  assert.equal(await field.locator('.is-defense,.is-set').count(),0);
  assert.equal(await field.locator('img').getAttribute('src'),image);
  await page.locator('[data-play-quick-position="defense"]').click();
  assert.equal(await field.locator('.is-defense').count(),1);
  await page.locator('[data-play-analyze]').click();
  assert.equal(await page.locator('.play-analysis-summary').count(),1);
  assert.equal(await field.locator('.is-defense').count(),1,'Analysis must not execute or reposition cards');
  await page.locator('.play-catalog [data-play-card]').first().click();await page.locator('[data-play-place="graveyard"][data-play-side="self"]').click();
  await page.locator('[data-play-unknown-card]').click();await page.locator('[data-play-place="s1"][data-play-side="opponent"]').click();
  assert.equal(await page.locator('[data-play-zone="s1"][data-play-side="opponent"] .play-card-back').count(),1);
  await page.waitForFunction(()=>[...document.querySelectorAll('.play-catalog img')].every(i=>i.complete&&i.naturalWidth>0));
  await page.screenshot({path:path.join(out,'calculator-desktop.png'),fullPage:true});
  await page.locator('.play-context summary').click();await page.locator('[data-play-setting="turn"]').fill('3');await page.locator('[data-play-setting="turn"]').dispatchEvent('change');
  assert.equal(await page.locator('[data-play-setting="turn"]').inputValue(),'3');
  assert.equal(await page.evaluate(()=>Object.keys(localStorage).filter(k=>/playcalc.*(?:scenario|last:)/.test(k)).length),0);
  // Exercise the real worker, with no prewritten route returned by the stub.
  await page.evaluate(()=>mountDeck(665));if(await page.locator('[data-play-mode="duel"][aria-pressed="false"]').count())await page.locator('[data-play-mode="duel"]').click();await page.locator('[data-play-opponent]').selectOption('656');
  const facts=JSON.parse(fs.readFileSync(path.join(app,'assets/modules/play-calculator/card-facts.json'),'utf8'));
  for(const cid of [17768,18203]){
    const row=data.cards.find(c=>Number(c.deck_id)===665&&facts.bindings[`665:${c.id}`]===cid&&c.section==='main');assert(row);
    await page.locator('[data-play-search]').fill(row.card_name);
    await page.locator(`.play-catalog [data-play-card="665:${row.id}"]`).click();
    await page.locator('[data-play-place="hand"][data-play-side="self"]').click();
  }
  // A known response must be in the captured hand, not inferred from the rival list.
  const ash=data.cards.find(c=>Number(c.deck_id)===656&&facts.bindings[`656:${c.id}`]===12950);assert(ash);
  await page.locator('[data-play-pool]').selectOption('opponent');
  await page.locator('[data-play-search]').fill(ash.card_name);
  await page.locator(`.play-catalog [data-play-card="656:${ash.id}"]`).click();
  await page.locator('[data-play-place="hand"][data-play-side="opponent"]').click();
  await page.locator('[data-play-analyze]').click();
  await page.locator('.play-computed-plan').first().waitFor({timeout:20000});
  await page.getByRole('heading',{name:'Qué se analizó y qué falta',exact:true}).waitFor();
  assert((await page.locator('.play-scenario-audit').innerText()).includes(ash.card_name));
  assert(await page.locator('.play-response-branches article').count()>0,'Known Ash branches must be rendered');
  assert((await page.locator('.play-response-branches').allTextContents()).some(t=>t.includes('CL2')));

  assert((await page.locator('.play-computed-plan').allTextContents()).some(t=>t.includes('Xyz')));
  assert.equal(await page.locator('[data-play-zone="hand"][data-play-side="self"] [data-play-token]').count(),2,'Worker must leave capture unchanged');
  await page.locator('.play-computed-plan > details > summary').first().click();
  await page.locator('.play-computed-plan').first().screenshot({path:path.join(out,'planner-desktop.png')});
  await page.setViewportSize({width:412,height:915});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
  await page.screenshot({path:path.join(out,'planner-mobile.png'),fullPage:true});
  await page.setViewportSize({width:1500,height:1000});
  // New interaction: free practice, physical random opening and a real worker.
  await page.locator('[data-play-mode="practice"]').click();
  assert.equal(await page.locator('.play-half.is-opponent,[data-play-unknown-card]').count(),0);
  assert.equal(await page.locator('[data-play-pool] option').count(),1);
  await page.locator('[data-play-random-hand]').click();
  assert.equal(await page.locator('.play-opening-cards [data-play-token]').count(),5);
  assert.equal(await page.locator('[data-play-zone="hand"][data-play-side="self"] [data-play-token]').count(),5);
  const initial=await page.locator('.play-opening-cards [data-play-token]').evaluateAll(nodes=>nodes.map(n=>n.dataset.playCard));
  const counts=initial.reduce((m,k)=>(m[k]=(m[k]||0)+1,m),{});
  for(const [key,count]of Object.entries(counts))assert(count<=data.cards.find(c=>`665:${c.id}`===key&&c.deck_id===665).quantity);
  await page.locator('[data-play-draw-card]').click();assert.equal(await page.locator('.play-opening-cards [data-play-token]').count(),6);
  await page.locator('[data-play-undo]').click();assert.equal(await page.locator('.play-opening-cards [data-play-token]').count(),5);
  // Seed only the test RNG, keeping the same physical-copy draw implementation.
  await page.evaluate(async()=>{
    const facts=await(await fetch('./assets/modules/play-calculator/card-facts.json')).json();
    const original=YgoHandPractice.opening;
    YgoHandPractice.opening=s=>{
      const pool=YgoHandPractice.remaining(YgoPlayEngine.create(s.self));let n=0;
      return original(s,()=>{const cid=[17768,18203][n++];let index=pool.findIndex(k=>facts.bindings[k]===cid);if(index<0)index=0;const value=(index+.1)/pool.length;pool.splice(index,1);return value;});
    };
  });
  await page.locator('[data-play-random-hand]').click();
  await page.locator('[data-play-explore-hand]').click();
  await page.locator('.play-computed-plan').first().waitFor({timeout:20000});
  assert((await page.locator('.play-computed-plan').allTextContents()).some(t=>t.includes('Xyz')));
  assert.equal(await page.locator('.play-opening-cards [data-play-token]').count(),5,'Calculation does not consume the sampled hand');
  await page.locator('.play-hand-practice').scrollIntoViewIfNeeded();
  await page.screenshot({path:path.join(out,'practice-free-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
  await page.screenshot({path:path.join(out,'practice-free-mobile.png'),fullPage:true});
  await page.setViewportSize({width:1500,height:1000});
  await page.evaluate(()=>mountDeck(705));await page.locator('[data-play-opponent]').selectOption('706');await page.locator('[data-play-mode="practice"]').click();
  await page.locator('[data-play-random-hand]').click();assert.equal(await page.locator('.play-opening-cards [data-play-token]').count(),4);
  assert.equal(await page.locator('[data-play-setting="phase"]').inputValue(),'draw');
  assert.equal(await page.locator('[data-play-zone="m4"]').count(),0);assert.equal(await page.locator('[data-play-zone^="emz"]').count(),0);
  await page.locator('[data-play-pool]').selectOption('self');await page.locator('[data-play-section]').selectOption('main');
  await page.waitForFunction(()=>[...document.querySelectorAll('.play-catalog .play-original-image')].every(i=>i.complete&&i.naturalWidth>0));
  assert(await page.locator('.play-catalog .play-original-image').count()>0);
  await page.evaluate(()=>mountDeck(650));if(await page.locator('[data-play-mode="duel"][aria-pressed="false"]').count())await page.locator('[data-play-mode="duel"]').click();await page.locator('[data-play-opponent]').selectOption('656');assert.equal(await page.locator('[data-play-token]').count(),0);
  await page.setViewportSize({width:412,height:915});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
  await page.screenshot({path:path.join(out,'calculator-mobile.png'),fullPage:true});
  await page.evaluate(async()=>{await navigator.serviceWorker.ready;if(!navigator.serviceWorker.controller)await new Promise(r=>navigator.serviceWorker.addEventListener('controllerchange',r,{once:true}));});
  await page.goto(origin+'/play-calculator.html?deck=650',{waitUntil:'networkidle'});await context.setOffline(true);await page.reload({waitUntil:'domcontentloaded'});
  if(await page.locator('[data-play-mode="duel"][aria-pressed="false"]').count())await page.locator('[data-play-mode="duel"]').click();await page.locator('[data-play-opponent]').selectOption('656');assert.equal(await page.locator('[data-play-token]').count(),0);
  await page.locator('[data-play-analyze]').click();
  await page.getByRole('heading',{name:'Secuencias calculadas desde tu estado',exact:true}).waitFor({timeout:20000});
  await page.locator('[data-play-search]').fill('Agujero');assert.equal(await page.locator('[data-play-save],[data-play-import],[data-play-export]').count(),0);
  await page.locator('[data-play-mode="practice"]').click();await page.locator('[data-play-random-hand]').click();
  assert.equal(await page.locator('.play-opening-cards [data-play-token]').count(),5,'Random opening works offline without opponent: '+await page.locator('[data-play-message]').innerText()+' module='+await page.evaluate(()=>typeof YgoHandPractice));
  assert.equal(errors.length,0,errors.join('\n'));assert.equal(external.length,0);
  const result={ok:true,originalImages:true,rushOriginalImages:true,twoDecksOnly:true,freePractice:true,randomOpening:true,randomDrawAndUndo:true,freeHandPlanner:true,offlineRandomHand:true,dragDrop:true,noScenarioPersistence:true,noImportExport:true,mobile:true,offline:true,errors,externalRequests:external};
  fs.writeFileSync(path.join(out,'ui-result.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
