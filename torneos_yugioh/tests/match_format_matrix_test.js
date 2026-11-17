const { chromium } = require('playwright');

const assert = (condition, message) => { if (!condition) throw new Error(message); };

(async () => {
  const browser = await chromium.launch({ headless: true });
  const report = {};
  for (const scenario of [
    { name: 'desktop', viewport: { width: 1440, height: 1000 } },
    { name: 's25-portrait', viewport: { width: 384, height: 854 } },
    { name: 's25-landscape', viewport: { width: 854, height: 384 } },
  ]) {
    const page = await browser.newPage({ viewport: scenario.viewport });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(`https://stationxtulua.com/yugioh/?qa=format-matrix-${Date.now()}#gestion`, { waitUntil: 'networkidle', timeout: 90000 });
    const values = await page.locator('[data-gestion-competition-match-format]').first().locator('option').evaluateAll((options) => options.map((option) => ({ value: option.value, participants: option.dataset.participants })));
    assert(['1v1', '2v2', '3v3'].every((value) => values.some((option) => option.value === value)), 'Falta un formato de match obligatorio.');
    assert(values.find((option) => option.value === '1v1')?.participants === '1', '1v1 no declara un participante por lado.');
    assert(values.find((option) => option.value === '2v2')?.participants === '2', '2v2 no declara dos participantes por lado.');
    assert(values.find((option) => option.value === '3v3')?.participants === '3', '3v3 no declara tres participantes por lado.');
    const duelBlocks = await page.locator('[data-result-capture-duel]').count();
    assert(duelBlocks === 5, `Se esperaban cinco bloques D1-D5 y se encontraron ${duelBlocks}.`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    report[scenario.name] = { values, duelBlocks, overflow, errors };
    assert(!overflow, `${scenario.name} tiene desbordamiento horizontal.`);
    assert(!errors.length, `${scenario.name} produjo errores: ${errors.join(' | ')}`);
    await page.close();
  }
  await browser.close();
  console.log(JSON.stringify({ ok: true, report }, null, 2));
})().catch((error) => { console.error(error); process.exitCode = 1; });
