const assert = require("node:assert/strict");
const Policy = require("../assets/modules/play-calculator/chain-policy.js");
const Chain = require("../assets/modules/play-calculator/chain.js");
const Engine = require("../assets/modules/play-calculator/engine.js");
const facts = require("../assets/modules/play-calculator/card-facts.json");
const fixture = require("./fixtures/calculator_inventory.json");
assert.equal(Policy.version, 1);
assert.equal(Policy.MAX_LINKS, 20);
const response = { side: "opponent", outcome: "negate-effect" };
assert.equal(Policy.responseAllowed(response, { side: "self" }, 1), true);
assert.equal(Policy.responseAllowed(response, { side: "opponent" }, 1), false);
assert.equal(Policy.responseAllowed(response, { side: "self" }, 0), false);
assert.equal(Policy.responseAllowed({ ...response, outcome: "invented" }, { side: "self" }, 1), false);
assert.equal(Policy.canChainRule(0, { chainSpeed: 1 }), true);
assert.equal(Policy.canChainRule(1, { chainSpeed: 1 }), false);
assert.equal(Policy.canChainRule(1, { chainSpeed: 2 }), true);
assert.deepEqual(Policy.resolutionOrder(3), [2, 1, 0]);
assert.deepEqual(Policy.resolutionOrder(0), []);
assert.throws(() => Policy.resolutionOrder(21), /Cantidad/);
assert.deepEqual(Policy.negationOptions(response, "rule:x"), { negateEffectRuleIds: ["rule:x"] });
assert.deepEqual(Policy.negationOptions({ outcome: "negate-activation" }, "rule:x"),
  { negateActivationRuleIds: ["rule:x"] });
assert.deepEqual(Policy.negationOptions(null, "rule:x"), {});
assert.throws(() => Policy.negationOptions({ outcome: "invented" }, "rule:x"), /Negación/);
assert.equal(typeof Chain.resolve, "function");
const self = Engine.catalog(fixture.allDecks.find(d => d.id === 665), fixture.cards);
const opponent = Engine.catalog(fixture.allDecks.find(d => d.id === 656), fixture.cards);
const actorCard = self.cards.find(c => facts.bindings[c.key] === 17768);
assert(actorCard);
const scenario = Engine.place(Engine.create(self, opponent), { key: actorCard.key, owner: "self", zone: "m1" });
const rule = {
  version: 1, id: "chain-general-fixture", deckId: self.id, format: "tcg_advanced",
  scope: "single-effect", label: "Efecto sintético con coste",
  source: "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=17768",
  actor: { side: "self", zones: ["monsters"], cid: 17768, faceUp: true },
  guards: [{ kind: "state", field: "phase", values: ["main1"] }],
  select: [], costs: [{ op: "payLP", amount: 500 }],
  resolve: [{ op: "damage", side: "opponent", amount: 1000 }],
  assumptions: ["Prueba de cadena sin acreditar efecto de carta real"],
  tags: [], activation: true, oncePerTurn: true, activationNegatedConsumesUse: true,
};
const original = JSON.stringify(scenario);
const cl1 = Engine.appendChain(scenario, { side: "self", cardName: "Prueba", effect: "CL1",
  ruleId: rule.id, actorUid: scenario.tokens[0].uid });
const cl2 = Engine.appendChain(cl1, { side: "opponent", effect: "Niego activación declarada",
  outcome: "negate-activation" });
const resolved = Chain.resolve(cl2, { ...facts, rules: [rule] });
assert.equal(resolved.status, "conditional", JSON.stringify(resolved));
assert(resolved.branches.length > 0);
assert(resolved.branches.every(b => b.state.lp.self === 7500 && b.state.lp.opponent === 8000));
assert.equal(JSON.stringify(scenario), original);
console.log(JSON.stringify({ ok: true, bounded: true, reverse: true, paidCost: true, failClosed: true }));
