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
assert(next._ruleEvents.some((e) => e.kind === "summon" && e.cid === 19366 &&
  e.method === "special"), "Dark Blade must record Goblin's Special Summon from Deck");
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

const tatsun = card(memento.catalog, memento.db, 19362);
const grabber = card(memento.catalog, memento.db, 20196);
let tatsunHand = E.create(memento.catalog);
tatsunHand = E.place(tatsunHand, { key: tatsun.key, owner: "self", zone: "hand" });
tatsunHand = E.place(tatsunHand, { key: angwitch.key, owner: "self", zone: "m1" });
const ownMementoSummons = R.actions(tatsunHand, memento.db).actions.filter((a) =>
  a.event.ruleId === "671:tatsun:special");
assert.equal(ownMementoSummons.length, 4,
  "Tatsun may Special Summon itself with only a Memento monster face-up and four free zones");
let foreignMonster = E.place(tatsunHand, { key: grabber.key, owner: "self", zone: "m2" });
assert.equal(R.actions(foreignMonster, memento.db).actions
  .filter((a) => a.event.ruleId === "671:tatsun:special").length, 0,
  "A face-up non-Memento monster must prevent Tatsun's Special Summon");
foreignMonster = E.position(foreignMonster, foreignMonster.tokens.find((t) =>
  t.key === grabber.key).uid, "set");
assert.equal(R.actions(foreignMonster, memento.db).actions
  .filter((a) => a.event.ruleId === "671:tatsun:special").length, 3,
  "The official condition concerns face-up monsters, not face-down ones");
assert.equal(R.actions(tatsunHand, memento.db, { specialBlocked: true }).actions
  .filter((a) => a.event.ruleId === "671:tatsun:special").length, 0);
const summonedTatsun = ownMementoSummons[0].state;
assert(summonedTatsun._ruleEvents.some((e) => e.kind === "summon" &&
  e.cid === 19362 && e.method === "special"));
const sentByTatsun = R.actions(summonedTatsun, memento.db).actions.find((a) =>
  a.event.ruleId === "671:tatsun:mill" &&
  [19472, 19366].every((cid) => a.state.tokens.some((t) =>
    memento.db.bindings[t.key] === cid && t.zone === "graveyard")));
assert(sentByTatsun, "Tatsun can destroy Level 3 Angwitch and send Level 2 Ghattic + Level 1 Goblin");
assert(sentByTatsun.state.tokens.some((t) => t.key === angwitch.key && t.zone === "graveyard"));
assert.deepEqual(sentByTatsun.state._ruleEvents.filter((e) => e.kind === "sent_to_graveyard")
  .map((e) => e.sourceCid), [19362, 19362]);
assert(R.actions(sentByTatsun.state, memento.db).actions.some((a) =>
  a.event.ruleId === "671:ghattic:revive"),
"The Ghattic revival must recognize Tatsun as the verified source");
assert.equal(R.actions(sentByTatsun.state, memento.db).actions.filter((a) =>
  a.event.ruleId === "671:tatsun:mill").length, 0,
"Tatsun's destruction/mill effect cannot be used again during the same turn");

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

const boneParty = card(memento.catalog, memento.db, 19368);
let boneStart = E.create(memento.catalog);
boneStart = E.place(boneStart, { key: boneParty.key, owner: "self", zone: "hand" });
boneStart = E.place(boneStart, { key: tatsun.key, owner: "self", zone: "m1" });
const boneActions = R.actions(boneStart, memento.db).actions
  .filter((a) => a.event.ruleId === "671:bone-party:main");
const boneAdd = boneActions.find((a) => a.state.tokens.some((t) =>
  memento.db.bindings[t.key] === 19364 && t.zone === "hand"));
assert(boneAdd, "Bone Party must destroy Tatsun and add the different-name Angwitch from Deck");
assert(boneAdd.state.tokens.some((t) => t.key === boneParty.key && t.zone === "graveyard"));
assert(boneAdd.state.tokens.some((t) => t.key === tatsun.key && t.zone === "graveyard"));
const boneDefense = boneActions.find((a) => a.state.tokens.some((t) =>
  memento.db.bindings[t.key] === 19364 && t.zone === "m1" && t.position === "defense"));
assert(boneDefense, "Bone Party must also Special Summon Angwitch in Defense Position");
assert(boneDefense.state._ruleEvents.some((e) =>
  e.kind === "summon" && e.cid === 19364 && e.method === "special"));
assert(R.actions(boneDefense.state, memento.db).actions.some((a) =>
  a.event.ruleId === "671:angwitch:search"),
  "The Special Summon from Bone Party must activate Angwitch's search window");
assert.equal(R.actions(boneDefense.state, memento.db).actions.filter((a) =>
  a.event.ruleId === "671:bone-party:main").length, 0,
  "One Bone Party activation cannot then choose its other outcome");
const boneWithDroll = R.actions(boneStart, memento.db, { noDeckToHand: true }).actions
  .filter((a) => a.event.ruleId === "671:bone-party:main");
assert(boneWithDroll.length > 0 && boneWithDroll.every((a) =>
  a.state.tokens.some((t) => memento.db.bindings[t.key] === 19364 && E.isMonster(t.zone))),
  "Droll blocks adding but does not block Bone Party's distinct Special Summon choice");
