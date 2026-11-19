/* Honest capability assessment: simulated does not mean legally certified. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorDiagnostics = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const statuses = Object.freeze(["modelled", "conditional", "unresolved"]);
  const labels = Object.freeze({
    modelled: "Efecto modelado", conditional: "Resultado condicional",
    unresolved: "No acreditado",
  });
  function planner(result) {
    if (!result || !Array.isArray(result.plans)) throw Error("Resultado de búsqueda inválido");
    const count = result.plans.length;
    // Planner assumes no opponent response even if the local effects are implemented.
    const status = count ? "conditional" : "unresolved";
    return Object.freeze({ status, label: labels[status],
      modelledSteps: count > 0, opponentResponsesVerified: false,
      truncated: !!result.limited,
      unsupportedCards: Array.isArray(result.unsupported) ? result.unsupported.length : 0,
      explanation: count
        ? "Secuencias calculadas bajo supuestos; no certifican una jugada completa ni las respuestas del rival."
        : result.reason || "No hay una secuencia acreditada con el estado y las reglas disponibles." });
  }
  function chain(result) {
    if (!result || !["conditional", "unresolved"].includes(result.status) ||
      !Array.isArray(result.branches)) throw Error("Resultado de cadena inválido");
    return Object.freeze({ status: result.status, label: labels[result.status],
      modelledSteps: result.branches.length > 0,
      opponentResponsesVerified: false, truncated: result.branches.length >= 40,
      explanation: result.warning || result.reason || "Interacción sin evidencia suficiente." });
  }
  return Object.freeze({ version: 1, statuses, labels, planner, chain });
});
