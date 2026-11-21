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
  // 14 identidades de monstruos del Main + 13 del Extra aprobado (41/15/14).
  // Un procedimiento de TY-PHON admite cualquiera de estas identidades bajo
  // control propio, sujeto a ATK actual declarado y observación del rival.
  const mementoTyphonMaterials = [...mementoMonsters, 20196, 12950, 9279, 20500,
    20423, 12870, 16845, 20216, 19878, 13081, 19184, 20225, 19188,
    19932, 20226, 20389, 9657];
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
      {
        "version": 1,
        "id": "671:19363:normal",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura: Invocación Normal a zona m1",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19364&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand"
          ],
          "cid": 19363
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          },
          {
            "kind": "state",
            "field": "normalAvailable",
            "values": [
              true
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "summon",
            "ref": "actor",
            "method": "normal",
            "from": "hand",
            "to": "m1",
            "position": "attack"
          }
        ],
        "tags": [],
        "assumptions": [
          "Invocación Normal de Hoja Oscura CID19363 desde una copia física en mano con la Invocación Normal del turno disponible; respeta las restricciones y las zonas del escenario capturado."
        ],
        "activation": false,
        "oncePerTurn": false
      },
      {
        "version": 1,
        "id": "671:19363:normal",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura: Invocación Normal a zona m2",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19364&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand"
          ],
          "cid": 19363
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          },
          {
            "kind": "state",
            "field": "normalAvailable",
            "values": [
              true
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "summon",
            "ref": "actor",
            "method": "normal",
            "from": "hand",
            "to": "m2",
            "position": "attack"
          }
        ],
        "tags": [],
        "assumptions": [
          "Invocación Normal de Hoja Oscura CID19363 desde una copia física en mano con la Invocación Normal del turno disponible; respeta las restricciones y las zonas del escenario capturado."
        ],
        "activation": false,
        "oncePerTurn": false
      },
      {
        "version": 1,
        "id": "671:19363:normal",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura: Invocación Normal a zona m3",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19364&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand"
          ],
          "cid": 19363
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          },
          {
            "kind": "state",
            "field": "normalAvailable",
            "values": [
              true
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "summon",
            "ref": "actor",
            "method": "normal",
            "from": "hand",
            "to": "m3",
            "position": "attack"
          }
        ],
        "tags": [],
        "assumptions": [
          "Invocación Normal de Hoja Oscura CID19363 desde una copia física en mano con la Invocación Normal del turno disponible; respeta las restricciones y las zonas del escenario capturado."
        ],
        "activation": false,
        "oncePerTurn": false
      },
      {
        "version": 1,
        "id": "671:19363:normal",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura: Invocación Normal a zona m4",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19364&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand"
          ],
          "cid": 19363
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          },
          {
            "kind": "state",
            "field": "normalAvailable",
            "values": [
              true
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "summon",
            "ref": "actor",
            "method": "normal",
            "from": "hand",
            "to": "m4",
            "position": "attack"
          }
        ],
        "tags": [],
        "assumptions": [
          "Invocación Normal de Hoja Oscura CID19363 desde una copia física en mano con la Invocación Normal del turno disponible; respeta las restricciones y las zonas del escenario capturado."
        ],
        "activation": false,
        "oncePerTurn": false
      },
      {
        "version": 1,
        "id": "671:19363:normal",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura: Invocación Normal a zona m5",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19364&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand"
          ],
          "cid": 19363
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          },
          {
            "kind": "state",
            "field": "normalAvailable",
            "values": [
              true
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "summon",
            "ref": "actor",
            "method": "normal",
            "from": "hand",
            "to": "m5",
            "position": "attack"
          }
        ],
        "tags": [],
        "assumptions": [
          "Invocación Normal de Hoja Oscura CID19363 desde una copia física en mano con la Invocación Normal del turno disponible; respeta las restricciones y las zonas del escenario capturado."
        ],
        "activation": false,
        "oncePerTurn": false
      },

      {
        "version": 1,
        "id": "671:dark-blade:goblin",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura C05: destruirse e Invocar Angbruja del Deck",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19363&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19363,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19364,
            "from": "deck",
            "to": "m1",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "monsterEffect",
          "summonFromDeck"
        ],
        "assumptions": [
          "C05: Hoja Oscura propio en Campo destruye esa copia mediante su efecto de Main Phase; solo si se destruye, Invoca Angbruja Nivel 3 física del Deck. Se activa una sola vez por turno."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:dark-blade:goblin",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura C05: destruirse e Invocar Angbruja del Deck",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19363&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19363,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19364,
            "from": "deck",
            "to": "m2",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "monsterEffect",
          "summonFromDeck"
        ],
        "assumptions": [
          "C05: Hoja Oscura propio en Campo destruye esa copia mediante su efecto de Main Phase; solo si se destruye, Invoca Angbruja Nivel 3 física del Deck. Se activa una sola vez por turno."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:dark-blade:goblin",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura C05: destruirse e Invocar Angbruja del Deck",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19363&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19363,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19364,
            "from": "deck",
            "to": "m3",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "monsterEffect",
          "summonFromDeck"
        ],
        "assumptions": [
          "C05: Hoja Oscura propio en Campo destruye esa copia mediante su efecto de Main Phase; solo si se destruye, Invoca Angbruja Nivel 3 física del Deck. Se activa una sola vez por turno."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:dark-blade:goblin",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura C05: destruirse e Invocar Angbruja del Deck",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19363&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19363,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19364,
            "from": "deck",
            "to": "m4",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "monsterEffect",
          "summonFromDeck"
        ],
        "assumptions": [
          "C05: Hoja Oscura propio en Campo destruye esa copia mediante su efecto de Main Phase; solo si se destruye, Invoca Angbruja Nivel 3 física del Deck. Se activa una sola vez por turno."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:dark-blade:goblin",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Hoja Oscura C05: destruirse e Invocar Angbruja del Deck",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19363&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19363,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19364,
            "from": "deck",
            "to": "m5",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "monsterEffect",
          "summonFromDeck"
        ],
        "assumptions": [
          "C05: Hoja Oscura propio en Campo destruye esa copia mediante su efecto de Main Phase; solo si se destruye, Invoca Angbruja Nivel 3 física del Deck. Se activa una sola vez por turno."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:angwitch:destroy-revive-mace",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Angbruja C05: destruirse y revivir Goblin de Nivel 1",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19364&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19364,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "target",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "move",
            "ref": "target",
            "to": "free-monster",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "monsterEffect"
        ],
        "assumptions": [
          "C05: selecciona Goblin Memento Nivel 1 real en Cementerio; destruye la propia Angbruja y solo tras esa destrucción Invoca Goblin desde GY. Es variante del mismo segundo efecto de Angbruja que revive Maza en C04, no efecto adicional."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:goblin:send-two",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Goblin C05: autodestruirse y enviar Fusión + Creación Combinada",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19366&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19366,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19904,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19360,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          }
        ],
        "tags": [
          "monsterEffect",
          "sendFromDeck"
        ],
        "assumptions": [
          "C05: Goblin propio se destruye por su efecto y envía dos cartas Memento distintas del Deck: una copia física de Fusión Mementotlán y una Creación Combinada. Cada carta debe existir aún en Deck."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:graveyard-search",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C05: desterrarse desde GY, destruir Desvantasma y buscar otra copia",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "graveyard"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "victim",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19472,
              "faceUp": true
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "banished",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "destroy",
            "ref": "victim",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19904,
            "from": "deck",
            "to": "hand",
            "position": "attack"
          }
        ],
        "tags": [
          "addFromDeck"
        ],
        "assumptions": [
          "C05: primera copia de Fusión Mementotlán activada desde GY se destierra como coste, destruye Desvantasma del Campo y busca una segunda copia identificada del Deck; la primera copia no puede reutilizarse tras su destierro. Distintos efectos de Fusión comparten cada uno un límite de uso por turno."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C05: Maza mano + Tatsun/Goblin GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "mace",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "mace",
              "tatsun",
              "goblin"
            ],
            "materialCids": [
              19365,
              19362,
              19366
            ],
            "to": "m1",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: la segunda copia de Fusión está en mano y usa Maza de Mano, Tatsun y Goblin del GY. Requiere destrucción propia por efecto este turno; los dos materiales de GY se devuelven al Deck. La primera Fusión permanece desterrada."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C05: Maza mano + Tatsun/Goblin GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "mace",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "mace",
              "tatsun",
              "goblin"
            ],
            "materialCids": [
              19365,
              19362,
              19366
            ],
            "to": "m2",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: la segunda copia de Fusión está en mano y usa Maza de Mano, Tatsun y Goblin del GY. Requiere destrucción propia por efecto este turno; los dos materiales de GY se devuelven al Deck. La primera Fusión permanece desterrada."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C05: Maza mano + Tatsun/Goblin GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "mace",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "mace",
              "tatsun",
              "goblin"
            ],
            "materialCids": [
              19365,
              19362,
              19366
            ],
            "to": "m3",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: la segunda copia de Fusión está en mano y usa Maza de Mano, Tatsun y Goblin del GY. Requiere destrucción propia por efecto este turno; los dos materiales de GY se devuelven al Deck. La primera Fusión permanece desterrada."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C05: Maza mano + Tatsun/Goblin GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "mace",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "mace",
              "tatsun",
              "goblin"
            ],
            "materialCids": [
              19365,
              19362,
              19366
            ],
            "to": "m4",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: la segunda copia de Fusión está en mano y usa Maza de Mano, Tatsun y Goblin del GY. Requiere destrucción propia por efecto este turno; los dos materiales de GY se devuelven al Deck. La primera Fusión permanece desterrada."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C05: Maza mano + Tatsun/Goblin GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "mace",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "mace",
              "tatsun",
              "goblin"
            ],
            "materialCids": [
              19365,
              19362,
              19366
            ],
            "to": "m5",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: la segunda copia de Fusión está en mano y usa Maza de Mano, Tatsun y Goblin del GY. Requiere destrucción propia por efecto este turno; los dos materiales de GY se devuelven al Deck. La primera Fusión permanece desterrada."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:creation-king:fusion-send",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Rey Creación C05: enviar Akhíron, Dormiveja y Dragón de Cuerno",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=20216&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 20216,
          "faceUp": true,
          "notNegated": true
        },
        "trigger": {
          "kind": "fusion_summoned",
          "actorCid": 20216
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "take",
            "side": "self",
            "cid": 20491,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 20199,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19361,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          }
        ],
        "tags": [
          "monsterEffect",
          "sendFromDeck"
        ],
        "assumptions": [
          "C05: tras la Fusión acreditada de Rey Creación, envía Akhíron CID20491, Dormiveja CID20199 y Dragón de Cuerno CID19361, tres nombres reales aún presentes en Deck. No confundir Horned Dragon con Akihiron."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C05: devolver Hoja, Angbruja, Desvantasma, Maza y Dragón de Cuerno",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19363
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m1",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: desde GY devuelve al Deck cinco nombres Memento distintos: Hoja Oscura, Angbruja, Desvantasma, Maza y Dragón de Cuerno CID19361. Conserva Akhíron CID20491 y Dormiveja CID20199 en GY como recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C05: devolver Hoja, Angbruja, Desvantasma, Maza y Dragón de Cuerno",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19363
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m2",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: desde GY devuelve al Deck cinco nombres Memento distintos: Hoja Oscura, Angbruja, Desvantasma, Maza y Dragón de Cuerno CID19361. Conserva Akhíron CID20491 y Dormiveja CID20199 en GY como recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C05: devolver Hoja, Angbruja, Desvantasma, Maza y Dragón de Cuerno",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19363
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m3",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: desde GY devuelve al Deck cinco nombres Memento distintos: Hoja Oscura, Angbruja, Desvantasma, Maza y Dragón de Cuerno CID19361. Conserva Akhíron CID20491 y Dormiveja CID20199 en GY como recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C05: devolver Hoja, Angbruja, Desvantasma, Maza y Dragón de Cuerno",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19363
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m4",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: desde GY devuelve al Deck cinco nombres Memento distintos: Hoja Oscura, Angbruja, Desvantasma, Maza y Dragón de Cuerno CID19361. Conserva Akhíron CID20491 y Dormiveja CID20199 en GY como recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C05: devolver Hoja, Angbruja, Desvantasma, Maza y Dragón de Cuerno",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19363
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m5",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C05: desde GY devuelve al Deck cinco nombres Memento distintos: Hoja Oscura, Angbruja, Desvantasma, Maza y Dragón de Cuerno CID19361. Conserva Akhíron CID20491 y Dormiveja CID20199 en GY como recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },

      {
        "version": 1,
        "id": "671:angwitch:destroy-revive-mace",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Angbruja: destruirse y revivir Maza Nivel 1 del Cementerio",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19364&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19364,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "target",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19365
            }
          }
        ],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "move",
            "ref": "target",
            "to": "free-monster",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "monsterEffect"
        ],
        "assumptions": [
          "Primera variante demostrada del segundo efecto de Angbruja: selecciona Maza Memento Nivel 1 en Cementerio; destruye la propia Angbruja en Campo como efecto y, solo si fue destruida, revive esa misma Maza en una zona libre. Requiere materiales y estados físicos exactos y no inventa otros objetivos."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C04: usar Desvantasma en Campo, Goblin en mano y Tatsun en GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "ghattic",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19472,
              "faceUp": true
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "ghattic",
              "goblin",
              "tatsun"
            ],
            "materialCids": [
              19472,
              19366,
              19362
            ],
            "to": "m1",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "requiredOrigins": "hand-and-field",
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 documentada: se usó destrucción de un Memento propio por efecto este turno, por lo que Fusión Mementotlán puede usar Tatsun del Cementerio y barajarlo al Deck. Los otros dos materiales físicos son Desvantasma del Campo y Goblin de la mano. Si no existe registro de destrucción propia este turno, se rechaza."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C04: usar Desvantasma en Campo, Goblin en mano y Tatsun en GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "ghattic",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19472,
              "faceUp": true
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "ghattic",
              "goblin",
              "tatsun"
            ],
            "materialCids": [
              19472,
              19366,
              19362
            ],
            "to": "m2",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "requiredOrigins": "hand-and-field",
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 documentada: se usó destrucción de un Memento propio por efecto este turno, por lo que Fusión Mementotlán puede usar Tatsun del Cementerio y barajarlo al Deck. Los otros dos materiales físicos son Desvantasma del Campo y Goblin de la mano. Si no existe registro de destrucción propia este turno, se rechaza."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C04: usar Desvantasma en Campo, Goblin en mano y Tatsun en GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "ghattic",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19472,
              "faceUp": true
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "ghattic",
              "goblin",
              "tatsun"
            ],
            "materialCids": [
              19472,
              19366,
              19362
            ],
            "to": "m3",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "requiredOrigins": "hand-and-field",
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 documentada: se usó destrucción de un Memento propio por efecto este turno, por lo que Fusión Mementotlán puede usar Tatsun del Cementerio y barajarlo al Deck. Los otros dos materiales físicos son Desvantasma del Campo y Goblin de la mano. Si no existe registro de destrucción propia este turno, se rechaza."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C04: usar Desvantasma en Campo, Goblin en mano y Tatsun en GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "ghattic",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19472,
              "faceUp": true
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "ghattic",
              "goblin",
              "tatsun"
            ],
            "materialCids": [
              19472,
              19366,
              19362
            ],
            "to": "m4",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "requiredOrigins": "hand-and-field",
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 documentada: se usó destrucción de un Memento propio por efecto este turno, por lo que Fusión Mementotlán puede usar Tatsun del Cementerio y barajarlo al Deck. Los otros dos materiales físicos son Desvantasma del Campo y Goblin de la mano. Si no existe registro de destrucción propia este turno, se rechaza."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:memento-fusion:fuse",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fusión Mementotlán C04: usar Desvantasma en Campo, Goblin en mano y Tatsun en GY",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19904
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "ghattic",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19472,
              "faceUp": true
            }
          },
          {
            "ref": "goblin",
            "selector": {
              "side": "self",
              "zones": [
                "hand"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "graveyard"
              ],
              "cid": 19362
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "fusion",
            "cid": 20216,
            "materials": [
              "ghattic",
              "goblin",
              "tatsun"
            ],
            "materialCids": [
              19472,
              19366,
              19362
            ],
            "to": "m5",
            "recipe": "exact-cids",
            "allowGraveyard": true,
            "requiredOrigins": "hand-and-field",
            "distinctCids": true
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 documentada: se usó destrucción de un Memento propio por efecto este turno, por lo que Fusión Mementotlán puede usar Tatsun del Cementerio y barajarlo al Deck. Los otros dos materiales físicos son Desvantasma del Campo y Goblin de la mano. Si no existe registro de destrucción propia este turno, se rechaza."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:creation-king:fusion-send",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Rey Creación C04: enviar jefe, Dormiveja y Dragón de Cuerno",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=20216&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 20216,
          "faceUp": true,
          "notNegated": true
        },
        "trigger": {
          "kind": "fusion_summoned",
          "actorCid": 20216
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "take",
            "side": "self",
            "cid": 19360,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 20199,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19361,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          }
        ],
        "tags": [
          "monsterEffect",
          "sendFromDeck"
        ],
        "assumptions": [
          "C04: tras Fusión real de Rey Creación, envía exactamente Creación Combinada, Dormiveja y Dragón de Cuerno al Cementerio desde Deck. Sin evento de Fusión no se ofrece esta variante."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C04: cinco nombres después de Fusión y Rey",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m1",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 exacta: baraja Angbruja, Maza, Desvantasma, Goblin y Dragón de Cuerno desde Mano/GY (cinco nombres distintos) e Invoca Creación Combinada desde el Cementerio. Deja Dormiveja en GY para uso posterior."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C04: cinco nombres después de Fusión y Rey",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m2",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 exacta: baraja Angbruja, Maza, Desvantasma, Goblin y Dragón de Cuerno desde Mano/GY (cinco nombres distintos) e Invoca Creación Combinada desde el Cementerio. Deja Dormiveja en GY para uso posterior."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C04: cinco nombres después de Fusión y Rey",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m3",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 exacta: baraja Angbruja, Maza, Desvantasma, Goblin y Dragón de Cuerno desde Mano/GY (cinco nombres distintos) e Invoca Creación Combinada desde el Cementerio. Deja Dormiveja en GY para uso posterior."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C04: cinco nombres después de Fusión y Rey",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m4",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 exacta: baraja Angbruja, Maza, Desvantasma, Goblin y Dragón de Cuerno desde Mano/GY (cinco nombres distintos) e Invoca Creación Combinada desde el Cementerio. Deja Dormiveja en GY para uso posterior."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada C04: cinco nombres después de Fusión y Rey",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19361
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m5",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "C04 exacta: baraja Angbruja, Maza, Desvantasma, Goblin y Dragón de Cuerno desde Mano/GY (cinco nombres distintos) e Invoca Creación Combinada desde el Cementerio. Deja Dormiveja en GY para uso posterior."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },

      {
        "version": 1,
        "id": "671:moon:link-from-angwitch-tatsun",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Luna del Cielo Cerrado: Link-2 desde Angbruja + Tatsun de efecto",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19932&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19364,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19362,
              "faceUp": true
            }
          }
        ],
        "costs": [],
        "resolve": [
          {
            "op": "link",
            "cid": 19932,
            "materials": [
              "actor",
              "tatsun"
            ],
            "materialCids": [
              19364,
              19362
            ],
            "to": "emz1",
            "recipe": "exact-cids",
            "linkRating": 2
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento de Enlace C03 con exactamente dos monstruos de efecto identificados Angbruja y Tatsun, sin activar el efecto propio de Luna. No certifica listas prohibidas/limitadas ni disponibilidad fuera del Extra documentado."
        ],
        "activation": false,
        "oncePerTurn": false
      },
      {
        "version": 1,
        "id": "671:moon:link-from-angwitch-tatsun",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Luna del Cielo Cerrado: Link-2 desde Angbruja + Tatsun de efecto",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19932&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19364,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "tatsun",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "cid": 19362,
              "faceUp": true
            }
          }
        ],
        "costs": [],
        "resolve": [
          {
            "op": "link",
            "cid": 19932,
            "materials": [
              "actor",
              "tatsun"
            ],
            "materialCids": [
              19364,
              19362
            ],
            "to": "emz2",
            "recipe": "exact-cids",
            "linkRating": 2
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento de Enlace C03 con exactamente dos monstruos de efecto identificados Angbruja y Tatsun, sin activar el efecto propio de Luna. No certifica listas prohibidas/limitadas ni disponibilidad fuera del Extra documentado."
        ],
        "activation": false,
        "oncePerTurn": false
      },

      {
        "version": 1,
        "id": "671:tatsun:mill",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Tatsunootoshigo: destruirse y enviar Desvantasma y Dormiveja (2+3=5)",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19362&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19362,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19472,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 20199,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          }
        ],
        "tags": [
          "monsterEffect",
          "sendFromDeck"
        ],
        "assumptions": [
          "Ruta C01 exacta: Tatsun nivel original 5 se destruye a sí mismo y manda Desvantasma (Nivel 2) y Dormiveja (Nivel 3), nombres distintos cuya suma es 5. Exige ambas cartas reales en Deck y no ejecuta automáticamente disparadores posteriores."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:creation-king:fusion-send",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Rey Creación: tras Fusión, enviar Creación Combinada, Maza y Goblin al Cementerio",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=20216&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 20216,
          "faceUp": true,
          "notNegated": true
        },
        "trigger": {
          "kind": "fusion_summoned",
          "actorCid": 20216
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "take",
            "side": "self",
            "cid": 19360,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19365,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19366,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          }
        ],
        "tags": [
          "monsterEffect",
          "sendFromDeck"
        ],
        "assumptions": [
          "Se ofrece solo tras evento de Invocación por Fusión registrado para este Rey Creación físico. Se acreditan tres cartas Memento con CID exactos en Deck: Creación Combinada, Maza y Goblin. No simula envíos alternativos ni Extra Deck."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: barajar los cinco nombres exactos de C01 tras Rey Creación",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 20199
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m1",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C01 exacto: devuelve al Deck Tatsun, Angbruja, Desvantasma, Dormiveja y Goblin desde mano/Cementerio como cinco nombres distintos. Deja Maza en Cementerio. Creación Combinada se invoca desde GY a una zona libre sin inventar material."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: barajar los cinco nombres exactos de C01 tras Rey Creación",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 20199
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m2",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C01 exacto: devuelve al Deck Tatsun, Angbruja, Desvantasma, Dormiveja y Goblin desde mano/Cementerio como cinco nombres distintos. Deja Maza en Cementerio. Creación Combinada se invoca desde GY a una zona libre sin inventar material."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: barajar los cinco nombres exactos de C01 tras Rey Creación",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 20199
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m3",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C01 exacto: devuelve al Deck Tatsun, Angbruja, Desvantasma, Dormiveja y Goblin desde mano/Cementerio como cinco nombres distintos. Deja Maza en Cementerio. Creación Combinada se invoca desde GY a una zona libre sin inventar material."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: barajar los cinco nombres exactos de C01 tras Rey Creación",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 20199
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m4",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C01 exacto: devuelve al Deck Tatsun, Angbruja, Desvantasma, Dormiveja y Goblin desde mano/Cementerio como cinco nombres distintos. Deja Maza en Cementerio. Creación Combinada se invoca desde GY a una zona libre sin inventar material."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: barajar los cinco nombres exactos de C01 tras Rey Creación",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 20199
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m5",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C01 exacto: devuelve al Deck Tatsun, Angbruja, Desvantasma, Dormiveja y Goblin desde mano/Cementerio como cinco nombres distintos. Deja Maza en Cementerio. Creación Combinada se invoca desde GY a una zona libre sin inventar material."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },

      {
        "version": 1,
        "id": "671:bone-party:main",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Fiesta de Huesos: destruir Angbruja y traer Tatsun en Defensa",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19368&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "s1",
            "s2",
            "s3",
            "s4",
            "s5"
          ],
          "cid": 19368
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "victim",
            "selector": {
              "side": "self",
              "zones": [
                "monsters"
              ],
              "faceUp": true,
              "cid": 19364
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "actor",
            "to": "graveyard",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "destroy",
            "ref": "victim",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19362,
            "from": "deck",
            "to": "free-monster",
            "position": "defense",
            "summonMethod": "special"
          }
        ],
        "tags": [
          "summonFromDeck"
        ],
        "assumptions": [
          "Caso C02 exacto: Angbruja propia boca arriba es destruida por Fiesta de Huesos y Tatsun distinto se Invoca Especial del Deck en Defensa. El paso requiere copia física de Tatsun y zona propia libre. La Mágica pasa a Cementerio al activarse, no como coste de descarte."
        ],
        "activation": true,
        "oncePerTurn": true,
        "chainSpeed": 2,
        "activationNegatedConsumesUse": true
      },
      {
        "version": 1,
        "id": "671:tatsun:mill",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Tatsunootoshigo: destruirse y enviar Goblin, Maza y Desvantasma (1+1+2 <= 5)",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19362&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "monsters"
          ],
          "cid": 19362,
          "faceUp": true,
          "notNegated": true
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [],
        "costs": [],
        "resolve": [
          {
            "op": "destroy",
            "ref": "actor",
            "reason": "effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19366,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19365,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          },
          {
            "op": "take",
            "side": "self",
            "cid": 19472,
            "from": "deck",
            "to": "graveyard",
            "position": "attack",
            "cause": "monster_effect"
          }
        ],
        "tags": [
          "monsterEffect",
          "sendFromDeck"
        ],
        "assumptions": [
          "Elección C02 exacta: se destruye Tatsun Nivel 5 propio y se envían Goblin (Nivel 1), Maza (Nivel 1) y Desvantasma (Nivel 2), tres nombres diferentes con suma de niveles 4. Los disparadores opcionales posteriores, incluido el de Desvantasma, no se ejecutan automáticamente."
        ],
        "activation": true,
        "oncePerTurn": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: Invocación Especial barajando cinco nombres de C02",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m1",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C02 desde mano o Cementerio, no efecto activado. Baraja exactamente cinco monstruos Memento físicos de nombres distintos: Angbruja, Tatsun, Goblin, Maza y Desvantasma. Requiere sus cinco copias en mano/Cementerio y una zona de monstruos disponible; el motor no inventa recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: Invocación Especial barajando cinco nombres de C02",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m2",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C02 desde mano o Cementerio, no efecto activado. Baraja exactamente cinco monstruos Memento físicos de nombres distintos: Angbruja, Tatsun, Goblin, Maza y Desvantasma. Requiere sus cinco copias en mano/Cementerio y una zona de monstruos disponible; el motor no inventa recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: Invocación Especial barajando cinco nombres de C02",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m3",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C02 desde mano o Cementerio, no efecto activado. Baraja exactamente cinco monstruos Memento físicos de nombres distintos: Angbruja, Tatsun, Goblin, Maza y Desvantasma. Requiere sus cinco copias en mano/Cementerio y una zona de monstruos disponible; el motor no inventa recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: Invocación Especial barajando cinco nombres de C02",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m4",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C02 desde mano o Cementerio, no efecto activado. Baraja exactamente cinco monstruos Memento físicos de nombres distintos: Angbruja, Tatsun, Goblin, Maza y Desvantasma. Requiere sus cinco copias en mano/Cementerio y una zona de monstruos disponible; el motor no inventa recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },
      {
        "version": 1,
        "id": "671:tecuhtlica:five-names",
        "deckId": 671,
        "format": "tcg_advanced",
        "scope": "single-effect",
        "label": "Creación Combinada: Invocación Especial barajando cinco nombres de C02",
        "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2&request_locale=en",
        "actor": {
          "side": "self",
          "zones": [
            "hand",
            "graveyard"
          ],
          "cid": 19360
        },
        "guards": [
          {
            "kind": "state",
            "field": "active",
            "values": [
              "self"
            ]
          },
          {
            "kind": "state",
            "field": "phase",
            "values": [
              "main1",
              "main2"
            ]
          }
        ],
        "select": [
          {
            "ref": "material1",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19364
            }
          },
          {
            "ref": "material2",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19362
            }
          },
          {
            "ref": "material3",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19366
            }
          },
          {
            "ref": "material4",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19365
            }
          },
          {
            "ref": "material5",
            "selector": {
              "side": "self",
              "zones": [
                "hand",
                "graveyard"
              ],
              "cid": 19472
            }
          }
        ],
        "costs": [
          {
            "op": "move",
            "ref": "material1",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material2",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material3",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material4",
            "to": "deck",
            "position": "attack"
          },
          {
            "op": "move",
            "ref": "material5",
            "to": "deck",
            "position": "attack"
          }
        ],
        "resolve": [
          {
            "op": "move",
            "ref": "actor",
            "to": "m5",
            "position": "attack",
            "summonMethod": "special"
          }
        ],
        "tags": [],
        "assumptions": [
          "Procedimiento C02 desde mano o Cementerio, no efecto activado. Baraja exactamente cinco monstruos Memento físicos de nombres distintos: Angbruja, Tatsun, Goblin, Maza y Desvantasma. Requiere sus cinco copias en mano/Cementerio y una zona de monstruos disponible; el motor no inventa recursos."
        ],
        "activation": false,
        "oncePerTurn": false,
        "distinctSelectedNames": true
      },

      ...[
        {
          "version": 1,
          "deckId": 671,
          "format": "tcg_advanced",
          "scope": "single-effect",
          "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19188&ope=2&request_locale=en",
          "id": "671:sp:link-with-moon",
          "label": "S:P Pequeña Caballera: Enlace-2 usando Luna y Angbruja",
          "actor": {
            "side": "self",
            "zones": [
              "monsters"
            ],
            "cid": 19932,
            "faceUp": true
          },
          "guards": [
            {
              "kind": "state",
              "field": "active",
              "values": [
                "self"
              ]
            },
            {
              "kind": "state",
              "field": "phase",
              "values": [
                "main1",
                "main2"
              ]
            }
          ],
          "select": [
            {
              "ref": "witch",
              "selector": {
                "side": "self",
                "zones": [
                  "monsters"
                ],
                "cid": 19364,
                "faceUp": true
              }
            }
          ],
          "costs": [],
          "resolve": [
            {
              "op": "link",
              "cid": 19188,
              "materials": [
                "actor",
                "witch"
              ],
              "materialCids": [
                19932,
                19364
              ],
              "to": "emz1",
              "recipe": "exact-cids",
              "linkRating": 2
            }
          ],
          "tags": [],
          "assumptions": [
            "Receta concreta de dos monstruos de efecto: Luna (Link-2) cuenta como un material Link y Angbruja como otro. No es una certificación de legalidad del formato o lista de prohibiciones."
          ],
          "activation": false,
          "oncePerTurn": false
        },
        {
          "version": 1,
          "deckId": 671,
          "format": "tcg_advanced",
          "scope": "single-effect",
          "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19188&ope=2&request_locale=en",
          "id": "671:sp:link-with-moon",
          "label": "S:P Pequeña Caballera: Enlace-2 usando Luna y Angbruja",
          "actor": {
            "side": "self",
            "zones": [
              "monsters"
            ],
            "cid": 19932,
            "faceUp": true
          },
          "guards": [
            {
              "kind": "state",
              "field": "active",
              "values": [
                "self"
              ]
            },
            {
              "kind": "state",
              "field": "phase",
              "values": [
                "main1",
                "main2"
              ]
            }
          ],
          "select": [
            {
              "ref": "witch",
              "selector": {
                "side": "self",
                "zones": [
                  "monsters"
                ],
                "cid": 19364,
                "faceUp": true
              }
            }
          ],
          "costs": [],
          "resolve": [
            {
              "op": "link",
              "cid": 19188,
              "materials": [
                "actor",
                "witch"
              ],
              "materialCids": [
                19932,
                19364
              ],
              "to": "emz2",
              "recipe": "exact-cids",
              "linkRating": 2
            }
          ],
          "tags": [],
          "assumptions": [
            "Receta concreta de dos monstruos de efecto: Luna (Link-2) cuenta como un material Link y Angbruja como otro. No es una certificación de legalidad del formato o lista de prohibiciones."
          ],
          "activation": false,
          "oncePerTurn": false
        },
        {
          "version": 1,
          "deckId": 671,
          "format": "tcg_advanced",
          "scope": "single-effect",
          "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19188&ope=2&request_locale=en",
          "id": "671:sp:banish-link-extra",
          "label": "S:P Pequeña Caballera: desterrar una carta tras Enlace usando Extra Deck",
          "actor": {
            "side": "self",
            "zones": [
              "monsters"
            ],
            "cid": 19188,
            "faceUp": true,
            "notNegated": true
          },
          "guards": [
            {
              "kind": "state",
              "field": "active",
              "values": [
                "self",
                "opponent"
              ]
            }
          ],
          "trigger": {
            "kind": "link_summoned_with_extra",
            "actorCid": 19188
          },
          "select": [
            {
              "ref": "target",
              "selector": {
                "side": "self",
                "zones": [
                  "field-all"
                ]
              }
            }
          ],
          "costs": [],
          "resolve": [
            {
              "op": "move",
              "ref": "target",
              "to": "banished",
              "position": "attack"
            },
            {
              "op": "flag",
              "flag": "directAttackBlocked",
              "value": true,
              "duration": "turn"
            }
          ],
          "tags": [
            "monsterEffect"
          ],
          "assumptions": [
            "Sólo se ofrece inmediatamente tras una Invocación Link del S:P físico usando material del Extra Deck. Requiere carta objetivo identificada en Campo o Cementerio; no se simula el segundo efecto rápido de retorno al final de fase.",
            "El destierro del primer efecto impide ataques directos de todos los monstruos propios durante este turno, aun si S:P abandona el Campo. Los efectos se limitan por nombre a una vez por turno."
          ],
          "activation": true,
          "oncePerTurn": true
        },
        {
          "version": 1,
          "deckId": 671,
          "format": "tcg_advanced",
          "scope": "single-effect",
          "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19188&ope=2&request_locale=en",
          "id": "671:sp:banish-link-extra",
          "label": "S:P Pequeña Caballera: desterrar una carta tras Enlace usando Extra Deck",
          "actor": {
            "side": "self",
            "zones": [
              "monsters"
            ],
            "cid": 19188,
            "faceUp": true,
            "notNegated": true
          },
          "guards": [
            {
              "kind": "state",
              "field": "active",
              "values": [
                "self",
                "opponent"
              ]
            }
          ],
          "trigger": {
            "kind": "link_summoned_with_extra",
            "actorCid": 19188
          },
          "select": [
            {
              "ref": "target",
              "selector": {
                "side": "self",
                "zones": [
                  "graveyard"
                ]
              }
            }
          ],
          "costs": [],
          "resolve": [
            {
              "op": "move",
              "ref": "target",
              "to": "banished",
              "position": "attack"
            },
            {
              "op": "flag",
              "flag": "directAttackBlocked",
              "value": true,
              "duration": "turn"
            }
          ],
          "tags": [
            "monsterEffect"
          ],
          "assumptions": [
            "Sólo se ofrece inmediatamente tras una Invocación Link del S:P físico usando material del Extra Deck. Requiere carta objetivo identificada en Campo o Cementerio; no se simula el segundo efecto rápido de retorno al final de fase.",
            "El destierro del primer efecto impide ataques directos de todos los monstruos propios durante este turno, aun si S:P abandona el Campo. Los efectos se limitan por nombre a una vez por turno."
          ],
          "activation": true,
          "oncePerTurn": true
        },
        {
          "version": 1,
          "deckId": 671,
          "format": "tcg_advanced",
          "scope": "single-effect",
          "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19188&ope=2&request_locale=en",
          "id": "671:sp:banish-link-extra",
          "label": "S:P Pequeña Caballera: desterrar una carta tras Enlace usando Extra Deck",
          "actor": {
            "side": "self",
            "zones": [
              "monsters"
            ],
            "cid": 19188,
            "faceUp": true,
            "notNegated": true
          },
          "guards": [
            {
              "kind": "state",
              "field": "active",
              "values": [
                "self",
                "opponent"
              ]
            }
          ],
          "trigger": {
            "kind": "link_summoned_with_extra",
            "actorCid": 19188
          },
          "select": [
            {
              "ref": "target",
              "selector": {
                "side": "opponent",
                "zones": [
                  "field-all"
                ]
              }
            }
          ],
          "costs": [],
          "resolve": [
            {
              "op": "move",
              "ref": "target",
              "to": "banished",
              "position": "attack"
            },
            {
              "op": "flag",
              "flag": "directAttackBlocked",
              "value": true,
              "duration": "turn"
            }
          ],
          "tags": [
            "monsterEffect"
          ],
          "assumptions": [
            "Sólo se ofrece inmediatamente tras una Invocación Link del S:P físico usando material del Extra Deck. Requiere carta objetivo identificada en Campo o Cementerio; no se simula el segundo efecto rápido de retorno al final de fase.",
            "El destierro del primer efecto impide ataques directos de todos los monstruos propios durante este turno, aun si S:P abandona el Campo. Los efectos se limitan por nombre a una vez por turno."
          ],
          "activation": true,
          "oncePerTurn": true
        },
        {
          "version": 1,
          "deckId": 671,
          "format": "tcg_advanced",
          "scope": "single-effect",
          "source": "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19188&ope=2&request_locale=en",
          "id": "671:sp:banish-link-extra",
          "label": "S:P Pequeña Caballera: desterrar una carta tras Enlace usando Extra Deck",
          "actor": {
            "side": "self",
            "zones": [
              "monsters"
            ],
            "cid": 19188,
            "faceUp": true,
            "notNegated": true
          },
          "guards": [
            {
              "kind": "state",
              "field": "active",
              "values": [
                "self",
                "opponent"
              ]
            }
          ],
          "trigger": {
            "kind": "link_summoned_with_extra",
            "actorCid": 19188
          },
          "select": [
            {
              "ref": "target",
              "selector": {
                "side": "opponent",
                "zones": [
                  "graveyard"
                ]
              }
            }
          ],
          "costs": [],
          "resolve": [
            {
              "op": "move",
              "ref": "target",
              "to": "banished",
              "position": "attack"
            },
            {
              "op": "flag",
              "flag": "directAttackBlocked",
              "value": true,
              "duration": "turn"
            }
          ],
          "tags": [
            "monsterEffect"
          ],
          "assumptions": [
            "Sólo se ofrece inmediatamente tras una Invocación Link del S:P físico usando material del Extra Deck. Requiere carta objetivo identificada en Campo o Cementerio; no se simula el segundo efecto rápido de retorno al final de fase.",
            "El destierro del primer efecto impide ataques directos de todos los monstruos propios durante este turno, aun si S:P abandona el Campo. Los efectos se limitan por nombre a una vez por turno."
          ],
          "activation": true,
          "oncePerTurn": true
        }
      ],
      ...["hand","set"].map(mode => ({
        ...rule(671, "671:goblin-biker-grand-breakout:deck-summon",
          "Gran Fuga Moto Goblin: sacrificar un monstruo propio e Invocar Goblin del Deck",
          20533,official(20533),
          [{op:"take",side:"self",cid:19366,from:"deck",to:"free-monster",
            position:"attack",summonMethod:"special",cannotAttackThisTurn:true}],
          ["summonFromDeck"],
          ["Mágica Rápida: se tributa una copia propia en Campo como coste, incluso boca abajo; se Invoca el Goblin físico restante en el Main Deck y no puede atacar este turno.",
           "Solo se aplica la primera parte: el efecto de GY exige un Xyz «Goblin», ausente en el Extra de este deck. No se inventa una interacción de Xyz.",
           "Desde mano se modela en la Main o Battle propias; colocada se requiere turno previo y ventana Main/Battle en cualquiera de los turnos. No se evalúan cadenas rivales arbitrarias."],
        ),
        actor:mode==="hand" ?
          {side:"self",zones:["hand"],cid:20533} :
          {side:"self",zones:["s1","s2","s3","s4","s5"],cid:20533,faceUp:false},
        guards:[
          {kind:"state",field:"active",values:mode==="hand"?["self"]:["self","opponent"]},
          {kind:"state",field:"phase",values:["main1","main2","battle"]},
        ],
        select:[{ref:"victim",selector:{side:"self",zones:["monsters"]}}],
        costs:[{op:"tribute",ref:"victim",reason:"cost",allowSet:true}],
        afterResolution:[{op:"move",ref:"actor",to:"graveyard",position:"attack"}],
        chainSpeed:2,
        activationNegatedConsumesUse:true,
      })),
      {
        ...rule(671,"671:aero-shark:damage-per-banished",
          "Portaaviones Sumergible Aero Tiburón: desacoplar 1 material e infligir 100 LP por monstruo desterrado",
          9657,official(9657),
          [{op:"damagePerBanishedMonsters",side:"opponent",perMonster:100}],
          ["monsterEffect"],
          ["Solo cuenta monstruos propios desterrados boca arriba con tipo comprobable mediante el catálogo oficial. Las cartas desterradas boca abajo no se cuentan como monstruos por información no visible. Ante cualquier carta desterrada boca arriba con tipo desconocido, no se inventa daño. No incorpora Invocación Xyz genérica ni efectos rivales sin modelar."],
        ),
        actor:{side:"self",zones:["monsters"],cid:9657,faceUp:true,notNegated:true},
        guards:main,
        select:[{ref:"material",selector:{side:"self",zones:["actor-materials"]}}],
        costs:[{op:"move",ref:"material",to:"graveyard",position:"attack"}],
        activation:true,oncePerTurn:true
      },
      // Second exact one-target mode: banish one identified non-actor card
      // from hand face down AT RESOLUTION (not as an activation cost).
      ...["self","opponent"].flatMap(side => ["main","extra"].map(section => ({
        ...rule(671,"671:gordian:one-monster-one-hand",
          "Rebanador Gordiano: devolver una carta boca arriba desterrando 1 carta de mano",
          21203,official(21203),
          [{op:"move",ref:"payment",to:"banished",position:"set"},
           {op:"move",ref:"target",to:section==="extra"?"extra":"hand",position:"attack"}],
          [],
          ["Variante verificada para una carta boca arriba (incluye Magia/Trampa del Main) y una carta propia distinta de Gordiano en mano. El destierro boca abajo se realiza en resolución y la carta devuelta va al propietario. No reproduce múltiples objetivos, pagos combinados ni cadenas complejas. Requiere Side swap a Main."],
        ),
        actor:{side:"self",zones:["hand"],cid:21203},
        guards:[{kind:"state",field:"active",values:["self"]},
          {kind:"state",field:"phase",values:["main1","main2"]},{kind:"phaseStart"}],
        select:[
          {ref:"payment",selector:{side:"self",zones:["hand"]}},
          {ref:"target",selector:{side,zones:[section==="main"?"field-all":"monsters"],section,faceUp:true}}
        ],
        afterResolution:[{op:"move",ref:"actor",to:"graveyard",position:"attack"}],
        activation:true,oncePerTurn:false,cannotBeNegated:true,noChainReplay:true
      }))),
      // One-target Gordian Slicer alternative, with six *identified* physical
      // Extra Deck cards paid only during resolution. This intentionally does
      // not model multiple targets or alternative hand banishment.
      ...["self","opponent"].flatMap(side => ["main","extra"].map(section => ({
        ...rule(671,"671:gordian:one-monster-six-extra",
          "Rebanador Gordiano: devolver una carta boca arriba tras desterrar 6 del Extra",
          21203,official(21203),
          [{op:"banishExtraFaceDown",refs:["ex1","ex2","ex3","ex4","ex5","ex6"],count:6},
           {op:"move",ref:"target",to:section==="extra"?"extra":"hand",position:"attack"}],
          [],
          ["Variante verificada para UNA carta boca arriba del campo (incluidas Magias/Trampas Main) y seis cartas físicas concretas del Extra. La prohibición de negar la activación/efecto es obligatoria. La elección alternativa de desterrar de mano, varias cartas objetivo y las cadenas complejas siguen sin modelar. Requiere Side swap a Main antes del duelo."],
        ),
        actor:{side:"self",zones:["hand"],cid:21203},
        guards:[{kind:"state",field:"active",values:["self"]},
          {kind:"state",field:"phase",values:["main1","main2"]},
          {kind:"phaseStart"}],
        select:[
          ...[20216,19878,16845,20389,19188,20225].map((cid,i)=>({
            ref:"ex"+(i+1),selector:{side:"self",zones:["extra"],cid}
          })),
          {ref:"target",selector:{side,zones:[section==="main"?"field-all":"monsters"],section,faceUp:true}}
        ],
        afterResolution:[{op:"move",ref:"actor",to:"graveyard",position:"attack"}],
        activation:true,oncePerTurn:false,cannotBeNegated:true,noChainReplay:true
      }))),
      ...["self","opponent"].flatMap(side => ["main","extra"].map(section => ({
        ...rule(671, "671:typhon:detach-bounce",
          "TY-PHON: desacoplar un material y devolver un monstruo a su propietario",
          19184, official(19184),
          [{ op: "move", ref: "target",
            to: section === "extra" ? "extra" : "hand", position: "attack" }],
          ["monsterEffect"],
          ["El material está físicamente acoplado a este TY-PHON. Se admite devolver el propio TY-PHON o un Xyz con materiales, enviando los materiales restantes al Cementerio. La elección del monstruo no selecciona como objetivo de carta. No se simulan respuestas a cadenas para esta variante."],
        ),
        actor: {side:"self",zones:["monsters"],cid:19184,faceUp:true,notNegated:true},
        guards: main,
        select: [{ref:"material",selector:{side:"self",zones:["actor-materials"]}},
          {ref:"target",selector:{side,zones:["monsters"],section},
            ...(side==="self"&&section==="extra"?{allowActor:true}:{})}],
        costs: [{op:"move",ref:"material",to:"graveyard",position:"attack"}],
        activation: true, oncePerTurn: true, noChainReplay: true,
      }))),
      {
        ...rule(671, "671:heat-wave:lock",
          "Oleada de Calor: impedir Invocaciones de monstruos de efecto hasta tu siguiente Robo",
          9139, official(9139),
          [{ op: "flag", flag: "effectMonsterSummonBlocked", value: true,
            duration: "next-own-draw" }],
          [],
          ["Solo al inicio de Main Phase 1 propia. Afecta a ambos jugadores y solo a monstruos de efecto. El tablero estático no puede comprobar acciones previas no capturadas. Admite copia boca abajo en zona M/T solo si fue colocada en un turno anterior; colocarla durante este Main Phase ya impediría el requisito de inicio."],
        ),
        actor: { side: "self", zones: ["hand", "s1", "s2", "s3", "s4", "s5"], cid: 9139 },
        guards: [{ kind: "state", field: "active", values: ["self"] },
          { kind: "state", field: "phase", values: ["main1"] },
          { kind: "phaseStart" }],
        afterResolution: [{ op: "move", ref: "actor", to: "graveyard", position: "attack" }],
        activationNegatedConsumesUse: false,
        oncePerTurn: false,
      },
      {
        ...rule(671, "671:droll:main-deck-lock",
          "El Bufón y el Pájaro del Candado: bloquear adiciones desde Main Deck",
          9279, official(9279),
          [{ op: "flag", flag: "noDeckToHand", value: true, duration: "turn" }],
          ["monsterEffect"],
          ["Requiere confirmación manual de que el rival acaba de añadir del Main Deck fuera de la fase de Robo. La observación caduca en la siguiente edición del tablero; no se presume una búsqueda solo por ver una carta en mano."],
        ),
        actor: { side: "self", zones: ["hand"], cid: 9279 },
        guards: [{ kind: "event", event: "opponent_main_deck_add", side: "opponent", currentTurn: true }],
        costs: [{ op: "move", ref: "actor", to: "graveyard", position: "attack" }],
        chainSpeed: 2,
        oncePerTurn: false,
      },
      {
        ...rule(671, "671:lancea:banish-lock",
          "Artefacto Lancea: Sacrificar durante el turno rival e impedir destierros", 11243,
          official(11243), [{ op: "flag", flag: "banishBlocked", value: true, duration: "turn" }],
          ["monsterEffect"],
          ["Solo tras intercambio de Side que incorpore Lancea al Main. Efecto rápido del turno rival desde mano o campo de monstruos boca arriba. No simula su colocación como Magia ni su disparador tras destrucción de una copia colocada."],
        ),
        actor: { side: "self", zones: ["hand", "monsters"], cid: 11243,
          faceUp: true, notNegated: true },
        guards: [{ kind: "state", field: "active", values: ["opponent"] }],
        costs: [{ op: "tribute", ref: "actor", reason: "cost", allowHand: true }],
        chainSpeed: 2, oncePerTurn: false,
      },
      ...["emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:sp:link-procedure", "S:P Pequeña Caballera: Invocación Link-2 con Angbruja y Tatsun", 19364,
          official(19188), [{ op: "link", cid: 19188, materials: ["actor", "tatsun"],
            materialCids: [19364, 19362], to: zone, recipe: "exact-cids", linkRating: 2 }], [],
          ["Sólo dos monstruos de efecto del Main boca arriba, sin material del Extra. No se concede el destierro al ser Invocada, porque ese disparador exige un material del Extra Deck."],
        ),
        select: [{ ref: "tatsun", selector: { side: "self", zones: ["monsters"],
          cid: 19362, faceUp: true } }],
        activation: false, oncePerTurn: false,
      })),
      ...["emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:moon:link-requiem", "Luna: convertir el Link-2 LUZ/Demonio en Réquiem Link-1", 19932,
          official(20225), [{ op: "link", cid: 20225, materials: ["actor"],
            materialCids: [19932], to: zone, recipe: "exact-cids", linkRating: 1 }], [],
          ["Sólo Luna boca arriba como único material LUZ/Demonio; no representa su efecto de tomar material rival para un Link-5. Luna figura Prohibida en TCG a fecha de consulta: la legalidad del deck debe revisarse aparte."],
        ),
        activation: false, oncePerTurn: false,
      })),
      ...["emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:moon:link-sequence", "Luna y Goblin: Invocar por Enlace Secuencia", 19932,
          official(20226), [{ op: "link", cid: 20226, materials: ["actor", "goblin"],
            materialCids: [19932, 19366], to: zone, recipe: "exact-cids", linkRating: 2 }], [],
          ["Variante exacta Luna LUZ/Demonio Link-2 (contada como un material) + Goblin de efecto boca arriba, como dos materiales Link de Secuencia. No infiere otros vínculos ni legalidad de Luna en TCG actual."],
        ),
        select: [{ ref: "goblin", selector: { side: "self", zones: ["monsters"], cid: 19366, faceUp: true } }],
        activation: false, oncePerTurn: false,
      })),
      ...[1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:requiem:tribute", "Réquiem del Demoniherrero: Sacrificarse e Invocar Grabador del Deck", 20225,
          official(20225), [{ op: "take", side: "self", cid: 20196, from: "deck", to: "m" + zone,
            position: "attack", summonMethod: "special" }], ["monsterEffect", "summonFromDeck"],
          ["Sólo Réquiem en campo y Grabador identificado disponible en Deck; el Sacrificio es coste y sigue pagado si se niega el efecto. No se incorpora aquí el equipamiento de Réquiem desde Cementerio."],
        ),
        actor: { side: "self", zones: ["monsters"], cid: 20225, faceUp: true, notNegated: true },
        guards: [{ kind: "state", field: "active", values: ["self", "opponent"] },
          { kind: "state", field: "phase", values: ["main1", "main2"] }],
        costs: [{ op: "tribute", ref: "actor", reason: "cost" }],
        chainSpeed: 2, oncePerTurn: false,
      })),
      {
        ...rule(671, "671:requiem:equip", "Réquiem: equiparse desde el Cementerio a Grabador", 20225,
          official(20225), [{ op: "equip", ref: "actor", target: "target", targetCid: 20196, to: "s1" }], [],
          ["Sólo Réquiem en Cementerio, Grabador boca arriba y casilla M/T 1 libre; no representa otros Demonios LUZ ni la ganancia continua de 600 ATK."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 20225 },
        select: [{ ref: "target", selector: { side: "self", zones: ["monsters"], cid: 20196, faceUp: true } }],
      },
      ...[1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:engraver:revive", "Grabador: barajar Réquiem del Cementerio e Invocarse", 20196,
          official(20196), [{ op: "move", ref: "actor", to: "m" + zone,
            position: "attack", summonMethod: "special" }], ["monsterEffect"],
          ["Sólo el otro monstruo Demonio/LUZ Réquiem, Link-1 identificado en Cementerio, se devuelve al Extra como coste; no modela otros materiales de barajado."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 20196 },
        select: [{ ref: "cost", selector: { side: "self", zones: ["graveyard"], cid: 20225 } }],
        costs: [{ op: "move", ref: "cost", to: "extra", position: "attack" }],
      })),
      {
        ...rule(671, "671:engraver:remove", "Grabador: enviar Réquiem equipado y monstruo rival al Cementerio", 20196,
          official(20196), [{ op: "move", ref: "gear", to: "graveyard", position: "attack" },
            { op: "move", ref: "enemy", to: "graveyard", position: "attack" }], ["monsterEffect"],
          ["Sólo Réquiem correctamente equipado al Grabador de esta copia, y monstruo rival conocido en campo. Se envían al Cementerio sin destruir, desterrar ni negar; no se modelan otros equipos Demoniherrero."],
        ),
        select: [
          { ref: "gear", selector: { side: "self", zones: ["s1"], cid: 20225, faceUp: true } },
          { ref: "enemy", selector: { side: "opponent", zones: ["monsters"] } },
        ],
        equippedToActorRef: "gear",
      },
      ...["m1", "m2", "m3", "m4", "m5", "emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:sequence:duke-fusion", "Secuencia: barajar dos Demonios y fusionar al Duque", 20226,
          official(20226), [{ op: "fusion", cid: 20389, materials: ["engraver", "goblin"],
            materialCids: [20196, 19366], to: zone, recipe: "exact-cids",
            allowGraveyard: true, graveyardPolicy: "fiendsmith-sequence" }], ["monsterEffect"],
          ["Sólo dos Demonios acreditados Grabador + Goblin ya en Cementerio y Duque único en Extra. Se barajan ambos al Deck; no se permiten otras recetas de Fusión."],
        ),
        select: [
          { ref: "engraver", selector: { side: "self", zones: ["graveyard"], cid: 20196 } },
          { ref: "goblin", selector: { side: "self", zones: ["graveyard"], cid: 19366 } },
        ],
      })),
      {
        ...rule(671, "671:duke:recover-engraver", "Duque de la Defunción: desterrar y recuperar Grabador", 20389,
          official(20389), [{ op: "move", ref: "target", to: "hand", position: "attack" }], ["monsterEffect"],
          ["Sólo Duque en Cementerio y Grabador de Nivel 6 Demonio identificado en el propio Cementerio; destierro de Duque como coste. No se simula la Normal adicional del Duque."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 20389 },
        select: [{ ref: "target", selector: { side: "self", zones: ["graveyard"], cid: 20196 } }],
        costs: [{ op: "move", ref: "actor", to: "banished", position: "attack" }],
      },
      {
        ...rule(671, "671:dragostapelia:counter", "Dragostapelia: colocar Contador Depredador a monstruo rival", 12870,
          official(12870), [{ op: "counter", ref: "target", kind: "predator", amount: 1 }], ["monsterEffect"],
          ["Sólo el contador sobre monstruo rival boca arriba conocido; reducción de Nivel y negación continua de efectos por Contador Depredador no ejecutadas automáticamente."],
        ),
        guards: [{ kind: "state", field: "active", values: ["self", "opponent"] },
          { kind: "state", field: "phase", values: ["main1", "main2"] }],
        select: [{ ref: "target", selector: { side: "opponent", zones: ["monsters"], faceUp: true } }],
        chainSpeed: 2,
      },
      {
        ...rule(671, "671:mictlan:activate", "Mementomictlán: activar Magia de Campo", 19367,
          official(19367), [{ op: "move", ref: "actor", to: "field", position: "attack" }], [],
          ["Activación de la Magia de Campo desde mano sin otra Magia de Campo; no ejecuta por sí misma el bloqueo de batalla ni dispara una destrucción."],
        ),
        actor: { side: "self", zones: ["hand"], cid: 19367 },
        oncePerTurn: false,
      },
      ...[19368, 19904].flatMap((cid) => [1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:mictlan:end-set", "Mementomictlán: colocar Magia Memento del Cementerio al final del turno", 19367,
          official(19367), [{ op: "move", ref: "target", to: "s" + zone, position: "set" }], [],
          ["Efecto acotado de End Phase propio: sólo Fiesta de Huesos o Fusión Mementotlán en Cementerio, con una zona M/T libre; no ejecuta el efecto de Invocar monstruos tras destrucción."],
        ),
        actor: { side: "self", zones: ["field"], cid: 19367, faceUp: true, notNegated: true },
        guards: [{ kind: "state", field: "active", values: ["self"] },
          { kind: "state", field: "phase", values: ["end"] }],
        select: [{ ref: "target", selector: { side: "self", zones: ["graveyard"], cid } }],
      }))),
      ...[1, 2, 3, 4, 5].map((zone) => ({
        ...rule(671, "671:horned:special", "Dragón de Cuerno: Invocarse con tres nombres Memento en Cementerio", 19361,
          official(19361), [{ op: "move", ref: "actor", to: "m" + zone,
            position: "attack", summonMethod: "special" }], [],
          ["Procedimiento desde mano con tres monstruos Memento de nombres distintos en Cementerio. El límite de este procedimiento se comparte durante todo el turno."],
        ),
        actor: { side: "self", zones: ["hand"], cid: 19361 },
        guards: [...main, { kind: "distinctCount", selector: {
          side: "self", zones: ["graveyard"], cids: mementoMonsters,
        }, min: 3 }],
        activation: false,
      })),
      {
        ...rule(671, "671:horned:destruction", "Dragón de Cuerno: destruir tres cartas boca arriba acreditadas", 19361,
          official(19361), [
            { op: "destroy", ref: "own", reason: "effect" },
            { op: "destroy", ref: "opponent_mace", reason: "effect" },
            { op: "destroy", ref: "opponent_goblin", reason: "effect" },
          ], ["monsterEffect"],
          ["Sólo si Dragón de Cuerno fue destruido en campo por efecto; se exige un Memento propio y dos monstruos rivales boca arriba concretos. No representa todas las elecciones de tres cartas ni simultaneidad reglamentaria."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 19361 },
        trigger: { kind: "destroyed_by_effect", actorCid: 19361, fromMonsters: true },
        guards: [{ kind: "state", field: "active", values: ["self", "opponent"] },
          { kind: "state", field: "phase", values: ["main1", "main2"] }],
        select: [
          { ref: "own", selector: { side: "self", zones: ["monsters"],
            cids: mementoMonsters, faceUp: true } },
          { ref: "opponent_mace", selector: { side: "opponent", zones: ["monsters"], cid: 19365, faceUp: true } },
          { ref: "opponent_goblin", selector: { side: "opponent", zones: ["monsters"], cid: 19366, faceUp: true } },
        ],
      },
      ...mementoMainCards.filter((cid) => cid !== 20491).map((cid) => ({
        ...rule(671, "671:akihiron:recover", "Akhíron: destruido por efecto recupera carta Memento", 20491,
          official(20491), [{ op: "move", ref: "target", to: "hand", position: "attack" }],
          ["monsterEffect"],
          ["Sólo la destrucción por efecto documentada de Akhíron y cartas conocidas del Main en Cementerio/destierro boca arriba. No se devuelve ningún monstruo del Extra a la mano."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 20491 },
        trigger: { kind: "destroyed_by_effect", actorCid: 20491 },
        select: [{ ref: "target", selector: { side: "self", zones: ["graveyard", "banished"],
          faceUp: true, cid } }],
      })),
      ...["m1", "m2", "m3", "m4", "m5", "emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:typhon:highest-atk", "TY-PHON: superponer al monstruo de mayor ATK", 19184,
          official(19184), [{ op: "xyzOverlay", cid: 19184, material: "actor",
            to: zone, recipe: "typhon-highest-atk" }], [],
          ["Procedimiento acreditado sólo tras observar al menos dos Invocaciones rivales desde Extra durante un único turno actual o anterior. Exige ATK actual de todos los monstruos propios boca arriba; sólo identidades del inventario aprobado. No prueba materiales de las Invocaciones rivales."],
        ),
        actor: { side: "self", zones: ["monsters"], cids: mementoTyphonMaterials, faceUp: true },
        guards: [...main, { kind: "extraSummonHistory", side: "opponent", min: 2, turns: 2 }],
        activation: false, oncePerTurn: false,
      })),
      ...["m1", "m2", "m3", "m4", "m5", "emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:memento-fusion:fuse", "Fusión Mementotlán: Invocar Rey Creación con tres monstruos", 19904,
          official(19904), [{ op: "fusion", cid: 20216, materials: ["a", "b", "c"],
            materialCids: [19364, 19472, 20199], to: zone,
            recipe: "exact-cids", allowGraveyard: false }], [],
          ["Sólo la receta acreditada Angbruja + Desvantasma + Dormiveja desde mano/campo propio; la variante con Cementerio tras una destrucción previa se deja pendiente."],
        ),
        actor: { side: "self", zones: ["hand", "s1", "s2", "s3", "s4", "s5"], cid: 19904 },
        select: [
          { ref: "a", selector: { side: "self", zones: ["hand", "monsters"], cid: 19364 } },
          { ref: "b", selector: { side: "self", zones: ["hand", "monsters"], cid: 19472 } },
          { ref: "c", selector: { side: "self", zones: ["hand", "monsters"], cid: 20199 } },
        ],
        costs: [{ op: "move", ref: "actor", to: "graveyard", position: "attack" }],
        chainSpeed: 2, activationNegatedConsumesUse: true,
      })),
      ...["m1", "m2", "m3", "m4", "m5", "emz1", "emz2"].map((zone) => ({
        ...rule(671, "671:memento-fusion:fuse", "Fusión Mementotlán: Invocar Dragón Gemelo con dos monstruos", 19904,
          official(19904), [{ op: "fusion", cid: 19878, materials: ["a", "b"],
            materialCids: [19364, 19472], to: zone,
            recipe: "exact-cids", allowGraveyard: false }], [],
          ["Sólo la receta acreditada Angbruja + Desvantasma desde mano/campo, sin materiales del Cementerio."],
        ),
        actor: { side: "self", zones: ["hand", "s1", "s2", "s3", "s4", "s5"], cid: 19904 },
        select: [
          { ref: "a", selector: { side: "self", zones: ["hand", "monsters"], cid: 19364 } },
          { ref: "b", selector: { side: "self", zones: ["hand", "monsters"], cid: 19472 } },
        ],
        costs: [{ op: "move", ref: "actor", to: "graveyard", position: "attack" }],
        chainSpeed: 2, activationNegatedConsumesUse: true,
      })),
      {
        ...rule(671, "671:twin-dragon:fusion-search", "Dragón Gemelo: destruir Dormiveja y buscar Tatsunootoshigo y Goblin", 19878,
          official(19878), [{ op: "destroy", ref: "victim", reason: "effect" },
            { op: "take", side: "self", cid: 19362, from: "deck", to: "hand", position: "attack" },
            { op: "take", side: "self", cid: 19366, from: "deck", to: "hand", position: "attack" }],
          ["monsterEffect", "addFromDeck"],
          ["Al ser Invocado por Fusión, sólo se modela destruir Dormiveja propia en mano/campo y buscar dos nombres distintos acreditados. No incluye búsquedas alternativas ni el destierro por batalla."],
        ),
        trigger: { kind: "fusion_summoned", actorCid: 19878 },
        select: [{ ref: "victim", selector: { side: "self", zones: ["hand", "monsters"],
          faceUp: true, cid: 20199 } }],
      },
      ...[19367, 19368].map((targetCid) => ({
        ...rule(671, "671:memento-fusion:graveyard-search", "Fusión Mementotlán: desterrar, destruir y buscar Magia Memento", 19904,
          official(19904), [{ op: "destroy", ref: "victim", reason: "effect" },
            { op: "take", side: "self", cid: targetCid, from: "deck", to: "hand", position: "attack" }],
          ["addFromDeck"],
          ["Sólo se acredita destruir Angbruja propia en campo y buscar Mementomictlán o Fiesta de Huesos. La Magia se destierra del Cementerio como coste."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 19904 },
        select: [{ ref: "victim", selector: { side: "self", zones: ["monsters"], cid: 19364 } }],
        costs: [{ op: "move", ref: "actor", to: "banished", position: "attack" }],
      })),
      {
        ...rule(671, "671:creation-king:graveyard-search", "Rey Creación: desterrar para buscar Mementomictlán", 20216,
          official(20216), [{ op: "take", side: "self", cid: 19367, from: "deck", to: "hand", position: "attack" }],
          ["addFromDeck"],
          ["Rey Creación debe estar en Cementerio; desterrarlo es coste de activación. Sólo se busca la copia acreditada de Mementomictlán."],
        ),
        actor: { side: "self", zones: ["graveyard"], cid: 20216 },
        costs: [{ op: "move", ref: "actor", to: "banished", position: "attack" }],
      },
      {
        ...rule(671, "671:creation-king:destroy", "Rey Creación: destruirse y destruir un monstruo rival conocido", 20216,
          official(20216), [{ op: "destroy", ref: "actor", reason: "effect" },
            { op: "destroy", ref: "target", reason: "effect" }], ["monsterEffect"],
          ["Variante estricta de un objetivo Memento propio (el mismo Rey) y uno rival boca arriba identificado. No cubre dos o más parejas ni objetivos colocados."],
        ),
        actor: { side: "self", zones: ["monsters"], cid: 20216, faceUp: true, notNegated: true },
        guards: [{ kind: "state", field: "active", values: ["self", "opponent"] },
          { kind: "state", field: "phase", values: ["main1", "main2"] }],
        select: [{ ref: "target", selector: { side: "opponent", zones: ["monsters"], faceUp: true } }],
        chainSpeed: 2,
      },
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
      ...[[19362, 19364], [19363, 19364], [19364, 19366], [19366, 19364], [20199, 19364],
        [19361, 19364], [20491, 19364]]
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
      // Mementotlan Goblin can destroy a different face-up Memento monster,
      // rather than only itself. This is a bounded variant with two exact
      // documented Deck targets; all Goblin mill variants share the same
      // once-per-turn effect ID, so choosing one exhausts the others.
      {
        ...rule(671, "671:goblin:send-two", "Goblin: destruir otro Memento y enviar Hoja Oscura y Angbruja", 19366,
          official(19366),
          [{ op: "destroy", ref: "victim", reason: "effect" },
            { op: "take", side: "self", cid: 19363, from: "deck", to: "graveyard", position: "attack", cause: "monster_effect" },
            { op: "take", side: "self", cid: 19364, from: "deck", to: "graveyard", position: "attack", cause: "monster_effect" }],
          ["monsterEffect", "sendFromDeck"],
          ["Variante acotada: Goblin permanece boca arriba, otro Memento propio identificado se destruye, y Hoja Oscura y Angbruja están disponibles en Deck. No modela objetivos boca abajo ni envíos alternativos."],
        ),
        select: [{ ref: "victim", selector: { side: "self", zones: ["monsters"],
          cids: mementoMonsters.filter(cid => cid !== 19366), faceUp: true } }],
      },
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
