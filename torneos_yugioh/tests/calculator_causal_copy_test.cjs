const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");

// Memento: sending a second Ghattic from Deck cannot reuse the UID of an
// earlier Ghattic already in GY. Triggered revival belongs to the sent copy.
const ids = [19366, 19472];
const own = E.catalog({id:671,name:"Memento causal fixture",format_codes:["tcg_advanced"]},
  [{deck_id:671,id:1,section:"main",card_name:"Mementotlan Goblin",quantity:1},
   {deck_id:671,id:2,section:"main",card_name:"Mementotlan Ghattic",quantity:2}]);
const keys = own.cards.map(c=>c.key);
const bindings = {[keys[0]]:ids[0],[keys[1]]:ids[1]};
const source = "https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19366&ope=2";
const send = {version:1,id:"671:causal-two-copies",deckId:671,format:"tcg_advanced",
  scope:"single-effect",label:"Goblin sends one Ghattic",source,
  assumptions:["Synthetic isolated cause, not a full Goblin effect"],
  actor:{side:"self",zones:["monsters"],cid:19366,faceUp:true},
  guards:[{kind:"state",field:"active",values:["self"]}],
  select:[],costs:[],resolve:[{op:"take",side:"self",cid:19472,from:"deck",
    to:"graveyard",position:"attack",cause:"monster_effect"}],
  tags:["monsterEffect","sendFromDeck"],activation:true,oncePerTurn:true};
R.validate(send);
let state = E.create(own);
state = E.place(state,{key:keys[0],owner:"self",zone:"m1"});
state = E.place(state,{key:keys[1],owner:"self",zone:"graveyard"});
const oldUid = state.tokens.find(t=>t.key===keys[1]).uid;
const db = {bindings,facts:{},rules:[send]};
const sent = R.actions(state,db);
assert.equal(sent.actions.length,1,JSON.stringify(sent.rejected));
const after = sent.actions[0].state;
const event = after._ruleEvents.find(e=>e.kind==="sent_to_graveyard");
assert(event, "Sending by monster effect must create a causal event");
assert.notEqual(event.uid,oldUid, "Event must not attach to older GY copy");
assert.equal(after.tokens.filter(t=>t.key===keys[1]&&t.zone==="graveyard").length,2);
const latest = after.tokens.find(t=>t.uid===event.uid);
assert.equal(latest.key,keys[1]);
const revive = Verified.forDeck(671).filter(rule=>rule.id==="671:ghattic:revive" &&
  rule.trigger?.sourceCid===19366 && rule.resolve?.[0]?.to==="m2");
assert.equal(revive.length,1);
const options = R.actions(after,{bindings,facts:{},rules:revive});
assert(options.actions.some(a=>a.ids.includes(event.uid)),
  "The newly sent Ghattic must be available to the proven Goblin trigger");
assert(options.actions.every(a=>!a.ids.includes(oldUid)),
  "Older Ghattic must not borrow the newly sent copy's causal event");
console.log("Memento causal copy: distinct GY instances and event-bound trigger passed.");
