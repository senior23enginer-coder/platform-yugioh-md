/* Narrow, officially sourced duel rules. No generated prose is executed. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoVerifiedCalculatorRules = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const main = [{ kind: "state", field: "active", values: ["self"] },
    { kind: "state", field: "phase", values: ["main1", "main2"] }];
  // Only identities from the verified Main Deck study are eligible. Extra Deck
  // monsters cannot be returned to hand by the recovery rules below.
  const mementoMonsters = [19360, 19361, 19362, 19363, 19364, 19365, 19366, 19472, 20199, 20491];
  const mementoMainCards = [...mementoMonsters, 19367, 19368, 19904];
  const official = (cid) =>
    `https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=${cid}&ope=2&request_locale=en`;
  function rule(deckId, id, label, cid, source, resolve, tags, assumptions) {
    return {
      version: 1, id, deckId, format: "tcg_advanced", scope: "single-effect",
      label, source, actor: { side: "self", zones: ["monsters"], cid, faceUp: true, notNegated: true },
      guards: main, select: [], costs: [], resolve, tags, assumptions,
      activation: true, oncePerTurn: true,
    };
  }
  function normalProcedure(cid, label) {
    return [1, 2, 3, 4, 5].map((zone) => ({
      ...rule(671, `671:${cid}:normal`, `${label}: Invocación Normal`, cid,
        official(cid), [{ op: "summon", ref: "actor", method: "normal", from: "hand",
          to: "m" + zone, position: "attack" }], [],
        ["Procedimiento desde mano de monstruo sin requisito de Sacrificio; sus efectos posteriores exigen su propio disparador."],
      ),
      actor: { side: "self", zones: ["hand"], cid },
      guards: [...main, { kind: "state", field: "normalAvailable", values: [true] }],
      activation: false, oncePerTurn: false,
    }));
  }
  function forDeck(id) {
    if (id === 665) return [rule(
      665, "665:fenrir:search", "Fenrir: añadir Riseheart del Deck", 17768,
      "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=17768&ope=2&request_locale=en",
      [{ op: "take", side: "self", cid: 18151, from: "deck", to: "hand", position: "attack" }],
      ["monsterEffect", "addFromDeck"],
      ["Sólo se modela la búsqueda de Riseheart; las otras elecciones y efectos de Fenrir siguen pendientes."],
    )];
    if (id === 671) return [
      ...[1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:tatsun:special", "Tatsunootoshigo: Invocación Especial desde mano", 19362,
          official(19362), [{ op: "move", ref: "actor", to: "m" + zone,
            position: "attack", summonMethod: "special" }], ["monsterEffect"],
          ["El campo propio sólo puede contener monstruos Memento boca arriba conocidos; cualquier otra identidad boca arriba impide esta activación."],
        ),
        actor: { side: "self", zones: ["hand"], cid: 19362 },
        guards: [...main, { kind: "onlyCids", selector: {
          side: "self", zones: ["monsters"], faceUp: true,
        }, allowedCids: mementoMonsters }],
      })),
      ...[[19472], [19472, 19366]].map((sent) => ({
        ...rule(671, "671:tatsun:mill", "Tatsunootoshigo: destruir Angbruja y enviar monstruos de Nivel total hasta 3", 19362,
          official(19362), [{ op: "destroy", ref: "victim", reason: "effect" },
            ...sent.map((cid) => ({ op: "take", side: "self", cid, from: "deck", to: "graveyard",
              position: "attack", cause: "monster_effect" }))],
          ["monsterEffect", "sendFromDeck"],
          ["Elección acotada: Angbruja es Nivel 3; Desvantasma Nivel 2 y Goblin Nivel 1. Otros niveles, víctimas y envíos aún no están modelados."],
        ),
        select: [{ ref: "victim", selector: { side: "self", zones: ["monsters"], cid: 19364 } }],
      })),
      ...[[19362, 19364], [19363, 19364], [19364, 19366], [19366, 19364], [20199, 19364]]
        .flatMap(([victimCid, targetCid]) => ["hand", "m1", "m2", "m3", "m4", "m5"]
          .map((destination) => ({
            ...rule(671, "671:bone-party:main", "Fiesta de Huesos: destruir Memento y obtener otro nombre", 19368,
              official(19368), [{ op: "destroy", ref: "victim", reason: "effect" },
                { op: "take", side: "self", cid: targetCid, from: "deck", to: destination,
                  position: destination === "hand" ? "attack" : "defense",
                  ...(destination === "hand" ? {} : { summonMethod: "special" }) }],
              destination === "hand" ? ["addFromDeck"] : ["summonFromDeck"],
              ["Se modelan únicamente cuatro parejas acreditadas de nombres diferentes, con destino mano o Defensa. La carta se mueve al Cementerio al activarse; eso no representa un coste de descartar."],
            ),
            actor: { side: "self", zones: ["hand", "s1", "s2", "s3", "s4", "s5"], cid: 19368 },
            select: [{ ref: "victim", selector: { side: "self", zones: ["hand", "monsters"],
              faceUp: true, cid: victimCid } }],
            costs: [{ op: "move", ref: "actor", to: "graveyard", position: "attack" }],
            chainSpeed: 2,
            activationNegatedConsumesUse: true,
          }))),
      // A Normal Summon is a procedure, not an effect. It records the event
      // needed to establish each search/recovery trigger for these Level 1–3 cards.
      ...normalProcedure(19364, "Angbruja"),
      ...normalProcedure(19472, "Desvantasma"),
      ...normalProcedure(19365, "Maza"),
      ...normalProcedure(20199, "Dormiveja"),
      ...[1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:shleepy:special", "Dormiveja: Invocarse en turno con destrucción por efecto", 20199,
          official(20199), [{ op: "move", ref: "actor", to: "m" + zone,
            position: "attack", summonMethod: "special" }], ["monsterEffect"],
          ["Sólo se admite una destrucción de monstruo propio con procedencia acreditada en este turno. No se infiere un evento por el texto libre ni por un campo observado a posteriori."],
        ),
        actor: { side: "self", zones: ["hand"], cid: 20199 },
        guards: [{ kind: "state", field: "active", values: ["self", "opponent"] },
          { kind: "state", field: "phase", values: ["draw", "standby", "main1", "battle", "main2", "end"] },
          { kind: "event", event: "owned_monster_destroyed_by_effect_this_turn" }],
        chainSpeed: 2,
      })),
      ...[19364, 19472].map((cid) => ({
        ...rule(671, "671:shleepy:send", "Dormiveja: destruida por efecto envía carta Memento", 20199,
          official(20199), [{ op: "take", side: "self", cid, from: "deck", to: "graveyard",
            position: "attack", cause: "monster_effect" }], ["monsterEffect", "sendFromDeck"],
          ["Sólo se admite destrucción por efecto registrada de esta copia, no la destrucción por batalla; los envíos actualmente disponibles son Angbruja y Desvantasma."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 20199 },
        trigger: { kind: "destroyed_by_effect", actorCid: 20199 },
      })),
      ...["m1", "m2", "m3", "m4", "m5", "emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:shleepy:fusion", "Dormiveja: fusionar tres Memento en Rey Creación", 20199,
          official(20199), [{ op: "fusion", cid: 20216,
            materials: ["actor", "angwitch", "ghattic"],
            materialCids: [20199, 19364, 19472], to: zone,
            recipe: "exact-cids", allowGraveyard: false }], ["monsterEffect"],
          ["Sólo la receta concreta Dormiveja + Angbruja + Desvantasma desde mano/campo; no generaliza todas las recetas ni el efecto posterior del Rey Creación."],
        ),
        trigger: { kind: "summoned", methods: ["normal", "special"], actorCid: 20199 },
        select: [{ ref: "angwitch", selector: { side: "self", zones: ["hand", "monsters"], cid: 19364 } },
          { ref: "ghattic", selector: { side: "self", zones: ["hand", "monsters"], cid: 19472 } }],
      })),
      ...mementoMonsters.filter((cid) => cid !== 19364).map((cid) => ({
        ...rule(671, "671:angwitch:search", "Angbruja: buscar monstruo Memento del Deck", 19364,
          official(19364), [{ op: "take", side: "self", cid, from: "deck", to: "hand", position: "attack" }],
          ["monsterEffect", "addFromDeck"],
          ["Se requiere un evento registrado de Invocación Normal/Especial de esta copia; sólo se ofrecen monstruos Memento del Main acreditados en el estudio."],
        ),
        trigger: { kind: "summoned", methods: ["normal", "special"], actorCid: 19364 },
      })),
      ...mementoMainCards.filter((cid) => cid !== 19472).map((cid) => ({
        ...rule(671, "671:ghattic:recover", "Desvantasma: recuperar carta Memento del Cementerio", 19472,
          official(19472), [{ op: "move", ref: "target", to: "hand", position: "attack" }],
          ["monsterEffect"],
          ["Se requiere Invocación registrada de Desvantasma; la recuperación se limita a cartas Memento del Main conocidas y no devuelve monstruos del Extra a la mano."],
        ),
        trigger: { kind: "summoned", methods: ["normal", "special"], actorCid: 19472 },
        select: [{ ref: "target", selector: { side: "self", zones: ["graveyard"], cid } }],
      })),
      ...[19362, 19366, 20199].flatMap((sourceCid) => [1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:ghattic:revive", "Desvantasma: Invocarse tras envío por monstruo Memento", 19472,
          official(19472), [{ op: "move", ref: "actor", to: "m" + zone,
            position: "attack", summonMethod: "special" }], ["monsterEffect"],
          ["Se exige el envío acreditado por Tatsunootoshigo, Goblin o Dormiveja; otros monstruos Memento que también puedan enviarla se incorporarán al verificar sus reglas."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 19472 },
        trigger: { kind: "sent_by_monster_effect", sourceCid, actorCid: 19472 },
      }))),
      ...mementoMainCards.filter((cid) => cid !== 19365).map((cid) => ({
        ...rule(671, "671:mace:search", "Maza: destruirse y buscar carta Memento", 19365,
          official(19365), [{ op: "destroy", ref: "actor", reason: "effect" },
            { op: "take", side: "self", cid, from: "deck", to: "hand", position: "attack" }],
          ["monsterEffect", "addFromDeck"],
          ["Caso acreditado: Maza se destruye a sí misma; también puede destruir otros monstruos Memento, opción aún no representada."],
        ),
      })),
      ...[1, 2, 3, 4, 5].map((zone) => rule(
      671, "671:dark-blade:goblin", "Hoja Oscura: destruirse e Invocar Goblin", 19363,
      "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19363&ope=2&request_locale=en",
      [{ op: "destroy", ref: "actor", reason: "effect" },
        { op: "take", side: "self", cid: 19366, from: "deck", to: "m" + zone,
          position: "attack", summonMethod: "special" }],
      ["monsterEffect", "summonFromDeck"],
      ["Caso acotado: Hoja Oscura se destruye a sí misma, Goblin está en Deck y hay zona de monstruos libre. No representa otras elecciones ni disparadores."],
      )),
      rule(671, "671:goblin:send-two", "Goblin: destruirse y enviar Hoja Oscura y Angbruja", 19366,
        "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19366&ope=2&request_locale=en",
        [{ op: "destroy", ref: "actor", reason: "effect" },
          { op: "take", side: "self", cid: 19363, from: "deck", to: "graveyard", position: "attack", cause: "monster_effect" },
          { op: "take", side: "self", cid: 19364, from: "deck", to: "graveyard", position: "attack", cause: "monster_effect" }],
        ["monsterEffect", "sendFromDeck"],
        ["Caso acotado: Goblin se destruye a sí mismo; Hoja Oscura y Angbruja están en Deck. No modela envíos alternativos ni disparadores posteriores."],
      ),
      rule(671, "671:goblin:send-two", "Goblin: destruirse y enviar Hoja Oscura y Desvantasma", 19366,
        official(19366),
        [{ op: "destroy", ref: "actor", reason: "effect" },
          { op: "take", side: "self", cid: 19363, from: "deck", to: "graveyard", position: "attack", cause: "monster_effect" },
          { op: "take", side: "self", cid: 19472, from: "deck", to: "graveyard", position: "attack", cause: "monster_effect" }],
        ["monsterEffect", "sendFromDeck"],
        ["Caso acotado de dos nombres distintos: Hoja Oscura y Desvantasma disponibles en Deck; las demás elecciones aún no están incorporadas."],
      ),
      ...[1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:tecuhtlica:five-names", "Creación Combinada: barajar cinco nombres e Invocar", 19360,
          "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
          [{ op: "move", ref: "actor", to: "m" + zone, position: "attack" }],
          [],
          ["Procedimiento de Invocación, no efecto activado. Las cinco cartas deben ser otros monstruos Memento de nombres distintos en mano o Cementerio. Sólo se modelan las cinco identidades verificadas de este estudio."],
        ),
        actor: { side: "self", zones: ["hand", "graveyard"], cid: 19360 },
        select: [1, 2, 3, 4, 5].map((n) => ({
          ref: "material" + n,
          selector: { side: "self", zones: ["hand", "graveyard"], cids: [19362, 19363, 19364, 19366, 20199] },
        })),
        costs: [1, 2, 3, 4, 5].map((n) => ({ op: "move", ref: "material" + n, to: "deck", position: "attack" })),
        activation: false,
        oncePerTurn: false,
        distinctSelectedNames: true,
      })),
    ];
    return [];
  }
  return { forDeck };
});
