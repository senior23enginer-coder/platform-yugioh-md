const assert = require("node:assert/strict");
const fs = require("node:fs");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const S = require("../assets/modules/play-calculator/study.js");
const base = require("../assets/modules/play-calculator/card-facts.json");

function loaded(id) {
  const data = JSON.parse(fs.readFileSync(`${__dirname}/../assets/editorial/20261117-folder-delivery-v40/${id}.json`));
  const rows = data.cards.flatMap((card) => card.slots.map((slot) => ({
    id: slot.card_id, deck_id: id, card_name: card.name, quantity: slot.quantity, section: slot.section,
  })));
  const catalog = E.catalog({ id, name: data.name, format_codes: [data.format] }, rows);
  const db = S.bind(base, [{ catalog, study: data }]);
  return { catalog, db };
}
function card(catalog, db, cid) {
  const found = catalog.cards.find((row) => db.bindings[row.key] === cid);
  assert(found, `Missing CID ${cid} from verified study`);
  return found;
}
const memento = loaded(671);
let s = E.create(memento.catalog);
const blade = card(memento.catalog, memento.db, 19363);
card(memento.catalog, memento.db, 19366);
s = E.place(s, { key: blade.key, owner: "self", zone: "m1" });
const original = JSON.stringify(s);
const result = R.actions(s, memento.db);
const bladeActions = result.actions.filter((a) => a.event.ruleId === "671:dark-blade:goblin");
assert.equal(bladeActions.length, 5, JSON.stringify(result.rejected));
const next = bladeActions[0].state;
assert(next.tokens.some((t) => t.key === blade.key && t.zone === "graveyard"));
assert(next.tokens.some((t) => memento.db.bindings[t.key] === 19366 && t.zone === "m1"));
assert.equal(JSON.stringify(s), original, "Calculating actions must not mutate the input");
assert.equal(R.actions(next, memento.db).actions.filter((a) => a.event.ruleId === "671:dark-blade:goblin").length, 0);
const negated = R.actions(s, memento.db, { negateEffectRuleIds: ["671:dark-blade:goblin"] });
assert(negated.actions.some((a) => a.event.effectNegated && a.state.tokens.some((t) => t.key === blade.key && t.zone === "m1")));
let goblinStart = E.create(memento.catalog);
const goblin = card(memento.catalog, memento.db, 19366);
goblinStart = E.place(goblinStart, { key: goblin.key, owner: "self", zone: "m3" });
const goblinActions = R.actions(goblinStart, memento.db).actions.filter((a) => a.event.ruleId === "671:goblin:send-two");
assert.equal(goblinActions.length, 1);
const sent = goblinActions[0].state.tokens;
for (const cid of [19366, 19363, 19364])
  assert(sent.some((t) => memento.db.bindings[t.key] === cid && t.zone === "graveyard"));
const goblinNegated = R.actions(goblinStart, memento.db, { negateEffectRuleIds: ["671:goblin:send-two"] });
assert.equal(goblinNegated.actions.length, 1);
assert(goblinNegated.actions[0].state.tokens.every((t) => t.zone !== "graveyard"));
const creation = card(memento.catalog, memento.db, 19360);
let five = E.create(memento.catalog);
five = E.place(five, { key: creation.key, owner: "self", zone: "hand" });
for (const id of [19362, 19363, 19364, 19366, 20199]) {
  five = E.place(five, { key: card(memento.catalog, memento.db, id).key,
    owner: "self", zone: id === 19364 ? "hand" : "graveyard" });
}
const fiveActions = R.actions(five, memento.db).actions.filter((a) => a.event.ruleId === "671:tecuhtlica:five-names");
assert.equal(fiveActions.length, 5, "Five distinct Memento names must enable five free monster zones");
const combined = fiveActions[0].state;
assert(combined.tokens.some((t) => t.key === creation.key && t.zone === "m1"));
assert.equal(combined.tokens.filter((t) => [19362, 19363, 19364, 19366, 20199]
  .includes(memento.db.bindings[t.key])).length, 0, "All five resources were shuffled into the Deck");
const four = E.clone(five);
four.tokens = four.tokens.filter((t) => memento.db.bindings[t.key] !== 20199);
assert.equal(R.actions(four, memento.db).actions.filter((a) => a.event.ruleId === "671:tecuhtlica:five-names").length, 0);
const repeated = E.place(four, { key: card(memento.catalog, memento.db, 19363).key,
  owner: "self", zone: "graveyard" });
assert.equal(R.actions(repeated, memento.db).actions.filter((a) => a.event.ruleId === "671:tecuhtlica:five-names").length, 0,
  "Five cards with only four distinct names cannot summon Combined Creation");
assert.equal(R.actions(five, memento.db, { specialBlocked: true }).actions.filter((a) => a.event.ruleId === "671:tecuhtlica:five-names").length, 0);
assert.equal(R.actions(five, memento.db, { blockedZones: ["m1", "m2", "m3", "m4", "m5"] }).actions.filter((a) => a.event.ruleId === "671:tecuhtlica:five-names").length, 0);
const kashtira = loaded(665);
let k = E.create(kashtira.catalog);
const fenrir = card(kashtira.catalog, kashtira.db, 17768);
card(kashtira.catalog, kashtira.db, 18151);
k = E.place(k, { key: fenrir.key, owner: "self", zone: "m1" });
const search = R.actions(k, kashtira.db).actions.find((a) => a.event.ruleId === "665:fenrir:search");
assert(search, "Fenrir search is an executable rule");
assert(search.state.tokens.some((t) => kashtira.db.bindings[t.key] === 18151 && t.zone === "hand"));
assert.equal(R.actions(k, kashtira.db, { noDeckToHand: true }).actions.filter((a) => a.event.ruleId === "665:fenrir:search").length, 0);
console.log("Verified rules: Memento Dark Blade/Goblin/Combined Creation and Kashtira Fenrir: OK");
