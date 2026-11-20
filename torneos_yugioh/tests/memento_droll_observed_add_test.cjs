'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const E = require('../assets/modules/play-calculator/engine.js');
const R = require('../assets/modules/play-calculator/rules.js');
const S = require('../assets/modules/play-calculator/study.js');
const Restrictions = require('../assets/modules/play-calculator/restrictions.js');
const base = require('../assets/modules/play-calculator/card-facts.json');
const source = JSON.parse(fs.readFileSync(
  __dirname + '/../assets/editorial/20261117-folder-delivery-v40/671.json', 'utf8'));
const rows = source.cards.flatMap(c => c.slots.map(slot => ({
  id:slot.card_id, deck_id:671, card_name:c.name, quantity:slot.quantity, section:slot.section,
})));
const catalog = E.catalog({id:671, name:source.name, format_codes:[source.format]}, rows);
const db = S.bind(base,[{catalog,study:source}]);
assert.equal(db.ruleImports[0].status,'accepted');
const id = '671:droll:main-deck-lock';
assert.equal(db.rules.filter(rule=>rule.id===id).length,1,'No duplicate portable/fallback Droll');
const droll = catalog.cards.find(c => db.bindings[c.key]===9279 && c.section==='main');
assert(droll, 'Droll must be in the actual approved Main Deck');
let state=E.create(catalog,catalog);
state=E.patch(state,{turn:2,active:'opponent',phase:'main1'});
state=E.place(state,{key:droll.key,owner:'self',zone:'hand'});
const actions = s => R.actions(s,db).actions.filter(a=>a.event.ruleId===id);
assert.equal(actions(state).length,0,'Droll must not activate from an inferred search');
const observed=E.observeOpponentMainDeckAdd(state);
assert.equal(actions(observed).length,1,'Explicit current non-Draw observation unlocks Droll');
assert.equal(JSON.stringify(state._ruleEvents||[]),'[]','Observing must not mutate original');
const response=actions(observed)[0].state;
assert(response.tokens.some(t=>t.key===droll.key&&t.zone==='graveyard'),
  'Droll must be sent from hand to GY as activation cost');
assert.equal(Restrictions.effective(response).noDeckToHand,true,
  'Deck-to-hand search must be blocked for both players this turn');
assert.equal(Restrictions.effective({...response,turn:3}).noDeckToHand,undefined,
  'Search lock must expire at the end of the current turn');
assert.equal(actions(E.patch(observed,{notes:'Otro paso físico observado'})).length,0,
  'An intervening edit closes the response window');
assert.throws(()=>E.observeOpponentMainDeckAdd(E.patch(state,{phase:'draw'})),/Robo/,
  'The official trigger expressly excludes Draw Phase');
assert.throws(()=>E.observeOpponentMainDeckAdd(E.create(catalog)),/práctica libre/,
  'No opponent event can be fabricated in practice mode');
assert.equal(actions(E.patch(observed,{turn:3})).length,0,
  'An old opponent event cannot be reused on a future turn');
assert.throws(()=>Restrictions.apply(state,'noDeckToHand',true),/Duracion/,
  'Droll turn lock must never silently persist across turns');
console.log('PASS Memento Droll: observed trigger, sending cost, closure, turn expiry and portable parity');
