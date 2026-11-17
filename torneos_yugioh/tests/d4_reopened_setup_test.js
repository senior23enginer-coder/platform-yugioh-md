const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });
  await page.route('**/*api=duel_session*', async (route) => {
    if (route.request().method() === 'POST') return route.abort('blockedbyclient');
    return route.continue();
  });
  await page.addInitScript(() => {
    localStorage.setItem('dml_result_capture_match_id', '5501');
    localStorage.setItem('dml_result_capture_route', 'resultados');
    localStorage.setItem('dml_duel_timer_5501_4', JSON.stringify({
      duelStarted: true,
      duelStartedAt: Date.now(),
      timerMode: 'none',
      running: false,
      startedAt: null,
      baseElapsed: 0,
      turnHistory: [],
      turnBaseline: 0,
      updatedAt: Date.now() + 60000,
      liveSynced: false,
    }));
  });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=d4-reopened-${Date.now()}#resultados`, {
    waitUntil: 'networkidle',
    timeout: 60000,
  });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(1800);

  const before = await page.evaluate(() => {
    const card = document.querySelector('[data-result-duel-card="4"]');
    const ledger = document.querySelector('[data-result-duel-turn-ledger="4"]');
    const buttons = [...document.querySelectorAll('[data-result-duel-turn-select="4"]')];
    return {
      current: document.querySelector('[data-result-current-duel]')?.textContent?.trim(),
      summary: document.querySelector('[data-result-capture-summary]')?.textContent?.trim(),
      status: document.querySelector('[data-result-duel-status="4"]')?.textContent?.trim(),
      active: card?.classList.contains('is-active'),
      locked: card?.classList.contains('is-locked'),
      ledgerVisible: Boolean(ledger?.offsetParent),
      owner: document.querySelector('[data-result-duel-turn-owner="4"]')?.textContent?.trim(),
      choicesEnabled: buttons.length === 2 && buttons.every((button) => !button.disabled),
      launchHidden: document.querySelector('[data-result-duel-launch="4"]')?.hidden,
    };
  });

  await page.locator('[data-result-duel-turn-select="4"][data-choice="first"]').click();
  await page.waitForTimeout(150);
  const after = await page.evaluate(() => ({
    status: document.querySelector('[data-result-duel-status="4"]')?.textContent?.trim(),
    launchHidden: document.querySelector('[data-result-duel-launch="4"]')?.hidden,
    beginDisabled: document.querySelector('[data-result-duel-begin="4"]')?.disabled,
    selected: document.querySelector('[data-result-duel-turn-choice="4"]')?.value,
  }));
  await page.locator('[data-result-duel-begin="4"]').click();
  await page.locator('[data-duel-start-without-timer]').click();
  await page.waitForTimeout(150);
  const started = await page.evaluate(() => ({
    status: document.querySelector('[data-result-duel-status="4"]')?.textContent?.trim(),
    currentTurn: document.querySelector('[data-result-turn-current="4"]')?.textContent?.trim(),
    currentPlayer: document.querySelector('[data-result-turn-player="4"]')?.textContent?.trim(),
    endTurnDisabled: document.querySelector('[data-result-turn-end="4"]')?.disabled,
    liveHidden: document.querySelector('[data-result-duel-live="4"]')?.hidden,
  }));
  await browser.close();

  const unexpectedErrors = errors.filter((message) => !message.includes('ERR_BLOCKED_BY_CLIENT'));
  const valid = before.current === 'D4'
    && before.summary?.includes('EN CURSO')
    && before.summary?.includes('Marcador 1-2')
    && before.status === 'Define el inicio'
    && before.active
    && !before.locked
    && before.ledgerVisible
    && before.owner?.includes('Perdedor del duelo anterior A')
    && before.choicesEnabled
    && before.launchHidden
    && after.status === 'Listo para comenzar'
    && !after.launchHidden
    && !after.beginDisabled
    && after.selected?.includes('"chosen_by":"A"')
    && started.status === 'En curso'
    && started.currentTurn === 'Turno 1'
    && started.currentPlayer?.includes('(A)')
    && !started.endTurnDisabled
    && !started.liveHidden
    && unexpectedErrors.length === 0;
  if (!valid) throw new Error(JSON.stringify({ before, after, started, errors: unexpectedErrors }));
  console.log(JSON.stringify({ ok: true, before, after, started, errors: unexpectedErrors }, null, 2));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
