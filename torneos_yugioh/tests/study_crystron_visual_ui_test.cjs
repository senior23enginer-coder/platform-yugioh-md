'use strict';
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const app=path.resolve(__dirname,'..');
const editorial=JSON.parse(fs.readFileSync(path.join(
  app,'assets/editorial/20261117-folder-delivery-v40/677.json'),'utf8'));
const pixel=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4vUAAAAASUVORK5CYII=','base64');
(async()=>{
  const browser=await chromium.launch({headless:true});
  try{
    assert.equal(editorial.routes.length,14,'All documented routes preserved');
    for(const width of [390,1280]){
      const page=await browser.newPage({viewport:{width,height:920}});
      const errors=[];
      page.on('pageerror',error=>errors.push(error.message));
      await page.route('**/*',route=>{
        const url=new URL(route.request().url());
        if(url.pathname==='/')return route.fulfill({status:200,contentType:'text/html',
          body:'<html><body><main id="test-guide"></main></body></html>'});
        if(/\.(png|jpe?g|webp)$/i.test(url.pathname))
          return route.fulfill({status:200,contentType:'image/png',body:pixel});
        return route.fulfill({status:404,body:''});
      });
      await page.goto('http://stationx.test/');
      await page.addScriptTag({content:'window.YgoFunctionalRole={project:data=>data,connectionHelp:{}};window.YgoStudyPhotoViewer={create:()=>({open(){},dispose(){}})};'});
      for(const file of ['presentation-contract.js','endboard.js','curated-study.js'])
        await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide',file)});
      await page.evaluate(data=>window.YgoCuratedStudy.render(
        document.querySelector('#test-guide'),{id:677,name:'Crystron/Horus'},data,
        {cards:data.cards.length,sha256:'fixture'}),editorial);
      const profile=await page.locator('.curated-profile dl').innerText();
      assert(profile.includes('Sincron')||profile.includes('Cristron'),
        'Crystron profile must be player-facing and retain the actual deck strategy');
      await page.locator('[data-curated-tab="routes"]').click();
      assert.equal(await page.locator('.curated-route-card').count(),14,
        'All 14 Crystron routes must be accessible');
      await page.locator('.curated-route-card').first().click();
      const step=page.locator('.curated-steps>li').first();
      const alt=await step.locator('img').evaluateAll(images=>images.map(image=>image.getAttribute('alt')));
      assert(alt.some(name=>name.includes('Smiger')),
        'The first Crystron action must include the authentic Smiger photo');
      assert((await step.innerText()).includes('Smiger'),
        'Step text and image must identify the same real card');
      const documented=await page.locator('.eb-layout-note').first().innerText();
      assert(/no verificad/i.test(documented),
        'Illustrative columns cannot be represented as certified field positions');
      const resultPhotos=await page.locator('.curated-final-board img').count();
      assert(resultPhotos>0,'Crystron outcome must include at least one known real card photo');
      assert.deepEqual(errors,[],'No browser runtime errors allowed at '+width+'px');
      await page.close();
    }
    console.log(JSON.stringify({ok:true,deck:677,routes:14,
      viewports:[390,1280],firstStep:'Smiger',nonSpatialDisclosure:true}));
  }finally{await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
