/* Memento 671: R12 uses its approved 41/15/14 inventory, while R24 tests
 * TY-PHON's alternative Xyz procedure from observed (not legally simulated)
 * opponent Extra Deck summons. No production deck or Side is modified. */
const assert=require('node:assert/strict');
const E=require('../assets/modules/play-calculator/engine.js');
const R=require('../assets/modules/play-calculator/rules.js');
const verified=require('../assets/modules/play-calculator/verified-rules.js');

const main=[
  [19364,'Mementotlan Angwitch',3,'monster'],[19362,'Mementotlan Tatsunootoshigo',3,'monster'],
  [19363,'Mementotlan Dark Blade',3,'monster'],[19366,'Mementotlan Goblin',2,'monster'],
  [19360,'Mementotlan Combined Creation',1,'monster'],[20491,'Mementotlan Akihiron',1,'monster'],
  [20199,'Mementotlan Sleepy',1,'monster'],[19365,'Mementotlan Mace',1,'monster'],
  [19472,'Mementotlan Ghattic',1,'monster'],[19361,'Mementotlan Horned Dragon',1,'monster'],
  [20196,'Fiendsmith Engraver',1,'monster'],[12950,'Ash Blossom',3,'monster'],
  [9279,'Droll & Lock Bird',3,'monster'],[20500,'Mulcharmy Fuwalos',1,'monster'],
  [19904,'Mementotlan Fusion',3,'spell'],[19368,'Mementotlan Bone Party',3,'spell'],
  [9139,'Heat Wave',3,'spell'],[20533,'Goblin Biker Grand Entrance',2,'spell'],
  [15299,'Forbidden Droplet',2,'spell'],[19367,'Mementomictlan',1,'spell'],
  [13619,'Called by the Grave',1,'spell'],[15296,'Triple Tactics Talent',1,'spell'],
];
const extra=[
  [20423,'Necroquip Princess',1,'fusion'],[12870,'Predaplant Dragostapelia',1,'fusion'],
  [16845,'Guardian Chimera',1,'fusion'],[20216,'Mementotlan Creation King',3,'fusion'],
  [19878,'Mementotlan Twin Dragon',1,'fusion'],[13081,'D/D/D Wave High King Caesar',1,'xyz'],
  [19184,'Super Starslayer TY-PHON - Sky Crisis',1,'xyz'],[20225,'Fiendsmith Requiem',1,'link'],
  [19188,'S:P Little Knight',1,'link'],[19932,'Moon of the Closed Heaven',1,'link'],
  [20226,'Fiendsmith Sequence',1,'link'],[20389,'Fiendsmith Duke',1,'fusion'],
  [9657,'Submersible Carrier Aero Shark',1,'xyz'],
];
const side=[
  [20500,'Mulcharmy Fuwalos',2,'monster'],[20764,'Mulcharmy Meowls',3,'monster'],
  [20206,'Mulcharmy Purulia',3,'monster'],[11243,'Artifact Lancea',3,'monster'],
  [21203,'Gordian Slicer',3,'spell'],
];
const quantity=list=>list.reduce((count,row)=>count+row[2],0);
assert.deepEqual([quantity(main),quantity(extra),quantity(side)],[41,15,14]);
assert.equal(main.length,22);assert.equal(extra.length,13);assert.equal(side.length,5);
const deck={id:671,name:'Memento · inventario aprobado',format_codes:['tcg_advanced']};
const rows=[...main.map(x=>['main',x]),...extra.map(x=>['extra',x]),...side.map(x=>['side',x])]
  .map(([section,[cid,name,qty]],i)=>({deck_id:671,id:i+1,section,
    card_name:name,quantity:qty,cid}));
