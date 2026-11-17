const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 412, height: 915 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=calendar-format-${Date.now()}#calendario`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-screen="calendario"]:not([hidden])').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(1200);

  const read = () => page.evaluate(() => {
    const color = (selector) => {
      const node = document.querySelector(selector);
      return node ? getComputedStyle(node).getPropertyValue('--accent').trim() : '';
    };
    return {
      tcg: color('[data-calendar-format="tcg"]'),
      rush: color('[data-calendar-format="rush"]'),
      goat: color('[data-calendar-format="goat"]'),
      incomplete: document.querySelectorAll('.calendar-day.date-incomplete').length,
      complete: document.querySelectorAll('.calendar-day.date-complete').length,
      incompleteBackground: getComputedStyle(document.querySelector('.calendar-day.date-incomplete') || document.body).backgroundImage,
      completeBackground: getComputedStyle(document.querySelector('.calendar-day.date-complete') || document.body).backgroundImage,
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    };
  });

  let result = await read();
  for (let index = 0; index < 3 && !result.complete; index += 1) {
    await page.locator('[data-calendar-prev]').click();
    await page.waitForTimeout(350);
    const previous = await read();
    result = {
      ...result,
      complete: previous.complete,
      completeBackground: previous.completeBackground,
      overflow: result.overflow || previous.overflow,
    };
  }
  await page.screenshot({ path: 'qa-calendar-format-s25.png', fullPage: false });
  await browser.close();

  if (result.tcg !== '#2f80ed' || (result.rush && result.rush !== '#ffd45f') || (result.goat && result.goat !== '#ff8a00')
    || !result.incomplete || !result.incompleteBackground.includes('15, 52, 103')
    || (result.complete && !result.completeBackground.includes('11, 70, 57')) || result.overflow || errors.length) {
    throw new Error(JSON.stringify({ result, errors }));
  }
  console.log(JSON.stringify({ ok: true, result, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
