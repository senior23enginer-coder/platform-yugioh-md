/* A07 / punto 1: coverage of the 22 rules not exercised by the original 13 cases.
 * Every passing case is a deliberately narrow, auditable state; this is NOT a
 * general Yu-Gi-Oh rules oracle or proof of any complete C01-C12 route. */
const assert = require('node:assert/strict');
const E = require('../assets/modules/play-calculator/engine.js');
const R = require('../assets/modules/play-calculator/rules.js');

const ids = {
  witch:19364, tatsun:19362, goblin:19366, ghost:19472, sleep:20199,
  mace:19365, boss:19360, horn:19361, bone:19368, fusion:19904,
  king:20216, twin:19878, chimera:16845, duke:20389, fiend:20196,
  sp:19188, requiem:20225, moon:19932, sequence:20226, aeroshark:9657,
  fuwalos:20500, lancea:11243, heat:9139, typhon:19184, escape:20533,
  trap:12950, opponent:777001, called:13619, gordian:21203, dragostapelia:12870,
};
const deck={id:671,name:'Memento / solo prueba',format_codes:['tcg_advanced']};
const extras=new Set([ids.king,ids.twin,ids.chimera,ids.duke,ids.sp,ids.requiem,ids.moon,ids.sequence,ids.aeroshark,ids.typhon,ids.dragostapelia]);
const rowIds=Object.values(ids).filter(id=>id!==ids.opponent);
const rows=rowIds.map((cid,i)=>({deck_id:671,id:i+1,card_name:'CID '+cid,
  section:extras.has(cid)?'extra':'main',quantity:cid===ids.fusion||cid===ids.witch?3:cid===ids.tatsun?2:1}));
const own=E.catalog(deck,rows);
const rival=E.catalog({id:672,name:'Rival sintético',format_codes:['tcg_advanced']},
  [{deck_id:672,id:1,card_name:'Monstruo rival sintético',section:'main',quantity:2},
    {deck_id:672,id:2,card_name:'Fusión rival observada',section:'extra',quantity:1},
    {deck_id:672,id:3,card_name:'Enlace rival observado',section:'extra',quantity:1}]);
const keys=new Map(own.cards.map((c,i)=>[rowIds[i],c.key]));
const bindings=Object.fromEntries([...rowIds.map(cid=>[keys.get(cid),cid]),[rival.cards[0].key,ids.opponent]]);
const db={bindings,facts:{},rules:[]};
const source=cid=>`https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=${cid}&ope=2`;
function rule(num,cid,zones,settings={}) {
  return {
    version:1,id:`671:A07:R${String(num).padStart(2,'0')}:p1-case`,deckId:671,
    format:'tcg_advanced',scope:'single-effect',label:`R${num} caso restringido`,
    source:source(cid),assumptions:['Estado sintético fijado, no regla exhaustiva'],
    actor:{side:'self',zones,cid},
    guards:[{kind:'state',field:'active',values:['self']}],
    select:[],costs:[],resolve:[{op:'flag',flag:'noExtra',value:false}],
    tags:[],activation:true,oncePerTurn:true,...settings,
  };
}
function state(pieces=[],opponent=false) {
  let s=E.create(own,opponent?rival:undefined);
  for(const [cid,zone,side='self',position='attack'] of pieces){
    s=E.place(s,{key:side==='self'?keys.get(cid):rival.cards[0].key,owner:side,zone});
    s=E.position(s,s.nextId-1,position);
  }
  return s;
}
function run(s,r,options={}) {db.rules=[r];return R.actions(s,db,options);}
function allowed(s,r,options={}) {
  const result=run(s,r,options);
  assert.equal(result.actions.length,1,`R${r.id} debe ejecutar una vez: ${JSON.stringify(result.rejected)}`);
  return result.actions[0].state;
}
function denied(s,r,reason='debe fallar cerrado',options={}) {
  const result=run(s,r,options);
  assert.equal(result.actions.length,0,`${r.id}: ${reason}`);
}
const covered=[];
function passed(n,fn){fn();covered.push('R'+String(n).padStart(2,'0'));}

