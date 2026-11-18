/* Conditional replay of one verified activation and an explicitly declared negation.
   Unknown chain links fail closed. This is not an opponent-card rules engine. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"), require("./rules.js"));
  else root.YgoPlayChain = factory(root.YgoPlayEngine, root.YgoPlayRules);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, R) {
  "use strict";
  function resolve(s, db) {
    E.validate(s);
    const links = s.chain || [];
    const blocked = (reason) => ({ status: "unresolved", reason, branches: [] });
    if (!links.length || links.length > 2)
      return blocked("Sólo se ejecutan cadenas de uno o dos eslabones acreditados.");
    const first = links[0];
    if (first.side !== "self" || !first.ruleId || !Number.isInteger(first.actorUid))
      return blocked("CL1 necesita una regla ejecutable y una copia propia identificada.");
    const rule = (db.rules || []).find((r) => r.id === first.ruleId &&
      r.deckId === s.self.id && r.format === s.format && r.activation);
    if (!rule) return blocked("La activación de CL1 no tiene una regla ejecutable acreditada.");
    try { R.validate(rule); } catch (e) { return blocked(e.message); }
    const actor = s.tokens.find((t) => t.uid === first.actorUid && t.controller === "self" &&
      db.bindings[t.key] === rule.actor.cid);
    if (!actor) return blocked("La copia de CL1 no coincide con el efecto declarado.");
    let options = {}, warning = "Solo se verificó el efecto propio; las ventanas de respuesta ajenas no están certificadas.";
    if (links.length === 2) {
      const response = links[1];
      if (response.side !== "opponent" || !["negate-effect", "negate-activation"].includes(response.outcome))
        return blocked("CL2 exige una negación concreta declarada por el jugador.");
      options = response.outcome === "negate-effect"
        ? { negateEffectRuleIds: [rule.id] }
        : { negateActivationRuleIds: [rule.id] };
      warning = "Negación de CL2 declarada por el jugador: la carta rival, sus costes y su ventana aún deben verificarse.";
    }
    const before = E.clone(s);
    before.chain = [];
    const result = R.actions(before, db, options);
    const branches = result.actions.filter((a) => a.event.ruleId === rule.id &&
      a.event.actor === actor.uid).map((a) => ({
      label: a.label, state: { ...a.state, chain: [] }, paid: a.paid,
      effectNegated: a.event.effectNegated, ruleId: rule.id,
    }));
    if (!branches.length)
      return blocked("No hay resolución legal acreditada para esta copia, sus costes y la respuesta declarada.");
    return { status: "conditional", warning, branches };
  }
  return { resolve };
});
