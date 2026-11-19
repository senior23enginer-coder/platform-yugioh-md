/** Standalone contract gate; no real deck delivery, production or prompts repo required. */
const assert = require("node:assert/strict");
const { spawnSync } = require("node:child_process");
const fs = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "..");
const moduleRoot = "torneos_yugioh/assets/modules/play-calculator/";
const html = fs.readFileSync(path.join(root, "torneos_yugioh/play-calculator.html"), "utf8");
const worker = fs.readFileSync(path.join(root, moduleRoot, "planner-worker.js"), "utf8");
const browserModules = [...html.matchAll(/<script\b[^>]*\bsrc="([^"]+)"/g)]
  .map(match => match[1].split("?")[0].split("/").pop());
const workerModules = [...worker.matchAll(/"\.\/([\w-]+\.js)(?:\?[^"\n]*)?"/g)]
  .map(match => match[1]);
function ordered(modules, before, after, context) {
  const first = modules.indexOf(before), last = modules.indexOf(after);
  assert(first !== -1 && last !== -1 && first < last,
    `${context}: ${before} debe cargarse antes de ${after}`);
}
for (const module of ["operations", "guards", "resources", "transitions", "timing",
  "events", "restrictions", "integrity", "diagnostics"]) {
  ordered(browserModules, `${module}.js`, "rules.js", "Navegador");
  ordered(workerModules, `${module}.js`, "rules.js", "Worker");
}
ordered(browserModules, "rules.js", "contract.js", "Navegador");
ordered(browserModules, "contract.js", "study.js", "Navegador");
ordered(browserModules, "verified-rules.js", "study.js", "Navegador");
ordered(browserModules, "study.js", "ui.js", "Navegador");
ordered(workerModules, "rules.js", "planner.js", "Worker");

const tests = [
  "calculator_operations_registry_test.cjs",
  "calculator_guards_registry_test.cjs",
  "calculator_resources_test.cjs",
  "calculator_transitions_test.cjs",
  "calculator_timing_test.cjs",
  "calculator_events_test.cjs",
  "calculator_chain_policy_test.cjs",
  "calculator_restrictions_test.cjs",
  "calculator_integrity_test.cjs",
  "calculator_diagnostics_test.cjs",
  "calculator_rule_operations_test.cjs",
  "calculator_contract_test.cjs",
  "calculator_cross_deck_matrix_test.cjs",
  "calculator_study_binding_contract_test.cjs",
];
for (const name of tests) {
  const result = spawnSync(process.execPath, [`torneos_yugioh/tests/${name}`],
    { cwd: root, encoding: "utf8" });
  if (result.status !== 0) {
    process.stderr.write(result.stdout || "");
    process.stderr.write(result.stderr || "");
    throw Error(`Falló ${name}: ${result.error?.message || result.status}`);
  }
  console.log(`OK ${name}`);
}
console.log(JSON.stringify({ ok: true, contractGate: "station-x-calculator/1",
  tests: tests.length, browserOrder: true, workerOrder: true,
  scope: "synthetic-only", realDecksCertified: false }));
