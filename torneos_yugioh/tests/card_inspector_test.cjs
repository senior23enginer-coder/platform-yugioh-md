const assert = require("node:assert/strict");
const M = require("../assets/modules/card-inspector/model.js");
const data = {
  allDecks: [
    { id: 1, name: "Deck A", format_codes: ["TCG"] },
    { id: 2, name: "Deck B", format_codes: ["TCG"] },
    { id: 3, name: "Rush", format_codes: ["Rush"] },
  ],
  cards: [
    {
      id: 10,
      deck_id: 1,
      card_name: "Carta española",
      quantity: 3,
      section: "main",
      ygoprodeck_id: 100,
    },
    {
      id: 11,
      deck_id: 1,
      card_name: "Carta española",
      quantity: 1,
      section: "reserve",
      ygoprodeck_id: 100,
    },
    {
      id: 12,
      deck_id: 2,
      card_name: "English Card",
      quantity: 2,
      section: "side",
      ygoprodeck_id: 100,
    },
    {
      id: 13,
      deck_id: 3,
      card_name: "English Card",
      quantity: 1,
      section: "main",
    },
    { id: 14, deck_id: 999, card_name: "Orphan" },
  ],
};
const studies = [
  {
    format: "tcg_advanced",
    cards: [
      {
        cid: 200,
        name: "English Card",
        slots: [{ card_id: 10 }],
        image: "./known.jpg",
        fields: [{ title: "Efecto", text: "Efecto guardado." }],
      },
    ],
  },
];
const before = JSON.stringify(data),
  cards = M.catalog(data, studies);
assert.equal(cards.length, 2);
assert.equal(M.search(cards, "espanola")[0].usages.length, 3);
assert.equal(M.search(cards, "200")[0].description, "",'Editorial summaries must not become printed card text');
assert.equal('fields' in cards[0],false);
assert.equal(M.usages({ id: 100 }, cards).length, 3);
assert.equal(
  M.usages({ id: 200, name: "English Card" }, cards).length,
  0,
  "CID must not match provider ID",
);
assert.equal(cards.find((c) => c.id === 100).usages[1].section, "reserve");
assert.equal(JSON.stringify(data), before);
console.log("Card inspector model: PASS");
