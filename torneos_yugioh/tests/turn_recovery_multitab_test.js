const { chromium } = require('playwright');

async function openMatch(browser, errors) {
  const page = await browser.newPage({ viewport: { width: 384, height: 854 } });
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.addInitScript(() => {
    localStorage.setItem('dml_result_capture_match_id', '5499');
    localStorage.setItem('dml_result_capture_route', 'resultados');
  });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=turn-recovery-${Date.now()}#resultados`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(2200);
  return page;
}

async function readState(page) {
  return page.evaluate(() => ({
    duel: document.querySelector('[data-result-current-duel]')?.textContent?.trim(),
    status: document.querySelector('[data-result-duel-status="3"]')?.textContent?.trim(),
    turn: document.querySelector('[data-result-turn-current="3"]')?.textContent?.trim(),
    player: document.querySelector('[data-result-turn-player="3"]')?.textContent?.trim(),
    disabled: document.querySelector('[data-result-turn-end="3"]')?.disabled,
    lpB: Number(document.querySelector('[data-result-capture-lp-b="3"]')?.value || 0),
  }));
}

function verify(state, errors, expected = null) {
  if (state.duel !== 'D3' || state.status !== 'En curso' || !/^Turno \d+$/.test(state.turn || '')
    || !/\([AB]\)$/.test(state.player || '') || state.disabled || state.lpB !== 6000 || errors.length
    || (expected && (state.turn !== expected.turn || state.player !== expected.player || state.lpB !== expected.lpB))) {
    throw new Error(JSON.stringify({ state, errors }));
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const firstPage = await openMatch(browser, errors);
  const initial = await readState(firstPage);
  verify(initial, errors);

  await firstPage.reload({ waitUntil: 'networkidle', timeout: 60000 });
  await firstPage.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await firstPage.waitForTimeout(2200);
  const reloaded = await readState(firstPage);
  verify(reloaded, errors, initial);

  const secondPage = await openMatch(browser, errors);
  const secondTab = await readState(secondPage);
  verify(secondTab, errors, initial);

  await browser.close();
  console.log(JSON.stringify({ ok: true, initial, reloaded, secondTab, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
