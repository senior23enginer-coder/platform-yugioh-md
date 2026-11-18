/* Declarative effect adapter. Data never becomes JavaScript; unknown operations fail closed. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"));
  else root.YgoPlayRules = factory(root.YgoPlayEngine);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E) {
  "use strict";
  const field = (z) => E.isMonster(z) || /^s[1-5]$/.test(z) || z === "field";
  const zones = [
    "hand",
    "deck",
    "extra",
    "graveyard",
    "banished",
    "field",
    "m1",
    "m2",
    "m3",
    "m4",
    "m5",
    "s1",
    "s2",
    "s3",
    "s4",
    "s5",
    "emz1",
    "emz2",
  ];
  const flags = [
    "specialBlocked",
    "noExtra",
    "xyzOnly",
    "graveyardBlocked",
    "effectsNegated",
    "noDeckToHand",
  ];
  const tags = [
    "addFromDeck",
    "summonFromDeck",
    "sendFromDeck",
    "monsterEffect",
  ];
  function check(ok, message) {
    if (!ok) throw Error(message);
  }
  function selector(q) {
    check(
      q &&
        ["self", "opponent"].includes(q.side) &&
        Array.isArray(q.zones) &&
        q.zones.length > 0 &&
        q.zones.every(
          (z) => zones.includes(z) || z === "monsters" || z === "field-all",
        ),
      "Selector incompleto",
    );
    check(
      q.cid === undefined || (Number.isInteger(q.cid) && q.cid > 0),
      "CID incorrecto",
    );
    check(
      q.faceUp === undefined || typeof q.faceUp === "boolean",
      "Posicion incorrecta",
    );
    check(q.key === undefined || typeof q.key === "string", "Clave incorrecta");
    check(
      Object.keys(q).every((k) =>
        ["side", "zones", "cid", "key", "faceUp", "notNegated"].includes(k),
      ),
      "Selector desconocido",
    );
  }
  function matches(s, t, q, db) {
    return (
      t.controller === q.side &&
      q.zones.some(
        (z) =>
          z === t.zone ||
          (z === "monsters" && E.isMonster(t.zone)) ||
          (z === "field-all" && field(t.zone)),
      ) &&
      (q.cid === undefined || db.bindings[t.key] === q.cid) &&
      (q.key === undefined || q.key === t.key) &&
      (q.faceUp === undefined || q.faceUp === (t.position !== "set")) &&
      (!q.notNegated || !E.negated(s, t))
    );
  }
  function candidates(s, q, db) {
    return s.tokens.filter((t) => matches(s, t, q, db));
  }
  function validate(rule) {
    check(
      rule &&
        rule.version === 1 &&
        typeof rule.id === "string" &&
        rule.id.length <= 100 &&
        Number.isInteger(rule.deckId),
      "Identidad de regla incompleta",
    );
    check(
      ["tcg_advanced", "goat", "rush_duel"].includes(rule.format),
      "Formato incorrecto",
    );
    check(
      typeof rule.source === "string" &&
        /^https:\/\/(?:www\.)?db\.yugioh-card\.com\//.test(rule.source),
      "Falta fuente oficial de efecto",
    );
    check(
      rule.scope === "single-effect" &&
        typeof rule.label === "string" &&
        rule.label.length <= 500,
      "Alcance incorrecto",
    );
    check(
      Array.isArray(rule.assumptions) && rule.assumptions.length > 0,
      "Faltan limites del modelo",
    );
    selector(rule.actor);
    check(
      rule.actor.side === "self",
      "El actor del efecto propio debe pertenecer a tu lado",
    );
    check(Number.isInteger(rule.actor.cid), "Actor sin CID");
    check(
      Array.isArray(rule.guards) &&
        rule.guards.length > 0 &&
        rule.guards.length <= 30,
      "Faltan condiciones",
    );
    for (const g of rule.guards) {
      if (g.kind === "count") {
        selector(g.selector);
        check(
          Number.isInteger(g.min) &&
            Number.isInteger(g.max) &&
            g.min >= 0 &&
            g.max >= g.min,
          "Conteo incorrecto",
        );
      } else if (g.kind === "state")
        check(
          ["active", "phase", "normalAvailable", "turn"].includes(g.field) &&
            Array.isArray(g.values) &&
            g.values.length > 0,
          "Condicion desconocida",
        );
      else if (g.kind === "flag")
        check(
          flags.includes(g.flag) && typeof g.value === "boolean",
          "Bloqueo desconocido",
        );
      else throw Error("Condicion no implementada");
    }
    check(
      Array.isArray(rule.select) && rule.select.length <= 4,
      "Selecciones incorrectas",
    );
    const refs = new Set(["actor"]);
    for (const x of rule.select) {
      check(
        /^[a-z][a-z0-9_]{0,30}$/.test(x.ref) && !refs.has(x.ref),
        "Referencia repetida",
      );
      refs.add(x.ref);
      selector(x.selector);
    }
    check(
      Array.isArray(rule.costs) &&
        Array.isArray(rule.resolve) &&
        rule.resolve.length > 0 &&
        rule.costs.length + rule.resolve.length <= 30,
      "Faltan coste o resolucion",
    );
    for (const op of [...rule.costs, ...rule.resolve]) {
      if (op.op === "move")
        check(
          refs.has(op.ref) &&
            zones.includes(op.to) &&
            ["attack", "defense", "set"].includes(op.position),
          "Movimiento incorrecto",
        );
      else if (op.op === "take")
        check(
          ["self", "opponent"].includes(op.side) &&
            Number.isInteger(op.cid) &&
            ["deck", "extra"].includes(op.from) &&
            zones.includes(op.to) &&
            ["attack", "defense", "set"].includes(op.position),
          "Recurso incorrecto",
        );
      else if (op.op === "payLP")
        check(
          Number.isInteger(op.amount) && op.amount > 0,
          "Coste LP incorrecto",
        );
      else if (op.op === "damage" || op.op === "recoverLP")
        check(
          ["self", "opponent"].includes(op.side) &&
            Number.isInteger(op.amount) && op.amount > 0 && op.amount <= 999999,
          "Modificacion de LP incorrecta",
        );
      else if (op.op === "negate")
        check(
          refs.has(op.ref) && ["turn", "manual"].includes(op.duration) &&
            typeof op.reason === "string" && op.reason.length <= 300,
          "Negacion incorrecta",
        );
      else if (op.op === "position")
        check(
          refs.has(op.ref) && ["attack", "defense", "set"].includes(op.position),
          "Cambio de posicion incorrecto",
        );
      else if (op.op === "flag")
        check(
          flags.includes(op.flag) && typeof op.value === "boolean",
          "Restriccion incorrecta",
        );
      else if (op.op === "normal")
        check(op.available === false, "Normal incorrecta");
      else throw Error("Operacion no implementada: " + op.op);
    }
    check(
      Array.isArray(rule.tags) &&
        rule.tags.every((t) => tags.includes(t)) &&
        typeof rule.activation === "boolean" &&
        typeof rule.oncePerTurn === "boolean",
      "Ventana o limite ausente",
    );
    return rule;
  }
  function checkpoint(s) {
    return {
      tokens: E.clone(s.tokens),
      nextId: s.nextId,
      lp: E.clone(s.lp),
      normalAvailable: !s._normal,
      _normal: s._normal,
      _xyz: s._xyz,
      _used: E.clone(s._used || []),
      _ruleFlags: E.clone(s._ruleFlags || {}),
    };
  }
  function actions(s, db, options = {}) {
    const actions = [],
      rejected = [];
    for (const rule of (db.rules || []).filter(
      (r) => r.deckId === s.self.id && r.format === s.format,
    )) {
      try {
        validate(rule);
      } catch (e) {
        rejected.push({ id: rule.id, message: e.message });
        continue;
      }
      const used = "rule:" + rule.id;
      if (rule.oncePerTurn && (s._used || []).includes(used)) continue;
      const f = { ...options, ...s._ruleFlags };
      if (rule.activation && f.effectsNegated) continue;
      if (
        !rule.guards.every((g) =>
          g.kind === "count"
            ? (() => {
                const n = candidates(s, g.selector, db).length;
                return n >= g.min && n <= g.max;
              })()
            : g.kind === "state"
              ? g.values.includes(
                  g.field === "normalAvailable" ? !s._normal : s[g.field],
                )
              : !!f[g.flag] === g.value,
        )
      )
        continue;
      let bindings = candidates(s, rule.actor, db).map((t) => ({
        actor: t.uid,
      }));
      for (const item of rule.select)
        bindings = bindings
          .flatMap((b) =>
            candidates(s, item.selector, db)
              .filter((t) => !Object.values(b).includes(t.uid))
              .map((t) => ({ ...b, [item.ref]: t.uid })),
          )
          .slice(0, 40);
      for (const refs of bindings.slice(0, 40)) {
        const actor = s.tokens.find((t) => t.uid === refs.actor);
        if (
          rule.activation &&
          field(actor.zone) &&
          (E.negated(s, actor) ||
            (E.isMonster(actor.zone) && actor.position === "set"))
        )
          continue;
        let n = E.clone(s);
        n._normal ??= !n.normalAvailable;
        function apply(op) {
          if (op.to) {
            check(
              !(options.blockedZones || []).includes(op.to),
              "Zona bloqueada",
            );
            check(
              !(f.graveyardBlocked && op.to === "graveyard"),
              "Destino de Cementerio pendiente por bloqueo",
            );
          }
          if (op.op === "move") {
            const t = n.tokens.find((t) => t.uid === refs[op.ref]);
            check(t, "Copia ausente");
            n = E.place(n, {
              uid: t.uid,
              zone: op.to,
              controller: ["graveyard", "banished", "deck", "extra"].includes(
                op.to,
              )
                ? t.owner
                : t.controller,
            });
            if (n.tokens.some((x) => x.uid === t.uid))
              n = E.position(n, t.uid, op.position);
          } else if (op.op === "take") {
            check(
              !(f.noDeckToHand && op.from === "deck" && op.to === "hand"),
              "La restriccion de añadir del Deck impide esta resolucion",
            );
            check(
              !(op.from === "extra" && (E.isMonster(op.to) || op.to === "hand")),
              "Invocacion o retorno desde Extra requiere un procedimiento de materiales acreditado",
            );
            const cards = n[op.side].cards.filter(
              (c) =>
                db.bindings[c.key] === op.cid &&
                c.section === (op.from === "extra" ? "extra" : "main"),
            );
            check(
              cards.length === 1,
              "Recurso no identificado univocamente en la lista",
            );
            const c = cards[0],
              t = n.tokens.find(
                (t) =>
                  t.owner === op.side && t.key === c.key && t.zone === op.from,
              );
            n = E.place(
              n,
              t
                ? { uid: t.uid, zone: op.to, controller: op.side }
                : {
                    key: c.key,
                    owner: op.side,
                    controller: op.side,
                    zone: op.to,
                  },
            );
            n = E.position(n, t ? t.uid : n.nextId - 1, op.position);
          } else if (op.op === "payLP") {
            check(n.lp.self > op.amount, "LP insuficientes");
            n.lp.self -= op.amount;
          } else if (op.op === "damage") {
            n.lp[op.side] = Math.max(0, n.lp[op.side] - op.amount);
          } else if (op.op === "recoverLP") {
            n.lp[op.side] += op.amount;
            check(n.lp[op.side] <= 999999, "LP exceden el limite de escenario");
          } else if (op.op === "negate") {
            const t = n.tokens.find((t) => t.uid === refs[op.ref]);
            check(t && field(t.zone) && t.position !== "set", "Objetivo de negacion no acreditado");
            t.negation = {
              active: true,
              duration: op.duration,
              reason: op.reason,
              turn: n.turn,
            };
          } else if (op.op === "position") {
            const t = n.tokens.find((t) => t.uid === refs[op.ref]);
            check(t && E.isMonster(t.zone), "Cambio de posicion necesita monstruo en campo");
            const fact = db.facts[db.bindings[t.key]];
            check(
              !(fact?.species?.includes("Link") && op.position !== "attack"),
              "Un monstruo Link no puede colocarse en Defensa",
            );
            t.position = op.position;
          } else if (op.op === "normal") {
            check(!n._normal, "Normal usada");
            n._normal = true;
          } else {
            n._ruleFlags = { ...n._ruleFlags, [op.flag]: op.value };
            if (op.flag === "xyzOnly") n._xyz = op.value;
          }
        }
        try {
          for (const op of rule.costs) apply(op);
          if (rule.oncePerTurn) n._used = [...(n._used || []), used];
          const paid = checkpoint(n);
          for (const op of rule.resolve) apply(op);
          const summoned = n.tokens.filter(
            (t) =>
              E.isMonster(t.zone) &&
              !s.tokens.some((b) => b.uid === t.uid && E.isMonster(b.zone)),
          );
          const normal = rule.costs
            .concat(rule.resolve)
            .some((op) => op.op === "normal");
          check(
            !(
              f.specialBlocked &&
              summoned.length &&
              !(
                normal &&
                summoned.length === 1 &&
                s.tokens.some(
                  (t) => t.uid === summoned[0].uid && t.zone === "hand",
                )
              )
            ),
            "Invocación Especial bloqueada",
          );
          const extra = summoned.filter(
            (t) => E.definition(n, t.key, t.owner)?.section === "extra",
          );
          check(!(f.noExtra && extra.length), "Extra bloqueado");
          check(
            !(
              f.xyzOnly &&
              extra.some(
                (t) => !/xyz/i.test(db.facts[db.bindings[t.key]]?.species || ""),
              )
            ),
            "Requisito Xyz no acreditado",
          );
          E.validate(n);
          actions.push({
            state: n,
            label: rule.label,
            ids: Object.values(refs),
            used: rule.oncePerTurn ? used : null,
            source: rule.source,
            paid,
            event: {
              activation: rule.activation,
              actor: refs.actor,
              tags: rule.tags,
              assumptions: rule.assumptions,
              ruleId: rule.id,
            },
          });
        } catch (e) {
          rejected.push({ id: rule.id, message: e.message });
        }
      }
    }
    return { actions, rejected };
  }
  return { validate, actions, checkpoint, matches };
});
