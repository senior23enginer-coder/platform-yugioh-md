const assert=require('node:assert/strict'),M=require('../assets/modules/deck-attributes/model.js');
assert.equal(Object.keys(M.catalog).length,6);
assert.deepEqual(M.primary(['dark','water','fire'],'fire'),['fire','dark','water']);
assert.deepEqual(M.remove(['dark','water'],'dark'),['water']);
assert.throws(()=>M.add(['dark','water','fire'],'wind'));assert.throws(()=>M.add(['dark'],'dark'));assert.throws(()=>M.validate(['divine']));assert.throws(()=>M.primary(['dark'],'fire'));assert.throws(()=>M.validate([]));
const decks=[{id:1,name:'Elfnote',team:'Equipo Uno',format_codes:['tcg_advanced']},{id:2,name:'Hero',team:'Equipo Dos',format_codes:['rush_duel']},{id:3,name:'Sin cartas',format_codes:['tcg_advanced']}],records=[{deck_id:1,attributes:['dark','water'],revision:1},{deck_id:2,attributes:['fire'],revision:1}];
const rows=M.rows(decks,records);assert.equal(rows.length,3);assert.equal(M.filter(rows,{primary:'water'}).length,0);assert.equal(M.filter(rows,{any:'water'}).length,1);assert.equal(M.filter(rows,{search:'elf oscuridad uno'}).length,1);assert.equal(M.filter(rows,{format:'rush_duel'}).length,1);assert.equal(M.filter(rows,{state:'empty'})[0].id,3);assert.equal(M.counts(rows).dark,1);assert.equal(M.counts(rows).empty,1);
console.log('PASS: three-attribute limit, primary promotion, filters, counts and decks without cards.');
assert(M.isTcg(decks[0]));assert(!M.isTcg(decks[1]));assert(!M.isTcg({format_codes:['goat']}));assert(M.isTcg({format_codes:['tcg_traditional']}));
const worlds=M.worlds(M.rows(decks.filter(M.isTcg),records));assert.equal(worlds.length,6);assert.equal(worlds.find(w=>w.code==='water').members.length,1);assert.equal(worlds.find(w=>w.code==='water').primary,0);assert.equal(worlds.find(w=>w.code==='fire').members.length,0);
console.log('PASS: worlds include secondary members, count primary separately and restrict TCG.');

assert.throws(()=>M.remove(['dark'],'dark'));console.log('PASS: the last world cannot be removed.');
