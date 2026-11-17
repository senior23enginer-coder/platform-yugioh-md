const { chromium } = require('playwright');

async function inspect(page, width, height) {
  await page.setViewportSize({ width, height });
  await page.addInitScript(() => {
    localStorage.setItem('dml_result_capture_match_id', '5499');
    localStorage.setItem('dml_result_capture_route', 'resultados');
  });
  await page.goto(`https://stationxtulua.com/yugioh/?qa=player-bar-${width}-${Date.now()}#resultados`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.locator('[data-result-capture-modal].show').waitFor({ state: 'visible', timeout: 15000 });
  const mobile = width <= 600;
  if (mobile) await page.locator('[data-result-terminals-open]').click();
  return page.evaluate(() => {
    const bar = document.querySelector('[data-result-player-screens]');
    const sheet = document.querySelector('[data-result-terminals-sheet]');
    const mobile = window.innerWidth <= 600;
    const container = mobile ? document.querySelector('.result-terminals-panel') : bar;
    const body = document.querySelector('[data-result-capture-modal] .result-console-body');
    const duel = document.querySelector('[data-result-capture-modal] .result-duel-section');
    const barBox = bar.getBoundingClientRect();
    const containerBox = container.getBoundingClientRect();
    const bodyBox = body.getBoundingClientRect();
    const duelBox = duel ? duel.getBoundingClientRect() : null;
    const controls = [...bar.querySelectorAll('.result-player-screen-actions a, .result-player-screen-actions button')].map((node) => {
      const box = node.getBoundingClientRect();
      return { text: node.textContent.trim(), left: box.left, right: box.right, top: box.top, bottom: box.bottom };
    });
    return {
      barWidth: barBox.width,
      bodyWidth: bodyBox.width,
      separated: !duelBox || barBox.bottom <= duelBox.top + 1,
      labels: [...bar.querySelectorAll('.result-player-screen-group > strong')].map((node) => node.textContent.trim()),
      controls,
      contained: controls.every((box) => box.left >= containerBox.left - 1 && box.right <= containerBox.right + 1 && box.top >= containerBox.top - 1 && box.bottom <= containerBox.bottom + 1),
      terminalButtonVisible: getComputedStyle(document.querySelector('[data-result-terminals-open]')).display !== 'none',
      terminalsOpen: sheet.classList.contains('is-open'),
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    };
  });
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const desktopPage = await browser.newPage();
  const desktop = await inspect(desktopPage, 1440, 900);
  await desktopPage.screenshot({ path: 'qa-judge-player-bar-desktop.png', fullPage: false });
  const mobilePage = await browser.newPage();
  const mobile = await inspect(mobilePage, 384, 854);
  await mobilePage.screenshot({ path: 'qa-judge-player-bar-mobile.png', fullPage: false });
  await browser.close();
  const baseValid = (row) => row.labels.join('|') === 'Terminal A|Terminal B' && row.contained && !row.overflow;
  if (!baseValid(desktop) || !baseValid(mobile) || !desktop.separated || desktop.terminalButtonVisible || !mobile.terminalButtonVisible || !mobile.terminalsOpen || desktop.barWidth < desktop.bodyWidth * 0.9) {
    throw new Error(JSON.stringify({ desktop, mobile }));
  }
  console.log(JSON.stringify({ ok: true, desktop, mobile }, null, 2));
})().catch((error) => { console.error(error); process.exit(1); });
