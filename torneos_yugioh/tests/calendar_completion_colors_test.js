const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 384, height: 854 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=calendar-colors-${Date.now()}#calendario`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-screen="calendario"]:not([hidden])').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(1200);
  const result = await page.evaluate(() => {
    const incomplete = [...document.querySelectorAll('.calendar-day.date-incomplete')];
    const complete = [...document.querySelectorAll('.calendar-day.date-complete')];
    const badgeColor = (node) => getComputedStyle(node.querySelector('.calendar-day-total')).backgroundColor;
    return {
      incomplete: incomplete.length,
      complete: complete.length,
      incompleteBadge: incomplete[0] ? badgeColor(incomplete[0]) : '',
      completeBadge: complete[0] ? badgeColor(complete[0]) : '',
      legend: [...document.querySelectorAll('.calendar-legend span')].map((node) => node.textContent.trim()),
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    };
  });
  await page.screenshot({ path: 'qa-calendar-completion-colors-s25.png', fullPage: false });
  await browser.close();
  if (!result.incomplete || !result.complete || result.incompleteBadge !== 'rgb(47, 128, 237)'
    || result.completeBadge !== 'rgb(34, 201, 122)' || result.overflow || errors.length
    || !result.legend.includes('Fecha incompleta') || !result.legend.includes('Fecha completa')) {
    throw new Error(JSON.stringify({ result, errors }));
  }
  console.log(JSON.stringify({ ok: true, result, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
