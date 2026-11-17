const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),app=path.join(root,'torneos_yugioh'),out=path.join(root,'outputs/endboards-20261113');
const policy=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/strategy-content-state.json')));
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext({serviceWorkers:'block'}),page=await context.newPage(),errors=[],report=[];
  page.on('pageerror',e=>errors.push(e.message));
  await context.route('**/*',r=>{
   const u=new URL(r.request().url());if(r.request().method()!=='GET'||u.hostname!=='stationx.test')return r.abort();
   const file=path.resolve(app,'.'+u.pathname.replace(/^\/yugioh/,''));
   return file.startsWith(app+path.sep)&&fs.existsSync(file)?r.fulfill({path:file}):r.fulfill({status:404,body:'Missing asset'});
  });
  await page.setContent('<base href="https://stationx.test/yugioh/"><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/modules/deck-guide/checked-study.css"><link rel="stylesheet" href="assets/modules/deck-guide/curated-study.css"><link rel="stylesheet" href="assets/modules/deck-guide/curated-visual.css"><link rel="stylesheet" href="assets/modules/deck-guide/endboard.css"><main></main>');
  for(const name of ['photo-viewer','functional-role','endboard','curated-visual','curated-study','checked-study'])await page.addScriptTag({path:path.join(app,`assets/modules/deck-guide/${name}.js`)});
  for(const [id,entry] of Object.entries(policy.checked_studies)){
   const d=JSON.parse(fs.readFileSync(path.join(app,entry.path)));
   for(const width of [1440,390]){
    await page.setViewportSize({width,height:1000});
    await page.evaluate(async({id,entry})=>{document.querySelector('main').innerHTML='';await YgoCheckedStudy.render(document.querySelector('main'),{id:Number(id)},entry,()=>true);},{id,entry});
    assert.equal(await page.locator('[role=tab]').count(),5);
    await page.locator('[data-curated-tab=cards]').click();assert.equal(await page.locator('.curated-catalog [data-curated-card]').count(),d.cards.length);
    assert(!(await page.locator('.curated-catalog').innerText()).includes('Sin sinergia demostrada'));
    if(id==='665'){
     await page.locator('[data-curated-function]').selectOption('Staple · respuesta situacional');assert.equal(await page.locator('.curated-catalog-card').count(),5);
     await page.locator('[data-curated-function]').selectOption('Recurso del Extra para Pot');assert.equal(await page.locator('.curated-catalog-card').count(),3);
     await page.locator('.curated-catalog-card').filter({hasText:'Fossil Warrior'}).click();assert((await page.locator('[data-card-modal-body]').innerText()).includes('destierra boca abajo'));await page.locator('[data-card-close]').click();
     await page.screenshot({path:path.join(out,`functional-${id}-${width}.png`),fullPage:true});
     await page.locator('[data-curated-function]').selectOption('');
    }
    await page.locator('.curated-catalog [data-curated-card]').first().click();assert(await page.locator('.curated-card-modal').evaluate(n=>n.open));
    await page.locator('.curated-card-modal [data-curated-photo]').click();await page.locator('.study-photo-stage img').evaluate(i=>i.decode());
    await page.keyboard.press('Escape');assert(await page.locator('.curated-card-modal').evaluate(n=>n.open));await page.locator('[data-card-close]').click();
    await page.locator('[data-curated-tab=routes]').click();
    assert.equal(await page.locator('[data-route-library] [data-open-route]').count(),d.routes.length);
    const current=d.routes;
    assert.equal(await page.locator('.curated-route-card:visible').count(),current.length);
    assert(!/ALERTA REGLAMENTARIA|Control \(según|No mostrarla como|CID \d+|\(17768\)/.test(await page.locator('[data-route-library]').innerText()));
    assert.equal(await page.locator('.curated-route-card:visible .eb-preview').count(),current.length);
    for(const r of current){const card=page.locator(`[data-route-library] [data-open-route="${r.id}"]`);assert.equal(await card.locator('strong').first().textContent(),r.title);assert((await card.locator('.curated-route-purpose').textContent()).includes(r.purpose));assert.equal(await card.locator('.curated-route-thumbs').count(),0);}
    assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)),`Library overflow ${id}:${width}`);
    if(id==='665')await page.screenshot({path:path.join(out,`library-${id}-${width}.png`),fullPage:true});
    for(const r of d.routes){
     await page.locator(`[data-route-library] [data-open-route="${r.id}"]`).click();
     assert.equal(await page.locator('[data-visual-step]').count(),r.steps.length);
     assert(await page.locator('.cv-final').isVisible());assert(await page.locator('[data-endboard-root]').isVisible());assert.equal(await page.locator('[data-endboard-case]:visible [data-endboard-zone]').count(),5);const choose=page.locator('[data-endboard-variant]');if(await choose.count()){await choose.selectOption('1');assert.equal(await page.locator('[data-endboard-case="1"]:visible').count(),1);await choose.selectOption('0');}
     assert.equal(await page.locator('.cv-sheet .cv-historical').count(),r.historical?1:0);
     assert.equal(await page.locator('.cv-sheet [data-route-next]').count(),0);
     assert(!/ALERTA REGLAMENTARIA|No mostrarla como|\bCID \d+/.test(await page.locator('.cv-sheet').innerText()));
     if(r.id==='665:A09:FENRIR:BIRTH:SHANGRI05'){
      assert.equal(await page.locator('.cv-opening>.curated-photos figure').count(),2);
      assert.equal(await page.locator('[data-endboard-case="0"] .eb-zone>.eb-item').count(),4);
      assert.equal(await page.locator('[data-endboard-case="0"] .eb-materials button').count(),2);
     }
     await page.locator('[data-route-back]').click();
    }
    await page.locator('[data-curated-tab=plans]').click();
    assert.equal(await page.locator('.cv-opponents article').count(),d.opponent_scenarios.length);
    assert.equal(await page.locator('.curated-motor').count(),0);
    await page.locator('.cv-opponents [data-open-route]').first().click();assert(await page.locator('.cv-sheet').isVisible());
    await page.locator('[data-curated-tab=practice]').click();
    assert(await page.locator('.cv-improvement').isVisible());assert.equal(await page.locator('[data-practice]:visible').count(),0);
    assert(d.optimization.proposals.length);await page.locator('.cv-improvement .curated-optimization>summary').click();
    assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)),`Overflow ${id}:${width}`);
    await page.locator('.cv-exercises>summary').click();assert.equal(await page.locator('[data-practice]:visible').count(),3);
    await page.locator('[data-practice]').first().click();assert.equal(await page.locator('[data-practice-answer]:visible').count(),1);
    await page.locator('[data-curated-tab=routes]').click();await page.locator('[data-route-back]').click();
    const route=id==='665'?d.routes.find(r=>r.id==='665:A09:FENRIR:BIRTH:SHANGRI05'):current[0];
    await page.locator(`[data-route-library] [data-open-route="${route.id}"]`).click();
    await page.locator('.cv-sheet img:visible').evaluateAll(async images=>{images.forEach(i=>i.loading='eager');await Promise.all(images.map(i=>i.decode()));});
    if(['650','665','704'].includes(id))await page.screenshot({path:path.join(out,`visual-${id}-${width}.png`),fullPage:true,timeout:30000});
    assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)),`Lesson overflow ${id}:${width}`);
   }
   report.push({id:Number(id),cards:d.cards.length,routes:d.routes.length,historical:d.routes.filter(r=>r.historical).length});
  }
  assert.equal(report.reduce((n,r)=>n+r.routes,0),108);assert.equal(report.reduce((n,r)=>n+r.historical,0),14);assert.deepEqual(errors,[]);
  const result={ok:true,decks:report,productionWrites:0};fs.writeFileSync(path.join(out,'tests.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
