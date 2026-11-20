const assert = require("node:assert/strict");
const E = require("../assets/modules/play-calculator/engine.js");
const R = require("../assets/modules/play-calculator/rules.js");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");

// Isolated two-step line, not a certification of a complete A09 route.
const ids = [19366,19363,19472];
const names = ["Mementotlan Goblin","Mementotlan Dark Blade","Mementotlan Ghattic"];
const own = E.catalog({id:671,name:"Memento Goblin/Ghattic",format_codes:["tcg_advanced"]},
  ids.map((cid,i)=>({deck_id:671,id:i+1,section:"main",
    card_name:names[i],quantity:1})));
const bindings=Object.fromEntries(own.cards.map((card,i)=>[card.key,ids[i]]));
let s=E.create(own);
s=E.place(s,{key:own.cards[0].key,owner:"self",zone:"m1"});
const rules=Verified.forDeck(671);
const goblin=rules.filter(r=>r.id==="671:goblin:send-two" &&
  r.label.includes("destruirse y enviar Hoja Oscura y Desvantasma"));
const ghattic=rules.filter(r=>r.id==="671:ghattic:revive" &&
  r.trigger?.sourceCid===19366 && r.resolve?.[0]?.to==="m1");
assert.equal(goblin.length,1);
assert.equal(ghattic.length,1);
const sends=R.actions(s,{bindings,facts:{},rules:goblin});
assert.equal(sends.actions.length,1,JSON.stringify(sends.rejected));
const sent=sends.actions[0].state;
assert.equal(sent.tokens.find(t=>bindings[t.key]===19366).zone,"graveyard");
const event=sent._ruleEvents.find(e=>e.kind==="sent_to_graveyard"&&e.cid===19472);
assert(event,"Ghattic must have an attributable Goblin send event");
assert.equal(event.sourceCid,19366);
assert.equal(sent.tokens.find(t=>t.uid===event.uid).zone,"graveyard");
const responses=R.actions(sent,{bindings,facts:{},rules:ghattic});
assert.equal(responses.actions.length,1,JSON.stringify(responses.rejected));
const revived=responses.actions[0].state;
assert.equal(revived.tokens.find(t=>t.uid===event.uid).zone,"m1");
const withoutCause=E.clone(sent);
withoutCause._ruleEvents=withoutCause._ruleEvents.filter(e=>e!==undefined&&e.kind!=="sent_to_graveyard");
assert.equal(R.actions(withoutCause,{bindings,facts:{},rules:ghattic}).actions.length,0,
  "A card in GY must not revive without its causal send event");
const blocked=R.actions(sent,{bindings,facts:{},rules:ghattic},{specialBlocked:true});
assert.equal(blocked.actions.length,0,"Special Summon restrictions must prevent Ghattic revival");
console.log("Memento Goblin -> Ghattic: source, physical UID, revival and restrictions verified.");
