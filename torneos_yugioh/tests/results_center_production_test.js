const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=results-center-${Date.now()}#resultados`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-screen="resultados"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });
  await page.locator('[data-result-queue] tr').first().waitFor({ state: 'visible' });

  const headers = (await page.locator('.result-queue-table thead th').allTextContents()).map((text) => text.trim());
  const initialRows = await page.locator('[data-result-queue] tr').count();
  const versusCount = await page.locator('[data-result-queue] .result-versus-cell').count();
  const initialActions = await page.locator('[data-result-pick]').allTextContents();
  const futureRowShadows = await page.locator('[data-result-queue] tr.timing-future, [data-result-queue] tr.timing-unscheduled').evaluateAll((rows) => rows.map((row) => getComputedStyle(row).boxShadow));
  const selectedCellShadows = await page.locator('[data-result-queue] tr.selected td').evaluateAll((cells) => cells.map((cell) => getComputedStyle(cell).boxShadow));
  await page.locator('[data-result-status]').selectOption('FINALIZADA');
  const finalRows = await page.locator('[data-result-queue] tr').count();
  const finalActions = await page.locator('[data-result-pick]').allTextContents();
  await page.locator('[data-result-clear-filters]').click();
  const restoredPhase = await page.locator('[data-result-phase]').inputValue();
  await page.screenshot({ path: 'qa-results-center-desktop-v11.png', fullPage: false });

  await page.setViewportSize({ width: 412, height: 915 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.locator('[data-result-queue] tr').first().waitFor({ state: 'visible' });
  const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  const mobileCards = await page.locator('[data-result-queue] tr').count();
  await page.screenshot({ path: 'qa-results-center-mobile-v11.png', fullPage: false });
  await browser.close();

  assert.deepStrictEqual(headers, ['Programacion', 'Enfrentamiento', 'Estado', 'Marcador', 'Accion']);
  assert(initialRows > 0 && versusCount === initialRows, 'Cada fila debe mostrar un enfrentamiento unificado.');
  assert(futureRowShadows.length > 0 && futureRowShadows.every((shadow) => shadow === 'none'), 'Las filas futuras no deben mostrar lineas amarillas.');
  assert(selectedCellShadows.every((shadow) => !/255,\s*(210|211),\s*(92|95)/.test(shadow)), 'La fila seleccionada no debe repetir lineas amarillas entre columnas.');
  assert(initialActions.every((label) => /Capturar|Continuar|Revisar/.test(label)), 'Las acciones no reflejan el estado.');
  assert(finalRows > 0 && finalActions.every((label) => /Revisar/.test(label)), 'Los resultados finalizados deben indicar Revisar.');
  assert.strictEqual(restoredPhase, '', 'Limpiar debe quitar el filtro de fase.');
  assert(mobileCards > 0 && !mobileOverflow, 'La vista movil debe mostrar tarjetas sin desbordamiento.');
  assert.strictEqual(errors.length, 0, errors.join('\n'));
  console.log(JSON.stringify({ ok: true, headers, initialRows, versusCount, futureRowShadows, selectedCellShadows, finalRows, restoredPhase, mobileCards, mobileOverflow, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
