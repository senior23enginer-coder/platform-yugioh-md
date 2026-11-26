'use strict';
// P06 guard against linear growth of one JS/PHP test for every analyzed card.
// Data-based cases go to fixtures/; new generic engine test programs require
// an explicit, reviewed baseline update (PR #47: A01 runners; startup PR: shared UI/cache regression; PR #49: administrative CID reconciliation). This NEVER executes any deck analysis.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'..');
const file=path.join(root,'scripts/p06-test-source-allowlist.json');
const baseline=JSON.parse(fs.readFileSync(file,'utf8'));
assert.equal(baseline.schema,'station-x-p06-test-source-baseline/1');
assert(Array.isArray(baseline.paths));
assert.equal(new Set(baseline.paths).size,baseline.paths.length,'Duplicate allowlist entry');
assert.equal(baseline.baseline_total,217,'Baseline alterations require explicit review');
assert.equal(baseline.paths.length,baseline.baseline_total,'Broken baseline count');
const dir=path.join(root,'torneos_yugioh/tests');
const scan=folder=>fs.readdirSync(folder,{withFileTypes:true}).flatMap(entry=>{
  const absolute=path.join(folder,entry.name);
  if(entry.isDirectory())return scan(absolute);
  if(!entry.isFile()||!/_test\.(?:cjs|php|js)$/.test(entry.name))return [];
  return [path.relative(root,absolute).split(path.sep).join('/')];
});
const found=scan(dir).sort();
const legacy=baseline.paths.slice().sort();
const unexpected=found.filter(x=>!legacy.includes(x));
const missing=legacy.filter(x=>!found.includes(x));
assert.deepEqual({unexpected,missing},{unexpected:[],missing:[]},
  'P06: test programs changed without updating the reviewed baseline. Use JSON fixture data for deck-specific cases; retain general motor tests when justified by a reviewed PR.');
const dangerous=fs.readdirSync(dir,{withFileTypes:true}).filter(x=>x.isDirectory()&&
  /^deck-\d+|^(?:memento|crystron)-new-tests/i.test(x.name));
assert.equal(dangerous.length,0,'New per-deck test folders must use declarative JSON fixtures');
console.log(JSON.stringify({ok:true,protectedTestPrograms:legacy.length,
  addedProgramsWithoutReview:unexpected.length,deletedWithoutReview:missing.length,
  verifiedOnly:true,deckAnalysesExecuted:0}));
