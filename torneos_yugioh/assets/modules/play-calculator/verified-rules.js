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
      // A Normal Summon is a procedure, not an effect. It records the event
      // needed to establish each search/recovery trigger for these Level 1–3 cards.
      ...normalProcedure(19364, "Angbruja"),
      ...normalProcedure(19472, "Desvantasma"),
      ...normalProcedure(19365, "Maza"),
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
      ...[1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:ghattic:revive", "Desvantasma: Invocarse tras envío por Goblin", 19472,
          official(19472), [{ op: "move", ref: "actor", to: "m" + zone,
            position: "attack", summonMethod: "special" }], ["monsterEffect"],
          ["Se exige el envío causado y registrado por el efecto de Goblin; otros monstruos Memento que también puedan enviarla se incorporarán al verificar sus reglas."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 19472 },
        trigger: { kind: "sent_by_monster_effect", sourceCid: 19366, actorCid: 19472 },
      })),
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
        { op: "take", side: "self", cid: 19366, from: "deck", to: "m" + zone, position: "attack" }],
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