passed(1,()=>{
  const r=rule(1,ids.heat,['hand'],{
    guards:[{kind:'state',field:'active',values:['self']},
      {kind:'state',field:'phase',values:['main1']},{kind:'phaseStart'}],
    resolve:[{op:'flag',flag:'specialBlocked',value:true},
      {op:'move',ref:'actor',to:'graveyard',position:'attack'}],
  });
  const s=state([[ids.heat,'hand'],[ids.tatsun,'hand']]);
  const after=allowed(s,r);
  assert(after._ruleFlags.specialBlocked,'Oleada bloquea la Especial en escenario restringido');
  const special=rule(1,ids.tatsun,['hand'],{
    resolve:[{op:'summon',ref:'actor',from:'hand',method:'special',to:'m1',position:'attack'}],
  });
  denied(after,special,'el bloqueo de Oleada impide el despliegue');
  const late=rule(1,ids.tatsun,['hand'],{id:'671:A07:R01:already-main-action',
    resolve:[{op:'flag',flag:'noExtra',value:false}]});
  const usedMain=allowed(s,late);
  denied(usedMain,r,'Oleada no puede activarse tras otra acción de motor en Main1');
  const main2=E.patch(s,{phase:'main2'});
  denied(main2,r,'Oleada solo corresponde al inicio de Main1');
});

passed(3,()=>{
  const r=rule(3,ids.tatsun,['hand'],{
    guards:[{kind:'state',field:'active',values:['self']},
      {kind:'count',selector:{side:'self',zones:['monsters'],cids:[ids.fiend,ids.moon]},min:0,max:0}],
    resolve:[{op:'summon',ref:'actor',from:'hand',method:'special',to:'m2',position:'attack'}],
  });
  assert.equal(allowed(state([[ids.tatsun,'hand']]),r).tokens[0].zone,'m2');
  denied(state([[ids.tatsun,'hand'],[ids.fiend,'m1']]),r,'Grabador boca arriba impide la Especial');
});

passed(7,()=>{
  const fused=rule(7,ids.fusion,['hand'],{
    select:[{ref:'a',selector:{side:'self',zones:['monsters'],cid:ids.witch}},
      {ref:'b',selector:{side:'self',zones:['monsters'],cid:ids.ghost}},
      {ref:'c',selector:{side:'self',zones:['hand'],cid:ids.sleep}}],
    resolve:[{op:'fusion',cid:ids.king,materials:['a','b','c'],
      materialCids:[ids.witch,ids.ghost,ids.sleep],to:'emz1',recipe:'exact-cids',allowGraveyard:false},
      {op:'move',ref:'actor',to:'graveyard',position:'attack'}],
  });
  const before=state([[ids.fusion,'hand'],[ids.witch,'m1'],[ids.ghost,'m2'],[ids.sleep,'hand']]);
  const after=allowed(before,fused);
  const trigger=rule(7,ids.king,['monsters'],{
    id:'671:A07:R07:king-send-after-fusion',
    trigger:{kind:'fusion_summoned',actorCid:ids.king},
    resolve:[{op:'take',side:'self',cid:ids.mace,from:'deck',to:'graveyard',position:'attack'},
      {op:'take',side:'self',cid:ids.boss,from:'deck',to:'graveyard',position:'attack'},
      {op:'take',side:'self',cid:ids.goblin,from:'deck',to:'graveyard',position:'attack'}],
    tags:['monsterEffect','sendFromDeck'],
  });
  assert.equal(allowed(after,trigger).tokens.filter(t=>t.zone==='graveyard').length,7);
  const noFusion=E.clone(after);noFusion._ruleEvents=[];
  denied(noFusion,trigger,'no atribuir envío a un Rey que no fue fusionado');
});

