const { chromium } = require("playwright"),
  fs = require("node:fs"),
  path = require("node:path"),
  http = require("node:http"),
  assert = require("node:assert/strict");
const base = path.resolve(__dirname, "..");
(async () => {
  const server = http.createServer((req, res) => {
    res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
    res.end(
      '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:16px;font:16px sans-serif;background:#071222;color:#eff;}button,input,select{font:inherit;max-width:100%;box-sizing:border-box}h1{font-size:28px}.panel{padding:16px;border:1px solid #345;border-radius:12px}*{box-sizing:border-box}a{color:cyan}[hidden]{display:none}</style>' +
        fs.readFileSync(
          path.join(base, "includes/decks/card-inspector-view.php"),
          "utf8",
        ),
    );
  });
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  const origin = "http://127.0.0.1:" + server.address().port;
  const browser = await chromium.launch({ headless: true });
  let apiCalls = 0;
  try {
    for (const width of [1440, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } }),
        errors = [];
      page.on("pageerror", (e) => errors.push(e.message));
      await page.route("**/assets/editorial/**", (route) =>
        route.fulfill({
          json: route.request().url().endsWith("strategy-content-state.json")
            ? { checked_studies: { 1: { path: "./assets/editorial/1.json" } } }
            : {
                format: "tcg",
                cards: [
                  {
                    cid: 2,
                    name: "Original Card",
                    slots: [{ card_id: 1 }],
                    image: "./test-card.svg",
                    fields: [
                      { title: "Efecto", text: "Lee este efecto completo." },
                      { title: "Coste", text: "Coste documentado." },
                    ],
                  },
                ],
              },
        }),
      );
      await page.route("**/?api=deck_builder**", (route) => {
        const params = new URL(route.request().url()).searchParams;
        if (params.get("op") === "detail")
          return route.fulfill({
            json: {
              ok: true,
              card: {
                id: 100,
                name: "Original Card",
                description:
                  "Full printed effect. Once per turn: detach 1 material; apply the effect.",
                image: params.get("local") === "1" ? null : "./test-card.svg",
              },
            },
          });
        apiCalls++;
        const q = params.get("q");
        return route.fulfill(
          q === "error"
            ? { status: 503, json: { ok: false, message: "API no disponible" } }
            : {
                json: {
                  ok: true,
                  cards: [
                    {
                      id: 100,
                      name: "Remote Card",
                      description: "API card effect",
                      image: "",
                    },
                  ],
                  total_rows: 1,
                  has_more: false,
                },
              },
        );
      });
      await page.route("**/test-card.svg", (r) =>
        r.fulfill({
          contentType: "image/svg+xml",
          body: '<svg xmlns="http://www.w3.org/2000/svg" width="630" height="900"><rect width="630" height="900" fill="#aa8844"/><text x="30" y="60">Image test fixture</text></svg>',
        }),
      );
      await page.goto(origin + "/#inspector_cartas");
      await page.evaluate(() => {
        document.querySelector("[data-screen]").hidden = false;
        window.DML_DATA = {
          allDecks: [{ id: 1, name: "My deck", format_codes: ["TCG"] }],
          cards: Array.from({ length: 23 }, (_, i) => ({
            id: i + 1,
            deck_id: 1,
            card_name: i ? "Card " + i : "Carta original",
            quantity: 3,
            section: i ? "extra" : "main",
            ygoprodeck_id: i ? null : 100,
          })),
        };
      });
      await page.addStyleTag({
        path: path.join(base, "assets/deck-builder.css"),
      });
      await page.addStyleTag({
        path: path.join(base, "assets/modules/card-inspector/styles.css"),
      });
      await page.addScriptTag({
        path: path.join(base, "assets/modules/card-inspector/model.js"),
      });
      await page.addScriptTag({
        path: path.join(base, "assets/modules/card-inspector/ui.js"),
      });
      await page.waitForFunction(
        () => document.querySelectorAll("[data-ci-card]").length === 20,
      );
      const before = apiCalls;
      await page.locator("[data-ci-next]").click();
      await page.waitForFunction(
        () => document.querySelectorAll("[data-ci-card]").length === 3,
      );
      assert.equal(apiCalls, before);
      await page.locator("[data-ci-query]").fill("original");
      await page.locator("[data-ci-form]").evaluate((f) => f.requestSubmit());
      await page.waitForFunction(
        () => document.querySelectorAll("[data-ci-card]").length === 1,
      );
      await page.locator("[data-ci-card]").click();
      await page
        .locator("[data-ci-detail]")
        .getByText(
          "Full printed effect. Once per turn: detach 1 material; apply the effect.",
        )
        .waitFor();
      assert.equal(await page.locator("[data-ci-detail] details").count(), 0);
      assert.equal(
        await page
          .getByText("Lee este efecto completo.", { exact: true })
          .count(),
        0,
      );
      assert.equal(
        await page.getByText("Efecto documentado", { exact: true }).count(),
        0,
      );
      await page.locator("[data-ci-fetch-card]").click();
      await page.waitForFunction(
        () => !document.querySelector("[data-ci-fetch-card]"),
      );
      await page.locator("[data-ci-zoom]").click();
      await page.waitForFunction(
        () =>
          document.querySelector(".ci-photo-dialog img").naturalWidth === 630,
      );
      await page.locator('[data-ci-photo-action="natural"]').click();
      const natural = await page
        .locator(".ci-photo-dialog img")
        .evaluate((i) => i.getBoundingClientRect().width);
      await page.locator('[data-ci-photo-action="in"]').click();
      assert(
        (await page
          .locator(".ci-photo-dialog img")
          .evaluate((i) => i.getBoundingClientRect().width)) > natural,
      );
      await page.keyboard.press("Escape");
      assert.equal(
        await page.locator(".ci-photo-dialog").evaluate((d) => d.open),
        false,
      );
      assert.equal(
        await page.locator("[data-ci-detail] a").last().getAttribute("href"),
        origin + "/?deck_id=1#deck_ficha",
      );
      assert.equal(
        await page
          .locator("[data-ci-detail]")
          .getByText("Main Deck ×3")
          .count(),
        1,
      );
      await page.locator("[data-ci-query]").fill("remote");
      await page.locator("[data-ci-source]").selectOption("api");
      await page.getByRole("button", { name: /Remote Card/ }).waitFor();
      await page.getByRole("button", { name: /Remote Card/ }).click();
      await page.getByText("API card effect", { exact: true }).waitFor();
      await page
        .locator("[data-ci-detail]")
        .getByRole("link", { name: "Ver deck: My deck" })
        .waitFor();
      await page.locator("[data-ci-query]").fill("error");
      await page.locator("[data-ci-form]").evaluate((f) => f.requestSubmit());
      await page.getByText("API no disponible", { exact: true }).waitFor();
      await page.locator("[data-ci-source]").selectOption("local");
      await page.waitForFunction(() =>
        document
          .querySelector("[data-ci-status]")
          .textContent.includes("0 cartas"),
      );
      assert.equal(await page.locator("[data-ci-card]").count(), 0);
      assert.equal(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
        true,
        "no viewport overflow",
      );
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log(
      "Card inspector UI: PASS desktop/mobile, local pagination, API, error handling, exact deck links; no DB writes.",
    );
  } finally {
    await browser.close();
    await new Promise((r) => server.close(r));
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
