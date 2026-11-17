const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
(async()=>{
 const state=JSON.parse(fs.readFileSync(path.join(__dirname,'../assets/editorial/strategy-content-state.json')));assert.equal(state.cleared_deck_ids.length,67);
 // The archived reset remains valid independently of later approved publications.
 delete state.checked_studies;
 const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext(),page=await context.newPage();
  await context.route('https://fixture.test/**',route=>route.request().url().includes('strategy-content-state.json')?route.fulfill({json:state}):route.fulfill({contentType:'text/html',body:'<button data-deck-category-open="strategy"><small>Perfil documentado</small></button><section class="deck-strategy-profile"><div class="panel-head">Old header</div><div class="deck-strategy-identity">Old strategy</div><div class="deck-strategy-content">Old plan</div><details class="deck-strategy-editor"><form><input name="deck_analysis_deck_id" type="hidden" value="665"><input name="deck_analysis_play_style" value="Old style"><textarea>Old explanation</textarea><span data-deck-analysis-editor-state>Old state</span></form></details></section>'}));
  await page.goto('https://fixture.test/');await page.evaluate(()=>{window.originalCalls=0;window.YgoDeckStrategy={render:()=>window.originalCalls++};});
  await page.addScriptTag({path:path.join(__dirname,'../assets/modules/deck-guide/content-state.js')});
  for(const id of state.cleared_deck_ids){
   await page.evaluate(({id,revision})=>YgoDeckStrategy.render({id},{revision,play_style:'Old active text'},{}),{id,revision:state.cleared_profile_revisions[id]});
   await page.waitForFunction(()=>document.querySelector('.deck-strategy-profile').style.visibility==='');
   assert.equal(await page.locator('.deck-strategy-identity').isVisible(),false);assert.equal(await page.locator('.deck-strategy-content').isVisible(),false);
   assert.equal(await page.locator('[data-deck-category-open="strategy"] small').textContent(),'Sin información');
   assert.equal(await page.locator('input[name="deck_analysis_play_style"]').inputValue(),'');
  }
  assert.equal(await page.evaluate(()=>window.originalCalls),0);
  assert.equal(await page.locator('input[name="deck_analysis_deck_id"]').inputValue(),'665');
  await page.evaluate(({id,revision})=>YgoDeckStrategy.render({id},{revision:revision+1,play_style:'New manual content'},{}),{id:665,revision:state.cleared_profile_revisions[665]});await page.waitForFunction(()=>document.querySelector('.deck-strategy-profile').style.visibility==='');
  assert(await page.locator('.deck-strategy-identity').isVisible());assert.equal(await page.evaluate(()=>window.originalCalls),0,'Do not reactivate old deliveries for manual edits');
  await page.evaluate(()=>YgoDeckStrategy.render({id:999999},null,{}));await page.waitForFunction(()=>window.originalCalls===1);
  console.log(JSON.stringify({ok:true,clearedDecks:67,oldGuideNeverLoaded:true,editorBlank:true,nativeIdPreserved:true,newManualProfileSupported:true,otherDecksUnaffected:true}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