const own=E.catalog(deck,rows);
assert.equal(own.cards.length,35,'Side no es recurso del duelo sin intercambio');
assert.equal(own.cards.filter(c=>c.section==='main').reduce((n,c)=>n+c.quantity,0),41);
assert.equal(own.cards.filter(c=>c.section==='extra').reduce((n,c)=>n+c.quantity,0),15);
const rival=E.catalog({id:672,name:'Rival de laboratorio',format_codes:['tcg_advanced']},[
  {deck_id:672,id:1,section:'main',card_name:'Rival efecto',quantity:1},
  {deck_id:672,id:2,section:'extra',card_name:'Fusion rival observada',quantity:1},
  {deck_id:672,id:3,section:'extra',card_name:'Link rival observado',quantity:1},
]);
const keys=new Map(own.cards.map(c=>[rows.find(row=>row.id===Number(c.key.split(':')[1])).cid,c.key]));
const cardRows=rows.filter(row=>row.section!=='side');
const db={bindings:Object.fromEntries(cardRows.map(row=>[keys.get(row.cid),row.cid])),facts:{},rules:[]};
for(const [cid,, ,kind] of [...main,...extra])db.facts[cid]={cardType:kind==='fusion'||kind==='link'||kind==='xyz'?'monster':kind,
  species:kind==='xyz'?'Xyz':kind};
db.facts[19188].atk=1600;
db.facts[19360].atk=5000;
db.facts[20216].atk=3000;
db.facts[9657].atk=1900;
const source=cid=>`https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=${cid}&ope=2`;
function rule(cid,id,actorZones,guards,resolve,costs=[],select=[],activation=true){
  return {version:1,id,deckId:671,format:'tcg_advanced',scope:'single-effect',label:id,
    source:source(cid),assumptions:['Inventario 41/15/14 exacto; rival observado, no resolución de materiales rivales'],
    actor:{side:'self',zones:actorZones,cid},guards,select,costs,resolve,
    tags:[],activation,oncePerTurn:false};
}
const state=(rivalIncluded=true)=>E.create(own,rivalIncluded?rival:undefined);
function placed(s,cid,zone,position='attack') {
  const n=E.place(s,{key:keys.get(cid),owner:'self',zone});
  return E.position(n,n.nextId-1,position);
}
const actions=(s,r,options={})=>R.actions(s,{...db,rules:[r]},options);
const none=(s,r,label,options={})=>assert.equal(actions(s,r,options).actions.length,0,label);
const one=(s,r,label,options={})=>{
  const result=actions(s,r,options);
  assert.equal(result.actions.length,1,`${label}: ${JSON.stringify(result.rejected)}`);
  return result.actions[0].state;
};

// R12: both unavailable searches are guarded by type/archetype and the actual
// remaining Main/Extra quantities. A synthetic main-only card search must not
// become a Fiendsmith S/T search merely because it has an available CID.
const fiendsmith=rule(20196,'671:A07:R12:actual-fiendsmith-st',['hand'],[
  {kind:'state',field:'active',values:['self']},
  {kind:'inventoryTarget',from:'deck',nameTerms:['Fiendsmith','Demoniherrero'],
    cardTypes:['spell','trap'],min:1}],
  [{op:'flag',flag:'noExtra',value:false}],
  [{op:'move',ref:'actor',to:'graveyard',position:'attack'}]);
const engraver=placed(state(),20196,'hand');
none(engraver,fiendsmith,'Grabador no puede descartar para buscar M/T Fiendsmith ausente');
assert.equal(engraver.tokens.find(t=>db.bindings[t.key]===20196).zone,'hand',
  'No gastar el coste al intentar activar una búsqueda sin objetivo');
const bogusSearch=rule(20196,'671:A07:R12:wrong-deck-target',['hand'],[
  {kind:'state',field:'active',values:['self']}],
  [{op:'take',side:'self',cid:999999,from:'deck',to:'hand',position:'attack'}]);
none(engraver,bogusSearch,'El motor tampoco inventa CIDs no incluidos en el Main');
const goblinXyz=rule(20533,'671:A07:R12:no-goblin-xyz',['graveyard'],[
  {kind:'state',field:'active',values:['self']},
  {kind:'inventoryTarget',from:'extra',nameTerms:['Goblin'],species:'Xyz',min:1}],
  [{op:'flag',flag:'noExtra',value:false}]);
none(placed(state(),20533,'graveyard'),goblinXyz,
  'El segundo efecto GY de Gran Fuga no tiene Xyz Goblin en Extra');
assert(!own.cards.some(c=>c.section==='extra' && /goblin/i.test(c.name)));
assert(!own.cards.some(c=>c.section==='side'));
assert.equal(R.inventoryCandidates(engraver,db,
  {from:'deck',nameTerms:['Fiendsmith','Demoniherrero'],cardTypes:['spell','trap']}).length,0);
