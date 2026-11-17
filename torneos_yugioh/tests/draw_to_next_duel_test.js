const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 384, height: 854 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.addInitScript(() => {
    localStorage.setItem('dml_result_capture_match_id', '5499');
    localStorage.setItem('dml_result_capture_route', 'resultados');
  });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=draw-next-${Date.now()}#resultados`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(2200);
  const result = await page.evaluate(() => ({
    current: document.querySelector('[data-result-current-duel]')?.textContent?.trim(),
    d1: document.querySelector('[data-result-duel-card="1"]')?.dataset.duelResult,
    d2: document.querySelector('[data-result-duel-card="2"]')?.dataset.duelResult,
    d3Active: document.querySelector('[data-result-duel-card="3"]')?.classList.contains('is-active'),
    d3Status: document.querySelector('[data-result-duel-status="3"]')?.textContent?.trim(),
    d3CurrentTurn: document.querySelector('[data-result-turn-current="3"]')?.textContent?.trim(),
    d3CurrentPlayer: document.querySelector('[data-result-turn-player="3"]')?.textContent?.trim(),
    d3EndTurnDisabled: document.querySelector('[data-result-turn-end="3"]')?.disabled,
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    saveState: document.querySelector('[data-result-save-state]')?.textContent?.trim()
  }));
  await page.screenshot({ path: 'qa-draw-to-d3-s25.png', fullPage: false });
  await browser.close();
  if (result.current !== 'D3' || result.d1 !== 'E' || result.d2 !== 'B' || !result.d3Active
    || result.d3CurrentTurn !== 'Turno 4' || !result.d3CurrentPlayer?.includes('(B)') || result.d3EndTurnDisabled
    || result.overflow || errors.length) {
    throw new Error(JSON.stringify({ result, errors }));
  }
  console.log(JSON.stringify({ ok: true, result, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
