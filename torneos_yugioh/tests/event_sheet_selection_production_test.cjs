const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.YGO_TEST_URL || 'https://stationxtulua.com/yugioh/';
const out = path.resolve(__dirname, '../../outputs/ygo-event-sheet-fix-20261016');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1050 } });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const sheet = page.locator('[data-screen="evento_ficha"]');
  async function ready() {
    await page.waitForFunction(() => window.DML_DATA?.dbStatus?.connected);
    await page.waitForFunction(() => document.querySelector('[data-event-open]'));
  }
  async function verifyEvent(event) {
    await sheet.waitFor({ state: 'visible' });
    assert.equal(await sheet.locator('[data-competition-config-id]').inputValue(), String(event.id));
    assert.equal(await sheet.locator('[data-competition-config-name]').inputValue(), event.name);
    assert.equal((await sheet.locator('[data-competition-config-title]').innerText()).trim(), event.name.replace(/\s+/g, ' ').trim());
    assert.equal(new URL(page.url()).searchParams.get('event_id'), String(event.id));
    const seasonField = sheet.locator('[data-event-season-name]');
    assert.equal(await seasonField.inputValue(), event.season_name);
    assert.equal(await seasonField.getAttribute('readonly'), '');
    assert.equal(await sheet.locator('select[name="competition_season_id"]').count(), 0);
    assert.equal(await seasonField.evaluate(input => Boolean(input.closest('.event-main-fields'))), true);
  }
  async function back() {
    await sheet.locator('[data-route="torneos"]').first().click();
    await page.locator('[data-screen="torneos"]:not([hidden])').waitFor();
  }
  try {
    await page.goto(base + '?selection_check=' + Date.now() + '#torneos', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await ready();
    const events = await page.evaluate(() => window.DML_DATA.competitions);
    const apex = events.find(event => event.name === 'Apex League');
    const rush = events.find(event => event.name === 'Rush Dominion League');
    assert.ok(apex && rush);
    // Reproduce a stale Apex URL/storage before clicking Rush, then alternate every event.
    await page.evaluate(id => localStorage.setItem('dml_event_ficha_id', String(id)), apex.id);
    await page.goto(base + '?event_id=' + apex.id + '#torneos', { waitUntil: 'domcontentloaded' });
    await ready();
    assert.equal(await page.locator('[data-screen="torneos"] [data-event-season-filter]').count(), 0);
    assert.equal(await page.locator('[data-screen="torneos"] .event-season-toolbar').count(), 0);
    const order = [rush, apex, ...events.filter(event => event.id !== apex.id && event.id !== rush.id), rush];
    for (const event of order) {
      await page.locator('[data-screen="torneos"] [data-event-open="' + event.id + '"]').click();
      await verifyEvent(event);
      await back();
    }
    await page.locator('[data-screen="torneos"] [data-event-open="' + rush.id + '"]').click();
    await verifyEvent(rush);
    await page.evaluate(id => localStorage.setItem('dml_event_ficha_id', String(id)), apex.id);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await verifyEvent(rush);
    await page.screenshot({ path: path.join(out, 'rush-desktop.png') });
    await page.setViewportSize({ width: 390, height: 844 });
    await back();
    await page.locator('[data-screen="torneos"] [data-event-open="' + apex.id + '"]').click();
    await verifyEvent(apex);
    await back();
    await page.locator('[data-screen="torneos"] [data-event-open="' + rush.id + '"]').click();
    await verifyEvent(rush);
    await page.screenshot({ path: path.join(out, 'rush-mobile.png') });
    await page.goto(base + '?event_id=2147483647#evento_ficha', { waitUntil: 'domcontentloaded' });
    await page.locator('[data-event-open-error]:not([hidden])').waitFor();
    assert.equal(await sheet.isVisible(), false);
    assert.deepEqual(errors, []);
    const result = { passed: true, tested_events: events.length, stale_url_fixed: true, reload_preserves_selection: true, invalid_id_opens_no_other_event: true, season_readonly_in_event_data: true, listing_season_filter_removed: true, javascript_errors: errors };
    fs.writeFileSync(path.join(out, 'verification.json'), JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
