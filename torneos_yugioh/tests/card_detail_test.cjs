const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),D=require('../assets/modules/deck-guide/card-detail.js');
const app=path.resolve(__dirname,'..'),policy=require('../assets/editorial/strategy-content-state.json');let cards=0;
for(const entry of Object.values(policy.checked_studies)){
 const data=JSON.parse(fs.readFileSync(path.join(app,entry.path))),before=JSON.stringify(data);
 for(const c of data.cards){const rows=D.participation(c,data);assert.equal(new Set(rows.map(r=>r.id)).size,rows.length);for(const r of rows){assert(data.routes.some(x=>x.id===r.id));for(const s of r.steps)assert(s.index>=0&&s.index<data.routes.find(x=>x.id===r.id).steps.length);}cards++;}
 assert.equal(JSON.stringify(data),before);
 if(data.deck_id===665){const c=data.cards.find(c=>c.cid===17768),rows=D.participation(c,data);const row=rows.find(r=>r.id==='665:A09:WRAITSOTH:SHANGRI01');assert.equal(row.first,3);assert(row.steps.some(s=>s.role==='Material o coste de la jugada'));assert(D.participation(data.cards.find(c=>c.cid===18153),data).some(r=>r.id.includes('V11')));}
}
const c={id:'card-1',cid:1,image:'one.jpg',slots:[{card_id:10}],route_links:[{id:'r',step:0}]};
assert.equal(D.role('ESTADO / CONTEXTO').active,false);assert.equal(D.role('MATERIAL XYZ').name,'Material o coste de la jugada');assert.notEqual(D.role('INVOCAR XYZ').name,'Invocación Especial');assert.equal(D.role('XYZ · 2 MATERIALES').active,false);assert.equal(D.role('BUSCA').name,'Interviene en una búsqueda');
const edited={routes:[{id:'r',title:'Editada',native_editor_document:{nodes:[{id:'s',kind:'step',cards:[20],action:'Otro recurso'}]}}]};assert.equal(D.participation(c,edited).length,0,'A saved edit removing a card overrides legacy links');
const wrong={routes:[{id:'different',title:'Otra carta',steps:[{photos:[{cid:2,image:'one.jpg'}]}]}]};assert.equal(D.participation({...c,route_links:[]},wrong).length,0,'Explicit CID takes priority over a repeated photo');
assert(D.clean('GY; CL1; Main Phase; Kash').includes('Cementerio; eslabón 1 de la Cadena; fase principal; Kashtira'));
// Accesos Extra documentales: se muestran desde la carta sin inventar rutas A09.
const escapeHtml=s=>String(s??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const helper={esc:escapeHtml,text:escapeHtml,strip:()=>'<span>Imagen</span>'};
const aero={id:'671:CARD:9657',cid:9657,name:'Aero Tiburón',role:'Xyz Rango 3',image:'9657.png',
  slots:[{card_id:517,section:'extra',quantity:1}],fields:[{title:'Materiales y acceso al Extra (V40)',text:'2 Nivel 3'}],
  sources:[],operational:{},learning:{},synergy:{labels:[],resource:'',relation:''},
  extra_access:[{id:'EX-M03',cid:9657,status:'DOCUMENTADA_CONDICIONAL_SIN_REPLAY',source_section:'A09.0',
    opening:'Angbruja + Fiesta + Goblin',materials:'Dos Angbruja Nivel 3',steps:['Invocar dos Angbruja','Superponer como materiales Xyz'],
    result:'Aero Tiburón con dos materiales',limitation:'Sin desterrados no hay daño útil'}]};
const aeroHtml=D.render(aero,{routes:[]},helper);
assert(aeroHtml.includes('EX-M03')&&aeroHtml.includes('Dos Angbruja Nivel 3'));
assert(aeroHtml.includes('Accesos adicionales al Extra Deck')&&aeroHtml.includes('data-extra-access-id'));
assert(!aeroHtml.includes('No hay una jugada enlazada'),'Extra access should not be hidden behind a false no-route message');
assert(aeroHtml.includes('Condiciones y momento'),'Existing V40 material must appear when no operational guard is populated');
const missingHtml=D.render({...aero,extra_access:[],fields:[],operational:{}},{routes:[]},helper);
assert(missingHtml.includes('El paquete aún no aporta condiciones verificables'),'Never render an empty requirements section');
// Validate the actual Memento 671 editorial import, not only a synthetic example.
const memento=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/20261117-folder-delivery-v40/671.json'),'utf8'));
assert.equal(memento.deck_id,671);
assert.equal(memento.routes.length,12,'Extra access transformations are not extra A09 combo families');
assert.equal(memento.cards.filter(card=>card.slots.some(slot=>slot.section==='extra')).length,13);
const extraAccessExpectations=new Map([['EX-M01',12870],['EX-M02',19184],['EX-M03',9657],['EX-M04',13081]]);
for(const [accessId,cid] of extraAccessExpectations){
 const card=memento.cards.find(card=>card.cid===cid);
 assert(card && card.slots.some(slot=>slot.section==='extra'),'Missing Memento Extra card CID '+cid);
 assert(card.practical.length>0 && Object.keys(card.operational).length>0 && Object.keys(card.learning).length>0,
   'Memento Extra card lacks player-use explanation: '+cid);
 const access=(card.extra_access||[]).find(access=>access.id===accessId);
 assert(access,'Missing documented Extra access '+accessId);
 assert.equal(access.cid,cid,'Wrong Extra access card identity');
 assert(access.status==='DOCUMENTADA_CONDICIONAL_SIN_REPLAY'||access.status==='TEORICA_PENDIENTE_VALIDACION',
   'Do not treat documentary access as a certified replay');
 assert(access.opening&&access.materials&&access.result&&access.limitation&&access.steps.length>=2,
   'Incomplete documentary Extra access '+accessId);
 const rendered=D.render(card,memento,helper);
 assert(rendered.includes('data-extra-access-id="'+accessId+'"')&&rendered.includes('Accesos adicionales al Extra Deck'),
   'Station X card modal lost '+accessId);
 assert(!rendered.includes('No hay una jugada enlazada con evidencia específica'),
   'Do not hide documentary access '+accessId+' behind the no-route message');
}
// Prevent regressions where step photos silently drift from the canonical visual delivery.
let mementoStepPhotoRefs=0;
for(const route of memento.routes){
 for(const step of route.steps){
   assert(step.photos.length>0,'Missing step photos '+route.id+' '+step.number);
   for(const photo of step.photos){
     const native=memento.cards.find(card=>card.cid===photo.cid);
     assert(native,'Unexpected CID in photographed step '+photo.cid);
     assert.equal(photo.image,native.image,'Step image must use Station X native asset path');
     assert.equal(photo.full_image,native.full_image,'Full step image must use Station X native asset path');
     mementoStepPhotoRefs++;
   }
 }
}
assert.equal(mementoStepPhotoRefs,198,'Memento must preserve all 198 verified documentary step photographs');
const aeroLiveCard=memento.cards.find(card=>card.cid===9657);
assert(D.render(aeroLiveCard,memento,helper).includes('Dos Angbruja'),
  'Aero Tiburón must show its exact material line EX-M03 from the current Memento package');
console.log(JSON.stringify({ok:true,cards,sourcePreserved:true,allStepsIndexed:true,materialNotInvocation:true,nativeEditsTakePrecedence:true}));
