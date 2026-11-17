const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const app=path.resolve(__dirname,'..'),M=require('../assets/modules/deck-guide/functional-role.js'),policy=require('../assets/editorial/strategy-content-state.json');
let total=0;
for(const entry of Object.values(policy.checked_studies)){
 const data=JSON.parse(fs.readFileSync(path.join(app,entry.path))),before=JSON.stringify(data),out=M.project(data);
 assert.equal(JSON.stringify(data),before);assert.deepEqual(out.routes,data.routes);
 for(let i=0;i<out.cards.length;i++){
  const c=out.cards[i];assert.deepEqual(c.slots,data.cards[i].slots);assert.deepEqual(c.fields,data.cards[i].fields);
  assert(c.connection.labels.length);assert(c.connection.summary);assert(c.connection.labels.every(l=>M.connectionHelp[l]));
  assert(!c.synergy.labels.some(l=>/extraida|especificas documentadas/i.test(l)));total++;
 }
 if(data.deck_id===665){
  const fenrir=out.cards.find(c=>c.cid===17768),ash=out.cards.find(c=>c.cid===12950),tear=out.cards.find(c=>c.cid===18153);
  assert(fenrir.connection.summary.includes('Kashtira'));assert(fenrir.connection.labels.includes('Sinergia directa'));
  assert(ash.connection.labels.includes('Staple / respuesta independiente'));
  assert(tear.connection.labels.includes('Conexión por documentar'));
 }
}
const c={cid:1,name:'Carta A',role:'',slots:[{section:'main',quantity:1}],synergy:{labels:[]}},other={...c,cid:2,name:'Carta B'};
const r={origen:{cid:1,nombre:'Carta A'},destino:{cid:2,nombre:'Carta B'},clase:'CONFLICTO_REQUISITOS',interaccion:'Restricción documentada'};
const p=M.presentation(c,{cards:[c,other],causal_relations:[r]});assert.deepEqual(p.labels,['Conflicto o incompatibilidad']);
console.log(JSON.stringify({ok:true,cards:total,preservesEditorialData:true,conflictIsNotSynergy:true,independentUseIsNotUseless:true}));
