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
console.log(JSON.stringify({ok:true,cards,sourcePreserved:true,allStepsIndexed:true,materialNotInvocation:true,nativeEditsTakePrecedence:true}));
