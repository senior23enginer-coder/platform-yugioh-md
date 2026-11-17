const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const app=path.join(__dirname,'..'), state=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/strategy-content-state.json')));
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://fixture.test/**',route=>{
   const url=new URL(route.request().url());
   if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:'<button data-deck-category-open="strategy"><small>Perfil documentado</small></button><header class="deck-analysis-header">Other deck comparison</header><section class="deck-strategy-profile"><div class="old">Stale guide</div><details class="deck-strategy-editor">Editor</details></section>'});
   const file=path.join(app,decodeURIComponent(url.pathname));
   if(!file.startsWith(app)||!fs.existsSync(file))return route.fulfill({status:404,body:'Missing'});
   return route.fulfill({path:file});
  });
  await page.goto('https://fixture.test/');await page.addStyleTag({path:path.join(app,'assets/modules/deck-guide/checked-study.css')});
  await page.evaluate(()=>{window.calls=0;window.YgoDeckStrategy={render:()=>window.calls++};});
  await page.addStyleTag({path:path.join(app,'assets/modules/deck-guide/study-sections.css')});
  for(const name of ['study-eleven.js','study-sections.js','checked-study.js','content-state.js'])await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide',name)});
  let count=0;
  for(const [id,entry] of Object.entries(state.checked_studies)){
   await page.evaluate(id=>YgoDeckStrategy.render({id},null,{}),Number(id));
   await page.waitForFunction(id=>document.querySelector('[data-checked-study] h2')?.textContent.includes(id),JSON.parse(fs.readFileSync(path.join(app,entry.path))).name);
   const ten=entry.sections===10,allowed=ten?[2,4,5,6,7,8,9,10]:[5,...(entry.interactions?[8]:[]),...(entry.cases?[11]:[])];
   assert.equal(await page.locator('[data-study-tab]').count(),allowed.length);
   assert.equal(await page.locator('[data-study-tab="1"],[data-study-tab="3"]').count(),0);
   for(const n of allowed){await page.locator(`[data-study-tab="${n}"]`).click();assert(await page.locator(`[data-study-page="${n}"]`).isVisible());}
   if(ten){
    assert.equal(await page.locator('[data-study-tab="11"],[data-study-page="11"],[data-route-open="11"],[data-route-chains]').count(),0);
    const received=JSON.parse(fs.readFileSync(path.join(app,entry.path)));
    await page.locator('[data-study-tab="10"]').click();
    for(let i=0;i<received.routes.length;i++){
     await page.locator('[data-route-picker="visual"]').selectOption(String(i));
     assert.equal(await page.locator('[data-route-step-select] option').count(),received.routes[i].steps.length);
     await page.locator('[data-route-step-select]').selectOption(String(received.routes[i].steps.length-1));
     assert(await page.locator('[data-route-next]').isDisabled());
     await page.locator('[data-route-mode="board"]').click();assert(await page.locator('.study-state').isVisible());
     await page.locator('[data-route-mode="map"]').click();assert.equal(await page.locator('.study-action-map>li').count(),received.routes[i].steps.length);
     await page.locator('[data-route-mode="steps"]').click();
     assert.equal(await page.locator('[data-route-open="11"]').count(),0);
    }
   }else assert.equal(await page.locator('[data-route-picker],[data-route-step-select]').count(),0);
   await page.locator('[data-study-tab="5"]').click();
   assert.equal(await page.locator('.checked-card').count(),entry.cards);count+=entry.cards;
   assert.equal(await page.locator('.old').isVisible(),false);assert.equal(await page.locator('[data-deck-category-open="strategy"] small').textContent(),entry.sections===10?'Apartados 01\u201310':'Contenido con check individual');
   assert.equal(await page.locator('.deck-analysis-header').isVisible(),false);
   await page.locator('.checked-detail img').evaluate(i=>i.decode());
   assert(await page.locator('.checked-detail img').evaluate(i=>i.naturalWidth>0));
  }
  assert.equal(count,370);assert.equal(await page.evaluate(()=>calls),0);
  await page.evaluate(()=>YgoDeckStrategy.render({id:687},null,{}));await page.locator('h2').filter({hasText:'Volcanic'}).waitFor();
  await page.locator('[data-study-tab="10"]').click();
  await page.locator('[data-route-step-select]').selectOption('1');
  await page.evaluate(()=>YgoDeckStrategy.render({id:687},null,{}));
  await page.waitForFunction(()=>document.querySelector('.deck-strategy-profile').style.visibility!=='hidden');
  assert(await page.locator('[data-study-page="10"]').isVisible());
  assert.equal(await page.locator('[data-route-step-select]').inputValue(),'1');
  await page.locator('[data-study-tab="5"]').click();
  await page.locator('[data-checked-zone]').selectOption('reserve');
  const cardData=JSON.parse(fs.readFileSync(path.join(app,state.checked_studies['687'].path)));
  assert.equal(await page.locator('.checked-card').count(),cardData.cards.filter(c=>c.slots.some(s=>s.section==='reserve')).length);
  await page.locator('[data-checked-search]').fill('Clashing');assert.equal(await page.locator('.checked-card').count(),1);assert(await page.locator('.checked-detail').textContent().then(t=>t.includes('Solo en Reserva')));
  await page.locator('.checked-detail [data-photo]').click();assert(await page.locator('dialog').isVisible());await page.keyboard.press('Escape');assert.equal(await page.locator('dialog').isVisible(),false);
  await page.locator('[data-checked-search]').fill('no-such-card');assert.equal(await page.locator('.checked-card').count(),0);
  await page.setViewportSize({width:390,height:844});await page.locator('[data-checked-search]').fill('');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth));
  // Rapid navigation must not attach a late response to the next deck.
  await page.evaluate(()=>{YgoDeckStrategy.render({id:665},null,{});YgoDeckStrategy.render({id:687},null,{});});await page.locator('h2').filter({hasText:'Volcanic'}).waitFor();
  await page.evaluate(()=>YgoDeckStrategy.render({id:646},null,{}));await page.locator('[data-strategy-empty]:visible').waitFor();assert.equal(await page.locator('[data-checked-study]').isVisible(),false);
  assert.deepEqual(errors,[]);console.log(JSON.stringify({ok:true,decks:11,checkedCards:count,search:true,zoneSeparation:true,photoZoom:true,mobile:true,oldDeliveriesInactive:true,rapidSwitch:true}));
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
