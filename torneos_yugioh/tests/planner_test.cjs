const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const P = require("../assets/modules/play-calculator/planner.js");
const db = require("../assets/modules/play-calculator/card-facts.json");
function catalog(id) {
  return E.catalog(
    { id, name: "Deck " + id, format_codes: ["tcg_advanced"] },
    Object.entries(db.bindings)
      .filter(([key]) => key.startsWith(id + ":"))
      .map(([key, cid]) => {
        const f = db.facts[cid];
        return {
          id: Number(key.split(":")[1]),
          deck_id: id,
          card_name: f.name,
          quantity: 3,
          section: /Xyz|Synchro|Fusion|Link/.test(f.species) ? "extra" : "main",
        };
      }),
  );
}
const a = catalog(665),
  b = catalog(656);
function add(s, cid, zone, side = "self", position = "attack") {
  const c = s[side].cards.find((c) => db.bindings[c.key] === cid);
  assert(c, "Fixture missing " + cid);
  return E.position(
    E.place(s, { key: c.key, owner: side, controller: side, zone }),
    s.nextId,
    position,
  );
}
let s = E.create(a, b);
s = add(s, 17768, "hand");
s = add(s, 18203, "hand");
const before = JSON.stringify(s),
  result = P.solve(s, db, { depth: 5, budget: 6000, width: 80 });
assert.equal(JSON.stringify(s), before, "Calculation must be immutable");
assert.equal(result.goal, "balanced");
for (const goal of ["balanced", "removal", "board", "resources", "resilience"]) {
  const ranked = P.solve(s, db, { depth: 3, budget: 400, goal });
  assert.equal(ranked.goal, goal);
  assert(ranked.plans.length);
  assert(ranked.plans.every((p) =>
    Number.isFinite(p.priority) &&
    Number.isFinite(p.metrics.field) &&
    Number.isInteger(p.metrics.hand) &&
    p.metrics.actions === p.steps.length,
  ));
}
assert.equal(P.solve(s, db, { goal: "nonexistent", depth: 1 }).goal, "balanced");
assert(
  result.plans.some((p) => p.steps.some((step) => step.label.includes("Xyz"))),
  "Must derive an Xyz sequence without a documented route",
);
for (const plan of result.plans) {
  const checks = plan.steps.filter(
    (step) => step.label.includes("añade") && step.label.includes("Fenrir"),
  );
  assert(checks.length <= 1, "Once per turn cannot be reused");
  for (const step of plan.steps) {
    const x = E.clone(s);
    x.tokens = step.after.map(({ name, cid, ...t }) => t);
    x.nextId = Math.max(0, ...x.tokens.map((t) => t.uid)) + 1;
    E.validate(x);
  }
}
assert.equal(P.solve({ ...s, active: "opponent" }, db).plans.length, 0);
assert.equal(
  P.solve(
    {
      ...s,
      format: "rush_duel",
      self: { ...s.self, formats: ["rush_duel"] },
      opponent: { ...s.opponent, formats: ["rush_duel"] },
    },
    db,
  ).plans.length,
  0,
);
assert(
  !P.solve(s, db, { specialBlocked: true }).plans.some((p) =>
    p.steps.some((step) => /Especial|Xyz/.test(step.label)),
  ),
);
assert(
  !P.solve(s, db, { used: ["17768:search"] }).plans.some((p) =>
    p.steps.some((step) => step.label.startsWith("Kashtira Fenrir: añade")),
  ),
);
const limited = P.solve(s, db, { budget: 1 });
assert(limited.limited);
// A spell must consume its copy and LP. Payment of the last 1000 LP is forbidden.
let c = E.create(a, b);
c = add(c, 12464, "hand");
const target = c.opponent.cards.find((c) => P.profile(c, db)?.kind === "spell");
c = E.place(c, {
  key: target.key,
  owner: "opponent",
  controller: "opponent",
  zone: "s1",
});
let removal = P.solve(c, db);
assert(
  removal.plans.some(
    (p) =>
      p.lp.self === 7000 &&
      p.final.some((t) => t.owner === "opponent" && t.zone === "banished"),
  ),
);
c.lp.self = 1000;
assert(
  !P.solve(c, db).plans.some((p) =>
    p.steps.some((step) => step.label.includes("Cosmic Cyclone")),
  ),
);
// A full field still permits a Tribute Summon that frees its own zone.
let full = E.create(a, b);
for (let i = 1; i <= 5; i++) full = add(full, i % 2 ? 17768 : 17769, "m" + i);
full = add(full, 17770, "hand");
assert(
  P.solve(full, db, {
    depth: 1,
    noExtra: true,
    effectsNegated: true,
    specialBlocked: true,
  }).plans.some((p) =>
    p.steps.some((step) => step.label.includes("tributando")),
  ),
);
// Face-down banished monsters are not identifiable Birth targets.
let birth = E.create(a, b);
birth = add(birth, 17815, "s1");
birth = add(birth, 17768, "banished", "self", "set");
assert(
  !P.solve(birth, db).plans.some((p) =>
    p.steps.some((step) => step.label.includes("recupera")),
  ),
);
let eye = E.create(a, b);
eye = add(eye, 9861, "m1");
eye = add(eye, 17768, "material:1");
eye = add(eye, 17769, "material:1");
const rival = eye.opponent.cards.find(
  (c) => P.profile(c, db)?.kind === "monster",
);
eye = E.place(eye, {
  key: rival.key,
  owner: "opponent",
  controller: "opponent",
  zone: "m1",
});
const control = P.solve(eye, db, { depth: 1 }).plans.find((p) =>
  p.steps[0].label.includes("toma control"),
);
assert(control);
assert.equal(control.final.filter((t) => t.zone === "material:1").length, 1);
assert(
  control.final.some((t) => t.owner === "opponent" && t.controller === "self"),
);
assert(
  !P.solve(eye, db, { used: ["9861:control"], depth: 1 }).plans.some((p) =>
    p.steps[0].label.includes("toma control"),
  ),
);
console.log(
  JSON.stringify({
    ok: true,
    dynamicSearch: true,
    immutable: true,
    lpCosts: true,
    oncePerTurn: true,
    fullFieldTribute: true,
    hiddenInformation: true,
    formatIsolation: true,
  }),
);
