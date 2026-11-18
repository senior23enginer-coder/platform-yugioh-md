const assert = require("node:assert/strict");
const fs = require("node:fs");
const E = require("../assets/modules/play-calculator/engine.js");
const S = require("../assets/modules/play-calculator/study.js");
const C = require("../assets/modules/play-calculator/chain.js");
const base = require("../assets/modules/play-calculator/card-facts.json");
const study = JSON.parse(fs.readFileSync(`${__dirname}/../assets/editorial/20261117-folder-delivery-v40/665.json`));
const catalog = E.catalog({ id: 665, name: study.name, format_codes: [study.format] },
  study.cards.flatMap((c) => c.slots.map((slot) => ({
    deck_id: 665, id: slot.card_id, card_name: c.name, quantity: slot.quantity, section: slot.section,
  }))));
const db = S.bind(base, [{ catalog, study }]);
const fenrir = catalog.cards.find((c) => db.bindings[c.key] === 17768);
assert(fenrir);
let scenario = E.create(catalog);
scenario = E.place(scenario, { key: fenrir.key, owner: "self", zone: "m1" });
const uid = scenario.tokens[0].uid;
const old = JSON.stringify(scenario);
const first = E.appendChain(scenario, { side: "self", cardName: "Kashtira Fenrir",
  effect: "Añadir Riseheart", ruleId: "665:fenrir:search", actorUid: uid });
const normal = C.resolve(first, db);
assert.equal(normal.status, "conditional", JSON.stringify(normal));
assert(normal.branches.some((b) => b.state.tokens.some((t) =>
  db.bindings[t.key] === 18151 && t.zone === "hand")));
const second = E.appendChain(first, { side: "opponent", cardName: "Respuesta declarada",
  effect: "Negar efecto de Fenrir", outcome: "negate-effect" });
const negated = C.resolve(second, db);
assert.equal(negated.status, "conditional");
assert(negated.branches.every((b) => b.effectNegated &&
  !b.state.tokens.some((t) => db.bindings[t.key] === 18151 && t.zone === "hand")));
assert(negated.branches.every((b) => b.state.chain.length === 0));
assert.match(negated.warning, /declaradas por el jugador/i);
assert.equal(JSON.stringify(scenario), old);
const badResponse = E.appendChain(first, { side: "opponent", effect: "Respuesta sin resolver" });
assert.equal(C.resolve(badResponse, db).status, "unresolved");
const unmodelled = E.appendChain(scenario, { side: "self", effect: "Efecto sin regla" });
assert.equal(C.resolve(unmodelled, db).status, "unresolved");
const activation = E.appendChain(first, { side: "opponent", effect: "Niego activación", outcome: "negate-activation" });
assert.equal(C.resolve(activation, db).status, "unresolved",
  "Do not invent the once-per-turn outcome of activation negation");
const priced = { ...db.rules.find((r) => r.id === "665:fenrir:search"),
  id: "665:test:priced-search", costs: [{ op: "payLP", amount: 500 }],
  activationNegatedConsumesUse: true };
assert(priced.actor);
const pricedDb = { ...db, rules: [priced] };
const pricedFirst = E.appendChain(scenario, { side: "self", cardName: "Coste de prueba",
  effect: "Buscar después de pagar 500 LP", ruleId: priced.id, actorUid: uid });
const pricedSecond = E.appendChain(pricedFirst, { side: "opponent", effect: "Niego la activación",
  outcome: "negate-activation" });
const pricedResult = C.resolve(pricedSecond, pricedDb);
assert.equal(pricedResult.status, "conditional");
assert(pricedResult.branches.every((b) => b.state.lp.self === 7500),
  "A negated activation does not refund a paid LP cost");
assert(pricedResult.branches.every((b) => b.state._ruleUseTurns["rule:" + priced.id] === 1),
  "Explicit activation-negation usage semantics are preserved");
assert(pricedResult.branches.every((b) => !b.state.tokens.some((t) =>
  db.bindings[t.key] === 18151 && t.zone === "hand")));
const repeatAllowed = { ...priced, activationNegatedConsumesUse: false };
const repeatResult = C.resolve(pricedSecond, { ...db, rules: [repeatAllowed] });
assert.equal(repeatResult.status, "conditional");
assert(repeatResult.branches.every((b) => !(b.state._used || []).includes("rule:" + priced.id) &&
  b.state._ruleUseTurns?.["rule:" + priced.id] === undefined),
  "Explicit activation-negation semantics can leave the effect available");