passed(9,()=>{
  const r=rule(9,ids.bone,['hand'],{
    select:[{ref:'victim',selector:{side:'self',zones:['monsters'],cid:ids.witch}}],
    resolve:[{op:'destroy',ref:'victim',reason:'effect'},
      {op:'take',side:'self',cid:ids.tatsun,from:'deck',to:'m2',position:'defense',summonMethod:'special'},
      {op:'move',ref:'actor',to:'graveyard',position:'attack'}],
    tags:['summonFromDeck'],
  });
  const next=allowed(state([[ids.bone,'hand'],[ids.witch,'m1']]),r);
  assert(next.tokens.some(t=>bindings[t.key]===ids.tatsun&&t.zone==='m2'&&t.position==='defense'));
  assert(next._ruleEvents.some(e=>e.kind==='summon'&&e.cid===ids.tatsun));
  denied(state([[ids.bone,'hand']]),r,'Fiesta necesita destruir un Memento');
});

passed(10,()=>{
  const r=rule(10,ids.moon,['monsters'],{
    resolve:[{op:'link',cid:ids.requiem,materials:['actor'],
      materialCids:[ids.moon],to:'emz1',recipe:'exact-cids',linkRating:1}],
    activation:false,oncePerTurn:false,
  });
  const s=state([[ids.moon,'m1']]);
  const after=allowed(s,r);
  assert(after.tokens.some(t=>bindings[t.key]===ids.requiem&&t.zone==='emz1'));
  assert(after.tokens.some(t=>bindings[t.key]===ids.moon&&t.zone==='graveyard'));
  denied(state([[ids.witch,'m1']]),r,'solo Luna LUZ/Demonio es material del caso fijado');
});

passed(11,()=>{
  const r=rule(11,ids.witch,['monsters'],{
    select:[{ref:'second',selector:{side:'self',zones:['monsters'],cid:ids.tatsun}}],
    resolve:[{op:'link',cid:ids.sp,materials:['actor','second'],
      materialCids:[ids.witch,ids.tatsun],to:'emz1',recipe:'exact-cids',linkRating:2}],
    activation:false,oncePerTurn:false,
  });
  const s=state([[ids.witch,'m1'],[ids.tatsun,'m2']]);
  assert(allowed(s,r).tokens.some(t=>bindings[t.key]===ids.sp&&t.zone==='emz1'));
  denied(s,r,'no permitir Extra bajo bloqueo',{noExtra:true});
});

passed(12,()=>{
  const r=rule(12,ids.fiend,['hand'],{
    resolve:[{op:'take',side:'self',cid:999999,from:'deck',to:'hand',position:'attack'}],
    tags:['addFromDeck'],
  });
  const attempted=run(state([[ids.fiend,'hand']]),r);
  assert.equal(attempted.actions.length,0,'Una búsqueda sin objetivo en este Main debe ser imposible');
  assert(attempted.rejected.some(x=>/Recurso no identificado/.test(x.message)));
  assert(own.cards.every(c=>c.section==='main'||c.section==='extra'),
    'Una Side aún sin intercambio no debe figurar en el catálogo de duelo');
});

passed(13,()=>{
  const r=rule(13,ids.fusion,['graveyard'],{
    select:[{ref:'victim',selector:{side:'self',zones:['monsters'],cid:ids.tatsun}}],
    costs:[{op:'move',ref:'actor',to:'banished',position:'attack'}],
    resolve:[{op:'destroy',ref:'victim',reason:'effect'},
      {op:'take',side:'self',cid:ids.fusion,from:'deck',to:'hand',position:'attack'}],
    tags:['addFromDeck'],
  });
  const s=state([[ids.fusion,'graveyard'],[ids.tatsun,'m1']]);
  const after=allowed(s,r);
  assert(after.tokens.some(t=>bindings[t.key]===ids.fusion&&t.zone==='banished'));
  assert(after.tokens.some(t=>bindings[t.key]===ids.fusion&&t.zone==='hand'));
  const negated=run(s,r,{negateEffectRuleIds:[r.id]});
  assert.equal(negated.actions.length,1);
  assert.equal(negated.actions[0].state.tokens.find(t=>bindings[t.key]===ids.tatsun).zone,'m1');
  assert(negated.actions[0].state.tokens.some(t=>bindings[t.key]===ids.fusion&&t.zone==='banished'));
});

