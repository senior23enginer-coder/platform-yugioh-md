/* Static local worker: deterministic rules and bounded response branches, no AI service. */
importScripts(
  "./engine.js?v=20261116-actions-1",
  "./operations.js?v=20261118-registry-1",
  "./guards.js?v=20261118-guards-1",
  "./resources.js?v=20261118-resources-1",
  "./transitions.js?v=20261118-transitions-1",
  "./timing.js?v=20261118-timing-1",
  "./events.js?v=20261118-events-1",
  "./restrictions.js?v=20261120-machine-synchro-1",
  "./integrity.js?v=20261118-integrity-1",
  "./diagnostics.js?v=20261118-diagnostics-1",
  "./rules.js?v=20261120-smiger-rule-1",
  "./planner.js?v=20261120-portable-horus-1",
  "./battle.js?v=20261118-battle-1",
  "./scenario.js?v=20261118-scenario-1",
);
self.onmessage = (event) => {
  const { id, scenario, facts, options } = event.data;
  try {
    self.postMessage({
      id,
      result: self.YgoScenarioAnalysis.solve(scenario, facts, options),
    });
  } catch (error) {
    self.postMessage({ id, error: error.message });
  }
};
