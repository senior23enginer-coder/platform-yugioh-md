'use strict';
// P06: compare GPT-owned paper scenarios with actual existing engine rules.
// This is a regression test of historical fixtures, NOT an A01–A10 analysis.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const {fromPaper,run}=require('./run-deck-logic-fixtures.cjs');
const root=path.resolve(__dirname,'..');
const allowed=new Map([
  [671,'torneos_yugioh/assets/editorial/20261117-folder-delivery-v40/671.json'],
  [677,'torneos_yugioh/assets/editorial/20261117-folder-delivery-v40/677.json']
]);
const fixturePath=process.argv[2];
assert(fixturePath,'Specify the existing GPT paper-case JSON path; no deck is auto-selected.');
const suite=JSON.parse(fs.readFileSync(path.resolve(fixturePath),'utf8'));
assert.equal(suite.schema,'station-x-legacy-paper-regression-suite/1');
assert.equal(suite.explicitly_not_a_new_deck_analysis,true);
assert.equal(suite.coverage_policy,
  'partial_by_legacy_file; do not delete original tests until all assertions match');
assert.deepEqual(suite.documents.map(x=>x.deck_id),[671,677],
  'P06 may exercise only the two historical regressions, not the deck catalogue');
const testedLegacy=new Set();
const reports=[];
let count=0;
for(const paper of suite.documents){
  const record=JSON.parse(fs.readFileSync(path.join(root,allowed.get(paper.deck_id)),'utf8'));
  assert.equal(record.deck_id,paper.deck_id);
  assert.equal(record.format,paper.format);
  const original=JSON.stringify(record);
  const inventory=record.cards.flatMap(card=>(card.slots||[]).map(slot=>({
    id:slot.card_id,section:slot.section,quantity:slot.quantity
  })));
  const pkg={schema:'station-x-deck-package/1',deck_id:record.deck_id,
    inventory,record};
  for(const item of paper.cases){
    assert(/^[a-z_]+_test\.cjs$/.test(item.source_legacy_test));
    const originalTest=path.join(root,'torneos_yugioh/tests',item.source_legacy_test);
    assert(fs.existsSync(originalTest),'Missing source regression '+item.source_legacy_test);
    const code=fs.readFileSync(originalTest,'utf8');
    assert(code.includes(item.effect_id),
      'Fixture effect is absent from its original historical test: '+item.case_id);
    testedLegacy.add(item.source_legacy_test);
  }
  const adapted=fromPaper(paper,pkg,{deckId:record.deck_id,explicitlyAssigned:true});
  assert.deepEqual(adapted.blocked,[],
    'P06 legacy fixtures must never silently skip unsupported logical cases');
  const report=run(adapted.bundle,{deckId:record.deck_id,explicitlyAssigned:true});
  assert.equal(JSON.stringify(record),original,'Read-only paper regression mutated canonical deck');
  assert.equal(report.failed,0,JSON.stringify(report.cases.filter(x=>!x.passed),null,2));
  assert.equal(report.passed,paper.cases.length);
  count+=report.passed;
  reports.push({deck_id:record.deck_id,cases:report.total,passed:report.passed,
    legacySources:[...new Set(paper.cases.map(c=>c.source_legacy_test))]});
}
// The legacy suites are run independently after declarative fixtures pass.
// They are not deleted, and the 8 new cases are explicitly a *subset* of
// their original assertions (planner, other effects and negations remain).
for(const filename of [...testedLegacy].sort()){
  const result=spawnSync(process.execPath,[path.join(root,'torneos_yugioh/tests',filename)],{
    cwd:root,encoding:'utf8',maxBuffer:8*1024*1024
  });
  if(result.status!==0){
    process.stderr.write(result.stdout||'');process.stderr.write(result.stderr||'');
    throw Error('Legacy verification failed alongside new paper cases: '+filename);
  }
}
assert.equal(count,8,'Expected exactly the reviewed 8-case initial P06 slice');
assert.equal(testedLegacy.size,2,'Expected one historical test per deck');
console.log(JSON.stringify({ok:true,legacyTestFilesPreserved:40,
  legacyFilesCompared:testedLegacy.size,
  paperCasesPassed:count,coverage:'partial; not eligible for deleting legacy files',
  analysesExecuted:0,reports}));
