const { chromium } = require('playwright');
const assert = require('node:assert/strict'), fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),out=path.join(root,'outputs/match-sheet-desktop-20261104');
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext({viewport:{width:1600,height:1000},serviceWorkers:'block'}),page=await context.newPage(),errors=[],blocked=[];
  page.on('pageerror',e=>errors.push(e.message));
  await context.route('**/*',r=>{if(r.request().method()!=='GET'){blocked.push(new URL(r.request().url()).search);return r.abort();}return r.continue();});
  await page.goto('https://stationxtulua.com/yugioh/',{waitUntil:'domcontentloaded',timeout:90000});
  await page.waitForTimeout(1200);
  await page.evaluate(async()=>{for(const n of document.querySelectorAll('.modal.show')){const m=bootstrap.Modal.getInstance(n);if(m._isTransitioning)await new Promise(r=>n.addEventListener('shown.bs.modal',r,{once:true}));await new Promise(r=>{n.addEventListener('hidden.bs.modal',r,{once:true});m.hide();});}});
  if(process.env.MATCH_SHEET_PREVIEW==='1'){
    await page.addStyleTag({path:path.join(root,'torneos_yugioh/assets/modules/match-sheet/layout.css')});
    await page.addScriptTag({path:path.join(root,'torneos_yugioh/assets/modules/match-sheet/navigation.js')});
  }
  await page.locator('[data-alert-modal-open]').dispatchEvent('click');await page.locator('#alertCenterModal.show').waitFor();
  await page.waitForFunction(()=>!bootstrap.Modal.getInstance(document.querySelector('#alertCenterModal'))._isTransitioning);
  const match=page.locator('#alertCenterModal [data-alert-open-match]').first();await match.click();
  await page.locator('#matchSheetModal.show').waitFor();await page.waitForFunction(()=>!bootstrap.Modal.getInstance(document.querySelector('#matchSheetModal'))._isTransitioning);
  assert.deepEqual(await page.locator('.modal.show').evaluateAll(nodes=>nodes.map(n=>n.id)),['matchSheetModal']);assert.equal(await page.locator('.modal-backdrop.show').count(),1);
  const sizes=[];
  for(const viewport of [{width:1600,height:1000},{width:1024,height:768},{width:390,height:844}]){
    await page.setViewportSize(viewport);
    const values=await page.locator('#matchSheetModal').evaluate(el=>{
      const box=s=>el.querySelector(s).getBoundingClientRect(),content=box('.modal-content'),title=box('.modal-header>div:first-child'),action=box('.modal-top-actions'),close=box('.btn-close'),details=[...el.querySelectorAll('.match-sheet-details>span')].map(n=>({left:n.getBoundingClientRect().left,width:n.getBoundingClientRect().width,top:n.getBoundingClientRect().top}));
      return {width:innerWidth,content:{width:content.width,height:content.height},headerClear:title.right<=action.left+1&&action.right<=close.left+1,statsColumns:getComputedStyle(el.querySelector('.mini-grid')).gridTemplateColumns,detailCards:details,bodyOverflow:el.querySelector('.modal-body').scrollWidth>el.querySelector('.modal-body').clientWidth+1};
    });
    assert(values.content.width<=viewport.width);assert(values.content.height<=viewport.height);assert(!values.bodyOverflow,JSON.stringify(values));if(viewport.width>760){assert(values.headerClear,JSON.stringify(values));assert(values.detailCards[3].width>values.detailCards[0].width*2);}
    await page.evaluate(async()=>{await Promise.all([...document.querySelectorAll('#matchSheetModal img')].map(i=>i.decode().catch(()=>{})));await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));});
    sizes.push(values);await page.screenshot({path:path.join(out,`match-${viewport.width}.png`),fullPage:false,timeout:30000});
  }
  // Exercise an immediate modal handoff without invoking result-save/live APIs.
  await page.evaluate(()=>{bootstrap.Modal.getInstance(document.querySelector('#matchSheetModal')).hide();bootstrap.Modal.getOrCreateInstance(document.querySelector('#resultCaptureModal')).show();});
  await page.locator('#resultCaptureModal.show').waitFor();await page.waitForFunction(()=>!bootstrap.Modal.getInstance(document.querySelector('#resultCaptureModal'))._isTransitioning);
  assert.deepEqual(await page.locator('.modal.show').evaluateAll(nodes=>nodes.map(n=>n.id)),['resultCaptureModal']);assert.equal(await page.locator('.modal-backdrop.show').count(),1);
  await page.evaluate(()=>bootstrap.Modal.getInstance(document.querySelector('#resultCaptureModal')).hide());await page.waitForFunction(()=>!document.body.classList.contains('modal-open'));assert.equal(await page.locator('.modal-backdrop').count(),0);
  assert.deepEqual(errors,[]);
  const result={ok:true,preview:process.env.MATCH_SHEET_PREVIEW==='1',sizes,modalHandoff:true,bodyScrollReleased:true,productionWrites:0,blockedPosts:blocked,pageErrors:errors};fs.writeFileSync(path.join(out,process.env.MATCH_SHEET_PREVIEW==='1'?'preview.json':'production.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
