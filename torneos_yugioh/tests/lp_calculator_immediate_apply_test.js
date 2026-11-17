const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 384, height: 854 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.route('**/*api=duel_session*', async (route) => {
    if (route.request().method() === 'POST') return route.abort('blockedbyclient');
    return route.continue();
  });
  await page.addInitScript(() => {
    localStorage.setItem('dml_result_capture_match_id', '5499');
    localStorage.setItem('dml_result_capture_route', 'resultados');
  });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=lp-immediate-${Date.now()}#resultados`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(2200);

  const before = Number(await page.locator('[data-result-capture-lp-b="3"]').inputValue());
  await page.locator('[data-result-lp-open="3"]').click();
  await page.locator('[data-result-lp-side="b"]').click();
  await page.locator('[data-result-lp-key="1"]').click();
  await page.locator('[data-result-lp-key="00"]').click();
  await page.locator('[data-result-lp-op="subtract"]').click();
  const after = Number(await page.locator('[data-result-capture-lp-b="3"]').inputValue());
  const pending = (await page.locator('[data-result-save-state]').textContent() || '').trim();

  await browser.close();
  if (before !== 6000 || after !== 5900 || !pending.includes('Cambios pendientes') || errors.length) {
    throw new Error(JSON.stringify({ before, after, pending, errors }));
  }
  console.log(JSON.stringify({ ok: true, before, after, pending, serverWriteBlocked: true, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
