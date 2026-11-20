const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const app=path.resolve(__dirname,'..');
const memento=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/20261117-folder-delivery-v40/671.json'),'utf8'));
const one=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4vUAAAAASUVORK5CYII=','base64');
(async()=>{
  const browser=await chromium.launch({headless:true});
  try{
    for(const width of [390,1280]){
      const page=await browser.newPage({viewport:{width,height:920}});
      const errors=[];
      page.on('pageerror',e=>errors.push(e.message));
      await page.route('**/*',route=>{
        const u=new URL(route.request().url());
        if(u.pathname==='/')return route.fulfill({status:200,contentType:'text/html',body:'<html><body><main id="test-guide"></main></body></html>'});
        if(/\.(png|jpe?g|webp)$/i.test(u.pathname))return route.fulfill({status:200,contentType:'image/png',body:one});
        return route.fulfill({status:404,body:''});
      });
      await page.goto('http://stationx.test/');
      await page.addScriptTag({content:'window.YgoFunctionalRole={project:data=>data,connectionHelp:{}};window.YgoStudyPhotoViewer={create:()=>({open(){},dispose(){}})};'});
      await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide/presentation-contract.js')});
      await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide/endboard.js')});
      await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide/curated-study.js')});
      await page.evaluate(data=>window.YgoCuratedStudy.render(
        document.querySelector('#test-guide'),{id:671,name:'Memento'},data,{cards:data.cards.length,sha256:'fixture'}
      ),memento);
      const style=await page.locator('.curated-profile dl').innerText();
      assert(!style.includes('Dictamen editorial'),'Visible playstyle must be readable, not an audit');
      assert(style.includes('Cementerio'),'Memento playstyle is sourced from the study');
      assert(!style.includes('estudio individual'),'Archetype field cannot display research iteration metadata');
      assert(await page.locator('.curated-full-profile').count(),'Full study explanation remains accessible');
      await page.locator('[data-curated-tab="routes"]').click();
      await page.locator('.curated-route-card').first().click();
      const firstStep=page.locator('.curated-steps>li').first();
      assert(await firstStep.locator('img').count()>0,'Memento Step 1 must have card photos');
      const names=await firstStep.locator('img').evaluateAll(imgs=>imgs.map(img=>img.getAttribute('alt')));
      assert(names.some(n=>n.includes('Angbruja')),'Real Memento card photo is bound to the action');
      assert((await firstStep.innerText()).includes('Invoca Normal Angbruja'),'Complete source action visible');
      assert((await page.locator('.curated-result-full').innerText()).includes('Rey Creación'),'Full end result is visible');
      assert(await page.locator('.eb-layout-note').count()>0,'Nonspatial final-board disclosure must remain visible');
      assert((await page.locator('.eb-layout-note').first().innerText()).includes('no verificadas'),
        'Illustrative slots must not be represented as certified physical positions');
      assert(await page.locator('.curated-final-board img').count()>0,'Result must have card evidence');
      assert.deepEqual(errors,[],'Browser errors in guide on '+width+'px');
      await page.close();
    }
    console.log(JSON.stringify({ok:true,deck:671,viewports:[390,1280],stepPhoto:'Angbruja',result:'Rey Creación',spatialClaim:'documented only',fullSourceAccessible:true}));
  }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
