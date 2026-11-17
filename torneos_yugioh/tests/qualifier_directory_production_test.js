const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=qualifier-directory-${Date.now()}#clasificatorio`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-screen="clasificatorio"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });
  await page.locator('[data-qualifier-directory]:not([hidden])').waitFor({ state: 'visible' });

  const initialRows = await page.locator('[data-qualifier-directory-row]:visible').count();
  const detailInitiallyHidden = await page.locator('[data-qualifier-detail]').getAttribute('hidden') !== null;
  await page.locator('[data-qualifier-directory-search]').fill('evento-inexistente-qa');
  const filteredRows = await page.locator('[data-qualifier-directory-row]:visible').count();
  await page.locator('[data-qualifier-directory-clear]').click();
  const restoredRows = await page.locator('[data-qualifier-directory-row]:visible').count();
  await page.screenshot({ path: 'qa-qualifier-directory-desktop-v8.png', fullPage: false });

  await page.locator('[data-qualifier-directory-open]').first().click();
  await page.waitForURL(/event_id=\d+.*#clasificatorio/, { timeout: 30000 });
  await page.locator('[data-qualifier-detail]:not([hidden])').waitFor({ state: 'visible' });
  const detailTitle = (await page.locator('.qualifier-workspace-title h1').textContent()).trim();
  await page.locator('[data-qualifier-directory-back]').click();
  await page.waitForURL((url) => !url.searchParams.has('event_id') && url.hash === '#clasificatorio', { timeout: 30000 });
  await page.locator('[data-qualifier-directory]:not([hidden])').waitFor({ state: 'visible' });

  await page.setViewportSize({ width: 412, height: 915 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.locator('[data-qualifier-directory]:not([hidden])').waitFor({ state: 'visible' });
  const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  await page.screenshot({ path: 'qa-qualifier-directory-mobile-v8.png', fullPage: false });
  await browser.close();

  assert(initialRows > 0, 'No se listaron clasificatorios.');
  assert(detailInitiallyHidden, 'El detalle no debe abrirse automaticamente.');
  assert.strictEqual(filteredRows, 0, 'El buscador no filtro el listado.');
  assert.strictEqual(restoredRows, initialRows, 'Limpiar no restauro los eventos.');
  assert(detailTitle.length > 0, 'Ver no abrio el clasificatorio elegido.');
  assert.strictEqual(mobileOverflow, false, 'El directorio desborda en mobile.');
  assert.strictEqual(errors.length, 0, errors.join('\n'));
  console.log(JSON.stringify({ ok: true, initialRows, filteredRows, restoredRows, detailTitle, mobileOverflow, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
