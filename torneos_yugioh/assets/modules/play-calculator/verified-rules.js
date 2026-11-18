/* Narrow, officially sourced duel rules. No generated prose is executed. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoVerifiedCalculatorRules = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const main = [{ kind: "state", field: "active", values: ["self"] },
    { kind: "state", field: "phase", values: ["main1", "main2"] }];
  function rule(deckId, id, label, cid, source, resolve, tags, assumptions) {
    return {
      version: 1, id, deckId, format: "tcg_advanced", scope: "single-effect",
      label, source, actor: { side: "self", zones: ["monsters"], cid, faceUp: true, notNegated: true },
      guards: main, select: [], costs: [], resolve, tags, assumptions,
      activation: true, oncePerTurn: true,
    };
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
          { op: "take", side: "self", cid: 19363, from: "deck", to: "graveyard", position: "attack" },
          { op: "take", side: "self", cid: 19364, from: "deck", to: "graveyard", position: "attack" }],
        ["monsterEffect", "sendFromDeck"],
        ["Caso acotado: Goblin se destruye a sí mismo; Hoja Oscura y Angbruja están en Deck. No modela envíos alternativos ni disparadores posteriores."],
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
