const assert=require('node:assert/strict'),path=require('node:path');
const base=process.env.CALCULATOR_RELEASE_DIR||path.resolve(__dirname,'..');
const E=require(path.join(base,'assets/modules/play-calculator/engine.js')),H=require(path.join(base,'assets/modules/play-calculator/hand-practice.js'));
const fixture=require('./fixtures/calculator_inventory.json');
const catalog=id=>E.catalog(fixture.allDecks.find(d=>d.id===id),fixture.cards);
const self=catalog(665),s=E.create(self),before=JSON.stringify(s);
assert(s.practice);assert.equal(s.opponent.id,0);assert.deepEqual(s.opponent.cards,[]);assert.equal(E.validate(s),s);
const hand=H.opening(s,()=>0);
assert.equal(hand.tokens.length,5);assert(hand.tokens.every(t=>t.owner==='self'&&t.zone==='hand'&&E.definition(hand,t.key,t.owner).section==='main'));
assert.equal(JSON.stringify(s),before);assert.equal(H.remaining(hand).length,self.cards.filter(c=>c.section==='main').reduce((n,c)=>n+c.quantity,0)-5);
for(const c of self.cards)assert(hand.tokens.filter(t=>t.key===c.key).length<=c.quantity);
let moved=E.place(hand,{uid:hand.tokens[0].uid,zone:'graveyard',controller:'self'});
assert.equal(H.remaining(moved).length,H.remaining(hand).length,'Field/GY copies cannot be drawn again');
moved=E.place(moved,{uid:hand.tokens[0].uid,zone:'deck',controller:'self'});assert.equal(H.remaining(moved).length,H.remaining(hand).length+1);
const full=H.draw(s,H.remaining(s).length,()=>0.999999);assert.equal(H.remaining(full).length,0);assert.throws(()=>H.draw(full));assert.throws(()=>H.draw(s,1,()=>1));assert.throws(()=>H.draw(s,1,()=>NaN));
assert.throws(()=>E.place(s,{key:'unknown',owner:'opponent',controller:'opponent',zone:'s1'}));
assert.throws(()=>E.place(hand,{uid:hand.tokens[0].uid,controller:'opponent',zone:'m1'}));
const duel=E.create(self,catalog(656)),opening=H.opening(duel,()=>0);assert(!opening.practice);assert.equal(opening.opponent.id,656);assert.equal(opening.tokens.length,5);
for(const id of [705,706]){const rush=H.opening(E.create(catalog(id)),()=>0);assert.equal(rush.tokens.length,4);assert.equal(rush.format,'rush_duel');assert.equal(rush.phase,'draw');}
const goat=H.opening(E.create({...self,formats:['goat']}),()=>0);assert.equal(goat.tokens.length,5);assert.equal(goat.phase,'draw');
const tiny=E.catalog({id:10,name:'Weighted',format_codes:['tcg_advanced']},[{id:1,deck_id:10,card_name:'A',quantity:3,section:'main'},{id:2,deck_id:10,card_name:'B',quantity:1,section:'main'},{id:3,deck_id:10,card_name:'Extra',quantity:1,section:'extra'}]),weighted=E.create(tiny),counts={};
for(let i=0;i<4;i++){const drawn=H.draw(weighted,1,()=>(i+.5)/4).tokens[0];counts[drawn.key]=(counts[drawn.key]||0)+1;}
assert.deepEqual(counts,{'10:1':3,'10:2':1});assert.throws(()=>H.opening(weighted));
console.log(JSON.stringify({ok:true,freeMode:true,physicalCopyWeights:true,noReplacement:true,mainOnly:true,immutable:true,rushOpening:4,tcgOpening:5,emptyDeckRejected:true,opponentIsolation:true}));
