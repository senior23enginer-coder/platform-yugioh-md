const assert = require("node:assert/strict");
const Rules = require("../assets/modules/play-calculator/rules.js");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");

// These are executable variants, not independent certified effects or routes.
const variants = Verified.forDeck(671);
assert.equal(variants.length, 233, "Memento must preserve all verified fallback variants");
const byEffect = new Map();
for (const rule of variants) {
  Rules.validate(rule);
  const rows = byEffect.get(rule.id) || [];
  rows.push(rule);
  byEffect.set(rule.id, rows);
}
assert.equal(byEffect.size, 42, "Memento must preserve distinct effect IDs including Lancea");
for (const [effectId, rows] of byEffect) {
  const signatures = new Set(rows.map(row =>
    JSON.stringify([row.actor.cid, row.oncePerTurn, row.activation])));
  assert.equal(signatures.size, 1,
    effectId + ": variants of one effect must share the actor and once-per-turn policy");
  assert(rows.every(row => row.deckId === 671 && row.format === "tcg_advanced"));
}
const goblin = byEffect.get("671:goblin:send-two");
assert.equal(goblin.length, 3);
assert(goblin.every(row => row.oncePerTurn),
  "Goblin send variants must share a per-turn marker");
assert(goblin.some(row => row.select.some(selection => selection.ref === "victim")),
  "Goblin must support a separately identified Memento destruction target");
console.log(JSON.stringify({ ok: true, variants: variants.length, effectIds: byEffect.size,
  documentedRoutesCertified: false }));
