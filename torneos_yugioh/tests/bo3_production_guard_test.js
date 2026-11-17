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
  await page.goto(`https://stationxtulua.com/yugioh/?qa=bo3-guard-${Date.now()}#resultados`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(1800);

  const initialDuel = (await page.locator('[data-result-current-duel]').textContent() || '').trim();
  await page.locator('[data-result-capture-duel="3"]').evaluate((select) => {
    select.disabled = false;
    select.value = 'B';
    select.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await page.waitForTimeout(250);
  const result = await page.evaluate(() => ({
    current: document.querySelector('[data-result-current-duel]')?.textContent?.trim(),
    summary: document.querySelector('[data-result-capture-summary]')?.textContent?.trim(),
    d3Status: document.querySelector('[data-result-duel-status="3"]')?.textContent?.trim(),
    d3Active: document.querySelector('[data-result-duel-card="3"]')?.classList.contains('is-active'),
    d3Complete: document.querySelector('[data-result-duel-card="3"]')?.classList.contains('is-complete'),
    d3Visible: Boolean(document.querySelector('[data-result-duel-card="3"]')?.offsetParent),
    d4Active: document.querySelector('[data-result-duel-card="4"]')?.classList.contains('is-active'),
    methodEnabled: !document.querySelector('[data-result-capture-method="3"]')?.disabled,
    lpAEnabled: !document.querySelector('[data-result-capture-lp-a="3"]')?.disabled,
    saveDisabled: document.querySelector('[data-result-capture-save]')?.disabled,
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  }));
  await browser.close();

  const unexpectedErrors = errors.filter((message) => !message.includes('ERR_BLOCKED_BY_CLIENT'));
  if (initialDuel !== 'D3' || result.current !== 'D3' || !result.summary?.includes('FINALIZADA')
    || !result.summary?.includes('Marcador 0-2') || result.d3Status !== 'Listo para guardar'
    || !result.d3Active || result.d3Complete || !result.d3Visible || result.d4Active
    || !result.methodEnabled || !result.lpAEnabled || result.saveDisabled || result.overflow || unexpectedErrors.length) {
    throw new Error(JSON.stringify({ initialDuel, result, errors: unexpectedErrors }));
  }
  console.log(JSON.stringify({ ok: true, initialDuel, result, errors: unexpectedErrors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
