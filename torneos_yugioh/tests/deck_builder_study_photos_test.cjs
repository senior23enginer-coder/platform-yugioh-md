const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

const app = path.resolve(__dirname, '..');
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4vUAAAAASUVORK5CYII=', 'base64');
const records = {
  671: {
    deck_id: 671,
    cards: [
      { name: 'Angbruja', image: './assets/editorial/deck-data-671/angbruja.png', full_image: './assets/editorial/deck-data-671/angbruja.png', slots: [{ card_id: 499, section: 'main', quantity: 3 }] },
      { name: 'Hoja Oscura', image: './assets/editorial/deck-data-671/hoja.png', full_image: './assets/editorial/deck-data-671/hoja.png', slots: [{ card_id: 500, section: 'main', quantity: 3 }] }
    ]
  },
  677: {
    deck_id: 677,
    cards: [{ name: 'Smiger', image: './assets/editorial/deck-data-677/smiger.jpg', slots: [{ card_id: 561, section: 'main', quantity: 2 }] }]
  },
  999: {
    deck_id: 999,
    cards: [{ name: 'Hostil', image: 'https://evil.example/x.png', slots: [{ card_id: 300, section: 'main', quantity: 1 }] }]
  },
  1000: { deck_id: 999, cards: [{ name: 'Otro deck', image: './assets/editorial/stolen.png', slots: [{ card_id: 301, section: 'main', quantity: 1 }] }] }
};
const calls = [];
async function main() {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      calls.push(url.pathname + url.search);
      if (url.pathname === '/') return route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body><div data-card-gallery></div></body></html>' });
      if (url.pathname.endsWith('/strategy-content-state.json')) {
        return route.fulfill({
          status: 200, contentType: 'application/json',
          body: JSON.stringify({ checked_studies: Object.fromEntries(Object.keys(records).map(id => [id, { path: './assets/editorial/studies/' + id + '.json' }])) })
        });
      }
      const match = url.pathname.match(/\/assets\/editorial\/studies\/(\d+)\.json$/);
      if (match) return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(records[match[1]]) });
      if (url.pathname.match(/\/assets\/editorial\/deck-data-\d+\/.+\.(png|jpg)$/)) return route.fulfill({ status: 200, contentType: 'image/png', body: png });
      // Catalog assets deliberately unavailable: the fallback must still show the card.
      if (url.searchParams.get('api') === 'deck_builder') return route.fulfill({ status: 404, body: '' });
      return route.fulfill({ status: 404, body: '' });
    });
    await page.goto('http://stationx.test/');
    await page.addScriptTag({ path: path.join(app, 'assets/deck-builder.js') });
    await page.addScriptTag({ path: path.join(app, 'assets/card-inspector.js') });
    const mount = async (id, rows) => page.evaluate(({id,rows}) => {
      window.YgoDeckBuilder.mount(document.querySelector('[data-card-gallery]'), { id, name: 'Deck ' + id }, { cards: rows, canEditEvents: false });
    }, {id,rows});
    await mount(671, [
      { id: 499, deck_id: 671, card_name: 'Angbruja', quantity: 3, section: 'main', ygoprodeck_id: null },
      { id: 500, deck_id: 671, card_name: 'Hoja Oscura', quantity: 3, section: 'main', ygoprodeck_id: 12 }
    ]);
    await page.waitForFunction(() => {
      const imgs = [...document.querySelectorAll('.deck-builder-view-card img')];
      return imgs.length === 2 && imgs.every(img => img.complete && img.naturalWidth > 0);
    });
    const urls = await page.locator('.deck-builder-view-card img').evaluateAll(nodes => nodes.map(n => n.getAttribute('src')));
    assert(urls[0].endsWith('angbruja.png') && urls[1].endsWith('hoja.png'), 'Imported photos must display even with missing catalog IDs');
    const names = await page.locator('.deck-builder-view-card').allTextContents();
    assert(names[0].includes('Angbruja') && names[0].includes('×3'), 'Do not change deck quantities');
    await page.locator('[data-card-photo]').first().click();
    await page.waitForSelector('.card-inspector');
    assert((await page.locator('.card-inspector img').getAttribute('src')).endsWith('angbruja.png'), 'Local-only photo must open in inspector');
    await page.locator('[data-inspect-close]').click();

    await mount(677, [{ id: 561, deck_id: 677, card_name: 'Smiger', quantity: 2, section: 'main', ygoprodeck_id: null }]);
    await page.waitForFunction(() => document.querySelector('.deck-builder-view-card img')?.getAttribute('src')?.endsWith('smiger.jpg'));
    assert.equal(await page.locator('.deck-builder-view-card img').count(), 1, 'Crystron card photo must be shown');
    assert((await page.locator('.deck-builder-view-card').innerText()).includes('×2'));

    await mount(999, [{ id: 300, deck_id: 999, card_name: 'Hostil', quantity: 1, section: 'main', ygoprodeck_id: null }]);
    await page.waitForFunction(() => document.querySelectorAll('.deck-builder-view-card').length === 1);
    await page.waitForTimeout(80);
    assert.equal(await page.locator('.deck-builder-view-card img').count(), 0, 'External image URLs must be rejected');

    await mount(1000, [{ id: 301, deck_id: 1000, card_name: 'Otra carta', quantity: 1, section: 'side', ygoprodeck_id: null }]);
    await page.waitForTimeout(80);
    assert.equal(await page.locator('.deck-builder-view-card img').count(), 0, 'Study from a different deck must be rejected');

    await mount(1001, [{ id: 302, deck_id: 1001, card_name: 'Sin estudio', quantity: 1, section: 'main', ygoprodeck_id: null }]);
    await page.waitForTimeout(80);
    assert.equal(await page.locator('.deck-builder-view-card img').count(), 0, 'Decklist without a study must remain functional');
    assert(!calls.some(x => x.includes('evil.example')), 'Must never fetch external photo URLs');
    console.log(JSON.stringify({ok:true, decks:['671','677','999','1000','1001'], localPhotos:true, catalogFallback:true, zoom:true, namesAndQuantitiesPreserved:true, hostileUrlBlocked:true, foreignDeckBlocked:true}));
  } finally { await browser.close(); }
}
main().catch(e => { console.error(e); process.exitCode = 1; });
