const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const Restrictions = require("../assets/modules/play-calculator/restrictions.js");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");

// Side Deck card becomes legal only after it has been exchanged into Main.
// This fixture models that post-Side inventory, not the active Memento Main.
const own = E.catalog({id:671,name:"Memento after Side",format_codes:["tcg_advanced"]},
  [{deck_id:671,id:1,card_name:"Artifact Lancea",section:"main",quantity:1}]);
const enemy = E.catalog({id:672,name:"Opponent",format_codes:["tcg_advanced"]},
  [{deck_id:672,id:1,card_name:"Opponent monster",section:"main",quantity:1}]);
const rule = Verified.forDeck(671).find(r=>r.id==="671:lancea:banish-lock");
assert(rule);
R.validate(rule);
const db={bindings:{[own.cards[0].key]:11243},rules:[rule],facts:{}};
function scenario(zone="hand",active="opponent",position="attack"){
  let s=E.create(own,enemy);
  s=E.place(s,{key:own.cards[0].key,owner:"self",zone});
  s=E.position(s,s.nextId-1,position);
  return E.patch(s,{active});
}
for(const zone of ["hand","m1"]){
  const s=scenario(zone);
  const original=JSON.stringify(s);
  const legal=R.actions(s,db);
  assert.equal(legal.actions.length,1,JSON.stringify(legal.rejected));
  const next=legal.actions[0].state;
  assert.equal(JSON.stringify(s),original,"Analysis must not mutate captured duel");
  assert.equal(next.tokens.find(t=>t.key===own.cards[0].key).zone,"graveyard",
    "Tribute is paid from the hand or face-up monster field");
  assert.equal(Restrictions.effective(next).banishBlocked,true);
  assert.equal(next._ruleFlagTurns.banishBlocked,next.turn);
  assert.equal(Restrictions.effective({...next,turn:next.turn+1}).banishBlocked,undefined,
    "Lancea's banish restriction expires on the following turn");
  const negated=R.actions(s,db,{negateEffectRuleIds:[rule.id]});
  assert.equal(negated.actions.length,1);
  assert.equal(negated.actions[0].state.tokens[0].zone,"graveyard",
    "Effect negation does not refund Lancea's tribute");
  assert.notEqual(Restrictions.effective(negated.actions[0].state).banishBlocked,true,
    "Negating effect stops the banish restriction");
  assert.equal(R.actions(s,db,{graveyardBlocked:true}).actions.length,0,
    "A prevented tribute destination is not an executable action");
}
assert.equal(R.actions(scenario("hand","self"),db).actions.length,0,
  "Quick Effect is available only on the opponent's turn");
assert.equal(R.actions(scenario("m1","opponent","set"),db).actions.length,0,
  "A face-down monster is not a face-up tribute source");
console.log("Memento Lancea: opponent-turn, cost, negation, zones and turn expiry passed.");
