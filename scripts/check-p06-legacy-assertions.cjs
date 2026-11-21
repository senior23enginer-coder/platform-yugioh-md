'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'..');
const file=process.argv[2];
assert(file,'Provide the GPT P06 assertion inventory path; do not discover decks');
const approved=JSON.parse(fs.readFileSync(path.resolve(file),'utf8'));
assert.equal(approved.schema,'station-x-p06-legacy-assertion-inventory/1');
assert.equal(approved.semantic_parity_complete,false);
assert.equal(approved.historical_tests_preserved,true);
const result=spawnSync(process.execPath,[
  path.join(__dirname,'export-p06-legacy-inventory.cjs'),'--emit-json'
],{cwd:root,encoding:'utf8',maxBuffer:8*1024*1024});
if(result.status!==0)throw Error('Cannot audit actual historical regression sources: '+result.stderr);
const line=result.stdout.split('\n').find(x=>x.startsWith('P06_ASSERTIONS_JSON='));
assert(line,'Source audit did not provide JSON');
const actual=JSON.parse(line.slice('P06_ASSERTIONS_JSON='.length));
assert.deepEqual(actual,approved,
 'Actual Platform historical source or assertion inventory differs from reviewed GPT manifest');
assert.equal(approved.tests.length,40);
assert.equal(approved.stats.textual_assertion_sites,1005);
console.log(JSON.stringify({ok:true,matchedLegacySources:40,
  textualAssertionSites:1005,fullSemanticMigration:false,
  preservedOriginalTests:true,deckAnalysesExecuted:0}));
