// Local browser regression: real guide modules and all 11 records; no database/network access.
const { chromium } = require("playwright");
const http = require("node:http"),
  fs = require("node:fs"),
  path = require("node:path"),
  assert = require("node:assert/strict");
const app = path.resolve(__dirname, ".."),
  out = path.resolve(__dirname, "../../outputs/guide-scope");
const records = fs
  .readdirSync(path.join(app, "assets/editorial/20261101-11-decks"))
  .filter((n) => /^deck-\d+\.json$/.test(n))
  .map((n) =>
    JSON.parse(
      fs.readFileSync(
        path.join(app, "assets/editorial/20261101-11-decks", n),
        "utf8",
      ),
    ),
  );
const snapshot = JSON.stringify(records),
  allDecks = records.map((r) => ({
    id: r.deckId,
    name: r.name,
    format_codes: [r.format],
  }));
allDecks.push({ id: 647, name: "Branded", format_codes: ["tcg_advanced"] });
const html = `<!doctype html><html lang="es"><meta charset="utf-8"><title>Guía por deck</title>
 <link rel="stylesheet" href="/assets/modules/deck-guide/learning-guide.css"><style>body{margin:0;background:#07101f;color:#eaf3ff;font-family:system-ui}main{max-width:1500px;margin:auto;padding:20px}button,select,input{font:inherit}button,select{color:#eaf3ff;background:#10263a;border:1px solid #285771;border-radius:8px;padding:10px;cursor:pointer}h1{font-size:1.4rem}summary{cursor:pointer}img{max-width:100%}</style>
 <main><h1>Ficha del deck · Guía de juego</h1><div class="modal-body"><div class="deck-strategy-profile"></div></div></main>
 <script>window.DML_DATA={allDecks:${JSON.stringify(allDecks)},deckAnalysisProfiles:[]};</script>
 <script src="/assets/modules/deck-guide/strategy-workspace.js"></script><script src="/assets/modules/deck-guide/deck-deliveries.js"></script><script src="/assets/modules/deck-guide/deck-study.js"></script><script src="/assets/modules/deck-guide/learning-guide.js"></script></html>`;
