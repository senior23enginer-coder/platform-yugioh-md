const assert = require('node:assert/strict');
const E = require('../assets/modules/play-calculator/engine.js');
const R = require('../assets/modules/play-calculator/rules.js');

const deck = {id:671,name:'Memento',format_codes:['tcg_advanced']};
const rows = [
  {deck_id:671,id:1,section:'main',card_name:'Mementotlán Tatsun',quantity:1},
  {deck_id:671,id:2,section:'main',card_name:'Mementotlán Angbruja',quantity:1},
  {deck_id:671,id:3,section:'main',card_name:'Mementotlán Desvantasma',quantity:1},
];
const catalog = E.catalog(deck,rows);
const db = {bindings:Object.fromEntries(catalog.cards.map((c,i)=>[c.key,[19362,19364,19472][i]])),facts:{},rules:[]};
let scenario = E.create(catalog);
scenario = E.place(scenario,{key:catalog.cards[0].key,owner:'self',zone:'m1'});
scenario = E.place(scenario,{key:catalog.cards[1].key,owner:'self',zone:'m2'});

const rule = {
  version:1,id:'671:A07:R04:restricted-test',deckId:671,format:'tcg_advanced',
  scope:'single-effect',label:'Tatsun: destrucción y envío fijado por caso',
  source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19362&ope=2',
  assumptions:['Caso cerrado con Angbruja propia y Desvantasma aún en el Deck; no representa selección general por niveles'],
  actor:{side:'self',zones:['monsters'],cid:19362,notNegated:true},
  guards:[{kind:'state',field:'active',values:['self']}],
  select:[{ref:'victim',selector:{side:'self',zones:['monsters'],cid:19364}}],
  costs:[],
  resolve:[{op:'destroy',ref:'victim',reason:'effect'}, {op:'take',side:'self',cid:19472,from:'deck',to:'graveyard',position:'attack'}],
  tags:['monsterEffect','sendFromDeck'],activation:true,oncePerTurn:true,
};
assert.equal(R.validate(rule),rule);
db.rules=[rule];
const result=R.actions(scenario,db);
assert.equal(result.actions.length,1,JSON.stringify(result.rejected));
const next=result.actions[0].state;
assert.equal(next.tokens.find(t=>db.bindings[t.key]===19364).zone,'graveyard');
assert.equal(next.tokens.find(t=>db.bindings[t.key]===19472).zone,'graveyard');
assert.equal(next._ruleEvents[0].kind,'destroy');
assert.equal(next._ruleEvents[0].stage,'resolve');
assert.equal(next._ruleEvents[0].cid,19364);
assert.equal(R.actions(next,db).actions.length,0,'No repetir el mismo efecto sin reiniciar turno');

const negated=R.actions(scenario,db,{negateEffectRuleIds:[rule.id]});
assert.equal(negated.actions.length,1,JSON.stringify(negated.rejected));
assert.equal(negated.actions[0].event.effectNegated,true);
assert.equal(negated.actions[0].state.tokens.length,scenario.tokens.length);
assert.equal(negated.actions[0].state._ruleEvents?.length||0,0);
assert(negated.actions[0].state.tokens.every(t=>t.zone!=='graveyard'));

const withCost={...rule,id:'671:A07:R32:cost-test',costs:[{op:'move',ref:'victim',to:'graveyard',position:'attack'}],resolve:[{op:'take',side:'self',cid:19472,from:'deck',to:'hand',position:'attack'}]};
db.rules=[withCost];
const costNegated=R.actions(scenario,db,{negateEffectRuleIds:[withCost.id]});
assert.equal(costNegated.actions.length,1,JSON.stringify(costNegated.rejected));
const costState=costNegated.actions[0].state;
assert.equal(costState.tokens.find(t=>db.bindings[t.key]===19364).zone,'graveyard','Coste permanece pagado');
assert(!costState.tokens.some(t=>db.bindings[t.key]===19472),'Resolución negada no toma carta del Deck');
assert.equal(costNegated.actions[0].paid.tokens.find(t=>db.bindings[t.key]===19364).zone,'graveyard');
console.log('Memento A07: destrucción por efecto, envío fijado y negación con costes conservados: OK');
