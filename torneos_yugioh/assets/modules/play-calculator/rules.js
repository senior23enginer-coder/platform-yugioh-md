/* Declarative effect adapter. Data never becomes JavaScript; unknown operations fail closed. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"), require("./operations.js"), require("./guards.js"), require("./resources.js"), require("./transitions.js"), require("./timing.js"), require("./events.js"), require("./restrictions.js"), require("./integrity.js"));
  else root.YgoPlayRules = factory(root.YgoPlayEngine, root.YgoCalculatorOperations, root.YgoCalculatorGuards, root.YgoCalculatorResources, root.YgoCalculatorTransitions, root.YgoCalculatorTiming, root.YgoCalculatorEvents, root.YgoCalculatorRestrictions, root.YgoCalculatorIntegrity);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, Operations, Guards, Resources, Transitions, Timing, Events, Restrictions, Integrity) {
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
  const flags = Restrictions.names;
  function heatWaveBlocks(flags, db, cid) {
    if (!flags.effectMonsterSummonBlocked) return false;
    // A known Normal Monster is allowed. When its nature is unknown, fail
    // closed rather than recommending an Effect Monster Summon as legal.
    const species = String(db.facts?.[cid]?.species || "").toLowerCase();
    return !species.includes("normal monster");
  }
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
          (z) => zones.includes(z) || z === "monsters" || z === "field-all" || z === "actor-materials",
        ),
      "Selector incompleto",
    );
    check(
      q.cid === undefined || (Number.isInteger(q.cid) && q.cid > 0),
      "CID incorrecto",
    );
    check(q.cids === undefined || (Array.isArray(q.cids) && q.cids.length > 0 &&
      q.cids.length <= 60 && q.cids.every((id) => Number.isInteger(id) && id > 0) &&
      new Set(q.cids).size === q.cids.length), "Lista CID incorrecta");
    check(
      q.faceUp === undefined || typeof q.faceUp === "boolean",
      "Posicion incorrecta",
    );
    check(q.section === undefined || ["main", "extra"].includes(q.section),
      "Origen del objetivo no acreditado");
    if (q.zones.includes("actor-materials"))
      check(q.zones.length === 1 && q.side === "self" && q.section === undefined,
        "Material debe pertenecer al Xyz actor");
    check(q.key === undefined || typeof q.key === "string", "Clave incorrecta");
    check(
      Object.keys(q).every((k) =>
        ["side", "zones", "cid", "cids", "key", "faceUp", "notNegated", "section"].includes(k),
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
      (q.cids === undefined || q.cids.includes(db.bindings[t.key])) &&
      (q.key === undefined || q.key === t.key) &&
      (q.section === undefined || E.definition(s,t.key,t.owner)?.section === q.section) &&
      (q.faceUp === undefined || q.faceUp === (t.position !== "set")) &&
      (!q.notNegated || !E.negated(s, t))
    );
  }
  function candidates(s, q, db) {
    return s.tokens.filter((t) => matches(s, t, q, db));
  }
  function inventoryCandidates(s, db, query) {
    const section = query.from === "extra" ? "extra" : "main";
    const terms = query.nameTerms.map(E.norm);
    return s.self.cards.filter(c => c.section === section &&
      (E.remaining(s, c.key, "self") > 0 ||
        s.tokens.some(t => t.key === c.key && t.owner === "self" && t.zone === query.from)) &&
      terms.some(term => E.norm(c.name).includes(term)) &&
      (!query.cardTypes || query.cardTypes.includes(db.facts?.[db.bindings[c.key]]?.cardType)) &&
      (!query.species || E.norm(db.facts?.[db.bindings[c.key]]?.species || "").includes(E.norm(query.species))));
  }
  function extraSummonWindow(s) {
    return Events.observedExtraSummonWindow(s);
  }
  function monsterEffectBlockedByTyphon(s, db, token) {
    if (!token || !E.isMonster(token.zone) || token.position === "set") return false;
    const activeTyphon = s.tokens.some(t => db.bindings[t.key] === 19184 &&
      t.xyzSummoned === true && E.isMonster(t.zone) && t.position !== "set");
    if (!activeTyphon) return false;
    const atk = Number.isInteger(token.currentATK) ? token.currentATK :
      db.facts?.[db.bindings[token.key]]?.atk;
    // Unknown current ATK needs a separate ruling/measurement, not a false
    // claim that the monster is allowed to activate.
    return !Number.isInteger(atk) || atk >= 3000;
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
    if (rule.trigger !== undefined)
      check(rule.trigger && rule.trigger.actorCid === rule.actor.cid &&
        (rule.trigger.kind === "sent_by_monster_effect"
          ? Number.isInteger(rule.trigger.sourceCid) && rule.trigger.sourceCid > 0
          : rule.trigger.kind === "destroyed_by_effect" || rule.trigger.kind === "fusion_summoned" ||
            rule.trigger.kind === "link_summoned_with_extra" ||
            (rule.trigger.kind === "summoned" &&
              Array.isArray(rule.trigger.methods) && rule.trigger.methods.length > 0 &&
              rule.trigger.methods.every(m => ["normal", "special"].includes(m)))) &&
        (rule.trigger.fromMonsters === undefined ||
          (rule.trigger.kind === "destroyed_by_effect" && rule.trigger.fromMonsters === true)),
        "Disparador causal incorrecto");
    check(
      rule.actor.side === "self",
      "El actor del efecto propio debe pertenecer a tu lado",
    );
    const typhonProcedure = rule.resolve?.length === 1 &&
      rule.resolve[0].op === "xyzOverlay" && rule.resolve[0].cid === 19184 &&
      rule.source.includes("cid=19184") && rule.actor.cid === undefined &&
      rule.actor.zones.length === 1 && rule.actor.zones[0] === "monsters" &&
      rule.actor.faceUp === true && Array.isArray(rule.actor.cids) &&
      rule.actor.cids.length > 0 && rule.actor.cids.length <= 60;
    check(Number.isInteger(rule.actor.cid) || typhonProcedure,
      "Actor sin CID o procedimiento TY-PHON sin materiales acreditados");
    check(rule.chainSpeed === undefined || [1, 2].includes(rule.chainSpeed),
      "Velocidad de efecto incorrecta");
    check(
      Array.isArray(rule.guards) &&
        rule.guards.length > 0 &&
        rule.guards.length <= 30,
      "Faltan condiciones",
    );
    for (const g of rule.guards) {
      Guards.assertSupported(g);
      if (g.kind === "count") {
        selector(g.selector);
        check(
          Number.isInteger(g.min) &&
            Number.isInteger(g.max) &&
            g.min >= 0 &&
            g.max >= g.min,
          "Conteo incorrecto",
        );
      } else if (g.kind === "distinctCount") {
        selector(g.selector);
        check(Number.isInteger(g.min) && g.min >= 0 && g.min <= 10,
          "Conteo de nombres diferentes incorrecto");
      } else if (g.kind === "onlyCids") {
        selector(g.selector);
        check(g.selector.side === "self" && g.selector.zones.length === 1 &&
          g.selector.zones[0] === "monsters" && g.selector.faceUp === true &&
          Array.isArray(g.allowedCids) && g.allowedCids.length > 0 &&
          g.allowedCids.length <= 60 && new Set(g.allowedCids).size === g.allowedCids.length &&
          g.allowedCids.every((cid) => Number.isInteger(cid) && cid > 0),
        "El guardia de monstruos permitidos requiere identidades y campo boca arriba acreditados");
      } else if (g.kind === "event") {
        check(g.event === "opponent_activation"
          ? g.side==="opponent" && g.currentTurn===true &&
            rule.actor.cid===19188 && rule.id==="671:sp:temporary-banish" &&
            Object.keys(g).length===4
          : g.event === "opponent_main_deck_add"
          ? g.side === "opponent" && g.currentTurn === true && Object.keys(g).length === 4
          : g.event === "owned_monster_destroyed_by_effect_this_turn"
          ? Object.keys(g).length === 2
          : ["destroy", "summon", "fusion_summon"].includes(g.event) &&
            (g.event !== "destroy" || g.by === "effect") &&
            ["self", "opponent"].includes(g.side) && g.currentTurn === true,
          "Condición de evento desconocida");
      } else if (g.kind === "phaseStart") {
        check(Object.keys(g).length === 1,
          "Inicio de fase requiere historial de acciones sin parámetros");
      } else if (g.kind === "extraSummonHistory") {
        check(g.side === "opponent" && g.min === 2 && g.turns === 2,
          "Historial rival de Extra exige dos Invocaciones en un mismo turno elegible");
      } else if (g.kind === "inventoryTarget") {
        check(g.from === "deck" || g.from === "extra", "Origen de inventario incorrecto");
        check(Array.isArray(g.nameTerms) && g.nameTerms.length > 0 && g.nameTerms.length <= 5 &&
          g.nameTerms.every(term => typeof term === "string" && term.trim().length >= 3 && term.length <= 60),
          "Identidad del objetivo de inventario incorrecta");
        check(g.cardTypes === undefined || (Array.isArray(g.cardTypes) && g.cardTypes.length > 0 &&
          g.cardTypes.every(t => ["spell", "trap", "monster"].includes(t))),
          "Tipo de carta buscada desconocido");
        check(g.species === undefined || (typeof g.species === "string" && g.species.length <= 30 && g.species.length > 0),
          "Tipo de Extra desconocido");
        check(g.min === 1, "El objetivo a buscar debe existir en la lista disponible");
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
      Array.isArray(rule.select) && rule.select.length <= 7,
      "Selecciones incorrectas",
    );
    check(rule.oncePerName === undefined || (rule.oncePerName === true && rule.oncePerTurn === true && Number.isInteger(rule.actor.cid)),
      "Uso compartido por nombre exige CID y límite por turno");
    if (rule.maxUsesPerTurn !== undefined)
      check(rule.maxUsesPerTurn === 4 && rule.deckId === 677 && rule.actor.cid === 19200 &&
        rule.id === '677:sarcophagus:send-horus' && rule.source.includes('cid=19200') &&
        rule.oncePerName === true && rule.oncePerTurn === true,
        'Un contador de cuatro usos solo se admite para el Sarcófago del Rey acreditado');
    if (rule.noChainReplay !== undefined)
      check(rule.noChainReplay === true &&
        ((rule.actor.cid === 19184 && rule.id === "671:typhon:detach-bounce") ||
         (rule.actor.cid === 21203 && ["671:gordian:one-monster-six-extra","671:gordian:one-monster-one-hand"].includes(rule.id))),
        "Resolución no seleccionadora de TY-PHON requiere regla acreditada");
    if (rule.afterResolution !== undefined) {
      check(([9139,21203].includes(rule.actor.cid) ||
        (rule.actor.cid === 20533 && rule.id === "671:goblin-biker-grand-breakout:deck-summon")) &&
        rule.source.includes("cid=" + rule.actor.cid) &&
        rule.activation === true &&
        Array.isArray(rule.afterResolution) && rule.afterResolution.length === 1 &&
        rule.afterResolution[0].op === "move" &&
        rule.afterResolution[0].ref === "actor" &&
        rule.afterResolution[0].to === "graveyard" &&
        rule.afterResolution[0].position === "attack",
        "Envío de Mágica Normal al Cementerio no acreditado");
    }
    if (rule.cannotBeNegated !== undefined)
      check(rule.cannotBeNegated === true && rule.actor.cid === 21203 &&
        ["671:gordian:one-monster-six-extra","671:gordian:one-monster-one-hand"].includes(rule.id),
        "Solo Gordian Slicer tiene prohibición de negación acreditada");
    check(rule.distinctSelectedNames === undefined || typeof rule.distinctSelectedNames === "boolean",
      "Restriccion de nombres incorrecta");
    const refs = new Set(["actor"]);
    for (const x of rule.select) {
      check(
        /^[a-z][a-z0-9_]{0,30}$/.test(x.ref) && !refs.has(x.ref),
        "Referencia repetida",
      );
      refs.add(x.ref);
      selector(x.selector);
      check(x.allowActor === undefined ||
        (x.allowActor === true && x.ref === "target" &&
          x.selector.side === "self" &&
          ((x.selector.faceUp === true && x.selector.zones.includes("field-all")) ||
            (rule.actor.cid === 19184 && x.selector.zones.includes("monsters") &&
              x.selector.section === "extra"))),
        "Solo objetivos propios boca arriba acreditados pueden coincidir con el actor");
    }
    check(rule.equippedToActorRef === undefined ||
      (rule.actor.cid === 20196 && rule.equippedToActorRef === "gear" &&
        rule.select.some((x) => x.ref === "gear" && x.selector.side === "self" &&
          x.selector.cid === 20225 && x.selector.zones.length === 1 &&
          x.selector.zones[0] === "s1")),
    "Un equipo sólo puede referirse a Réquiem acreditado sobre Grabador");
    check(
      Array.isArray(rule.costs) &&
        Array.isArray(rule.resolve) &&
        rule.resolve.length > 0 &&
        rule.costs.length + rule.resolve.length <= 30,
      "Faltan coste o resolucion",
    );
    for (const [phase, operations] of [["costs", rule.costs], ["resolve", rule.resolve]]) for (const op of operations) {
      Operations.assertSupported(op, phase);
      if (op.op === "shuffle")
        check(
          Array.isArray(op.refs) && op.refs.length >= 2 && op.refs.length <= 5 &&
          new Set(op.refs).size === op.refs.length && op.refs.every(ref=>refs.has(ref) && ref!=="actor") &&
          op.distinctCids === true && Array.isArray(op.allowedCids) &&
          op.allowedCids.length >= op.refs.length &&
          op.allowedCids.every(cid=>Number.isInteger(cid) && cid>0),
          "Barajado requiere cartas propias identificadas y nombres diferentes",
        );
      else if (op.op === "link")
        check(
          Number.isInteger(op.cid) && op.cid>0 &&
          Array.isArray(op.materials) && op.materials.length>=1 && op.materials.length<=4 &&
          new Set(op.materials).size===op.materials.length && op.materials.every(ref=>refs.has(ref)) &&
          Array.isArray(op.materialCids) && op.materialCids.length===op.materials.length &&
          op.materialCids.every(cid=>Number.isInteger(cid)&&cid>0) &&
          ["m1","m2","m3","m4","m5","emz1","emz2"].includes(op.to) &&
          op.recipe==="exact-cids" && Number.isInteger(op.linkRating) && op.linkRating===op.materials.length &&
          (op.materials.length>1 || (op.cid===20225 && rule.actor.cid===19932 &&
            op.materialCids[0]===19932)),
          "Link requiere rating y materiales exactos acreditados",
        );
      else if (op.op === "xyz")
        check(
          Number.isInteger(op.cid) && op.cid>0 &&
          Array.isArray(op.materials) && op.materials.length>=2 && op.materials.length<=4 &&
          new Set(op.materials).size===op.materials.length && op.materials.every(ref=>refs.has(ref)) &&
          Array.isArray(op.materialCids) && op.materialCids.length===op.materials.length &&
          op.materialCids.every(cid=>Number.isInteger(cid)&&cid>0) &&
          ["m1","m2","m3","m4","m5","emz1","emz2"].includes(op.to) &&
          op.recipe==="exact-cids",
          "Xyz requiere materiales acreditados por CID",
        );
      else if (op.op === "xyzOverlay")
        check(op.cid === 19184 && op.material === "actor" &&
          ["m1","m2","m3","m4","m5","emz1","emz2"].includes(op.to) &&
          op.recipe === "typhon-highest-atk" &&
          rule.guards.some(g => g.kind === "extraSummonHistory") &&
          rule.activation === false,
          "TY-PHON requiere historial rival, monstruo mayor ATK y superposición explícita");
      else if (op.op === "necroquipSummon")
        check(phase==="resolve" && rule.id==="671:princess:contact-summon" &&
          rule.actor.cid===20196 && rule.activation===false &&
          op.cid===20423 && op.recipe==="equipped-monster-plus-fiend" &&
          op.refs?.length===3 && JSON.stringify(op.refs)===
            JSON.stringify(["actor","equip","fiend"]) &&
          op.materialCids?.length===3 &&
          JSON.stringify(op.materialCids)===JSON.stringify([20196,20225,19366]) &&
          ["m1","m2","m3","m4","m5"].includes(op.to) &&
          refs.has("equip") && refs.has("fiend"),
          "Princesa requiere Grabador equipado, Réquiem y Demonio de mano físicos");
      else if (op.op === "fusion")
        check(
          Number.isInteger(op.cid) && op.cid > 0 &&
          Array.isArray(op.materials) && op.materials.length >= 2 && op.materials.length <= 5 &&
          new Set(op.materials).size === op.materials.length &&
          op.materials.every(ref => refs.has(ref)) &&
          Array.isArray(op.materialCids) && op.materialCids.length === op.materials.length &&
          op.materialCids.every(cid => Number.isInteger(cid) && cid > 0) &&
          ["m1","m2","m3","m4","m5","emz1","emz2"].includes(op.to) &&
          op.recipe === "exact-cids" && typeof op.allowGraveyard === "boolean" &&
          (op.requiredOrigins === undefined || op.requiredOrigins === "hand-and-field") &&
          (op.distinctCids === undefined || op.distinctCids === true) &&
          (op.graveyardPolicy === undefined ||
            (op.graveyardPolicy === "fiendsmith-sequence" && rule.actor.cid === 20226 && op.cid === 20389 && op.allowGraveyard)),
          "Fusión requiere materiales exactos, identificados y de mano/campo",
        );
      else if (op.op === "destroy")
        check(
          refs.has(op.ref) && op.reason === "effect",
          "Destrucción no acreditada",
        );
      else if (op.op === "tribute")
        check(refs.has(op.ref) && op.reason === "cost" && rule.costs.includes(op) &&
          (op.allowHand === undefined || op.allowHand === true) &&
          (op.allowSet === undefined || (op.allowSet === true && rule.actor.cid === 20533 &&
            rule.id === "671:goblin-biker-grand-breakout:deck-summon" && op.allowHand === undefined)),
          "Sacrificar como coste exige un monstruo identificado y el bloque costs");
      else if (op.op === "changeControl")
        check(refs.has(op.ref) && ["self", "opponent"].includes(op.to) &&
          typeof op.reason === "string" && op.reason.length > 0,
          "Cambio de control no acreditado");
      else if (op.op === "equip")
        check(refs.has(op.ref) && refs.has(op.target) && op.to === "s1" &&
          rule.actor.cid === 20225 && op.ref === "actor" && op.targetCid === 20196,
          "Equipamiento de Réquiem requiere Grabador LUZ/Demonio fijado");
      else if (op.op === "counter")
        check(refs.has(op.ref) && op.kind === "predator" && op.amount === 1 &&
          rule.actor.cid === 12870,
          "Contador Depredador exige Dragostapelia y objetivo concreto");
      else if (op.op === "banishExtraFaceDown")
        check((rule.costs.includes(op) ||
           (phase === "resolve" && rule.actor.cid === 21203 &&
            rule.cannotBeNegated === true && rule.guards.some(g => g.kind === "phaseStart"))) &&
           op.count === 6 && Array.isArray(op.refs) &&
          op.refs.length === 6 && new Set(op.refs).size === 6 &&
          op.refs.every(ref=>ref!=="actor" && refs.has(ref)),
          "Gordiano debe costear seis cartas del Extra identificadas por objetivo");
      else if (op.op === "move")
        check(
          refs.has(op.ref) &&
            (zones.includes(op.to) ||
              (op.to === "free-monster" && phase === "resolve" && op.summonMethod === "special")) &&
            ["attack", "defense", "set"].includes(op.position) &&
            (op.summonMethod === undefined ||
              (op.summonMethod === "special" && ["m1","m2","m3","m4","m5","free-monster"].includes(op.to))),
          "Movimiento incorrecto",
        );
      else if (op.op === "take")
        check(
          ["self", "opponent"].includes(op.side) &&
            Number.isInteger(op.cid) &&
            ["deck", "extra"].includes(op.from) &&
            (zones.includes(op.to) ||
              (op.to === "free-monster" && phase === "resolve" &&
                op.side === "self" && op.from === "deck" && op.summonMethod === "special")) &&
            ["attack", "defense", "set"].includes(op.position) &&
          (op.summonMethod === undefined || (op.summonMethod === "special" &&
            op.side === "self" && op.from === "deck" &&
            ["m1", "m2", "m3", "m4", "m5", "free-monster"].includes(op.to))) &&
          (op.cannotAttackThisTurn === undefined || (op.cannotAttackThisTurn === true &&
              rule.actor.cid === 20533 && rule.id === "671:goblin-biker-grand-breakout:deck-summon" &&
              op.side === "self" && op.cid === 19366 && op.from === "deck" &&
              op.to === "free-monster" && op.summonMethod === "special")) &&
          (op.cause === undefined || (op.cause === "monster_effect" &&
              op.from === "deck" && op.to === "graveyard" &&
              rule.actor.side === op.side && Array.isArray(rule.tags) && rule.tags.includes("monsterEffect"))),
          "Recurso incorrecto",
        );
      else if (op.op === "payLP")
        check(
          Number.isInteger(op.amount) && op.amount > 0,
          "Coste LP incorrecto",
        );
      else if (op.op === "temporaryBanishPair")
        check(phase==="resolve" && rule.actor.cid===19188 &&
          rule.id==="671:sp:temporary-banish" && rule.chainSpeed===2 &&
          op.sourceCid===19188 && Array.isArray(op.refs) &&
          op.refs.length===2 && op.refs[0]==="actor" &&
          op.refs[1]==="target" && refs.has("target") &&
          rule.guards.some(g=>g.kind==="event"&&g.event==="opponent_activation"),
          "Destierro temporal S:P exige activación rival observada y dos monstruos fijados");
      else if (op.op === "drawUnknown")
        check(phase==="resolve" && rule.deckId===671 &&
          rule.id==="671:guardian-chimera:draw-destroy" &&
          rule.actor.cid===16845 && rule.source.includes("cid=16845") &&
          rule.trigger?.kind==="fusion_summoned" &&
          op.side==="self" && op.count===1,
          "Robo desconocido exige Quimera Guardián fusionada de forma acreditada");
      else if (op.op === "damagePerBanishedMonsters")
        check(rule.actor.cid === 9657 && rule.source.includes("cid=9657") &&
          op.side === "opponent" && op.perMonster === 100 &&
          rule.select.some(item => item.ref === "material" &&
            item.selector.zones.length === 1 &&
            item.selector.zones[0] === "actor-materials"),
          "Aero Shark requiere material Xyz y 100 LP por monstruo desterrado propio");
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
          flags.includes(op.flag) && typeof op.value === "boolean" &&
          (op.flag !== "effectMonsterSummonBlocked" ||
            (rule.actor.cid === 9139 && rule.source.includes("cid=9139") &&
              rule.actor.zones.includes("hand") &&
              rule.guards.some(g => g.kind === "phaseStart") &&
              rule.guards.some(g => g.kind === "state" && g.field === "phase" &&
                g.values.length === 1 && g.values[0] === "main1"))) &&
          (op.duration || "persistent") ===
            (Restrictions.turnScoped.includes(op.flag) ? "turn" :
              op.flag === "effectMonsterSummonBlocked" ? "next-own-draw" : "persistent"),
          "Restriccion o duracion incorrecta",
        );
      else if (op.op === "normal")
        check(typeof op.available === "boolean", "Normal incorrecta");
      else if (op.op === "summon")
        check(refs.has(op.ref) && ["normal", "special"].includes(op.method) &&
          ["m1", "m2", "m3", "m4", "m5"].includes(op.to) &&
          op.from === "hand" && op.position === "attack" &&
          rule.resolve.includes(op),
          "Invocación desde mano requiere procedimiento explícito y zona libre");
      else throw Error("Operacion no implementada: " + op.op);
    }
    check(
      Array.isArray(rule.tags) &&
        rule.tags.every((t) => tags.includes(t)) &&
        typeof rule.activation === "boolean" &&
        typeof rule.oncePerTurn === "boolean",
      "Ventana o limite ausente",
    );
    if (rule.activationNegatedConsumesUse !== undefined)
      check(typeof rule.activationNegatedConsumesUse === "boolean", "Límite tras negación de activación no acreditado");
    return rule;
  }
  function checkpoint(s) {
    return {
      tokens: E.clone(s.tokens),
      nextId: s.nextId,
      lp: E.clone(s.lp),
      selfUnknownHand: s.selfUnknownHand||0,
      normalAvailable: !s._normal,
      _normal: s._normal,
      _xyz: s._xyz,
      _used: E.clone(s._used || []),
      _ruleFlags: E.clone(s._ruleFlags || {}),
      _ruleFlagTurns: E.clone(s._ruleFlagTurns || {}),
      _ruleEvents: E.clone(s._ruleEvents || []),
      _ruleUseTurns: E.clone(s._ruleUseTurns || {}),
      _ruleUseCounts: E.clone(s._ruleUseCounts || {}),
    };
  }
  function triggerMatches(s, rule, uid) {
    return Events.matchingTrigger(s, rule, uid, E.isMonster);
  }
  function actions(s, db, options = {}) {
    const actions = [],
      rejected = [];
    for (const rule of (db.rules || []).filter(
      (r) => r.deckId === s.self.id && r.format === s.format &&
        (!options.prepared || r.id === options.prepared.ruleId),
    )) {
      try {
        validate(rule);
      } catch (e) {
        rejected.push({ id: rule.id, message: e.message });
        continue;
      }
      const used = rule.oncePerName ? "rule:cid:" + rule.actor.cid : "rule:" + rule.id;
      // El límite por nombre se reinicia por turno, pero no por nueva copia.
      if (!options.prepared && rule.oncePerTurn &&
        (rule.maxUsesPerTurn === undefined
          ? Timing.usedThisTurn(s, used)
          : (() => {
              const prior = s._ruleUseCounts?.[used];
              return prior?.turn === s.turn
                ? prior.count >= rule.maxUsesPerTurn
                : Timing.usedThisTurn(s, used); // unknown legacy use count: fail closed
            })())) continue;
      if (!options.prepared && rule.trigger && !s.tokens.some(t => triggerMatches(s, rule, t.uid))) continue;
      const f = Restrictions.effective(s, options);
      if (rule.activation && f.effectsNegated) continue;
      if (!options.prepared &&
        !rule.guards.every((g) =>
          g.kind === "count"
            ? (() => {
                const n = candidates(s, g.selector, db).length;
                return n >= g.min && n <= g.max;
              })()
            : g.kind === "distinctCount"
              ? new Set(candidates(s, g.selector, db).map(t => db.bindings[t.key])).size >= g.min
            : g.kind === "onlyCids"
              ? candidates(s, g.selector, db).every((t) => g.allowedCids.includes(db.bindings[t.key]))
            : g.kind === "event"
              ? Events.matchesGuard(s, g)
            : g.kind === "phaseStart"
              ? Timing.isPhaseStart(s)
            : g.kind === "extraSummonHistory"
              ? extraSummonWindow(s)
            : g.kind === "inventoryTarget"
              ? inventoryCandidates(s, db, g).length >= g.min
            : g.kind === "state"
              ? g.values.includes(
                  g.field === "normalAvailable" ? (s._normal === undefined ? s.normalAvailable : !s._normal) : s[g.field],
                )
              : !!f[g.flag] === g.value,
        )
      )
        continue;
      let bindings = options.prepared ? [options.prepared.refs] : candidates(s, rule.actor, db).map((t) => ({
        actor: t.uid,
      }));
      if (!options.prepared && rule.trigger) bindings=bindings.filter(b=>triggerMatches(s,rule,b.actor));
      for (const item of options.prepared ? [] : rule.select)
        bindings = bindings
          .flatMap((b) =>
            (item.selector.zones.includes("actor-materials")
              ? s.tokens.filter(t => t.zone === "material:" + b.actor)
              : candidates(s, item.selector, db))
              .filter((t) => {
                // A face-up own card may legally target itself only when
                // the verified rule explicitly opts into that target shape.
                if (Object.entries(b).some(([ref,uid]) =>
                  uid === t.uid && !(ref === "actor" && item.allowActor === true)))
                  return false;
                if (!rule.distinctSelectedNames) return true;
                const earlier = Object.entries(b).filter(([ref]) => ref !== "actor")
                  .map(([, uid]) => s.tokens.find((token) => token.uid === uid));
                // When every material selector names one distinct physical CID,
                // no permutation exists: an arbitrary UID ordering is not a
                // legality restriction. Preserve ordering for interchangeable
                // material pools to avoid duplicate combinations.
                const exactDifferentCids = rule.select.every((selection) =>
                  Number.isInteger(selection.selector?.cid)) &&
                  new Set(rule.select.map((selection) => selection.selector.cid)).size === rule.select.length;
                return earlier.every((other) => db.bindings[other.key] !== db.bindings[t.key]) &&
                  (!earlier.length || exactDifferentCids ||
                    t.uid > earlier[earlier.length - 1].uid);
              })
              .map((t) => ({ ...b, [item.ref]: t.uid })),
          )
          .slice(0, 40);
      for (const refs of bindings.slice(0, 40)) {
        // The two opponent field selections are unordered, not named targets:
        // canonicalize pair order without forbidding same-name cards.
        if(rule.actor.cid===16845 && refs.first && refs.second &&
          refs.first>refs.second)continue;
        const actor = s.tokens.find((t) => t.uid === refs.actor) ||
          (options.prepared?.actorKey ? { key: options.prepared.actorKey } : null);
        if (!actor) continue;
        if (rule.equippedToActorRef &&
          s.tokens.find((t) => t.uid === refs[rule.equippedToActorRef])?.equippedToUid !== actor.uid)
          continue;
        if (
          !options.prepared &&
          rule.activation &&
          field(actor.zone) &&
          (E.negated(s, actor) ||
            (E.isMonster(actor.zone) && actor.position === "set"))
        )
          continue;
        if (rule.activation && monsterEffectBlockedByTyphon(s, db, actor)) continue;
        // Las Mágicas Rápidas colocadas no pueden activarse ese mismo turno.
        // Sin procedencia temporal de la carta colocada, se falla cerrado.
        if (rule.activation && !Timing.canActivateSetSpellTrap(s, actor)) continue;
        let n = E.clone(s);
        n._normal ??= !n.normalAvailable;
        let opStage = "cost";
        function apply(op) {
          if (op.to && op.to !== "free-monster") {
            Transitions.assertDestination(op.to, options, f);
          }
          if (op.op === "shuffle") {
            const targets=Resources.requireTokens(n,refs,op.refs,t=>t.owner==="self" &&
              t.controller==="self" && ["hand","graveyard"].includes(t.zone),
              "Material de barajado fuera de mano/Cementerio propio");
            const cids=targets.map(t=>db.bindings[t.key]);
            check(new Set(cids).size===cids.length && cids.every(cid=>op.allowedCids.includes(cid)),
              "Nombres repetidos o materiales ajenos al requisito");
            for(const t of targets)n=E.place(n,{uid:t.uid,zone:"deck",controller:t.owner});
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"shuffle_distinct",cids,stage:opStage}];
          } else if (op.op === "link") {
            check(!(f.noExtra || f.specialBlocked || f.noMoreSummons || f.xyzOnly || f.machineSynchroOnly || heatWaveBlocks(f,db,op.cid)),"Link bloqueado por restricción de Extra, Especial u Oleada de Calor");
            check(!(options.blockedZones||[]).includes(op.to),"Zona de Link bloqueada");
            const selected=Resources.requireTokens(n,refs,op.materials,t=>t.owner==="self"&&
              t.controller==="self"&&E.isMonster(t.zone)&&t.position!=="set",
              "Material Link no disponible boca arriba en campo propio");
            const actual=selected.map(t=>db.bindings[t.key]).sort((a,b)=>a-b);
            const required=[...op.materialCids].sort((a,b)=>a-b);
            check(actual.every((cid,i)=>cid===required[i]),"Materiales Link no coinciden con receta exacta");
            const extraCard=n.self.cards.filter(c=>c.section==="extra"&&db.bindings[c.key]===op.cid);
            check(extraCard.length===1,"Link no encontrado de forma única en Extra");
            for(const t of selected)n=E.place(n,{uid:t.uid,zone:"graveyard",controller:t.owner});
            const previous=n.tokens.find(t=>t.owner==="self"&&t.key===extraCard[0].key&&t.zone==="extra");
            n=E.place(n,previous?{uid:previous.uid,zone:op.to,controller:"self"}:{key:extraCard[0].key,owner:"self",controller:"self",zone:op.to});
            const summoned=n.tokens.find(t=>t.key===extraCard[0].key&&t.zone===op.to);
            n=E.position(n,summoned.uid,"attack");
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"link_summon",cid:op.cid,uid:summoned.uid,
              materials:selected.map(t=>t.uid),usedExtraDeckMaterial:selected.some(t=>
                E.definition(n,t.key,t.owner)?.section==="extra"),
              side:"self",turn:n.turn,captureRevision:n._captureRevision,stage:opStage}];
          } else if (op.op === "xyz") {
            check(!(f.noExtra || f.specialBlocked || f.noMoreSummons || f.machineSynchroOnly || heatWaveBlocks(f,db,op.cid)),"Xyz bloqueado por restricción de Extra, Especial u Oleada de Calor");
            check(!(options.blockedZones||[]).includes(op.to),"Zona Extra bloqueada");
            const selected=Resources.requireTokens(n,refs,op.materials,t=>t.owner==="self"&&
              t.controller==="self"&&E.isMonster(t.zone)&&t.position!=="set",
              "Material Xyz no disponible boca arriba en campo propio");
            const actual=selected.map(t=>db.bindings[t.key]).sort((a,b)=>a-b);
            const required=[...op.materialCids].sort((a,b)=>a-b);
            check(actual.every((cid,i)=>cid===required[i]),"Materiales Xyz no coinciden con receta exacta");
            const extraCard=n.self.cards.filter(c=>c.section==="extra"&&db.bindings[c.key]===op.cid);
            check(extraCard.length===1,"Xyz no encontrado de forma única en Extra");
            const previous=n.tokens.find(t=>t.owner==="self"&&t.key===extraCard[0].key&&t.zone==="extra");
            n=E.place(n,previous?{uid:previous.uid,zone:op.to,controller:"self"}:{key:extraCard[0].key,owner:"self",controller:"self",zone:op.to});
            const summoned=n.tokens.find(t=>t.key===extraCard[0].key&&t.zone===op.to);
            n=E.position(n,summoned.uid,"attack");
            for(const t of selected)n=E.place(n,{uid:t.uid,zone:"material:"+summoned.uid,controller:"self"});
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"xyz_summon",cid:op.cid,uid:summoned.uid,materials:selected.map(t=>t.uid),turn:n.turn,stage:opStage}];
          } else if (op.op === "xyzOverlay") {
            check(!(f.noExtra || f.specialBlocked || f.noMoreSummons || f.machineSynchroOnly || heatWaveBlocks(f,db,op.cid)),
              "Superposición bloqueada por restricción de Invocaciones");
            check(extraSummonWindow(n) && Timing.isOwnMainPhase(n),
              "TY-PHON exige dos Invocaciones rivales del Extra en un mismo turno válido");
            const selected=n.tokens.find(t => t.uid === refs[op.material]);
            check(selected && selected.owner === "self" && selected.controller === "self" &&
              E.isMonster(selected.zone) && selected.position !== "set",
              "La superposición exige monstruo propio boca arriba en campo");
            const ownMonsters=n.tokens.filter(t => t.controller === "self" && E.isMonster(t.zone) &&
              t.position !== "set");
            check(ownMonsters.every(t => Number.isInteger(t.currentATK)),
              "ATK actual de cada monstruo propio debe declararse para comparar");
            check(selected.currentATK === Math.max(...ownMonsters.map(t => t.currentATK)),
              "TY-PHON debe superponerse sobre un monstruo de mayor ATK");
            check(!n.tokens.some(t => t.uid !== selected.uid && t.zone === op.to &&
              t.controller === "self"), "Zona de destino ocupada");
            check(!(options.blockedZones || []).includes(op.to),"Zona de superposición bloqueada");
            const extraCard=n.self.cards.filter(c => c.section === "extra" && db.bindings[c.key] === op.cid);
            const available=extraCard.length === 1 && n.tokens.find(t => t.key === extraCard[0].key &&
              t.owner === "self" && t.zone === "extra");
            check(extraCard.length === 1 && (available || E.remaining(n, extraCard[0].key,"self") > 0),
              "TY-PHON no disponible en Extra propio");
            const typhonUid=available ? available.uid : n.nextId++;
            const oldZone=selected.zone;
            for (const attached of n.tokens.filter(t => t.zone === "material:" + selected.uid))
              attached.zone="material:"+typhonUid;
            selected.zone="material:"+typhonUid;
            delete selected.currentATK;
            if(available) Object.assign(available,{zone:op.to,controller:"self",position:"attack",
              xyzSummoned:true,currentATK:2900});
            else n.tokens.push({uid:typhonUid,key:extraCard[0].key,owner:"self",controller:"self",
              zone:op.to,position:"attack",xyzSummoned:true,currentATK:2900});
            n=Restrictions.apply(n,"noMoreSummons",true,"turn");
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"xyz_summon",cid:19184,
              uid:typhonUid,materials:[selected.uid],turn:n.turn,side:"self",
              method:"typhon-alternate",from:oldZone,stage:opStage}];
          } else if (op.op === "necroquipSummon") {
            check(!(f.noExtra||f.specialBlocked||f.noMoreSummons||f.xyzOnly||
              f.machineSynchroOnly||f.graveyardBlocked),
              "Invocación por procedimiento de Princesa bloqueada");
            const [engraver,equip,fiend]=Resources.requireTokens(n,refs,op.refs,t=>
              t.owner==="self"&&t.controller==="self",
              "Material de Princesa no pertenece al jugador");
            check(engraver&&engraver.uid===refs.actor&&
              db.bindings[engraver.key]===20196&&E.isMonster(engraver.zone)&&
              engraver.position!=="set"&&
              equip&&db.bindings[equip.key]===20225&&equip.zone==="s1"&&
              equip.equippedToUid===engraver.uid &&
              fiend&&db.bindings[fiend.key]===19366&&fiend.zone==="hand",
              "Princesa exige Grabador equipado físicamente con Réquiem y Goblin de mano");
            check(!n.tokens.some(t=>db.bindings[t.key]===20423 &&
              E.isMonster(t.zone)&&t.controller==="self"),
              "Sólo puedes controlar una Princesa Necroequipo");
            const extra=n.self.cards.find(c=>db.bindings[c.key]===20423 &&
              c.section==="extra"&&E.remaining(n,c.key,"self")>0);
            check(extra,"No existe Princesa física disponible en Extra Deck");
            Transitions.assertVacant(n,"self",op.to);
            const names=[engraver.uid,equip.uid,fiend.uid];
            for(const t of [engraver,equip,fiend]){
              n=E.place(n,{uid:t.uid,zone:"graveyard",controller:"self"});
              const physical=n.tokens.find(x=>x.uid===t.uid);
              if(physical)delete physical.equippedToUid;
            }
            n=E.place(n,{key:extra.key,owner:"self",controller:"self",zone:op.to});
            const princess=n.tokens.find(t=>t.key===extra.key&&t.zone===op.to);
            n=E.position(n,princess.uid,"attack");
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"necroquip_contact_summon",
              cid:20423,uid:princess.uid,materials:names,turn:n.turn,stage:opStage}];
          } else if (op.op === "fusion") {
            check(!(f.noExtra || f.specialBlocked || f.noMoreSummons || f.xyzOnly || heatWaveBlocks(f,db,op.cid)), "Fusión bloqueada por restricción de Extra, Especial u Oleada de Calor");
            check(!(options.blockedZones || []).includes(op.to), "Zona Extra bloqueada");
            const selected = Resources.requireTokens(n,refs,op.materials,t=>t.owner==="self" &&
              t.controller==="self" && (t.zone==="hand" || E.isMonster(t.zone) ||
                (op.allowGraveyard && t.zone==="graveyard")),
              "Material no disponible en zona autorizada");
            if (selected.some(t=>t.zone==="graveyard"))
              check(op.allowGraveyard && (op.graveyardPolicy === "fiendsmith-sequence" ||
                (n._ruleEvents||[]).some(e=>e.kind==="destroy" && e.turn===n.turn &&
                  e.side==="self" && e.by==="effect")),
                "Material del Cementerio requiere destrucción propia por efecto este turno");
            if (op.graveyardPolicy === "fiendsmith-sequence")
              check(selected.every(t=>t.zone==="graveyard") &&
                op.materialCids.every(cid=>[20196,19366].includes(cid)),
                "Secuencia requiere dos Demonios fijados en Cementerio");
            if (op.requiredOrigins === "hand-and-field")
              check(selected.some(t=>t.zone==="hand") && selected.some(t=>E.isMonster(t.zone)),
                "La receta exige material de mano y de campo");
            if (op.distinctCids)
              check(new Set(selected.map(t=>db.bindings[t.key])).size===selected.length,
                "La receta exige nombres distintos");
            const actual = selected.map(t=>db.bindings[t.key]).sort((a,b)=>a-b);
            const required = [...op.materialCids].sort((a,b)=>a-b);
            check(actual.every((cid,i)=>cid===required[i]), "Materiales no coinciden con la receta exacta");
            const fromHand=selected.filter(t=>t.zone==="hand").length;
            const fromField=selected.filter(t=>E.isMonster(t.zone)).length;
            const extraCard=n.self.cards.filter(c=>c.section==="extra" && db.bindings[c.key]===op.cid);
            check(extraCard.length===1,"Monstruo de Fusión no encontrado de forma única en el Extra");
            for(const t of selected)n=E.place(n,{uid:t.uid,
              zone:op.allowGraveyard && t.zone==="graveyard"?"deck":"graveyard",controller:t.owner});
            const previous=n.tokens.find(t=>t.owner==="self" && t.key===extraCard[0].key && t.zone==="extra");
            n=E.place(n,previous?{uid:previous.uid,zone:op.to,controller:"self"}:{key:extraCard[0].key,owner:"self",controller:"self",zone:op.to});
            const summoned=n.tokens.find(t=>t.key===extraCard[0].key && t.zone===op.to);
            n=E.position(n,summoned.uid,"attack");
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"fusion_summon",cid:op.cid,uid:summoned.uid,
              materials:selected.map(t=>t.uid),fromHand,fromField,
              sourceCid:db.bindings[actor.key],captureRevision:n._captureRevision,
              turn:n.turn,stage:opStage}];
          } else if (op.op === "destroy") {
            const t = n.tokens.find((t) => t.uid === refs[op.ref]);
            check(t && (field(t.zone) || t.zone === "hand"), "Objetivo de destrucción fuera de campo/mano");
            check(!(f.graveyardBlocked), "Destrucción hacia Cementerio bloqueada");
            const event = {kind:"destroy",uid:t.uid,cid:db.bindings[t.key],from:t.zone,
              side:t.controller,owner:t.owner,turn:n.turn,by:"effect",stage:opStage};
            n = E.place(n, {uid:t.uid,zone:"graveyard",controller:t.owner});
            n._ruleEvents = [...(n._ruleEvents||[]),event];
          } else if (op.op === "tribute") {
            const [t]=Resources.requireTokens(n,refs,[op.ref],t=>t.controller==="self" &&
              (E.isMonster(t.zone) && (t.position!=="set" || op.allowSet === true) ||
                op.allowHand===true && t.zone==="hand"),
              "Sacrificio exige monstruo propio boca arriba en campo");
            check(!f.graveyardBlocked,"Destino del coste de Sacrificio no acreditado");
            const event={kind:"tribute",uid:t.uid,cid:db.bindings[t.key],from:t.zone,by:"cost",stage:opStage};
            n=E.place(n,{uid:t.uid,zone:"graveyard",controller:t.owner});
            n._ruleEvents=[...(n._ruleEvents||[]),event];
          } else if (op.op === "changeControl") {
            const t=n.tokens.find(t=>t.uid===refs[op.ref]);
            check(t && E.isMonster(t.zone) && t.position!=="set",
              "Cambiar control exige monstruo boca arriba en campo");
            try { Transitions.assertVacant(n,op.to,t.zone,t.uid); }
            catch { throw Error("Zona ocupada por el nuevo controlador"); }
            n=E.place(n,{uid:t.uid,zone:t.zone,controller:op.to});
          } else if (op.op === "equip") {
            const t=n.tokens.find(t=>t.uid===refs[op.ref]);
            const target=n.tokens.find(t=>t.uid===refs[op.target]);
            check(t && t.owner==="self" && t.zone==="graveyard" &&
              target && target.controller==="self" && E.isMonster(target.zone) &&
              target.position!=="set" && db.bindings[target.key]===op.targetCid &&
              !n.tokens.some(x=>x.controller==="self"&&x.zone===op.to),
              "Réquiem requiere Cementerio, Grabador visible y zona de equipo libre");
            n=E.place(n,{uid:t.uid,zone:op.to,controller:"self"});
            n.tokens.find(x=>x.uid===t.uid).equippedToUid=target.uid;
          } else if (op.op === "counter") {
            const t=n.tokens.find(t=>t.uid===refs[op.ref]);
            check(t && E.isMonster(t.zone) && t.position!=="set",
              "Contador requiere monstruo boca arriba en campo");
            t.counters={...(t.counters||{}),predator:(t.counters?.predator||0)+1};
          } else if (op.op === "banishExtraFaceDown") {
            check(!f.banishBlocked,"Lancea impide desterrar los seis costes del Extra");
            const targets=Resources.requireTokens(n,refs,op.refs,t=>t.owner==="self" &&
              t.zone==="extra" && E.definition(n,t.key,t.owner)?.section==="extra",
              "Se requieren seis cartas físicas distintas del Extra propio");
            for(const t of targets){
              n=E.place(n,{uid:t.uid,zone:"banished",controller:"self"});
              n=E.position(n,t.uid,"set");
            }
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"banish_extra_face_down_cost",
              uids:targets.map(t=>t.uid),turn:n.turn,side:"self",stage:opStage}];
          } else if (op.op === "move") {
            const t = n.tokens.find((t) => t.uid === refs[op.ref]);
            check(t, "Copia ausente");
            if(op.summonMethod){
              check(t.owner==="self" && t.controller==="self" &&
                ["hand","graveyard"].includes(t.zone) && !f.specialBlocked && !f.noMoreSummons &&
                !heatWaveBlocks(f,db,db.bindings[t.key]),
                "Invocación Especial desde zona no acreditada o bloqueada");
            }
            const destination = op.to === "free-monster"
              ? ["m1","m2","m3","m4","m5"].find(zone =>
                  !(options.blockedZones || []).includes(zone) &&
                  !n.tokens.some(token => token.controller === "self" && token.zone === zone))
              : op.to;
            check(destination, "No hay zona de monstruos propia libre");
            Transitions.assertDestination(destination, options, f);
            // Returning an Xyz monster to its owner's Extra Deck sends its
            // remaining physically attached materials to their owners' GYs.
            if ((destination === "extra" || destination === "hand") && E.isMonster(t.zone))
              for (const attached of n.tokens.filter(token => token.zone === "material:" + t.uid))
                n = E.place(n,{uid:attached.uid,zone:"graveyard",controller:attached.owner});
            n = E.place(n, {
              uid: t.uid,
              zone: destination,
              controller: Transitions.destinationController(t, destination),
            });
            if (n.tokens.some((x) => x.uid === t.uid))
              n = E.position(n, t.uid, op.position);
            if(op.summonMethod) n._ruleEvents=[...(n._ruleEvents||[]),
              {kind:"summon",method:op.summonMethod,uid:t.uid,
                cid:db.bindings[t.key],turn:n.turn,stage:opStage}];
          } else if (op.op === "take") {
            if (op.summonMethod) check(!f.specialBlocked && !heatWaveBlocks(f,db,op.cid),
              "Invocación desde Deck bloqueada por restricción de Especial");
            check(
              !(f.noDeckToHand && op.from === "deck" && op.to === "hand"),
              "La restriccion de añadir del Deck impide esta resolucion",
            );
            check(
              !(op.from === "extra" && (E.isMonster(op.to) || op.to === "hand")),
              "Invocacion o retorno desde Extra requiere un procedimiento de materiales acreditado",
            );
            // Unknown own draws may contain any Main card. Until identified,
            // a later search/mill cannot safely claim a specific copy is in Deck.
            check(!(op.side==="self" && op.from==="deck" && n.selfUnknownHand>0),
              "Robo propio de identidad desconocida: identifica la carta antes de buscar o enviar desde Deck");
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
            const destination = op.to === "free-monster"
              ? ["m1","m2","m3","m4","m5"].find(zone =>
                  !(options.blockedZones || []).includes(zone) &&
                  !n.tokens.some(token => token.controller === "self" && token.zone === zone))
              : op.to;
            check(destination, "No hay zona propia disponible para Invocación Especial");
            Transitions.assertDestination(destination, options, f);
            n = E.place(
              n,
              t
                ? { uid: t.uid, zone: destination, controller: op.side }
                : {
                    key: c.key,
                    owner: op.side,
                    controller: op.side,
                    zone: destination,
                  },
            );
            // A cause belongs to the particular physical copy moved by this
            // operation, not an older copy with the same CID already in GY.
            const movedUid = t ? t.uid : n.nextId - 1;
            n = E.position(n, movedUid, op.position);
            if (op.cannotAttackThisTurn) {
              const summoned=n.tokens.find(t=>t.uid===movedUid);
              check(summoned && E.isMonster(summoned.zone),
                "Restricción de ataque requiere Invocación real");
              summoned.attackLockedTurn=n.turn;
            }
            if (op.cause === "monster_effect") {
              const sent=n.tokens.find(x=>x.uid===movedUid && x.key===c.key &&
                x.zone==="graveyard" && x.owner===op.side);
              check(sent,"Carta enviada por efecto no identificada");
              n._ruleEvents=[...(n._ruleEvents||[]),{kind:"sent_to_graveyard",uid:sent.uid,cid:op.cid,sourceCid:db.bindings[actor.key],turn:n.turn,stage:opStage}];
            }
            if (op.summonMethod) {
              check(!f.specialBlocked && !f.noMoreSummons,"Invocación Especial desde Deck bloqueada");
              const summoned=n.tokens.find(x=>x.key===c.key && x.zone===destination && x.owner===op.side);
              n._ruleEvents=[...(n._ruleEvents||[]),{kind:"summon",method:"special",
                uid:summoned.uid,cid:op.cid,side:op.side,turn:n.turn,stage:opStage}];
            }
          } else if (op.op === "payLP") {
            n.lp = Resources.applyLP(n.lp, op);
          } else if (op.op === "damage") {
            n.lp = Resources.applyLP(n.lp, op);
          } else if (op.op === "temporaryBanishPair") {
            const witness=(n._ruleEvents||[]).find(e=>
              e.kind==="opponent_activation"&&e.side==="opponent" &&
              e.turn===n.turn&&e.captureRevision===n._captureRevision &&
              n.tokens.some(t=>t.uid===e.uid&&t.owner==="opponent"));
            check(witness && !f.banishBlocked && n.active==="opponent",
              "S:P requiere activación rival observada y destierro permitido");
            const pair=Resources.requireTokens(n,refs,op.refs,t=>
              E.isMonster(t.zone)&&t.position!=="set",
              "Se requieren dos monstruos boca arriba en Campo");
            check(pair.length===2 && pair[0].controller==="self" &&
              db.bindings[pair[0].key]===19188 &&
              pair[1].controller==="opponent",
              "S:P debe desterrar propia S:P y un monstruo rival real");
            const record=pair.map(t=>({uid:t.uid,controller:t.controller,
              position:t.position,owner:t.owner}));
            for(const item of record){
              n=E.place(n,{uid:item.uid,zone:"banished",controller:item.owner});
              n.tokens.find(t=>t.uid===item.uid).temporaryReturn={
                turn:n.turn,controller:item.controller,position:item.position,
                sourceCid:19188};
            }
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"temporary_banish",
              sourceCid:19188,uids:record.map(t=>t.uid),turn:n.turn,
              stage:opStage}];
          } else if (op.op === "drawUnknown") {
            const event=(n._ruleEvents||[]).find(e=>e.kind==="fusion_summon" &&
              e.uid===refs.actor && e.cid===16845 && e.sourceCid===19904 &&
              e.fromHand===1 && e.fromField===2 && e.turn===n.turn &&
              e.captureRevision===n._captureRevision);
            check(event,"Quimera requiere Fusión con Mágica y un material de mano y dos del Campo en esta ventana");
            const ownMain=n.self.cards.filter(c=>c.section==="main").reduce((v,c)=>v+c.quantity,0);
            const known=n.tokens.filter(t=>t.owner==="self" &&
              E.definition(n,t.key,t.owner)?.section==="main").length;
            check(ownMain-known-(n.selfUnknownHand||0)>=op.count,"Main sin cartas físicas suficientes para robar");
            n.selfUnknownHand=(n.selfUnknownHand||0)+op.count;
            n._ruleEvents=[...(n._ruleEvents||[]),{kind:"draw_unknown",side:"self",
              count:op.count,sourceCid:16845,turn:n.turn,stage:opStage}];
          } else if (op.op === "damagePerBanishedMonsters") {
            const visibleBanished = n.tokens.filter(t=>t.owner==="self" &&
              t.zone==="banished" && t.position!=="set");
            let count=0;
            for(const token of visibleBanished){
              const kind=db.facts?.[db.bindings[token.key]]?.kind;
              check(["monster","spell","trap"].includes(kind),
                "No se puede verificar si una carta desterrada boca arriba es monstruo");
              if(kind==="monster")count++;
            }
            if(count>0)n.lp=Resources.applyLP(n.lp,{
              op:"damage",side:op.side,amount:count*op.perMonster});
            n._ruleEvents=[...(n._ruleEvents||[]),{
              kind:"aero_shark_damage",cid:9657,monsters:count,damage:count*op.perMonster,
              turn:n.turn,stage:opStage}];
          } else if (op.op === "recoverLP") {
            n.lp = Resources.applyLP(n.lp, op);
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
            if (op.available === false) {
              check(!n._normal, "Normal usada");
              n._normal = true;
            } else {
              check(n._normal, "No se ha consumido la Normal que se recupera");
              n._normal = false;
            }
            n.normalAvailable = !n._normal;
          } else if (op.op === "summon") {
            const t=n.tokens.find(t=>t.uid===refs[op.ref]);
            check(t && t.owner==="self" && t.controller==="self" && t.zone===op.from,
              "La copia a Invocar no está en la mano propia");
            try { Transitions.assertVacant(n,"self",op.to,t.uid); }
            catch { throw Error("Zona de Invocación ocupada"); }
            if(op.method==="normal") {
              check(!heatWaveBlocks(f,db,db.bindings[t.key]),
                "Oleada de Calor impide Invocación Normal de monstruo de efecto");
              check(Timing.isOwnMainPhase(n) && !n._normal,
                "Invocación Normal indisponible en esta ventana");
              check(!f.noMoreSummons,"TY-PHON impide Invocaciones Normales restantes");
              n._normal=true;
              n.normalAvailable=false;
            } else check(!f.specialBlocked && !f.noMoreSummons &&
              !heatWaveBlocks(f,db,db.bindings[t.key]),"Invocación Especial bloqueada");
            n=E.place(n,{uid:t.uid,zone:op.to,controller:"self"});
            n=E.position(n,t.uid,op.position);
            n._ruleEvents=[...(n._ruleEvents||[]),
              {kind:"summon",method:op.method,uid:t.uid,cid:db.bindings[t.key],turn:n.turn,stage:opStage}];
          } else {
            n = Restrictions.apply(n, op.flag, op.value, op.duration || "persistent");
          }
        }
        try {
          if (!options.prepared) for (const op of rule.costs) apply(op);
          const activationNegated=(options.negateActivationRuleIds||[]).includes(rule.id);
          check(!activationNegated || (rule.activation && typeof rule.activationNegatedConsumesUse === "boolean"),
            "La negación de activación exige el límite explícito de esa carta");
          if (!options.prepared && rule.oncePerTurn && (!activationNegated || rule.activationNegatedConsumesUse)) {
            n._used = [...new Set([...(n._used || []), used])];
            n._ruleUseTurns = { ...n._ruleUseTurns, [used]: n.turn };
            if (rule.maxUsesPerTurn !== undefined) {
              const previous = n._ruleUseCounts?.[used];
              n._ruleUseCounts = { ...(n._ruleUseCounts || {}),
                [used]: { turn:n.turn, count:(previous?.turn === n.turn ? previous.count : 0) + 1 } };
            }
          }
          const paid = checkpoint(n);
          const effectNegated = (options.negateEffectRuleIds || []).includes(rule.id);
          check(!rule.cannotBeNegated || !(activationNegated || effectNegated),
            "No se puede negar la activación ni el efecto de Gordian Slicer");
          check(!(activationNegated&&effectNegated),"No se puede negar simultáneamente activación y efecto");
          opStage = "resolve";
          if (!options.deferResolution && !effectNegated&&!activationNegated)
            for (const op of rule.resolve) apply(op);
          // Post-chain cleanup is not a cost or the card's effect: an
          // activated Normal Spell goes to the GY even if its effect is negated.
          if (!options.deferResolution && rule.afterResolution)
            for (const op of rule.afterResolution) apply(op);
          const summoned = n.tokens.filter(
            (t) =>
              E.isMonster(t.zone) &&
              !s.tokens.some((b) => b.uid === t.uid && E.isMonster(b.zone)),
          );
          const normal = rule.costs
            .concat(rule.resolve)
            .some((op) => op.op === "normal" || (op.op === "summon" && op.method === "normal"));
          check(!summoned.some(t => heatWaveBlocks(f,db,db.bindings[t.key])),
            "Oleada de Calor impide Invocaciones de monstruos de efecto");
          check(
            !(
              (f.specialBlocked || f.noMoreSummons) &&
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
          Integrity.validate(n);
          if (!options.prepared && ["main1","main2"].includes(n.phase)) {
            n._rulePhaseActions=Timing.countPhaseAction(n);
          }
          actions.push({
            state: n,
            label: activationNegated?`Activación negada · ${rule.label}`:effectNegated ? `Efecto negado · ${rule.label}` : rule.label,
            ids: Object.values(refs),
            used: rule.oncePerTurn ? used : null,
            source: rule.source,
            paid,
            ...(options.deferResolution ? { prepared: {
              ruleId: rule.id, refs: E.clone(refs), actorKey: actor.key,
            } } : {}),
            event: {
              activation: rule.activation,
              actor: refs.actor,
              tags: rule.tags,
              assumptions: rule.assumptions,
              ruleId: rule.id,
              effectNegated,
              activationNegated,
            },
          });
        } catch (e) {
          rejected.push({ id: rule.id, message: e.message });
        }
      }
    }
    return { actions, rejected };
  }
  return { validate, actions, checkpoint, matches, inventoryCandidates,
    monsterEffectBlockedByTyphon };
});
