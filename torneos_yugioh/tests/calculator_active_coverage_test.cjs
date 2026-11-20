'use strict';
const assert=require('node:assert/strict');
const E=require('../assets/modules/play-calculator/engine.js');
const P=require('../assets/modules/play-calculator/planner.js');
const catalog=E.catalog({id:9701,name:'Prueba estado real',format_codes:['tcg_advanced']},[
  {id:11,deck_id:9701,card_name:'Carta sin soporte en mano',section:'main',quantity:1},
  {id:12,deck_id:9701,card_name:'Carta sin soporte en Cementerio',section:'main',quantity:1},
  {id:13,deck_id:9701,card_name:'Carta del Deck no robada',section:'main',quantity:1},
]);
const db={bindings:{},facts:{},rules:[]};
let state=E.create(catalog);
assert.deepEqual(P.solve(state,db).unsupported,[],'Undrawn deck list cannot be called uncovered active effects');
state=E.place(state,{key:'9701:11',owner:'self',zone:'hand'});
state=E.place(state,{key:'9701:12',owner:'self',zone:'graveyard'});
const before=JSON.stringify(state);
const result=P.solve(state,db,{depth:1,budget:15});
assert.equal(JSON.stringify(state),before,'Analysis cannot mutate captured duel');
assert.deepEqual(result.unsupported.map(c=>c.name).sort(),
  ['Carta sin soporte en Cementerio','Carta sin soporte en mano'].sort());
assert(!result.unsupported.some(c=>c.name==='Carta del Deck no robada'));
console.log('Calculator uncovered effects scoped to current hand/field/GY/banished state.');
