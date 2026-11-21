(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoPlayEngine = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const VERSION = 1,
    SIDES = ["self", "opponent"];
  const norm = (v) =>
    String(v ?? "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, " ")
      .trim();
  const clone = (v) => JSON.parse(JSON.stringify(v));
  const assert = (ok, message) => {
    if (!ok) throw new Error(message);
  };
  function signature(value) {
    let n = 2166136261;
    for (const c of JSON.stringify(value)) {
      n ^= c.charCodeAt(0);
      n = Math.imul(n, 16777619);
    }
    return (n >>> 0).toString(16);
  }
  function catalog(deck, rows) {
    const cards = rows
      .filter(
        (r) =>
          Number(r.deck_id) === Number(deck.id) &&
          ["main", "extra"].includes(String(r.section || "main").toLowerCase()),
      )
      .map((r, i) => ({
        key: `${Number(deck.id)}:${Number(r.id) || i + 1}`,
        name: String(r.card_name || "")
          .trim()
          .slice(0, 250),
        quantity: r.quantity == null ? 1 : Number(r.quantity),
        section: String(r.section || "main").toLowerCase(),
        imageId: Number(r.ygoprodeck_id) || 0,
        role: String(r.role || "").slice(0, 500),
      }));
    assert(
      cards.every(
        (c) =>
          c.name &&
          Number.isInteger(c.quantity) &&
          c.quantity > 0 &&
          c.quantity <= 99,
      ),
      "La lista guardada tiene cantidades o nombres inválidos.",
    );
    assert(
      new Set(cards.map((c) => c.key)).size === cards.length,
      "La lista contiene identificadores de carta duplicados.",
    );
    return {
      id: Number(deck.id),
      name: String(deck.name),
      formats: deck.format_codes || [],
      cards,
      signature: signature(cards),
    };
  }
  function commonFormat(a, b) {
    return a.formats.find(
      (f) =>
        ["tcg_advanced", "tcg_traditional", "ocg", "ocg_advanced", "goat", "rush_duel"].includes(f) &&
        b.formats.includes(f),
    );
  }
  function create(self, opponent) {
    const practice = !opponent;
    opponent ||= { id: 0, name: "Práctica libre", formats: clone(self.formats), cards: [], signature: signature([]) };
    const format = commonFormat(self, opponent);
    assert(format, "Los decks deben compartir formato.");
    assert(
      self.cards.some((c) => c.section === "main"),
      "Tu deck necesita un Main Deck cargado.",
    );
    return {
      version: VERSION,
      ...(practice ? { practice: true } : {}),
      self: clone(self),
      opponent: clone(opponent),
      format,
      turn: 1,
      goesFirst: true,
      active: "self",
      phase: "main1",
      normalAvailable: true,
      lp: { self: 8000, opponent: 8000 },
      unknown: { hand: 0, banished: 0 },
      // Unidentified draws from OUR deck, never the opponent unknown.hand counter.
      selfUnknownHand: 0,
      opponentHandComplete: false,
      notes: "",
      tokens: [],
      nextId: 1,
      confirmed: [],
      usedEffects: "",
      chain: [],
    };
  }
  const allCards = (s) => [...s.self.cards, ...s.opponent.cards];
  const definition = (s, key, owner) =>
    key === "unknown" && owner === "opponent"
      ? {
          key,
          name: "Carta rival desconocida",
          quantity: 99,
          section: "unknown",
        }
      : s[owner]?.cards.find((c) => c.key === key);
  function zones(s) {
    const count = s.format === "rush_duel" ? 3 : 5;
    return [
      "hand",
      "graveyard",
      "banished",
      "extra",
      "field",
      ...Array.from({ length: count }, (_, i) => "m" + (i + 1)),
      ...Array.from({ length: count }, (_, i) => "s" + (i + 1)),
      ...(["tcg_advanced", "tcg_traditional", "ocg", "ocg_advanced"].includes(s.format) ? ["emz1", "emz2"] : []),
    ];
  }
  const isMonster = (z) => /^m[1-5]$|^emz[12]$/.test(z);
  const fixed = (z) => /^[ms][1-5]$|^emz[12]$|^field$/.test(z);
  function remaining(s, key, owner) {
    const card = definition(s, key, owner);
    return card
      ? card.quantity -
          s.tokens.filter((t) => t.key === key && t.owner === owner).length
      : 0;
  }
  function validate(s) {
    assert(
      s && s.version === VERSION && s.self && s.opponent,
      "Archivo de escenario incompatible.",
    );
    assert(
      commonFormat(s.self, s.opponent) === s.format,
      "Formato de escenario incompatible.",
    );
    if(s.effectUsage!==undefined)assert(Array.isArray(s.effectUsage)&&s.effectUsage.length<=1800&&s.effectUsage.every(e=>Number.isInteger(e.uid)&&e.uid>0&&typeof e.key==='string'&&Number.isInteger(e.slot)&&e.slot>=1&&e.slot<=6&&typeof e.id==='string'&&e.id.length<=80&&Number.isInteger(e.turn)&&e.turn>0),'Efectos utilizados inválidos.');
    if (s.chain !== undefined)
      assert(Array.isArray(s.chain) && s.chain.length <= 20 &&
        s.chain.every((link, i) => link.link === i + 1 &&
          SIDES.includes(link.side) &&
          typeof link.cardName === "string" && link.cardName.length <= 250 &&
          typeof link.effect === "string" && link.effect.trim().length > 0 && link.effect.length <= 300 &&
          (link.ruleId === undefined || (typeof link.ruleId === "string" && link.ruleId.length <= 100 && link.ruleId.length > 0)) &&
          (link.actorUid === undefined || (Number.isInteger(link.actorUid) && link.actorUid > 0)) &&
          (link.outcome === undefined || ["negate-effect", "negate-activation"].includes(link.outcome))),
      "Cadena pendiente inválida.");
    for (const side of SIDES) {
      assert(
        Number.isInteger(s[side].id) &&
          (s[side].id > 0 || (s.practice === true && side === "opponent" && s[side].id === 0)) &&
          Array.isArray(s[side].cards),
        "Participante inválido.",
      );
      assert(
        new Set(s[side].cards.map((c) => c.key)).size === s[side].cards.length,
        "Cartas duplicadas en el catálogo.",
      );
      for (const c of s[side].cards)
        assert(
          typeof c.key === "string" &&
            typeof c.name === "string" &&
            c.name.length <= 250 &&
            Number.isInteger(c.quantity) &&
            c.quantity > 0 &&
            c.quantity <= 99,
          "Carta de catálogo inválida.",
        );
    }
    assert(
      Number.isInteger(s.turn) &&
        s.turn >= 1 &&
        s.turn <= 999 &&
        SIDES.includes(s.active),
      "Turno inválido.",
    );
    assert(
      ["draw", "standby", "main1", "battle", "main2", "end"].includes(
        s.phase,
      ) && !(s.format === "rush_duel" && s.phase === "main2"),
      "Fase inválida.",
    );
    assert(
      typeof s.normalAvailable === "boolean" &&
        typeof s.goesFirst === "boolean" &&
        typeof s.opponentHandComplete === "boolean",
      "Opciones de turno inválidas.",
    );
    assert(
      s.lp &&
        SIDES.every(
          (side) =>
            Number.isInteger(s.lp[side]) &&
            s.lp[side] >= 0 &&
            s.lp[side] <= 999999,
        ),
      "LP inválidos.",
    );
    assert(
      s.unknown &&
        ["hand", "banished"].every(
          (z) =>
            Number.isInteger(s.unknown[z]) &&
            s.unknown[z] >= 0 &&
            s.unknown[z] <= 99,
        ),
      "Cantidad de cartas desconocidas inválida.",
    );
    if (s.selfUnknownHand !== undefined) {
      const ownMain = s.self.cards.filter(c=>c.section==="main").reduce((n,c)=>n+c.quantity,0);
      const knownMain = s.tokens.filter(t=>t.owner==="self" &&
        s.self.cards.some(c=>c.key===t.key && c.section==="main")).length;
      assert(Number.isInteger(s.selfUnknownHand) && s.selfUnknownHand>=0 &&
        s.selfUnknownHand<=99 && knownMain+s.selfUnknownHand<=ownMain,
        "Robo propio desconocido excede las cartas disponibles del Main.");
    }
    assert(
      typeof s.notes === "string" &&
        s.notes.length <= 4000 &&
        typeof s.usedEffects === "string" &&
        s.usedEffects.length <= 2000,
      "Notas inválidas.",
    );
    assert(
      Array.isArray(s.tokens) &&
        s.tokens.length <= 300 &&
        Array.isArray(s.confirmed) &&
        s.confirmed.every((x) => typeof x === "string" && x.length <= 150),
      "Escenario demasiado grande o inválido.",
    );
    if (s.practice) assert(s.opponent.id === 0 && s.opponent.cards.length === 0 &&
      s.unknown.hand === 0 && s.unknown.banished === 0 &&
      s.tokens.every(t => t.owner === "self" && t.controller === "self"),
      "La práctica libre solo utiliza tus cartas y tu lado del tablero.");
    const ids = new Set();
    for (const t of s.tokens) {
      if (t.currentATK !== undefined)
        assert(Number.isInteger(t.currentATK) && t.currentATK >= 0 && t.currentATK <= 999999,
          "ATK actual del monstruo no acreditado.");
      if (t.setTurn !== undefined)
        assert(Number.isInteger(t.setTurn) && t.setTurn > 0 &&
          /^s[1-5]$/.test(t.zone) && t.position === "set",
        "Turno de colocación inválido.");
      if(t.negation){assert(typeof t.negation.active==='boolean'&&['turn','manual'].includes(t.negation.duration)&&Number.isInteger(t.negation.turn)&&t.negation.turn>0&&typeof t.negation.reason==='string'&&t.negation.reason.length<=300,'Estado de negación inválido.');}
      assert(
        Number.isInteger(t.uid) && t.uid > 0 && !ids.has(t.uid),
        "Identificador de copia inválido.",
      );
      ids.add(t.uid);
      assert(
        SIDES.includes(t.owner) &&
          SIDES.includes(t.controller) &&
          definition(s, t.key, t.owner),
        "La carta no pertenece a los participantes.",
      );
      assert(
        zones(s).includes(t.zone) || /^material:\d+$/.test(t.zone),
        "Zona inexistente para este formato.",
      );
      assert(
        ["attack", "defense", "set"].includes(t.position),
        "Posición inválida.",
      );
    }
    for (const side of SIDES)
      for (const c of s[side].cards)
        assert(
          remaining(s, c.key, side) >= 0,
          "No puedes usar más copias de " + c.name + " que las guardadas.",
        );
    assert(
      s.tokens.filter((t) => t.owner === "opponent").length +
        s.unknown.hand +
        s.unknown.banished <=
        s.opponent.cards.reduce((n, c) => n + c.quantity, 0),
      "La información rival supera el total de cartas de su lista.",
    );
    for (const t of s.tokens) {
      if (fixed(t.zone))
        assert(
          s.tokens.filter(
            (x) =>
              x.zone === t.zone &&
              (t.zone.startsWith("emz") || x.controller === t.controller),
          ).length === 1,
          "La zona está ocupada.",
        );
      if (t.zone.startsWith("material:")) {
        const host = s.tokens.find(
          (x) => x.uid === Number(t.zone.split(":")[1]),
        );
        assert(
          host && host.uid !== t.uid && isMonster(host.zone),
          "El material necesita un monstruo presente en el campo.",
        );
      }
    }
    assert(
      Number.isInteger(s.nextId) && s.nextId > Math.max(0, ...ids),
      "Contador de copias inválido.",
    );
    return s;
  }
  function change(s, mutate) {
    const next = clone(s);
    next._captureRevision = (s._captureRevision || 0) + 1;
    mutate(next);
    next.confirmed = [];
    return validate(next);
  }
  function place(s, { key, owner, controller = owner, zone, uid = null }) {
    return change(s, (next) => {
      let token =
        uid === null ? null : next.tokens.find((t) => t.uid === Number(uid));
      if (uid !== null) assert(token, "No se encontró la copia seleccionada.");
      if (zone === "deck") {
        if (token) {
          const attached = next.tokens.filter(
            (t) => t.zone === "material:" + token.uid,
          );
          assert(
            !attached.length,
            "Mueve los materiales antes de retirar su monstruo.",
          );
          next.tokens = next.tokens.filter((t) => t !== token);
        }
        return;
      }
      if (!token) {
        assert(
          remaining(next, key, owner) > 0,
          "No quedan copias disponibles.",
        );
        token = {
          uid: next.nextId++,
          key,
          owner,
          controller,
          zone,
          position: key === "unknown" ? "set" : "attack",
        };
        next.tokens.push(token);
        // When the player reveals a drawn card and registers its identity in hand,
        // consume a prior unknown OWN draw instead of inventing another card.
        if (owner==="self" && zone==="hand" && next.selfUnknownHand>0)
          next.selfUnknownHand--;
      } else {
        if (!isMonster(zone))
          assert(
            !next.tokens.some((t) => t.zone === "material:" + token.uid),
            "Mueve los materiales antes de retirar su monstruo.",
          );
        if((isMonster(token.zone)||/^s[1-5]$/.test(token.zone)||token.zone==='field')&&!(isMonster(zone)||/^s[1-5]$/.test(zone)||zone==='field'))delete token.negation;
        if (token.xyzSummoned && isMonster(token.zone) && !isMonster(zone))
          delete token.xyzSummoned;
        if (token.currentATK !== undefined && isMonster(token.zone) && !isMonster(zone))
          delete token.currentATK;
        // The attack restriction belongs to that physical Summon on the field;
        // leaving the monster zones ends it even if the same card returns.
        if (token.attackLockedTurn !== undefined && isMonster(token.zone) && !isMonster(zone))
          delete token.attackLockedTurn;
        token.zone = zone;
        token.controller = controller;
        // Una carta colocada abandona su ventana de Set al salir de M/T.
        if (!/^s[1-5]$/.test(zone)) delete token.setTurn;
      }
    });
  }
  function patch(s, values) {
    return change(s, (n) => {
      for (const key of [
        "turn",
        "goesFirst",
        "active",
        "phase",
        "normalAvailable",
        "notes",
        "usedEffects",
        "opponentHandComplete",
      ])
        if (Object.hasOwn(values, key)) n[key] = values[key];
      if (values.lp) n.lp = { ...n.lp, ...values.lp };
      if (values.unknown) n.unknown = { ...n.unknown, ...values.unknown };
    });
  }
  // Advance the capture clock only. Drawing and resolving end-of-turn effects
  // require explicit player input, so neither is inferred here.
  function advanceTurn(s) {
    validate(s);
    assert(!(s.chain || []).length, "Resuelve o retira la cadena pendiente antes de avanzar turno.");
    assert(s.turn < 999, "Se alcanzó el máximo de turnos del escenario.");
    return change(s, (n) => {
      n.turn += 1;
      n.active = s.practice ? "self" : (s.active === "self" ? "opponent" : "self");
      n.phase = "draw";
      n.normalAvailable = true;
      for (const token of n.tokens) {
        if (token.negation?.duration === "turn" && token.negation.turn < n.turn)
          delete token.negation;
      }
    });
  }
  function advancePhase(s) {
    validate(s);
    assert(!(s.chain || []).length, "Resuelve o retira la cadena pendiente antes de cambiar fase.");
    const phases = s.format === "rush_duel"
      ? ["draw", "main1", "battle", "end"]
      : ["draw", "standby", "main1", "battle", "main2", "end"];
    const index = phases.indexOf(s.phase);
    assert(index >= 0 && index < phases.length - 1,
      "La fase Final requiere avanzar al turno siguiente.");
    return change(s, (n) => { n.phase = phases[index + 1]; });
  }
  // A witness of an opponent's Extra Deck summon, not a proof of the materials
  // or the full legal summoning procedure. Record it only with an identified
  // Extra Deck card and an actual occupied monster zone in the resulting state.
  function observeExtraSummon(s, { key, uid = null, zone }) {
    assert(!s.practice, "La observación exige un rival identificado.");
    const card = definition(s, key, "opponent");
    assert(card && card.section === "extra" && isMonster(zone),
      "La observación exige una carta rival identificada del Extra Deck.");
    const prev = uid === null ? s.tokens.find(t => t.key === key &&
      t.owner === "opponent" && t.zone === "extra") : s.tokens.find(t => t.uid === uid);
    if (uid !== null) assert(prev && prev.key === key && prev.owner === "opponent" &&
      prev.zone === "extra", "La copia observada no estaba en el Extra rival.");
    const next = place(s, { key, uid: prev?.uid ?? null, owner: "opponent",
      controller: "opponent", zone });
    const token = next.tokens.find(t => t.key === key && t.owner === "opponent" && t.zone === zone);
    assert(token, "No se pudo acreditar la Invocación del rival.");
    next._ruleEvents = [...(next._ruleEvents || []), {
      kind: "observed_extra_summon", side: "opponent", from: "extra",
      uid: token.uid, key, turn: next.turn,
    }];
    return validate(next);
  }
  function appendChain(s, { side, cardName = "", effect, ruleId, actorUid, outcome }) {
    return change(s, (n) => {
      assert(SIDES.includes(side), "Selecciona el jugador que activó el eslabón.");
      assert(typeof effect === "string" && effect.trim() && effect.length <= 300,
        "Describe el efecto o la activación pendiente (máximo 300 caracteres).");
      n.chain ||= [];
      assert(n.chain.length < 20, "La cadena alcanzó el límite de captura.");
      n.chain.push({ link: n.chain.length + 1, side,
        cardName: String(cardName).slice(0, 250), effect: effect.trim(),
        ...(ruleId ? { ruleId, actorUid } : {}), ...(outcome ? { outcome } : {}) });
    });
  }
  function removeChain(s, link) {
    return change(s, (n) => {
      assert(Number.isInteger(link) && link >= 1 && link <= (n.chain || []).length,
        "Eslabón inexistente.");
      n.chain.splice(link - 1, 1);
      n.chain.forEach((entry, i) => { entry.link = i + 1; });
    });
  }
  function observeOpponentMainDeckAdd(s) {
    assert(!s.practice, "No se registra una búsqueda rival en práctica libre.");
    assert(s.phase !== "draw", "Droll no responde a una adición en la fase de Robo.");
    assert(!(s.chain || []).length, "Resuelve la cadena antes de registrar la adición rival.");
    return change(s, next => {
      next._ruleEvents = [...(next._ruleEvents || []), {
        kind: "opponent_main_deck_add", side: "opponent", turn: next.turn,
        phase: next.phase, captureRevision: next._captureRevision,
      }];
    });
  }
  function position(s, uid, value) {
    return change(s, (n) => {
      const t = n.tokens.find((t) => t.uid === uid);
      assert(t, "Selecciona una copia.");
      if (value === "set" && /^s[1-5]$/.test(t.zone)) {
        if (t.position !== "set" || !Number.isInteger(t.setTurn)) t.setTurn = n.turn;
      } else if (value !== "set") delete t.setTurn;
      t.position = value;
    });
  }
  function negated(s,t){return !!(t.negation?.active&&(t.negation.duration==='manual'||t.negation.turn===s.turn));}
  function cardStatus(s,uid,values){
    return change(s,n=>{
      const t=n.tokens.find(t=>t.uid===Number(uid));assert(t,'Selecciona una copia.');
      if(values.negation!==undefined)t.negation={active:!!values.negation.active,duration:values.negation.duration||'turn',reason:String(values.negation.reason||'').slice(0,300),turn:n.turn};
      if(values.effect){
        const {slot,id='',used}=values.effect;assert(Number.isInteger(slot)&&slot>=1&&slot<=6&&typeof id==='string'&&id.length<=80,'Marcador de efecto inválido.');
        n.effectUsage=(n.effectUsage||[]).filter(e=>!(e.uid===t.uid&&e.slot===slot&&e.turn===n.turn));
        if(used)n.effectUsage.push({uid:t.uid,key:t.key,slot,id,turn:n.turn});
      }
    });
  }
  function aliases(card, metadata = {}) {
    return [
      ...new Set(
        [
          card.name,
          ...card.name.split(/\s+\/\s+/),
          metadata[String(card.imageId)]?.name,
        ]
          .filter(Boolean)
          .map(norm),
      ),
    ];
  }
  function resolve(name, catalog, metadata) {
    const query = norm(name);
    return catalog.cards
      .filter((c) => aliases(c, metadata).includes(query))
      .map((c) => c.key);
  }
  function resource(text, self, metadata) {
    if (/^(?:una? )?zona (?:de )?(?:M\/T|Magia\/Trampa) libre$/i.test(text))
      return { kind: "space", row: "s", text };
    const match = text.match(
      /^(.+?) en (Deck|GY|Cementerio|mano|campo|destierro|Extra)(?: o (Deck|GY|Cementerio|mano|campo|destierro|Extra))?$/i,
    );
    if (!match) return { kind: "unknown", text };
    const toZone = (z) =>
      ({
        deck: "deck",
        gy: "graveyard",
        cementerio: "graveyard",
        mano: "hand",
        campo: "field-all",
        destierro: "banished",
        extra: "extra",
      })[z.toLowerCase()];
    const keys = resolve(match[1], self, metadata);
    return {
      kind: keys.length ? "card-zone" : "unknown",
      keys,
      zones: [toZone(match[2]), ...(match[3] ? [toZone(match[3])] : [])],
      text,
    };
  }
  function compile(guide, self) {
    if (!guide) return [];
    return (guide.combos || []).map((c) => {
      const tactical =
        Object.entries(c.detail || {}).find(([k]) =>
          k.startsWith("tactical_full_prompt_detail"),
        )?.[1] || {};
      const names = Array.isArray(tactical.minimum_opening_hand)
        ? tactical.minimum_opening_hand
        : [];
      return {
        id: c.id,
        title: c.title,
        conditions: c.conditions || "",
        warning: c.warning || "",
        sourceStatus: c.status || "",
        executable: c.executable !== false,
        steps: (c.steps || []).map((s) => s.text),
        result: c.detail?.result || "",
        requires: names.map((name) => ({
          name,
          keys: resolve(name, self, guide.card_metadata),
        })),
        normal: /normal libre|unspent normal|usar la normal|uses? normal/i.test(
          (c.conditions || "") + " " + JSON.stringify(tactical),
        ),
        resources: (Array.isArray(tactical.additional_required_resources)
          ? tactical.additional_required_resources
          : []
        ).map((text) => resource(text, self, guide.card_metadata)),
        documentedHand: names.length > 0,
        binding: self.signature,
      };
    });
  }
  function suggestions(s, routes) {
    validate(s);
    return routes
      .map((r) => {
        const missing = [],
          pending = [],
          matched = [];
        if (!r.executable)
          missing.push(
            "La fuente marca esta ruta como no ejecutable con la construcción documentada.",
          );
        if (r.binding !== s.self.signature)
          pending.push("La lista cambió desde que se preparó esta ruta.");
        if (r.calculatorVerified === false)
          pending.push("Ruta A09 documental: no hay evidencia de ejecución íntegra por el motor; los pasos descritos no se simulan automáticamente.");
        if (s.lp.self === 0 || s.lp.opponent === 0)
          missing.push(
            "Uno de los participantes tiene 0 LP; revisa el estado antes de estudiar otra jugada.",
          );
        if (r.normal) {
          if (s.active !== "self" || !["main1", "main2"].includes(s.phase))
            missing.push(
              "La apertura usa una Invocación Normal: necesita tu Main Phase.",
            );
          if (!s.normalAvailable)
            missing.push(
              "Marcaste que no puedes utilizar la Invocación Normal.",
            );
          if (
            s.tokens.filter((t) => t.controller === "self" && /^m/.test(t.zone))
              .length >= (s.format === "rush_duel" ? 3 : 5)
          )
            missing.push("No hay zona de monstruos libre.");
        }
        const consumed = new Map();
        for (const requirement of r.requires) {
          if (!requirement.keys.length) {
            pending.push("Comprobar el requisito textual: " + requirement.name);
            continue;
          }
          const copy = s.tokens.find(
            (t) =>
              t.owner === "self" &&
              t.controller === "self" &&
              t.zone === "hand" &&
              requirement.keys.includes(t.key) &&
              !consumed.has(t.uid),
          );
          if (copy) {
            consumed.set(copy.uid, true);
            matched.push(requirement.name);
          } else missing.push("Falta en tu mano: " + requirement.name);
        }
        for (const requirement of r.resources || []) {
          if (requirement.kind === "unknown") {
            pending.push("Comprobar recurso: " + requirement.text);
            continue;
          }
          if (requirement.kind === "space") {
            const max = s.format === "rush_duel" ? 3 : 5;
            if (
              s.tokens.filter(
                (t) =>
                  t.controller === "self" &&
                  t.zone.startsWith(requirement.row) &&
                  /^s\d$/.test(t.zone),
              ).length >= max
            )
              missing.push("No hay zona de Magia/Trampa libre.");
            else matched.push(requirement.text);
            continue;
          }
          const found = requirement.keys.some((key) =>
            requirement.zones.some((zone) =>
              zone === "deck"
                ? availableInMainDeck(s, key)
                : (zone === "extra" &&
                    definition(s, key, "self")?.section === "extra" &&
                    remaining(s, key, "self") > 0) ||
                  s.tokens.some(
                    (t) =>
                      t.key === key &&
                      t.owner === "self" &&
                      t.controller === "self" &&
                      (zone === "field-all"
                        ? isMonster(t.zone) || /^s\d$|^field$/.test(t.zone)
                        : t.zone === zone),
                  ),
            ),
          );
          if (found) matched.push(requirement.text);
          else
            missing.push(
              "Recurso fuera de las zonas requeridas: " + requirement.text,
            );
        }
        if (!r.documentedHand)
          pending.push(
            "La fuente no define una mano mínima estructurada; comprueba el estado inicial completo.",
          );
        pending.push(
          "Revisar los requisitos del campo, Cementerio, Deck, costes, efectos usados y respuestas rivales descritos en esta ruta.",
        );
        if (!s.opponentHandComplete || s.unknown.hand)
          pending.push(
            "La mano rival contiene información desconocida; no se presume que no tenga respuestas.",
          );
        const confirmed = s.confirmed.includes(r.id),
          status = missing.length
            ? "blocked"
            : confirmed
              ? "confirmed"
              : "conditional";
        return { ...r, missing, pending, matched, status, confirmed };
      })
      .sort(
        (a, b) =>
          ({ confirmed: 0, conditional: 1, blocked: 2 })[a.status] -
            { confirmed: 0, conditional: 1, blocked: 2 }[b.status] ||
          b.matched.length - a.matched.length,
      );
  }
  function availableInMainDeck(s, key) {
    const card = definition(s, key, "self");
    return card?.section === "main" && remaining(s, key, "self") > 0;
  }
  function confirm(s, id, routes) {
    assert(
      suggestions(s, routes).some((r) => r.id === id && r.status !== "blocked"),
      "La ruta tiene requisitos incumplidos.",
    );
    const n = clone(s);
    n.confirmed = n.confirmed.includes(id)
      ? n.confirmed.filter((x) => x !== id)
      : [...n.confirmed, id];
    return validate(n);
  }
  function restore(saved, self, opponent) {
    const s = clone(saved);
    assert(
      s.self.id === self.id && s.opponent.id === opponent.id,
      "El escenario corresponde a otros participantes.",
    );
    assert(
      s.self.signature === self.signature &&
        s.opponent.signature === opponent.signature,
      "Las listas cambiaron: conserva el archivo anterior y prepara otro escenario.",
    );
    s.self = clone(self);
    s.opponent = clone(opponent);
    return validate(s);
  }
  function practiceHand(s, hand) {
    let next = clone(s);
    for (const token of next.tokens.filter(
      (t) => t.owner === "self" && t.zone === "hand",
    ))
      next = place(next, { uid: token.uid, zone: "deck", controller: "self" });
    for (const name of hand.cartas || []) {
      const variants = String(name)
        .split(/\s+\/\s+/)
        .map(norm);
      const matches = next.self.cards.filter(
        (c) =>
          c.section === "main" &&
          [norm(c.name), ...c.name.split(/\s+\/\s+/).map(norm)].some((n) =>
            variants.includes(n),
          ),
      );
      assert(
        matches.length === 1,
        "No se pudo identificar sin ambigüedad la carta de ejemplo: " + name,
      );
      next = place(next, {
        key: matches[0].key,
        owner: "self",
        controller: "self",
        zone: "hand",
      });
    }
    return next;
  }
  return {
    VERSION,
    norm,
    clone,
    signature,
    catalog,
    create,
    zones,
    isMonster,
    remaining,
    definition,
    validate,
    place,
    patch,
    advanceTurn,
    advancePhase,
    observeExtraSummon,
    observeOpponentMainDeckAdd,
    appendChain,
    removeChain,
    position,
    cardStatus,
    negated,
    compile,
    suggestions,
    confirm,
    restore,
    practiceHand,
  };
});
