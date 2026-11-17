/* Static local worker: no requests to AI or rules services. */
importScripts(
  "./engine.js?v=20261116-actions-1",
  "./planner.js?v=20261116-actions-1",
);
self.onmessage = (event) => {
  const { id, scenario, facts, options } = event.data;
  try {
    self.postMessage({
      id,
      result: self.YgoPlayPlanner.solve(scenario, facts, options),
    });
  } catch (error) {
    self.postMessage({ id, error: error.message });
  }
};
