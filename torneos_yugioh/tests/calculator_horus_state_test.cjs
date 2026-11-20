'use strict';
const assert = require('node:assert/strict');
const E = require('../assets/modules/play-calculator/engine.js');
const P = require('../assets/modules/play-calculator/planner.js');
const base = require('../assets/modules/play-calculator/card-facts.json');
const deck = {id:677,name:'Crystron Horus',format_codes:['tcg_advanced']};
const own = E.catalog(deck,[
  {id:528,deck_id:677,card_name:'Amset, Gloria de Horus',section:'main',quantity:3},
  {id:529,deck_id:677,card_name:'Hapi, Guía de Horus',section:'main',quantity:1},
  {id:536,deck_id:677,card_name:'Sarcófago del Rey',section:'main',quantity:2},
  {id:999,deck_id:677,card_name:'Flor de Ceniza y Primavera Feliz',section:'main',quantity:1}
]);
const rival = E.catalog({id:9991,name:'Rival de prueba',format_codes:['tcg_advanced']},[
  {id:1,deck_id:9991,card_name:'Carta rival de prueba',section:'main',quantity:1}
]);
const db = {...base, rules:[], identities:{},
  bindings:{...base.bindings,'677:528':19153,'677:529':19155,'677:536':19200,'677:999':12950},
  names:{...base.names,'677:528':'Amset, Gloria de Horus','677:529':'Hapi, Guía de Horus',
    '677:536':'Sarcófago del Rey','677:999':'Flor de Ceniza y Primavera Feliz'}
};
const add=(state,id,zone,position='attack')=>{
  const c=own.cards.find(x=>x.key==='677:'+id);
  assert(c,'Fixture missing slot '+id);
  const uid=state.nextId;
  return E.position(E.place(state,{key:c.key,owner:'self',controller:'self',zone}),uid,position);
};
const options={depth:2,width:80,budget:400};
const contains=(result,needle)=>result.plans.some(p=>p.steps.some(s=>s.label.includes(needle)));
let state=E.create(own,rival);
state=add(state,536,'s3');
state=add(state,528,'graveyard');
state=add(state,529,'graveyard');
state=add(state,999,'hand');
const before=JSON.stringify(state);
let result=P.solve(state,db,options);
assert.equal(JSON.stringify(state),before,'Calculation must not mutate the captured duel');
assert(contains(result,'Amset, Gloria de Horus'),'Amset GY summon should appear with Sarcophagus');
assert(contains(result,'Hapi, Guía de Horus'),'Hapi GY summon should appear with Sarcophagus');
assert(!contains(result,'Invoca Normalmente Flor de Ceniza'),'Hand trap cannot be generic recommended Normal Summon');
assert(result.plans.every(p=>p.steps.filter(s=>s.label.includes('Amset, Gloria de Horus')).length<=1),'Amset cannot be GY summoned twice per turn');
assert(!contains(P.solve(state,db,{...options,specialBlocked:true}),'Invoca Especialmente desde el Cementerio'),'Special restriction must stop Horus procedure');
assert(!contains(P.solve(state,db,{...options,graveyardBlocked:true}),'Invoca Especialmente desde el Cementerio'),'GY restriction must stop Horus procedure');
const noSarc={...E.clone(state),tokens:state.tokens.filter(t=>t.key!=='677:536')};
assert(!contains(P.solve(noSarc,db,options),'Invoca Especialmente desde el Cementerio'),'Without Sarcophagus no GY summon');
const facedown=E.clone(state);facedown.tokens.find(t=>t.key==='677:536').position='set';
assert(!contains(P.solve(facedown,db,options),'Invoca Especialmente desde el Cementerio'),'Face-down Sarcophagus does not satisfy the condition');
const used=P.solve(state,db,{...options,used:['horus:gy-special:19153']});
assert(!contains(used,'Amset, Gloria de Horus'),'Once-per-turn used marker should block Amset');
console.log('State-driven calculator: Horus GY procedures and hand-trap exclusion verified.');
