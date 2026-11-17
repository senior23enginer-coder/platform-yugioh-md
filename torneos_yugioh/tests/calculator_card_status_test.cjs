const assert = require("node:assert/strict"),
  E = require("../assets/modules/play-calculator/engine.js"),
  P = require("../assets/modules/play-calculator/planner.js"),
  db = require("../assets/modules/play-calculator/card-facts.json");
const inventory = require("./fixtures/calculator_inventory.json");
const catalog = (id) =>
  E.catalog(
    inventory.allDecks.find((d) => d.id === id),
    inventory.cards,
  );
const self = catalog(665),
  opponent = catalog(656),
  fenrir = self.cards.find((c) => db.bindings[c.key] === 17768),
  unicorn = self.cards.find((c) => db.bindings[c.key] === 17769);
let s = E.create(self, opponent);
s = E.place(s, { key: fenrir.key, owner: "self", zone: "m1" });
s = E.place(s, { key: unicorn.key, owner: "self", zone: "m2" });
const before = JSON.stringify(s),
  uid = s.tokens[0].uid;
const hasSearch = (result) =>
  result.plans.some((p) =>
    p.steps.some(
      (step) =>
        step.label.includes(fenrir.name) &&
        step.label.includes("Deck a la mano"),
    ),
  );
assert(hasSearch(P.solve(s, db, { depth: 1 })));
let marked = E.cardStatus(s, uid, {
  negation: { active: true, reason: "Infinite Impermanence", duration: "turn" },
});
assert.equal(
  JSON.stringify(s),
  before,
  "Annotations must not mutate the old state",
);
assert(E.negated(marked, marked.tokens[0]));
const result = P.solve(marked, db, { depth: 1 });
assert(!hasSearch(result), "Negated Fenrir must not search");
assert(
  result.plans.some((p) =>
    p.steps.some((step) => step.label.includes("Invocar Xyz")),
  ),
  "Negated monsters can still be captured as Xyz materials",
);
marked = E.patch(marked, { turn: 2 });
assert(!E.negated(marked, marked.tokens[0]));
assert(hasSearch(P.solve(marked, db, { depth: 1 })));
marked = E.cardStatus(s, uid, {
  effect: { slot: 1, id: "17768:search", used: true },
});
assert(
  !hasSearch(P.solve(marked, db, { depth: 1 })),
  "Used named effect must not be offered",
);
assert(
  hasSearch(P.solve(E.patch(marked, { turn: 2 }), db, { depth: 1 })),
  "A later turn does not inherit last-turn usage",
);
const removed = E.place(marked, {
  uid,
  key: fenrir.key,
  owner: "self",
  zone: "deck",
});
assert.equal(
  removed.effectUsage.length,
  1,
  "Returning the card to the deck must not erase per-name usage",
);
const released = E.place(
  E.cardStatus(s, uid, { negation: { active: true, duration: "manual" } }),
  { uid, key: fenrir.key, owner: "self", zone: "graveyard" },
);
assert(
  !released.tokens[0].negation,
  "Leaving the field clears that field-instance negation",
);
assert.throws(() => E.cardStatus(s, uid, { effect: { slot: 7, used: true } }));
console.log(
  "Calculator status: PASS immutable, per-copy negation, named used effects, turn expiry, material use and leave-field cleanup.",
);
