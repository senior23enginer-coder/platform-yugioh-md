'use strict';
// P06 route trace provenance: compare GPT-owned declaration with the exact
// pre-existing C01–C12 tests and canonical replay metadata. This is not
// a fresh AI analysis, nor a substitute for executing the full legacy tests.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');
const root=path.resolve(__dirname,'..');
const source=process.argv[2];
assert(source,'Pass the GPT legacy route trace JSON; no deck is auto-selected.');
const trace=JSON.parse(fs.readFileSync(path.resolve(source),'utf8'));
assert.equal(trace.schema,'station-x-legacy-route-traces/1');
assert.equal(trace.deck_id,671);
assert.equal(trace.format,'tcg_advanced');
assert.equal(trace.authorization,'regression_only_no_new_deck_analysis');
assert.equal(trace.historical_tests_retained,true);
assert.equal(trace.routes.length,12);
const original=JSON.parse(fs.readFileSync(path.join(root,
  'torneos_yugioh/assets/editorial/20261117-folder-delivery-v40/671.json'),'utf8'));
assert.equal(original.deck_id,671);
const ids=new Set();
let total=0;
const mismatches=[];
const sha=buffer=>crypto.createHash('sha1').update(
  Buffer.concat([Buffer.from('blob '+buffer.length+'\0'),buffer])).digest('hex');
for(const entry of trace.routes){
  assert(/^671:A09:C(?:0[1-9]|1[0-2])$/.test(entry.route_id));
  assert(!ids.has(entry.route_id),'Route registered twice');
  ids.add(entry.route_id);
  const sourceRoute=original.routes.find(r=>r.id===entry.route_id);
  assert(sourceRoute,'Historical route absent');
  assert.equal(sourceRoute.steps.length,entry.route_documented_steps);
  assert.equal(sourceRoute.replay_certification?.scope,entry.source_scope);
  assert.equal(sourceRoute.replay_certification?.test,entry.source_test);
  assert.equal(sourceRoute.replay_certification?.verified_final,entry.verified_final_description);
  assert.equal(sourceRoute.replay_certification?.executed_transitions,
    entry.declared_executed_transitions);
  assert(entry.source_test.startsWith('torneos_yugioh/tests/memento_') &&
    /_route_replay_test\.cjs$/.test(entry.source_test));
  const content=fs.readFileSync(path.join(root,entry.source_test));
  assert.equal(sha(content),entry.source_blob_sha,'Historical test changed: '+entry.route_id);
  const found=[...content.toString('utf8').matchAll(
    /\bs\s*=\s*(?:apply|choose|step|take|replay)\(\s*s\s*,\s*['"](671:[^'"]+)['"]/g
  )].map(match=>match[1]);
  assert.deepEqual(found,entry.ordered_effect_ids,
    'Historical step order differs from GPT trace: '+entry.route_id);
  assert.equal(entry.observed_action_calls,found.length);
  for(const effect of found)
    assert(original.calculator.rules.some(r=>r.id===effect),
      'Replay uses an unknown portable effect: '+effect);
  if(found.length!==entry.declared_executed_transitions){
    assert.equal(entry.parity_status,'KNOWN_METADATA_DISCREPANCY');
    mismatches.push(entry.route_id);
  }else assert.equal(entry.parity_status,'TRACE_ALIGNED_NOT_FULLY_MIGRATED');
  assert.equal(entry.migration_status,'TRACE_ONLY_LEGACY_TEST_RETAINED');
  total+=found.length;
}
assert.equal(total,105,'Unexpected source transition count');
assert.deepEqual(mismatches,['671:A09:C02'],'The C02 metadata conflict must not be silently ignored');
assert.deepEqual(trace.stats,{routes:12,observed_effect_calls:105,
  metadata_discrepancies:mismatches});
console.log(JSON.stringify({ok:true,historicalRouteFilesProtected:12,orderedEffectCalls:total,
  metadataDiscrepancies:mismatches,fullIndependentReplaysMigrated:0,
  A01ToA10Executions:0,legacyFilesDeleted:0}));
