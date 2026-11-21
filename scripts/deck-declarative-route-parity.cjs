'use strict';
// Reusable P06 verifier: one declarative regression fixture may contain many
// routes. It compares exact current physical transitions to legacy tests,
// without running a new deck study or changing the canonical inventory.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const {runRoutes}=require('./run-deck-logic-fixtures.cjs');
const root=path.resolve(__dirname,'..');
const fixturePath=process.argv[2];
assert(fixturePath,'Specify a GPT-owned route fixture file; no automatic deck selection.');
const fixture=JSON.parse(fs.readFileSync(path.resolve(fixturePath),'utf8'));
assert.equal(fixture.schema,'station-x-declarative-route-fixtures/1');
assert.equal(fixture.scope,'historical_regression_only_not_a_new_deck_analysis');
assert([671,677].includes(fixture.deck_id),
  'P06 only verifies historical deck fixtures, never discovers catalogue decks');
assert(Array.isArray(fixture.routes)&&fixture.routes.length>0);
const p=path.join(root,'torneos_yugioh/assets/editorial/20261117-folder-delivery-v40',
  fixture.deck_id+'.json');
const record=JSON.parse(fs.readFileSync(p,'utf8'));
assert.equal(record.deck_id,fixture.deck_id);
assert.equal(record.format,fixture.format);
const original=JSON.stringify(record);
const cards=record.cards.flatMap(c=>(c.slots||[]).map(slot=>({
  card_id:slot.card_id,cid:c.cid,name:c.name,section:slot.section,
  quantity:slot.quantity
})));
const fixtures={schema:'station-x-logic-case-fixtures/1',deck_id:fixture.deck_id,
  format:fixture.format,cards,rules:record.calculator.rules,cases:[],
  route_cases:fixture.routes};
for(const route of fixture.routes){
  assert.equal(route.expected_transitions,route.steps.length);
  const canonical=record.routes.find(r=>r.id===route.route_id);
  assert(canonical,'Route absent from canonical historical documentation');
  assert.equal(canonical.steps.length,route.documented_steps);
  assert.equal(canonical.replay_certification?.test,fixture.source);
  assert.equal(canonical.replay_certification?.executed_transitions,
    route.expected_transitions);
}
const output=runRoutes(fixtures,{deckId:fixture.deck_id,explicitlyAssigned:true});
assert.equal(output.failed,0,JSON.stringify(output.routes.filter(x=>!x.passed),null,2));
assert.equal(output.passed,fixture.routes.length);
assert.equal(JSON.stringify(record),original,'Paper fixture must not mutate original deck');
assert(/^torneos_yugioh\/tests\/[a-z0-9_]+_test\.cjs$/.test(fixture.source));
const old=spawnSync(process.execPath,[path.join(root,fixture.source)],{
  cwd:root,encoding:'utf8',maxBuffer:8*1024*1024
});
if(old.status!==0){
  process.stderr.write(old.stdout||'');
  process.stderr.write(old.stderr||'');
  throw Error('Historical route replay failed: '+fixture.source);
}
console.log(JSON.stringify({ok:true,deck_id:fixture.deck_id,
  declarativeRoutesPassed:output.passed,
  declarativeTransitions:fixture.routes.reduce((n,r)=>n+r.steps.length,0),
  historicalReplayAlsoPassed:true,sourceDeckUnmodified:true,
  analysesExecuted:0}));
