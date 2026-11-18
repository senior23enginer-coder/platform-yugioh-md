const assert = require("node:assert/strict");
const fs = require("node:fs");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const P = require("../assets/modules/play-calculator/planner.js");
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
const marked = P.capabilities(E.create(memento.catalog), memento.db);
for (const id of ["671:dark-blade:goblin", "671:goblin:send-two", "671:tecuhtlica:five-names"])
  assert.equal(marked.filter((row) => row.id === "rule:" + id).length, 1,
    "One effect must have one shared used-effect checkbox: " + id);
let s = E.create(memento.catalog);
const blade = card(memento.catalog, memento.db, 19363);
card(memento.catalog, memento.db, 19366);
s = E.place(s, { key: blade.key, owner: "self", zone: "m1" });
const original = JSON.stringify(s);
const result = R.actions(s, memento.db);
const bladeActions = result.actions.filter((a) => a.event.ruleId === "671:dark-blade:goblin");
assert.equal(bladeActions.length, 5, JSON.stringify(result.rejected));
assert.equal(R.actions({ ...s, _used: ["rule:671:dark-blade:goblin"] }, memento.db)
  .actions.filter((a) => a.event.ruleId === "671:dark-blade:goblin").length, 0,
  "A declared manual used-effect marker must suppress every zone variant");
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
assert.equal(goblinActions.length, 2, "Two verified Goblin send-pairs share one effect use");
const toAngwitch = goblinActions.find((a) => a.state.tokens.some((t) =>
  t.zone === "graveyard" && memento.db.bindings[t.key] === 19364));
const toGhattic = goblinActions.find((a) => a.state.tokens.some((t) =>
  t.zone === "graveyard" && memento.db.bindings[t.key] === 19472));
assert(toAngwitch && toGhattic);
const sent = toAngwitch.state.tokens;
for (const cid of [19366, 19363, 19364])
  assert(sent.some((t) => memento.db.bindings[t.key] === cid && t.zone === "graveyard"));
assert.deepEqual(toAngwitch.state._ruleEvents.filter((e) => e.kind === "sent_to_graveyard")
  .map((e) => e.cid), [19363, 19364], "The Goblin effect must record causal GY sends");
const goblinNegated = R.actions(goblinStart, memento.db, { negateEffectRuleIds: ["671:goblin:send-two"] });
assert.equal(goblinNegated.actions.length, 2);
assert(goblinNegated.actions[0].state.tokens.every((t) => t.zone !== "graveyard"));
const ghatticRevivals = R.actions(toGhattic.state, memento.db).actions.filter((a) =>
  a.event.ruleId === "671:ghattic:revive");
assert.equal(ghatticRevivals.length, 5,
  "Ghattic can be Special Summoned to any free zone after Goblin sends it by effect");
const revived = ghatticRevivals[0].state;
assert(revived.tokens.some((t) => t.zone === "m1" && memento.db.bindings[t.key] === 19472));
assert(revived._ruleEvents.some((e) => e.kind === "summon" &&
  e.method === "special" && e.cid === 19472));
assert(R.actions(revived, memento.db).actions.some((a) =>
  a.event.ruleId === "671:ghattic:recover" && a.state.tokens.some((t) =>
    t.zone === "hand" && memento.db.bindings[t.key] === 19363)),
"Ghattic's search must be available after its proved Special Summon");
assert.equal(R.actions(toGhattic.state, memento.db, { specialBlocked: true }).actions
  .filter((a) => a.event.ruleId === "671:ghattic:revive").length, 0);
const fakeSent = E.clone(toGhattic.state);
fakeSent._ruleEvents = fakeSent._ruleEvents.filter((e) => e.kind !== "sent_to_graveyard");
assert.equal(R.actions(fakeSent, memento.db).actions.filter((a) =>
  a.event.ruleId === "671:ghattic:revive").length, 0,
"A Ghattic card in GY cannot revive without an identified source effect");
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

// Unlike test-only A07 contracts, these rules are bound to the real study.
const angwitch = card(memento.catalog, memento.db, 19364);
let angHand = E.create(memento.catalog);
angHand = E.place(angHand, { key: angwitch.key, owner: "self", zone: "hand" });
assert.equal(R.actions(angHand, memento.db).actions.filter((a) => a.event.ruleId === "671:angwitch:search").length, 0,
  "A card merely in hand must not obtain a summon-triggered search");
