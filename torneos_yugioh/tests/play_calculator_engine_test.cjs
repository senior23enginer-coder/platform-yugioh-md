const assert = require("node:assert/strict"),
  fs = require("node:fs"),
  path = require("node:path");
const E = require("../assets/modules/play-calculator/engine.js");
let checks = 0;
const ok = (value, message) => {
  assert(value, message);
  checks++;
};
const rejects = (call) => {
  assert.throws(call);
  checks++;
};
const deck = (id, format = "tcg_advanced") => ({
  id,
  name: "Deck " + id,
  format_codes: [format],
});
const rows = [
  { id: 1, deck_id: 1, card_name: "Carta A", quantity: 2, section: "main" },
  { id: 2, deck_id: 1, card_name: "Carta B", quantity: 1, section: "extra" },
  {
    id: 3,
    deck_id: 1,
    card_name: "Side no activo",
    quantity: 3,
    section: "side",
  },
  { id: 4, deck_id: 2, card_name: "Rival", quantity: 3, section: "main" },
];
const a = E.catalog(deck(1), rows),
  b = E.catalog(deck(2), rows);
let s = E.create(a, b);
ok(a.cards.length === 2, "Side excluded");
rejects(() => E.create(a, E.catalog(deck(3, "rush_duel"), [])));
const original = JSON.stringify(s);
s = E.place(s, { key: "1:1", owner: "self", zone: "hand" });
ok(JSON.stringify(E.create(a, b)) === original, "Pure immutable state");
s = E.place(s, { key: "1:1", owner: "self", zone: "m1" });
rejects(() => E.place(s, { key: "1:1", owner: "self", zone: "hand" }));
rejects(() =>
  E.place(s, { key: "2:4", owner: "opponent", controller: "self", zone: "m1" }),
);
rejects(() => E.place(s, { key: "foreign:1", owner: "self", zone: "hand" }));
s = E.place(s, { uid: 2, zone: "graveyard", controller: "self" });
ok(
  s.tokens.find((t) => t.uid === 2).zone === "graveyard",
  "Move existing copy",
);
s = E.place(s, { uid: 1, zone: "m1", controller: "self" });
s = E.place(s, { uid: 2, zone: "material:1", controller: "self" });
rejects(() => E.place(s, { uid: 1, zone: "deck", controller: "self" }));
s = E.place(s, { uid: 2, zone: "graveyard", controller: "self" });
s = E.position(s, 1, "defense");
ok(s.tokens[0].position === "defense", "Defensive position");
s = E.place(s, { key: "2:4", owner: "opponent", zone: "emz1" });
rejects(() => E.place(s, { key: "1:2", owner: "self", zone: "emz1" }));
const mirror = E.create(a, a);
let mirrored = E.place(mirror, { key: "1:1", owner: "self", zone: "hand" });
mirrored = E.place(mirrored, { key: "1:1", owner: "opponent", zone: "hand" });
ok(
  E.remaining(mirrored, "1:1", "self") === 1 &&
    E.remaining(mirrored, "1:1", "opponent") === 1,
  "Mirror copies separate",
);
for (const format of ["goat", "rush_duel"]) {
  const c = E.catalog(deck(1, format), rows),
    d = E.catalog(deck(2, format), rows),
    x = E.create(c, d);
  ok(!E.zones(x).includes("emz1"), "No Extra Monster zones in " + format);
  if (format === "rush_duel") {
    ok(!E.zones(x).includes("m4"), "Three monster zones");
    rejects(() => E.patch(x, { phase: "main2" }));
  }
}
rejects(() => E.patch(s, { turn: 0 }));
rejects(() => E.patch(s, { unknown: { hand: -1 } }));
rejects(() => E.patch(s, { lp: { self: -1 } }));
rejects(() => E.position(s, 1, "invalid"));
rejects(() => E.restore({ ...s, nextId: 1 }, a, b));
rejects(() => E.restore(s, { ...a, signature: "changed" }, b));
ok(
  JSON.stringify(E.restore(s, a, b)) === JSON.stringify(s),
  "Scenario round trip",
);
const guide = JSON.parse(
  fs.readFileSync(
    path.resolve(
      __dirname,
      "../assets/editorial/20261101-11-decks/deck-687.json",
    ),
    "utf8",
  ),
);
const own = E.catalog(
  deck(687),
  guide.cards.map((c, i) => ({
    id: i + 1,
    deck_id: 687,
    card_name: c.name,
    quantity: c.quantity,
    section: c.section,
    ygoprodeck_id: c.id,
  })),
);
const routes = E.compile(guide, own);
let duel = E.create(own, b);
const rocket = own.cards.find((c) => c.name.includes("Volcanic Rocket"));
ok(routes.length === 10, "All documented Volcanic routes");
ok(
  E.suggestions(duel, routes).find((r) => r.id === "I-A").status === "blocked",
  "Missing Rocket",
);
duel = E.place(duel, { key: rocket.key, owner: "self", zone: "hand" });
let r = E.suggestions(duel, routes).find((r) => r.id === "I-A");
ok(
  r.status === "conditional" && r.pending.length > 0,
  "Visible starter does not certify full route",
);
duel = E.confirm(duel, "I-A", routes);
ok(
  E.suggestions(duel, routes).find((r) => r.id === "I-A").status ===
    "confirmed",
  "Explicit user confirmation",
);
duel = E.patch(duel, { phase: "battle" });
ok(
  !duel.confirmed.length,
  "Board/context changes invalidate prior confirmations",
);
ok(
  E.suggestions(duel, routes).find((r) => r.id === "I-A").status === "blocked",
  "Normal requires own Main Phase",
);
rejects(() => E.confirm(duel, "I-A", routes));
duel = E.patch(duel, { phase: "main1", normalAvailable: false });
ok(
  E.suggestions(duel, routes).find((r) => r.id === "I-A").status === "blocked",
  "Normal not available",
);
duel = E.patch(duel, { normalAvailable: true, lp: { opponent: 0 } });
ok(
  E.suggestions(duel, routes).every((r) => r.status === "blocked"),
  "Zero LP not ignored",
);
const malformed = E.clone(s);
malformed.tokens[0].key = "<script>";
rejects(() => E.validate(malformed));
let resources = E.create(own, b);
resources = E.place(resources, {
  key: rocket.key,
  owner: "self",
  zone: "hand",
});
const reload = own.cards.find((c) =>
  c.name.includes("Blaze Accelerator Reload"),
);
for (let i = 0; i < reload.quantity; i++)
  resources = E.place(resources, {
    key: reload.key,
    owner: "self",
    zone: "banished",
  });
