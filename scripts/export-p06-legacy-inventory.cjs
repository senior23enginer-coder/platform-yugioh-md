'use strict';
// P06 regression inventory exporter: source-only audit of the 40 protected
// historical tests. It never loads an AI model, executes A01-A10, or edits decks.
// Assertion "sites" are textual candidates, not independent semantic proof.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');
const root=path.resolve(__dirname,'..');
const dir=path.join(root,'torneos_yugioh/tests');
const original=fs.readdirSync(dir).filter(name=>
 /(memento|crystron|cristron|horus)/i.test(name)&&/_test\.(?:cjs|php)$/.test(name)).sort();
assert.equal(original.length,40,'P06 requires the frozen 40-test historical baseline');
const sha=bytes=>crypto.createHash('sha1').update(
 Buffer.concat([Buffer.from('blob '+bytes.length+'\0'),bytes])).digest('hex');
const tests=original.map(filename=>{
 const bytes=fs.readFileSync(path.join(dir,filename));
 const source=bytes.toString('utf8');
 const ids=[...new Set([...source.matchAll(/\b(?:671|677):[a-z0-9:-]+/gi)]
   .map(x=>x[0]))].sort();
 const isRoute=/^memento_c(?:0[1-9]|1[0-2])_route_replay_test\.cjs$/.test(filename);
 const origin=['calculator_portable_memento_test.cjs',
   'calculator_crystron_smiger_test.cjs'].includes(filename);
 return {test_file:'torneos_yugioh/tests/'+filename,
   source_blob_sha:sha(bytes),
   assertion_call_sites:(source.match(/\bassert(?:\.[a-zA-Z]+)?\s*\(/g)||[]).length,
   referenced_effect_ids:ids,
   domain_migration_status:isRoute?'GOLDEN_ROUTE_TRACE_REPLAYED_ASSERTIONS_RETAINED':
      origin?'PARTIAL_PAPER_CASES_ASSERTIONS_RETAINED':'LEGACY_SOURCE_ASSERTIONS_RETAINED'};
});
const document={schema:'station-x-p06-legacy-assertion-inventory/1',
 scope:'regression_only_no_new_deck_analysis',
 historical_tests_preserved:true,semantic_parity_complete:false,
 tests,stats:{tests:tests.length,
   textual_assertion_sites:tests.reduce((n,t)=>n+t.assertion_call_sites,0),
   golden_route_files:tests.filter(t=>t.domain_migration_status.startsWith('GOLDEN_')).length,
   partial_paper_case_sources:tests.filter(t=>t.domain_migration_status==='PARTIAL_PAPER_CASES_ASSERTIONS_RETAINED').length}};
if(process.argv.includes('--emit-json'))process.stdout.write('P06_ASSERTIONS_JSON='+JSON.stringify(document)+'\n');
else console.log(JSON.stringify({ok:true,...document.stats,assertionSitesAreTextual:true,
  semanticParityComplete:false,deckAnalysesExecuted:0}));
