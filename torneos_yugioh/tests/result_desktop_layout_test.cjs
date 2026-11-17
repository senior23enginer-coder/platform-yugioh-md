// Presentation-only test against rendered markup. Scripts and all non-GET
// requests are blocked: no dice, live-session or result APIs are executed.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const out = path.join(root, 'outputs/result-desktop-20261108');
(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ serviceWorkers: 'block' });
    await context.route('**/*', route => {
      const r = route.request();
      return r.method() !== 'GET' || ['script', 'image', 'media'].includes(r.resourceType())
        ? route.abort() : route.continue();
    });
    const page = await context.newPage();
    await page.goto('https://stationxtulua.com/yugioh/', { waitUntil: 'load', timeout: 90000 });
    await page.evaluate(() => {
      document.querySelectorAll('.modal.show').forEach(n => n.classList.remove('show'));
      const modal = document.querySelector('#resultCaptureModal');
      modal.classList.add('show'); modal.style.display = 'block';
      document.body.classList.add('modal-open');
      const put = (s, text) => modal.querySelector(s).textContent = text;
      put('[data-result-capture-title]', 'Ronda 1 \u2014 Mesa 3 | Liga Apex');
      put('[data-result-capture-meta]', '2026-11-05 19:00 | 1V1 | Mejor de 5');
      put('.result-side-a strong', 'Thornveil Brood / Traptrix Ragnaraika');
      put('.result-side-b strong', 'Darknet Protocol / Maliss');
      put('[data-result-player-link="A"]', 'Abrir');
      put('.result-player-screen-group > strong', 'Terminal Thornveil Brood / Traptrix Ragnaraika (Lado A)');
      put('[data-result-turn-choice-question]', 'Lado A gan\u00f3 el dado. \u00bfQu\u00e9 elige?');
      put('[data-result-capture-dice-summary]', 'Lado A sac\u00f3 4, lado B sac\u00f3 3. Elige primero o segundo.');
      const card = modal.querySelector('[data-result-duel-card="1"]');
      card.classList.add('is-active');
      card.querySelector('.result-duel-setup').hidden = false;
      card.querySelector('.result-turn-ledger').hidden = false;
      modal.querySelector('[data-result-turn-choice-panel]').hidden = false;
    });
    const metrics = () => page.locator('#resultCaptureModal').evaluate(el => {
      const rect = s => { const r = el.querySelector(s).getBoundingClientRect(); return { x:r.x, y:r.y, width:r.width, height:r.height, bottom:r.bottom }; };
      const visible = s => !!el.querySelector(s).getClientRects().length && getComputedStyle(el.querySelector(s)).display !== 'none';
      const body = el.querySelector('.modal-body');
      return { form:rect('.modal-content'), head:rect('.modal-header'), body:rect('.modal-body'), footer:rect('.modal-footer'),
        overflow:body.scrollWidth > body.clientWidth + 1,
        start:rect('[data-result-duel-card="1"] .result-duel-start-box'),
        ledger:rect('[data-result-duel-card="1"] .result-turn-ledger'),
        launch:rect('[data-result-duel-card="1"] .result-duel-launch'),
        ledgerVisible:visible('[data-result-duel-card="1"] .result-turn-ledger'),
        globalChoiceVisible:visible('[data-result-turn-choice-panel]'),
        choiceFont:getComputedStyle(el.querySelector('.result-turn-ledger-actions button')).fontSize };
    });
    // New rules must leave the existing phone layout byte-for-byte equivalent
    // in its computed geometry, including the mobile sheets and footer.
    const mobile = [];
    for (const width of [390, 760]) {
      await page.setViewportSize({ width, height:844 });
      mobile.push(await metrics());
    }
    const preview = process.env.RESULT_DESKTOP_PRODUCTION !== '1';
    if (preview) await page.addStyleTag({ path:path.join(root, 'torneos_yugioh/assets/result-desktop.css') });
    for (const [i, width] of [390, 760].entries()) {
      await page.setViewportSize({ width, height:844 });
      assert.deepEqual(await metrics(), mobile[i], 'Mobile layout changed');
    }
    const results = [];
    for (const width of [2560, 1952, 1440, 1100, 1024]) {
      await page.setViewportSize({ width, height:1000 });
      await page.evaluate(() => {
        const modal = document.querySelector('#resultCaptureModal');
        modal.classList.remove('opening-resolved');
        modal.querySelector('[data-result-turn-choice-panel]').hidden = false;
        modal.querySelector('[data-result-duel-card="1"] .result-duel-launch').hidden = true;
      });
      let m = await metrics();
      assert(m.form.width <= 1440 && m.form.x >= 23, JSON.stringify(m));
      assert(m.head.bottom <= m.body.y + 1 && m.body.bottom <= m.footer.y + 1, JSON.stringify(m));
      assert(m.footer.bottom <= 977 && !m.overflow, JSON.stringify(m));
      if (width >= 761) assert(m.globalChoiceVisible && !m.ledgerVisible, 'Duplicate D1 choice');
      await page.locator('.result-console-body').evaluate(n => n.scrollTop = 0);
      await page.screenshot({ path:path.join(out, `${preview?'preview':'production'}-opening-${width}.png`), timeout:30000 });
      await page.evaluate(() => {
        const modal = document.querySelector('#resultCaptureModal');
        modal.classList.add('opening-resolved');
        modal.querySelector('[data-result-turn-choice-panel]').hidden = true;
        modal.querySelector('[data-result-duel-card="1"] .result-duel-launch').hidden = false;
        modal.querySelector('[data-result-duel-first="1"]').textContent = 'Thornveil Brood / Traptrix Ragnaraika (Lado A)';
        modal.querySelector('[data-result-duel-second="1"]').textContent = 'Darknet Protocol / Maliss (Lado B)';
        modal.querySelector('[data-result-duel-card="1"] .result-turn-ledger > span').textContent = 'Lado A eligi\u00f3 primero. Puedes corregir la elecci\u00f3n antes de comenzar.';
      });
      m = await metrics();
      assert(!m.overflow && m.ledgerVisible);
      if (width >= 761) {
        assert.equal(m.start.y, m.ledger.y, 'Preparation columns must share a row');
        assert(m.launch.y >= Math.max(m.start.bottom, m.ledger.bottom), 'Launch must follow both preparation panels');
        assert(parseFloat(m.choiceFont) >= 13.5, 'Desktop controls too small');
      }
      results.push({ width, ...m });
      // Scroll the actual body, not the page, to verify the launch is reachable.
      await page.locator('[data-result-duel-card="1"]').scrollIntoViewIfNeeded();
      await page.screenshot({ path:path.join(out, `${preview?'preview':'production'}-${width}.png`), timeout:30000 });
      await page.evaluate(() => {
        const card = document.querySelector('[data-result-duel-card="1"]');
        card.querySelector('.result-duel-setup').hidden = true;
        card.querySelector('.result-duel-live').hidden = false;
      });
      assert(!(await metrics()).overflow, 'Running duel overflows');
      assert(await page.locator('[data-result-duel-card="1"]').evaluate(card => {
        const lp = card.querySelector('.result-lp-grid').getBoundingClientRect();
        const participants = card.querySelector('.result-participant-grid').getBoundingClientRect();
        return lp.bottom <= participants.top;
      }), 'LP and participant fields overlap during a running duel');
      // T1: editable hands only; T2: previous hand summary and rival control;
      // T3+: summary only. Both blocks legitimately coexist during T2.
      for (const turn of [1, 2, 3]) {
        await page.locator('[data-result-duel-card="1"]').evaluate((card, turn) => {
          card.querySelector('.result-brick-fieldset').hidden = turn >= 3;
          card.querySelector('.result-brick-summary').hidden = turn === 1;
          card.querySelector('[data-result-brick-side="A"]').hidden = turn === 2;
          const label = card.querySelector('[data-result-brick-side="B"]');
          [...label.childNodes].filter(n => n.nodeType === 3).forEach(n => n.remove());
          label.append('Darknet Protocol / Maliss (Lado B) tuvo brick');
          for (const [side, name] of [['a', 'Thornveil Brood / Traptrix Ragnaraika (Lado A)'], ['b', 'Darknet Protocol / Maliss (Lado B)']]) {
            const input = card.querySelector(`[data-result-capture-lp-${side}]`);
            [...input.parentElement.childNodes].filter(n => n.nodeType === 3).forEach(n => n.remove());
            input.parentElement.prepend('LP final ' + name);
          }
          card.querySelector('.result-brick-summary strong').textContent = 'Thornveil Brood / Traptrix Ragnaraika (Lado A): No' + (turn >= 3 ? ' | Darknet Protocol / Maliss (Lado B): No' : '');
        }, turn);
        assert(await page.locator('[data-result-duel-card="1"]').evaluate(card => {
          const nodes = ['.result-outcome-sheet', '.result-brick-fieldset', '.result-brick-summary', '.result-lp-grid', '.result-participant-grid', '.result-lp-open', '.result-duel-note-field']
            .map(s => card.querySelector(s)).filter(n => n.getClientRects().length && getComputedStyle(n).display !== 'none');
          const boxes = nodes.map(n => n.getBoundingClientRect());
          return boxes.every((a,i) => boxes.slice(i+1).every(b => Math.min(a.right,b.right)-Math.max(a.left,b.left)<=1 || Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)<=1));
        }), `Running panels overlap at width ${width}, turn ${turn}`);
        assert(!(await metrics()).overflow, `Turn ${turn} overflows at ${width}`);
        assert(await page.locator('[data-result-duel-card="1"] > .result-lp-grid:not(.result-participant-grid)').evaluate(grid => {
          const inputs = grid.querySelectorAll('input');
          return Math.abs(inputs[0].getBoundingClientRect().y - inputs[1].getBoundingClientRect().y) <= 1;
        }), 'LP inputs must align despite long participant names');
        if (width === 1440 || width === 1952) {
          await page.locator('[data-result-duel-card="1"]').scrollIntoViewIfNeeded();
          await page.screenshot({ path:path.join(out, `${preview?'preview':'production'}-running-${width}-t${turn}.png`), timeout:30000 });
        }
      }
      await page.evaluate(() => {
        const card = document.querySelector('[data-result-duel-card="1"]');
        card.querySelector('.result-duel-setup').hidden = false;
        card.querySelector('.result-duel-live').hidden = true;
      });
    }
    // D2 and later keep their choice even when the D1 opening panel is visible.
    await page.evaluate(() => {
      const m = document.querySelector('#resultCaptureModal');
      m.querySelector('[data-result-turn-choice-panel]').hidden = false;
      const d2 = m.querySelector('[data-result-duel-card="2"]');
      d2.classList.add('is-active');
      d2.querySelector('.result-turn-ledger').hidden = false;
    });
    assert(await page.locator('[data-result-duel-card="2"] .result-turn-ledger').isVisible());
    const report = { ok:true, preview, mobileUnchanged:true, productionWrites:0, results };
    fs.writeFileSync(path.join(out, `${preview?'preview':'production'}.json`), JSON.stringify(report,null,2));
    console.log(JSON.stringify(report));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