assert.equal(R.inventoryCandidates(state(),db,
  {from:'extra',nameTerms:['Goblin'],species:'Xyz'}).length,0);

// R24: the two rival Extra summons must belong to ONE eligible turn. The
// witness API checks the source Extra card and creates real board tokens;
// it does not pretend to validate the rival's own material requirements.
const typhon=rule(19188,'671:A07:R24:overlay-exact',['monsters'],[
  {kind:'state',field:'active',values:['self']},
  {kind:'state',field:'phase',values:['main1','main2']},
  {kind:'extraSummonHistory',side:'opponent',min:2,turns:2}],
  [{op:'xyzOverlay',cid:19184,material:'actor',to:'m1',recipe:'typhon-highest-atk'}],
  [],[],false);
typhon.source=source(19184);
const publishedTyphon=verified.forDeck(671).filter(r=>r.id==='671:typhon:highest-atk');
assert.equal(publishedTyphon.length,7,'El catálogo ejecutable debe incluir siete destinos para TY-PHON');
assert(publishedTyphon.every(r=>r.source===source(19184)+'&request_locale=en'));
function observedTwoOn(turn){
  let s=placed(state(),19188,'m1');
  s.tokens.find(t=>db.bindings[t.key]===19188).currentATK=1600;
  s.turn=turn;s.active='opponent';
  s=E.observeExtraSummon(s,{key:rival.cards[1].key,zone:'m1'});
  s=E.observeExtraSummon(s,{key:rival.cards[2].key,zone:'m2'});
  s.active='self';
  return s;
}
none(placed(state(),19188,'m1'),typhon,'Sin historial rival no hay superposición');
const two=observedTwoOn(1);
two.turn=2;
const result=one(two,typhon,'Dos invocaciones en el turno rival anterior habilitan TY-PHON');
const publishedResult=one(two,publishedTyphon.find(r=>r.resolve[0].to==='m1'),
  'Catálogo de producción: TY-PHON ejecuta superposición con inventario aprobado');
assert(publishedResult.tokens.some(t=>db.bindings[t.key]===19184 && t.xyzSummoned));
const boss=result.tokens.find(t=>db.bindings[t.key]===19184);
const sp=result.tokens.find(t=>db.bindings[t.key]===19188);
assert(boss && boss.zone==='m1' && boss.xyzSummoned===true);
assert.equal(sp.zone,'material:'+boss.uid,'S:P pasa a material debajo de TY-PHON');
assert.equal(result.tokens.filter(t=>E.isMonster(t.zone)&&t.controller==='self').length,1);
assert(result._ruleFlags.noMoreSummons);
assert.equal(result._ruleFlagTurns.noMoreSummons,2);
assert(result._ruleEvents.some(e=>e.kind==='xyz_summon' && e.cid===19184));
for (const opponentMonster of result.tokens.filter(t=>t.owner==='opponent' && E.isMonster(t.zone))) {
  opponentMonster.currentATK=3300;
  assert(R.monsterEffectBlockedByTyphon(result,db,opponentMonster),
    'La restricción de ATK de TY-PHON también alcanza al rival');
}

const normal=rule(19364,'671:A07:R24:no-normal-after-xyz',['hand'],[
  {kind:'state',field:'active',values:['self']}],
  [{op:'summon',ref:'actor',method:'normal',from:'hand',to:'m2',position:'attack'}]);
const withHand=placed(result,19364,'hand');
none(withHand,normal,'TY-PHON prohíbe Normal posteriores en el mismo turno');
const special={...normal,id:'671:A07:R24:no-special-after-xyz',
  resolve:[{op:'summon',ref:'actor',method:'special',from:'hand',to:'m2',position:'attack'}]};
none(withHand,special,'TY-PHON prohíbe Especial posteriores en el mismo turno');
const nextTurn=E.patch(withHand,{turn:3});
assert.equal(actions(nextTurn,normal).actions.length,1,'El bloqueo de Invocaciones caduca en el siguiente turno');

