"use strict";
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const { chromium } = require("playwright");
const app = path.resolve(__dirname, "..");
const loader = fs.readFileSync(path.join(app, "assets/modules/startup/resources.js"), "utf8");
const footer = fs.readFileSync(path.join(app, "includes/presentation/layout/footer.php"), "utf8");
const inert = footer.match(/<template data-startup-calculator>([\s\S]*?)<\/template>/)[1];
assert.equal((inert.match(/<script /g) || []).length, 24);
assert(!footer.replace(inert, "").includes('src="./assets/modules/play-calculator/'));
assert(!footer.includes("fullcalendar@"), "Retired calendar library still downloaded");
for (const name of ["deck", "equipos", "clasificatorio", "dashboard", "torneos", "copas", "mundial", "divisiones"]) {
  const template = fs.readFileSync(path.join(app, "includes/presentation/screens", name + ".php"), "utf8");
  for (const img of template.matchAll(/<img\s+[^>]+/g)) assert(img[0].includes('loading="lazy"'), name + " eager image");
}
const profile = fs.readFileSync(path.join(app, "assets/source/javascript/19-deck-profile.js"), "utf8");
assert(!profile.includes("YgoPlayCalculator?.mount"), "Hidden calculator mounts before navigation");

async function browserTests() {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [390, 1440]) {
      const context = await browser.newContext({ viewport: { width, height: 900 } });
      const page = await context.newPage();
      const requests = [], errors = [];
      let failEngine = true;
      page.on("pageerror", error => errors.push(error.message));
      const markup = `<!doctype html><html><body><section data-screen="dashboard">Inicio</section>
        <section data-screen="calculadora_jugadas" hidden></section>
        <section hidden><img loading="lazy" src="/hidden-logo.png" width="80" height="80"></section>
        <template data-startup-calculator><script src="/engine.js"></script><script src="/ui.js"></script></template>
        <script>window.execution=[];</script><script src="/loader.js"></script></body></html>`;
      await page.route("https://startup.test/**", async route => {
        const url = new URL(route.request().url()); requests.push(url.pathname);
        if (url.pathname === "/") return route.fulfill({ contentType: "text/html", body: markup });
        if (url.pathname === "/loader.js") return route.fulfill({ contentType: "text/javascript", body: loader });
        if (url.pathname === "/engine.js") return route.fulfill({ status: failEngine ? 503 : 200,
          contentType: "text/javascript", body: failEngine ? "" : "execution.push('engine');window.engineReady=true;" });
        if (url.pathname === "/ui.js") return route.fulfill({ contentType: "text/javascript",
          body: "if(!window.engineReady)throw Error('Missing dependency');execution.push('ui');" });
        return route.abort();
      });
      await page.goto("https://startup.test/", { waitUntil: "load" });
      assert.deepEqual(await page.evaluate(() => execution), []);
      assert(!requests.includes("/hidden-logo.png"), "Hidden logo downloaded on dashboard");
      assert(!requests.includes("/engine.js"), "Calculator downloaded before route opens");
      await page.evaluate(() => {
        document.querySelector('[data-screen="calculadora_jugadas"]').hidden = false;
        window.dispatchEvent(new CustomEvent("dml:routechange", { detail: { target: "calculadora_jugadas" } }));
      });
      await page.getByRole("button", { name: "Reintentar" }).waitFor();
      assert.deepEqual(await page.evaluate(() => execution), [], "Failed dependency executed downstream modules");
      failEngine = false;
      await page.getByRole("button", { name: "Reintentar" }).click();
      await page.waitForFunction(() => window.execution.length === 2);
      assert.deepEqual(await page.evaluate(() => execution), ["engine", "ui"]);
      await page.evaluate(() => Promise.all([YgoStartupResources.ensureCalculator(), YgoStartupResources.ensureCalculator()]));
      assert.deepEqual(await page.evaluate(() => execution), ["engine", "ui"], "Duplicate initialization");
      assert.equal(await page.locator("[data-startup-status]").isVisible(), false);
      // A new document with a direct calculator hash needs no route event.
      await page.goto("https://startup.test/#calculadora_jugadas", { waitUntil: "load" });
      await page.reload({ waitUntil: "load" });
      await page.waitForFunction(() => window.execution.length === 2);
      assert.deepEqual(errors, []);
      await context.close();
    }
  } finally { await browser.close(); }
}

async function workerTests() {
  const handlers = {}, stores = new Map(); let fetches = 0, offline = false;
  const key = request => typeof request === "string" ? request : request.url;
  const caches = { async open(name) {
    if (!stores.has(name)) stores.set(name, new Map()); const data = stores.get(name);
    return { match: async request => data.get(key(request))?.clone(),
      put: async (request, response) => data.set(key(request), response.clone()),
      keys: async () => [...data.keys()].map(url => ({ url })),
      delete: async request => data.delete(key(request)), addAll: async () => {} };
  }, keys: async () => [...stores.keys()], delete: async name => stores.delete(name) };
  const scope = "https://startup.test/yugioh/";
  const context = vm.createContext({ URL, Date, Intl, Response, caches,
    self: { registration: { scope }, addEventListener: (type, callback) => handlers[type] = callback },
    fetch: async () => { fetches++; if (offline) throw Error("offline"); return new Response("fresh", { headers: { date: new Date().toUTCString() } }); } });
  vm.runInContext(fs.readFileSync(path.join(app, "service-worker.js"), "utf8"), context);
  async function request(relative, mode = "cors") {
    let response; const waits = [];
    handlers.fetch({ request: { method: "GET", url: new URL(relative, scope).href, mode },
      respondWith: value => response = value, waitUntil: value => waits.push(value) });
    if (!response) return null;
    const result = await response; await Promise.all(waits); return result;
  }
  await request("assets/app.js?v=20261125-startup-1");
  await request("assets/app.js?v=20261125-startup-1");
  assert.equal(fetches, 1, "Versioned bundle was downloaded twice");
  await request("assets/uploads/decks/logo.png"); await request("assets/uploads/decks/logo.png");
  assert.equal(fetches, 2, "Uploaded logo was downloaded twice");
  await request("assets/editorial/active.json"); await request("assets/editorial/active.json");
  assert.equal(fetches, 4, "Editorial JSON must remain fresh");
  assert.equal(await request("?api=statistics"), null, "API must bypass worker cache");
  await request("./", "navigate"); await request("./", "navigate");
  assert.equal(fetches, 6, "Navigation must remain network-first");
  offline = true;
  assert.equal(await (await request("./", "navigate")).text(), "fresh", "Visited page unavailable offline");
  assert.equal(await (await request("assets/app.js?v=20261125-startup-1")).text(), "fresh");
  offline = false;
  const runtime = await caches.open("yugioh-md-runtime-v1");
  await runtime.put(new URL("assets/uploads/expired.png", scope).href,
    new Response("old", { headers: { date: new Date(Date.now() - 8 * 86400000).toUTCString() } }));
  assert.equal(await (await request("assets/uploads/expired.png")).text(), "fresh", "Expired runtime image reused");
}
(async () => { await workerTests(); await browserTests(); console.log("Startup: hidden images, ordered on-demand scripts, retry, direct links and fresh/offline cache passed (mobile/desktop)."); })()
  .catch(error => { console.error(error); process.exitCode = 1; });
