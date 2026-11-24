const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const app=path.resolve(__dirname,'..');
const memento=JSON.parse(fs.readFileSync(path.join(app,'assets/editorial/20261117-folder-delivery-v40/671.json'),'utf8'));
const sourceRoot=process.argv[2]||process.env.YGO_PROMPTS_ROOT||'';
const livePackage=sourceRoot?JSON.parse(fs.readFileSync(path.join(sourceRoot,'01_INFORMACION_PARA_IMPORTAR/decks/DECK_NUEVO_12_MEMENTOTLAN_DEMONIHERRERO/datos/stationx-deck.json'),'utf8')):null;
const crystronPackage=sourceRoot?JSON.parse(fs.readFileSync(path.join(sourceRoot,'01_INFORMACION_PARA_IMPORTAR/decks/DECK_NUEVO_13_CRISTRON_HORUS/datos/stationx-deck.json'),'utf8')):null;
if(crystronPackage)assert.equal(crystronPackage.deck_id,677);
if(livePackage)assert.equal(livePackage.deck_id,671,'Only assigned Memento deck may be tested');
const one=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4vUAAAAASUVORK5CYII=','base64');
(async()=>{
  const browser=await chromium.launch({headless:true});
  try{
    for(const width of [390,1280]){
      const page=await browser.newPage({viewport:{width,height:920}});
      const errors=[];
      page.on('pageerror',e=>errors.push(e.message));
      await page.route('**/*',route=>{
        const u=new URL(route.request().url());
        if(u.pathname==='/')return route.fulfill({status:200,contentType:'text/html',body:'<html><body><main id="test-guide"></main></body></html>'});
        if(/\.(png|jpe?g|webp)$/i.test(u.pathname))return route.fulfill({status:200,contentType:'image/png',body:one});
        return route.fulfill({status:404,body:''});
      });
      await page.goto('http://stationx.test/');
      await page.addScriptTag({content:'window.YgoFunctionalRole={project:data=>data,connectionHelp:{}};window.YgoStudyPhotoViewer={create:()=>({open(){},dispose(){}})};'});
      await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide/presentation-contract.js')});
      await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide/endboard.js')});
      await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide/delivery-extensions.js')});
      await page.addScriptTag({path:path.join(app,'assets/modules/deck-guide/curated-study.js')});
      await page.evaluate(data=>window.YgoCuratedStudy.render(
        document.querySelector('#test-guide'),{id:671,name:'Memento'},data,{cards:data.cards.length,sha256:'fixture'}
      ),memento);
      const style=await page.locator('.curated-profile dl').innerText();
      assert(!style.includes('Dictamen editorial'),'Visible playstyle must be readable, not an audit');
      assert(!style.includes('Consultar el plan'),'Historical placeholder must stay hidden');
      assert(!style.includes('estudio individual'),'Archetype field cannot display research iteration metadata');
      assert.equal(await page.locator('.curated-full-profile').count(),0,'Historical audit must not render as a player plan');
      await page.locator('[data-curated-tab="routes"]').click();
      await page.locator('.curated-route-card').first().click();
      const firstStep=page.locator('.curated-steps>li').first();
      assert(await firstStep.locator('img').count()>0,'Memento Step 1 must have card photos');
      const names=await firstStep.locator('img').evaluateAll(imgs=>imgs.map(img=>img.getAttribute('alt')));
      assert(names.some(n=>n.includes('Angbruja')),'Real Memento card photo is bound to the action');
      assert((await firstStep.innerText()).includes('Invoca Normal Angbruja'),'Complete source action visible');
      assert((await page.locator('.curated-result-full').innerText()).includes('Rey Creación'),'Full end result is visible');
      assert(await page.locator('.eb-layout-note').count()>0,'Nonspatial final-board disclosure must remain visible');
      assert(/ilustrativ|no verificad/i.test(await page.locator('.eb-layout-note').allInnerTexts().then(items=>items.join(' '))),
        'Illustrative slots must not be represented as certified physical positions');
      assert(await page.locator('.curated-final-board img').count()>0,'Result must have card evidence');
      // The A01-v1 reader must show all six new profile fields without the
      // historical Estilo de juego fallback; this is a synthetic layout only.
      const synthetic={...memento,name:'Nexo Umbral',profile:{
        'Arquetipo':'Nexo Umbral','Tipo de estrategia':'Combo de Fusión y Cementerio',
        'Estilo breve':'Motor sintético de Fusión y Cementerio para preparar recursos.',
        'Resumen para ficha':'Abre con recursos controlados, utiliza materiales y conserva opciones.',
        'Cómo se juega':'Primero construye recursos y conserva respuestas. Segundo comprueba amenazas y materiales. En turnos posteriores recupera únicamente recursos disponibles.',
        'Qué vigilar':'No asumir materiales ni rutas ausentes del inventario.'
      }};
      await page.evaluate(({data,viewportWidth})=>window.YgoCuratedStudy.render(
        document.querySelector('#test-guide'),{id:671,name:data.name},data,
        {cards:data.cards.length,sha256:'synthetic-a01-'+viewportWidth}
      ),{data:synthetic,viewportWidth:width});
      const a01Intro=await page.locator('.curated-profile').innerText();
      assert(a01Intro.includes('Nexo Umbral'),'A01 archetype missing in new reader');
      assert(a01Intro.includes('Estilo breve'),'A01 six-field record needs a visible style label');
      assert(a01Intro.includes('Motor sintético de Fusión'),'A01 style not rendered');
      assert((await page.locator('[data-a01-summary]').innerText()).includes('Abre con recursos controlados'),
        'A01 summary not shown in correct UI field');
      assert(a01Intro.includes('No asumir materiales'),'A01 warnings not displayed');
      await page.locator('.curated-full-profile summary').click();
      assert((await page.locator('.curated-full-profile').innerText()).includes('Primero construye recursos'),
        'A01 full gameplan must remain accessible');

      // New certified A01 can explain strategic choices without selecting first Main cards.
      const guide={
        schema:'station-x-player-guide/1',
        featured_cards:[{cid:19364,role:'Iniciadora y búsqueda',reason:'La búsqueda conecta con la siguiente pieza del motor.',when_to_use:'Cuando la búsqueda y las piezas siguen disponibles.',related_cids:[19904]}],
        phases:['first','second','mid','late'].map((id,i)=>({id,title:'Fase '+i,plan:'Desarrollar recursos con cartas que existen, respetando la ventana y la respuesta del rival.',preconditions:'Mano, campo y recursos comprobados.',risks:'Las negaciones pueden cortar la ruta actual.',related_route_ids:[]}))
      };
      const certified={...synthetic,player_guide:guide};
      await page.evaluate(({data,viewportWidth})=>window.YgoCuratedStudy.render(
        document.querySelector('#test-guide'),{id:671,name:data.name},data,
        {cards:data.cards.length,sha256:'certified-a01-'+viewportWidth}
      ),{data:certified,viewportWidth:width});
      assert.equal(await page.locator('.curated-featured-card').count(),1,'Only source-backed featured cards should show');
      assert((await page.locator('.curated-featured-card').innerText()).includes('Angbruja'),'Selected card should be identified by real name');
      assert.equal(await page.locator('.curated-game-phase').count(),4,'Full plan should expose all four phases');
      assert(!(await page.locator('[data-curated-content="intro"]').innerText()).includes('PARCIAL_AVANZADO'),'Editorial status must not leak into intro');

      // Production-shaped legacy fields must never be projected, even in closed details.
      // Synthetic strategy tests the renderer only, never semantic deck acceptance.
      const strategySections=['first','second','mid','late','weak_hand','disruption','win_condition','common_errors'].map((id,i)=>({
        id,title:'Lección de prueba '+i,objective:'Objetivo de prueba '+i,situation:'Situación de prueba '+i,
        card_cids:[19364],actions:['Primera acción de prueba '+i,'Segunda acción de prueba '+i],
        expected_result:'Resultado de prueba '+i,resources_to_keep:'Recursos de prueba '+i,alternative:'Alternativa de prueba '+i,related_route_ids:[memento.routes[0].id]
      }));
      const legacyPolluted={...certified,delivery_progress:[{nombre:'Cobertura documental',porcentaje_cobertura_documental:100}],
        rule_scenarios:[{situacion:'Control editorial',prueba_de_legalidad:'No escribir instrucciones al jugador'}],
        general_rules:[{detalle:'Regla general duplicada'}],format_context:{deckSpecificControl:['No escribir que se ha comprobado']},
        delivery_dossier:{A01_identidad:{audit:'Plan de juego completo, condiciones y evaluación editorial'}},
        player_guide:{...guide,strategy:{schema:'station-x-player-strategy/1',sections:strategySections}}};
      await page.evaluate(({data,viewportWidth})=>window.YgoCuratedStudy.render(
        document.querySelector('#test-guide'),{id:671,name:data.name},data,
        {cards:data.cards.length,sha256:'structured-a01-'+viewportWidth}
      ),{data:legacyPolluted,viewportWidth:width});
      const overview=page.locator('[data-curated-content="intro"]');
      assert.equal(await overview.locator('[data-player-section]').count(),8);
      assert.equal(await overview.locator('.curated-full-profile').count(),0,'Structured plan must replace old prose');
      assert.equal(await overview.locator('[data-player-section] ol li').count(),16);
      assert.equal(await overview.locator('[data-player-section] img').count(),8);
      assert.equal(await overview.locator('[data-player-section] [data-open-route]').count(),8);
      assert(!/Cobertura documental|Control editorial|Regla general duplicada|evaluación editorial|No escribir/i.test(await overview.textContent()),'Hidden or expanded audit content leaked into real DOM');
      assert.equal(await page.locator('[data-curated-editorial]').count(),0,'Audit filter must not appear');

      if(livePackage){
        const actual=livePackage.record;
        assert.equal(actual.a01_profile_contract,'station-x-a01-profile/1');
        await page.evaluate(({data,viewportWidth})=>window.YgoCuratedStudy.render(
          document.querySelector('#test-guide'),{id:671,name:'Memento'},data,
          {cards:data.cards.length,sha256:'actual-memento-a01-t5-'+viewportWidth}
        ),{data:actual,viewportWidth:width});
        const intro=page.locator('[data-curated-content="intro"]');
        const visible=await intro.innerText();
        const publicProfile=await page.locator('.curated-profile').innerText();
        for(const field of ['Arquetipo','Tipo de estrategia','Estilo breve']){
          assert(publicProfile.includes(field),'Missing player field in browser: '+field);
        }
        assert(publicProfile.includes(actual.profile['Resumen para ficha']),'Actual summary differs from GPT JSON');
        assert(publicProfile.includes(actual.profile['Qué vigilar']),'Actual risks differ from GPT JSON');
        assert.equal(await page.locator('.curated-featured-card').count(),4,'Actual Memento should select 4 grounded cards');
        if(actual.player_guide.strategy){
          assert.equal(await intro.locator('[data-player-section]').count(),8,'Actual Memento requires eight strategy lessons');
          assert.equal(await intro.locator('.curated-full-profile').count(),0,'Structured lessons replace legacy prose');
        }else{
          assert.equal(await page.locator('.curated-game-phase').count(),4,'Actual Memento needs four full game phases');
          const titles=await page.locator('.curated-game-phase h5').allInnerTexts();
          assert.deepEqual(titles,actual.player_guide.phases.map(p=>p.title),'Different phase titles rendered');
        }
        for(const featured of actual.player_guide.featured_cards){
          const card=actual.cards.find(c=>c.cid===featured.cid);
          assert(card, 'Featured CID must belong to Memento');
          const content=await page.locator('.curated-featured-card').filter({hasText:card.name}).innerText();
          assert(content.includes(featured.reason)&&content.includes(featured.when_to_use),
            'Player must see why and when to use '+card.name);
        }
        assert(!/Dictamen editorial|PARCIAL_AVANZADO|Cat[aá]logo completo|consultar el plan|CID\s*\d+|\b[CV]\d{2}\b/i.test(visible),
          'A01 visible guide leaked internal audit tokens');
        assert.equal(await intro.locator('a[href*="guide.html"]').count(),0,'External historical HTML guide should not be linked');
        assert.equal(await intro.locator('[data-rule-scenarios] details').filter({hasText:'Reglas generales y fuentes'}).count(),0,
          'Empty general rules section must not be visible');
        if(!actual.player_guide.strategy){
          await page.locator('.curated-full-profile summary').click();
          assert((await page.locator('.curated-full-profile').innerText()).includes(actual.profile['Cómo se juega']),'Full legacy plan accessible');
        }
        const evidence=path.join(app,'../outputs/a01-t5-memento-671');
        fs.mkdirSync(evidence,{recursive:true});
        await page.screenshot({path:path.join(evidence,'guide-'+width+'.png')});
        if(width===1280)fs.writeFileSync(path.join(evidence,'ui-evidence.json'),JSON.stringify({
          ok:true,deck_id:671,source:'real GPT base structured Memento package',
          viewports:[390,1280],profile_fields:6,featured_cards:4,phases:4,
          prohibited_public_audit_tokens:0,external_guide_links:0,
          fictional_featured_cards:0,nonproduction:true
        },null,2));
      }
      if(crystronPackage){
        const actual=crystronPackage.record;
        assert.equal(actual.a01_profile_contract,'station-x-a01-profile/1');
        await page.evaluate(({data,viewportWidth})=>window.YgoCuratedStudy.render(
          document.querySelector('#test-guide'),{id:677,name:'Crystron'},data,
          {cards:data.cards.length,sha256:'actual-crystron-a01-t5-'+viewportWidth}
        ),{data:actual,viewportWidth:width});
        const intro=page.locator('[data-curated-content="intro"]');
        const visible=await intro.innerText();
        const publicProfile=await page.locator('.curated-profile').innerText();
        for(const field of ['Arquetipo','Tipo de estrategia','Estilo breve']){
          assert(publicProfile.includes(field),'Missing player field in browser: '+field);
        }
        assert(publicProfile.includes(actual.profile['Resumen para ficha']),'Actual summary differs from GPT JSON');
        assert(publicProfile.includes(actual.profile['Qué vigilar']),'Actual risks differ from GPT JSON');
        assert.equal(await page.locator('.curated-featured-card').count(),4,'Actual Crystron should select 4 grounded cards');
        if(actual.player_guide.strategy){
          assert.equal(await intro.locator('[data-player-section]').count(),8,'Actual Crystron requires eight strategy lessons');
          assert.equal(await intro.locator('.curated-full-profile').count(),0,'Structured lessons replace legacy prose');
        }else{
          assert.equal(await page.locator('.curated-game-phase').count(),4,'Actual Crystron needs four full game phases');
          const titles=await page.locator('.curated-game-phase h5').allInnerTexts();
          assert.deepEqual(titles,actual.player_guide.phases.map(p=>p.title),'Different phase titles rendered');
        }
        for(const featured of actual.player_guide.featured_cards){
          const card=actual.cards.find(c=>c.cid===featured.cid);
          assert(card, 'Featured CID must belong to Crystron');
          const content=await page.locator('.curated-featured-card').filter({hasText:card.name}).innerText();
          assert(content.includes(featured.reason)&&content.includes(featured.when_to_use),
            'Player must see why and when to use '+card.name);
        }
        assert(!/Dictamen editorial|PARCIAL_AVANZADO|Cat[aá]logo completo|consultar el plan|CID\s*\d+|\b[CV]\d{2}\b/i.test(visible),
          'A01 visible guide leaked internal audit tokens');
        assert.equal(await intro.locator('a[href*="guide.html"]').count(),0,'External historical HTML guide should not be linked');
        assert.equal(await intro.locator('[data-rule-scenarios] details').filter({hasText:'Reglas generales y fuentes'}).count(),0,
          'Empty general rules section must not be visible');
        if(!actual.player_guide.strategy){
          await page.locator('.curated-full-profile summary').click();
          assert((await page.locator('.curated-full-profile').innerText()).includes(actual.profile['Cómo se juega']),'Full actual A01 plan must be accessible');
        }
        const evidence=path.join(app,'../outputs/a01-t5-crystron-677');
        fs.mkdirSync(evidence,{recursive:true});
        await page.screenshot({path:path.join(evidence,'guide-'+width+'.png')});
        if(width===1280)fs.writeFileSync(path.join(evidence,'ui-evidence.json'),JSON.stringify({
          ok:true,deck_id:677,source:'real GPT base structured Crystron package',
          viewports:[390,1280],profile_fields:6,featured_cards:4,phases:4,
          prohibited_public_audit_tokens:0,external_guide_links:0,
          fictional_featured_cards:0,nonproduction:true
        },null,2));
      }
      assert.deepEqual(errors,[],'Browser errors in guide on '+width+'px');
      await page.close();
    }
    console.log(JSON.stringify({ok:true,deck:671,viewports:[390,1280],realMementoA01T5:!!livePackage,stepPhoto:'Angbruja',result:'Rey Creación',spatialClaim:'documented only',fullSourceAccessible:true}));
  }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
