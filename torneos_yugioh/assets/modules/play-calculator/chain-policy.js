/* Conservative, deck-independent chain ordering and response policy. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorChainPolicy = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const MAX_LINKS = 20;
  const outcomes = Object.freeze(["negate-effect", "negate-activation"]);
  function responseAllowed(link, previous, pendingCount) {
    return link?.side === "opponent" && outcomes.includes(link.outcome) &&
      previous?.side !== "opponent" && pendingCount > 0;
  }
  function canChainRule(index, rule) {
    return index === 0 || rule?.chainSpeed === 2;
  }
  function resolutionOrder(count) {
    if (!Number.isInteger(count) || count < 0 || count > MAX_LINKS)
      throw Error("Cantidad de eslabones incorrecta");
    return Array.from({ length: count }, (_, i) => count - i - 1);
  }
  function negationOptions(response, ruleId) {
    if (!response) return {};
    if (!outcomes.includes(response.outcome) || typeof ruleId !== "string" || !ruleId)
      throw Error("Negación sin efecto identificado");
    return response.outcome === "negate-effect"
      ? { negateEffectRuleIds: [ruleId] }
      : { negateActivationRuleIds: [ruleId] };
  }
  return Object.freeze({ version: 1, MAX_LINKS, outcomes, responseAllowed,
    canChainRule, resolutionOrder, negationOptions });
});
