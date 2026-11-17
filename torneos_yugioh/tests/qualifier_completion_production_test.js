const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(`https://stationxtulua.com/yugioh/?event_id=15&qa=qualifier-completion-${Date.now()}#clasificatorio`, {
    waitUntil: 'networkidle',
    timeout: 60000,
  });
  await page.locator('[data-screen="clasificatorio"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });

  const rankingHeaders = (await page.locator('[data-qualifier-ranking-table] thead th').allTextContents()).map((text) => text.trim());
  const progressLabels = (await page.locator('[data-qualifier-ranking-body] .qualifier-combat-progress strong').allTextContents()).map((text) => text.trim());
  const matchCount = await page.locator('[data-qualifier-match-list] [data-qualifier-match-row]').count();
  await page.screenshot({ path: 'qa-qualifier-progress-desktop-v10.png', fullPage: false });

  await page.locator('[data-qualifier-tab="finalizar"]').click();
  const finalizeHeaders = (await page.locator('[data-qualifier-finalize-table] thead th').allTextContents()).map((text) => text.trim());
  const finalizeRows = await page.locator('[data-qualifier-finalize-table] tbody tr').count();
  const pendingRows = await page.locator('[data-qualifier-finalize-table] tbody tr.is-pending').count();
  const completeRows = await page.locator('[data-qualifier-finalize-table] tbody tr.is-complete').count();
  await page.screenshot({ path: 'qa-qualifier-finalize-desktop-v10.png', fullPage: false });

  await page.setViewportSize({ width: 412, height: 915 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.locator('[data-screen="clasificatorio"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });
  const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  await page.screenshot({ path: 'qa-qualifier-progress-mobile-v10.png', fullPage: false });
  await browser.close();

  assert.deepStrictEqual(rankingHeaders, ['#', 'Equipo / Deck', 'PTS', 'PJ', 'PG / E / PP', 'CG / CP', 'DIF', 'Combates']);
  assert(progressLabels.length > 0 && progressLabels.every((label) => /\/ 3$/.test(label)), 'Todo progreso debe usar el requisito de 3 combates.');
  assert.strictEqual(matchCount, 42, 'La actualizacion no debe regenerar ni eliminar los 42 cruces existentes.');
  assert.deepStrictEqual(finalizeHeaders, ['Equipo / Deck', 'Cumplimiento de combates']);
  assert(finalizeRows > 0 && pendingRows + completeRows === finalizeRows, 'Cada participante debe tener estado amarillo o verde.');
  assert.strictEqual(mobileOverflow, false, 'La vista movil produce desbordamiento horizontal.');
  assert.strictEqual(errors.length, 0, errors.join('\n'));
  console.log(JSON.stringify({ ok: true, rankingHeaders, progressSample: progressLabels.slice(0, 5), matchCount, finalizeHeaders, finalizeRows, pendingRows, completeRows, mobileOverflow }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
