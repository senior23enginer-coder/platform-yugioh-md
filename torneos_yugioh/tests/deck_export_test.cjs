const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const E = require('../assets/modules/deck-export/export.js');
const data = {
  allDecks: [{id:1,name:'Volcanic estación',format_codes:['tcg_advanced']},{id:2,name:'Rush',format_codes:['rush_duel']},{id:3,name:'GOAT',format_codes:['goat']}],
  cards: [
    {deck_id:1,card_name:'Volcanic Trooper',ygoprodeck_id:123,quantity:2,section:'main'},
    {deck_id:1,card_name:'Soldado Volcánico / Volcanic Trooper',quantity:1,section:'reserve'},
    {deck_id:1,card_name:'Side card',quantity:3,section:'side',name_en:'Side card',name_es:'Carta de Side'},
    {deck_id:2,card_name:'未翻訳',quantity:1,section:'extra'},
    {deck_id:2,card_name:'Skill',quantity:1,section:'skill'},
  ]
};
const record = {cards:[{name:'Soldado Volcánico / Volcanic Trooper',quantity:99,section:'side'}]};
const records = new Map([[1,record]]);
const result = E.build(data,data.allDecks,records);
assert(result.text.startsWith('\ufeff'));
assert(result.text.includes('2x | EN: Volcanic Trooper | ES: Soldado Volcánico'));
assert(!result.text.includes('99x'));
assert(result.text.includes('Reserva — 1 copias'));
assert(result.text.includes('Side Deck — 3 copias'));
assert(result.text.includes('extra') === false);
assert(result.text.includes('Extra Deck — 1 copias'));
assert(result.text.includes('skill — 1 copias'));
assert(result.text.includes('未翻訳'));
assert(result.text.includes('goat'));
assert.equal(result.missing,2);
assert.equal(E.names({card_name:'D/D/D'},null).es,'');
assert.equal(E.filename('Volcanic estación'),'Volcanic estación.txt');
assert.equal(E.filename('Deck / a:b'),'Deck _ a_b.txt');
assert(!E.build(data,[data.allDecks[0]],records).text.includes('DECK: Rush'));
assert.equal(E.names({card_name:'Volcanic Trooper'}, {cards:[{name:'A / Volcanic Trooper'},{name:'B / Volcanic Trooper'}]}).es,'');
const original=JSON.stringify(data);
E.build(data,data.allDecks,records);
assert.equal(JSON.stringify(data),original);

(async()=>{
  const {chromium}=require('playwright');
  const browser=await chromium.launch({headless:true});
  try {
    const page=await browser.newPage({acceptDownloads:true});
    await page.setContent('<button data-deck-download="1">Importar</button><button data-deck-download-all>Importar listado</button><p data-deck-download-status></p><div hidden data-deck-list-id="2"></div>');
    await page.evaluate(({data,record})=>{window.DML_DATA=data;window.YgoDeckDeliveries={load:async id=>Number(id)===1?record:null};},{data,record});
    await page.addScriptTag({path:path.resolve(__dirname,'../assets/modules/deck-export/export.js')});
    async function download(selector) {
      const event=page.waitForEvent('download');
      await page.locator(selector).click();
      const file=await event;
      return {name:file.suggestedFilename(),text:fs.readFileSync(await file.path(),'utf8')};
    }
    const single=await download('[data-deck-download]');
    assert.equal(single.name,'Volcanic estación.txt');
    assert(single.text.includes('DECK: Volcanic estación'));
    assert(!single.text.includes('DECK: Rush'));
    const all=await download('[data-deck-download-all]');
    assert.equal(all.name,'Station_X_Todos_los_decks.txt');
    assert(all.text.includes('DECK: Rush')&&all.text.includes('DECK: GOAT'));
    assert(all.text.includes('未翻訳'));
    assert.equal(await page.locator('[data-deck-download-all]').isEnabled(),true);
    await page.evaluate(()=>{window.YgoDeckDeliveries.load=async()=>{throw Error('Offline');};});
    const fallback=await download('[data-deck-download]');
    assert(fallback.text.includes('No documentado'));
    assert((await page.locator('[data-deck-download-status]').textContent()).includes('no estuvieron disponibles'));
    console.log(JSON.stringify({ok:true,actualTxtDownloads:3,allFormats:true,filterIndependent:true,noListWrites:true}));
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
