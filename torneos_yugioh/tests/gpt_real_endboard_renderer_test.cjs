'use strict';
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const src=process.argv[2];
if(!src||!fs.existsSync(src))throw Error('Pass checked-out GPT root');
const app=path.resolve(__dirname,'..');
const window={};
for(const name of ['endboard.js','presentation-contract.js','curated-visual.js']){
  vm.runInNewContext(fs.readFileSync(path.join(app,'assets/modules/deck-guide',name),'utf8'),{window}, {filename:name});
}
const esc=value=>String(value??'').replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
const text=value=>esc(value).replace(/\n/g,'<br>');
const photo=p=>'<figure><img alt="'+esc(p.name)+'" src="'+esc(p.image)+'"></figure>';
const h={esc,text,photo,strip:a=>(a||[]).map(photo).join(''),resources:()=>'',links:()=>''};
let rendered=0,variants=0;
for(const [deck,folder,expected] of [[671,'DECK_NUEVO_12_MEMENTOTLAN_DEMONIHERRERO',12],[677,'DECK_NUEVO_13_CRISTRON_HORUS',14]]){
  const p=path.join(src,'01_INFORMACION_PARA_IMPORTAR/decks',folder,'datos/stationx-deck.json');
  const record=JSON.parse(fs.readFileSync(p,'utf8')).record;
  assert.equal(record.routes.length,expected);
  for(const route of record.routes){
    const html=window.YgoEndboard.render(record,route,h);
    const preview=window.YgoEndboard.preview(route,h);
    const lesson=window.YgoCuratedVisual.lesson(record,route,h);
    assert(html.includes('data-endboard-root')&&html.includes('Ubicaciones ilustrativas'),'Missing source-limited board disclaimer');
    assert(html.includes('casillas')||html.includes('columnas'),'Missing zone caution');
    assert(html.includes('eb-image-frame'),'No card image rendered on endboard '+route.id);
    assert(preview.includes('Campo final'),'Board preview missing '+route.id);
    assert(lesson.includes('cv-steps')&&lesson.includes('Campo final'),'No step-by-step guide '+route.id);
    assert(lesson.includes('eb-root'),'Guide does not embed the final field '+route.id);
    assert(lesson.includes('cv-result'),'Missing full result narrative '+route.id);
    if(route.endboard.variants.length>1){
      assert(html.includes('data-endboard-variant'),'Conditional variants must be selectable '+route.id);
    }
    variants+=route.endboard.variants.length;rendered++;
  }
}
assert.equal(rendered,26);
assert.equal(variants,28);
console.log(JSON.stringify({ok:true,rendered_routes:rendered,board_variants:variants,
  viewer:'YgoCuratedVisual + YgoEndboard',explicit_note:true,unspecified_slots_not_certified:true}));
