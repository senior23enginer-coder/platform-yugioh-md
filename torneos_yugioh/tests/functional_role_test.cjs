const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const model=require('../assets/modules/deck-guide/functional-role.js');
const app=path.resolve(__dirname,'..'),policy=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/strategy-content-state.json')));
let cards=0;
for(const entry of Object.values(policy.checked_studies)){
 const data=JSON.parse(fs.readFileSync(path.join(app,entry.path))),before=JSON.stringify(data),out=model.project(data);
 assert.equal(JSON.stringify(data),before,'Never mutate editorial sources');
 assert.deepEqual(out.cards.map(c=>c.slots),data.cards.map(c=>c.slots));
 assert.deepEqual(out.routes,data.routes);assert.deepEqual(out.skills,data.skills);
 for(const c of out.cards){assert(c.synergy.labels.length);assert(!c.synergy.labels.includes('Sin sinergia demostrada'));cards++;}
 if(Number(data.deck_id)===665){
  if(data.delivery_v21){
   assert(out.cards.find(c=>c.cid===17768).connection.labels.includes('Sinergia directa'));
   assert(out.cards.find(c=>c.cid===12950).connection.labels.includes('Staple / respuesta independiente'));
   assert(out.cards.find(c=>c.cid===18153).connection.labels.includes('Conexión por documentar'));
   continue;
  }
  const find=name=>out.cards.find(c=>c.name.startsWith(name));
  for(const name of ['Nibiru','Droll','Ghost Belle','Dimensional Barrier']){
   const c=find(name);assert(c.synergy.labels.includes('Staple · respuesta situacional'));assert(c.functional.explanation.includes('Reserva'));assert(c.slots.every(s=>s.section==='reserve'));
  }
  for(const name of ['Fossil Warrior','Fossil Machine']){
   const c=find(name);assert(c.synergy.labels.includes('Recurso del Extra para Pot'));assert(c.functional.explanation.includes('destierra boca abajo'));assert(c.functional.explanation.includes('No activa por ello'));
  }
  const g=find('Infinitrack');assert(g.synergy.labels.includes('Recurso potencial del Extra para Pot'));assert(g.slots.every(s=>s.section==='reserve'));assert(g.functional.explanation.includes('incorporarla antes'));
  const raw=data.cards.find(c=>c.name.startsWith('Fossil Warrior'));
  assert(model.classify(raw,{...data,cards:[]}).labels.includes('Utilidad del Extra · acceso por documentar'));
  const avarice={name:'Pot of Avarice',slots:[{section:'main'}]};
  assert(!model.classify(raw,{cards:[avarice]}).labels.some(s=>/para Pot/.test(s)));
 }
}
const unknown={name:'Unknown',role:'',slots:[{section:'main'}],synergy:{labels:['Sin sinergia demostrada']}};
assert.deepEqual(model.classify(unknown,{cards:[]}).labels,['Función por aclarar']);
console.log(JSON.stringify({ok:true,cards,sourceAndInventoriesPreserved:true}));
