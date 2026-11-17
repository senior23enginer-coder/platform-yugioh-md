const path = require("node:path"),
  fs = require("node:fs"),
  assert = require("node:assert/strict"),
  net = require("node:net"),
  { spawn, spawnSync } = require("node:child_process");
const { chromium } = require("playwright"),
  M = require("../assets/modules/combo-creator/model.js");
const ROOT = path.resolve(__dirname, "../.."),
  APP = path.join(ROOT, "torneos_yugioh"),
  OUT = path.join(ROOT, "outputs/combo-creator-fixes-20261103");
const php = process.env.PHP_BINARY || "php";
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const db = path.join(OUT, `regression-${Date.now()}.sqlite`),
    fixture = JSON.parse(
      fs.readFileSync(
        path.join(__dirname, "fixtures/calculator_inventory.json"),
      ),
    );
  const listener = net.createServer();
  await new Promise((r) => listener.listen(0, "127.0.0.1", r));
  const port = listener.address().port;
  await new Promise((r) => listener.close(r));
  const origin = `http://127.0.0.1:${port}`,
    server = spawn(
      php,
      [
        "-S",
        `127.0.0.1:${port}`,
        "-t",
        APP,
        path.join(__dirname, "combo_creator_router.php"),
      ],
      { env: { ...process.env, COMBO_TEST_DB: db }, stdio: "ignore" },
    );
  let browser;
  try {
    for (let i = 0; i < 80; i++) {
      try {
        if ((await fetch(origin)).ok) break;
      } catch {}
      await new Promise((r) => setTimeout(r, 100));
    }
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1600, height: 1000 },
      }),
      page = await context.newPage(),
      errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await context.route("**/*", (r) =>
      r.request().url().startsWith(origin) ? r.continue() : r.abort(),
    );
    await page.goto(origin, { waitUntil: "networkidle" });
    await page.locator("[data-cc-deck]").selectOption("665");
    await page.locator("[data-cc-work]:visible").waitFor();
    await page.locator("[data-cc-card]").first().click();
    await page.locator("[data-cc-deck]").selectOption("650");
    await page.waitForFunction(
      () => !document.querySelector("[data-cc-new]").disabled,
    );
    assert(await page.locator("[data-cc-insert-card]").isDisabled());
    assert.equal(
      await page.locator('[data-cc-card][aria-pressed="true"]').count(),
      0,
    );
    const session = await (
      await context.request.get(origin + "/?api=combo_creator&deck_id=665")
    ).json();
    let doc = M.create(665);
    doc.title = "Branch regression";
    doc.board_format = "tcg";
    doc.editorial_status = "conditional";
    for (const kind of ["opening", "decision", "step", "step", "result"])
      doc = M.addNode(doc, kind);
    [
      "Opening",
      "Opponent response",
      "Alternative A",
      "Alternative B",
      "Result",
    ].forEach((title, i) => (doc.nodes[i].title = title));
    const own = fixture.cards.find(
      (c) => c.deck_id === 665 && c.section === "main",
    );
    doc.nodes[0].cards = [own.id];
    doc.nodes[4].cards = [own.id];
    doc.nodes[4].placements = [
      {
        card_index: 0,
        zone: "monster_1",
        position: "attack",
        materials: "1 documented material",
      },
    ];
    doc.nodes[4].details = {
      cost: "Documented cost",
      origin: "Hand",
      destination: "Monster zone",
      source: "Manual fixture",
    };
    doc = M.connect(doc, doc.nodes[0].id, doc.nodes[1].id);
    doc = M.connect(doc, doc.nodes[1].id, doc.nodes[2].id, "Only if negated");
    doc = M.connect(doc, doc.nodes[1].id, doc.nodes[3].id, "Only if resolved");
    doc = M.connect(doc, doc.nodes[2].id, doc.nodes[4].id);
    const saved = await (
      await context.request.post(origin + "/?api=combo_creator&deck_id=665", {
        headers: { "X-CSRF-Token": session.token },
        data: {
          deck_id: 665,
          revision: 0,
          inventory_sha256: session.inventory_sha256,
          document: doc,
        },
      })
    ).json();
    assert(saved.ok, JSON.stringify(saved));
    await page.locator("[data-cc-deck]").selectOption("665");
    await page
      .locator(`[data-cc-saved] option[value="${doc.id}"]`)
      .waitFor({ state: "attached" });
    await page.locator("[data-cc-saved]").selectOption(doc.id);
    await page.locator('[data-cc-view="guide"]').click();
    const preview = page.locator("[data-cc-preview]");
    assert.equal(
      await preview.locator(".cc-guide-step:visible h4").first().textContent(),
      "Opening",
    );
    await preview
      .locator("[data-cc-guide-follow]")
      .filter({ visible: true })
      .first()
      .click();
    assert.equal(
      await preview.locator(".cc-guide-step:visible h4").first().textContent(),
      "Opponent response",
    );
    await preview
      .locator(`[data-cc-guide-follow="${doc.nodes[2].id}"]`)
      .click();
    assert.equal(await preview.locator(".cc-guide-step:visible").count(), 1);
    assert.equal(
      await preview.locator(".cc-guide-step:visible h4").first().textContent(),
      "Alternative A",
    );
    await preview
      .locator(`[data-cc-guide-follow="${doc.nodes[4].id}"]`)
      .click();
    assert.equal(
      await preview.locator('[data-cc-zone="monster_1"] img:visible').count(),
      1,
    );
    assert((await preview.textContent()).includes("Documented cost"));
    await preview
      .locator(".cc-guide-step:visible > div .cc-faces img")
      .first()
      .press("Enter");
    assert(await page.locator("dialog.cc-photo-dialog").isVisible());
    await page.keyboard.press("Escape");
    await preview.locator("[data-cc-guide-back]").click();
    await preview.locator("[data-cc-guide-back]").click();
    await preview
      .locator(`[data-cc-guide-follow="${doc.nodes[3].id}"]`)
      .click();
    assert.equal(
      await preview.locator(".cc-guide-step:visible h4").first().textContent(),
      "Alternative B",
    );
    assert.equal(
      await preview.locator(".cc-guide-step.is-result:visible").count(),
      0,
    );
    await preview.locator("[data-cc-guide-all]").click();
    assert.equal(await preview.locator(".cc-guide-step:visible").count(), 5);
    assert(
      (
        await preview
          .locator(".cc-guide-step")
          .evaluateAll((nodes) =>
            nodes.map((n) => getComputedStyle(n, "::before").content),
          )
      ).every((v) => v === "none" || v === "normal"),
    );
    await page.screenshot({
      path: path.join(OUT, "guide-desktop.png"),
      fullPage: true,
    });
    await page.locator('[data-cc-meta="title"]').fill("Revision two");
    let response = page.waitForResponse((r) => r.request().method() === "POST");
    await page.locator("[data-cc-save]").click();
    assert.equal((await (await response).json()).record.revision, 2);
    await page.locator("[data-cc-history]").click();
    await page.locator('[data-cc-restore="1"]').waitFor({ state: "attached" });
    await page
      .locator("[data-cc-history-panel] > details")
      .filter({ has: page.locator('[data-cc-restore="1"]') })
      .locator("> summary")
      .click();
    await page.locator('[data-cc-restore="1"]').click();
    assert.equal(
      await page.locator('[data-cc-meta="title"]').inputValue(),
      "Branch regression",
    );
    response = page.waitForResponse((r) => r.request().method() === "POST");
    await page.locator("[data-cc-save]").click();
    const restored = await (await response).json();
    assert.equal(restored.record.revision, 3);
    assert.equal(
      restored.record.document.nodes[4].placements[0].zone,
      "monster_1",
    );
    const history = await (
      await context.request.get(
        origin + "/?api=combo_creator&deck_id=665&history=" + doc.id,
      )
    ).json();
    assert.equal(history.history.length, 3);
    const foreign = await context.request.get(
      origin + "/?api=combo_creator&deck_id=650&history=" + doc.id,
    );
    assert.equal(foreign.status(), 422);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('[data-cc-view="matrix"]').click();
    const mobile = await page
      .locator(".cc-matrix-scroll")
      .evaluate((e) => ({ width: e.clientWidth, scroll: e.scrollWidth }));
    assert(mobile.scroll <= mobile.width + 1, JSON.stringify(mobile));
    assert.equal(
      await page.evaluate(
        () => document.documentElement.scrollWidth > innerWidth + 1,
      ),
      false,
    );
    await page.screenshot({
      path: path.join(OUT, "matrix-mobile.png"),
      fullPage: true,
    });
    await page.locator("[data-cc-duplicate]").click();
    assert(
      (await page.locator('[data-cc-meta="title"]').inputValue()).includes(
        "copia",
      ),
    );
    assert.equal(await page.locator("[data-cc-saved]").inputValue(), "");
    const change = spawnSync(
      php,
      [
        "-r",
        "$p=new PDO('sqlite:'.getenv('COMBO_TEST_DB'));$p->exec('UPDATE sx_ygo_deck_cards SET quantity=0 WHERE deck_id=665');",
      ],
      { env: { ...process.env, COMBO_TEST_DB: db }, encoding: "utf8" },
    );
    assert.equal(change.status, 0);
    const old = await context.request.get(
      origin + "/?api=combo_creator&deck_id=665",
    );
    assert.equal(old.status(), 200);
    const oldData = await old.json();
    assert.equal(oldData.cards_loaded, false);
    assert.equal(oldData.records.length, 1);
    assert.equal(oldData.records[0].stale, true);
    await page.evaluate(() =>
      YgoComboCreator.mountViewer(
        document.querySelector("[data-deck-created-combos]"),
        { id: 665, name: "Kashtira Pure" },
      ),
    );
    await page.locator("[data-cc-read]").waitFor();
    await page.locator("[data-cc-read]").click();
    assert(await page.locator("[data-cc-create]").isDisabled());
    assert.equal(
      (await page.locator("[data-cc-reading] img").count()) > 0,
      true,
    );
    assert.deepEqual(errors, []);
    const result = {
      ok: true,
      deckSelectionReset: true,
      branchIsolation: true,
      keyboardPhotoZoom: true,
      partialBoard: true,
      technicalFields: true,
      historyRestoreAppends: true,
      foreignHistoryRejected: true,
      mobileMatrix: true,
      duplicateDraft: true,
      historicalReadWithoutMain: true,
      pageErrors: errors,
    };
    fs.writeFileSync(
      path.join(OUT, "regression-result.json"),
      JSON.stringify(result, null, 2),
    );
    console.log(JSON.stringify(result));
  } finally {
    if (browser) await browser.close();
    server.kill();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