const server = http.createServer((req, res) => {
  const u = new URL(req.url, "http://localhost");
  if (u.pathname === "/") {
    if (u.searchParams.get("op") === "image") {
      res.writeHead(200, { "Content-Type": "image/svg+xml" });
      return res.end(
        '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="146"><rect width="100" height="146" fill="#243d5c"/></svg>',
      );
    }
    res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
    return res.end(html);
  }
  const file = path.resolve(app, "." + decodeURIComponent(u.pathname));
  if (
    !file.startsWith(app + path.sep) ||
    !fs.existsSync(file) ||
    !fs.statSync(file).isFile()
  ) {
    res.writeHead(404);
    return res.end();
  }
  const ext = path.extname(file),
    mime = {
      ".js": "application/javascript",
      ".css": "text/css",
      ".json": "application/json",
      ".txt": "text/plain",
      ".png": "image/png",
    };
  res.writeHead(200, {
    "Content-Type":
      (mime[ext] || "application/octet-stream") + "; charset=utf-8",
  });
  fs.createReadStream(file).pipe(res);
});
(async () => {
  fs.mkdirSync(out, { recursive: true });
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  const browser = await chromium.launch(),
    page = await browser.newPage({ viewport: { width: 1600, height: 1050 } }),
    errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  const base = `http://127.0.0.1:${server.address().port}`;
  await page.route("**/*", (r) =>
    r.request().url().startsWith(base) ? r.continue() : r.abort(),
  );
  try {
    await page.goto(base);
    // Reject a valid record for the wrong deck, then allow a retry.
    await page.route("**/deck-687.json*", (r) =>
      r.fulfill({ json: records.find((r) => r.deckId === 656) }),
    );
    assert.match(
      await page.evaluate(() =>
        YgoDeckDeliveries.load(687).then(
          () => "",
          (e) => e.message,
        ),
      ),
      /no corresponde/,
    );
    await page.unroute("**/deck-687.json*");
    const coverage = [];
    for (const record of records) {
      await page.evaluate((id) => {
        const d = DML_DATA.allDecks.find((d) => d.id === id);
        YgoDeckStrategy.render(
          d,
          {
            revision: 1,
            play_style: "PERFIL ANTERIOR",
            primary_game_plan: "PLAN ANTERIOR",
          },
          {},
        );
      }, record.deckId);
      const guide = page.locator(".learning-guide");
      await guide.locator('[data-learning-nav="training"]').waitFor();
      assert.equal(
        await guide.locator("h2").textContent(),
        "Aprende a jugar " + record.name,
      );
      assert.equal(
        await guide.locator(".learn-style strong").textContent(),
        record.profile.play_style,
      );
      assert(!(await guide.innerText()).includes("PLAN ANTERIOR"));
      assert.match(
        await guide
          .locator(".learn-source-tools a[download]")
          .getAttribute("href"),
        new RegExp(`deck-${record.deckId}\\.json`),
      );
      await guide.locator('[data-learning-nav="combos"]').click();
      assert.equal(await guide.locator("iframe,.learn-atlas").count(), 0);
      assert.equal(
        await guide.locator("[data-learn-combo] option").count(),
        record.combos.length,
      );
      for (let i = 0; i < record.combos.length; i++) {
        const combo = record.combos[i];
        await guide.locator("[data-learn-combo]").selectOption(String(i));
        assert.equal(
          await guide.locator("[data-learn-step]").count(),
          combo.steps.length,
        );
        assert.equal(
          (await guide.locator(".learn-start-conditions").innerText()).replace(
            /\s+/g,
            " ",
          ),
          combo.conditions.replace(/\s+/g, " "),
        );
        if (combo.detail?.result)
          assert(
            (await guide.locator(".learn-combo-overview").innerText()).includes(
              combo.detail.result,
            ),
          );
        const imageIds = await guide
          .locator('img[src*="op=image"]')
          .evaluateAll((imgs) =>
            imgs.map((i) => new URL(i.src).searchParams.get("id")),
          );
        assert(
          imageIds.every((id) => record.card_metadata[id]),
          "Card image must belong to current record",
        );
        if (combo.executable === false)
          assert(
            (
              await guide.locator(".learn-warning").first().innerText()
            ).includes("NO EJECUTABLE"),
          );
      }
      await guide.locator("[data-learn-combo]").selectOption("0");
      await guide.locator(".learn-technical-data>summary").click();
      await guide
        .getByText("Restricciones, procedencia y detalles restantes", {
          exact: true,
        })
        .click();
      assert.equal(
        await guide
          .locator(".learn-technical-data .learn-delivery-fields>details")
          .count(),
        0,
      );
      if (record.combos[0].detail?.source_entry_id)
        assert(
          (await guide.locator(".learn-technical-data").innerText()).includes(
            "Referencia en el documento",
          ),
        );
      if (record.deckId === 687) {
        await page.screenshot({
          path: path.join(out, "volcanic-desktop.png"),
          fullPage: true,
        });
      }
      await guide.locator('[data-learning-nav="cards"]').click();
      assert.equal(
        await guide.locator("[data-learn-card-row]").count(),
        record.cards.length,
      );
      assert.equal(
        await guide.locator("[data-card-field]").count(),
        record.cards.length * 18,
      );
      assert((await guide.innerText()).includes("Cómo utilizar esta carta"));
      await guide.locator('[data-learning-nav="training"]').click();
      assert.equal(
        await guide.locator("[data-learn-hand] option").count(),
        record.hands.length,
      );
      let topics = 0;
      for (const area of ["plan", "build", "consistency", "practice"]) {
        await guide.locator(`[data-learning-nav="${area}"]`).click();
        topics += await guide.locator("[data-study-topic]").count();
      }
      assert.equal(topics, record.topics.length);
      await guide.locator("[data-learn-document]").click();
      assert.equal(
        await guide.locator(".learn-section-heading h3").textContent(),
        "Biblioteca de " + record.name,
      );
      assert(await guide.locator('[data-library-entry="doc:atlas"]').count());
      await guide.locator('[data-library-entry="doc:atlas"]').click();
      assert.equal(
        await guide
          .locator(
            "[data-library-content] iframe,[data-library-content] script,[data-library-content] nav,[data-library-content] aside",
          )
          .count(),
        0,
      );
      assert(
        await guide
          .locator("[data-library-content]")
          .evaluate(
            (el, id) =>
              [...el.querySelectorAll("[id]")].every((n) =>
                n.id.startsWith("source-" + id + "-"),
              ),
            record.deckId,
          ),
      );
      await guide
        .locator("[data-library-search]")
        .fill("Comprobaciones de las jugadas");
      assert.equal(
        await guide.locator('[data-library-entry="route-checks"]').count(),
        1,
      );
      await guide.locator("[data-library-search]").fill("");
      assert.equal(await guide.locator("iframe").count(), 0);
      await page.setViewportSize({ width: 390, height: 844 });
      await guide.locator('[data-learning-nav="combos"]').click();
      const overflow = await guide.evaluate((el) =>
        [...el.querySelectorAll("*")]
          .filter((x) => x.scrollWidth > x.clientWidth + 2)
          .slice(0, 8)
          .map((x) => [x.className, x.clientWidth, x.scrollWidth]),
      );
      assert(
        await guide.evaluate((el) => el.scrollWidth <= el.clientWidth + 1),
        "Mobile overflow: " + record.deckId + " " + JSON.stringify(overflow),
      );
      if (record.deckId === 687)
        await page.screenshot({
          path: path.join(out, "volcanic-mobile.png"),
          fullPage: true,
        });
      await page.setViewportSize({ width: 1600, height: 1050 });
      coverage.push({
        deckId: record.deckId,
        routes: record.combos.length,
        cards: record.cards.length,
        topics,
      });
    }
    // Slow prior selection must not overwrite the final deck.
    await page.reload();
    await page.route("**/deck-656.json*", async (r) => {
      await new Promise((done) => setTimeout(done, 150));
      return r.continue();
    });
    await page.evaluate(() => {
      for (const id of [656, 687])
        YgoDeckStrategy.render(
          DML_DATA.allDecks.find((d) => d.id === id),
          { revision: 2 },
          {},
        );
    });
    await page.locator('[data-learning-nav="training"]').waitFor();
    await page.waitForTimeout(200);
    assert.equal(
      await page.locator(".learn-hero h2").textContent(),
      "Aprende a jugar Volcanic",
    );
    // Other catalog decks retain the existing fallback.
    await page.evaluate(() =>
      YgoDeckStrategy.render(
        DML_DATA.allDecks.find((d) => d.id === 647),
        { revision: 1 },
        {},
      ),
    );
    await page.waitForFunction(() =>
      document.querySelector('[data-learning-nav="plan"]'),
    );
    assert.equal(
      await page.locator('[data-learning-nav="training"]').count(),
      0,
    );
    // A failed or mismatched response stays in this deck and can be retried.
    await page.reload();
    await page.route("**/deck-687.json*", (r) =>
      r.fulfill({ json: records.find((r) => r.deckId === 665) }),
    );
    await page.evaluate(() =>
      YgoDeckStrategy.render(
        DML_DATA.allDecks.find((d) => d.id === 687),
        { revision: 3 },
        {},
      ),
    );
    await page.locator('.learn-pane [role="alert"]').waitFor();
    assert.equal(
      await page.locator(".learn-pane [data-learn-document]").count(),
      0,
    );
    assert.equal(await page.locator(".learn-pane iframe").count(), 0);
    await page.unroute("**/deck-687.json*");
    await page.locator("[data-learn-retry]").click();
    await page.locator('[data-learning-nav="training"]').waitFor();
    assert.equal(
      await page.locator(".learn-hero h2").textContent(),
      "Aprende a jugar Volcanic",
    );
    assert.deepEqual(errors, []);
    assert.equal(JSON.stringify(records), snapshot);
    const result = {
      ok: true,
      coverage,
      errors,
      productionAccess: false,
      sourceRecordsModified: false,
    };
    fs.writeFileSync(
      path.join(out, "result.json"),
      JSON.stringify(result, null, 2),
    );
    console.log(
      JSON.stringify({
        ok: true,
        decks: coverage.length,
        routes: coverage.reduce((sum, d) => sum + d.routes, 0),
        errors,
      }),
    );
  } finally {
    await browser.close();
    await new Promise((r) => server.close(r));
  }
})().catch((e) => {
  console.error(e);
  server.close();
  process.exitCode = 1;
});
