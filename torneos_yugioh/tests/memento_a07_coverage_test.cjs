const assert = require('node:assert/strict');
const coverage = require('../assets/editorial/memento-a07-functional-coverage.json');
const rules = require('../assets/modules/play-calculator/rules.js');

assert.equal(coverage.schema, 'station-x-a07-coverage/1');
assert.equal(coverage.deck_id, 671);
assert.deepEqual(coverage.inventory, { main: 41, extra: 15, side: 14, reserve: 0 });
assert.equal(coverage.rules.length, 34);
assert.deepEqual(coverage.rules.map(r => r.id), Array.from({length:34}, (_,i) => 'R' + String(i+1).padStart(2,'0')));
assert.equal(new Set(coverage.rules.map(r => r.capability)).size, 34);
assert(coverage.rules.every(r => r.status === 'DOCUMENTED_NOT_ENGINE_TESTED' && r.engine_test_id === null));
assert.equal(coverage.routes.length, 12);
assert.equal(coverage.routes.filter(r=>r.status==='REPLAY_CERTIFIED').length,6,
  'C01 through C06 have exact-state no-response replays with correct CIDs');
assert.equal(coverage.routes.find(r=>r.id==='C02').test,
  'torneos_yugioh/tests/memento_c02_route_replay_test.cjs');
assert.equal(coverage.routes.find(r=>r.id==='C01').test,
  'torneos_yugioh/tests/memento_c01_route_replay_test.cjs');
assert.equal(coverage.routes.find(r=>r.id==='C03').test,
  'torneos_yugioh/tests/memento_c03_route_replay_test.cjs');
assert.equal(coverage.routes.find(r=>r.id==='C04').test,
  'torneos_yugioh/tests/memento_c04_route_replay_test.cjs');
assert.equal(coverage.routes.find(r=>r.id==='C05').test,
  'torneos_yugioh/tests/memento_c05_route_replay_test.cjs');
assert.equal(coverage.routes.find(r=>r.id==='C06').test,
  'torneos_yugioh/tests/memento_c06_route_replay_test.cjs');
assert(coverage.routes.filter(r=>!['C01','C02','C03','C04','C05','C06'].includes(r.id)).every(r=>r.status==='NO_REPLAY_CERTIFIED'),
  'Unreplayed routes must not be marked complete');
assert.equal(coverage.partial_route_cases.length,2);
assert.equal(coverage.partial_route_cases[0].id,'C01');
assert.equal(coverage.partial_route_cases[1].id,'C02');
assert.equal(coverage.partial_route_cases[0].status,'FIXED_STATE_NO_CHAIN_REPLAY');
assert.equal(coverage.partial_engine_cases.length, 13);
assert.equal(new Set(coverage.partial_engine_cases.map(c=>c.id.split(':')[2])).size, 12);
assert(coverage.partial_engine_cases.every(c => c.status === 'ENGINE_TESTED_RESTRICTED_CASE' && c.scope));
const already = new Set(coverage.partial_engine_cases.map(c => c.id.split(':')[2]));
const added = new Set(coverage.incremental_rule_cases_p1.map(c => c.id));
const blocked = new Set(coverage.blocked_rule_cases_p1.map(c => c.id));
assert.equal(added.size,22);
assert.equal(blocked.size,0);
assert(coverage.incremental_rule_cases_p1.every(c =>
  ['ENGINE_TESTED_RESTRICTED_CASE','ENGINE_TESTED_INVENTORY_GUARD'].includes(c.status) && c.scope));
assert.deepEqual(coverage.incremental_rule_cases_p1.filter(c=>c.status==='ENGINE_TESTED_INVENTORY_GUARD').map(c=>c.id),['R12']);
assert.deepEqual(coverage.r12_r24_focused_evidence.map(c=>c.id),['R12','R24']);
assert(coverage.r12_r24_focused_evidence.every(c=>c.test==='torneos_yugioh/tests/memento_a07_r12_r24_test.cjs' && c.scope));
assert(coverage.blocked_rule_cases_p1.every(c => c.status === 'OPERATOR_PENDING_FAIL_CLOSED' && c.operator));
assert.equal([...already,...added,...blocked].length,34,'Ninguna regla R01-R34 puede estar duplicada entre estados');
assert.equal(new Set([...already,...added,...blocked]).size,34,'Punto 1 debe clasificar exactamente R01-R34');
assert.deepEqual([...new Set([...already,...added,...blocked])].sort(),coverage.rules.map(r=>r.id));
assert(coverage.missing_capabilities.includes('fusion_material_constraints_and_resolution'));
const skeleton = {
 version:1, id:'671:R06:test', deckId:671, format:'tcg_advanced',
 scope:'single-effect', label:'Fusión documental; no ejecutar sin operador',
 source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2',
 assumptions:['Solo un test de rechazo; no representa un efecto ejecutable'],
 actor:{side:'self',zones:['hand'],cid:19904},
 guards:[{kind:'state',field:'active',values:['self']}],
 select:[], costs:[], resolve:[{op:'fusion',materials:[]}],
 tags:[], activation:true, oncePerTurn:true
};
assert.throws(() => rules.validate(skeleton), /Fusión requiere materiales exactos/);
console.log('A07 Memento: 34 controles documentales sin certificación integral; 12 rutas sin replay; Fusión incompleta rechazada.');
