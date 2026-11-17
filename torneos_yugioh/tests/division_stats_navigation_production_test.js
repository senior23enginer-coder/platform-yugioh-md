const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(`https://stationxtulua.com/yugioh/?deck_id=676&event_id=15&qa=profile-navigation-${Date.now()}#divisiones`, {
    waitUntil: 'networkidle',
    timeout: 90000
  });
  const divisionScreen = page.locator('[data-screen="divisiones"]:not([hidden])');
  await divisionScreen.waitFor({ state: 'visible', timeout: 20000 });
  const divisionRows = await divisionScreen.locator('[data-division-row]').count();
  const selectorVisible = await divisionScreen.locator('.division-selector-grid').isVisible();
  await divisionScreen.locator('[data-division-scroll]').first().click();
  const activeDivision = divisionScreen.locator('[data-division-detail]:not([hidden])');
  const divisionCategories = await activeDivision.locator('[data-division-category-open]').count();
  await activeDivision.locator('[data-division-category-open="decks"]').click();
  const divisionModal = page.locator('[data-division-category-modal].show');
  await divisionModal.waitFor({ state: 'visible', timeout: 10000 });
  const divisionTitle = (await divisionModal.locator('[data-division-category-title]').innerText()).trim();
  const deckTable = await divisionModal.locator('.division-deck-table').count();
  await divisionModal.locator('[data-division-category-next]').click();
  const processTitle = (await divisionModal.locator('[data-division-category-title]').innerText()).trim();
  await divisionModal.locator('[data-division-category-next]').click();
  const eventsTitle = (await divisionModal.locator('[data-division-category-title]').innerText()).trim();
  await divisionModal.locator('[data-division-category-next]').click();
  const matchesTitle = (await divisionModal.locator('[data-division-category-title]').innerText()).trim();
  const divisionSectionParam = new URL(page.url()).searchParams.get('division_section');
  await page.waitForTimeout(400);
  await divisionModal.screenshot({ path: 'qa-division-category-modal-v20.png' });
  await divisionModal.locator('.btn-close').click();
  await divisionModal.waitFor({ state: 'hidden' });
  await page.waitForTimeout(500);
  await activeDivision.screenshot({ path: 'qa-division-profile-v20.png' });

  await page.goto(`https://stationxtulua.com/yugioh/?deck_id=676&event_id=15&qa=stats-navigation-${Date.now()}#estadisticas`, {
    waitUntil: 'networkidle',
    timeout: 90000
  });
  const statsScreen = page.locator('[data-screen="estadisticas"]:not([hidden])');
  await statsScreen.waitFor({ state: 'visible', timeout: 20000 });
  const statsCategories = await statsScreen.locator('[data-stats-tab]').count();
  const inlineViews = await statsScreen.locator(':scope > .analytics-view:visible').count();
  await statsScreen.locator('[data-stats-tab="summary"]').click();
  const statsModal = page.locator('[data-stats-category-modal].show');
  await statsModal.waitFor({ state: 'visible', timeout: 10000 });
  const statsTitle = (await statsModal.locator('[data-stats-category-title]').innerText()).trim();
  const statsKpis = await statsModal.locator('[data-stats-kpi]').count();
  await statsModal.locator('[data-stats-category-next]').click();
  const rankingTitle = (await statsModal.locator('[data-stats-category-title]').innerText()).trim();
  await statsModal.locator('.btn-close').click();
  await statsModal.waitFor({ state: 'hidden' });
  await page.waitForTimeout(500);
  await statsScreen.screenshot({ path: 'qa-stats-directory-v20.png' });
  await statsScreen.locator('[data-stats-open-filters]').click();
  const filterModal = page.locator('[data-stats-filter-modal].show');
  await filterModal.waitFor({ state: 'visible', timeout: 10000 });
  const statsFilters = await filterModal.locator('[data-stats-filter]').count();
  await filterModal.screenshot({ path: 'qa-stats-filter-modal-v20.png' });
  await filterModal.locator('.btn-close').click();

  const mobile = await browser.newPage({ viewport: { width: 412, height: 915 } });
  mobile.on('pageerror', (error) => errors.push(error.message));
  mobile.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await mobile.goto(`https://stationxtulua.com/yugioh/?qa=profile-navigation-mobile-${Date.now()}#estadisticas`, { waitUntil: 'networkidle', timeout: 90000 });
  await mobile.locator('[data-stats-tab="meta"]').click();
  const mobileModal = mobile.locator('[data-stats-category-modal].show');
  await mobileModal.waitFor({ state: 'visible', timeout: 10000 });
  await mobile.waitForTimeout(500);
  const mobileBox = await mobileModal.locator('.modal-dialog').boundingBox();
  const mobileOverflow = await mobile.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  await mobile.screenshot({ path: 'qa-stats-category-mobile-v20.png', fullPage: false });
  await browser.close();

  assert(divisionRows > 0, 'La tabla debe conservar las divisiones registradas.');
  assert.strictEqual(selectorVisible, false, 'No debe mostrarse el selector duplicado de divisiones.');
  assert.strictEqual(divisionCategories, 4, 'La ficha de division debe mostrar cuatro categorias.');
  assert.strictEqual(divisionTitle, 'Decks y equipos');
  assert.strictEqual(deckTable, 1, 'La categoria debe conservar la tabla de participantes.');
  assert.strictEqual(processTitle, 'Procesos de la division');
  assert.strictEqual(eventsTitle, 'Eventos asociados');
  assert.strictEqual(matchesTitle, 'Combates de la division');
  assert.strictEqual(divisionSectionParam, 'matches');
  assert.strictEqual(statsCategories, 8, 'Estadisticas debe mostrar ocho categorias.');
  assert.strictEqual(inlineViews, 0, 'Las vistas extensas no deben quedar abiertas en la pagina principal.');
  assert.strictEqual(statsTitle, 'Resumen general');
  assert(statsKpis >= 7, 'El resumen debe conservar sus indicadores.');
  assert.strictEqual(rankingTitle, 'Rankings competitivos');
  assert(statsFilters >= 12, 'El modal debe conservar todos los filtros estadisticos.');
  assert(mobileBox && Math.abs(mobileBox.width - 412) <= 2 && Math.abs(mobileBox.height - 915) <= 2, 'La modal movil debe ocupar toda la pantalla.');
  assert.strictEqual(mobileOverflow, false, 'La navegacion movil no debe desbordar horizontalmente.');
  assert.strictEqual(errors.length, 0, errors.join('\n'));
  console.log(JSON.stringify({ ok: true, divisionRows, divisionCategories, divisionTitle, processTitle, eventsTitle, matchesTitle, statsCategories, inlineViews, statsTitle, statsKpis, rankingTitle, statsFilters, mobileBox, mobileOverflow }, null, 2));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
