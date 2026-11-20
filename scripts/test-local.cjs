const { spawnSync } = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
const python = process.env.PYTHON_BINARY || 'python';
function run(command, args) {
  console.log(`\n${path.basename(command)} ${args.join(' ')}`);
  const result = spawnSync(command, args, { cwd: root, stdio: 'inherit' });
  if (result.error) console.error(result.error.message);
  if (result.status !== 0) process.exit(result.status || 1);
}
run(process.execPath, ['scripts/format-architecture.cjs', '--check']);
run(process.execPath, ['scripts/build-frontend.cjs', '--check']);
// Explicitly local checks only; production/browser scenarios are opt-in.
for (const name of ['source_architecture', 'domain_extractions', 'form_command_architecture', 'legacy_function_contract', 'calculator_import', 'calculator_portable_memento_import', 'event_admission', 'event_business_rules', 'event_contracts',
  'combo_creator', 'combo_ports', 'deck_attributes', 'deck_worlds_application', 'deck_worlds_persistence', 'reports_removed', 'deck_public_metadata', 'deck_metadata_architecture', 'event_model_sequence', 'event_recovery', 'event_teams', 'event_workflow',
  'free_for_all', 'schedule_capacity_policy', 'calendar_architecture', 'news_architecture', 'card_catalog_architecture', 'decklist_architecture', 'simple_pdf',
  'statistics_multiplayer', 'statistics_v8', 'turn_monotonic']) {
  run(php, [`torneos_yugioh/tests/${name}_test.php`]);
}
for (const name of ['best_of_rule_regression_test.js', 'event_model_parity_test.cjs',
  'event_admission_parity_test.cjs', 'play_calculator_engine_test.cjs', 'hand_practice_test.cjs', 'deck_attributes_model_test.cjs', 'deck_worlds_api_test.cjs', 'deck_league_position_test.cjs', 'global_ranking_scope_test.cjs', 'profile_global_rank_test.cjs', 'statistics_live_test.cjs', 'planner_test.cjs', 'scenario_analysis_test.cjs', 'calculator_battle_test.cjs', 'calculator_rule_operations_test.cjs', 'calculator_operations_registry_test.cjs', 'calculator_guards_registry_test.cjs', 'calculator_resources_test.cjs', 'calculator_transitions_test.cjs', 'calculator_timing_test.cjs', 'calculator_events_test.cjs', 'calculator_chain_policy_test.cjs', 'calculator_restrictions_test.cjs', 'calculator_integrity_test.cjs', 'calculator_diagnostics_test.cjs', 'calculator_cross_deck_matrix_test.cjs', 'calculator_study_binding_contract_test.cjs', 'calculator_contract_test.cjs', 'calculator_portable_variant_preservation_test.cjs', 'calculator_portable_memento_planner_test.cjs', 'calculator_verified_rules_test.cjs', 'calculator_causal_copy_test.cjs', 'calculator_coverage_labels_test.cjs', 'memento_goblin_alternate_target_test.cjs', 'memento_goblin_ghattic_chain_test.cjs', 'memento_lancea_turn_lock_test.cjs', 'memento_droll_observed_add_test.cjs', 'memento_heat_wave_test.cjs', 'memento_typhon_detach_test.cjs', 'memento_gordian_slicer_test.cjs', 'memento_aero_shark_damage_test.cjs', 'memento_planner_goblin_ghattic_test.cjs', 'memento_verified_rule_integrity_test.cjs', 'calculator_strategy_test.cjs', 'calculator_chain_capture_test.cjs', 'calculator_chain_resolution_test.cjs', 'step_lesson_test.cjs']) {
  run(process.execPath, [`torneos_yugioh/tests/${name}`]);
}
run(php, ['torneos_yugioh/tests/statistics_no_cuts_test.php']);
run(php, ['torneos_yugioh/tests/ranking_two_matches_test.php']);
run(process.execPath, ['torneos_yugioh/tests/combo_creator_study_photos_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/study_presentation_contract_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/study_presentation_ui_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/navigation_history_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/deck_data_import_ux_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_fullscreen_image_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_stages_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_deck_combobox_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_horus_state_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_deck_coverage_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_active_coverage_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_portable_horus_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_portable_memento_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_crystron_smiger_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_crystron_thystvern_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/calculator_browser_bootstrap_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/documented_combo_test.cjs']);
run(process.execPath, ['torneos_yugioh/tests/combo_source_test.cjs']);
run(python, ['-m', 'unittest', 'discover', '-s', 'torneos_yugioh/tests', '-p', 'editorial_sync_test.py']);
if (process.env.RUN_EDITORIAL_SOURCE_TESTS === '1') {
  run(python, ['-m', 'unittest', 'discover', '-s', 'torneos_yugioh/tests', '-p', 'deck_library_test.py']);
} else {
  console.log('Skipped historical library build: GPT research inputs belong to the independent repository; RUN_EDITORIAL_SOURCE_TESTS=1 requires external source deliveries.');
}
run(python, ['-m', 'unittest', 'discover', '-s', 'torneos_yugioh/tests', '-p', 'asset_paths_test.py']);
if (fs.existsSync(path.join(root, 'outputs/ygo-import-11-decks-20261101/backend-test.php'))) {
  run(php, ['outputs/ygo-import-11-decks-20261101/backend-test.php']);
}
console.log('\nLocal checks passed. Production was not accessed.');