const angNormal = R.actions(angHand, memento.db).actions.find((a) => a.event.ruleId === "671:19364:normal");
assert(angNormal, "The verified Angwitch Normal Summon procedure must be available");
assert(angNormal.state._ruleEvents.some((e) => e.kind === "summon" && e.cid === 19364 && e.method === "normal"));
const angSearches = R.actions(angNormal.state, memento.db).actions
  .filter((a) => a.event.ruleId === "671:angwitch:search");
assert(angSearches.some((a) => a.state.tokens.some((t) =>
  t.zone === "hand" && memento.db.bindings[t.key] === 19366)),
  "The summon-triggered Angwitch search must find Goblin in the real Main Deck");
assert.equal(R.actions(angNormal.state, memento.db, { noDeckToHand: true }).actions
  .filter((a) => a.event.ruleId === "671:angwitch:search").length, 0,
"Droll's Deck-to-hand restriction must prevent the search");
const completedAng = angSearches.find((a) => a.state.tokens.some((t) =>
  t.zone === "hand" && memento.db.bindings[t.key] === 19366)).state;
assert.equal(R.actions(completedAng, memento.db).actions
  .filter((a) => a.event.ruleId === "671:angwitch:search").length, 0,
  "Every Angwitch search target shares a single use per turn");
const falseEvent = E.clone(angNormal.state);
falseEvent._ruleEvents[0].uid += 500;
assert.equal(R.actions(falseEvent, memento.db).actions
  .filter((a) => a.event.ruleId === "671:angwitch:search").length, 0,
  "An unrelated summon event must not authorize Angwitch's effect");

const ghattic = card(memento.catalog, memento.db, 19472);
let ghatticHand = E.create(memento.catalog);
ghatticHand = E.place(ghatticHand, { key: ghattic.key, owner: "self", zone: "hand" });
ghatticHand = E.place(ghatticHand, { key: blade.key, owner: "self", zone: "graveyard" });
assert.equal(R.actions(ghatticHand, memento.db).actions
  .filter((a) => a.event.ruleId === "671:ghattic:recover").length, 0);
const ghatticNormal = R.actions(ghatticHand, memento.db).actions
  .find((a) => a.event.ruleId === "671:19472:normal");
assert(ghatticNormal, "The Ghattic summon procedure must be available");
const ghatticRecover = R.actions(ghatticNormal.state, memento.db).actions
  .find((a) => a.event.ruleId === "671:ghattic:recover");
assert(ghatticRecover, "Ghattic must recover an eligible Memento card after being Summoned");
assert(ghatticRecover.state.tokens.some((t) => t.key === blade.key && t.zone === "hand"));
assert.equal(R.actions(ghatticRecover.state, memento.db).actions
  .filter((a) => a.event.ruleId === "671:ghattic:recover").length, 0,
  "Ghattic cannot recover a second card on the same turn");

const mace = card(memento.catalog, memento.db, 19365);
let maceStart = E.create(memento.catalog);
maceStart = E.place(maceStart, { key: mace.key, owner: "self", zone: "m1" });
const maceAction = R.actions(maceStart, memento.db).actions.find((a) =>
  a.event.ruleId === "671:mace:search" && a.state.tokens.some((t) =>
    t.zone === "hand" && memento.db.bindings[t.key] === 19366));
assert(maceAction, "Mace must be able to destroy itself and search Goblin");
assert(maceAction.state.tokens.some((t) => t.key === mace.key && t.zone === "graveyard"));
assert.equal(R.actions(maceStart, memento.db, { noDeckToHand: true }).actions
  .filter((a) => a.event.ruleId === "671:mace:search").length, 0);
const maceNegated = R.actions(maceStart, memento.db, { negateEffectRuleIds: ["671:mace:search"] });
assert(maceNegated.actions.some((a) => a.event.effectNegated &&
  a.state.tokens.some((t) => t.key === mace.key && t.zone === "m1")),
  "Negating Mace's effect must also stop the destruction that occurs on resolution");

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
