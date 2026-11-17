const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(`https://stationxtulua.com/yugioh/?qa=deck-navigation-${Date.now()}#deck`, { waitUntil: 'networkidle', timeout: 90000 });
  await page.locator('[data-screen="deck"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });
  const totalRows = await page.locator('[data-deck-list-row]').count();
  const filterCount = await page.locator('.deck-list-filters select').count();
  const nullInputs = await page.locator('input').evaluateAll((inputs) => inputs.filter((input) => String(input.value).toLowerCase() === 'null' || String(input.placeholder).toLowerCase() === 'null').length);

  await page.locator('[data-deck-list-format-filter]').selectOption('goat');
  const goatRows = await page.locator('[data-deck-list-row]:visible').count();
  const goatCountLabel = (await page.locator('[data-deck-list-visible-count]').innerText()).trim();
  await page.locator('[data-deck-list-clear]').click();
  const restoredRows = await page.locator('[data-deck-list-row]:visible').count();
  await page.locator('[data-deck-list-order]').selectOption('name');
  const orderedNames = await page.locator('[data-deck-list-row]:visible [data-label="Deck"] strong').allTextContents();

  await page.locator('[data-deck-list-row]:visible').first().locator('[data-open-deck-sheet]').click();
  await page.locator('[data-screen="deck_ficha"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });
  const categoryButtons = await page.locator('[data-deck-category-open]').count();
  const oldTabs = await page.locator('.deck-profile-tabs').count();
  await page.screenshot({ path: 'qa-deck-navigation-desktop-v13.png', fullPage: false });

  await page.locator('[data-deck-category-open="performance"]').click();
  await page.locator('#deckCategoryModal').waitFor({ state: 'visible', timeout: 10000 });
  const performanceTitle = (await page.locator('[data-deck-category-title]').innerText()).trim();
  const performanceGroups = await page.locator('[data-deck-category-body] .deck-stat-group').count();
  await page.locator('[data-deck-category-next]').click();
  const historyTitle = (await page.locator('[data-deck-category-title]').innerText()).trim();
  const historyRows = await page.locator('[data-deck-category-body] [data-profile-history] tr').count();
  const sectionParam = new URL(page.url()).searchParams.get('deck_section');
  await page.screenshot({ path: 'qa-deck-category-modal-desktop-v13.png', fullPage: false });

  const mobile = await browser.newPage({ viewport: { width: 412, height: 915 } });
  mobile.on('pageerror', (error) => errors.push(error.message));
  mobile.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await mobile.goto(`https://stationxtulua.com/yugioh/?qa=deck-navigation-mobile-${Date.now()}#deck_ficha`, { waitUntil: 'networkidle', timeout: 90000 });
  await mobile.locator('[data-deck-category-open="decklist"]').click();
  await mobile.locator('#deckCategoryModal').waitFor({ state: 'visible', timeout: 10000 });
  await mobile.waitForTimeout(400);
  const modalBox = await mobile.locator('#deckCategoryModal .modal-dialog').boundingBox();
  const mobileOverflow = await mobile.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  await mobile.screenshot({ path: 'qa-deck-category-modal-mobile-v13.png', fullPage: false });
  await browser.close();

  assert(totalRows >= 50, 'El listado debe cargar los decks persistidos.');
  assert.strictEqual(filterCount, 6, 'Deben existir filtros de formato, division, equipo, tier, actividad y orden.');
  assert.strictEqual(nullInputs, 0, 'No se debe mostrar null en los buscadores.');
  assert(goatRows > 0 && goatRows < totalRows && goatCountLabel.startsWith(`${goatRows} de `), 'El filtro GOAT no actualiza filas y contador.');
  assert.strictEqual(restoredRows, totalRows, 'Limpiar debe restaurar todos los decks.');
  assert.deepStrictEqual(orderedNames, orderedNames.slice().sort((a, b) => a.localeCompare(b)), 'El orden alfabetico no se aplica.');
  assert.strictEqual(categoryButtons, 8, 'La ficha debe mostrar ocho categorias.');
  assert.strictEqual(oldTabs, 0, 'La ficha no debe conservar las dos pestanas largas anteriores.');
  assert.strictEqual(performanceTitle, 'Rendimiento competitivo');
  assert(performanceGroups > 0, 'Rendimiento debe conservar sus indicadores.');
  assert.strictEqual(historyTitle, 'Historial y agenda');
  assert(historyRows > 0, 'Historial debe conservar las partidas del deck.');
  assert.strictEqual(sectionParam, 'history', 'La URL debe recordar la categoria abierta.');
  assert(modalBox && Math.abs(modalBox.width - 412) <= 2 && Math.abs(modalBox.height - 915) <= 2, 'El modal movil debe ocupar toda la pantalla.');
  assert.strictEqual(mobileOverflow, false, 'La ficha movil no debe desbordar horizontalmente.');
  assert.strictEqual(errors.length, 0, errors.join('\n'));
  console.log(JSON.stringify({ ok: true, totalRows, filterCount, goatRows, goatCountLabel, categoryButtons, performanceTitle, performanceGroups, historyTitle, historyRows, sectionParam, modalBox, mobileOverflow }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
