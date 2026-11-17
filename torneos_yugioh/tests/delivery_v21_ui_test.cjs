const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const app=path.resolve(__dirname,'..'),out=path.resolve(__dirname,'../../outputs/import-v21'),policy=require('../assets/editorial/strategy-content-state.json');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage({serviceWorkers:'block'}),errors=[],report=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',r=>{const u=new URL(r.request().url());if(u.hostname!=='stationx.test')return r.abort();const file=path.resolve(app,'.'+u.pathname.replace(/^\/yugioh/,''));return file.startsWith(app+path.sep)&&fs.existsSync(file)?r.fulfill({path:file}):r.fulfill({status:404,body:'Missing asset'});});
  await page.setContent('<base href="https://stationx.test/yugioh/"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#071321;color:#eef7ff}main{max-width:1400px;margin:auto;padding:16px;box-sizing:border-box}</style>'+['checked-study','curated-study','curated-visual','endboard','delivery-extensions'].map(n=>`<link rel="stylesheet" href="assets/modules/deck-guide/${n}.css">`).join('')+'<main></main>');
  for(const n of ['photo-viewer','functional-role','card-detail','endboard','delivery-extensions','curated-visual','curated-study','checked-study'])await page.addScriptTag({path:path.join(app,`assets/modules/deck-guide/${n}.js`)});
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   for(const [id,entry]of Object.entries(policy.checked_studies)){
    const data=JSON.parse(fs.readFileSync(path.join(app,entry.path)));
    await page.evaluate(async({id,entry})=>{document.querySelector('main').innerHTML='';await YgoCheckedStudy.render(document.querySelector('main'),{id:Number(id)},entry,()=>true);},{id,entry});
    assert.equal(await page.locator('[role=tab]').count(),5);
    assert((await page.locator('[data-curated-content=intro]').innerText()).includes('Cómo decidir con este deck'));
    await page.locator('[data-curated-tab=cards]').click();assert.equal(await page.locator('.curated-catalog-card').count(),data.cards.length);
    await page.locator('.curated-catalog-card').first().click();assert(await page.locator('.curated-card-modal').evaluate(n=>n.open));assert.equal(await page.locator('.curated-card-modal .delivery-details').count(),1);await page.locator('[data-card-close]').click();
    if(data.delivery_progress){
     const filter=page.locator('[data-curated-editorial]');await filter.selectOption('REVISADA_EDITORIALMENTE');
     assert.equal(await page.locator('.curated-catalog-card').count(),data.a01_a07_revision.reviewed);
     await filter.selectOption('ESTRUCTURADA_POR_TEXTO_OFICIAL_SIN_COTEJO_EDITORIAL');assert.equal(await page.locator('.curated-catalog-card').count(),data.a01_a07_revision.pending);
     await filter.selectOption('');const c=data.cards.find(c=>c.a05_status!=='REVISADA_EDITORIALMENTE')||data.cards[0];
     await page.locator('[data-curated-search]').fill(String(c.cid));assert.equal(await page.locator('.curated-catalog-card').count(),1);
     await page.locator('.curated-catalog-card').click();assert.equal(await page.locator('[data-rule-contract]>section').count(),6);assert((await page.locator('[data-card-review]').innerText()).length>10);await page.locator('.curated-card-modal img').first().dispatchEvent('error');assert((await page.locator('.curated-card-modal [data-card-image-error]').first().innerText()).includes(String(c.cid)));await page.screenshot({path:path.join(out,`v20-${id}-${width}.png`)});await page.locator('[data-card-close]').click();await page.locator('[data-curated-search]').fill('');
    }
    if(data.delivery_v21){
     await page.locator('[data-curated-tab=cards]').click();
     const connectionFilter=page.locator('[data-curated-function]'),choices=await connectionFilter.locator('option').evaluateAll(nodes=>nodes.map(n=>n.value).filter(Boolean));
     for(const label of choices){await connectionFilter.selectOption(label);assert(await page.locator('.curated-catalog-card').count()>0);assert(await page.locator('.curated-catalog-card').evaluateAll((nodes,label)=>nodes.every(n=>[...n.querySelectorAll('.curated-badges span')].some(s=>s.textContent===label)),label));}
     await connectionFilter.selectOption('');const roleFilter=page.locator('[data-curated-role]'),role=await roleFilter.locator('option').nth(1).getAttribute('value');await roleFilter.selectOption(role);assert(await page.locator('.curated-catalog-card').count()>0);await roleFilter.selectOption('');
     if(id==='665')await page.screenshot({path:path.join(out,`connection-categories-${width}.png`)});
     assert.equal(await page.locator('[data-causal-relation]').count(),data.delivery_v21.relations);
     await page.locator('[data-curated-tab=intro]').click();await page.locator('[data-variant-control]>summary').click();
     const cases=await page.locator('[data-variant-case] option').evaluateAll(nodes=>nodes.map(n=>n.value));
     for(const key of cases){await page.locator('[data-variant-case]').selectOption(key);for(const variant of ['LINEA_BASE','RECURSO_O_OBJETIVO_AUSENTE','RESPUESTA_EN_CADENA']){await page.locator('[data-variant-kind]').selectOption(variant);assert((await page.locator('[data-variant-detail]').innerText()).length>100);}}
     await page.locator('[data-curated-tab=plans]').click();assert.equal(await page.locator('[data-situation]').count(),7);
     for(const row of data.situational_decisions){await page.locator(`[data-situation="${row.escenario}"]`).click();assert((await page.locator('[data-situation-detail]').innerText()).includes(row.linea_preferida_condicionada));assert.equal(await page.locator('[data-situation-detail] img').count(),row.photos.length);assert.equal(await page.locator(`[data-situation="${row.escenario}"]`).getAttribute('aria-pressed'),'true');}
     if(['665','706'].includes(id))await page.screenshot({path:path.join(out,`decisions-${id}-${width}.png`)});
    }
    await page.locator('[data-curated-tab=routes]').click();
    const additions=data.routes.filter(r=>['V11','V12','cumulative-delta'].includes(r.source_extension));
    for(const r of additions){
     await page.locator(`.curated-route-library [data-open-route="${r.id}"]`).first().click();
     assert((await page.locator('[data-curated-lesson]').innerText()).includes(r.title));
     assert.equal(await page.locator('.cv-steps>li').count(),r.steps.length);assert(await page.locator('.cv-final img').count()>0);
     assert((await page.locator('[data-curated-lesson]').innerText()).includes('Si el rival interrumpe'));
     if(id==='653'&&r.source_extension==='V12')await page.screenshot({path:path.join(out,`new-route-${width}.png`),fullPage:true});
     await page.locator('[data-route-back]').click();
    }
    await page.locator('[data-curated-tab=plans]').click();
    if(data.side_plans.length){const detail=page.locator('[data-curated-content=plans]>.delivery-details').filter({has:page.locator('summary').filter({hasText:'Planes de Side'})});await detail.locator('summary').first().click();assert.equal(await detail.locator('.curated-scenarios>article').count(),data.side_plans.length);}
    await page.locator('[data-curated-tab=practice]').click();const support=page.locator('.cv-exercises');await support.locator('summary').click();assert.equal(await support.locator('[data-practice]').count(),data.practice.length);
    const button=support.locator('[data-practice]').last();await button.click();assert.equal(await button.getAttribute('aria-expanded'),'true');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),`${id} overflow at ${width}`);
    report.push({deck:id,width,routes:data.routes.length,newRoutesOpened:additions.length,cards:data.cards.length,sidePlans:data.side_plans.length,practice:data.practice.length});
   }
  }
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'ui-verification.json'),JSON.stringify({ok:true,report,errors},null,2));console.log(JSON.stringify({ok:true,decks:11,widths:[1440,390],cases:report.length,errors}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
