const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const C=require('../assets/modules/combo-creator/documented-import.js'),M=require('../assets/modules/combo-creator/model.js');
// This regression certifies the historical 108-route endboard delivery. Current
// delivery/native-inventory integration is covered by gpt_delivery_test.cjs.
const app=path.resolve(__dirname,'..'),policy={checked_studies:require('../assets/editorial/20261113-endboards/manifest.json').decks},native=require('./fixtures/calculator_inventory.json'),rows=[],ids=new Set();
const normalized=s=>String(s).replace(/\s/g,'');let variants=0,historical=0;
for(const [id,entry]of Object.entries(policy.checked_studies)){
 const data=JSON.parse(fs.readFileSync(path.join(app,entry.path))),before=JSON.stringify(data),cards=native.cards.filter(c=>Number(c.deck_id)===Number(id));
 const skills=data.skills.map((s,i)=>({id:`fixture-skill-${id}-${i}`,name:s.name,image:s.image,requirements:'',effect:''}));
 const inventory={deck:{id:Number(id),name:data.name},cards,format:data.format==='rush_duel'?'rush':'tcg',skills};
 for(const r of data.routes){
  const record=C.convert(data,r,cards,skills),doc=record.document;assert(!ids.has(doc.id));ids.add(doc.id);assert.equal(doc.id,C.convert(data,r,cards,skills).document.id);assert.equal(doc.deck_id,Number(id));assert.equal(M.ordered(doc).length,doc.nodes.length);
  const full=normalized(doc.conditions+' '+doc.nodes.map(n=>n.explanation).join(' '));
  for(const step of r.steps)assert(full.includes(normalized(step.action)),`Lost action ${r.id}`);
  assert(full.includes(normalized(r.result)),`Lost result ${r.id}`);
  assert.equal(doc.editorial_status,r.editorial_status||(r.historical?'not_executable':'pending'));historical+=Number(r.historical);
  for(const n of doc.nodes){assert(n.cards.every(id=>cards.some(c=>Number(c.id)===id)));assert(n.cards.length<=8);for(const p of n.placements||[])assert(p.card_index<n.cards.length);}
  if(data.skills.length){assert(doc.skill_id);assert.equal(doc.skill_snapshot.name,data.skills[0].name);assert(!doc.nodes.flatMap(n=>n.cards).includes(doc.skill_id));}
  if(r.endboard.variants.length>1)assert(doc.nodes.some(n=>n.kind==='decision'));variants+=r.endboard.variants.length;
  assert(record.issues.every(i=>i.reason.startsWith('Estado sin carta')),'Every identifiable step photo must bind to a native card');
  rows.push({document:doc,inventory});
 }
 assert.equal(JSON.stringify(data),before,'Never mutate original guide');
}
assert.equal(rows.length,108);assert.equal(variants,117);assert.equal(historical,14);
const kashtira=JSON.parse(fs.readFileSync(path.join(app,policy.checked_studies['665'].path)));
assert.equal(kashtira.routes.length,15);
const additions=kashtira.routes.filter(r=>r.source_extension);assert.equal(additions.length,3);
assert.equal(additions.reduce((n,r)=>n+r.steps.length,0),23);
const mirror=additions.find(r=>r.id.endsWith('ZEUS13'));assert.equal(mirror.turn_order,'second');assert.equal(mirror.endboard.variants[0].monster[0].material_count,0);
const anti=additions.find(r=>r.id.endsWith('BARONNE15'));assert.equal(anti.hand_photos.length,3);assert(anti.steps[1].photos.some(p=>p.image.endsWith('/16386.jpg')));assert(anti.steps[3].action.includes('Invocación 5'));
for(const r of additions){assert.equal(r.editorial_status,'conditional');assert(!r.historical);assert(r.steps.every(s=>s.photos.length));const counts={};for(const key of ['monster','spell','graveyard','banished'])for(const p of r.endboard.variants[0][key])for(const item of [p,...(p.materials||[])])counts[item.cid]=(counts[item.cid]||0)+(item.quantity||1);for(const [cid,count]of Object.entries(counts)){const card=kashtira.cards.find(c=>c.cid===Number(cid));assert(count<=card.slots.filter(s=>['main','extra'].includes(s.section)).reduce((n,s)=>n+s.quantity,0),'Final states must respect physical copies');}}
const folder=fs.mkdtempSync(path.join(os.tmpdir(),'stationx-doc-combos-')),file=path.join(folder,'documents.json');
try{fs.writeFileSync(file,JSON.stringify(rows));const run=spawnSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'documented_combo_validate.php'),file],{encoding:'utf8'});assert.equal(run.status,0,run.stdout+run.stderr);console.log(run.stdout.trim());}finally{assert(path.resolve(folder).startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(folder).startsWith('stationx-doc-combos-'));fs.rmSync(folder,{recursive:true,force:true});}
console.log(JSON.stringify({ok:true,decks:11,combos:108,variants:117,historical,stableIds:true,originalsUnchanged:true}));
