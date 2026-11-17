const { chromium } = require('playwright');

(async () => {
  const base = process.env.YGO_URL || 'https://stationxtulua.com/yugioh/';
  const api = await fetch(`${base}?api=live`).then((response) => response.json());
  const match = (api.matches || []).find((row) => !['FINALIZADA', 'COMPLETED'].includes(String(row.estado || '').toUpperCase())) || (api.matches || [])[0];
  if (!match?.id) throw new Error('No hay una partida disponible para validar las pantallas A/B.');
  const browser = await chromium.launch({ headless: true });
  const report = { matchId: match.id, sides: {} };
  for (const viewport of [{ name: 'portrait', width: 384, height: 854 }, { name: 'landscape', width: 854, height: 384 }]) {
    for (const side of ['A', 'B']) {
      const page = await browser.newPage({ viewport: { width: viewport.width, height: viewport.height } });
      const errors = [];
      page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
      page.on('pageerror', (error) => errors.push(error.message));
      await page.goto(`${base}?duel_player=${side}&match_id=${match.id}`, { waitUntil: 'networkidle' });
      await page.waitForSelector('[data-player-duel-root]');
      await page.waitForTimeout(1800);
      const metrics = await page.evaluate(() => ({
        title: document.querySelector('h1')?.textContent?.trim() || '',
        side: document.documentElement.dataset.duelPlayerSide,
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        endTurn: Boolean(document.querySelector('[data-player-end-turn]')),
        calculatorKeys: document.querySelectorAll('[data-player-key]').length,
        sync: document.querySelector('[data-player-sync]')?.textContent?.trim() || ''
      }));
      if (metrics.side !== side || metrics.overflow || !metrics.endTurn || metrics.calculatorKeys !== 12 || errors.length) {
        throw new Error(JSON.stringify({ viewport, side, metrics, errors }));
      }
      const key = `${side}-${viewport.name}`;
      report.sides[key] = { ...metrics, errors };
      await page.screenshot({ path: `qa-player-duel-${side.toLowerCase()}-${viewport.name}.png`, fullPage: true });
      await page.close();
    }
  }
  await browser.close();
  console.log(JSON.stringify({ ok: true, report }, null, 2));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