passed(14,()=>{
  const r=rule(14,ids.fusion,['hand'],{
    select:[{ref:'gy',selector:{side:'self',zones:['graveyard'],cid:ids.witch}},
      {ref:'hand',selector:{side:'self',zones:['hand'],cid:ids.ghost}},
      {ref:'field',selector:{side:'self',zones:['monsters'],cid:ids.tatsun}}],
    resolve:[{op:'fusion',cid:ids.king,materials:['gy','hand','field'],
      materialCids:[ids.witch,ids.ghost,ids.tatsun],to:'emz1',recipe:'exact-cids',allowGraveyard:true},
      {op:'move',ref:'actor',to:'graveyard',position:'attack'}],
  });
  const s=state([[ids.fusion,'hand'],[ids.witch,'graveyard'],[ids.ghost,'hand'],[ids.tatsun,'m1']]);
  denied(s,r,'sin destrucción por efecto no hay material en GY');
  const prepared=E.clone(s);
  prepared._ruleEvents=[{kind:'destroy',uid:900,cid:ids.mace,from:'m2',side:'self',turn:1,by:'effect',stage:'resolve'}];
  const fused=allowed(prepared,r);
  assert(fused.tokens.some(t=>bindings[t.key]===ids.king&&t.zone==='emz1'));
  assert(!fused.tokens.some(t=>bindings[t.key]===ids.witch),
    'Una carta barajada al Deck sale del registro de copias visibles');
  const previousTurn=E.clone(prepared);previousTurn._ruleEvents[0].turn=0;
  denied(previousTurn,r,'destrucción de turno anterior no abre GY');
});

passed(15,()=>{
  const r=rule(15,ids.twin,['monsters'],{
    trigger:{kind:'fusion_summoned',actorCid:ids.twin},
    select:[{ref:'victim',selector:{side:'self',zones:['hand'],cid:ids.sleep}}],
    resolve:[{op:'destroy',ref:'victim',reason:'effect'},
      {op:'take',side:'self',cid:ids.boss,from:'deck',to:'hand',position:'attack'},
      {op:'take',side:'self',cid:ids.goblin,from:'deck',to:'hand',position:'attack'}],
    tags:['monsterEffect','addFromDeck'],
  });
  const s=state([[ids.twin,'emz1'],[ids.sleep,'hand']]);
  s._ruleEvents=[{kind:'fusion_summon',uid:s.tokens[0].uid,cid:ids.twin,turn:1,side:'self'}];
  const n=allowed(s,r);
  assert(n.tokens.some(t=>bindings[t.key]===ids.sleep&&t.zone==='graveyard'));
  assert.equal(n.tokens.filter(t=>t.zone==='hand').length,2);
  const noTrigger=E.clone(s);noTrigger._ruleEvents=[];
  denied(noTrigger,r,'Dragón Gemelo no Invocado por Fusión');
});

passed(16,()=>{
  const r=rule(16,ids.fusion,['hand'],{
    select:[{ref:'h',selector:{side:'self',zones:['hand'],cid:ids.sleep}},
      {ref:'f1',selector:{side:'self',zones:['monsters'],cid:ids.witch}},
      {ref:'f2',selector:{side:'self',zones:['monsters'],cid:ids.ghost}}],
    resolve:[{op:'fusion',cid:ids.chimera,materials:['h','f1','f2'],
      materialCids:[ids.sleep,ids.witch,ids.ghost],to:'emz1',recipe:'exact-cids',
      allowGraveyard:false,requiredOrigins:'hand-and-field',distinctCids:true},
      {op:'move',ref:'actor',to:'graveyard',position:'attack'}],
  });
  const s=state([[ids.fusion,'hand'],[ids.sleep,'hand'],[ids.witch,'m1'],[ids.ghost,'m2']]);
  assert(allowed(s,r).tokens.some(t=>bindings[t.key]===ids.chimera&&t.zone==='emz1'));
  denied(state([[ids.fusion,'hand'],[ids.sleep,'hand'],[ids.witch,'hand'],[ids.ghost,'hand']]),
    {...r,select:r.select.map(x=>({...x,selector:{...x.selector,zones:['hand']}}))},
    'Quimera no admite solo materiales de mano');
});

