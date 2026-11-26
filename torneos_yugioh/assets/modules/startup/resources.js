/**
 * Browser resource adapter: calculator dependencies are downloaded only when
 * its route is opened. The inert template is the single ordered manifest.
 * No game rules, state persistence or navigation decisions belong here.
 */
(function (root) {
  "use strict";
  const template = document.querySelector("template[data-startup-calculator]");
  if (!template) return;
  const scripts = new Map();
  const preloads = new Set();
  let pending;

  function loadScript(source) {
    const url = new URL(source, document.baseURI).href;
    if (scripts.has(url)) return scripts.get(url);
    const promise = new Promise((resolve, reject) => {
      const script = document.createElement("script");
      // Execution stays ordered. A failed dependency stops downstream modules;
      // retry resumes here without executing successful modules twice.
      script.async = false;
      script.src = url;
      script.onload = resolve;
      script.onerror = () => {
        script.remove();
        scripts.delete(url);
        reject(new Error("No se pudo descargar una parte de la calculadora."));
      };
      document.head.append(script);
    });
    scripts.set(url, promise);
    return promise;
  }

  function notice() {
    const host = document.querySelector('[data-screen="calculadora_jugadas"]');
    if (!host) return null;
    let node = host.querySelector("[data-startup-status]");
    if (!node) {
      node = document.createElement("div");
      node.dataset.startupStatus = "";
      node.className = "alert alert-info";
      node.setAttribute("role", "status");
      host.prepend(node);
    }
    return node;
  }

  function ensureCalculator() {
    if (pending) return pending;
    const status = notice();
    if (status) {
      status.hidden = false;
      status.textContent = "Preparando la calculadora de jugadas…";
    }
    const sources = Array.from(
      template.content.querySelectorAll("script[src]"),
      (script) => script.getAttribute("src"),
    );
    // Fetch dependencies in parallel without evaluating them out of order.
    for (const source of sources) {
      if (preloads.has(source)) continue;
      preloads.add(source);
      const link = document.createElement("link");
      link.rel = "preload";
      link.as = "script";
      link.href = source;
      document.head.append(link);
    }
    pending = sources
      .reduce(
        (previous, source) => previous.then(() => loadScript(source)),
        Promise.resolve(),
      )
      .then(() => {
        if (status) status.hidden = true;
      })
      .catch((error) => {
        pending = null;
        if (status) {
          status.textContent = error.message + " ";
          const retry = document.createElement("button");
          retry.type = "button";
          retry.className = "btn btn-outline-info btn-sm";
          retry.textContent = "Reintentar";
          retry.onclick = () => ensureCalculator().catch(() => {});
          status.append(retry);
        }
        throw error;
      });
    return pending;
  }

  root.YgoStartupResources = { ensureCalculator };
  root.addEventListener("dml:routechange", (event) => {
    if (event.detail?.target === "calculadora_jugadas") {
      ensureCalculator().catch(() => {});
    }
  });
  // Covers direct links and restored routes even if app.js dispatched before us.
  if (location.hash === "#calculadora_jugadas")
    ensureCalculator().catch(() => {});
})(window);
