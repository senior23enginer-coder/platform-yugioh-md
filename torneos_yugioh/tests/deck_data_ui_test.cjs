const {chromium}=require('playwright');
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const app=path.resolve(__dirname,'..'),out=path.resolve(app,'../outputs/deck-data-20261117');
const candidate=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/20261117-folder-delivery-v39/665.json')));
candidate.routes.push({...candidate.routes[0],id:'665:A09:TEST_NEW',title:'Nueva jugada de prueba'});
const nativeCards=candidate.cards.flatMap(c=>c.slots.map(s=>({id:s.card_id,deck_id:665,card_name:c.name,section:s.section,quantity:s.quantity})));
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true});
 try{for(const width of [1440,390]){
  const page=await browser.newPage({viewport:{width,height:950}});const errors=[];page.on('pageerror',e=>errors.push(e.message));let commits=0,exports=0;
  await page.route('http://stationx.test/**',async route=>{
   const url=new URL(route.request().url());
   if(url.searchParams.get('api')==='deck_data'){
    const op=url.searchParams.get('op');assert.equal(url.searchParams.get('deck_id'),'665');
    if(['preview','commit'].includes(op))assert.equal(route.request().headers()['x-csrf-token'],'test-token');
    if(op==='export'){exports++;return route.fulfill({contentType:'application/zip',body:Buffer.from('PK export fixture')});}
    let value={ok:true,token:'test-token',has_data:true,cards:38,routes:16};
    if(op==='preview'){assert.match(route.request().postData(),/package/);value={ok:true,ticket:'ticket-1',deck:'Kashtira Pure',cards:38,routes:17,new_routes:1,changed_routes:2,removed_routes:0,saved_revisions:3,candidate,native_cards:nativeCards,skills:[]};}
    if(op==='commit'){assert.match(route.request().postData(),/ticket-1/);commits++;value={ok:true,deck:'Kashtira Pure',entry:{cards:38,routes:17}};}
    return route.fulfill({json:value});
   }
   if(url.pathname==='/')return route.fulfill({contentType:'text/html; charset=utf-8',body:'<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/modules/deck-data/styles.css"><body style="font-family:system-ui"><button data-deck-data-open>Importar / exportar Data</button><script src="/assets/modules/deck-data/ui.js"></script></body></html>'});
   const file=path.join(app,url.pathname);if(fs.existsSync(file))return route.fulfill({path:file});return route.abort();
  });
  await page.goto('http://stationx.test/');
  for(const name of ['photo-viewer','functional-role','card-detail','endboard','delivery-extensions','curated-visual','curated-study'])await page.addScriptTag({path:path.join(app,`assets/modules/deck-guide/${name}.js`)});
  await page.addScriptTag({path:path.join(app,'assets/modules/combo-creator/documented-import.js')});
  await page.evaluate(()=>{YgoDeckData.mount({id:665,name:'Kashtira Pure'});window.listDownloads=[];document.addEventListener('click',e=>{if(e.target.dataset.deckDownload)listDownloads.push(e.target.dataset.deckDownload);});});
  await page.locator('[data-deck-data-open]').click();await page.getByText('38 fichas y 16 jugadas disponibles para exportar.').waitFor();
  assert.equal(await page.locator('.deck-data-actions button').count(),3);
  await page.locator('[data-dd-list]').click();assert.deepEqual(await page.evaluate(()=>listDownloads),['665']);
  const download=page.waitForEvent('download');await page.locator('[data-dd-export]').click();assert.equal((await download).suggestedFilename(),'Kashtira Pure-Data.zip');assert.equal(exports,1);
  await page.locator('[data-dd-file]').setInputFiles({name:'Kashtira.zip',mimeType:'application/zip',buffer:Buffer.from('PK fixture')});await page.locator('[data-dd-preview]:visible').waitFor();
  assert.equal(commits,0);assert.match(await page.locator('[data-dd-preview]').innerText(),/3 ediciones guardadas/);
  const overflow=await page.evaluate(()=>{const d=document.querySelector('dialog');return d.scrollWidth>d.clientWidth+1;});assert.equal(overflow,false);
  await page.screenshot({path:path.join(out,`preview-${width}.png`)});
  await page.locator('[data-dd-commit]').click();await page.locator('[data-dd-reload]:visible').waitFor();assert.equal(commits,1);assert.match(await page.locator('[data-dd-status]').textContent(),/17 jugadas/);
  await page.keyboard.press('Escape');assert.equal(await page.locator('dialog').evaluate(d=>d.open),false);assert.equal(errors.length,0,errors.join('\n'));await page.close();
 }
 fs.writeFileSync(path.join(out,'ui-validation.json'),JSON.stringify({ok:true,viewports:[1440,390],previewBeforeCommit:true,exports:true}));console.log('Deck Data desktop/mobile preview, download, import and keyboard: passed');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
