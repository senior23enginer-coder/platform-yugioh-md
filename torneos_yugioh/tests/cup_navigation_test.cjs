const {chromium}=require('playwright'),assert=require('node:assert/strict'),path=require('node:path'),fs=require('node:fs');
const app=path.resolve(__dirname,'..'),out=path.resolve(app,'../outputs/cup-open-20261108');
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<div class="modal show"><main></main></div>');
 for(const file of ['assets/modules/cup-bracket/navigation.css'])await page.addStyleTag({path:path.join(app,file)});
 await page.addScriptTag({path:path.join(app,'assets/cup-tree.js')});await page.addScriptTag({path:path.join(app,'assets/modules/cup-bracket/navigation.js')});
 await page.evaluate(()=>{
  const matches=[{id:1,ronda:1,mesa:1,a:'A',b:'B',estado:'PENDIENTE'},
   {id:2,ronda:1,mesa:2,a:'Snake-Eyes',b:'Dracotail',estado:'FINALIZADA',result:'B',score:'0-2'},
   {id:3,ronda:1,mesa:3,a:'DoomZ',b:'Tearlaments',estado:'FINALIZADA',result:'A',score:'2-1'},
   {id:4,ronda:1,mesa:4,a:'C',b:'D',estado:'FINALIZADA',result:'A',score:'2-0'}];
  const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  document.querySelector('main').innerHTML=StationXCupTree.render(matches,{id:9,name:'Copa Leyendas'}, {},[],esc);
  window.openedMatches=[];YgoCupBracketNavigation.init(id=>openedMatches.push(id));YgoCupBracketNavigation.init(id=>openedMatches.push(id));
 });
 assert.equal(await page.locator('[data-cup-preview]').count(),1);
 assert.equal(await page.locator('[data-cup-tree-node="r2m1"]').evaluate(n=>n.tagName),'ARTICLE');
 assert.equal(await page.locator('[data-cup-tree-node="r2m2"]').evaluate(n=>n.tagName),'BUTTON');
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:900});const ready=page.locator('[data-cup-tree-node="r2m2"]');
  await ready.focus();await page.keyboard.press('Enter');
  assert(await page.locator('.cup-match-preview').evaluate(n=>n.open));
  assert(await page.locator('.cup-match-preview').evaluate(n=>Boolean(n.closest('.modal.show'))));
  assert((await page.locator('.cup-match-preview').innerText()).includes('Dracotail vs DoomZ'));
  assert((await page.locator('.cup-match-preview').innerText()).includes('todavía no tiene fecha ni un combate registrado'));
  assert.equal(await page.locator('.cup-match-preview [data-match-sheet-capture]').count(),0);
  assert(await page.locator('.cup-match-preview').evaluate(n=>n.scrollWidth<=n.clientWidth));
  await page.locator('.cup-match-preview').screenshot({path:path.join(out,`preview-${width}.png`)});
  await page.keyboard.press('Escape');assert(!(await page.locator('.cup-match-preview').evaluate(n=>n.open)));
  assert(await ready.evaluate(n=>n===document.activeElement));
 }
 await page.locator('[data-schedule-open-match="1"]').click();await page.locator('[data-schedule-open-match="2"]').click();
 assert.deepEqual(await page.evaluate(()=>openedMatches),['1','2']);assert.deepEqual(errors,[]);
 fs.writeFileSync(path.join(out,'tests.json'),JSON.stringify({ok:true,widths:[1440,390],persistedMatchesOpenOnce:true,readyProjectionOpens:true,unknownProjectionNotInteractive:true,writes:0},null,2));
 console.log('OK: pending/completed matches, ready projections, unresolved projections, keyboard, focus and mobile/desktop.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
