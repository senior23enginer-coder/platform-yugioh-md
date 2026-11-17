const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const app=path.resolve(__dirname,'..'),policy=require('../assets/editorial/strategy-content-state.json');
const C=require('../assets/modules/combo-creator/documented-import.js'),M=require('../assets/modules/combo-creator/model.js');
const native=require('./fixtures/calculator_inventory.json'),rows=[],ids=new Set();let cardsTotal=0,side=0,practice=0,steps=0;
for(const [id,entry]of Object.entries(policy.checked_studies)){
 const data=JSON.parse(fs.readFileSync(path.join(app,entry.path))),old=require(`../assets/editorial/20261113-endboards/${id}.json`),before=JSON.stringify(data);
 assert.equal(data.deck_id,Number(id));assert(old.routes.every(r=>data.routes.some(x=>x.id===r.id)));
 assert.deepEqual(data.cards.map(c=>[c.id,c.cid,c.slots]),old.cards.map(c=>[c.id,c.cid,c.slots]));
 const cards=native.cards.filter(c=>Number(c.deck_id)===Number(id));
 const skills=data.skills.map((s,i)=>({id:`fixture-skill-${id}-${i}`,name:s.name,image:s.image,requirements:'',effect:''}));
 const inventory={deck:{id:Number(id),name:data.name},cards,format:data.format==='rush_duel'?'rush':'tcg',skills};
 for(const r of data.routes){
  assert(!ids.has(r.id));ids.add(r.id);assert(r.steps.length>0);steps+=r.steps.length;
  for(const s of r.steps){assert(s.photos.length>0);for(const p of s.photos)assert(fs.existsSync(path.join(app,p.image)));}
  if(r.source_extension==='V11'||r.source_extension==='V12'){assert(r.interruptions.length);if(r.source_extension!=='cumulative-delta')assert(r.plans.first);assert(r.sources.length);assert(r.board.length);}
  const record=C.convert(data,r,cards,skills),doc=record.document;
  assert.equal(doc.id,C.convert(data,r,cards,skills).document.id);assert.equal(M.ordered(doc).length,doc.nodes.length);
  const full=doc.conditions+'\n'+doc.nodes.map(n=>n.explanation).join('\n');
  for(const s of r.steps)assert(full.includes(s.action),`Lost ${r.id} step ${s.number}`);
  assert(full.includes(r.result));assert(record.issues.every(i=>i.reason.startsWith('Estado sin carta')),JSON.stringify({route:r.id,issues:record.issues}));
  assert(doc.nodes.flatMap(n=>n.cards).every(id=>cards.some(c=>c.id===id)));
  if(['V11','V12','cumulative-delta'].includes(r.source_extension))assert(doc.nodes.some(n=>n.kind==='result'&&n.cards.length));
  rows.push({document:doc,inventory});
 }
 cardsTotal+=data.cards.length;side+=data.side_plans.length;practice+=data.practice.length;
 assert.equal(JSON.stringify(data),before);assert.equal(entry.routes,data.routes.length);
}
assert.equal(ids.size,127);assert.equal(cardsTotal,370);assert.equal(side,16);assert.equal(practice,152);assert.equal(steps,632);
const elf=JSON.parse(fs.readFileSync(path.join(app,policy.checked_studies['650'].path)));
const lucina=elf.routes.find(r=>r.id==='650:A09:ELFNOTE:LUCINA:STRELITZIA02');
assert(lucina.steps[1].photos.some(p=>p.cid===22119));assert(lucina.steps[3].photos.every(p=>!p.label.includes('XYZ')));
const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'stationx-delivery-'));
try{const file=path.join(tmp,'docs.json');fs.writeFileSync(file,JSON.stringify(rows));const run=spawnSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'documented_combo_validate.php'),file],{encoding:'utf8'});assert.equal(run.status,0,run.stdout+run.stderr);console.log(run.stdout.trim());}
finally{assert(path.resolve(tmp).startsWith(path.resolve(os.tmpdir())+path.sep));fs.rmSync(tmp,{recursive:true,force:true});}
console.log(JSON.stringify({ok:true,decks:11,routes:ids.size,cards:cardsTotal,steps,side,practice,nativeDocumentsValidated:rows.length}));
