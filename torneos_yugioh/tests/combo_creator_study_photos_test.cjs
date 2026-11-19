const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');

const app = path.resolve(__dirname, '..');
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4vUAAAAASUVORK5CYII=', 'base64');
const records = Object.fromEntries([650, 653, 671, 677].map(id => [
  id,
  JSON.parse(fs.readFileSync(path.join(app, 'assets/editorial/20261117-folder-delivery-v40', id + '.json'), 'utf8'))
]));
const imageRoute = id => './assets/editorial/photo-' + id + '.png';

(async () => {
  const db = path.join(__dirname, 'combo-photo-' + process.pid + '.sqlite');
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => {
      const p = socket.address().port;
      socket.close(() => resolve(p));
    });
  });
  const origin = 'http://127.0.0.1:' + port;
  const server = spawn(process.env.PHP_BINARY || 'php', ['-S', '127.0.0.1:' + port, '-t', app, path.join(__dirname, 'combo_creator_router.php')], {
    env: { ...process.env, COMBO_TEST_DB: db, COMBO_TEST_STUDY_PHOTOS: '1' },
    stdio: ['ignore', 'ignore', 'pipe']
  });
  let browser;
  let logs = '';
  server.stderr.on('data', data => { logs += data.toString(); });
  try {
    for (let i = 0; i < 80; i++) {
      try { if ((await fetch(origin)).ok) break; } catch (_) {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 700, height: 980 } });
    const errors = [], external = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('request', req => { if (!req.url().startsWith(origin)) external.push(req.url()); });
    page.on('dialog', dialog => dialog.accept());

    await page.route('**/assets/editorial/strategy-content-state.json*', route => route.fulfill({
      status: 200, contentType: 'application/json',
      body: JSON.stringify({ checked_studies: Object.fromEntries(Object.keys(records).map(id => [id, {
        path: './assets/editorial/study-photo-' + id + '.json'
      }])) })
    }));
    await page.route(/\/assets\/editorial\/study-photo-(\d+)\.json(?:\?.*)?$/, route => {
      const id = Number(route.request().url().match(/study-photo-(\d+)\.json/)[1]);
      const record = records[id];
      const payload = {
        schema: 'station-x-curated-v62',
        deck_id: id === 650 ? 651 : id, // Mismatched study must never supply images.
        cards: record.cards.map(card => ({
          ...card,
          image: id === 677 && card.slots.some(s => Number(s.card_id) === 561)
            ? 'https://untrusted.example/unsafe.png'
            : imageRoute(id),
          full_image: imageRoute(id)
        }))
      };
      // A remote card.image must not accidentally use full_image as an unsafe bypass.
      if (id === 677) {
        const bad = payload.cards.find(card => card.slots.some(s => Number(s.card_id) === 561));
        bad.full_image = 'https://untrusted.example/unsafe.png';
      }
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload) });
    });
    await page.route(/\/assets\/editorial\/photo-\d+\.png(?:\?.*)?$/, route => route.fulfill({
      status: 200, contentType: 'image/png', body: png
    }));
    await page.route('**/assets/cards/originals/identities.json*', route => route.fulfill({
      status: 200, contentType: 'application/json', body: JSON.stringify({
        cards: {
          '671:499': {
            card_name: 'Oleada de Calor',
            image_id: 999999,
            file: './assets/cards/originals/not-found-test.png'
          }
        }
      })
    }));
    await page.route('**/assets/cards/originals/not-found-test.png*', route => route.fulfill({ status: 404, body: '' }));
    await page.goto(origin, { waitUntil: 'domcontentloaded' });

    // Same-deck study covers real native slot IDs, including missing/broken catalog photos.
    await page.locator('[data-cc-deck]').selectOption('671');
    await page.locator('[data-cc-work]:visible').waitFor();
    await page.locator('[data-cc-search]').fill('Oleada de Calor');
    const memento = page.locator('[data-cc-card="499"]');
    await memento.waitFor();
    await page.waitForFunction(() => {
      const img = document.querySelector('[data-cc-card="499"] img');
      return img && img.complete && img.naturalWidth > 0 && img.getAttribute('src').includes('photo-671.png');
    }, { timeout: 12000 });
    assert.equal(await memento.locator('.cc-no-image').count(), 0, 'Memento should have a visible image');
    await memento.click();
    await page.locator('[data-cc-insert-card]').click();
    await page.waitForFunction(() => {
      const img = document.querySelector('.cc-node .cc-face img');
      return img && img.getAttribute('src').includes('photo-671.png');
    });
    assert.equal(await page.locator('.cc-node .cc-no-image').count(), 0, 'Canvas must reuse the same photo');
    const mainQuantity = await page.locator('[data-cc-card="499"]').textContent();
    assert.match(mainQuantity, /×3/, 'The card quantity must not change');

    // Another deck uses the same generic image resolver, not a Memento-specific rule.
    await page.locator('[data-cc-deck]').selectOption('677');
    await page.locator('[data-cc-search]').fill('Entierro Insensato');
    await page.waitForFunction(() => {
      const img = document.querySelector('[data-cc-card="539"] img');
      return img && img.complete && img.naturalWidth > 0 && img.getAttribute('src').includes('photo-677.png');
    }, { timeout: 12000 });
    assert.equal(await page.locator('[data-cc-card="539"] .cc-no-image').count(), 0);

    await page.locator('[data-cc-search]').fill('Juicio Solemne');
    await page.locator('[data-cc-card="561"]').waitFor();
    await page.waitForTimeout(100);
    assert.equal(await page.locator('[data-cc-card="561"] img').count(), 0, 'Remote image must be rejected');

    // A third deck proves the implementation is not hardcoded to the pilot pair.
    await page.locator('[data-cc-deck]').selectOption('653');
    await page.locator('[data-cc-work]:visible').waitFor();
    await page.waitForFunction(() =>
      [...document.querySelectorAll('[data-cc-palette] img')].some(img =>
        img.getAttribute('src').includes('photo-653.png') && img.complete && img.naturalWidth > 0
      ), { timeout: 12000 });

    // A study belonging to another deck must not supply images.
    await page.locator('[data-cc-deck]').selectOption('650');
    await page.locator('[data-cc-work]:visible').waitFor();
    await page.waitForTimeout(100);
    assert.equal(await page.locator('[data-cc-palette] img[src*="photo-650.png"]').count(), 0);

    assert.equal(external.length, 0, 'Do not request untrusted image URLs: ' + external);
    assert.deepEqual(errors, [], 'Unexpected browser errors');
    console.log(JSON.stringify({
      ok: true, decksTested: [671, 677, 653, 650],
      nativeCardIds: true, missingCatalogFallback: true,
      editorPaletteAndCanvas: true, noCrossDeckImages: true, noExternalImageFetches: true
    }));
    await page.close();
  } finally {
    await browser?.close();
    server.kill();
    try { fs.unlinkSync(db); } catch (_) {}
    try { fs.unlinkSync(db + '-journal'); } catch (_) {}
    if (logs.includes('Fatal error')) console.error(logs);
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
