const { chromium } = require('playwright');

(async () => {
  const base = process.env.YGO_BASE_URL || 'http://127.0.0.1:8765/';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 384, height: 854 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(`${base}?qa=standard-calculator#resultados`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.locator('[data-result-lp-calculator]').evaluate((node) => {
    node.hidden = false;
    const modal = node.closest('[data-result-capture-modal]');
    if (modal) {
      modal.style.display = 'block';
      modal.classList.add('show');
      modal.removeAttribute('aria-hidden');
    }
  });
  for (const key of ['5', 'multiply', '6', 'equals']) {
    await page.locator(`[data-result-standard-key="${key}"]`).click();
  }
  const result = (await page.locator('[data-result-standard-display]').textContent() || '').trim();
  const metrics = await page.locator('[data-result-standard-calculator]').evaluate((node) => ({
    scrollWidth: node.scrollWidth,
    clientWidth: node.clientWidth,
    rect: node.getBoundingClientRect().toJSON(),
  }));
  await browser.close();
  if (result !== '30' || metrics.scrollWidth > metrics.clientWidth + 1 || metrics.rect.width > 384 || errors.length) {
    throw new Error(JSON.stringify({ result, metrics, errors }));
  }
  console.log(JSON.stringify({ ok: true, result, mobileOverflow: false }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