let split=placed(state(),19188,'m1');
split.tokens[0].currentATK=1600;
split.active='opponent';
split=E.observeExtraSummon(split,{key:rival.cards[1].key,zone:'m1'});
split.turn=2;
split=E.observeExtraSummon(split,{key:rival.cards[2].key,zone:'m2'});
split.active='self';
none(split,typhon,'Una Invocación por turno NO suma dos para TY-PHON');
const oneOnly=E.clone(two);
oneOnly._ruleEvents=oneOnly._ruleEvents.slice(0,1);
none(oneOnly,typhon,'Una sola Invocación Extra tampoco cumple el umbral');
const stale=E.clone(two);stale.turn=3;
none(stale,typhon,'Dos turnos atrás no cuentan');
let sameTurn=placed(state(),19188,'m1');
sameTurn.turn=2;
sameTurn.tokens.find(t=>db.bindings[t.key]===19188).currentATK=1600;
sameTurn=E.observeExtraSummon(sameTurn,{key:rival.cards[1].key,zone:'m1'});
sameTurn=E.observeExtraSummon(sameTurn,{key:rival.cards[2].key,zone:'m2'});
assert.equal(actions(sameTurn,typhon).actions.length,1,
  'Dos Especial rivales desde Extra en tu turno también abren TY-PHON');

const unknownAtk=E.clone(two);
delete unknownAtk.tokens.find(t=>db.bindings[t.key]===19188).currentATK;
none(unknownAtk,typhon,'ATK desconocido debe impedir acreditar el monstruo mayor');
let lower=placed(two,19360,'m2');
lower.tokens.find(t=>db.bindings[t.key]===19360).currentATK=5000;
none(lower,typhon,'S:P 1600 no puede ser material si controlas jefe 5000');
const chooseBoss={...typhon,id:'671:A07:R24:highest-attack-boss',
  actor:{side:'self',zones:['monsters'],cid:19360},
  resolve:[{op:'xyzOverlay',cid:19184,material:'actor',to:'m2',recipe:'typhon-highest-atk'}]};
const onBoss=one(lower,chooseBoss,'El monstruo de 5000 sí admite superposición');
assert(onBoss.tokens.some(t=>db.bindings[t.key]===19360 && /^material:/.test(t.zone)));
none(two,typhon,'La zona bloqueada impide superposición',{blockedZones:['m1']});
none(two,typhon,'No Extra impide superposición',{noExtra:true});

// While an Xyz Summoned TY-PHON stays face-up, an already present 3000 ATK
// Creation King must not be allowed to activate its field effect.
const kingRule=rule(20216,'671:A07:R24:both-sides-atk-lock',['monsters'],[
  {kind:'state',field:'active',values:['self']}],
  [{op:'flag',flag:'noExtra',value:false}]);
const prior=placed(two,20216,'m2');
prior.tokens.find(t=>db.bindings[t.key]===20216).currentATK=3000;
assert.equal(actions(prior,kingRule).actions.length,1,'Rey sin TY-PHON puede activar');
const after=placed(result,20216,'m2');
after.tokens.find(t=>db.bindings[t.key]===20216).currentATK=3000;
none(after,kingRule,'TY-PHON bloquea el efecto del propio Rey con ATK 3000');

// An Xyz material already under the overlaid monster must be transferred.
const aeroRule={...typhon,id:'671:A07:R24:xyz-material-transfer',
  actor:{side:'self',zones:['monsters'],cid:9657}};
const aeroScenario=E.clone(two);
const spToken=aeroScenario.tokens.find(t=>db.bindings[t.key]===19188);
spToken.key=keys.get(9657);spToken.currentATK=1900;
for(let i=0;i<2;i++){
  let n=E.place(aeroScenario,{key:keys.get(19364),owner:'self',zone:'material:'+spToken.uid});
  aeroScenario.tokens=n.tokens;aeroScenario.nextId=n.nextId;
}
const aeroResult=one(aeroScenario,aeroRule,'Materiales de Xyz anterior se transfieren');
const t=aeroResult.tokens.find(x=>db.bindings[x.key]===19184);
assert.equal(aeroResult.tokens.filter(x=>x.zone==='material:'+t.uid).length,3,
  'TY-PHON debe conservar Xyz anterior y dos materiales transferidos');

console.log('Memento R12: 41/15/14 cotejado; búsquedas inexistentes y Side bloqueados.');
console.log('Memento R24: TY-PHON superpuesto con ATK/historial/overlay/turno verificados; 1+1 en turnos distintos rechazado.');
