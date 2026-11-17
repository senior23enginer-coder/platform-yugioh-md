const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const php = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
if (!php.includes("phase = 'friendly' AND competition_id IS NULL")) {
  throw new Error('El servidor no limita la eliminacion a amistosos manuales independientes.');
}
for (const table of ['sx_ygo_match_results', 'sx_ygo_turn_audit', 'sx_ygo_live_duel_state', 'sx_ygo_match_snapshots']) {
  if (!php.includes(table)) throw new Error(`Falta limpiar ${table}.`);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 384, height: 854 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.addInitScript(() => localStorage.setItem('dml_screen', 'calendario'));
  await page.goto(`https://stationxtulua.com/yugioh/?qa=friendly-delete-${Date.now()}#calendario`, { waitUntil: 'networkidle', timeout: 60000 });

  const match = await page.evaluate(() => {
    const fixtures = Object.values(window.DML_DATA?.fixtures || {}).flatMap((group) => Array.isArray(group) ? group : Object.values(group || {}));
    const matches = fixtures.flatMap((round) => round.matches || []);
    return matches.find((row) => row.manual_friendly && row.scheduled_on) || null;
  });
  if (!match) throw new Error('No hay un amistoso manual programado para validar la interfaz.');

  const day = page.locator(`[data-calendar-day="${match.scheduled_on}"]`).first();
  await day.evaluate((button) => button.click());
  await page.locator('[data-calendar-day-modal].show').waitFor({ state: 'visible', timeout: 10000 });
  const deleteButton = page.locator(`[data-delete-friendly="${match.id}"]`).first();
  await deleteButton.click();
  await page.locator('[data-delete-friendly-modal].show').waitFor({ state: 'visible', timeout: 10000 });
  const result = {
    id: await page.locator('[data-delete-friendly-match-id]').inputValue(),
    label: (await page.locator('[data-delete-friendly-label]').textContent() || '').trim(),
    submitText: (await page.locator('[data-delete-friendly-form] button[type="submit"]').textContent() || '').trim(),
    overflow: await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1),
  };
  await browser.close();

  if (result.id !== String(match.id) || !result.label.includes(' vs ') || !result.submitText.includes('Eliminar amistoso') || result.overflow || errors.length) {
    throw new Error(JSON.stringify({ match, result, errors }));
  }
  console.log(JSON.stringify({ ok: true, matchId: match.id, result, errors }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
