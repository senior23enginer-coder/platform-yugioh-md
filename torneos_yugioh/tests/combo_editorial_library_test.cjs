const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const M=require('../assets/modules/combo-creator/model.js');
const root=path.resolve(__dirname,'../..'),app=path.join(root,'torneos_yugioh'),out=path.join(root,'outputs/combo-library-20261113');
const policy=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/strategy-content-state.json')));
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext({serviceWorkers:'block'}),page=await context.newPage(),errors=[];
  let sourceFailure=false,personalFailure=false;
  page.on('pageerror',e=>errors.push(e.message));
  await context.route('**/*',async route=>{
   const u=new URL(route.request().url());assert.equal(route.request().method(),'GET','Read-only test');
   if(u.searchParams.get('api')==='combo_creator'){
    if(personalFailure)return route.fulfill({status:503,json:{ok:false,message:'Creaciones temporalmente no disponibles'}});
    const id=Number(u.searchParams.get('deck_id'));let doc=M.addNode(M.create(id),'step');doc.title=`Manual ${id}`;
    return route.fulfill({json:{ok:true,cards:[],cards_loaded:true,records:[{id:doc.id,title:doc.title,revision:1,document:doc,cards_snapshot:[]} ]}});
   }
   if(sourceFailure&&u.pathname.endsWith('/strategy-content-state.json'))return route.fulfill({status:503,body:'Unavailable'});
   const f=path.resolve(app,'.'+u.pathname.replace(/^\/yugioh/,''));
   return f.startsWith(app+path.sep)&&fs.existsSync(f)?route.fulfill({path:f}):route.fulfill({status:404,body:'Missing asset'});
  });
  await page.setContent('<base href="https://stationx.test/yugioh/"><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/modules/combo-creator/styles.css"><link rel="stylesheet" href="assets/modules/deck-guide/checked-study.css"><link rel="stylesheet" href="assets/modules/deck-guide/curated-study.css"><link rel="stylesheet" href="assets/modules/deck-guide/curated-visual.css"><link rel="stylesheet" href="assets/modules/deck-guide/endboard.css"><main><div id="original"></div><div data-deck-created-combos></div></main>');
  for(const f of ['combo-creator/model','combo-creator/ui','deck-guide/photo-viewer','deck-guide/functional-role','deck-guide/endboard','deck-guide/curated-visual','deck-guide/curated-study','deck-guide/checked-study','combo-creator/editorial-library'])await page.addScriptTag({path:path.join(app,'assets/modules/'+f+'.js')});
  await page.evaluate(()=>window.addEventListener('stationx:combo-edit',e=>window.lastDocumentedEdit=e.detail));
  const mount=id=>page.evaluate(id=>YgoComboCreator.mountViewer(document.querySelector('[data-deck-created-combos]'),{id,name:`Deck ${id}`}),id);
  let routes=0;
  for(const [id,entry]of Object.entries(policy.checked_studies)){
   await mount(Number(id));
   const doc=page.locator('[data-cc-editorial-library]');assert.equal(await doc.locator('[data-route-library] [data-open-route]').count(),entry.routes);
   assert(await doc.locator('[data-curated-page=routes]').isVisible());
   assert.equal(await page.locator('[data-cc-personal] [data-cc-read]').count(),1);
   await page.locator('[data-cc-read]').click();assert((await page.locator('[data-cc-reading]').innerText()).includes(`Manual ${id}`));
   for(const width of [1440,390]){
    await page.setViewportSize({width,height:1000});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    const expected=await doc.locator('.curated-route-card:visible').first().getAttribute('data-open-route');await doc.locator('.curated-route-card:visible').first().click();assert.deepEqual(await page.evaluate(()=>window.lastDocumentedEdit),{deckId:Number(id),sourceId:expected});
    if(id==='665')await page.screenshot({path:path.join(out,`library-${width}.png`),fullPage:true});
   }
   routes+=entry.routes;
  }
  // Both entry points can coexist without duplicate accessibility IDs.
  await mount(665);await page.evaluate(async entry=>YgoCheckedStudy.render(document.querySelector('#original'),{id:665},entry,()=>true),policy.checked_studies['665']);
  assert(await page.evaluate(()=>{const ids=[...document.querySelectorAll('[id]')].map(n=>n.id);return new Set(ids).size===ids.length;}));
  // Failure of either source must preserve the other; retry recovers the library.
  sourceFailure=true;await mount(665);assert(await page.locator('[data-cc-editorial-retry]').isVisible());assert.equal(await page.locator('[data-cc-read]').count(),1);
  sourceFailure=false;await page.locator('[data-cc-editorial-retry]').click();await page.locator('[data-cc-editorial-library] .curated-route-card:visible').first().waitFor();
  personalFailure=true;await mount(665);assert.equal(await page.locator('[data-cc-editorial-library] [data-route-library] [data-open-route]').count(),15);assert((await page.locator('[data-cc-personal]').innerText()).includes('temporalmente'));
  personalFailure=false;await mount(9999);assert((await page.locator('[data-cc-editorial-library]').innerText()).includes('No hay guías'));assert.equal(await page.locator('[data-cc-read]').count(),1);
  await page.evaluate(()=>Promise.all([YgoComboCreator.mountViewer(document.querySelector('[data-deck-created-combos]'),{id:665,name:'Kashtira'}),YgoComboCreator.mountViewer(document.querySelector('[data-deck-created-combos]'),{id:704,name:'Pyro'})]));
  assert.equal(await page.locator('[data-cc-editorial-library] [data-route-library] [data-open-route]').count(),2);assert((await page.locator('[data-cc-personal]').innerText()).includes('Manual 704'));
  assert.deepEqual(errors,[]);assert.equal(routes,108);
  const result={ok:true,decks:11,routes,viewports:[1440,390],independentFailures:true,uniqueIds:true,productionWrites:0};fs.writeFileSync(path.join(out,'tests.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
