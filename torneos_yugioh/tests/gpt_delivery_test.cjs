const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const app=path.resolve(__dirname,'..'),out=path.resolve(app,'../outputs/import-20261117-gpt');
const read=p=>JSON.parse(fs.readFileSync(p,'utf8'));
const registry=read(path.join(app,'assets/editorial/strategy-content-state.json'));
const live=read(path.join(out,'live.json')),before=read(path.join(out,'registry-before.json'));
const targets=new Set(['660','671','673','677','699']);
const C=require('../assets/modules/combo-creator/documented-import.js'),docs=[];
const signature=rows=>rows.map(c=>[c.card_name,c.section,Number(c.quantity)]).sort();
const plan=read(path.join(out,'inventory-plan.json'));
for(const [id,e] of Object.entries(registry.checked_studies)){
  if(!targets.has(id))assert.deepEqual(e,before.checked_studies[id],`Unrelated deck ${id} changed`);
  const d=read(path.join(app,e.path)),cards=live.cards.filter(c=>Number(c.deck_id)===Number(id));
  if(targets.has(id))assert.deepEqual(signature(cards),signature(plan[id].cards));
  for(const c of d.cards)for(const s of c.slots)assert(cards.some(n=>Number(n.id)===s.card_id&&n.section===s.section&&Number(n.quantity)===s.quantity));
  assert.equal(new Set(d.routes.map(r=>r.id)).size,d.routes.length);
  for(const r of d.routes){
    const skills=(d.skills||[]).map((s,i)=>({id:`skill-${id}-${i}`,name:s.name,image:s.image,requirements:'',effect:''}));
    const record=C.convert(d,r,cards,skills);
    assert(record.document.nodes.flatMap(n=>n.cards).every(cid=>cards.some(c=>Number(c.id)===cid)));
    docs.push({document:record.document,inventory:{deck:{id:Number(id),name:d.name},cards,format:d.format==='rush_duel'?'rush':d.format==='goat'?'goat':'tcg',skills}});
  }
}
const file=path.join(out,'native-validation-input.json');fs.writeFileSync(file,JSON.stringify(docs));
const r=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[path.join(__dirname,'documented_combo_validate.php'),file],{encoding:'utf8'});
assert.equal(r.status,0,r.stdout+r.stderr);console.log(r.stdout);
console.log(`PASS: ${Object.keys(registry.checked_studies).length} decks, native inventory bindings, unrelated decks unchanged.`);
