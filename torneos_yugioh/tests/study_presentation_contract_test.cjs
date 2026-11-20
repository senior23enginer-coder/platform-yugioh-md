const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const P=require('../assets/modules/deck-guide/presentation-contract.js');
const root=path.resolve(__dirname,'../assets/editorial/20261117-folder-delivery-v40');

for(const [id,expectedRoutes] of [[671,12],[677,14],[665,16]]){
  const source=JSON.parse(fs.readFileSync(path.join(root,id+'.json'),'utf8'));
  assert.equal(source.routes.length,expectedRoutes,'Existing route inventory must not change '+id);
  const style=P.style(source.profile);
  if(id!==665)assert(style&&style.length<=191,'Memento and Crystron need a short visible game style '+id);
  assert(!style.startsWith('Dictamen editorial'),'Internal audit text must not be used as style '+id);
  const brief=P.brief(source.profile);
  if(id!==665)assert(brief&&brief.length<=260,'Main profile must have a short player-focused description '+id);
  for(const route of source.routes){
    for(const step of route.steps){
      const images=P.stepPhotos(step,source.cards);
      assert(Array.isArray(images));
      assert(images.length<=Math.max(4,step.photos?.length||0));
      if(!step.photos?.length)for(const image of images)assert(source.cards.some(c=>c.cid===image.cid||c.id===image.id),'Inferred photo must belong to the current deck');
    }
    if(P.status(route)==='documented'){
      assert(!route.endboard?.variants?.length,'Do not claim a complete board when none was supplied');
      assert(Array.isArray(P.resultPhotos(route,source.cards)));
    }
  }
}
const memento=JSON.parse(fs.readFileSync(path.join(root,'671.json'),'utf8'));
const step=memento.routes[0].steps[0];
assert(step.photos.length>0,'Canonical Memento step photos must now be synchronized');
const historicallyEmpty={...step,photos:[]};
assert.equal(historicallyEmpty.photos.length,0,'Regression fixture must represent historical empty step photos');
const opening=P.openingPhotos(memento.routes[0],memento.cards);
assert(opening.some(p=>p.name==='Angbruja'),'Initial hand must include Angbruja');
assert(!opening.some(p=>p.name==='Maza'),'Card only documented in GY/Deck must not appear as initial hand');
assert(!P.archetype(memento.profile).includes('estudio individual'),'Archetype must not include audit metadata');
const pictures=P.stepPhotos(historicallyEmpty,memento.cards);
const explicit=P.stepPhotos(step,memento.cards);
assert(explicit.some(p=>p.name==='Angbruja'), 'Current visual content must preserve the exact first-step photo');
assert(pictures.some(x=>x.name==='Angbruja'),'Memento Angbruja should appear in step one');
const outcome=P.resultPhotos(memento.routes[0],memento.cards);
assert(outcome.length>0,'Memento outcome should display supported card evidence');
assert.equal(P.status(memento.routes[0]),'documented','No positions may be fabricated');
assert.equal(P.photosFromText('Sin cartas verificadas',memento.cards).length,0,'Do not invent cards');
assert.equal(P.photosFromText('Carta no contenida en el deck',memento.cards).length,0,'Cross-deck photos rejected');
assert(!P.style({'Tipo de estrategia':'Consultar el plan de juego','Estilo de juego':'Dictamen editorial PARCIAL_AVANZADO: destrucción propia → Cementerio.'}).includes('Dictamen'),'Fallback should strip audit jargon');
console.log(JSON.stringify({ok:true,decks:[665,671,677],mementoStepPhotos:pictures.length,mementoOutcomePhotos:outcome.length,unchangedRoutes:true,auditPrefixFiltered:true}));
