'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const E=require('../assets/modules/play-calculator/engine.js');
const Study=require('../assets/modules/play-calculator/study.js');
const root=path.resolve(__dirname,'..');
const editorial=JSON.parse(fs.readFileSync(path.join(root,'assets/editorial/strategy-content-state.json'),'utf8'));
for(const [id,expected,ruleCount] of [[671,12,316],[677,14,13]]){
  const entry=editorial.checked_studies[String(id)];
  assert(entry,'Missing checked study '+id);
  const study=JSON.parse(fs.readFileSync(path.join(root,entry.path.replace(/^\.\//,'')),'utf8'));
  assert.equal(study.routes.length,expected,'Preserve historical route IDs and count');
  assert.equal(study.calculator?.rules?.length||0,ruleCount,'Portable rule count changed: audit imported effects');
  const inventory=study.cards.flatMap(card=>(card.slots||[]).map(slot=>({
    id:slot.card_id,deck_id:id,card_name:card.name,section:slot.section,quantity:slot.quantity
  })));
  const native=E.catalog({id,name:study.name||'Deck',format_codes:[study.format]},inventory);
  const compiled=Study.compile(study,native);
  assert.equal(compiled.length,expected);
  assert(compiled.every(route=>route.calculatorVerified===false),
    'Editorial route cannot imply fully executable action');
  const comments=E.suggestions(E.create(native),compiled);
  assert(comments.every(row=>row.pending.some(x=>x.includes('no hay evidencia de ejecución íntegra'))),
    'All documentary routes must explain incomplete runtime coverage');
  const bound=Study.bind({bindings:{},names:{},facts:{}},[{catalog:native,study}]);
  assert.equal(bound.studyCoverage[0].routes,expected);
  assert.equal(bound.ruleImports.filter(x=>x.status==='accepted').reduce((n,x)=>n+x.count,0),ruleCount,
    'Portable rules must be accepted independently of documentary route completeness');
}
const ui=fs.readFileSync(path.join(root,'assets/modules/play-calculator/ui.js'),'utf8');
assert(ui.includes('reglas declarativas aceptadas desde el estudio'));
assert(ui.includes('IDs de efecto declarativos'));
assert(ui.includes('Ninguno de estos recuentos certifica una ruta completa'));
console.log('Coverage check: 12 Memento / 14 Crystron routes documentary; portable rules verified separately.');
