/* Staged, bounded chain replay. Costs are paid in CL order; verified effects
   resolve in reverse order. Opponent negations remain conditional declarations. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"), require("./rules.js"), require("./chain-policy.js"), require("./integrity.js"), require("./diagnostics.js"));
  else root.YgoPlayChain = factory(root.YgoPlayEngine, root.YgoPlayRules, root.YgoCalculatorChainPolicy, root.YgoCalculatorIntegrity, root.YgoCalculatorDiagnostics);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, R, Policy, Integrity, Diagnostics) {
  "use strict";
  function resolveCore(s, db) {
    E.validate(s);
    Integrity.validate(s);
    const links = s.chain || [];
    const blocked = (reason) => ({ status: "unresolved", reason, branches: [] });
    if (!links.length || links.length > Policy.MAX_LINKS)
      return blocked("La cadena requiere entre uno y veinte eslabones.");
    const start = E.clone(s);
    start.chain = [];
    let paths = [{ state: start, pending: [], negations: [] }];
    let conditional = false;
    for (const [index, link] of links.entries()) {
      if (link.side === "opponent") {
        if (!paths.every(path => Policy.responseAllowed(link, links[index - 1], path.pending.length)))
          return blocked(`CL${index + 1}: respuesta rival sin negación acreditada o sin activación previa.`);
        conditional = true;
        paths = paths.map((path) => ({ ...path,
          negations: [...path.negations, { target: path.pending.length - 1, outcome: link.outcome }],
        }));
        continue;
      }
      if (!link.ruleId || !Number.isInteger(link.actorUid))
        return blocked(`CL${index + 1}: falta regla ejecutable o copia identificada.`);
      const rule = (db.rules || []).find((r) => r.id === link.ruleId &&
        r.deckId === s.self.id && r.format === s.format && r.activation);
      if (!rule) return blocked(`CL${index + 1}: efecto no acreditado.`);
      try { R.validate(rule); } catch (e) { return blocked(e.message); }
      if (rule.noChainReplay)
        return blocked("TY-PHON elige el monstruo al resolver, no al activar; no se certifica este replay de cadena.");
      if (!Policy.canChainRule(index, rule))
        return blocked(`CL${index + 1}: no se acreditó velocidad 2 para encadenar este efecto.`);
      paths = paths.flatMap((path) => {
        const actor = path.state.tokens.find((t) => t.uid === link.actorUid &&
          t.controller === "self" && db.bindings[t.key] === rule.actor.cid);
        if (!actor) return [];
        const prepared = R.actions(path.state, db, { deferResolution: true });
        return prepared.actions.filter((a) => a.event.ruleId === rule.id &&
          a.event.actor === actor.uid && a.prepared).map((a) => ({
          state: a.state, pending: [...path.pending, a.prepared], negations: path.negations,
        }));
      }).slice(0, 40);
      if (!paths.length)
        return blocked(`CL${index + 1}: no se pudieron pagar costes o acreditar objetivos y activación.`);
    }
    const branches = [];
    for (const path of paths) {
      let candidates = [{ state: path.state, labels: [], paid: null, effectNegated: false }];
      for (const i of Policy.resolutionOrder(path.pending.length)) {
        const prepared = path.pending[i];
        const response = path.negations.find((n) => n.target === i);
        const options = { prepared, ...Policy.negationOptions(response, prepared.ruleId) };
        candidates = candidates.flatMap((current) => {
          const rule = (db.rules || []).find((r) => r.id === prepared.ruleId);
          const state = response?.outcome === "negate-activation" && rule?.activationNegatedConsumesUse === false
            ? { ...current.state, _used: (current.state._used || []).filter((id) =>
              id !== "rule:" + prepared.ruleId && id !== "rule:" + prepared.ruleId + ":turn:" + current.state.turn),
              _ruleUseTurns: Object.fromEntries(Object.entries(current.state._ruleUseTurns || {})
                .filter(([id]) => id !== "rule:" + prepared.ruleId)) }
            : current.state;
          return R.actions(state, db, options).actions.filter((a) =>
            a.event.ruleId === prepared.ruleId && a.event.actor === prepared.refs.actor)
            .map((a) => ({ state: a.state, labels: [...current.labels, a.label],
              paid: a.paid, effectNegated: current.effectNegated || a.event.effectNegated || a.event.activationNegated }));
        }).slice(0, 40);
        if (!candidates.length) break;
      }
      for (const candidate of candidates) branches.push({
        label: candidate.labels.join(" ← "), state: { ...candidate.state, chain: [] },
        paid: candidate.paid, effectNegated: candidate.effectNegated,
      });
      if (branches.length >= 40) break;
    }
    if (!branches.length)
      return blocked("La resolución inversa no está acreditada con esos objetivos, costes y negaciones.");
    return { status: "conditional",
      warning: conditional
        ? "Respuestas rivales declaradas por el jugador: la carta, sus costes y su ventana no están certificados."
        : "Se verificaron costes y efectos propios; las ventanas de respuesta rivales no están certificadas.",
      branches: branches.slice(0, 40) };
  }
  function resolve(s, db) {
    const result = resolveCore(s, db);
    return { ...result, assessment: Diagnostics.chain(result) };
  }
  return { resolve };
});
