const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");

// A narrow executable fixture: Goblin may destroy another Memento monster
// but cannot bypass the once-per-turn marker shared with its self-destroy line.
const cids = [19366, 19365, 19363, 19364];
const names = ["Mementotlan Goblin", "Mementotlan Mace",
  "Mementotlan Dark Blade", "Mementotlan Angwitch"];
const own = E.catalog({ id: 671, name: "Memento", format_codes: ["tcg_advanced"] },
  cids.map((cid, i) => ({ deck_id: 671, id: i + 1, section: "main",
    card_name: names[i], quantity: 1 })));
const bindings = Object.fromEntries(own.cards.map((card, i) => [card.key, cids[i]]));
const rules = Verified.forDeck(671).filter(rule => rule.id === "671:goblin:send-two");
assert.equal(rules.length, 4, "Existing and Ghattic send-pair variants preserved");
for (const rule of rules) R.validate(rule);
const db = { bindings, rules, facts: {} };
function fixture() {
  let s = E.create(own);
  s = E.place(s, { key: own.cards[0].key, owner: "self", zone: "m1" });
  s = E.place(s, { key: own.cards[1].key, owner: "self", zone: "m2" });
  return s;
}
const scenario = fixture();
const original = JSON.stringify(scenario);
const result = R.actions(scenario, db);
assert.equal(JSON.stringify(scenario), original, "Do not mutate the captured state");
const alternate = result.actions.find(action => action.label.includes("otro Memento"));
assert(alternate, "Goblin must offer a different verified Memento as destruction target");
assert.equal(alternate.used, "rule:671:goblin:send-two");
const zone = cid => alternate.state.tokens.find(t => bindings[t.key] === cid)?.zone;
assert.equal(zone(19366), "m1", "Goblin survives when another Memento is destroyed");
assert.equal(zone(19365), "graveyard", "Selected Memento is destroyed");
assert.equal(zone(19363), "graveyard");
assert.equal(zone(19364), "graveyard");
assert.equal(R.actions(alternate.state, db).actions.length, 0,
  "The same Goblin effect cannot be used twice after choosing its alternate line");
const withoutTarget = E.place(scenario, {
  key: own.cards[1].key, owner: "self", uid: scenario.tokens[1].uid, zone: "deck",
});
assert(!R.actions(withoutTarget, db).actions.some(a => a.label.includes("otro Memento")),
  "No other Memento target means no alternate-target line");
console.log("Memento Goblin alternate destruction: legal target, zones, shared once-per-turn and missing target verified.");