passed(18,()=>{
  const r=rule(18,ids.mace,['hand'],{
    guards:[{kind:'state',field:'active',values:['opponent']},
      {kind:'state',field:'phase',values:['main1','main2']},
      {kind:'count',selector:{side:'self',zones:['monsters'],cid:ids.boss},min:1,max:1}],
    select:[{ref:'target',selector:{side:'opponent',zones:['monsters'],cid:ids.opponent,faceUp:true}}],
    costs:[{op:'move',ref:'actor',to:'graveyard',position:'attack'}],
    resolve:[{op:'changeControl',ref:'target',to:'self',reason:'hasta fin de turno (no temporizado)'}],
  });
  const s=state([[ids.mace,'hand'],[ids.boss,'m1'],[ids.opponent,'m2','opponent']],true);
  s.active='opponent';
  const next=allowed(s,r);
  assert.equal(next.tokens.find(t=>bindings[t.key]===ids.opponent).controller,'self');
  const wrongZone=state([[ids.mace,'graveyard'],[ids.boss,'m1'],[ids.opponent,'m2','opponent']],true);
  wrongZone.active='opponent';
  denied(wrongZone,r,'Maza en GY no puede descartarse desde mano');
});

passed(19,()=>{
  const r=rule(19,ids.requiem,['graveyard'],{
    select:[{ref:'target',selector:{side:'self',zones:['monsters'],cid:ids.fiend,faceUp:true}}],
    resolve:[{op:'equip',ref:'actor',target:'target',targetCid:ids.fiend,to:'s1'}],
  });
  const s=state([[ids.requiem,'graveyard'],[ids.fiend,'m1']]);
  const n=allowed(s,r);
  const equipped=n.tokens.find(t=>bindings[t.key]===ids.requiem);
  assert.equal(equipped.zone,'s1');
  assert.equal(equipped.equippedToUid,n.tokens.find(t=>bindings[t.key]===ids.fiend).uid);
  denied(state([[ids.requiem,'graveyard']]),r,
    'sin Demonio LUZ no hay equipamiento que habilite a Princesa');
});

passed(20,()=>{
  const r=rule(20,ids.sequence,['monsters'],{
    select:[{ref:'fiend',selector:{side:'self',zones:['graveyard'],cid:ids.fiend}},
      {ref:'goblin',selector:{side:'self',zones:['graveyard'],cid:ids.goblin}}],
    resolve:[{op:'fusion',cid:ids.duke,materials:['fiend','goblin'],
      materialCids:[ids.fiend,ids.goblin],to:'m2',recipe:'exact-cids',allowGraveyard:true,
      graveyardPolicy:'fiendsmith-sequence'}],
    tags:['monsterEffect'],
  });
  const s=state([[ids.sequence,'emz1'],[ids.fiend,'graveyard'],[ids.goblin,'graveyard']]);
  const after=allowed(s,r);
  assert(after.tokens.some(t=>bindings[t.key]===ids.duke&&t.zone==='m2'));
  assert(!after.tokens.some(t=>bindings[t.key]===ids.fiend||bindings[t.key]===ids.goblin),
    'Ambos materiales Demonio desde GY deben barajarse al Deck');
  denied(state([[ids.sequence,'emz1'],[ids.fiend,'graveyard']]),r,
    'No inventar Goblin disponible desde el Deck');
});

passed(22,()=>{
  const r=rule(22,ids.horn,['hand'],{
    guards:[{kind:'state',field:'active',values:['self']},
      {kind:'distinctCount',selector:{side:'self',zones:['graveyard'],cids:[ids.witch,ids.tatsun,ids.goblin,ids.ghost]},min:3}],
    resolve:[{op:'summon',ref:'actor',from:'hand',method:'special',to:'m1',position:'attack'}],
  });
  const yes=state([[ids.horn,'hand'],[ids.witch,'graveyard'],[ids.tatsun,'graveyard'],[ids.goblin,'graveyard']]);
  assert(allowed(yes,r).tokens.some(t=>bindings[t.key]===ids.horn&&t.zone==='m1'));
  denied(state([[ids.horn,'hand'],[ids.witch,'graveyard'],[ids.witch,'graveyard'],[ids.goblin,'graveyard']]),
    r,'tres copias pero solo dos nombres distintos');
});

