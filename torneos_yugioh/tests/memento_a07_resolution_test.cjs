const assert = require('node:assert/strict');
const E = require('../assets/modules/play-calculator/engine.js');
const R = require('../assets/modules/play-calculator/rules.js');

const deck = {id:671,name:'Memento',format_codes:['tcg_advanced']};
const rows = [
  {deck_id:671,id:1,section:'main',card_name:'Mementotlán Tatsun',quantity:1},
  {deck_id:671,id:2,section:'main',card_name:'Mementotlán Angbruja',quantity:1},
  {deck_id:671,id:3,section:'main',card_name:'Mementotlán Desvantasma',quantity:1},
  {deck_id:671,id:4,section:'main',card_name:'Mementotlán Dormiveja',quantity:1},
  {deck_id:671,id:5,section:'main',card_name:'Fusión Mementotlán',quantity:1},
  {deck_id:671,id:6,section:'extra',card_name:'Rey Creación',quantity:1},
  {deck_id:671,id:7,section:'extra',card_name:'Duque de la Defunción',quantity:1},
  {deck_id:671,id:8,section:'main',card_name:'Mementotlán Goblin',quantity:1},
  {deck_id:671,id:9,section:'main',card_name:'Tecuhtlica - Creación Combinada',quantity:1},
  {deck_id:671,id:10,section:'main',card_name:'Demoniherrero Grabador',quantity:1},
  {deck_id:671,id:11,section:'extra',card_name:'D/D/D César, Alto Rey de las Olas',quantity:1},
];
const catalog = E.catalog(deck,rows);
const db = {bindings:Object.fromEntries(catalog.cards.map((c,i)=>[c.key,[19362,19364,19472,20199,19904,20216,20389,19366,19360,20196,13081][i]])),facts:{},rules:[]};
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

