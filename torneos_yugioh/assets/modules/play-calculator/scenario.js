/* Scenario analysis and bounded, explicit single-response branches. No hidden cards are fabricated. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(
      require("./engine.js"),
      require("./planner.js"),
      require("./rules.js"),
    );
  else
    root.YgoScenarioAnalysis = factory(
      root.YgoPlayEngine,
      root.YgoPlayPlanner,
      root.YgoPlayRules,
    );
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, P, R) {
  "use strict";
  const official = (cid) =>
    "https://www.db.yugioh-card.com/yugiohdb/card_search.action?ope=2&cid=" +
    cid +
    "&request_locale=en";
  const reactions = {
    12950: "Ash Blossom & Joyous Spring",
    13631: "Infinite Impermanence",
    9279: "Droll & Lock Bird",
    14741: "Nibiru, the Primal Being",
  };
  const field = (z) => E.isMonster(z) || /^s[1-5]$/.test(z) || z === "field";
  const cid = (db, t) => db.identities?.[t.key]?.cid || db.bindings[t.key];
  function audit(s, db) {
    const rules = (db.rules || []).filter(
        (r) => r.deckId === s.self.id && r.format === s.format,
      ),
      valid = [],
      invalid = [];
    for (const r of rules) {
      try {
        R.validate(r);
        valid.push(r);
      } catch (e) {
        invalid.push({ id: r.id, message: e.message });
      }
    }
    const sides = ["self", "opponent"].map((side) => {
      const catalog = s[side],
        cards = catalog.cards.map((c) => {
          const p = P.profile(c, db),
            id = db.identities?.[c.key]?.cid || db.bindings[c.key];
          const scope =
            s.format === "tcg_advanced" && id === 14741
              ? "response-pending"
              : s.format === "tcg_advanced" && reactions[id]
                ? "response"
                : s.format !== "rush_duel" && P.modelled.includes(p?.cid)
                  ? "partial"
                  : valid.some((r) => r.actor.cid === id)
                    ? "declared"
                    : "missing";
          return {
            key: c.key,
            name: c.name,
            cid: id || null,
            scope,
            positioned: s.tokens.filter(
              (t) => t.key === c.key && t.owner === side,
            ).length,
          };
        });
      return {
        side,
        name: catalog.name,
        id: catalog.id,
        cards,
        modelled: cards.filter((c) =>
          ["partial", "response", "declared"].includes(c.scope),
        ).length,
        total: cards.length,
      };
    });
    return {
      complete: false,
      sides,
      knownResponses: s.tokens
        .filter(
          (t) =>
            t.owner === "opponent" &&
            t.zone === "hand" &&
            reactions[cid(db, t)],
        )
        .map((t) => ({
          cid: cid(db, t),
          name: E.definition(s, t.key, t.owner)?.name,
        })),
      invalidRules: invalid,
      knownOpponent: s.tokens
        .filter(
          (t) =>
            t.controller === "opponent" &&
            t.key !== "unknown" &&
            ["hand", "graveyard", "banished"].includes(t.zone),
        )
        .map((t) => ({
          uid: t.uid,
          name: E.definition(s, t.key, t.owner)?.name,
          zone: t.zone,
        })),
      unresolvedField: s.tokens
        .filter(
          (t) =>
            field(t.zone) &&
            t.position !== "set" &&
            !E.negated(s, t) &&
            !P.modelled.includes(
              P.profile(E.definition(s, t.key, t.owner), db)?.cid,
            ),
        )
        .map((t) => ({
          uid: t.uid,
          name: E.definition(s, t.key, t.owner)?.name || "Carta desconocida",
        })),
      hidden:
        s.unknown.hand +
        s.unknown.banished +
        s.tokens.filter(
          (t) =>
            t.key === "unknown" ||
            (t.controller === "opponent" && t.position === "set"),
        ).length,
      limits: [
        "El análisis es parcial: los efectos continuos, protecciones y disparos sin modelar pueden cambiar cada resultado.",
        "Las ramas calculan una respuesta conocida por vez. No resuelven cadenas arbitrarias ni varias interrupciones consecutivas.",
        "Las cartas desconocidas se conservan como incertidumbre; no se suponen respuestas de cartas que solo están en la lista.",
      ],
    };
  }
  function branches(input, plan, db, options = {}) {
    if (input.format !== "tcg_advanced") return [];
    const out = [];
    for (let i = 0; i < plan.steps.length; i++) {
      if (out.length >= Math.max(1, options.responseBudget || 12)) return out;
      const step = plan.steps[i],
        event = step.event;
      if (!event?.activation || !step.paid) continue;
      const before = { ...E.clone(input), ...E.clone(step.before) },
        after = { ...E.clone(input), ...E.clone(step.afterState) },
        paid = { ...E.clone(input), ...E.clone(step.paid) };
      for (const response of before.tokens.filter(
        (t) =>
          t.controller === "opponent" &&
          t.owner === "opponent" &&
          t.zone === "hand" &&
          reactions[cid(db, t)],
      )) {
        if (out.length >= Math.max(1, options.responseBudget || 12)) return out;
        const id = cid(db, response),
          name = E.definition(input, response.key, response.owner).name;
        if (
          [12950, 14741].includes(id) &&
          (before._used || []).includes("response:opponent:" + id)
        )
          continue;
        const row = {
          step: i + 1,
          card: name,
          cid: id,
          source: official(id),
          status: "conditional",
          chain: [],
          limits: [
            "Supone que no hay otra respuesta ni protección adicional sin modelar.",
          ],
          continuations: [],
        };
        let state;
        if (options.graveyardBlocked) {
          out.push({
            ...row,
            status: "unresolved",
            reason:
              "No se puede completar el estado tras esta respuesta sin conocer cómo afecta el bloqueo o reemplazo de Cementerio a las cartas de la rama.",
          });
          continue;
        }
        if (id === 12950) {
          if (
            !event.tags.some((t) =>
              ["addFromDeck", "summonFromDeck", "sendFromDeck"].includes(t),
            ) ||
            (before._used || []).includes("opponent:12950")
          )
            continue;
          state = paid;
          row.reason =
            "La búsqueda, envío o Invocación desde Deck de este efecto no resuelve; los costes y usos consumidos permanecen.";
          row.chain = [
            "CL1: " + step.label,
            "CL2: descartar " + name + "; negar ese efecto",
            "Resolver CL2 y después CL1 negado",
          ];
          state._used = [...(state._used || []), "opponent:12950"];
        } else if (id === 13631) {
          const target = paid.tokens.find(
            (t) =>
              t.uid === event.actor &&
              t.controller === "self" &&
              E.isMonster(t.zone) &&
              t.position !== "set",
          );
          if (
            !event.tags.includes("monsterEffect") ||
            !target ||
            before.tokens.some(
              (t) => t.controller === "opponent" && field(t.zone),
            )
          )
            continue;
          state = paid;
          target.negation = {
            active: true,
            duration: "turn",
            reason: name,
            turn: input.turn,
          };
          row.reason =
            "Desde mano, con campo rival vacío: niega los efectos del monstruo objetivo hasta terminar el turno. No aplica la negación de columna de una copia previamente colocada.";
          row.chain = [
            "CL1: " + step.label,
            "CL2: " + name + " selecciona al monstruo que activa",
            "Resolver CL2 y CL1 con sus efectos negados",
          ];
        } else if (id === 9279) {
          if (!event.tags.includes("addFromDeck") || input.phase === "draw")
            continue;
          state = after;
          row.reason =
            "Se conserva la primera adición ya resuelta. Droll se activa en una cadena posterior y bloquea nuevas adiciones del Main a la mano durante este turno.";
          row.chain = [
            "Resuelve: " + step.label,
            "Cadena nueva: enviar " + name + " de la mano al Cementerio",
            "Resolver: no añadir del Main a la mano",
          ];
          state._ruleFlags = { ...state._ruleFlags, noDeckToHand: true };
        } else {
          out.push({
            ...row,
            status: "unresolved",
            reason:
              "Nibiru necesita el total de monstruos Invocados este turno, permisos para Sacrificar y resolución de la Ficha. Esos datos no se deducen del número de monstruos presentes.",
            summonedThisTurn: Number.isInteger(options.summonedThisTurn)
              ? options.summonedThisTurn
              : null,
          });
          continue;
        }
        const t = state.tokens.find(
          (t) => t.uid === response.uid && t.zone === "hand",
        );
        if (!t) continue;
        t.zone = "graveyard";
        t.position = "attack";
        state.confirmed = [];
        try {
          E.validate(state);
        } catch (e) {
          out.push({ ...row, status: "unresolved", reason: e.message });
          continue;
        }
        row.state = state.tokens.map((t) => ({
          ...t,
          name:
            E.definition(state, t.key, t.owner)?.name || "Carta desconocida",
        }));
        row.lp = state.lp;
        row.normalAvailable = state.normalAvailable;
        const remaining = {
          ...options,
          used: [...new Set([...(options.used || []), ...(state._used || [])])],
          noDeckToHand: !!state._ruleFlags?.noDeckToHand,
          depth: 3,
          width: 12,
          budget: 180,
        };
        const next = P.solve(state, db, remaining);
        row.continuations = next.plans
          .slice(0, 2)
          .map((p) => ({ steps: p.steps, final: p.final }));
        row.continuationReason =
          next.reason ||
          (!row.continuations.length
            ? "No hay continuación demostrada con las acciones implementadas."
            : "Continuaciones parciales sin una segunda respuesta rival.");
        out.push(row);
        if (out.length >= Math.max(1, options.responseBudget || 12)) return out;
      }
    }
    return out;
  }
  function solve(input, db, options = {}) {
    const result = P.solve(input, db, options);
    result.audit = audit(input, db);
    let budget = 12;
    for (const plan of result.plans) {
      plan.responses =
        budget > 0
          ? branches(input, plan, db, { ...options, responseBudget: budget })
          : [];
      budget -= plan.responses.length;
      plan.responsesLimited = budget <= 0;
    }
    return result;
  }
  return { solve, audit, branches };
});
