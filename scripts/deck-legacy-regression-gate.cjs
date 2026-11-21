'use strict';
// Migration gate P06: one shared legacy suite and immutable historical
// inventory. NO deck is selected or analyzed by the default --check mode.
// Old tests are retained until every assertion has an equivalent paper fixture.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const directory=path.join(__dirname,'../torneos_yugioh/tests');
const protectedLegacy=[
  "calculator_crystron_smiger_test.cjs",
  "calculator_crystron_thystvern_field_test.cjs",
  "calculator_crystron_thystvern_test.cjs",
  "calculator_horus_sarcophagus_four_uses_test.cjs",
  "calculator_horus_state_test.cjs",
  "calculator_portable_horus_test.cjs",
  "calculator_portable_memento_import_test.php",
  "calculator_portable_memento_planner_test.cjs",
  "calculator_portable_memento_test.cjs",
  "memento_a07_coverage_test.cjs",
  "memento_a07_r12_r24_test.cjs",
  "memento_a07_remaining_rules_test.cjs",
  "memento_a07_resolution_test.cjs",
  "memento_aero_shark_damage_test.cjs",
  "memento_c01_route_replay_test.cjs",
  "memento_c02_route_replay_test.cjs",
  "memento_c03_route_replay_test.cjs",
  "memento_c04_route_replay_test.cjs",
  "memento_c05_route_replay_test.cjs",
  "memento_c06_route_replay_test.cjs",
  "memento_c07_route_replay_test.cjs",
  "memento_c08_route_replay_test.cjs",
  "memento_c09_route_replay_test.cjs",
  "memento_c10_route_replay_test.cjs",
  "memento_c11_route_replay_test.cjs",
  "memento_c12_route_replay_test.cjs",
  "memento_droll_observed_add_test.cjs",
  "memento_goblin_alternate_target_test.cjs",
  "memento_goblin_biker_breakout_test.cjs",
  "memento_goblin_ghattic_chain_test.cjs",
  "memento_gordian_slicer_test.cjs",
  "memento_heat_wave_test.cjs",
  "memento_lancea_turn_lock_test.cjs",
  "memento_m01_acceptance_audit_test.cjs",
  "memento_planner_goblin_ghattic_test.cjs",
  "memento_princess_trigger_draw_test.cjs",
  "memento_sp_link_extra_test.cjs",
  "memento_typhon_detach_test.cjs",
  "memento_verified_rule_integrity_test.cjs",
  "study_crystron_visual_ui_test.cjs"
];
const discovered=fs.readdirSync(directory).filter(f=>
  /(memento|crystron|cristron|horus)/i.test(f) &&
  /_test\.(?:cjs|php)$/.test(f)).sort();
assert.deepEqual(discovered,protectedLegacy,
  'Memento/Crystron legacy regression inventory changed: review migration parity before adding/removing per-deck tests');
const countByCategory={routes:0,effectAndEngine:0,presentation:0};
for(const filename of protectedLegacy){
  assert(fs.statSync(path.join(directory,filename)).size>0,'Missing covered legacy assertion '+filename);
  if(/memento_c\d{2}_route/.test(filename))countByCategory.routes++;
  else if(/study_crystron_visual/.test(filename))countByCategory.presentation++;
  else countByCategory.effectAndEngine++;
}
let executed=0;
if(process.argv.includes('--run')){
  for(const filename of protectedLegacy){
    const executable=filename.endsWith('.php')?(process.env.PHP_BINARY||'php'):process.execPath;
    const result=spawnSync(executable,[path.join(directory,filename)],{
      cwd:path.resolve(__dirname,'..'),encoding:'utf8',maxBuffer:8*1024*1024
    });
    if(result.status!==0){
      process.stderr.write(result.stdout||'');
      process.stderr.write(result.stderr||'');
      throw Error('Legacy regression failed: '+filename);
    }
    executed++;
  }
}
// The new generic A10B interpreter lives in scripts/run-deck-logic-fixtures.cjs.
// This inventory is a safety gate, NOT a claim that 40 cases were migrated.
// When paper fixtures achieve equivalent verified coverage, update this
// inventory in the same PR that removes redundant legacy files.
console.log(JSON.stringify({ok:true,legacyProtected:protectedLegacy.length,
  countByCategory,executed,fixtureEngine:'scripts/run-deck-logic-fixtures.cjs',
  migratedToPaperFixtures:0,analysesInitiated:0}));
