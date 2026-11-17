const assert=require('node:assert/strict');
const scopes=require('../assets/modules/dashboard/ranking-scopes.js');
const data={competitions:[{id:7,name:'Liga Elite',type:'league',status:'active',division_ids:[11,12]},{id:8,name:'Copa',type:'cup',status:'active'},{id:9,name:'Cerrado',status:'closed'}],divisions:{E1:{id:11,name:'Elite — Primera División',league_competition_id:7,decks:[{id:1}]},E2:{id:12,name:'Elite — Segunda División',league_competition_id:7,decks:[{id:2}]}},allDecks:[]};
const opts=scopes.options(data);
assert.deepEqual(opts.map(o=>o.label),['Liga Elite — Primera División','Liga Elite — Segunda División','Copa']);
const matches=[{id:1,competition_id:7,division:'E1'},{id:2,competition_id:7,division:'E2'},{id:3,competition_id:8,division:'E1'},{id:4,competition_id:7,division:'E1',division_id:12}];
assert.deepEqual(scopes.matches(opts[0],matches).map(m=>m.id),[1]);
assert.deepEqual(scopes.matches(opts[1],matches).map(m=>m.id),[2,4]);
assert.deepEqual(scopes.matches(opts[2],matches).map(m=>m.id),[3]);
// A team may have decks in both divisions. Filter deck IDs before team totals.
const ranking=[{id:1,team:'Compartido',pts:3},{id:2,team:'Compartido',pts:9},{id:99,pts:40}];
assert.equal(scopes.ranking(opts[0],ranking).reduce((n,r)=>n+r.pts,0),3);
assert.equal(scopes.ranking(opts[1],ranking).reduce((n,r)=>n+r.pts,0),9);
assert.equal(scopes.ranking(opts[2],ranking).length,3);
assert.equal(scopes.ranking({...opts[0],deckIds:[]},ranking).length,0);
assert.deepEqual(scopes.options({}),[]);
console.log('Dashboard event/division scopes: passed');