passed(23,()=>{
  const r=rule(23,ids.dragostapelia,['monsters'],{
    select:[{ref:'target',selector:{side:'opponent',zones:['monsters'],cid:ids.opponent,faceUp:true}}],
    resolve:[{op:'counter',ref:'target',kind:'predator',amount:1}],
    tags:['monsterEffect'],
  });
  const s=state([[ids.dragostapelia,'emz1'],[ids.opponent,'m1','opponent']],true);
  const n=allowed(s,r);
  assert.equal(n.tokens.find(t=>bindings[t.key]===ids.opponent).counters.predator,1);
  denied(state([[ids.dragostapelia,'emz1']]),r,
    'sin monstruo rival boca arriba no hay contador legal');
});

passed(24,()=>{
  const r=rule(24,ids.typhon,['extra'],{
    guards:[{kind:'state',field:'active',values:['self']},
      {kind:'extraSummonHistory',side:'opponent',min:2,turns:2}],
    resolve:[{op:'flag',flag:'noExtra',value:false}],
    activation:false,oncePerTurn:false,
  });
  const s=state([[ids.typhon,'extra']],true);s.turn=2;
  denied(s,r,'cero Invocaciones rivales desde el Extra no habilitan TY-PHON');
  const two=E.clone(s);two.active='opponent';
  let observed=E.observeExtraSummon(two,{key:rival.cards[1].key,zone:'m1'});
  observed=E.observeExtraSummon(observed,{key:rival.cards[2].key,zone:'m2'});
  observed.active='self';
  assert.equal(run(observed,r).actions.length,1,'dos eventos observados el mismo turno abren condición de historial');
  let split=E.clone(two);split.turn=1;
  split=E.observeExtraSummon(split,{key:rival.cards[1].key,zone:'m1'});
  split.turn=2;
  split=E.observeExtraSummon(split,{key:rival.cards[2].key,zone:'m2'});
  split.active='self';
  denied(split,r,'una Invocación en cada turno no suma dos: impedir falso positivo anterior');
});

passed(25,()=>{
  const r=rule(25,ids.witch,['monsters'],{
    select:[{ref:'b',selector:{side:'self',zones:['monsters'],cid:ids.witch}}],
    resolve:[{op:'xyz',cid:ids.aeroshark,materials:['actor','b'],
      materialCids:[ids.witch,ids.witch],to:'emz1',recipe:'exact-cids'}],
    activation:false,oncePerTurn:false,
  });
  const s=state([[ids.witch,'m1'],[ids.witch,'m2']]);
  const outcomes=run(s,r);
  assert.equal(outcomes.actions.length,2,'Dos copias de Angbruja de Nivel 3 son materiales simétricos');
  const n=outcomes.actions[0].state;
  const shark=n.tokens.find(t=>bindings[t.key]===ids.aeroshark);
  assert.equal(n.tokens.filter(t=>t.zone==='material:'+shark.uid).length,2);
  denied(state([[ids.witch,'m1']]),r,'falta segundo monstruo Nivel 3');
});

passed(29,()=>{
  const r=rule(29,ids.fuwalos,['hand'],{
    guards:[{kind:'state',field:'active',values:['opponent']},
      {kind:'count',selector:{side:'self',zones:['field-all']},min:0,max:0}],
    costs:[{op:'move',ref:'actor',to:'graveyard',position:'attack'}],
  });
  const s=state([[ids.fuwalos,'hand']]);s.active='opponent';
  assert.equal(allowed(s,r).tokens.find(t=>bindings[t.key]===ids.fuwalos).zone,'graveyard');
  const occupied=state([[ids.fuwalos,'hand'],[ids.witch,'m1']]);occupied.active='opponent';
  denied(occupied,r,'Fuwalos no se activa si controlas alguna carta');
});

