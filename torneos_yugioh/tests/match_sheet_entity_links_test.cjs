const {chromium}=require('playwright');
const assert=require('node:assert/strict'),path=require('node:path');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage({viewport:{width:1440,height:900}});
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.setContent(`<div id="matchSheetModal" class="modal show" aria-modal="true"><div data-match-sheet-identity><dl class="calendar-event-identity"><div><dt>Liga</dt><dd></dd></div></dl></div><section class="match-sheet-duel"><article><strong data-match-sheet-a></strong></article><b>VS</b><article><strong data-match-sheet-b></strong></article></section></div>`);
  await page.addStyleTag({path:path.join(__dirname,'../assets/modules/match-sheet/entity-links.css')});
  await page.addScriptTag({path:path.join(__dirname,'../assets/modules/match-sheet/entity-links.js')});
  await page.evaluate(()=>{
   window.order=[];window.opened=[];
   window.bootstrap={Modal:{getOrCreateInstance(modal){return {hide(){order.push('hide');setTimeout(()=>{modal.classList.remove('show');modal.dispatchEvent(new Event('hidden.bs.modal'));},10);}};}}};
   window.renderFixture=(type='league',extra=[])=>{
    const modal=document.querySelector('#matchSheetModal');modal.classList.add('show');
    window.YgoMatchSheetLinks.render({modal,
     match:{competition_id:10,a:'Old display label',b:'Crystron',deck_a_id:671,deck_b_id:677,team_a_extra:extra},
     decks:[{id:671,name:'Memento'},{id:677,name:'Crystron'},{id:678,name:'Crystron'},{id:660,name:'Purrely'}],
     competitions:[{id:10,type,name:type==='league'?'Liga Apex':'Evento '+type}],
     onDeck(id){order.push('deck');opened.push(['deck',id,modal.classList.contains('show')]);},
     onEvent(id){order.push('event');opened.push(['event',id,modal.classList.contains('show')]);}});
   };
  });
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:900});
   for(const type of ['league','cup','qualifier','world','custom']){
    for(const [kind,id] of [['deck',671],['deck',677],['event',10]]){
     await page.evaluate(type=>renderFixture(type),type);
     const button=page.locator(`[data-match-sheet-entity="${kind}"][data-entity-id="${id}"]`);
     assert(await button.isVisible());
     const count=await page.evaluate(()=>opened.length);
     await button.focus();await page.keyboard.press('Enter');
     await page.waitForFunction(count=>opened.length===count+1,count);
     const result=await page.evaluate(()=>opened.at(-1));
     assert.deepEqual(result,[kind,id,false]);
     assert.deepEqual(await page.evaluate(()=>order.slice(-2)),['hide',kind]);
    }
   }
  }
  await page.evaluate(()=>renderFixture('cup',['Purrely','Unknown deck']));
  assert.equal(await page.locator('[data-match-sheet-a] button').count(),2);
  assert.equal(await page.locator('[data-match-sheet-a]').innerText(),'Memento + Purrely + Unknown deck');
  assert.equal(await page.locator('[data-entity-id="678"]').count(),0);
  await page.evaluate(()=>renderFixture());await page.locator('[data-match-sheet-a]').click();
  await page.waitForFunction(()=>opened.at(-1)?.[0]==='deck'&&opened.at(-1)?.[1]===671);
  assert.deepEqual(errors,[]);
  console.log('OK: both deck cards, event identity, real IDs, duplicate names, team decks, keyboard, close-before-navigation and all event types at desktop/mobile widths.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
