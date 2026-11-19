(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"), require("./rules.js"), require("./restrictions.js"), require("./integrity.js"));
  else root.YgoPlayPlanner = factory(root.YgoPlayEngine, root.YgoPlayRules, root.YgoCalculatorRestrictions, root.YgoCalculatorIntegrity);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, R, Restrictions, Integrity) {
  "use strict";
  const effects = {
    17768: [["search", "Buscar un monstruo Kashtira"]],
    17769: [["search", "Buscar una Magia Kashtira"]],
    17770: [["search", "Buscar una Trampa Kashtira"]],
    18151: [["special", "Invocar Riseheart desde mano"]],
    18204: [["activate", "Activar Wraitsoth y buscar"]],
    17815: [["revive", "Recuperar un Kashtira con Birth"]],
    18203: [["summon", "Invocar con Kashtiratheosis"]],
    9861: [["control", "Desacoplar y tomar control con Big Eye"]],
  };
  const modelled = new Set([
    ...Object.keys(effects).map(Number),
    17800,
    4342,
    4678,
    5432,
    12464,
  ]);
  const clone = E.clone,
    monster = E.isMonster;
  const field = (t) =>
    monster(t.zone) || /^s[1-5]$/.test(t.zone) || t.zone === "field";
  function profile(card, db) {
    const fact = db.facts[db.bindings[card.key]];
    if (!fact) return null;
    const names = [fact.name, db.names?.[card.key]].filter(Boolean).map(E.norm);
    if (!names.includes(E.norm(card.name))) return null;
    if (
      card.imageId &&
      db.imageIds?.[card.key] &&
      card.imageId !== db.imageIds[card.key]
    )
      return null;
    return fact;
  }
  function capabilities(s, db) {
    const own = s.self.cards.flatMap((c) => {
      const p = profile(c, db);
      const builtIn = (effects[p?.cid] || []).map(([key, label]) => ({
        id: `${p.cid}:${key}`,
        cardKey: c.key,
        label: `${c.name}: ${label}`,
      }));
      const declared = (db.rules || [])
        .filter((r) => {
          try {
            R.validate(r);
            return (
              r.deckId === s.self.id &&
              r.format === s.format &&
              r.actor.cid ===
                (db.identities?.[c.key]?.cid || db.bindings[c.key])
            );
          } catch {
            return false;
          }
        })
        .map((r) => ({
          id: "rule:" + r.id,
          cardKey: c.key,
          label: c.name + ": " + r.label,
        }));
      return [...builtIn, ...declared];
    });
    const replies = {
      12950: "Ash Blossom & Joyous Spring",
      14741: "Nibiru, the Primal Being",
    };
    const opponent = (s.opponent?.cards || []).flatMap((c) => {
      const id = db.identities?.[c.key]?.cid || db.bindings[c.key];
      return s.format === "tcg_advanced" && replies[id]
        ? [
            {
              id: "response:opponent:" + id,
              cardKey: c.key,
              label: "Rival: " + c.name + " · efecto utilizado este turno",
            },
          ]
        : [];
    });
    // A rule can expose several legal target/zone variants, but its use-once
    // marker is shared. Show a single checkbox for that effect in the UI.
    const unique = new Map();
    for (const row of [...own, ...opponent])
      if (!unique.has(row.cardKey + ":" + row.id))
        unique.set(row.cardKey + ":" + row.id, row);
    return [...unique.values()];
  }
  function snapshot(s, db) {
    return s.tokens.map((t) => ({
      ...t,
      name: E.definition(s, t.key, t.owner)?.name || "Carta desconocida",
      cid: db.bindings[t.key] || null,
    }));
  }
  function solve(input, db, options = {}) {
    E.validate(input);
    Integrity.validate(input);
    const structured = (db.rules || []).filter((r) => {
      try {
        R.validate(r);
        return r.deckId === input.self.id && r.format === input.format;
      } catch {
        return false;
      }
    });
    const unsupported = input.self.cards
      .filter(
        (c) =>
          !modelled.has(profile(c, db)?.cid) &&
          !structured.some(
            (r) =>
              r.actor.cid ===
              (db.identities?.[c.key]?.cid || db.bindings[c.key]),
          ),
      )
      .map((c) => ({ name: c.name, normal: !!profile(c, db)?.normalAllowed }));
    const base = {
      plans: [],
      unsupported,
      explored: 0,
      limited: false,
      reason: "",
      assumptions: [
        "Líneas calculadas bajo el supuesto de que el rival no responde y no hay protecciones o efectos continuos adicionales sin modelar.",
        "No se interpreta texto libre, no se predice la mano oculta ni se certifica la legalidad integral. Los efectos no implementados se muestran aparte.",
        "La prioridad es una valoración de recursos y retirada rival, no probabilidad de ganar ni garantía de la mejor jugada.",
      ],
    };
    if (
      input.format === "rush_duel" &&
      !(db.rules || []).some(
        (r) => r.deckId === input.self.id && r.format === input.format,
      )
    )
      return {
        ...base,
        reason:
          "Los efectos Rush y la Skill local aún no están modelados; no se calculan secuencias TCG para este formato.",
      };
    if (input.active !== "self" || !["main1", "main2"].includes(input.phase))
      return {
        ...base,
        reason:
          "La búsqueda actual requiere tu Main Phase. Las ventanas de respuesta y combate no se simulan.",
      };
    if (input.lp.self <= 0 || input.lp.opponent <= 0)
      return {
        ...base,
        reason: "Revisa los LP: el duelo ya tiene un participante con 0 LP.",
      };
    if (input.chain?.length)
      return {
        ...base,
        reason: "Hay una cadena pendiente registrada. Resuelve sus eslabones y actualiza el tablero antes de calcular otra secuencia; el motor no inventa su resolución.",
      };
    if (input.usedEffects.trim())
      return {
        ...base,
        reason:
          "Hay restricciones en texto libre. Tras comprobarlas, usa los controles estructurados del motor; ese texto no se convierte en permisos automáticos.",
      };
    // Continuous replacements and locks cannot safely be ignored.
    const blockers = input.tokens.filter(
      (t) =>
        field(t) &&
        t.position !== "set" &&
        !E.negated(input, t) &&
        [18191].includes(db.bindings[t.key]),
    );
    if (blockers.length)
      return {
        ...base,
        reason:
          "Arise-Heart está boca arriba: su reemplazo de Cementerio y sus disparos aún requieren modelado. No se inventa un estado posterior.",
      };
    const root = clone(input);
    root._used = [
      ...new Set([
        ...(options.used || []),
        ...(input.effectUsage || [])
          .filter((e) => e.turn === input.turn && e.id)
          .map((e) => e.id),
      ]),
    ];
    root._normal = !root.normalAvailable;
    root._xyz = !!options.xyzOnly;
    root._steps = [];
    const blocked = new Set(options.blockedZones || []),
      width = Math.min(80, Math.max(1, Number(options.width) || 35)),
      depth = Math.min(8, Math.max(1, Number(options.depth) || 6));
    const limit = Math.min(6000, Math.max(1, Number(options.budget) || 1800));
    const definition = (s, t) => E.definition(s, t.key, t.owner),
      fact = (s, t) => profile(definition(s, t), db);
    const own = (s, pred) =>
      s.tokens.filter((t) => t.controller === "self" && pred(t));
    const mons = (s) => own(s, (t) => monster(t.zone)),
      faceMons = (s) => mons(s).filter((t) => t.position !== "set");
    const enemy = (s) =>
      s.tokens.filter((t) => t.controller === "opponent" && field(t));
    const free = (s, prefix) =>
      Array.from({ length: 5 }, (_, i) => prefix + (i + 1)).find(
        (z) =>
          !blocked.has(z) &&
          !s.tokens.some((t) => t.controller === "self" && t.zone === z),
      );
    const unused = (s, id) => !s._used.includes(id);
    const kashtira = (s, t) => fact(s, t)?.setcode === "Kashtira";
    const available = (s, c) =>
      E.remaining(s, c.key, "self") +
      s.tokens.filter(
        (t) =>
          t.owner === "self" &&
          t.key === c.key &&
          ["deck", "extra"].includes(t.zone),
      ).length;
    const deckCards = (s, test) =>
      s.self.cards.filter(
        (c) =>
          c.section === "main" &&
          available(s, c) > 0 &&
          test(profile(c, db), c),
      );
    function take(s, c, zone, position = "attack") {
      let t = s.tokens.find(
        (t) =>
          t.owner === "self" &&
          t.key === c.key &&
          (t.zone === "deck" || (c.section === "extra" && t.zone === "extra")),
      );
      if (t) {
        t.zone = zone;
        t.position = position;
        t.controller = "self";
      } else {
        t = {
          uid: s.nextId++,
          key: c.key,
          owner: "self",
          controller: "self",
          zone,
          position,
        };
        s.tokens.push(t);
      }
      return t;
    }
    function move(s, uid, zone, position) {
      const t = s.tokens.find((t) => t.uid === uid);
      if (!t) return;
      if (
        field(t) &&
        !(monster(zone) || /^s[1-5]$/.test(zone) || zone === "field")
      )
        delete t.negation;
      t.zone = zone;
      t.controller =
        zone === "graveyard" || zone === "banished" ? t.owner : t.controller;
      if (position) t.position = position;
      if (zone === "graveyard" || zone === "banished") {
        for (const mat of s.tokens.filter((m) => m.zone === `material:${uid}`))
          move(s, mat.uid, "graveyard", "attack");
      }
    }
    const combinations = (items, n) =>
      n === 0
        ? [[]]
        : items.flatMap((x, i) =>
            combinations(items.slice(i + 1), n - 1).map((xs) => [x, ...xs]),
          );
    const startingEnemy = enemy(root).length;
    const goals = {
      balanced: { removal: 150, field: 1, hand: 12, tempo: 2 },
      removal: { removal: 300, field: 0.65, hand: 8, tempo: 2 },
      board: { removal: 70, field: 1.8, hand: 6, tempo: 2 },
      resources: { removal: 60, field: 0.65, hand: 35, tempo: 5 },
      resilience: { removal: 110, field: 0.9, hand: 22, tempo: 3 },
    };
    const goal = Object.hasOwn(goals, options.goal) ? options.goal : "balanced";
    const weights = goals[goal];
    base.goal = goal;
    base.goalLabels = {
      balanced: "Equilibrar presencia de campo, remoción y recursos",
      removal: "Priorizar retirada de cartas rivales conocidas",
      board: "Priorizar desarrollo de monstruos propios",
      resources: "Conservar cartas en mano y reducir acciones",
      resilience: "Reducir exposición a las respuestas rivales conocidas y modeladas",
    };
    function metrics(s) {
      return {
        removed: startingEnemy - enemy(s).length,
        field: faceMons(s).reduce(
          (v, t) => v + (fact(s, t)?.rank ? 90 : 20) + (fact(s, t)?.atk || 0) / 200,
          0,
        ),
        hand: own(s, (t) => t.zone === "hand").length,
        actions: s._steps.length,
      };
    }
    function score(s) {
      const m = metrics(s);
      return m.removed * weights.removal + m.field * weights.field +
        m.hand * weights.hand - m.actions * weights.tempo +
        (s.lp.opponent === 0 && s.lp.self > 0 ? 10000 : 0);
    }
    function stateKey(s) {
      return JSON.stringify([
        s.tokens
          .map((t) => [
            t.key,
            t.owner,
            t.controller,
            t.zone,
            t.position,
            E.negated(s, t),
          ])
          .sort(),
        s._used.slice().sort(),
        s._normal,
        s._xyz,
        Object.entries(s._ruleFlags || {}).sort(([a], [b]) => a.localeCompare(b)),
        // Two visually identical boards are not equivalent when only one
        // carries a proven causal trigger (e.g. sent by monster effect).
        s._ruleEvents || [],
        s.lp.self,
        s.lp.opponent,
      ]);
    }
    const baseOptions = options;
    function actions(s) {
      if (s.lp.self <= 0 || s.lp.opponent <= 0) return [];
      const options = Restrictions.effective(s, baseOptions);
      const out = [],
        mz = free(s, "m"),
        sz = free(s, "s");
      function add(label, ids, change, used, source, metadata = {}) {
        const n = clone(s);
        change(n);
        if (used && !n._used.includes(used)) n._used.push(used);
        const deckHand = n.tokens.some(
          (t) =>
            t.zone === "hand" &&
            t.owner === "self" &&
            definition(n, t)?.section === "main" &&
            !s.tokens.some((b) => b.uid === t.uid && b.zone !== "deck"),
        );
        if ((options.noDeckToHand || s._ruleFlags?.noDeckToHand) && deckHand)
          return;
        try {
          E.validate(n);
        } catch {
          return;
        }
        const changed = n.tokens
          .filter((t) => {
            const before = s.tokens.find((x) => x.uid === t.uid);
            return (
              !before ||
              before.zone !== t.zone ||
              before.controller !== t.controller ||
              before.position !== t.position
            );
          })
          .map((t) => t.uid);
        const actor = s.tokens.find((t) => t.uid === ids[0]);
        const ap = actor && fact(s, actor);
        const activation =
          metadata.event?.activation ??
          !!(
            used ||
            (actor &&
              actor.zone === "hand" &&
              ["spell", "trap"].includes(ap?.kind))
          );
        const tags = [];
        if (activation) {
          if (deckHand) tags.push("addFromDeck");
          if (
            n.tokens.some(
              (t) =>
                monster(t.zone) &&
                !s.tokens.some((b) => b.uid === t.uid) &&
                definition(n, t)?.section === "main",
            )
          )
            tags.push("summonFromDeck");
          if (actor && monster(actor.zone) && ap?.kind === "monster")
            tags.push("monsterEffect");
        }
        const paid = R.checkpoint(s);
        if (used && !paid._used.includes(used)) paid._used.push(used);
        if (
          activation &&
          actor?.zone === "hand" &&
          ["spell", "trap"].includes(ap?.kind)
        ) {
          const token = paid.tokens.find((t) => t.uid === actor.uid),
            end = n.tokens.find((t) => t.uid === actor.uid);
          if (token && end) {
            token.zone = end.zone;
            token.position = end.position;
          }
        }
        if (activation) {
          paid.lp = E.clone(n.lp);
          for (const token of paid.tokens) {
            const end = n.tokens.find((t) => t.uid === token.uid);
            if (token.zone.startsWith("material:") && end?.zone === "graveyard")
              token.zone = "graveyard";
          }
        }
        n._steps.push({
          before: R.checkpoint(s),
          paid: metadata.paid || paid,
          afterState: R.checkpoint(n),
          event: metadata.event || { activation, actor: actor?.uid, tags },
          label,
          cards: [...new Set([...ids, ...changed])]
            .map((uid) => {
              const before = s.tokens.find((x) => x.uid === uid),
                after = n.tokens.find((x) => x.uid === uid),
                t = after || before;
              return t
                ? {
                    key: t.key,
                    owner: t.owner,
                    name: definition(n, t)?.name || "",
                    from:
                      before?.zone ||
                      (definition(n, t)?.section === "extra"
                        ? "extra"
                        : "deck"),
                    to: after?.zone || before.zone,
                  }
                : null;
            })
            .filter(Boolean),
          source: source || "https://www.yugioh-card.com/en/rulebook/",
          after: snapshot(n, db),
          lp: clone(n.lp),
        });
        out.push(n);
      }
      for (const action of R.actions(s, db, options).actions)
        add(
          action.label,
          action.ids,
          (n) => Object.assign(n, action.state),
          action.used,
          action.source,
          action,
        );
      if (s.format === "rush_duel") return out;
      for (const t of own(s, (t) => t.zone === "hand")) {
        const p = fact(s, t);
        if (!p) continue;
        if (p.normalAllowed && !s._normal) {
          const birth =
            !options.effectsNegated &&
            own(
              s,
              (t) =>
                field(t) &&
                t.position !== "set" &&
                !E.negated(s, t) &&
                fact(s, t)?.cid === 17815,
            ).length > 0;
          const count = p.level >= 7 ? 2 : p.level >= 5 ? 1 : 0;
          for (const tribute of combinations(
            mons(s),
            birth && p.level === 7 ? 0 : count,
          ).slice(0, 12)) {
            const summonZone = mz || tribute[0]?.zone;
            if (!summonZone || blocked.has(summonZone)) continue;
            if (tribute.length && options.graveyardBlocked) continue;
            add(
              `Invoca Normalmente ${definition(s, t).name}${tribute.length ? " tributando " + tribute.map((x) => definition(s, x).name).join(" y ") : ""}`,
              [t.uid, ...tribute.map((x) => x.uid)],
              (n) => {
                tribute.forEach((x) => move(n, x.uid, "graveyard", "attack"));
                move(n, t.uid, summonZone);
                n._normal = true;
              },
              null,
              p.source,
            );
          }
        }
        if (
          [17768, 17769, 17770].includes(p.cid) &&
          !mons(s).length &&
          mz &&
          !options.specialBlocked
        )
          add(
            `Invoca Especialmente ${definition(s, t).name}: no controlas monstruos`,
            [t.uid],
            (n) => move(n, t.uid, mz),
            null,
            p.source,
          );
        if (
          p.cid === 18151 &&
          faceMons(s).some((x) => kashtira(s, x)) &&
          mz &&
          !options.specialBlocked &&
          unused(s, "18151:special")
        )
          add(
            "Invoca Riseheart; desde esta Invocación el Extra queda limitado a Xyz",
            [t.uid],
            (n) => {
              move(n, t.uid, mz);
              n._xyz = true;
            },
            "18151:special",
            p.source,
          );
        if (p.cid === 17815 && sz)
          add(
            "Activa Kashtira Birth en una zona de Magia/Trampa libre",
            [t.uid],
            (n) => move(n, t.uid, sz),
            null,
            p.source,
          );
        if (
          p.cid === 18204 &&
          !options.effectsNegated &&
          unused(s, "18204:activate") &&
          !s.tokens.some(
            (x) => x.controller === "self" && x.zone === "field",
          ) &&
          !blocked.has("field")
        )
          for (const c of deckCards(
            s,
            (f) => f?.kind === "monster" && f.setcode === "Kashtira",
          ))
            add(
              `Activa Wraitsoth y añade ${c.name} del Deck`,
              [t.uid],
              (n) => {
                move(n, t.uid, "field");
                take(n, c, "hand");
              },
              "18204:activate",
              p.source,
            );
        if (
          p.cid === 18203 &&
          sz &&
          mz &&
          !options.effectsNegated &&
          !options.specialBlocked &&
          unused(s, "18203:summon") &&
          !options.graveyardBlocked
        )
          for (const target of faceMons(s).filter((x) => kashtira(s, x)))
            for (const c of deckCards(
              s,
              (f) =>
                f?.kind === "monster" &&
                f.setcode === "Kashtira" &&
                f.attribute !== fact(s, target)?.attribute,
            ))
              add(
                `Kashtiratheosis selecciona ${definition(s, target).name} e Invoca ${c.name} en Defensa; Extra limitado a Xyz`,
                [t.uid, target.uid],
                (n) => {
                  move(n, t.uid, "graveyard");
                  take(n, c, mz, "defense");
                  n._xyz = true;
                },
                "18203:summon",
                p.source,
              );
        if (sz && !options.effectsNegated && !options.graveyardBlocked) {
          const opponents = enemy(s);
          if (p.cid === 12464 && s.lp.self > 1000)
            for (const x of opponents.filter((x) => !monster(x.zone)))
              add(
                `Paga 1000 LP con Cosmic Cyclone y destierra ${definition(s, x)?.name || "la carta rival"}`,
                [t.uid, x.uid],
                (n) => {
                  n.lp.self -= 1000;
                  move(n, t.uid, "graveyard");
                  move(n, x.uid, "banished", "attack");
                },
                null,
                p.source,
              );
          if (p.cid === 5432)
            for (const x of opponents.filter(
              (x) =>
                monster(x.zone) &&
                x.position !== "set" &&
                fact(s, x) &&
                !fact(s, x).species.includes("Link"),
            ))
              add(
                `Book of Moon coloca ${definition(s, x).name} en Defensa boca abajo`,
                [t.uid, x.uid],
                (n) => {
                  move(n, t.uid, "graveyard");
                  move(n, x.uid, x.zone, "set");
                },
                null,
                p.source,
              );
          const targets =
            p.cid === 4342
              ? s.tokens.filter((x) => monster(x.zone))
              : p.cid === 4678
                ? opponents.filter((x) => !monster(x.zone))
                : [];
          // Destruction protections and mandatory leave-field triggers are not assumed resolved.
          if (targets.length)
            add(
              `Activa ${definition(s, t).name}: destruye ${targets.length} carta(s) del campo indicado; comprobar protecciones y disparos`,
              [t.uid, ...targets.map((x) => x.uid)],
              (n) => {
                move(n, t.uid, "graveyard");
                targets.forEach((x) => move(n, x.uid, "graveyard", "attack"));
              },
              null,
              p.source,
            );
        }
      }
      if (!options.effectsNegated)
        for (const t of faceMons(s)) {
          const p = fact(s, t);
          if (!p || E.negated(s, t)) continue;
          if (
            [17768, 17769, 17770].includes(p.cid) &&
            unused(s, `${p.cid}:search`)
          ) {
            const kind = { 17768: "monster", 17769: "spell", 17770: "trap" }[
              p.cid
            ];
            for (const c of deckCards(
              s,
              (f) => f?.setcode === "Kashtira" && f.kind === kind,
            ))
              add(
                `${definition(s, t).name}: añade ${c.name} del Deck a la mano`,
                [t.uid],
                (n) => take(n, c, "hand"),
                `${p.cid}:search`,
                p.source,
              );
          }
          if (
            p.cid === 9861 &&
            mz &&
            unused(s, "9861:control") &&
            unused(s, `${t.uid}:control`) &&
            !options.graveyardBlocked
          ) {
            const mats = s.tokens.filter((x) => x.zone === `material:${t.uid}`);
            for (const mat of mats)
              for (const target of enemy(s).filter((x) => monster(x.zone)))
                add(
                  `Big Eye desacopla ${definition(s, mat).name} y toma control de ${definition(s, target)?.name || "un monstruo rival"}; Big Eye no puede atacar este turno`,
                  [t.uid, mat.uid, target.uid],
                  (n) => {
                    move(n, mat.uid, "graveyard", "attack");
                    const x = n.tokens.find((x) => x.uid === target.uid);
                    x.controller = "self";
                    x.zone = mz;
                  },
                  `${t.uid}:control`,
                  p.source,
                );
          }
        }
      if (
        mz &&
        !options.specialBlocked &&
        !options.effectsNegated &&
        !options.graveyardBlocked &&
        unused(s, "17815:revive") &&
        own(
          s,
          (t) =>
            field(t) &&
            t.position !== "set" &&
            !E.negated(s, t) &&
            fact(s, t)?.cid === 17815,
        ).length
      )
        for (const target of s.tokens.filter(
          (t) =>
            t.owner === "self" &&
            ["graveyard", "banished"].includes(t.zone) &&
            t.position !== "set" &&
            kashtira(s, t) &&
            !fact(s, t)?.rank,
        ))
          add(
            `Birth recupera ${definition(s, target).name} de ${target.zone === "graveyard" ? "Cementerio" : "destierro boca arriba"}`,
            [
              own(
                s,
                (t) =>
                  field(t) &&
                  t.position !== "set" &&
                  fact(s, t)?.cid === 17815 &&
                  !E.negated(s, t),
              )[0].uid,
              target.uid,
            ],
            (n) => move(n, target.uid, mz),
            "17815:revive",
            fact(s, target).source,
          );
      if (!options.specialBlocked && !options.noExtra) {
        const materials = faceMons(s).filter((t) => fact(s, t)?.level === 7);
        for (const c of s.self.cards.filter(
          (c) =>
            c.section === "extra" &&
            [17800, 9861].includes(profile(c, db)?.cid) &&
            available(s, c) > 0,
        ))
          for (const pair of combinations(materials, 2).slice(0, 15)) {
            const zone = mz || pair[0].zone;
            if (blocked.has(zone)) continue;
            add(
              `Superpone ${pair.map((t) => definition(s, t).name).join(" y ")} para Invocar Xyz ${c.name}; conserva 2 materiales`,
              pair.map((t) => t.uid),
              (n) => {
                const xyz = take(
                  n,
                  c,
                  zone,
                  profile(c, db).cid === 17800 ? "defense" : "attack",
                );
                pair.forEach((t) => {
                  const nested = n.tokens.filter(
                    (x) => x.zone === `material:${t.uid}`,
                  );
                  nested.forEach((x) => move(n, x.uid, "graveyard"));
                  move(n, t.uid, `material:${xyz.uid}`, "attack");
                });
              },
              null,
              profile(c, db).source,
            );
          }
      }
      return out;
    }
    let frontier = [root];
    const visited = new Set([stateKey(root)]),
      found = [];
    for (
      let d = 0;
      d < depth && frontier.length && base.explored < limit;
      d++
    ) {
      const next = [];
      for (const s of frontier) {
        for (const n of actions(s)) {
          if (++base.explored > limit) {
            base.limited = true;
            break;
          }
          const key = stateKey(n);
          if (visited.has(key)) continue;
          visited.add(key);
          next.push(n);
          found.push(n);
        }
        if (base.explored > limit) break;
      }
      next.sort((a, b) => score(b) - score(a));
      if (next.length > width) base.limited = true;
      frontier = next.slice(0, width);
    }
    if (frontier.length) base.limited = true;
    found.sort((a, b) => score(b) - score(a));
    const endings = new Set();
    for (const s of found) {
      const resultKey = JSON.stringify(
        s.tokens
          .filter((t) => field(t) || t.zone === "hand")
          .map((t) => [t.key, t.owner, t.controller, t.zone, t.position])
          .sort(),
      );
      if (endings.has(resultKey)) continue;
      endings.add(resultKey);
      base.plans.push({
        steps: s._steps,
        final: snapshot(s, db),
        lp: s.lp,
        normalAvailable: !s._normal,
        xyzOnly: s._xyz,
        used: s._used,
        priority: Math.round(score(s) - score(root)),
        removed: startingEnemy - enemy(s).length,
        metrics: metrics(s),
      });
      if (base.plans.length >= 6) break;
    }
    if (!base.plans.length)
      base.reason =
        "No hay acciones calculables con los efectos implementados y el estado capturado. Esto no demuestra que no existan otras jugadas.";
    return base;
  }
  return { solve, profile, capabilities, modelled: [...modelled] };
});
