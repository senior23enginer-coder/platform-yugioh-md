'use strict';
// Contract shared by pilots 671/677: inventory, structured steps, official
// executable actors and visual provenance. Structural checks never certify play.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const app = path.resolve(__dirname,'..');
const root = path.join(app,'torneos_yugioh/assets/editorial/20261117-folder-delivery-v40');
const pilots=[
  {id:671,folder:'DECK_NUEVO_12_MEMENTOTLAN_DEMONIHERRERO',main:41,extra:15,side:14,routes:12,steps:69},
  {id:677,folder:'DECK_NUEVO_13_CRISTRON_HORUS',main:40,extra:15,side:15,routes:14,steps:60},
];
const args=process.argv.slice(2);
const strict=args.includes('--require-finished');
const selected=args.find(arg=>arg.startsWith('--deck='));
const requested=selected?Number(selected.split('=')[1]):null;
assert(requested===null||pilots.some(p=>p.id===requested),'Only canonical pilot IDs are supported');
const promptsArg=args.find(arg=>arg.startsWith('--prompts-root='));
const promptsRoot=promptsArg?path.resolve(promptsArg.slice('--prompts-root='.length)):null;
const report=[];
for(const pilot of pilots.filter(p=>requested===null||p.id===requested)){
  const deck=JSON.parse(fs.readFileSync(path.join(root,pilot.id+'.json'),'utf8'));
  assert.equal(deck.deck_id,pilot.id,'Wrong deck identity');
  assert.equal(deck.format,'tcg_advanced','Do not mix game formats');
  assert.equal(deck.cards.length,39,'Pilot identity count unexpectedly changed');
  const byCID=new Map();
  const quantities={main:0,extra:0,side:0,reserve:0};
  const rowIds=new Set();
  for(const card of deck.cards){
    assert(Number.isInteger(card.cid)&&card.cid>0&&!byCID.has(card.cid),'Duplicate/invalid card CID');
    byCID.set(card.cid,card);
    assert(card.name&&card.image&&card.full_image,'A04: card image identity missing');
    assert(Array.isArray(card.fields)&&card.fields.length&&Array.isArray(card.sources)&&card.sources.length,
      'A05: card must have a description and source');
    for(const slot of card.slots||[]){
      assert(Number.isInteger(slot.card_id)&&!rowIds.has(slot.card_id),'A03: native row reused');
      rowIds.add(slot.card_id);
      assert(Object.hasOwn(quantities,slot.section)&&Number.isInteger(slot.quantity)&&slot.quantity>0,
        'A03: invalid section or quantity');
      quantities[slot.section]+=slot.quantity;
    }
  }
  assert.equal(quantities.main,pilot.main);
  assert.equal(quantities.extra,pilot.extra);
  assert.equal(quantities.side,pilot.side);
  assert.equal(quantities.reserve,0);
  assert(deck.profile&&Object.keys(deck.profile).length>=4,'A01: missing deck profile');
  assert(Array.isArray(deck.routes)&&deck.routes.length===pilot.routes,'A09: lost route');
  assert(Array.isArray(deck.calculator?.rules),'A07: no portable effects');
  assert.equal(deck.calculator.deck_id,pilot.id);
  assert.equal(deck.calculator.format,deck.format);
  const actors=new Set();
  for(const rule of deck.calculator.rules){
    assert.equal(rule.deckId,pilot.id,'A07: cross-deck executable rule');
    assert.equal(rule.format,deck.format,'A07: cross-format executable rule');
    if(Number.isInteger(rule.actor?.cid)){
      assert(byCID.has(rule.actor.cid),'A07: actor absent from pilot inventory');
      actors.add(rule.actor.cid);
    }else if(Array.isArray(rule.actor?.cids)){
      assert(rule.actor.cids.every(cid=>byCID.has(cid)),'A07: procedure requires foreign materials');
    }
  }
  const routeIds=new Set();
  let steps=0,stepImages=0,endboards=0,spatialVerified=0;
  const photoCheck=(item)=>{
    if(Array.isArray(item)){for(const child of item)photoCheck(child);return;}
    if(!item||typeof item!=='object')return;
    if(Object.hasOwn(item,'cid')&&(Object.hasOwn(item,'image')||Object.hasOwn(item,'full_image'))){
      const card=byCID.get(Number(item.cid));
      assert(card,'A04/A10: photo from another deck: '+item.cid);
      assert.equal(item.image,card.image,'A04/A10: non-native image path');
      assert.equal(item.full_image,card.full_image,'A04/A10: non-native full-image path');
      stepImages++;
    }
    for(const [k,value] of Object.entries(item))if(k!=='image'&&k!=='full_image')photoCheck(value);
  };
  for(const route of deck.routes){
    assert(route.id.startsWith(pilot.id+':A09:')&&!routeIds.has(route.id),'A09: duplicate/foreign route');
    routeIds.add(route.id);
    assert(Array.isArray(route.steps)&&route.steps.length>0,'A09: route has no actions');
    assert(route.presentation_status==='ZONAS_DOCUMENTADAS_CASILLAS_ILUSTRATIVAS',
      'A10: illustrative placements must never be described as exact');
    assert(route.endboard?.source==='A09_SOURCE_DERIVED'&&route.endboard.spatial_verified===false,
      'A10: missing disclosed non-spatial source');
    assert(Array.isArray(route.endboard.variants)&&route.endboard.variants.length>0,
      'A10: expected an illustrated final-board representation');
    for(const variant of route.endboard.variants){
      assert(variant.spatial_verified===false,
        'A10: illustrative slots cannot claim physical exactitude');
      photoCheck(variant);
    }
    photoCheck(route.result_photos||[]);
    for(const step of route.steps){
      assert(typeof step.action==='string'&&step.action.trim(),'A09: missing action text');
      assert(Array.isArray(step.photos)&&step.photos.length>0,'A10: missing step photo for '+route.id);
      photoCheck(step.photos);
      steps++;
    }
    endboards++;
    if(route.endboard.spatial_verified===true)spatialVerified++;
  }
  assert.equal(steps,pilot.steps,'A09: documented route step count drift');
  const stages=[
    {id:'A01',check:'perfil y plan de juego',evidence:'DOCUMENTED'},
    {id:'A02',check:'identidad / importacion del deck',evidence:'STRUCTURE_CHECKED'},
    {id:'A03',check:'inventario, zonas, cantidades, Skills',evidence:'STRUCTURE_CHECKED'},
    {id:'A04',check:'identidad y referencias de imagen',evidence:'STRUCTURE_CHECKED'},
    {id:'A05',check:'fichas y fuentes de las 39 cartas',evidence:'DOCUMENTED'},
    {id:'A06',check:'motores de juego y relaciones verificadas',evidence:deck.engine_maps?.length?'DOCUMENTED':'MISSING_ENGINE_MAPS'},
    {id:'A07',check:'efectos ejecutables y pruebas por carta',evidence:actors.size===byCID.size?'ACTOR_IDS_COVERED_NOT_ALL_EFFECTS':'PARTIAL_ACTOR_COVERAGE'},
    {id:'A08',check:'variantes y decisiones de juego',evidence:deck.decisions?.length?'DOCUMENTED_NOT_REPLAYED':'NOT_PRESENT'},
    {id:'A09',check:'ejecucion legal de rutas y respuesta rival',evidence:'REPLAYS_NOT_CERTIFIED'},
    {id:'A10',check:'pasos con fotos y campos finales',evidence:'ILLUSTRATIVE_NOT_SPATIAL_CERTIFIED'},
  ];
  let sourceParity='NOT_CHECKED';
  if(promptsRoot){
    const file=path.join(promptsRoot,'01_INFORMACION_PARA_IMPORTAR','decks',
      pilot.folder,'datos','stationx-deck.json');
    const pkg=JSON.parse(fs.readFileSync(file,'utf8'));
    assert.equal(pkg.schema,'station-x-deck-package/1');
    assert.equal(pkg.package_root,pilot.folder);
    assert.equal(pkg.deck_id,pilot.id);
    assert.equal(pkg.record.deck_id,pilot.id);
    assert.deepEqual(pkg.record.calculator,deck.calculator,
      'A07: canonical prompt and platform effects differ');
    assert.deepEqual(pkg.record.routes.map(r=>r.id),deck.routes.map(r=>r.id),
      'A09: canonical source lost route IDs');
    const sourceRows=pkg.inventory.map(r=>[r.id,r.section,r.quantity].join(':')).sort();
    const localRows=deck.cards.flatMap(c=>c.slots.map(s=>[s.card_id,s.section,s.quantity].join(':'))).sort();
    assert.deepEqual(sourceRows,localRows,'A03: prompt/native card quantities or IDs differ');
    for(let i=0;i<deck.routes.length;i++){
      const a=deck.routes[i],b=pkg.record.routes[i];
      assert.deepEqual(a.steps.map(s=>s.action),b.steps.map(s=>s.action),
        'A09: prompt and platform action sequence differ: '+a.id);
      assert.deepEqual(a.steps.map(s=>s.photos.map(p=>p.cid)),
        b.steps.map(s=>s.photos.map(p=>p.cid)),
        'A10: source and platform step photographs differ: '+a.id);
      assert.equal(a.endboard.variants.length,b.endboard.variants.length,
        'A10: lost source board alternatives: '+a.id);
    }
    sourceParity='CHECKED';
  }
  report.push({
    deck_id:pilot.id,
    inventory:quantities,
    card_identities:byCID.size,
    portable_effect_variants:deck.calculator.rules.length,
    distinct_effect_ids:new Set(deck.calculator.rules.map(r=>r.id)).size,
    actor_identities:actors.size,
    actors_without_executable_rules:deck.cards.filter(c=>!actors.has(c.cid)).map(c=>({cid:c.cid,name:c.name})),
    documented_routes:deck.routes.length,documented_steps:steps,
    photos_checked:stepImages,illustrated_endboards:endboards,exact_endboards:spatialVerified,
    certified_replays:0,
    source_parity:sourceParity,stages,certified:false
  });
}
const result={schema:'station-x-pilot-analysis-gate/1',structure_ok:true,certified:false,decks:report};
console.log(JSON.stringify(result));
if(strict)throw Error('No completar A01-A10: no hay replays A09 certificados ni tableros espaciales verificados');
