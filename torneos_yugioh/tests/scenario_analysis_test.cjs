const assert = require("node:assert/strict");
const fs = require("node:fs"),
  path = require("node:path");
const E = require("../assets/modules/play-calculator/engine.js");
const P = require("../assets/modules/play-calculator/planner.js");
const R = require("../assets/modules/play-calculator/rules.js");
const A = require("../assets/modules/play-calculator/scenario.js");
const Study = require("../assets/modules/play-calculator/study.js");
const base = require("../assets/modules/play-calculator/card-facts.json");
const fixture = require("./fixtures/calculator_inventory.json");
const own = E.catalog(
  fixture.allDecks.find((d) => d.id === 665),
  fixture.cards,
);
function setup(response) {
  const db = E.clone(base),
    opp = E.catalog(
      { id: 999, name: "Rival", format_codes: ["tcg_advanced"] },
      [
        {
          deck_id: 999,
          id: 1,
          card_name: "Respuesta conocida",
          section: "main",
          quantity: 3,
        },
      ],
    );
  db.bindings["999:1"] = response;
  db.identities = { "999:1": { cid: response } };
  let s = E.create(own, opp);
  const card = own.cards.find((c) => db.bindings[c.key] === 17768);
  assert(card);
  s = E.place(s, {
    key: card.key,
    owner: "self",
    controller: "self",
    zone: "m1",
  });
  s = E.place(s, {
    key: "999:1",
    owner: "opponent",
    controller: "opponent",
    zone: "hand",
  });
  return { s, db };
}
let { s, db } = setup(12950);
const original = JSON.stringify(s);
let result = A.solve(s, db, { depth: 1, budget: 100 });
const resistant = A.solve(s, db, { depth: 1, budget: 100, goal: "resilience" });
assert.equal(resistant.goal, "resilience");
assert(resistant.plans.every((p) => p.responseAudit && Number.isFinite(p.resilienceScore)));
assert(resistant.plans.every((p, i, all) => i === 0 || all[i - 1].resilienceScore >= p.resilienceScore));
const ash = result.plans
  .flatMap((p) => p.responses)
  .find((r) => r.cid === 12950 && r.state);
assert(ash);
assert(
  !ash.state.some((t) => t.controller === "self" && t.zone === "hand"),
  "Negated search adds nothing",
);
assert(
  ash.state.some((t) => t.owner === "opponent" && t.zone === "graveyard"),
  "Response cost paid",
);
assert.equal(
  JSON.stringify(s),
  original,
  "Analysis cannot mutate the captured state",
);
assert.equal(result.audit.complete, false);
const usedAsh = E.cardStatus(s, 2, {
  effect: { slot: 1, id: "response:opponent:12950", used: true },
});
assert(
  !A.solve(usedAsh, db, { depth: 1, budget: 100 })
    .plans.flatMap((p) => p.responses)
    .some((r) => r.cid === 12950),
  "Known named opponent HOPT must be consumed",
);

({ s, db } = setup(13631));
result = A.solve(s, db, { depth: 1, budget: 100 });
const imperm = result.plans
  .flatMap((p) => p.responses)
  .find((r) => r.cid === 13631 && r.state);
assert(imperm);
assert(
  imperm.state.find((t) => t.controller === "self" && t.zone === "m1").negation
    .active,
);
let occupied = E.place(s, {
  key: "999:1",
  owner: "opponent",
  controller: "opponent",
  zone: "s1",
});
assert(
  !A.solve(occupied, db, { depth: 1, budget: 100 })
    .plans.flatMap((p) => p.responses)
    .some((r) => r.cid === 13631 && r.state),
  "Imperm from hand requires empty field",
);
({ s, db } = setup(9279));
result = A.solve(s, db, { depth: 1, budget: 100 });
const droll = result.plans
  .flatMap((p) => p.responses)
  .find((r) => r.cid === 9279 && r.state);
assert(droll);
assert(
  droll.state.some((t) => t.controller === "self" && t.zone === "hand"),
  "First search remains",
);
assert.equal(droll.chain.length, 3);
for (const plan of droll.continuations)
  for (const step of plan.steps)
    assert(
      !step.event.tags.includes("addFromDeck"),
      "Droll prevents subsequent additions",
    );
