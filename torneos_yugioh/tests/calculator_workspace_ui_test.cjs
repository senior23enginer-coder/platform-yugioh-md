const { chromium } = require("playwright"),
  fs = require("node:fs"),
  path = require("node:path"),
  http = require("node:http"),
  assert = require("node:assert/strict");
const app = path.resolve(__dirname, "..");
const data = JSON.parse(
  fs.readFileSync(
    path.join(app, "tests/fixtures/calculator_inventory.json"),
    "utf8",
  ),
);
data.allDecks.push(
  {
    id: 9001,
    name: "Deck sin cartas",
    team: "Equipo prueba",
    format_codes: ["tcg_advanced"],
  },
  { id: 9002, name: "Formato sin tablero", format_codes: ["tcg_traditional"] },
);
const scripts = ["engine", "storage", "hand-practice", "planner", "card-menu", "ui", "workspace"];
const html =
  '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:12px;background:#071222;color:#eee;font:16px sans-serif}*{box-sizing:border-box}button,select,input{max-width:100%;font:inherit}label{display:grid}button{padding:8px}</style><link rel="stylesheet" href="/assets/modules/play-calculator/styles.css"><link rel="stylesheet" href="/assets/modules/play-calculator/workspace.css"><link rel="stylesheet" href="/assets/modules/play-calculator/card-menu.css">' +
  fs
    .readFileSync(
      path.join(app, "includes/decks/calculator-workspace-view.php"),
      "utf8",
    )
    .replace(" hidden", "") +
  "<script>window.DML_DATA=" +
  JSON.stringify(data) +
  ";window.YgoDeckDeliveries={load:async()=>null};</script>" +
  scripts
    .map(
      (n) =>
        '<script src="/assets/modules/play-calculator/' + n + '.js"></script>',
    )
    .join("");