passed(30,()=>{
  const r=rule(30,ids.lancea,['hand'],{
    guards:[{kind:'state',field:'active',values:['opponent']}],
    costs:[{op:'tribute',ref:'actor',reason:'cost',allowHand:true}],
    resolve:[{op:'flag',flag:'banishBlocked',value:true,duration:'turn'}],
  });
  const s=state([[ids.lancea,'hand']]);s.active='opponent';
  const n=allowed(s,r);
  assert(n._ruleFlags.banishBlocked);
  assert(n._ruleEvents.some(e=>e.kind==='tribute'&&e.by==='cost'));
  const tryBanish=rule(30,ids.fusion,['graveyard'],{
    costs:[{op:'move',ref:'actor',to:'banished',position:'attack'}],
    resolve:[{op:'flag',flag:'noExtra',value:false}],
  });
  const blocked=E.place(n,{key:keys.get(ids.fusion),owner:'self',zone:'graveyard'});
  blocked.active='self';
  denied(blocked,tryBanish,'Lancea impide destierro propio también');
});

passed(28,()=>{
  const six=[ids.king,ids.twin,ids.chimera,ids.duke,ids.sp,ids.requiem];
  const r=rule(28,ids.gordian,['hand'],{
    guards:[{kind:'state',field:'active',values:['self']},
      {kind:'state',field:'phase',values:['main1','main2']},{kind:'phaseStart'}],
    select:[...six.map((cid,i)=>({ref:'ex'+i,
      selector:{side:'self',zones:['extra'],cid}})),
      {ref:'victim',selector:{side:'opponent',zones:['monsters'],cid:ids.opponent,faceUp:true}}],
    costs:[{op:'banishExtraFaceDown',refs:six.map((_,i)=>'ex'+i),count:6}],
    resolve:[{op:'move',ref:'victim',to:'deck',position:'attack'},
      {op:'move',ref:'actor',to:'graveyard',position:'attack'}],
  });
  const s=state([[ids.gordian,'hand'],...six.map(cid=>[cid,'extra']),
    [ids.opponent,'m1','opponent']],true);
  const n=allowed(s,r);
  assert.equal(n.tokens.filter(t=>t.zone==='banished'&&t.position==='set').length,6,
    'Seis cartas del Extra deben quedar desterradas boca abajo por el objetivo');
  assert(!n.tokens.some(t=>bindings[t.key]===ids.opponent),
    'El objetivo rival devuelto al Deck sale del registro de copias visibles');
  assert.equal(run(s,r,{negateEffectRuleIds:[r.id]}).actions[0].state.tokens
    .filter(t=>t.zone==='banished').length,6,
    'Negar el efecto de Gordiano no devuelve el coste del Extra');
  denied(state([[ids.gordian,'hand'],...six.slice(0,5).map(cid=>[cid,'extra']),
    [ids.opponent,'m1','opponent']],true),r,
    'cinco cartas del Extra no pueden pagar el coste por una sola carta objetivo');
});

passed(33,()=>{
  const r=rule(33,ids.witch,['monsters'],{
    select:[{ref:'b',selector:{side:'self',zones:['monsters'],cid:ids.witch}}],
    resolve:[{op:'xyz',cid:ids.aeroshark,materials:['actor','b'],
      materialCids:[ids.witch,ids.witch],to:'emz1',recipe:'exact-cids'}],
    activation:false,oncePerTurn:false,
  });
  const s=state([[ids.witch,'m1'],[ids.witch,'m2']]);
  const result=run(s,r);
  assert.equal(result.actions.length,2);
  const n=result.actions[0].state;
  assert.equal(n.tokens.filter(t=>E.isMonster(t.zone)).length,1);
  denied(s,r,'sin Extra no hay Xyz',{noExtra:true});
});

/* First-pass cases are deliberately limited. R12 and R24 now receive a
 * separate focused fixture covering the approved inventory and TY-PHON. */
assert.deepEqual(covered,['R01','R03','R07','R09','R10','R11','R12','R13','R14','R15','R16',
  'R18','R19','R20','R22','R23','R24','R25','R29','R30','R28','R33']);
assert.equal(covered.length,22);
console.log('Memento A07 punto 1: 22/22 reglas antes sin caso probadas; '+
  'R12/R24 ampliadas en suite específica. 0/34 certificación integral.');
module.exports={covered};
