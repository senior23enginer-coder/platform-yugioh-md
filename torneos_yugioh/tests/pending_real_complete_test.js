const { chromium } = require('playwright');

const assert = (condition, message) => { if (!condition) throw new Error(message); };

(async () => {
  const browser = await chromium.launch({ headless: true });
  const report = {};
  for (const scenario of [
    { name: 'desktop', viewport: { width: 1440, height: 1000 } },
    { name: 's25', viewport: { width: 384, height: 854 } },
  ]) {
    const page = await browser.newPage({ viewport: scenario.viewport });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(`https://stationxtulua.com/yugioh/?qa=pending-real-${Date.now()}#estadisticas`, { waitUntil: 'networkidle', timeout: 90000 });
    const persisted = await page.evaluate(() => ({
      ranking: (window.DML_DATA?.rankingHistory || []).length,
      summaries: (window.DML_DATA?.eventSummaries || []).length,
      meta: (window.DML_DATA?.metaHistory || []).length,
      snapshots: (window.DML_DATA?.matchSnapshots || []).length,
    }));
    await page.locator('[data-stats-tab="matchups"]').click();
    const matrixCells = await page.locator('[data-stats-matchup-matrix] td').count();
    await page.locator('[data-stats-tab="records"]').click();
    const rankingRows = await page.locator('[data-stats-ranking-history] tr').count();
    await page.locator('[data-stats-tab="advanced"]').click();
    const tierRows = await page.locator('[data-stats-tier-performance] tr').count();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    await page.screenshot({ path: `qa-pending-real-${scenario.name}.png`, fullPage: true });
    report[scenario.name] = { persisted, matrixCells, rankingRows, tierRows, overflow, errors };
    assert(persisted.ranking > 0, 'No se persistio el ranking historico.');
    assert(matrixCells > 0, 'La matriz de matchups esta vacia.');
    assert(rankingRows > 0, 'No se renderizo el historico de posiciones.');
    assert(tierRows > 0, 'No se renderizo el rendimiento por tier.');
    assert(!overflow, `${scenario.name} tiene desbordamiento global.`);
    assert(!errors.length, `${scenario.name}: ${errors.join(' | ')}`);
    await page.close();
  }
  const publicPage = await browser.newPage({ viewport: { width: 384, height: 854 } });
  const publicErrors = [];
  publicPage.on('pageerror', (error) => publicErrors.push(error.message));
  await publicPage.goto(`https://stationxtulua.com/yugioh/?public_live=1&qa=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
  const publicCards = await publicPage.locator('.public-match').count();
  const publicOverflow = await publicPage.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  await publicPage.screenshot({ path: 'qa-public-live-s25.png', fullPage: true });
  assert(!publicErrors.length, publicErrors.join(' | '));
  assert(!publicOverflow, 'El marcador publico desborda en S25.');
  report.publicLive = { publicCards, publicOverflow, publicErrors };
  await browser.close();
  console.log(JSON.stringify({ ok: true, report }, null, 2));
})().catch((error) => { console.error(error); process.exitCode = 1; });
