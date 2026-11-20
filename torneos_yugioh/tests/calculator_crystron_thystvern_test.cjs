'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Engine = require('../assets/modules/play-calculator/engine.js');
const Rules = require('../assets/modules/play-calculator/rules.js');
const Study = require('../assets/modules/play-calculator/study.js');
const Planner = require('../assets/modules/play-calculator/planner.js');
const facts = require('../assets/modules/play-calculator/card-facts.json');

const study = JSON.parse(fs.readFileSync(path.join(__dirname,
  '../assets/editorial/20261117-folder-delivery-v40/677.json'), 'utf8'));
const effectId = '677:thystvern:graveyard-search';
const targets = [12606, 12608, 12756, 20758, 20759];
const variants = study.calculator.rules.filter(rule => rule.id === effectId);
assert.equal(variants.length, targets.length, 'All real Main Deck targets must be represented');
assert.deepEqual(variants.map(rule => rule.resolve[0].cid).sort((a,b) => a-b), targets);
assert(variants.every(rule => rule.actor.cid === 12609 &&
  rule.actor.zones.length === 1 && rule.actor.zones[0] === 'graveyard' &&
  rule.oncePerName && rule.oncePerTurn && rule.activation &&
  rule.costs.some(op => op.op === 'move' && op.ref === 'actor' && op.to === 'banished')));
assert(variants.every(rule => rule.resolve.length === 1 &&
  rule.resolve[0].from === 'deck' && rule.resolve[0].to === 'hand'),
  'The official graveyard effect adds only an available Crystron monster to hand');
const rows = study.cards.flatMap(card => (card.slots || []).map(slot => ({
  deck_id:677, id:slot.card_id, card_name:card.name,
  section:slot.section, quantity:slot.quantity,
})));
const native = Engine.catalog({id:677, name:'Crystron/Horus', format_codes:['tcg_advanced']}, rows);
const db = Study.bind(facts, [{catalog:native,study}]);
assert.equal(db.ruleImports[0].status, 'accepted', JSON.stringify(db.ruleImports));
assert.equal(db.rules.filter(rule => rule.id === effectId).length, targets.length,
  'Imported source and fallback must not create duplicate alternatives');
const key = cid => {
  const found = native.cards.find(card => db.bindings[card.key] === cid);
  assert(found, 'Unbound real deck card '+cid);
  return found.key;
};
const add = (state,cid,zone) => Engine.place(state, {key:key(cid),owner:'self',zone});
const actions = (state,options={}) => Rules.actions(state,db,options)
  .actions.filter(action => action.event?.ruleId === effectId);
let initial = add(Engine.create(native),12609,'graveyard');
const original = JSON.stringify(initial);
const candidates = actions(initial);
assert.equal(candidates.length, targets.length,
  'Every available legal Crystron target should be recommended, except Thystvern');
assert.equal(JSON.stringify(initial), original, 'Calculator must not mutate the physical board');
for (const candidate of candidates) {
  const searchedCid = candidate.state.tokens
    .filter(token => token.zone === 'hand' && targets.includes(db.bindings[token.key]))
    .map(token => db.bindings[token.key]);
  assert.equal(searchedCid.length,1,'Exactly one target is searched');
  assert(candidate.state.tokens.some(token => token.zone === 'banished' &&
    db.bindings[token.key] === 12609), 'Thystvern must be banished as activation cost');
  assert.equal(candidate.state._ruleUseTurns['rule:cid:12609'],candidate.state.turn,
    'All target variants share one usage token per name/turn');
  const simulatedReturned = Engine.clone(candidate.state);
  simulatedReturned.tokens.find(token => db.bindings[token.key] === 12609).zone='graveyard';
  assert.equal(actions(simulatedReturned).length,0,
    'Re-entering GY in the same turn must not bypass once-per-name');
}
for (const options of [{banishBlocked:true}, {noDeckToHand:true}, {graveyardBlocked:true}])
  assert.equal(actions(initial,options).length,0,
    'Locked resources or graveyard must forbid this search: '+JSON.stringify(options));
let depleted = add(initial,12606,'hand');
assert.equal(actions(depleted).length,targets.length-1,
  'A single Citree outside the Deck cannot be searched from an empty supply');
assert(!actions(depleted).some(action => action.state.tokens.some(token =>
  token.zone === 'hand' && token.uid !== depleted.tokens.at(-1)?.uid &&
  db.bindings[token.key] === 12606)), 'Do not conjure another Citree');
const moved = Engine.clone(initial);
moved.tokens.find(token => db.bindings[token.key] === 12609).zone='hand';
assert.equal(actions(moved).length,0,'Graveyard trigger is unavailable from hand');
const plans = Planner.solve(initial,db,{depth:1,budget:300,width:70});
assert(plans.plans.some(plan => plan.steps.some(step => step.label.includes('Thystvern'))),
  'Analyze must list the exact verified graveyard search in available actions');
console.log(JSON.stringify({ok:true,deck_id:677,verified_effect:effectId,
  variants:targets.length,sharedUse:true,inventoryAndLocks:true,planner:true}));
