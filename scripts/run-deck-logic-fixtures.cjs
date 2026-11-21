'use strict';
// One parameterized interpreter for future GPT paper-case fixture files.
// Never discovers or analyses decks. A real run requires an explicitly assigned
// deck ID, matching format and Main/Extra/Side present in the submitted file.
const fs=require('node:fs');
const path=require('node:path');
const E=require('../torneos_yugioh/assets/modules/play-calculator/engine.js');
const R=require('../torneos_yugioh/assets/modules/play-calculator/rules.js');
const facts=require('../torneos_yugioh/assets/modules/play-calculator/card-facts.json');
function check(ok,message){if(!ok)throw Error(message)}
function validate(bundle,{deckId,explicitlyAssigned=false}={}){
  check(explicitlyAssigned===true,'USER_DECK_ASSIGNMENT_REQUIRED');
  check(bundle?.schema==='station-x-logic-case-fixtures/1','INVALID_FIXTURE_SCHEMA');
  check(Number.isInteger(deckId)&&deckId>0&&bundle.deck_id===deckId,'DECK_ID_NOT_ASSIGNED');
  check(typeof bundle.format==='string'&&bundle.format!=='','FORMAT_REQUIRED');
  check(Array.isArray(bundle.cards)&&Array.isArray(bundle.rules)&&Array.isArray(bundle.cases),
    'FIXTURE_ARRAYS_REQUIRED');
  const byId=new Map(),counts={main:0,extra:0,side:0,reserve:0};
  for(const card of bundle.cards){
    const {card_id,cid,section,quantity,name}=card;
    check(Number.isInteger(card_id)&&card_id>0&&!byId.has(card_id),'DUPLICATE_NATIVE_CARD_ID');
    check(Number.isInteger(cid)&&cid>0&&typeof name==='string'&&name.trim(),'INVALID_CARD_IDENTITY');
    check(Object.hasOwn(counts,section)&&Number.isInteger(quantity)&&quantity>0,'INVALID_CARD_SLOT');
    byId.set(card_id,card);counts[section]+=quantity;
  }
  for(const section of ['main','extra','side'])
    check(counts[section]>0,'REQUIRED_'+section.toUpperCase()+'_NOT_LOADED');
  for(const rule of bundle.rules)
    check(rule.deckId===deckId&&rule.format===bundle.format,'FOREIGN_CALCULATOR_RULE');
  const caseIds=new Set();
  for(const item of bundle.cases){
    check(typeof item.case_id==='string'&&item.case_id.length>0&&!caseIds.has(item.case_id),
      'DUPLICATE_OR_INVALID_CASE');
    caseIds.add(item.case_id);
    check(typeof item.effect_id==='string'&&bundle.rules.some(r=>r.id===item.effect_id),
      'UNKNOWN_EFFECT_ID');
    check(typeof item.expected_legal==='boolean'&&item.state_before&&
      Array.isArray(item.state_before.placements),'INVALID_EXPECTED_CASE');
    for(const placed of item.state_before.placements)
      check(byId.has(placed.card_id)&&['hand','graveyard','banished','m1','m2','m3','m4','m5',
        's1','s2','s3','s4','s5','field','extra'].includes(placed.zone),
        'UNKNOWN_PHYSICAL_CARD_OR_ZONE');
    check(!item.expected_legal||item.expected_after&&Array.isArray(item.expected_after.tokens),
      'POSITIVE_CASE_REQUIRES_EXPLICIT_RESULT');
  }
  return {cards:byId,counts};
}
function run(bundle,opts={}){
  const {cards}=validate(bundle,opts);
  const native=E.catalog({id:bundle.deck_id,name:'Fixture de papel',
    format_codes:[bundle.format]},bundle.cards.map(c=>({
      id:c.card_id,deck_id:bundle.deck_id,card_name:c.name,
      section:c.section,quantity:c.quantity
    })));
  const db={...facts,bindings:{...facts.bindings},facts:{...facts.facts},rules:bundle.rules};
  for(const card of native.cards)db.bindings[card.key]=cards.get(Number(card.key.split(':')[1])).cid;
  const reports=[];
  for(const item of bundle.cases){
    let state=E.create(native);
    state=E.patch(state,{turn:item.state_before.turn||1,
      phase:item.state_before.phase||'main1',
      active:item.state_before.active||'self'});
    for(const placed of item.state_before.placements){
      const card=native.cards.find(c=>Number(c.key.split(':')[1])===placed.card_id);
      check(card,'CASE_REFERENCES_SIDE_OR_RESERVE_NOT_IN_ACTIVE_DECK');
      state=E.place(state,{key:card.key,owner:placed.owner||'self',
        controller:placed.controller||'self',zone:placed.zone});
    }
    const untouched=JSON.stringify(state);
    const evaluated=R.actions(state,db);
    const actions=evaluated.actions.filter(x=>x.event?.ruleId===item.effect_id);
    check(JSON.stringify(state)===untouched,'CALCULATOR_MUTATED_SOURCE_STATE');
    let passed=false, reason='';
    if(!item.expected_legal){
      passed=actions.length===0;
      if(!passed)reason='Effect was recommended despite a documented negative precondition.';
    }else{
      for(const action of actions){
        const expected=item.expected_after.tokens;
        const match=expected.every(token=>{
          const actual=action.state.tokens.filter(t=>
            db.bindings[t.key]===token.cid&&t.zone===token.zone);
          return actual.length>=(token.min||1);
        });
        const lp=item.expected_after.lp||null;
        const lpMatches=!lp||Object.entries(lp).every(([side,value])=>action.state.lp[side]===value);
        if(match&&lpMatches){passed=true;break;}
      }
      if(!passed)reason=actions.length===0?'No legal effect action was produced.':
        'No action matched the expected physical card zones/LP.';
    }
    reports.push({case_id:item.case_id,effect_id:item.effect_id,passed,reason});
  }
  return {schema:bundle.schema,deck_id:bundle.deck_id,total:reports.length,
    passed:reports.filter(r=>r.passed).length,failed:reports.filter(r=>!r.passed).length,
    cases:reports};
}
if(require.main===module){
  const args=process.argv.slice(2),file=args.find(a=>!a.startsWith('--'));
  const given=args.find(a=>a.startsWith('--deck-id='));
  check(file&&given&&args.includes('--user-assigned'),
    'Provide an explicitly assigned deck: --deck-id=ID --user-assigned fixture.json');
  const data=JSON.parse(fs.readFileSync(path.resolve(file),'utf8'));
  const result=run(data,{deckId:Number(given.slice(10)),explicitlyAssigned:true});
  console.log(JSON.stringify(result,null,2));if(result.failed)process.exitCode=1;
}
module.exports=Object.freeze({validate,run});
