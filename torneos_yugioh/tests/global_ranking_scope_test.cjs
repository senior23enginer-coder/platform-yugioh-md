const assert = require('node:assert/strict');
const {build} = require('../assets/modules/statistics/global-ranking.js');
const decks = Array.from({length:50}, (_,i)=>({id:i+1,team_id:i+1,format_codes:[i<45?'tcg_advanced':'ocg']}));
const global = Array.from({length:12},(_,i)=>({deck_id:i+1,team_id:i+1,format_id:'tcg_advanced',total:1200-i,rating:1200-i,J:2,state:'CLASIFICADO'}));
const data={allDecks:[...decks,{id:51,format_codes:['rush_duel']},{id:52,format_codes:['goat']}],statisticsV8:{policy:{minimum_matches:2},global}};
let pool=build(data);
assert.equal(pool.total,50);assert.equal(pool.classified,12);
assert.equal(pool.rows.find(r=>r.deck_id===1).position,1);
assert.equal(pool.rows.find(r=>r.deck_id===50).position,null);
assert.equal(pool.rows.find(r=>r.deck_id===50).total,null);
assert(!pool.rows.some(r=>r.deck_id===51||r.deck_id===52));
// No inventory or played matches are needed to include a catalog deck.
data.allDecks.push(decks[0]);assert.equal(build(data).total,50);
global.push({deck_id:46,team_id:46,format_id:'ocg',total:1300,rating:1300,J:2,state:'CLASIFICADO'});
pool=build(data);assert.equal(pool.rows.find(r=>r.deck_id===46).position,1);assert.equal(pool.rows.find(r=>r.deck_id===1).position,2);
global[0].J=1;assert.equal(build(data).rows.find(r=>r.deck_id===1).position,null);
global[0].J=2;global[0].state='INACTIVO';assert.equal(build(data).rows.find(r=>r.deck_id===1).position,null);
// One deck registered in two formats is counted once, using its declared first format.
decks[1].format_codes.push('ocg');global.push({deck_id:2,team_id:2,format_id:'ocg',total:9999,J:2,state:'CLASIFICADO'});
pool=build(data);assert.equal(pool.total,50);assert.equal(pool.rows.find(r=>r.deck_id===2).total,1199);
console.log('OK: full TCG/OCG roster, 50 decks vs 12 qualified, OCG positions, no inventory dependency, two-match minimum, no duplicates, no Rush/GOAT.');
