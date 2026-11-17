const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const verify = async (viewport, screenshot) => {
    const page = await browser.newPage({ viewport });
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(`https://stationxtulua.com/yugioh/?event_id=15&qa=qualifier-workspace-${Date.now()}#clasificatorio`, { waitUntil: 'networkidle', timeout: 60000 });
    await page.locator('[data-screen="clasificatorio"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });

    const tabLabels = await page.locator('[data-qualifier-tab]').allTextContents();
    const logoCount = await page.locator('[data-qualifier-ranking-body] .team-inline-badge.has-image img').count();
    const firstAsc = Number(await page.locator('[data-qualifier-position]').first().getAttribute('data-qualifier-position'));
    await page.locator('[data-qualifier-ranking-order]').selectOption('desc');
    const firstDesc = Number(await page.locator('[data-qualifier-position]').first().getAttribute('data-qualifier-position'));

    await page.locator('[data-qualifier-tab="jugar"]').click();
    const matchCount = await page.locator('[data-qualifier-match-list] [data-qualifier-match-row]').count();
    const firstPending = matchCount ? (await page.locator('[data-qualifier-match-list] [data-qualifier-match-row]').first().innerText()) : '';
    const matchLogoCount = await page.locator('[data-qualifier-match-list] .team-inline-badge.has-image img').count();
    await page.locator('[data-qualifier-match-order]').selectOption('finished_first');
    const firstFinished = matchCount ? (await page.locator('[data-qualifier-match-list] [data-qualifier-match-row]').first().innerText()) : '';

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    await page.screenshot({ path: screenshot, fullPage: false });
    await page.close();
    return { tabLabels: tabLabels.map((label) => label.trim()), logoCount, firstAsc, firstDesc, matchCount, firstPending, firstFinished, matchLogoCount, overflow };
  };

  const desktop = await verify({ width: 1440, height: 950 }, 'qa-qualifier-workspace-desktop-v7.png');
  const mobile = await verify({ width: 412, height: 915 }, 'qa-qualifier-workspace-mobile-v7.png');
  await browser.close();

  [desktop, mobile].forEach((result) => {
    assert.deepStrictEqual(result.tabLabels, ['Ranking', 'Combates', 'Finalizar evento']);
    assert(result.logoCount > 0, 'No se renderizaron logos en el ranking.');
    assert.strictEqual(result.firstAsc, 1, 'El ranking ascendente no comienza en la posicion 1.');
    assert(result.firstDesc > result.firstAsc, 'El ranking descendente no invierte las posiciones.');
    assert(result.matchCount > 0, 'No se renderizaron combates.');
    assert(/PENDIENTE|EN CURSO/.test(result.firstPending), 'El orden inicial no prioriza combates pendientes.');
    assert(/FINALIZADA/.test(result.firstFinished), 'El orden alternativo no prioriza combates finalizados.');
    assert(result.matchLogoCount > 0, 'No se renderizaron logos en los combates.');
    assert.strictEqual(result.overflow, false, 'La pantalla produce desbordamiento horizontal.');
  });
  assert.strictEqual(errors.length, 0, errors.join('\n'));
  console.log(JSON.stringify({ ok: true, desktop, mobile, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