(async () => {
  const server = http.createServer((req, res) => {
    const u = new URL(req.url, "http://test");
    if (u.pathname === "/") {
      res.setHeader("Content-Type", "text/html; charset=utf-8");
      return res.end(html);
    }
    const f = path.join(app, u.pathname);
    if (!f.startsWith(app + path.sep) || !fs.existsSync(f)) {
      res.writeHead(404);
      return res.end();
    }
    res.setHeader(
      "Content-Type",
      f.endsWith(".js")
        ? "application/javascript"
        : f.endsWith(".css")
          ? "text/css"
          : f.endsWith(".json")
            ? "application/json"
            : "image/jpeg",
    );
    fs.createReadStream(f).pipe(res);
  });
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  const browser = await chromium.launch(process.env.CALCULATOR_CHROMIUM_PATH ? { executablePath: process.env.CALCULATOR_CHROMIUM_PATH } : {});
  try {
    for (const width of [1440, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } }),
        errors = [];
      page.on("pageerror", (e) => errors.push(e.message));
      page.on('dialog',d=>d.accept());
      await page.goto("http://127.0.0.1:" + server.address().port, {
        waitUntil: "networkidle",
      });
      const self = page.locator("[data-calculator-deck]");
      assert.equal(
        await self.locator("option").count(),
        data.allDecks.length + 1,
      );
      await self.selectOption("665");
      const rival = page.locator("[data-play-opponent]");
      assert.equal(
        await rival.locator("option").count(),
        data.allDecks.length + 1,
      );
      assert.equal(
        await rival.locator('option[value="705"]').evaluate((o) => o.disabled),
        true,
        await rival.innerHTML(),
      );
      assert.match(
        await rival.locator('option[value="9001"]').textContent(),
        /sin Main cargado/,
      );
      await rival.selectOption("9001");
      await page.locator("[data-play-unknown-card]").waitFor();
      assert.match(
        await page.locator("[data-play-message]").textContent(),
        /no tiene Main/,
      );
      const facts=require('../assets/modules/play-calculator/card-facts.json'),fenrir=data.cards.find(c=>c.deck_id===665&&facts.bindings[`665:${c.id}`]===17768&&c.section==='main');
      await page.locator('[data-play-search]').fill(fenrir.card_name);
      await page.locator(`.play-catalog [data-play-card="665:${fenrir.id}"]`).click();
      await page.locator('[data-play-place="m1"][data-play-side="self"]').click();
      await page.locator('[data-planner-used="17768:search"]').waitFor({state:'attached'});
      const fieldCard=page.locator('[data-play-zone="m1"][data-play-side="self"] [data-play-token]');
      await fieldCard.click({button:'right'});await page.locator('.play-card-menu').waitFor();
      await page.locator('[data-card-menu-position="defense"]').click();
      await page.locator('[data-card-negated]').check();
      await page.locator('[data-card-negation-reason]').fill('Infinite Impermanence');await page.locator('[data-card-negation-reason]').press('Tab');
      await page.locator('[data-card-effect-slot="1"]').check();await page.locator('[data-card-menu-close]').click();
      assert.equal(await fieldCard.getAttribute('class').then(c=>c.includes('is-defense')&&c.includes('is-negated')),true);
      await fieldCard.click();await page.locator('[data-play-token-menu]').click();
      await page.locator('[data-card-menu-position="set"]').click();
      await page.locator('[data-card-menu-zone]').selectOption('graveyard');await page.locator('[data-card-menu-move]').click();await page.locator('[data-card-menu-close]').click();
      assert.equal(await fieldCard.count(),0);
      await page.locator('[data-play-setting="turn"]').fill('2');await page.locator('[data-play-setting="turn"]').dispatchEvent('change');
      await page.locator('[data-play-setting="active"]').selectOption('opponent');assert.match(await page.locator('.play-turn-bar strong').textContent(),/Turno 2.*Deck sin cartas/);
      assert.equal(await page.getByText('E1 usados',{exact:true}).count(),0);
      await page.locator('[data-calculator-mode="practice"]').click();
      await page.locator("[data-play-random-hand]").click();
      assert.equal(
        await page.locator(".play-opening-cards [data-play-token]").count(),
        5,
      );
      await page.locator("[data-calculator-search]").fill("Equipo prueba");
      assert.match(
        await page.locator("[data-calculator-count]").textContent(),
        /^1 de /,
      );
      await self.selectOption("9001");
      await page.getByRole("heading", { name: /Deck sin cartas/ }).waitFor();
      assert.equal(await page.locator("[data-play-opponent]").count(), 0);
      await page.locator("[data-calculator-search]").fill("");
      await self.selectOption("9002");
      await page
        .getByText("Este formato no dispone de tablero", { exact: false })
        .waitFor();
      await page.locator('[data-calculator-mode="duel"]').click();
      await self.selectOption("705");
      await page.locator("[data-play-opponent]").selectOption("706");
      await page.locator('[data-calculator-mode="practice"]').click();
      await page.locator("[data-play-random-hand]").click();
      assert.equal(
        await page.locator(".play-opening-cards [data-play-token]").count(),
        4,
      );
      assert.equal(
        await page.evaluate(
          () => document.documentElement.scrollWidth > innerWidth + 1,
        ),
        false,
        JSON.stringify(
          await page.evaluate(() =>
            [...document.querySelectorAll("body *")]
              .filter((e) => e.getBoundingClientRect().right > innerWidth + 1)
              .slice(0, 12)
              .map((e) => [
                e.tagName,
                e.outerHTML.slice(0, 180),
                e.getBoundingClientRect().width,
              ]),
          ),
        ),
      );
      await self.selectOption("");
      assert.equal(await page.locator("[data-play-opponent]").count(), 0);
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log(
      "Calculator workspace: PASS desktop/mobile, all decks, empty inventories, format isolation, free practice, TCG/Rush hands, cleanup.",
    );
  } finally {
    await browser.close();
    await new Promise((r) => server.close(r));
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
