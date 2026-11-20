'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const E=require('../assets/modules/play-calculator/engine.js');
const Study=require('../assets/modules/play-calculator/study.js');
const root=path.resolve(__dirname,'..');
const editorial=JSON.parse(fs.readFileSync(path.join(root,'assets/editorial/strategy-content-state.json'),'utf8'));
for(const [id,expected] of [[671,12],[677,14]]){
  const entry=editorial.checked_studies[String(id)];
  assert(entry,'Missing checked study '+id);
  const study=JSON.parse(fs.readFileSync(path.join(root,entry.path.replace(/^\.\//,'')),'utf8'));
  assert.equal(study.routes.length,expected,'Preserve historical route IDs and count');
  assert.equal(study.calculator?.rules?.length||0,0,'Fixture changed: inspect rule coverage before modifying test');
  const native=E.catalog({id,name:study.name||'Deck',format_codes:[study.format]},[
    {id:99999,deck_id:id,card_name:'Carta de validación',section:'main',quantity:1}
  ]);
  const compiled=Study.compile(study,native);
  assert.equal(compiled.length,expected);
  assert(compiled.every(route=>route.calculatorVerified===false),
    'Editorial route cannot imply fully executable action');
  const comments=E.suggestions(E.create(native),compiled);
  assert(comments.every(row=>row.pending.some(x=>x.includes('no hay evidencia de ejecución íntegra'))),
    'All documentary routes must explain incomplete runtime coverage');
  const bound=Study.bind({bindings:{},names:{},facts:{}},[{catalog:native,study}]);
  assert.equal(bound.studyCoverage[0].routes,expected);
  assert.equal(bound.ruleImports.filter(x=>x.status==='accepted').reduce((n,x)=>n+x.count,0),0);
}
const ui=fs.readFileSync(path.join(root,'assets/modules/play-calculator/ui.js'),'utf8');
assert(ui.includes('reglas declarativas aceptadas desde el estudio'));
assert(ui.includes('reglas programadas disponibles'));
assert(ui.includes('El número de rutas no equivale a jugadas ejecutables'));
console.log('Coverage check: 12 Memento and 14 Crystron routes preserved and explicitly documentary.');
