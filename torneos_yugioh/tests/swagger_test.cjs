const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const os = require("node:os");
const net = require("node:net");
const { spawn, spawnSync } = require("node:child_process");
const SwaggerParser = require("@apidevtools/swagger-parser");
const { chromium } = require("playwright");
const root = path.resolve(__dirname, "../..");
const app = path.join(root, "torneos_yugioh");
const php = process.env.PHP_BINARY || "php";

async function freePort() {
  const server = net.createServer();
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
  const port = server.address().port;
  await new Promise((resolve) => server.close(resolve));
  return port;
}
(async () => {
  const spec = await SwaggerParser.validate(
    path.join(app, "api-docs/openapi.json"),
  );
  const operations = Object.values(spec.paths).flatMap((p) =>
    Object.entries(p),
  );
  assert.equal(operations.length, 13);
  assert.equal(new Set(operations.map(([, op]) => op.operationId)).size, 13);
  assert.equal(spec.servers[0].url, "../api/index.php");
  const check = spawnSync(
    process.execPath,
    ["scripts/build-swagger.cjs", "--check"],
    { cwd: root, encoding: "utf8" },
  );
  assert.equal(check.status, 0, check.stderr);
  const routing = spawnSync(
    php,
    [
      "-r",
      `echo json_encode(require '${path.join(app, "api/routes.php").replaceAll("\\", "/")}');`,
    ],
    { encoding: "utf8" },
  );
  assert.equal(routing.status, 0, routing.stderr);
  const routes = JSON.parse(routing.stdout);
  for (const [route, config] of Object.entries(routes)) {
    assert.deepEqual(
      Object.keys(spec.paths[route]).sort(),
      config.methods.map((x) => x.toLowerCase()).sort(),
    );
    const queries = Object.values(spec.paths[route]).flatMap((op) =>
      op.parameters.filter((x) => x.in === "query").map((x) => x.name),
    );
    assert.deepEqual([...new Set(queries)].sort(), [...config.query].sort());
  }

  // Copy only the real boundary and static documentation. The fake application below records
  // dispatch without loading any database, seed, provider or production configuration.
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), "ygo-swagger-"));
  fs.cpSync(path.join(app, "api"), path.join(fixture, "api"), {
    recursive: true,
  });
  fs.cpSync(path.join(app, "api-docs"), path.join(fixture, "api-docs"), {
    recursive: true,
  });
  fs.cpSync(
    path.join(app, "assets/modules/api-docs"),
    path.join(fixture, "assets/modules/api-docs"),
    { recursive: true },
  );
  fs.writeFileSync(
    path.join(fixture, "index.php"),
    `<?php
header('Content-Type: application/json');
echo json_encode(['query'=>$_GET,'method'=>$_SERVER['REQUEST_METHOD'],'body'=>file_get_contents('php://input'),'files'=>array_keys($_FILES),'delegated'=>true,'token'=>($_GET['api']==='deck_builder'?'fixture-decklist-token':'fixture-csrf-token')]);
`,
  );
  const port = await freePort();
  const origin = `http://127.0.0.1:${port}`;
  const server = spawn(php, ["-S", `127.0.0.1:${port}`, "-t", fixture], {
    stdio: "ignore",
  });
  let browser;
  try {
    for (let i = 0; ; i++) {
      try {
        await fetch(`${origin}/api-docs/openapi.json`);
        break;
      } catch (e) {
        if (i > 60) throw e;
        await new Promise((r) => setTimeout(r, 100));
      }
    }
    for (const [route, config] of Object.entries(routes)) {
      for (const method of config.methods) {
        const query = new URLSearchParams({
          deck_id: "650",
          id: "32909498",
          q: "Fenrir",
          api: "reports",
          op: "override",
          source_prompts: "1",
          public_live: "1",
        });
        const payload = JSON.stringify({
          deck_id: 650,
          revision: 0,
          document: { title: "boundary fixture" },
        });
        const response = await fetch(
          `${origin}/api/index.php${route}?${query}`,
          {
            method,
            ...(method === "POST"
              ? {
                  headers: { "Content-Type": "application/json" },
                  body: payload,
                }
              : {}),
          },
        );
        assert.equal(response.status, 200, route);
        const body = await response.json();
        assert.equal(body.delegated, true);
        assert.equal(body.query.api, config.api);
        if (config.op) assert.equal(body.query.op, config.op);
        else assert.equal(body.query.op, undefined);
        assert.equal(body.query.public_live, undefined);
        assert.equal(body.query.source_prompts, undefined);
        assert.equal(body.method, method);
        if (method === "POST")
          assert.equal(
            body.body,
            payload,
            "Body must remain byte-for-byte unchanged",
          );
      }
    }
    const upload = new FormData();
    upload.append(
      "package",
      new Blob(["fixture bytes"], { type: "application/zip" }),
      "fixture.zip",
    );
    const multipart = await fetch(
      `${origin}/api/index.php/deck-data/preview?deck_id=650`,
      { method: "POST", body: upload },
    );
    assert.deepEqual((await multipart.json()).files, ["package"]);
    assert.equal(
      (await fetch(`${origin}/api/index.php/not-found`)).status,
      404,
    );
    const wrong = await fetch(`${origin}/api/index.php/cards/search`, {
      method: "DELETE",
    });
    assert.equal(wrong.status, 405);
    assert.equal(wrong.headers.get("allow"), "GET");
    assert.equal(
      (await fetch(`${origin}/api/index.php`, { redirect: "manual" })).status,
      302,
    );

    const slashEntry = await fetch(`${origin}/api/index.php/`, {
      redirect: "manual",
    });
    assert.equal(slashEntry.status, 302);
    assert.equal(slashEntry.headers.get("location"), "/api-docs/");
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.route("**/*", (route) => {
      assert.equal(
        new URL(route.request().url()).origin,
        origin,
        "Swagger must not contact external servers",
      );
      return route.continue();
    });
    await page.goto(`${origin}/api-docs/`);
    await page.waitForSelector(".opblock");
    assert.equal(await page.locator(".opblock").count(), 13);
    const output = path.join(root, "outputs/swagger");
    fs.mkdirSync(output, { recursive: true });
    for (const [name, viewport] of [
      ["desktop", { width: 1440, height: 1000 }],
      ["mobile", { width: 390, height: 844 }],
    ]) {
      await page.setViewportSize(viewport);
      assert.equal(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth + 1,
        ),
        true,
        name + " horizontal overflow",
      );
      await page.screenshot({
        path: path.join(output, name + ".png"),
        fullPage: true,
      });
    }
    // Swagger executes the real alias URL. This response comes from our recording fixture.
    const first = page.locator("#operations-Decklist-getDecklist");
    await first.locator(".opblock-summary").click();
    await first.locator(".try-out__btn").click();
    await first.locator('input[type="text"]').first().fill("650");
    await first.locator(".execute").click();
    await first
      .locator(".responses-wrapper .response-col_status")
      .filter({ hasText: "200" })
      .first()
      .waitFor();
    const post = page.locator("#operations-Decklist-saveDecklist");
    await post.locator(".opblock-summary").click();
    await post.locator(".try-out__btn").click();
    await post.locator('input[type="text"]').first().fill("650");
    await post
      .locator("textarea")
      .first()
      .fill(JSON.stringify({ token: "", version: "fixture", cards: [] }));
    const sending = page.waitForRequest(
      (request) =>
        request.method() === "POST" && request.url().includes("/decklist"),
    );
    await post.locator(".execute").click();
    assert.equal(
      JSON.parse((await sending).postData()).token,
      "fixture-decklist-token",
      "Reuse actual token received by Swagger GET",
    );
    const worldsGet = page.locator("#operations-Mundos-getDeckWorlds");
    await worldsGet.locator(".opblock-summary").click();
    await worldsGet.locator(".try-out__btn").click();
    await worldsGet.locator('input[type="text"]').first().fill("650");
    const readingWorlds = page.waitForResponse(
      (response) =>
        response.url().includes("/worlds") &&
        response.request().method() === "GET",
    );
    await worldsGet.locator(".execute").click();
    await readingWorlds;
    await page.waitForFunction(() =>
      Object.keys(window.ui.authSelectors.authorized().toJS()).includes(
        "sessionCsrf",
      ),
    );
    const worldsPost = page.locator("#operations-Mundos-saveDeckWorlds");
    await worldsPost.locator(".opblock-summary").click();
    await worldsPost.locator(".try-out__btn").click();
    await worldsPost
      .locator("textarea")
      .first()
      .fill(
        JSON.stringify({
          deck_id: 650,
          expected_revision: 0,
          attributes: ["earth"],
        }),
      );
    const savingWorlds = page.waitForRequest(
      (request) =>
        request.method() === "POST" && request.url().includes("/worlds"),
    );
    await worldsPost.locator(".execute").click();
    assert.equal(
      (await savingWorlds).headers()["x-csrf-token"],
      "fixture-csrf-token",
    );
    assert.deepEqual(errors, []);
    console.log(
      "PASS: OpenAPI valid; 13 route/method contracts, query isolation, unchanged JSON/uploads, 404/405, local resources, desktop/mobile UI and Swagger execution. No database or production access.",
    );
  } finally {
    if (browser) await browser.close();
    server.kill();
    await new Promise((resolve) =>
      server.exitCode !== null ? resolve() : server.once("exit", resolve),
    );
    assert(
      path.resolve(fixture).startsWith(path.resolve(os.tmpdir()) + path.sep),
    );
    fs.rmSync(fixture, { recursive: true, force: true });
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
