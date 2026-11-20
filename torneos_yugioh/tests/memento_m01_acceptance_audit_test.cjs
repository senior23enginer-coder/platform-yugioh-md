'use strict';
// M01 audit: counts identities separately from effect variants. This is NOT a replay.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..', 'assets');
const deck = JSON.parse(fs.readFileSync(path.join(root,
  'editorial/20261117-folder-delivery-v40/671.json'), 'utf8'));
const coverage = JSON.parse(fs.readFileSync(path.join(root,
  'editorial/memento-a07-functional-coverage.json'), 'utf8'));
assert.equal(deck.deck_id, 671);
assert.equal(coverage.deck_id, 671);
const cards = deck.cards.filter(card => Number.isInteger(card.cid) && card.cid > 0);
const identities = new Set(cards.map(card => card.cid));
assert.equal(identities.size, cards.length, 'Duplicate CID identity in Memento');
const rules = deck.calculator?.rules;
assert(Array.isArray(rules), 'Missing portable calculator rules');
const actors = new Set();
const invalidActors = [];
for (const rule of rules) {
  if (Number.isInteger(rule.actor?.cid) && rule.actor.cid > 0) {
    if (!identities.has(rule.actor.cid)) invalidActors.push({id:rule.id, cid:rule.actor.cid});
    else actors.add(rule.actor.cid);
  }
  // No-CID special procedure is not evidence of an individual card actor.
}
assert.deepEqual(invalidActors, [], 'Rules must not attribute actors to another deck');
const missing = cards.filter(card => !actors.has(card.cid))
  .map(card => ({cid:card.cid,name:card.name}));
assert(Array.isArray(deck.routes) && Array.isArray(coverage.routes));
assert.equal(deck.routes.length, coverage.routes.length,
  'Canonical route count and A07 audit must stay synchronized');
const uncertified = coverage.routes.filter(route => route.status !== 'REPLAY_CERTIFIED');
const report = {
  schema:'station-x-m01-acceptance-audit/1',
  deck_id:671,
  card_identities:cards.length,
  actor_covered_identities:actors.size,
  missing_actor_identities:missing,
  portable_variants:rules.length,
  distinct_effect_ids:new Set(rules.map(rule => rule.id)).size,
  documented_routes:deck.routes.length,
  certified_replays:coverage.routes.length-uncertified.length,
  uncertified_route_ids:uncertified.map(route => route.id),
  complete:missing.length===0 && uncertified.length===0
};
console.log(JSON.stringify(report));
if (process.argv.includes('--require-complete'))
  assert(report.complete, 'M01 cannot close: missing actor rules or uncertified route replays');
