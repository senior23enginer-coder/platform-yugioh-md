const { chromium } = require('playwright');

const url = `https://stationxtulua.com/yugioh/?qa=turn-flow-${Date.now()}#resultados`;
const matchId = '5491';

async function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function openPersistedMatch(page) {
  await page.addInitScript(({ matchId }) => {
    localStorage.setItem('dml_result_capture_match_id', matchId);
    localStorage.setItem('dml_result_capture_route', 'resultados');
  }, { matchId });
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
}

async function verifyDuelDetails(page) {
  for (const duel of [1, 2, 3]) {
    const button = page.locator(`[data-result-duel-read="${duel}"]`);
    await assert(await button.count() === 1, `Falta el boton Ver de D${duel}.`);
    await button.click();
    const title = await page.locator('[data-result-duel-read-title]').textContent();
    await assert(String(title).includes(`D${duel}`), `La ficha abierta no corresponde a D${duel}.`);
    await page.locator('[data-result-duel-read-close]').filter({ hasText: 'Cerrar' }).click();
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 384, height: 854 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });

  await openPersistedMatch(page);
  await assert((await page.locator('[data-result-save-state]').textContent()).includes('Turnos guardados'), 'No aparece Turnos guardados al cargar.');
  await verifyDuelDetails(page);
  const portrait = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    modalWidth: document.querySelector('[data-result-capture-modal] .result-capture-form')?.getBoundingClientRect().width || 0,
    horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  }));
  await page.screenshot({ path: 'qa-turns-s25-portrait.png', fullPage: false });

  await page.locator('[data-result-capture-notes]').evaluate((node) => {
    node.value = `${node.value || ''} `;
    node.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await assert((await page.locator('[data-result-save-state]').textContent()).includes('Cambios pendientes'), 'No aparece Cambios pendientes al editar.');

  await page.reload({ waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await assert((await page.locator('[data-result-save-state]').textContent()).includes('Turnos guardados'), 'La recarga no restaura el estado guardado.');

  await page.setViewportSize({ width: 854, height: 384 });
  await page.reload({ waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  await verifyDuelDetails(page);
  const landscape = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    modalWidth: document.querySelector('[data-result-capture-modal] .result-capture-form')?.getBoundingClientRect().width || 0,
    horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  }));
  await page.screenshot({ path: 'qa-turns-s25-landscape.png', fullPage: false });

  await browser.close();
  await assert(errors.length === 0, `Errores del navegador: ${errors.join(' | ')}`);
  await assert(!portrait.horizontalOverflow && portrait.modalWidth <= portrait.viewport + 1, 'Hay desbordamiento horizontal en retrato.');
  await assert(!landscape.horizontalOverflow && landscape.modalWidth <= landscape.viewport + 1, 'Hay desbordamiento horizontal en paisaje.');
  console.log(JSON.stringify({ ok: true, matchId, portrait, landscape, errors }, null, 2));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
