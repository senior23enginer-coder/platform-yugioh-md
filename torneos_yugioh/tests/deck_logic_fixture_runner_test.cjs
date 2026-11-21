'use strict';
const assert=require('node:assert/strict');
const {validate,run,fromPaper}=require('../../scripts/run-deck-logic-fixtures.cjs');
const rule={
 version:1,id:'999999:synthetic-field:activate',deckId:999999,format:'tcg_advanced',
 scope:'single-effect',label:'Efecto de control sintético de activación',
 source:'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19367',
 actor:{side:'self',zones:['hand'],cid:19367},
 guards:[{kind:'state',field:'phase',values:['main1']}],
 select:[],costs:[],resolve:[{op:'move',ref:'actor',to:'field',position:'attack'}],
 tags:[],assumptions:['Escenario estrictamente sintético: no certifica una carta real'],activation:true,oncePerTurn:true
};
const bundle={schema:'station-x-logic-case-fixtures/1',deck_id:999999,
 format:'tcg_advanced',
 cards:[
   {card_id:1,cid:19367,name:'Carta sintética de efecto',section:'main',quantity:1},
   {card_id:2,cid:19184,name:'Extra de prueba',section:'extra',quantity:1},
   {card_id:3,cid:12950,name:'Side de prueba',section:'side',quantity:1}
 ],
 rules:[rule],cases:[
   {case_id:'F-POS',effect_id:rule.id,
     state_before:{turn:1,phase:'main1',active:'self',placements:[{card_id:1,zone:'hand'}]},
     expected_legal:true,expected_after:{tokens:[{cid:19367,zone:'field'}]}},
   {case_id:'F-NO-ZONE',effect_id:rule.id,
     state_before:{turn:1,phase:'main1',active:'self',placements:[{card_id:1,zone:'graveyard'}]},
     expected_legal:false},
   {case_id:'F-NO-PHASE',effect_id:rule.id,
     state_before:{turn:1,phase:'battle',active:'self',placements:[{card_id:1,zone:'hand'}]},
     expected_legal:false}
 ]};
const options={deckId:999999,explicitlyAssigned:true};
assert.throws(()=>validate(bundle,{deckId:999999,explicitlyAssigned:false}),
  /USER_DECK_ASSIGNMENT_REQUIRED/);
assert.throws(()=>validate(bundle,{deckId:1,explicitlyAssigned:true}),/DECK_ID_NOT_ASSIGNED/);
const wrong=JSON.parse(JSON.stringify(bundle));
wrong.cards=wrong.cards.filter(c=>c.section!=='side');
assert.throws(()=>validate(wrong,options),/REQUIRED_SIDE_NOT_LOADED/);
const conflicting=JSON.parse(JSON.stringify(bundle));
conflicting.rules[0].format='rush_duel';
assert.throws(()=>validate(conflicting,options),/FOREIGN_CALCULATOR_RULE/);
const result=run(bundle,options);
assert.equal(result.total,3);
assert.equal(result.failed,0,JSON.stringify(result));
assert.equal(result.passed,3);
const packageData={schema:'station-x-deck-package/1',deck_id:999999,
 inventory:bundle.cards.map(c=>({id:c.card_id,section:c.section,quantity:c.quantity})),
 record:{deck_id:999999,format:'tcg_advanced',
 cards:bundle.cards.map(c=>({cid:c.cid,name:c.name,slots:[{
   card_id:c.card_id,section:c.section,quantity:c.quantity}]})),
 calculator:{rules:[rule]}}};
const paper={schema:'station-x-paper-cases/1',deck_id:999999,format:'tcg_advanced',
 effects:[{effect_id:rule.id}],cases:bundle.cases.map(c=>({
   case_id:c.case_id,effect_id:c.effect_id,status:'VERIFICADO',
   known_vs_unknown:'known',expected_legal:c.expected_legal,
   state_before:c.state_before,expected_state_after:c.expected_after
 }))};
const adapted=fromPaper(paper,packageData,options);
assert.equal(adapted.blocked.length,0);
assert.equal(run(adapted.bundle,options).passed,3,'A10B must reuse GPT logical IDs');
const incomplete=JSON.parse(JSON.stringify(packageData));
incomplete.record.calculator.rules=[];
const notSupported=fromPaper(paper,incomplete,options);
assert.equal(notSupported.bundle.cases.length,0);
assert.equal(notSupported.blocked.length,3);
assert(notSupported.blocked.every(x=>x.reason==='OPERADOR_NO_SOPORTADO'));
assert.throws(()=>fromPaper(paper,packageData,{deckId:999999,explicitlyAssigned:false}),
 /USER_DECK_ASSIGNMENT_REQUIRED/);

console.log(JSON.stringify({ok:true,syntheticOnly:true,deckAnalysesExecuted:0,
  paperCases:3,pass:result.passed,runner:'single-reusable'}));
