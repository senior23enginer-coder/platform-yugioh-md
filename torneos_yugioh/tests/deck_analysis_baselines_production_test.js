const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];

  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 's25', width: 412, height: 915 },
  ]) {
    const page = await browser.newPage({ viewport });
    page.on('console', (message) => {
      if (message.type() === 'error') errors.push(`${viewport.name}: ${message.text()}`);
    });
    page.on('pageerror', (error) => errors.push(`${viewport.name}: ${error.message}`));
    await page.goto('https://stationxtulua.com/yugioh/?deck_view=analysis#deck_ficha', { waitUntil: 'networkidle' });
    await page.locator('.deck-analysis-screen:not([hidden])').waitFor();

    const identity = await page.locator('[data-deck-strategy-identity]').innerText();
    const plans = await page.locator('[data-deck-strategy-plans]').innerText();
    const strengths = await page.locator('[data-deck-strategy-strengths]').innerText();
    const scores = await page.locator('[data-deck-strategy-scores]').innerText();
    const normalizedIdentity = identity.toLowerCase();
    if (!normalizedIdentity.includes('synchro reactivo') || !normalizedIdentity.includes('v1')) throw new Error(`${viewport.name}: identidad de Kewl Tune incompleta: ${identity.replaceAll('\n', ' | ')}`);
    if (plans.includes('Pendiente de documentar')) throw new Error(`${viewport.name}: planes editoriales pendientes`);
    if (strengths.includes('Pendiente de documentar')) throw new Error(`${viewport.name}: lectura editorial pendiente`);
    if (scores.includes('Pendiente')) throw new Error(`${viewport.name}: valoraciones editoriales pendientes`);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
    if (overflow) throw new Error(`${viewport.name}: overflow horizontal`);
    console.log(`${viewport.name}: OK`);
    await page.close();
  }

  if (errors.length) throw new Error(errors.join('\n'));
  console.log('54 perfiles persistidos y ficha de Kewl Tune renderizada sin pendientes editoriales.');
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