assert.throws(() => E.appendChain(scenario, { side: "opponent", effect: "x", outcome: "arbitrary" }),
  /Cadena pendiente inválida/);
const unicorn = catalog.cards.find((c) => db.bindings[c.key] === 17769);
assert(unicorn);
let multi = E.place(scenario, { key: unicorn.key, owner: "self", zone: "m2" });
const unicornUid = multi.tokens.find((t) => t.key === unicorn.key).uid;
const cl1Rule = { ...priced, id: "665:test:cl1-search" };
const quick = { ...priced, id: "665:test:cl2-quick", label: "Efecto rápido de prueba",
  actor: { side: "self", zones: ["monsters"], cid: 17769, faceUp: true },
  chainSpeed: 2, costs: [{ op: "payLP", amount: 300 }],
  resolve: [{ op: "damage", side: "opponent", amount: 1000 }], tags: ["monsterEffect"] };
const multiDb = { ...db, rules: [cl1Rule, quick] };
multi = E.appendChain(multi, { side: "self", effect: "CL1 busca", ruleId: cl1Rule.id, actorUid: uid });
multi = E.appendChain(multi, { side: "self", effect: "CL2 rápido", ruleId: quick.id, actorUid: unicornUid });
const multiResult = C.resolve(multi, multiDb);
assert.equal(multiResult.status, "conditional", JSON.stringify(multiResult));
assert(multiResult.branches.some((b) => b.state.lp.self === 7200 && b.state.lp.opponent === 7000 &&
  b.state.tokens.some((t) => db.bindings[t.key] === 18151 && t.zone === "hand")));
assert(multiResult.branches[0].label.indexOf("rápido") < multiResult.branches[0].label.indexOf("Fenrir"),
  "The last chain link must resolve first");
const lockQuick = { ...quick, id: "665:test:cl2-lock", resolve: [{ op: "flag", flag: "noDeckToHand", value: true }] };
const locked = E.appendChain(E.removeChain(multi, 2), { side: "self", effect: "CL2 bloquea búsquedas",
  ruleId: lockQuick.id, actorUid: unicornUid });
assert.equal(C.resolve(locked, { ...db, rules: [cl1Rule, lockQuick] }).status, "unresolved",
  "An earlier link cannot search after a later link resolves a deck-to-hand lock");
const slow = { ...quick, id: "665:test:illegal-slow", chainSpeed: 1 };
const slowChain = E.appendChain(E.removeChain(multi, 2), { side: "self", effect: "CL2 lento",
  ruleId: slow.id, actorUid: unicornUid });
assert.equal(C.resolve(slowChain, { ...db, rules: [cl1Rule, slow] }).status, "unresolved");
const cl3 = { ...quick, id: "665:test:cl3-quick", label: "Tercer efecto rápido",
  costs: [], resolve: [{ op: "damage", side: "opponent", amount: 200 }] };
let three = E.appendChain(multi, { side: "self", effect: "CL3 rápido", ruleId: cl3.id, actorUid: unicornUid });
three = E.appendChain(three, { side: "opponent", effect: "CL4 niega CL3", outcome: "negate-effect" });
const longer = C.resolve(three, { ...db, rules: [cl1Rule, quick, cl3] });
assert.equal(longer.status, "conditional", JSON.stringify(longer));
assert(longer.branches.some((b) => b.state.lp.opponent === 7000),
  "CL4 must negate CL3 without negating CL2 or CL1");
const unknownCounter = E.appendChain(three, { side: "opponent", effect: "CL5 responde a CL4", outcome: "negate-effect" });
assert.equal(C.resolve(unknownCounter, { ...db, rules: [cl1Rule, quick, cl3] }).status, "unresolved",
  "Do not guess whether a response negates another opponent response");
const ui = fs.readFileSync(`${__dirname}/../assets/modules/play-calculator/ui.js`, "utf8");
for (const hook of ["data-chain-rule", "data-chain-actor", "data-chain-outcome", "data-chain-preview", "data-chain-apply"])
  assert(ui.includes(hook), "Missing structured chain UI control: " + hook);
console.log("Chain replay: CL1 verified effect, CL2 conditional effect negation, unknowns fail closed: OK");
