const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const L=require('../assets/modules/combo-creator/step-lesson.js'),C=require('../assets/modules/combo-creator/documented-import.js');
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const node={kind:'step',explanation:'Invocar Normalmente a Ecclesia. Activa su efecto, CL1, para buscar.\n\nActiva su efecto, CL1, para buscar.\n\nEcclesia NORMAL Invocar Normalmente a Ecclesia. Activa su efecto, CL1, para buscar. *Recurso / restricción:* Activa su efecto, CL1, para buscar.\n\nEcclesia · NORMAL\n\nMano: Una carta\nMonstruos: Ecclesia\nCementerio: Ninguna\nNormal, Extra, fase y restricciones: Normal gastada',details:{cost:'Sin coste adicional',origin:'Mano',destination:'Campo'}};
const before=JSON.stringify(node),lesson=L.fromNode(node,true),html=L.render(lesson,esc);
assert.equal(lesson.actions.length,2);assert.equal(lesson.additional.length,0);assert.equal(lesson.states.length,3);assert.equal(lesson.warnings.length,1);assert.equal(JSON.stringify(node),before);assert.equal(lesson.original,node.explanation);
assert(html.includes('Invoca de forma Normal'));assert(html.includes('eslabón 1 de la Cadena (CL1)'));assert(!html.includes('eslabón 1 de la Cadena (eslabón'));
assert(L.plain('CL1 Fenrir en Main Phase: añadir Unicornio del Deck a mano.').startsWith('Durante la Fase Principal, activa el efecto de Fenrir'));
assert(html.indexOf('Qué haces, en orden')<html.indexOf('Normal gastada'));assert(html.includes('Mano → Campo'));
assert.deepEqual(L.sentences('Invoca F.A. Dawn Dragster. Conserva su efecto.'),['Invoca F.A. Dawn Dragster.','Conserva su efecto.']);
assert(L.render(L.fromNode({kind:'step',explanation:'<script>alert(1)</script>',details:{}},false),esc).includes('&lt;script&gt;'));
const requirements=L.conditions('Necesitas una carta en mano.\n\nNo puedes usar el Extra este turno.\n\nhttps://www.db.yugioh-card.com/example',esc);assert(requirements.includes('No puedes usar el Extra este turno.'));assert(requirements.includes('Consultar fuente 1'));assert(!requirements.includes('href="javascript:'));
const manual=L.fromNode({kind:'step',explanation:'Mueve la carta según el estado que capturaste.',details:{}},false);assert.equal(manual.states.length,0);assert.equal(manual.movement,null);
const app=path.resolve(__dirname,'..'),policy=require('../assets/editorial/strategy-content-state.json'),inventory=require('./fixtures/calculator_inventory.json');let decks=0,routes=0,sourceSteps=0,nativeNodes=0;
for(const [id,entry]of Object.entries(policy.checked_studies)){
 const data=JSON.parse(fs.readFileSync(path.join(app,entry.path))),snapshot=JSON.stringify(data),cards=inventory.cards.filter(c=>c.deck_id===Number(id));decks++;
 for(const route of data.routes){routes++;
  sourceSteps+=route.steps.length;
  const record=C.convert(data,route,cards,data.skills.map((s,i)=>({id:`test-${id}-${i}`,name:s.name,image:s.image,requirements:'',effect:''})));
  for(const n of record.document.nodes){const snap=JSON.stringify(n),model=L.fromNode(n,true);assert.equal(model.original,n.explanation.trim());assert.equal(JSON.stringify(n),snap);assert(L.render(model,esc));nativeNodes++;}
 }
 assert.equal(JSON.stringify(data),snapshot);
}
assert.equal(routes,Object.values(policy.checked_studies).reduce((total,entry)=>total+entry.routes,0));console.log(JSON.stringify({ok:true,decks,routes,sourceSteps,nativeNodes,actionsSeparated:true,originalTextPreserved:true,restrictionsAfterAction:true,noInventedZones:true,manualCompatible:true}));
