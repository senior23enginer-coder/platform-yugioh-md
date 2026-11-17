const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{spawnSync}=require('child_process');
const app=path.resolve(__dirname,'..'),out=path.resolve(app,'..',process.env.YGO_IMPORT_OUTPUT||'outputs/import-20261116-v31');
const registry=require('../assets/editorial/strategy-content-state.json'),live=JSON.parse(fs.readFileSync(path.join(out,'live.json')));
const C=require('../assets/modules/combo-creator/documented-import.js');
const docs=[];let count=0;
for(const [id,entry]of Object.entries(registry.checked_studies)){
const d=JSON.parse(fs.readFileSync(path.join(app,entry.path))),cards=live.cards.filter(c=>Number(c.deck_id)===Number(id));
assert(d.cards.every(c=>c.slots.every(s=>s.card_id>0&&cards.some(n=>Number(n.id)===s.card_id&&n.section===s.section&&Number(n.quantity)===s.quantity))));
assert.equal(new Set(d.routes.map(r=>r.id)).size,d.routes.length);
const skills=d.skills.map((s,i)=>({id:`skill-${id}-${i}`,name:s.name,image:s.image,requirements:'',effect:''}));
for(const route of d.routes){
const record=C.convert(d,route,cards,skills);assert.equal(record.document.deck_id,Number(id));
assert(record.document.nodes.flatMap(n=>n.cards).every(cid=>cards.some(c=>Number(c.id)===cid)));
docs.push({document:record.document,inventory:{deck:{id:Number(id),name:d.name},cards,format:d.format==='rush_duel'?'rush':d.format==='goat'?'goat':'tcg',skills}});count++;
}
}
const file=path.join(out,'native-validation-input.json');fs.writeFileSync(file,JSON.stringify(docs));
const r=spawnSync('C:\\xampp\\php\\php.exe',[path.join(__dirname,'documented_combo_validate.php'),file],{encoding:'utf8'});
assert.equal(r.status,0,r.stdout+r.stderr);console.log(r.stdout);const result={decks:Object.keys(registry.checked_studies).length,routes:count,foreignCards:0};fs.writeFileSync(path.join(out,'native-validation.json'),JSON.stringify(result));console.log(JSON.stringify(result));
