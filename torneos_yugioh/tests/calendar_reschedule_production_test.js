const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(`https://stationxtulua.com/yugioh/?qa=calendar-reschedule-${Date.now()}#calendario`, {
    waitUntil: 'networkidle',
    timeout: 90000
  });
  await page.locator('[data-screen="calendario"]:not([hidden])').waitFor({ state: 'visible', timeout: 20000 });
  const incompleteEvent = page.locator('.calendar-day.date-incomplete [data-calendar-event-id]').first();
  await incompleteEvent.click();
  const modal = page.locator('[data-calendar-modal].show');
  await modal.waitFor({ state: 'visible', timeout: 10000 });

  const editButton = modal.locator('[data-edit-calendar-match]').first();
  await editButton.waitFor({ state: 'visible', timeout: 10000 });
  const selectedId = await editButton.getAttribute('data-edit-calendar-match');
  await editButton.click();
  const form = modal.locator('[data-calendar-modal-form]');
  const formMatchId = await form.locator('[data-calendar-modal-match-id]').inputValue();
  const dateValue = await form.locator('[data-calendar-modal-date]').inputValue();
  const dateRequired = await form.locator('[data-calendar-modal-date]').getAttribute('required');
  const title = (await form.locator('[data-calendar-modal-form-title]').innerText()).trim();
  const opponent = (await form.locator('[data-calendar-modal-form-meta]').innerText()).trim();
  const submitText = (await form.locator('button[type="submit"]').innerText()).trim();
  const selectedCards = await modal.locator('[data-calendar-schedule-card].is-selected').count();
  await modal.screenshot({ path: 'qa-calendar-reschedule-v19.png' });

  const mobile = await browser.newPage({ viewport: { width: 412, height: 915 } });
  mobile.on('pageerror', (error) => errors.push(error.message));
  mobile.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await mobile.goto(`https://stationxtulua.com/yugioh/?qa=calendar-reschedule-mobile-${Date.now()}#calendario`, {
    waitUntil: 'networkidle',
    timeout: 90000
  });
  await mobile.locator('.calendar-day.date-incomplete [data-calendar-event-id]').first().click();
  const mobileModal = mobile.locator('[data-calendar-modal].show');
  await mobileModal.locator('[data-edit-calendar-match]').first().click();
  const mobileOverflow = await mobile.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  const mobileFormVisible = await mobileModal.locator('[data-calendar-modal-form]').isVisible();
  const mobileFormBox = await mobileModal.locator('[data-calendar-modal-form]').boundingBox();
  await mobile.screenshot({ path: 'qa-calendar-reschedule-mobile-v19.png', fullPage: false });
  await browser.close();

  assert(selectedId, 'Debe existir al menos un duelo pendiente que se pueda reprogramar.');
  assert.strictEqual(formMatchId, selectedId, 'El formulario debe editar exactamente el duelo seleccionado.');
  assert(/^\d{4}-\d{2}-\d{2}$/.test(dateValue), 'El formulario debe cargar la fecha actual del duelo.');
  assert.notStrictEqual(dateRequired, null, 'La nueva fecha debe ser obligatoria.');
  assert(/^R.+\| M/.test(title), 'Debe identificarse la ronda y mesa seleccionadas.');
  assert(opponent.includes(' vs '), 'Debe identificarse el enfrentamiento seleccionado.');
  assert.strictEqual(submitText, 'Guardar nueva fecha');
  assert.strictEqual(selectedCards, 1, 'Solo un duelo puede estar seleccionado para editarse.');
  assert.strictEqual(mobileOverflow, false, 'La reprogramacion movil no debe desbordar horizontalmente.');
  assert.strictEqual(mobileFormVisible, true, 'El formulario de reprogramacion debe estar disponible en movil.');
  assert(mobileFormBox && mobileFormBox.y < 300, 'Al elegir un duelo, el formulario movil debe quedar a la vista.');
  assert.strictEqual(errors.length, 0, errors.join('\n'));
  console.log(JSON.stringify({ ok: true, selectedId, formMatchId, dateValue, title, opponent, submitText, selectedCards, mobileOverflow, mobileFormVisible, mobileFormBox }, null, 2));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
