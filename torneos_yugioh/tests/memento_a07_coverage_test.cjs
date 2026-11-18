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
assert(coverage.routes.every(r => r.status === 'NO_REPLAY_CERTIFIED'));
assert.equal(coverage.partial_engine_cases.length, 2);
assert(coverage.partial_engine_cases.every(c => c.status === 'ENGINE_TESTED_RESTRICTED_CASE' && c.scope));
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
assert.throws(() => rules.validate(skeleton), /Operacion no implementada: fusion/);
console.log('A07 Memento: 34 controles documentales; 0 probados en motor; 12 rutas sin replay; fusión no soportada (rechazo seguro).');