const boneBlocked = R.actions(boneStart, memento.db, { specialBlocked: true }).actions
  .filter((a) => a.event.ruleId === "671:bone-party:main");
assert(boneBlocked.length > 0 && boneBlocked.every((a) =>
  a.state.tokens.some((t) => memento.db.bindings[t.key] === 19364 && t.zone === "hand")),
  "Blocking Special Summons must leave only Bone Party's add-to-hand choice");
const boneNegated = R.actions(boneStart, memento.db, { negateEffectRuleIds: ["671:bone-party:main"] })
  .actions.filter((a) => a.event.ruleId === "671:bone-party:main");
assert(boneNegated.some((a) => a.state.tokens.some((t) => t.key === boneParty.key && t.zone === "graveyard") &&
  a.state.tokens.some((t) => t.key === tatsun.key && t.zone === "m1")),
  "Negating Bone Party's effect preserves the activated Spell leaving hand but does not destroy Tatsun");

const shleepy = card(memento.catalog, memento.db, 20199);
let shleepyHand = E.create(memento.catalog);
shleepyHand = E.place(shleepyHand, { key: shleepy.key, owner: "self", zone: "hand" });
assert.equal(R.actions(shleepyHand, memento.db).actions.filter((a) =>
  a.event.ruleId === "671:shleepy:special").length, 0,
"Dormiveja cannot Special Summon itself without a proved destruction this turn");
const madeDestroy = E.place(shleepyHand, { key: mace.key, owner: "self", zone: "m1" });
const destroyedMace = R.actions(madeDestroy, memento.db).actions.find((a) =>
  a.event.ruleId === "671:mace:search");
assert(destroyedMace && destroyedMace.state._ruleEvents.some((e) =>
  e.kind === "destroy" && e.owner === "self" && e.turn === 1));
const shleepySpecials = R.actions(destroyedMace.state, memento.db).actions.filter((a) =>
  a.event.ruleId === "671:shleepy:special");
assert.equal(shleepySpecials.length, 5,
  "Dormiveja may Special Summon itself after a Memento you possess was destroyed by effect");
assert.equal(R.actions(E.patch(destroyedMace.state, { turn: 2 }), memento.db).actions.filter((a) =>
  a.event.ruleId === "671:shleepy:special").length, 0,
"The destruction from an earlier turn must not permit Dormiveja to Summon");
assert.equal(R.actions(destroyedMace.state, memento.db, { specialBlocked: true }).actions.filter((a) =>
  a.event.ruleId === "671:shleepy:special").length, 0);

let fusionWithShleepy = E.create(memento.catalog);
for (const c of [shleepy, angwitch, ghattic])
  fusionWithShleepy = E.place(fusionWithShleepy, { key: c.key, owner: "self", zone: "hand" });
const normalShleepy = R.actions(fusionWithShleepy, memento.db).actions.find((a) =>
  a.event.ruleId === "671:20199:normal");
assert(normalShleepy, "The normal Summon must create the causal trigger for Dormiveja Fusion");
const shleepyFusion = R.actions(normalShleepy.state, memento.db).actions.find((a) =>
  a.event.ruleId === "671:shleepy:fusion");
assert(shleepyFusion, "Dormiveja must fuse with Angbruja and Desvantasma to make Creation King");
assert(shleepyFusion.state.tokens.some((t) =>
  memento.db.bindings[t.key] === 20216 && E.isMonster(t.zone)));
for (const cid of [20199, 19364, 19472])
  assert(shleepyFusion.state.tokens.some((t) =>
    memento.db.bindings[t.key] === cid && t.zone === "graveyard"));
assert.equal(R.actions(normalShleepy.state, memento.db, { noExtra: true }).actions.filter((a) =>
  a.event.ruleId === "671:shleepy:fusion").length, 0);

let destroyedShleepy = E.create(memento.catalog);
destroyedShleepy = E.place(destroyedShleepy, { key: boneParty.key, owner: "self", zone: "hand" });
destroyedShleepy = E.place(destroyedShleepy, { key: shleepy.key, owner: "self", zone: "hand" });
const boneDestroyShleepy = R.actions(destroyedShleepy, memento.db).actions.find((a) =>
  a.event.ruleId === "671:bone-party:main" && a.state.tokens.some((t) =>
    t.key === shleepy.key && t.zone === "graveyard"));
assert(boneDestroyShleepy, "Bone Party may destroy Dormiveja from hand as a card effect");
const sentAfterDestroy = R.actions(boneDestroyShleepy.state, memento.db).actions.find((a) =>
  a.event.ruleId === "671:shleepy:send" && a.state.tokens.some((t) =>
    memento.db.bindings[t.key] === 19472 && t.zone === "graveyard"));
assert(sentAfterDestroy, "Destroyed Dormiveja may send Desvantasma from Deck to GY");
assert(sentAfterDestroy.state._ruleEvents.some((e) =>
  e.kind === "sent_to_graveyard" && e.cid === 19472 && e.sourceCid === 20199));
assert(R.actions(sentAfterDestroy.state, memento.db).actions.some((a) =>
  a.event.ruleId === "671:ghattic:revive"),
"Desvantasma must be able to revive following the verified Dormiveja send");

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
