const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),out=path.join(root,'outputs/dashboard-divisions-20261107');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},serviceWorkers:'block'}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  const preview=process.env.RANKING_PRODUCTION!=='1';
  if(preview)await context.addInitScript({path:path.join(root,'torneos_yugioh/assets/modules/dashboard/ranking-scopes.js')});
  await context.route('**/*',r=>{
   const req=r.request(),u=new URL(req.url());
   if(req.method()!=='GET'||req.resourceType()==='image')return r.abort();
   if(preview&&u.pathname==='/yugioh/assets/app.js')return r.fulfill({path:path.join(out,'app.js'),contentType:'application/javascript'});
   return r.continue();
  });
  await page.goto('https://stationxtulua.com/yugioh/',{waitUntil:'domcontentloaded',timeout:90000});
  await page.waitForFunction(()=>document.querySelector('[data-dashboard-ranking-event] option')?.value.includes(':'));
  await page.waitForTimeout(1000);
  await page.evaluate(async()=>{for(const modal of document.querySelectorAll('.modal.show')){const b=bootstrap.Modal.getInstance(modal);if(b?._isTransitioning)await new Promise(r=>modal.addEventListener('shown.bs.modal',r,{once:true}));b?.hide();}});
  const values=await page.locator('[data-dashboard-ranking-event] option').evaluateAll(nodes=>nodes.map(n=>({value:n.value,label:n.textContent})));
  const elite=values.filter(o=>/Elite/i.test(o.label));assert.equal(elite.length,2);assert(elite.every(o=>/División/i.test(o.label)));
  const results=[];
  for(const option of values){
   await page.locator('[data-dashboard-ranking-event]').selectOption(option.value);
   await page.locator('[data-dashboard-ranking-metric]').selectOption('general_stats');
   const expected=await page.evaluate(value=>{
    const d=window.DML_DATA,s=YgoDashboardRankingScopes.options(d).find(s=>s.value===value);
    const matches=Object.entries(d.fixtures).flatMap(([division,rounds])=>(Array.isArray(rounds)?rounds:Object.values(rounds)).flatMap(round=>(round.matches||[]).map(m=>({...m,division}))));
    return {total:YgoDashboardRankingScopes.matches(s,matches).length,scope:s};
   },option.value);
   const actual=await page.locator('[data-dashboard-ranking-list] article').nth(2).locator('em').innerText();
   assert.equal(Number(actual),expected.total,option.label);
   const sourceLabel=await page.locator('[data-dashboard-ranking-source]').innerText();
   // Existing event-name aliases can refresh independently. The selected
   // division must still be explicit in the panel heading.
   if(expected.scope.divisionId)assert(sourceLabel.includes(option.label.split('—').at(-1).trim()));
   else assert(sourceLabel.trim());
   for(const metric of ['points','duels','damage_dealt','damage_received','surrenders','lp_losses']){
    await page.locator('[data-dashboard-ranking-metric]').selectOption(metric);
    assert(await page.locator('[data-dashboard-ranking-title]').innerText());
   }
   results.push({label:option.label,totalMatches:expected.total});
  }
  await page.locator('[data-dashboard-ranking-event]').selectOption(elite[0].value);
  await page.locator('[data-dashboard-ranking-metric]').selectOption('general_stats');
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await page.locator('.dashboard-ranking-panel').screenshot({path:path.join(out,`${preview?'preview':'production'}-${width}.png`)});
  }
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,`${preview?'preview':'production'}.json`),JSON.stringify({ok:true,results,productionWrites:0},null,2));console.log(JSON.stringify({ok:true,results}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