ok(
  E.suggestions(resources, routes).find((r) => r.id === "I-A").status ===
    "blocked",
  "Reload outside Deck/GY blocks the documented resource",
);
resources = E.place(resources, {
  uid: resources.tokens.find((t) => t.key === reload.key).uid,
  zone: "graveyard",
  controller: "self",
});
ok(
  E.suggestions(resources, routes).find((r) => r.id === "I-A").status ===
    "conditional",
  "Reload in own GY satisfies the visible resource",
);
for (let i = 1; i <= 5; i++) {
  const available = own.cards.find(
    (c) => c.section === "main" && E.remaining(resources, c.key, "self") > 0,
  );
  resources = E.place(resources, {
    key: available.key,
    owner: "self",
    zone: "s" + i,
  });
}
ok(
  E.suggestions(resources, routes)
    .find((r) => r.id === "I-A")
    .missing.some((m) => m.includes("Magia/Trampa")),
  "Occupied spell/trap zones affect recommendations",
);
let hidden = E.create(a, b);
hidden = E.place(hidden, { key: "unknown", owner: "opponent", zone: "s1" });
ok(
  hidden.tokens[0].position === "set",
  "Unknown opposing card retains no invented identity",
);
rejects(() => E.place(hidden, { key: "unknown", owner: "self", zone: "s1" }));
rejects(() => E.patch(hidden, { unknown: { hand: 3 } }));
rejects(() => E.catalog(deck(1), [{ ...rows[0], quantity: 0 }]));
console.log(
  JSON.stringify({ ok: true, checks, offlineEngine: true, databaseWrites: 0 }),
);
