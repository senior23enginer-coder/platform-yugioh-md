'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const app=path.resolve(__dirname,'..'),gpt=process.argv[2];
const assigned=[671,677,665,650,653,656,670,684,687,660,673,699,704,705,706,707];
const ids=process.argv[3]?[Number(process.argv[3])]:assigned;
assert(ids.every(id=>assigned.includes(id)));
const index=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/deck-data-sources/index.json'),'utf8'));
const out=path.join(app,'../outputs/a01-assigned');
fs.mkdirSync(out,{recursive:true});
const fields=['Arquetipo','Tipo de estrategia','Estilo breve','Resumen para ficha','Cómo se juega','Qué vigilar'];
(async()=>{
 const browser=await chromium.launch({headless:true});const reports=[];
 try{
 for(const id of ids){
  try{
   const folder=index[id].source_document.replace(/_CONTENIDO_CURADO\.md$/,'');
   const root=path.join(gpt,'01_INFORMACION_PARA_IMPORTAR/decks',folder);
   const {record}=JSON.parse(fs.readFileSync(path.join(root,'datos/stationx-deck.json'),'utf8'));
   assert.equal(record.deck_id,id);
   for(const width of [390,1280]){
    const page=await browser.newPage({viewport:{width,height:920}});const errors=[];
    try{
     page.on('pageerror',e=>errors.push(e.message));
     await page.route('**/*',r=>new URL(r.request().url()).pathname==='/'?r.fulfill({status:200,contentType:'text/html',body:'<html><body><main id="test-guide"></main></body></html>'}):/\.(png|jpe?g|webp)$/i.test(new URL(r.request().url()).pathname)?r.fulfill({status:200,contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4vUAAAAASUVORK5CYII=','base64')}):r.fulfill({status:404,body:''}));
     await page.goto('http://stationx.test/');
     await page.addScriptTag({content:'window.YgoFunctionalRole={project:data=>data,connectionHelp:{}};window.YgoStudyPhotoViewer={create:()=>({open(){},dispose(){}})};'});
     for(const file of ['presentation-contract.js','endboard.js','curated-visual.js','delivery-extensions.js','curated-study.js'])await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide',file)});
     await page.evaluate(({record,id})=>window.YgoCuratedStudy.render(document.querySelector('#test-guide'),{id,name:record.name},record,{cards:record.cards.length,sha256:'assigned-'+id}),{record,id});
     const profile=await page.locator('.curated-profile').innerText();
     const norm=x=>x.replace(/\s+/g,' ').trim();
     for(const field of fields.filter(x=>x!=='Cómo se juega'))assert(norm(profile).includes(norm(record.profile[field])),'Missing or truncated '+field);
     assert.equal(await page.locator('.curated-featured-card').count(),record.player_guide.featured_cards.length);
     assert.equal(record.player_guide.strategy.schema,'station-x-player-strategy/1');
     assert.equal(await page.locator('[data-player-section]').count(),8);
     for(const section of record.player_guide.strategy.sections){
      const lesson=page.locator('[data-player-section="'+section.id+'"]');
      const actual=norm(await lesson.innerText());
      for(const key of ['title','objective','situation','expected_result','resources_to_keep','alternative'])assert(actual.includes(norm(section[key])),'Missing lesson '+section.id+' '+key);
      assert.equal(await lesson.locator('ol li').count(),section.actions.length);
      for(const action of section.actions)assert(actual.includes(norm(action)),'Missing lesson action '+section.id);
      assert.equal(await lesson.locator('[data-open-route]').count(),section.related_route_ids.length);
      assert.equal(await lesson.locator('img').count(),section.card_cids.length);
     }
     for(const [i,featured] of record.player_guide.featured_cards.entries()){
      const card=record.cards.find(c=>c.cid===featured.cid);assert(card);
      const actual=await page.locator('.curated-featured-card').nth(i).innerText();
      assert(norm(actual).includes(norm(card.name))); assert(norm(actual).includes(norm(featured.reason))&&norm(actual).includes(norm(featured.when_to_use)));
     }
     assert.equal(await page.locator('.curated-full-profile').count(),0,'Legacy paragraph leaked');
     const intro=page.locator('[data-curated-content="intro"]');
     assert(!/Dictamen editorial|PARCIAL_AVANZADO|Cat[aá]logo completo|consultar el plan|CID\s*\d+|cobertura documental|validación observada|evaluación editorial|no\s+(?:escribir|redactar|inferir|inventar)\b|informe de auditoría|\b\d+\s+de\s+\d+\s+decks\b/i.test(await intro.textContent()));
     assert.equal(await intro.locator('a[href*="guide.html"]').count(),0);
     assert.deepEqual(errors,[]);
     await page.screenshot({path:path.join(out,id+'-'+width+'.png')});
    }finally{await page.close();}
   }
   reports.push({deck_id:id,status:'PASS',viewports:[390,1280],profile_fields:6,lessons:8,semantic_certification:false});
  }catch(e){reports.push({deck_id:id,status:'FAIL',error:e.message});}
 }
 }finally{await browser.close();}
 fs.writeFileSync(path.join(out,'ui-report.json'),JSON.stringify(reports,null,2));
 console.log(JSON.stringify(reports));if(reports.some(r=>r.status!=='PASS'))process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1});
