const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const scenarios = [
    { name: 'desktop', viewport: { width: 1440, height: 1000 } },
    { name: 's25', viewport: { width: 384, height: 854 } },
  ];
  const report = {};

  for (const scenario of scenarios) {
    const page = await browser.newPage({ viewport: scenario.viewport });
    const errors = [];
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`https://stationxtulua.com/yugioh/?qa=${Date.now()}#estadisticas`, { waitUntil: 'networkidle', timeout: 90000 });
    await page.locator('[data-stats-tab="advanced"]').click();
    await page.locator('[data-stats-view="advanced"]').waitFor({ state: 'visible' });
    const advancedRows = await page.locator('[data-stats-advanced-body] tr').count();
    const advancedText = await page.locator('[data-stats-view="advanced"]').innerText();
    await page.locator('[data-stats-tab="operations"]').click();
    await page.locator('[data-stats-view="operations"]').waitFor({ state: 'visible' });
    const operationsText = await page.locator('[data-stats-view="operations"]').innerText();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    const emptyButtons = await page.locator('[data-stats-view="operations"] button').evaluateAll((buttons) => buttons.filter((button) => !button.textContent.trim() && !button.getAttribute('aria-label') && !button.title).length);
    await page.screenshot({ path: `torneos_yugioh/qa-intelligence-${scenario.name}.png`, fullPage: true });
    report[scenario.name] = {
      advancedRows,
      hasPositionStats: advancedText.includes('WR D1-D5'),
      hasLiveCenter: operationsText.includes('Centro de partido en vivo'),
      hasOfflineQueue: await page.locator('[data-offline-queue-count]').count() === 1,
      hasNotifications: operationsText.includes('Activar avisos'),
      hasPublicSeason: operationsText.includes('Ver temporada como publico') && operationsText.toLowerCase().includes('marcador publico'),
      overflow,
      emptyButtons,
      errors: errors.filter((message) => !message.includes('favicon')).slice(0, 10),
    };
    await page.close();
  }

  await browser.close();
  const failed = Object.values(report).some((row) => !row.advancedRows || !row.hasPositionStats || !row.hasLiveCenter || !row.hasOfflineQueue || !row.hasNotifications || !row.hasPublicSeason || row.overflow || row.emptyButtons || row.errors.length);
  console.log(JSON.stringify({ ok: !failed, report }, null, 2));
  process.exitCode = failed ? 1 : 0;
})();
