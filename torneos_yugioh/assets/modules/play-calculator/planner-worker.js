/* Static local worker: deterministic rules and bounded response branches, no AI service. */
importScripts(
  "./engine.js?v=20261116-actions-1",
  "./rules.js?v=20261118-block1-5",
  "./planner.js?v=20261118-block1-2",
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