({ s, db } = setup(12950));
s = E.place(s, { uid: 2, zone: "deck", controller: "opponent" });
assert(
  !A.solve(s, db, { depth: 1, budget: 100 })
    .plans.flatMap((p) => p.responses)
    .some((r) => r.cid === 12950),
  "A response in the list but not known in hand is not fabricated",
);
// Portable rule: a concrete Fenrir search, attached to any native deck by exact identity.
({ s, db } = setup(12950));
s._normal = false;
s._used = [];
const target = own.cards.find((c) => db.bindings[c.key] === 18151);
assert(target);
const rule = {
  version: 1,
  id: "fenrir-search-fixture",
  deckId: 665,
  format: "tcg_advanced",
  scope: "single-effect",
  label: "Buscar Riseheart",
  source:
    "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=17768",
  actor: {
    side: "self",
    zones: ["monsters"],
    cid: 17768,
    faceUp: true,
    notNegated: true,
  },
  guards: [
    { kind: "state", field: "active", values: ["self"] },
    { kind: "state", field: "phase", values: ["main1", "main2"] },
    { kind: "flag", flag: "effectsNegated", value: false },
    { kind: "flag", flag: "noDeckToHand", value: false },
  ],
  select: [],
  costs: [],
  resolve: [
    {
      op: "take",
      side: "self",
      cid: 18151,
      from: "deck",
      to: "hand",
      position: "attack",
    },
  ],
  oncePerTurn: true,
  activation: true,
  tags: ["addFromDeck", "monsterEffect"],
  assumptions: ["No other continuous effects or responses in this fixture."],
};
db.rules = [rule];
assert.equal(R.actions(s, db).actions.length, 1);
const applied = R.actions(s, db).actions[0];
assert(
  applied.state.tokens.some((t) => t.key === target.key && t.zone === "hand"),
);
assert.equal(
  R.actions(applied.state, db).actions.length,
  0,
  "Effect use survives a continuation",
);
assert.equal(R.actions(s, db, { noDeckToHand: true }).actions.length, 0);
assert.equal(R.actions(s, db, { effectsNegated: true }).actions.length, 0);
const specialRule = {
  ...rule,
  resolve: [
    {
      op: "take",
      side: "self",
      cid: 18151,
      from: "deck",
      to: "m2",
      position: "attack",
    },
  ],
};
assert.equal(
  R.actions(s, { ...db, rules: [specialRule] }, { specialBlocked: true })
    .actions.length,
  0,
  "Declared effect cannot bypass Special Summon lock",
);
assert.equal(
  R.actions(s, { ...db, rules: [specialRule] }, { blockedZones: ["m2"] })
    .actions.length,
  0,
  "Declared effect cannot bypass blocked zone",
);

assert.throws(
  () =>
    R.validate({ ...rule, resolve: [{ op: "execute", code: "malicious" }] }),
  /Operacion/,
);
assert.equal(
  R.actions(s, { ...db, rules: [{ ...rule, format: "rush_duel" }] }).actions
    .length,
  0,
  "No cross-format effect",
);
const before = JSON.stringify(s);
assert.equal(
  R.actions(s, {
    ...db,
    rules: [{ ...rule, costs: [{ op: "payLP", amount: 8000 }] }],
  }).actions.length,
  0,
);
assert.equal(JSON.stringify(s), before, "Failed cost is atomic");
// Every active study, including five newer decks and Rush/GOAT, is discoverable.
const app = path.resolve(__dirname, "..");
const registry = JSON.parse(
  fs.readFileSync(
    path.join(app, "assets/editorial/strategy-content-state.json"),
  ),
);
let routeCount = 0,
  deckCount = 0;
for (const [id, entry] of Object.entries(registry.checked_studies)) {
  const study = JSON.parse(
    fs.readFileSync(path.join(app, entry.path.replace(/^\.\//, ""))),
  );
  const rows = study.cards.flatMap((c) =>
    c.slots.map((slot) => ({
      id: slot.card_id,
      deck_id: Number(id),
      card_name: c.name,
      quantity: slot.quantity,
      section: slot.section,
    })),
  );
  const catalog = E.catalog(
    { id: Number(id), name: study.name, format_codes: [study.format] },
    rows,
  );
  const bound = Study.bind(base, [{ catalog, study }]);
  const routes = Study.compile(study, catalog);
  assert.equal(routes.length, study.routes.length, id);
  assert.equal(bound.studyCoverage[0].id, Number(id));
  assert.equal(bound.studyCoverage[0].cards, catalog.cards.length);
  for (const identity of Object.keys(bound.identities))
    assert(
      catalog.cards.some((c) => c.key === identity),
      "No Side or reserve identity enters the duel",
    );
  const changed = E.clone(catalog);
  if (changed.cards.length) {
    changed.cards[0].name = "Different card";
    assert(
      !Study.bind(base, [{ catalog: changed, study }]).identities[
        changed.cards[0].key
      ],
      "Changed native slot must be reidentified",
    );
  }
  routeCount += routes.length;
  deckCount++;
}
assert.equal(deckCount, 16);
console.log(
  JSON.stringify({
    ok: true,
    deckCount,
    routeCount,
    knownResponses: true,
    costsAndContinuation: true,
    formatIsolation: true,
    atomicRules: true,
    hiddenCardsNotFabricated: true,
  }),
);
