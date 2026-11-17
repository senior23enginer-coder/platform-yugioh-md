(function () {
  "use strict";
  const host = document.querySelector('[data-screen="inspector_cartas"]'),
    M = window.YgoCardInspector;
  if (!host || !M) return;
  const $ = (s) => host.querySelector(s),
    esc = (v) =>
      String(v ?? "").replace(
        /[&<>"']/g,
        (c) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          })[c],
      );
  const safe = (url) => {
    if (typeof url !== "string" || !url.trim()) return "";
    try {
      const u = new URL(url, location.href);
      return ["http:", "https:"].includes(u.protocol) ? u.href : "";
    } catch {
      return "";
    }
  };
  const data = window.DML_DATA || {};
  let studies = [],
    local = M.catalog(data),
    rows = [],
    page = 0,
    total = 0,
    more = false,
    ticket = 0,
    loading = false,
    initialized = false,
    selectedCard = null,
    selectedIndex = -1;
  function status(text) {
    $("[data-ci-status]").textContent = text;
  }
  async function loadStudies() {
    try {
      const r = await fetch("./assets/editorial/strategy-content-state.json", {
        cache: "no-cache",
      });
      if (!r.ok) throw Error();
      const state = await r.json();
      const results = await Promise.allSettled(
        Object.values(state.checked_studies || {}).map(async (s) => {
          const u = new URL(s.path, location.href);
          if (
            u.origin !== location.origin ||
            !u.pathname.includes("/assets/editorial/")
          )
            throw Error();
          const r = await fetch(u);
          if (!r.ok) throw Error();
          return r.json();
        }),
      );
      studies = results
        .filter((r) => r.status === "fulfilled")
        .map((r) => r.value);
      return results.some((r) => r.status === "rejected");
    } catch {
      return true;
    }
  }
  const image = (c, cls = "") =>
    safe(c.image)
      ? `<img class="${cls}" src="${esc(safe(c.image))}" alt="${esc(c.name)}" loading="lazy">`
      : '<span class="ci-missing">Imagen no disponible en el catálogo local</span>';
  function draw() {
    $("[data-ci-results]").innerHTML =
      rows
        .map(
          (c, i) =>
            `<button class="ci-card" type="button" data-ci-card="${i}" aria-pressed="false">${image(c)}<b>${esc(c.name)}</b><small>${esc(c.id ? "ID API: " + c.id : c.cid ? "CID Konami: " + c.cid : "Carta local")}</small></button>`,
        )
        .join("") || "<p>No se encontraron cartas con esta búsqueda.</p>";
    $("[data-ci-prev]").disabled = loading || page === 0;
    $("[data-ci-next]").disabled = loading || !more;
    $("[data-ci-page]").textContent = total
      ? `${page + 1} / ${Math.ceil(total / 20)}`
      : "0 resultados";
    host.querySelectorAll("img").forEach(
      (img) =>
        (img.onerror = () => {
          const fallback = document.createElement("span");
          fallback.className = "ci-missing";
          fallback.textContent = "No se pudo cargar la imagen de " + img.alt;
          img.replaceWith(fallback);
        }),
    );
  }
  function detail(c, index, focus = true) {
    host
      .querySelectorAll("[data-ci-card]")
      .forEach((b) =>
        b.setAttribute(
          "aria-pressed",
          String(Number(b.dataset.ciCard) === index),
        ),
      );
    const uses = M.usages(c, local),
      groups = new Map();
    for (const u of uses) {
      if (!groups.has(u.deck_id)) groups.set(u.deck_id, []);
      groups.get(u.deck_id).push(u);
    }
    const photo = safe(c.image),
      detail = $("[data-ci-detail]");
    detail.innerHTML = `<h2 tabindex="-1">${esc(c.name)}</h2><div class="ci-summary">${photo ? `<div class="ci-photo-wrap"><button type="button" class="ci-photo" data-ci-zoom aria-label="Ampliar imagen de ${esc(c.name)}">${image(c)}</button><small data-ci-resolution>Pulsa la imagen para ampliar.</small></div>` : image(c)}<div>${c.names?.length > 1 ? `<p>${esc(c.names.join(" / "))}</p>` : ""}<p>${esc([c.type, c.race, c.attribute, c.format].filter(Boolean).join(" · "))}</p><p>${[
      "atk",
      "def",
      "level",
    ]
      .filter((k) => c[k] !== undefined && c[k] !== null)
      .map(
        (k) =>
          esc({ atk: "ATK", def: "DEF", level: "Nivel" }[k]) + ": " + esc(c[k]),
      )
      .join(
        " · ",
      )}</p><h3>Texto de la carta</h3><p class="ci-description">${esc(c.description || "No hay texto de la carta guardado en el catálogo local.")}</p>${c.description ? '<p class="note">Fuente: YGOPRODeck · texto en inglés.</p>' : ""}
    ${c.id && (!c.description || !c.imageIsOriginal) ? '<button class="btn btn-outline-info" type="button" data-ci-fetch-card>Obtener texto e imagen desde API</button><p class="note">Esta consulta requiere conexión. No cambia las cartas de tus decks.</p>' : ""}
    ${!c.id && c.cid ? `<a href="https://www.db.yugioh-card.com/yugiohdb/card_search.action?ope=2&amp;cid=${Number(c.cid)}&amp;request_locale=es" target="_blank" rel="noopener">Leer la carta en la base oficial de Konami</a>` : ""}
    <p role="status" data-ci-card-status></p></div></div>
      <h3>Decks que la usan (${groups.size})</h3>${
        [...groups.values()]
          .map((list) => {
            const u = list[0],
              url = new URL(location.href);
            url.search = "";
            url.searchParams.set("deck_id", u.deck_id);
            url.hash = "deck_ficha";
            return `<article class="ci-usage"><a href="${esc(url.href)}">Ver deck: ${esc(u.deck)}</a><small>${esc([u.team, u.format].filter(Boolean).join(" · "))}</small><p>${list.map((s) => `${esc(M.zones[s.section] || s.section)} ×${esc(s.quantity)}`).join(" · ")}</p></article>`;
          })
          .join("") ||
        `<p>${c.usages ? "Sin decks asociados." : "No hay coincidencia de ID API en los inventarios locales. Las cartas registradas sin ese identificador pueden consultarse en la búsqueda local."}</p>`
      }`;
    if (focus) detail.querySelector("h2").focus({ preventScroll: true });
    if (focus && innerWidth < 901)
      detail.scrollIntoView({ behavior: "smooth", block: "start" });
    detail
      .querySelector("[data-ci-zoom]")
      ?.addEventListener("click", (event) => openPhoto(c, event.currentTarget));
    detail
      .querySelector("[data-ci-fetch-card]")
      ?.addEventListener("click", () => inspect(c, index, false));
    const detailImage = detail.querySelector(".ci-photo img");
    if (detailImage) {
      const resolution = () => {
        const note = detail.querySelector("[data-ci-resolution]");
        if (note)
          note.textContent = `${detailImage.naturalWidth} × ${detailImage.naturalHeight} px · pulsa para ampliar`;
      };
      detailImage.addEventListener("load", resolution);
      if (detailImage.complete && detailImage.naturalWidth) resolution();
    }
    detail.querySelectorAll("img").forEach(
      (img) =>
        (img.onerror = () => {
          img.replaceWith(document.createTextNode("Imagen no disponible."));
        }),
    );
  }
  const detailRequests = new Map();
  async function inspect(c, index, localOnly) {
    const message = $("[data-ci-card-status]");
    if (message)
      message.textContent = localOnly
        ? "Leyendo texto guardado..."
        : "Consultando texto e imagen de la carta...";
    try {
      const key = c.id + ":" + localOnly;
      if (!detailRequests.has(key)) {
        const url = new URL(location.href);
        url.search = "";
        url.hash = "";
        url.searchParams.set("api", "deck_builder");
        url.searchParams.set("op", "detail");
        url.searchParams.set("id", c.id);
        url.searchParams.set("local", localOnly ? "1" : "0");
        const request = fetch(url)
          .then(async (r) => {
            const answer = await r.json();
            if (!r.ok || !answer.ok)
              throw Error(answer.message || "No se pudo leer la carta.");
            return answer.card;
          })
          .catch((e) => {
            detailRequests.delete(key);
            throw e;
          });
        detailRequests.set(key, request);
      }
      const found = await detailRequests.get(key);
      if (found) {
        if (Number(found.id) !== Number(c.id))
          throw Error("La identidad de la carta recibida no coincide.");
        for (const k of [
          "description",
          "type",
          "race",
          "attribute",
          "atk",
          "def",
          "level",
        ])
          if (found[k] !== undefined) c[k] = found[k];
        if (found.image) {
          c.image = found.image;
          c.imageIsOriginal = true;
        }
      }
      if (selectedCard !== c) return;
      detail(c, index, false);
    } catch (e) {
      if (selectedCard === c) {
        const message = $("[data-ci-card-status]");
        if (message) message.textContent = e.message;
      }
    }
  }
  let photoDialog = null,
    zoom = 1,
    photoTrigger = null;
  function openPhoto(c, trigger) {
    if (!photoDialog) {
      photoDialog = document.createElement("dialog");
      photoDialog.className = "ci-photo-dialog";
      photoDialog.setAttribute("aria-label", "Imagen de la carta ampliable");
      photoDialog.innerHTML =
        '<header><button type="button" data-ci-photo-action="out" aria-label="Alejar imagen">−</button><button type="button" data-ci-photo-action="in" aria-label="Acercar imagen">+</button><button type="button" data-ci-photo-action="fit">Ajustar</button><button type="button" data-ci-photo-action="natural">Tamaño natural</button><button type="button" data-ci-photo-action="close">Cerrar imagen</button></header><div class="ci-zoom-stage"><img alt=""></div><p></p>';
      host.append(photoDialog);
      photoDialog.addEventListener("click", (e) => {
        const action = e.target.closest("[data-ci-photo-action]")?.dataset
          .ciPhotoAction;
        if (!action) return;
        if (action === "close") {
          photoDialog.close();
          return;
        }
        zoom =
          action === "in"
            ? Math.min(4, zoom * 1.25)
            : action === "out"
              ? Math.max(0.25, zoom / 1.25)
              : action === "natural"
                ? 1
                : fitPhoto();
        drawPhoto();
      });
      photoDialog.addEventListener("close", () =>
        photoTrigger?.focus({ preventScroll: true }),
      );
      photoDialog.addEventListener("keydown", (e) => {
        if (e.key === "Escape") e.stopPropagation();
      });
      photoDialog.querySelector("img").onload = () => {
        zoom = fitPhoto();
        drawPhoto();
      };
    }
    photoTrigger = trigger;
    const img = photoDialog.querySelector("img");
    img.alt = c.name;
    img.src = safe(c.image);
    photoDialog.showModal();
    if (img.complete && img.naturalWidth) {
      zoom = fitPhoto();
      drawPhoto();
    }
  }
  function fitPhoto() {
    const img = photoDialog.querySelector("img"),
      stage = photoDialog.querySelector(".ci-zoom-stage");
    return Math.min(
      1,
      (stage.clientWidth - 16) / img.naturalWidth,
      (stage.clientHeight - 16) / img.naturalHeight,
    );
  }
  function drawPhoto() {
    const img = photoDialog.querySelector("img");
    img.style.width = Math.round(img.naturalWidth * zoom) + "px";
    photoDialog.querySelector("p").textContent =
      `${img.alt} · ${img.naturalWidth} × ${img.naturalHeight} px · zoom ${Math.round(zoom * 100)}%. El texto de la carta se puede leer en la ficha.`;
  }
  async function search(reset = true) {
    const current = ++ticket;
    selectedCard = null;
    selectedIndex = -1;
    if (reset) page = 0;
    const q = $("[data-ci-query]").value.trim(),
      source = $("[data-ci-source]").value;
    loading = true;
    rows = [];
    total = 0;
    more = false;
    draw();
    $("[data-ci-detail]").innerHTML =
      "<h2>Selecciona una carta</h2><p>Abre un resultado para leerlo y consultar sus decks.</p>";
    status("Buscando cartas…");
    try {
      if (source === "local") {
        local = M.catalog(data, studies);
        const found = M.search(local, q);
        total = found.length;
        rows = found.slice(page * 20, (page + 1) * 20);
        more = (page + 1) * 20 < total;
      } else {
        if (q.length < 2)
          throw Error(
            "Escribe al menos dos caracteres del nombre en inglés o el ID de YGOPRODeck.",
          );
        const url = new URL(location.href);
        url.search = "";
        url.hash = "";
        url.searchParams.set("api", "deck_builder");
        url.searchParams.set("op", "search");
        url.searchParams.set("q", q);
        url.searchParams.set("format", "all");
        url.searchParams.set("page", page);
        const response = await fetch(url);
        const answer = await response.json();
        if (!response.ok || !answer.ok)
          throw Error(answer.message || "No se pudo consultar la API.");
        if (current !== ticket) return;
        rows = answer.cards.map((c) => ({
          ...c,
          image: c.image ? c.image + "&size=full" : "",
          imageIsOriginal: true,
        }));
        total = answer.total_rows;
        more = answer.has_more;
      }
      if (current !== ticket) return;
      status(
        `${total} cartas encontradas en ${source === "local" ? "los decks locales" : "la API"}.`,
      );
    } catch (e) {
      if (current !== ticket) return;
      rows = [];
      status(e.message || "No se pudo completar la búsqueda.");
    } finally {
      if (current === ticket) {
        loading = false;
        draw();
      }
    }
  }
  $("[data-ci-form]").addEventListener("submit", (e) => {
    e.preventDefault();
    search();
  });
  $("[data-ci-source]").addEventListener("change", () => {
    $("[data-ci-help]").textContent =
      $("[data-ci-source]").value === "local"
        ? "Busca solo entre las cartas guardadas de todos los decks. No consulta la API."
        : "La API busca por nombre en inglés o ID de YGOPRODeck y requiere conexión. Las asociaciones con decks se consultan en la plataforma.";
    search();
  });
  $("[data-ci-prev]").onclick = () => {
    if (!loading && page > 0) {
      page--;
      search(false);
    }
  };
  $("[data-ci-next]").onclick = () => {
    if (!loading && more) {
      page++;
      search(false);
    }
  };
  host.addEventListener("click", (e) => {
    const b = e.target.closest("[data-ci-card]");
    if (b) {
      selectedIndex = Number(b.dataset.ciCard);
      selectedCard = rows[selectedIndex];
      detail(selectedCard, selectedIndex);
      if (selectedCard.usages && selectedCard.id)
        inspect(selectedCard, selectedIndex, true);
    }
  });
  async function initialize() {
    if (initialized) return;
    initialized = true;
    status("Preparando catálogo local…");
    const partial = await loadStudies();
    await search();
    if (partial)
      status(
        $("[data-ci-status]").textContent +
          " Parte de la documentación no está disponible; se conserva el inventario local.",
      );
  }
  window.addEventListener("dml:routechange", (e) => {
    if (e.detail.target === "inspector_cartas") initialize();
  });
  if (location.hash === "#inspector_cartas") initialize();
})();
