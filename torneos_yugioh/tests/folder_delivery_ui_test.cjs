const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const app=path.resolve(__dirname,'..'),policy=require('../assets/editorial/strategy-content-state.json');
(async()=>{const browser=await chromium.launch({headless:true});try{
const page=await browser.newPage({serviceWorkers:'block'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.route('**/*',r=>{const u=new URL(r.request().url());if(u.hostname!=='stationx.test')return r.abort();const p=path.resolve(app,'.'+u.pathname.replace(/^\/yugioh/,''));return p.startsWith(app+path.sep)&&fs.existsSync(p)?r.fulfill({path:p}):r.fulfill({status:404,body:'Missing'});});
await page.setContent('<base href="https://stationx.test/yugioh/"><meta name="viewport" content="width=device-width,initial-scale=1"><main></main>');
for(const n of ['photo-viewer','functional-role','card-detail','endboard','delivery-extensions','curated-visual','curated-study','checked-study'])await page.addScriptTag({path:path.join(app,`assets/modules/deck-guide/${n}.js`)});
await page.addScriptTag({path:path.join(app,'assets/modules/combo-creator/documented-import.js')});
const live=JSON.parse(fs.readFileSync(path.join(app,process.env.YGO_DELIVERY_SNAPSHOT||'../outputs/import-20261116-v31/live.json')));
for(const width of [1440,390]){await page.setViewportSize({width,height:900});for(const [id,entry]of Object.entries(policy.checked_studies)){
const data=JSON.parse(fs.readFileSync(path.join(app,entry.path)));
await page.evaluate(async({id,entry})=>{document.querySelector('main').innerHTML='';await YgoCheckedStudy.render(document.querySelector('main'),{id:Number(id)},entry,()=>true);},{id,entry});
assert.equal(await page.locator('[role=tab]').count(),5);
for(const tab of ['intro','cards','routes','plans','practice']){await page.locator(`[data-curated-tab=${tab}]`).click();assert(await page.locator(`[data-curated-content=${tab}] a[href$="-guide.html"]`).count());}
if(data.delivery_v40){await page.locator('[data-curated-tab=plans]').click();for(const d of data.decisions.filter(d=>d.id.includes(':V40:TACTIC:'))){await page.evaluate(id=>document.querySelector(`[data-decision="${id}"]`).click(),d.id);assert.equal(await page.locator(`[data-decision-answer="${d.id}"]`).getAttribute('hidden'),null);assert((await page.locator(`[data-decision-answer="${d.id}"]`).textContent()).includes('Si empiezas:'));}}
await page.locator('[data-curated-tab=cards]').click();assert.equal(await page.locator('.curated-catalog-card').count(),data.cards.length);await page.locator('.curated-catalog-card').first().click();assert(await page.locator('.curated-card-modal').evaluate(n=>n.open));await page.locator('[data-card-close]').click();
await page.locator('[data-curated-tab=routes]').click();assert.equal(await page.locator('.curated-route-library [data-open-route]').count(),data.routes.length);
for(const r of data.routes){await page.evaluate(({rid})=>document.querySelector(`.curated-route-library [data-open-route="${CSS.escape(rid)}"]`).click(),{rid:r.id});assert(await page.locator('[data-curated-lesson]').isVisible());await page.locator('[data-route-back]').click();}
assert.equal(errors.length,0,`${id}: ${errors.join(';')}`);
}}
const result={ok:true,decks:Object.keys(policy.checked_studies).length,widths:[1440,390],allRoutesOpened:true,cardModals:true};
fs.writeFileSync(path.resolve(app,'..',process.env.YGO_IMPORT_OUTPUT||'outputs/import-20261116-v31','ui-validation.json'),JSON.stringify(result));console.log(JSON.stringify(result));
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
