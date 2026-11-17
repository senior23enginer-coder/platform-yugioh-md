const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1180, height: 850 } });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=deck-cover-${Date.now()}#deck`, {
    waitUntil: 'networkidle',
    timeout: 90000
  });
  await page.locator('[data-deck-list-search]').fill('Purrely');
  const row = page.locator('[data-deck-list-row]:visible');
  await row.locator('[data-open-deck-sheet]').click();
  await page.locator('[data-screen="deck_ficha"]:not([hidden])').waitFor({ state: 'visible' });
  const cover = page.locator('[data-profile-cover]');
  const styles = await cover.evaluate((element) => {
    const computed = getComputedStyle(element);
    return {
      repeat: computed.backgroundRepeat,
      size: computed.backgroundSize,
      position: computed.backgroundPosition,
      image: computed.backgroundImage
    };
  });
  const avatarDisplay = await cover.locator('[data-profile-initials]').evaluate((element) => getComputedStyle(element).display);
  await page.evaluate(async () => {
    const deck = window.DML_DATA?.decks?.find?.((item) => String(item.name).toLowerCase() === 'purrely')
      || Object.values(window.DML_DATA?.divisions || {}).flatMap((division) => division.decks || []).find((item) => String(item.name).toLowerCase() === 'purrely');
    if (!deck?.cover_url) return;
    const image = new Image();
    image.src = new URL(deck.cover_url, window.location.href).href;
    await image.decode();
  });
  await page.waitForTimeout(500);
  await cover.screenshot({ path: 'qa-deck-cover-purrely-v17.png' });
  await browser.close();

  assert.strictEqual(styles.repeat, 'no-repeat, no-repeat');
  assert.strictEqual(styles.size, '100% 100%, contain');
  assert.strictEqual(styles.position, '50% 50%, 50% 50%');
  assert(styles.image.includes('deck-660'), 'La portada de Purrely no fue cargada.');
  assert.strictEqual(avatarDisplay, 'none', 'La inicial no debe cubrir una portada existente.');
  console.log(JSON.stringify({ ok: true, styles, avatarDisplay }, null, 2));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