let fusionState=E.create(catalog);
fusionState=E.place(fusionState,{key:catalog.cards[1].key,owner:'self',zone:'m1'});
fusionState=E.place(fusionState,{key:catalog.cards[2].key,owner:'self',zone:'m2'});
fusionState=E.place(fusionState,{key:catalog.cards[3].key,owner:'self',zone:'hand'});
fusionState=E.place(fusionState,{key:catalog.cards[4].key,owner:'self',zone:'hand'});
const fusionRule={
 version:1,id:'671:A07:R06:exact-materials-test',deckId:671,format:'tcg_advanced',
 scope:'single-effect',label:'Fusión C01: tres materiales exactos del caso',
 source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19904&ope=2',
 assumptions:['Solo C01 con tres materiales fijos; no valida todas las recetas ni la cadena de activación de la Mágica'],
 actor:{side:'self',zones:['hand'],cid:19904},
 guards:[{kind:'state',field:'active',values:['self']},{kind:'state',field:'phase',values:['main1','main2']}],
 select:[
  {ref:'a',selector:{side:'self',zones:['monsters'],cid:19364}},
  {ref:'b',selector:{side:'self',zones:['monsters'],cid:19472}},
  {ref:'c',selector:{side:'self',zones:['hand'],cid:20199}},
 ],
 costs:[],resolve:[{op:'fusion',cid:20216,materials:['a','b','c'],materialCids:[19364,19472,20199],to:'emz1',recipe:'exact-cids',allowGraveyard:false}],
 tags:[],activation:true,oncePerTurn:true,
};
db.rules=[fusionRule];
const fusionResult=R.actions(fusionState,db);
assert.equal(fusionResult.actions.length,1,JSON.stringify(fusionResult.rejected));
const fused=fusionResult.actions[0].state;
assert.equal(fused.tokens.find(t=>db.bindings[t.key]===20216).zone,'emz1');
assert.deepEqual([19364,19472,20199].map(cid=>fused.tokens.find(t=>db.bindings[t.key]===cid).zone),['graveyard','graveyard','graveyard']);
assert.equal(fused._ruleEvents.at(-1).kind,'fusion_summon');
assert.equal(R.actions(fusionState,db,{noExtra:true}).actions.length,0);
const noMaterial=E.clone(fusionState);
noMaterial.tokens=noMaterial.tokens.filter(t=>db.bindings[t.key]!==20199);
assert.equal(R.actions(noMaterial,db).actions.length,0);
const falseRecipe=structuredClone(fusionRule);
falseRecipe.resolve[0].materialCids=[19364,19472,19362];
db.rules=[falseRecipe];
assert.equal(R.actions(fusionState,db).actions.length,0,'No se acepta una receta distinta');
const dukeRule={
 version:1,id:'671:A07:R21:additional-normal-test',deckId:671,format:'tcg_advanced',
 scope:'single-effect',label:'Duque habilita una Normal adicional en este caso',
 source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=20389&ope=2',
 assumptions:['Duque ya está legalmente Invocado; efecto en Main Phase y Normal original consumida'],
 actor:{side:'self',zones:['monsters'],cid:20389,notNegated:true},
 guards:[{kind:'state',field:'active',values:['self']},{kind:'state',field:'phase',values:['main1','main2']},{kind:'state',field:'normalAvailable',values:[false]}],
 select:[],costs:[],resolve:[{op:'normal',available:true}],tags:['monsterEffect'],activation:true,oncePerTurn:true,
};
let usedNormal=E.create(catalog);
usedNormal=E.place(usedNormal,{key:catalog.cards[6].key,owner:'self',zone:'m1'});
usedNormal=E.patch(usedNormal,{normalAvailable:false});
db.rules=[dukeRule];
const restored=R.actions(usedNormal,db);
assert.equal(restored.actions.length,1,JSON.stringify(restored.rejected));
assert.equal(restored.actions[0].state._normal,false);
assert.equal(restored.actions[0].state.normalAvailable,true,'La Normal vuelve a estar disponible');
assert.equal(restored.actions[0].paid.normalAvailable,false);
assert.equal(R.actions(restored.actions[0].state,db).actions.length,0,'No usar otra vez Duque este turno');
const noNormalUsed=E.create(catalog);
const duquePresent=E.place(noNormalUsed,{key:catalog.cards[6].key,owner:'self',zone:'m1'});
assert.equal(R.actions(duquePresent,db).actions.length,0,'No crear una Normal extra sin consumo previo');
let bossState=E.create(catalog);
for(const i of [0,1,2,3,7,8])bossState=E.place(bossState,{key:catalog.cards[i].key,owner:'self',zone:'graveyard'});
const bossRule={
 version:1,id:'671:A07:R08:five-names-test',deckId:671,format:'tcg_advanced',
 scope:'single-effect',label:'Creación Combinada: cinco nombres distintos en GY',
 source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19360&ope=2',
 assumptions:['Cinco nombres Memento determinados en GY, zona m1 libre; no representa cualquier selección posible de la lista'],
 actor:{side:'self',zones:['graveyard'],cid:19360},
 guards:[{kind:'state',field:'active',values:['self']}],
 select:[0,1,2,3,7].map((i,k)=>({ref:'piece'+k,selector:{side:'self',zones:['graveyard'],cid:[19362,19364,19472,20199,19366][k]}})),
 costs:[],resolve:[
   {op:'shuffle',refs:['piece0','piece1','piece2','piece3','piece4'],distinctCids:true,allowedCids:[19362,19364,19472,20199,19366]},
   {op:'move',ref:'actor',to:'m1',position:'attack'},
 ],tags:[],activation:false,oncePerTurn:false,
};
db.rules=[bossRule];
const bossResult=R.actions(bossState,db);
assert.equal(bossResult.actions.length,1,JSON.stringify(bossResult.rejected));
const bossAfter=bossResult.actions[0].state;
assert.equal(bossAfter.tokens.length,1,'Cinco nombres barajados dejan únicamente al jefe en campo');
assert.equal(bossAfter.tokens[0].zone,'m1');
assert.equal(bossAfter._ruleEvents[0].kind,'shuffle_distinct');
const withoutGoblin=E.clone(bossState);
withoutGoblin.tokens=withoutGoblin.tokens.filter(t=>db.bindings[t.key]!==19366);
assert.equal(R.actions(withoutGoblin,db).actions.length,0,'Cuatro nombres no bastan');
const foreignName=structuredClone(bossRule);
foreignName.resolve[0].allowedCids=[19362,19364,19472,20199,20389];
db.rules=[foreignName];
assert.equal(R.actions(bossState,db).actions.length,0,'Un material no admitido bloquea el barajado');
let xyzState=E.create(catalog);
xyzState=E.place(xyzState,{key:catalog.cards[6].key,owner:'self',zone:'m1'});
xyzState=E.place(xyzState,{key:catalog.cards[9].key,owner:'self',zone:'m2'});
const xyzRule={
 version:1,id:'671:A07:R27:two-level-six-test',deckId:671,format:'tcg_advanced',
 scope:'single-effect',label:'César con Grabador y Duque ya en el campo',
 source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=13081&ope=2',
 assumptions:['Los dos Demonios Nivel 6 ya están boca arriba en el campo y no se reutilizan de una ruta que los consumió'],
 actor:{side:'self',zones:['monsters'],cid:20196},
 guards:[{kind:'state',field:'active',values:['self']}],
 select:[{ref:'duke',selector:{side:'self',zones:['monsters'],cid:20389}}],
 costs:[],resolve:[{op:'xyz',cid:13081,materials:['actor','duke'],materialCids:[20196,20389],to:'emz1',recipe:'exact-cids'}],
 tags:[],activation:false,oncePerTurn:false,
};
db.rules=[xyzRule];
const xyzResult=R.actions(xyzState,db);
assert.equal(xyzResult.actions.length,1,JSON.stringify(xyzResult.rejected));
const xyzAfter=xyzResult.actions[0].state;
const cesar=xyzAfter.tokens.find(t=>db.bindings[t.key]===13081);
assert.equal(cesar.zone,'emz1');
assert.equal(xyzAfter.tokens.filter(t=>t.zone==='material:'+cesar.uid).length,2);
assert.equal(xyzAfter.tokens.filter(t=>E.isMonster(t.zone)).length,1,'Los materiales ya no son monstruos en campo');
const withMissingDuke=E.clone(xyzState);
withMissingDuke.tokens=withMissingDuke.tokens.filter(t=>db.bindings[t.key]!==20389);
assert.equal(R.actions(withMissingDuke,db).actions.length,0);
const badXyz=structuredClone(xyzRule);
badXyz.resolve[0].materialCids=[20196,19364];
db.rules=[badXyz];
assert.equal(R.actions(xyzState,db).actions.length,0,'No acreditar César con materiales equivocados');
console.log('Memento A07: destrucción por efecto, envío fijado y negación con costes conservados: OK');
console.log('Memento A07: Fusión con materiales exactos, bloqueo de Extra y falta de material: OK');
console.log('Memento A07: Normal adicional de Duque con estado usado y límite por turno: OK');
console.log('Memento A07: cinco nombres distintos para Creación Combinada: OK');
console.log('Memento A07: César Xyz con materiales acoplados y requisitos exactos: OK');
