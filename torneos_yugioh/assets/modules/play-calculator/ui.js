(function (root) {
  "use strict";
  const E = root.YgoPlayEngine,
    S = root.YgoPlayStorage,
    instances = new WeakMap();
  let imageIndexPromise;
  const esc = (v) =>
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
  const sideName = { self: "Tu lado", opponent: "Rival" };
  const zoneName = (z) =>
    ({
      hand: "Mano",
      graveyard: "Cementerio",
      banished: "Destierro",
      extra: "Extra Deck",
      field: "Campo",
      deck: "Main Deck",
      emz1: "Zona Extra 1 · compartida",
      emz2: "Zona Extra 2 · compartida",
    })[z] ||
    (z.startsWith("material:")
      ? "Material bajo #" + z.slice(9)
      : /^m[1-5]$/.test(z)
        ? "Monstruo " + z.slice(1)
        : /^s[1-5]$/.test(z)
          ? "Magia/Trampa " + z.slice(1)
          : "Material");
  function mount(node, deck, data, options = {}) {
    if (!node) return;
    const native = E.catalog(deck, data.cards || []),
      previous = instances.get(node);
    const dataSignature = E.signature(data.cards || []);
    if (
      previous?.self.id === native.id &&
      previous.self.signature === native.signature &&
      previous.dataSignature === dataSignature &&
      (!options.mode || previous.mode === options.mode)
    )
      return;
    previous?.planWorker?.terminate();
    previous?.controller.abort();
    const state = {
      controller: new AbortController(),
      mode: options.mode || "duel",
      externalMode: options.mode !== undefined,
      dataSignature,
      self: native,
      opponents: [],
      scenario: null,
      routes: [],
      plannerFacts: null,
      planResult: null,
      planBusy: false,
      chainPreview: null,
      planRequest: 0,
      planWorker: null,
      planOptions: { used: [], blockedZones: [], goal: "balanced" },
      matchups: [],
      query: "",
      side: "both",
      section: "",
      page: 0,
      selection: null,
      history: [],
      message: "",
      error: false,
      analysis: false,
      editorialImages: {},
      failedImageUrls: new Set(),
    };
    instances.set(node, state);
    node.className = "play-calculator";
    S.saveSelf(native.id);
    const all = (data.allDecks || []).map((d) =>
      E.catalog(d, data.cards || []),
    );
    for (const c of all)
      if (c.cards.some((x) => x.section === "main")) S.saveDeck(c);
    // Show the complete roster. Availability and format are explained, not hidden.
    state.opponents = all;
    if (!native.cards.some((c) => c.section === "main")) {
      node.innerHTML = `<div class="play-empty"><h3>${esc(native.name)} · sin Main Deck cargado</h3><p>Este deck está registrado, pero necesita sus cartas del Main para generar manos y calcular opciones. Puedes seleccionar otro deck o abrir su ficha para cargar la lista.</p><button type="button" class="btn btn-outline-info" data-play-open-decklist>Abrir ficha del deck</button></div>`;
      node.querySelector("button").onclick = () => {
        if (node.closest('[data-screen="calculadora_jugadas"]')) {
          const url = new URL(location.href);
          url.search = "";
          url.searchParams.set("deck_id", native.id);
          url.hash = "deck_ficha";
          location.assign(url);
          return;
        }
        document
          .querySelector(
            '.deck-category-grid [data-deck-category-open="decklist"]',
          )
          ?.click();
      };
      return;
    }
    const baseFacts = fetch(
      "./assets/modules/play-calculator/card-facts.json?v=20261108-planner-152",
    ).then((r) => {
      if (!r.ok) throw Error("Catálogo no disponible");
      return r.json();
    });
    const ownStudy = root.YgoCalculatorStudy.load(native.id).catch(() => null);
    function prepareFacts() {
      const request = (state.factsRequest || 0) + 1;
      state.factsRequest = request;
      state.plannerFacts = null;
      const scenario = state.scenario;
      const rival =
        scenario && !scenario.practice
          ? root.YgoCalculatorStudy.load(scenario.opponent.id).catch(() => null)
          : Promise.resolve(null);
      Promise.all([baseFacts, ownStudy, rival])
        .then(([base, selfStudy, otherStudy]) => {
          if (instances.get(node) !== state || state.factsRequest !== request)
            return;
          applyEditorialImages(selfStudy, native);
          if (scenario && otherStudy) applyEditorialImages(otherStudy, scenario.opponent);
          state.plannerFacts = root.YgoCalculatorStudy.bind(base, [
            { catalog: native, study: selfStudy },
            ...(scenario
              ? [{ catalog: scenario.opponent, study: otherStudy }]
              : []),
          ]);
          const invalid = (state.plannerFacts.ruleImports || []).filter(r => r.status === "rejected");
          if (invalid.length) notice("Reglas del estudio no ejecutables: " +
            invalid.map(r => `deck ${r.deckId}: ${r.message}`).join("; "), true);
          if (selfStudy) {
            state.routes = root.YgoCalculatorStudy.compile(selfStudy, native);
            S.saveRoutes(native.id, {
              compiler: 2,
              binding: native.signature,
              routes: state.routes,
              matchups: [],
            });
          }
          if (state.scenario) draw();
        })
        .catch(() => {
          if (instances.get(node) === state && state.factsRequest === request)
            notice("No se pudo preparar el catálogo local del motor.", true);
        });
    }
    state.controller.signal.addEventListener("abort", () =>
      state.planWorker?.terminate(),
    );
    const opponent = null;
    function notice(text, error = false) {
      state.message = text;
      state.error = error;
      const n = node.querySelector("[data-play-message]");
      if (n) {
        n.textContent = text;
        n.setAttribute("role", error ? "alert" : "status");
      }
    }
    function commit(next) {
      state.history.push(E.clone(state.scenario));
      if (state.history.length > 30) state.history.shift();
      state.scenario = next;
      state.chainPreview = null;
      invalidatePlan();
      state.selection = null;
      draw();
    }
    function compatible(other) {
      return native.formats.some(
        (f) =>
          ["tcg_advanced", "tcg_traditional", "ocg", "ocg_advanced", "goat", "rush_duel"].includes(f) &&
          other.formats.includes(f),
      );
    }
    function opponentOption(o, s) {
      const ready = o.cards.some((c) => c.section === "main");
      return `<option value="${o.id}" ${!s?.practice && o.id === s?.opponent.id ? "selected" : ""} ${compatible(o) ? "" : "disabled"}>${esc(o.name)}${o.id === native.id ? " - espejo" : ""} - ${esc(o.formats.join(" / ") || "sin formato")}${compatible(o) ? "" : " - formato incompatible"}${ready ? "" : " - sin Main cargado"}</option>`;
    }
    state.setMode = (mode) => {
      if (!["practice", "duel"].includes(mode)) return false;
      if (mode === state.mode) return true;
      if (
        state.scenario?.tokens.length &&
        !root.confirm("Cambiar de modo inicia un tablero nuevo. ¿Continuar?")
      )
        return false;
      state.mode = mode;
      choose(mode === "practice" ? "free" : "");
      return true;
    };
    function choose(id) {
      invalidatePlan();
      state.planOptions = { used: [], blockedZones: [] };
      const other = state.opponents.find((c) => c.id === Number(id));
      if (other && !compatible(other)) {
        notice(
          "Estos decks no comparten formato. Selecciona un rival compatible.",
          true,
        );
        return;
      }
      if (!other && id !== "free") {
        state.scenario = null;
        draw();
        return;
      }
      state.mode = id === "free" ? "practice" : "duel";
      state.scenario = E.create(native, id === "free" ? null : other);
      prepareFacts();
      state.message =
        "Coloca las cartas de los dos decks. La preparación del duelo solo se mantiene mientras esta vista está abierta.";
      if (id === "free")
        state.message =
          "Práctica libre: genera una mano o coloca tus cartas para explorar opciones sin oponente.";
      if (other && !other.cards.some((c) => c.section === "main"))
        state.message +=
          " El rival no tiene Main cargado: solo puedes registrar cartas desconocidas; no se inventan cartas de su lista.";
      state.side = id === "free" ? "self" : "both";
      state.query = "";
      state.page = 0;
      state.pile = null;
      state.history = [];
      state.selection = null;
      state.analysis = false;
      draw();
      if (state.pendingPractice) {
        const hand = state.pendingPractice;
        state.pendingPractice = null;
        state.practice(hand);
      }
    }
    state.practice = (hand) => {
      if (!state.scenario) {
        state.pendingPractice = hand;
        notice(
          "Elige un rival para cargar esta mano de práctica. La Skill no se añade a la mano.",
        );
        return;
      }
      if (
        state.scenario.tokens.some(
          (t) => t.owner === "self" && t.zone === "hand",
        ) &&
        !root.confirm(
          "¿Reemplazar tu mano del escenario local por la mano de práctica? Puedes deshacerlo.",
        )
      )
        return;
      try {
        commit(E.practiceHand(state.scenario, hand));
        notice(
          "Mano de práctica cargada. Revisa turno, campo y requisitos; no se ha simulado una jugada. La Skill permanece fuera de la mano.",
        );
      } catch (error) {
        notice(
          error.message +
            ". El escenario se conserva; reproduce la mano manualmente.",
          true,
        );
      }
    };

    // Images belong to an exact native card slot, not to a guessed card name.
    // The active editorial record can contain photos for decks absent from the
    // historical originals index (for example Crystron and Memento).
    function applyEditorialImages(study, catalog) {
      if (!study || Number(study.deck_id) !== catalog.id || !Array.isArray(study.cards)) return;
      const own = new Map(catalog.cards.map(c => [c.key, c]));
      for (const entry of study.cards) {
        const url = entry?.image || entry?.full_image;
        if (typeof url !== 'string' ||
            !/^\.\/assets\/editorial\/[a-zA-Z0-9_./-]+\.(?:png|jpe?g|webp|gif)(?:\?[^#]*)?$/i.test(url) ||
            url.includes('..')) continue;
        for (const slot of entry.slots || []) {
          const key = catalog.id + ':' + Number(slot.card_id);
          const card = own.get(key);
          const aliases = [entry.name,entry.identity?.official_name,...(Array.isArray(entry.source_aliases)?entry.source_aliases:[])].filter(Boolean);
          if (!card || !aliases.some(name => E.norm(name) === E.norm(card.name))) continue;
          state.editorialImages[key] = url;
        }
      }
      if (state.scenario) draw();
    }
    function imageUrl(card) {
      // Try each grounded image source once. A missing editorial asset must not
      // suppress an existing original or provider image for the same card.
      const candidates = [
        state.editorialImages[card.key],
        state.images?.[card.key],
        card.imageId ? `./?api=deck_builder&op=image&id=${Number(card.imageId)}` : "",
      ];
      return candidates.find(url => url && !state.failedImageUrls.has(card.key + '|' + url)) || "";
    }
    function cardMarkup(card, owner, token) {
      const selected =
        state.selection &&
        (token
          ? state.selection.uid === token.uid
          : state.selection.key === card.key &&
            state.selection.owner === owner &&
            !state.selection.uid);
      const faceDown = token?.position === "set" || card.key === "unknown";
      const image = imageUrl(card);
      const negated = token && E.negated(state.scenario, token),
        used = token
          ? (state.scenario.effectUsage || []).filter(
              (e) => e.uid === token.uid && e.turn === state.scenario.turn,
            )
          : [];
      const marks = token
        ? `<span class="play-card-flags">${negated ? "<span>Negada</span>" : ""}${used.length ? "<span>" + used.map((e) => "E" + e.slot).join(", ") + " usados</span>" : ""}</span>`
        : "";
      return `<button type="button" class="play-card ${negated ? "is-negated" : ""} ${token?.position === "defense" || (token?.position === "set" && E.isMonster(token.zone)) ? "is-defense" : ""} ${faceDown ? "is-set" : ""} ${selected ? "is-selected" : ""}" draggable="true" data-play-card="${esc(card.key)}" data-play-owner="${owner}" ${token ? `data-play-token="${token.uid}"` : ""} aria-pressed="${!!selected}" aria-label="${esc(card.name)} · ${sideName[owner]}" title="${esc(card.name)} · ${sideName[owner]}">${faceDown ? '<span class="play-card-back" aria-hidden="true"></span>' : image ? `<img class="play-original-image" src="${esc(image)}" alt="${esc(card.name)}" loading="lazy" decoding="async" draggable="false" width="100" height="146"><span class="play-image-fallback">${esc(card.name)}</span>` : `<span class="play-image-unavailable">${esc(card.name)}<small>Imagen no disponible</small></span>`}<span class="play-card-caption">${esc(card.name)}</span><small class="play-card-count">${token ? (token.position === "set" ? "Boca abajo" : token.position === "defense" ? "Defensa" : "Boca arriba") : "×" + E.remaining(state.scenario, card.key, owner)}</small>${token ? `<b class="play-instance">#${token.uid}</b>` : ""}${marks}</button>`;
    }
    function slot(side, zone) {
      const s = state.scenario,
        tokens = s.tokens.filter(
          (t) =>
            t.zone === zone &&
            (zone.startsWith("emz") || t.controller === side),
        );
      return `<section class="play-zone ${zone.startsWith("emz") ? "is-extra-zone" : ""}" data-play-zone="${zone}" data-play-side="${side}" tabindex="0" role="group" aria-label="${sideName[side]} · ${zoneName(zone)}"><header>${zoneName(zone)}${tokens.length ? `<b>${tokens.length}</b>` : ""}${["tcg_advanced", "tcg_traditional", "ocg", "ocg_advanced"].includes(s.format) && (zone === "s1" || zone === "s5") ? "<small>Péndulo</small>" : ""}</header><div>${tokens.map((t) => cardMarkup(E.definition(s, t.key, t.owner), t.owner, t) + (s.tokens.some((x) => x.zone === "material:" + t.uid) ? `<small class="play-material-count">${s.tokens.filter((x) => x.zone === "material:" + t.uid).length} materiales</small>` : "")).join("") || '<span class="play-zone-symbol" aria-hidden="true">◇</span>'}</div>${tokens.length > 1 && !["hand"].includes(zone) ? `<button class="play-pile-button" type="button" data-play-pile="${zone}" data-play-side="${side}">Ver ${tokens.length}</button>` : ""}<button type="button" data-play-place="${zone}" data-play-side="${side}" aria-label="Colocar selección en ${sideName[side]} ${zoneName(zone)}" ${state.selection ? "" : "disabled"}>Colocar aquí</button></section>`;
    }
    function half(side) {
      const s = state.scenario,
        n = s.format === "rush_duel" ? 3 : 5;
      const row = (prefix) =>
        Array.from({ length: n }, (_, i) => slot(side, prefix + (i + 1))).join(
          "",
        );
      const hand = `<div class="play-hand">${slot(side, "hand")}${side === "opponent" && s.unknown.hand ? `<span class="play-hidden-count">${s.unknown.hand} cartas desconocidas</span>` : ""}${side === "self" && s.selfUnknownHand ? `<span class="play-hidden-count">${s.selfUnknownHand} robo propio por identificar</span>` : ""}</div>`;
      return `<section class="play-half is-${side}"><header class="play-player"><span>${esc(s[side].name)}<small>${sideName[side]}</small></span><strong>${Number(s.lp[side]).toLocaleString("es")} <small>LP</small></strong></header>${side === "opponent" ? hand : ""}<div class="play-field-row"><div class="play-side-zones">${slot(side, "field")}${slot(side, "extra")}</div><div class="play-main-zones" style="--zones:${n}"><div class="play-monsters">${row("m")}</div><div class="play-spells">${row("s")}</div></div><div class="play-side-zones">${slot(side, "graveyard")}${slot(side, "deck")}</div></div><div class="play-removed">${slot(side, "banished")}</div>${side === "self" ? hand : ""}</section>`;
    }
    function catalogView() {
      const s = state.scenario,
        owners = state.side === "both" ? ["self", "opponent"] : [state.side];
      const rows = owners.flatMap((owner) =>
        s[owner].cards
          .filter(
            (c) =>
              (!state.section || c.section === state.section) &&
              E.remaining(s, c.key, owner) > 0 &&
              E.norm(c.name + " " + c.role).includes(E.norm(state.query)),
          )
          .map((c) => ({ c, owner })),
      );
      rows.sort(
        (a, b) =>
          Number(a.owner !== "self") - Number(b.owner !== "self") ||
          Number(a.c.section === "extra") - Number(b.c.section === "extra") ||
          a.c.name.localeCompare(b.c.name, "es"),
      );
      const pages = Math.max(1, Math.ceil(rows.length / 12));
      state.page = Math.min(state.page, pages - 1);
      return `<aside class="play-catalog"><header><h4>Cartas del duelo</h4><p>${s.practice ? "Solo " + esc(s.self.name) : "Solo " + esc(s.self.name) + " y " + esc(s.opponent.name)}</p></header><label class="play-search-label"><i class="bi bi-search" aria-hidden="true"></i><input type="search" class="form-control" data-play-search value="${esc(state.query)}" placeholder="Buscar carta por nombre" aria-label="Buscar entre las cartas del duelo"></label><div class="play-catalog-filters"><label>Deck<select class="form-select" data-play-pool>${[
        ...(!s.practice ? [["both", "Ambos decks"]] : []),
        ["self", s.self.name + " · tú"],
        ...(!s.practice ? [["opponent", s.opponent.name + " · rival"]] : []),
      ]
        .map(
          ([k, v]) =>
            `<option value="${k}" ${state.side === k ? "selected" : ""}>${esc(v)}</option>`,
        )
        .join(
          "",
        )}</select></label><label>Sección<select class="form-select" data-play-section>${[
        ["", "Main + Extra"],
        ["main", "Main Deck"],
        ["extra", "Extra Deck"],
      ]
        .map(
          ([k, v]) =>
            `<option value="${k}" ${state.section === k ? "selected" : ""}>${v}</option>`,
        )
        .join(
          "",
        )}</select></label></div><div class="play-catalog-summary"><span>${rows.length} cartas disponibles</span><span>${state.page + 1} / ${pages}</span></div><div class="play-catalog-cards">${
        rows
          .slice(state.page * 12, state.page * 12 + 12)
          .map(
            ({ c, owner }) =>
              `<div class="play-catalog-entry is-${owner}">${cardMarkup(c, owner)}<span class="play-owner-label">${owner === "self" ? "Tu deck" : "Rival"} · ${c.section === "main" ? "Main" : "Extra"}</span></div>`,
          )
          .join("") || "<p>No hay cartas con esa búsqueda.</p>"
      }</div><div class="play-catalog-pages"><button type="button" class="btn btn-outline-info" data-play-page="-1" ${state.page === 0 ? "disabled" : ""}>Anterior</button><button type="button" class="btn btn-outline-info" data-play-page="1" ${state.page === pages - 1 ? "disabled" : ""}>Siguiente</button></div>${s.practice ? "" : '<button class="play-unknown-card" type="button" data-play-unknown-card>+ Carta rival oculta</button>'}<p>Las cantidades respetan Main y Extra. Side y Reserva permanecen fuera del duelo.</p></aside>`;
    }
    function selectedView() {
      const s = state.scenario,
        sel = state.selection,
        t = sel?.uid ? s.tokens.find((t) => t.uid === sel.uid) : null,
        card = sel ? E.definition(s, sel.key, sel.owner) : null;
      return `<section class="play-selection"><h4>${card ? esc(card.name) : "Selecciona una carta"}</h4>${card && imageUrl(card) ? `<a class="play-preview" href="${esc(imageUrl(card))}" target="_blank" rel="noopener" aria-label="Ampliar ${esc(card.name)}"><img src="${esc(imageUrl(card))}" alt="${esc(card.name)}" width="150" height="218"></a>` : ""}<p>${card ? "Arrastra la selección o elige una zona." : "Selecciona una carta o arrástrala directamente al campo."}</p>${
        state.pile
          ? `<details class="play-pile" open><summary>${zoneName(state.pile.zone)} · ${sideName[state.pile.side]}</summary><div>${s.tokens
              .filter(
                (t) =>
                  t.zone === state.pile.zone &&
                  t.controller === state.pile.side,
              )
              .map((t) =>
                cardMarkup(E.definition(s, t.key, t.owner), t.owner, t),
              )
              .join("")}</div></details>`
          : ""
      }${
        card
          ? `<label>Lado de control<select class="form-select" data-play-control><option value="self" ${(sel.controller || sel.owner) === "self" ? "selected" : ""}>Tu lado</option><option value="opponent" ${(sel.controller || sel.owner) === "opponent" ? "selected" : ""}>Rival</option></select></label><label>Zona<select class="form-select" data-play-destination>${E.zones(
              s,
            )
              .map((z) => `<option value="${z}">${zoneName(z)}</option>`)
              .join("")}${s.tokens
              .filter((x) => E.isMonster(x.zone) && x.uid !== t?.uid)
              .map(
                (x) =>
                  `<option value="material:${x.uid}">Material bajo #${x.uid} · ${esc(E.definition(s, x.key, x.owner).name)}</option>`,
              )
              .join(
                "",
              )}<option value="deck">Devolver a su Deck</option></select></label><button class="btn btn-info" type="button" data-play-move>Mover selección</button>${t ? `<label>Posición<select class="form-select" data-play-position><option value="attack" ${t.position === "attack" ? "selected" : ""}>Boca arriba / ataque</option><option value="defense" ${t.position === "defense" ? "selected" : ""}>Defensa boca arriba</option><option value="set" ${t.position === "set" ? "selected" : ""}>Boca abajo</option></select></label>` : ""}`
          : ""
      }
    ${
      s.tokens.some((t) => t.zone.startsWith("material:"))
        ? `<details><summary>Materiales acoplados</summary>${s.tokens
            .filter((t) => t.zone.startsWith("material:"))
            .map((t) => cardMarkup(E.definition(s, t.key, t.owner), t.owner, t))
            .join("")}</details>`
        : ""
    }</section>`;
    }
    function positionTools() {
      const token = state.scenario.tokens.find(
        (t) => t.uid === state.selection?.uid,
      );
      if (!token)
        return `<section class="play-position-tools"><p>Selecciona una carta colocada para cambiar su posición: boca arriba, defensa o boca abajo.</p></section>`;
      const card = E.definition(state.scenario, token.key, token.owner);
      const monster = E.isMonster(token.zone);
      return `<section class="play-position-tools" aria-label="Posición de la carta seleccionada"><strong>${esc(card.name)} <small>#${token.uid} · ${zoneName(token.zone)}</small></strong><button type="button" class="btn btn-outline-info" data-play-token-menu="${token.uid}">Opciones de carta</button><div role="group" aria-label="Cambiar posición">${[["attack", monster ? "Ataque boca arriba" : "Boca arriba"], ...(monster ? [["defense", "Defensa boca arriba"]] : []), ["set", monster ? "Defensa boca abajo" : "Boca abajo"]].map(([value, label]) => `<button type="button" class="btn btn-outline-info" data-play-quick-position="${value}" aria-pressed="${token.position === value}" ${token.key === "unknown" && value !== "set" ? "disabled" : ""}>${label}</button>`).join("")}</div></section>`;
    }
    function invalidatePlan() {
      state.planRequest++;
      state.planWorker?.terminate();
      state.planWorker = null;
      state.planBusy = false;
      state.planResult = null;
    }
    function plannerSettings() {
      const opts = state.planOptions,
        flags = [
          ["specialBlocked", "No puedo Invocar Especialmente"],
          ["noExtra", "No puedo Invocar desde el Extra"],
          ["machineSynchroOnly", "Extra limitado a Sincronía Máquina este turno"],
          ["xyzOnly", "Extra limitado a Xyz"],
          [
            "graveyardBlocked",
            "Cementerio bloqueado o con reemplazo no modelado",
          ],
          ["effectsNegated", "Efectos de mis cartas negados"],
          [
            "noDeckToHand",
            "No se puede añadir del Main a la mano (por ejemplo, Droll ya resuelto)",
          ],
        ];
      const ownCoverage=(state.plannerFacts?.studyCoverage||[]).find(row=>Number(row.id)===native.id);
      const imported=(state.plannerFacts?.ruleImports||[]).find(row=>Number(row.deckId)===native.id);
      const activeRuleVariants=(state.plannerFacts?.rules||[]).filter(rule=>rule.deckId===native.id && native.formats.includes(rule.format));
      const activeEffectIds=new Set(activeRuleVariants.map(rule=>rule.id)).size;
      const supplied=imported?.status==='accepted'?Number(imported.count||0):0;
      const coverage=ownCoverage
        ? `<section class="play-analysis-summary" role="note"><strong>Alcance del análisis de este deck:</strong> ${Number(ownCoverage.routes||0)} rutas documentadas, ${supplied} reglas declarativas aceptadas desde el estudio y ${activeEffectIds} IDs de efecto declarativos (${activeRuleVariants.length} variantes de ejecución). Ninguno de estos recuentos certifica una ruta completa. ${imported?.status==='rejected'?'El paquete de reglas fue rechazado: '+esc(imported.message):''}</section>`
        : '<p class="play-analysis-summary">Sin estudio editorial vinculado; solo se calculan acciones explícitas implementadas en el motor.</p>';
      return `${coverage}<section class="play-planner-settings"><p>El botón «Analizar» evalúa tu estado actual con las reglas implementadas. No necesitas elegir objetivos: se revisan las acciones verificables de la mano, el campo y el Cementerio. Una acción legal por sí sola no demuestra que sea útil. Los efectos aún sin modelar se identifican por separado.</p><details><summary>Restricciones y efectos ya utilizados para calcular</summary><p>Declara los bloqueos actuales. Las notas libres no se interpretan como reglas.</p><div>${flags.map(([key, label]) => `<label><input type="checkbox" data-planner-flag="${key}" ${opts[key] ? "checked" : ""}> ${label}</label>`).join("")}</div><label>Zonas propias bloqueadas (por ejemplo: m1,s2)<input class="form-control" data-planner-zones value="${esc(opts.blockedZones.join(","))}" placeholder="m1,s2,emz1"></label><fieldset><legend>Efectos ya usados este turno</legend>${(root.YgoPlayPlanner?.capabilities(state.scenario, state.plannerFacts || { facts: {}, bindings: {} }) || []).map((e) => `<label><input type="checkbox" data-planner-used="${e.id}" ${opts.used.includes(e.id) ? "checked" : ""}> ${esc(e.label)}</label>`).join("") || "<p>No hay efectos específicos disponibles para marcar.</p>"}</fieldset></details></section>`;
    }
    function calculate() {
      invalidatePlan();
      state.analysis = true;
      if (!state.plannerFacts || !root.YgoPlayPlanner) {
        notice(
          "Se está preparando el catálogo local del motor. Vuelve a calcular cuando termine.",
          true,
        );
        return;
      }
      state.planBusy = true;
      const id = state.planRequest;
      const worker = new Worker(
        "./assets/modules/play-calculator/planner-worker.js?v=20261120-smiger-rule-1",
      );
      state.planWorker = worker;
      worker.onmessage = (e) => {
        if (instances.get(node) !== state || e.data.id !== state.planRequest)
          return;
        worker.terminate();
        state.planWorker = null;
        state.planBusy = false;
        if (e.data.error)
          state.planResult = {
            plans: [],
            unsupported: [],
            reason: e.data.error,
            assumptions: [],
            explored: 0,
          };
        else state.planResult = e.data.result;
        draw();
        node.querySelector(".play-results")?.scrollIntoView({ block: "start" });
      };
      worker.onerror = () => {
        if (state.planRequest !== id) return;
        worker.terminate();
        state.planBusy = false;
        state.planResult = {
          plans: [],
          unsupported: [],
          reason:
            "No se pudo ejecutar el motor local. Revisa que sus recursos están preparados.",
          assumptions: [],
          explored: 0,
        };
        draw();
      };
      worker.postMessage({
        id,
        scenario: E.clone(state.scenario),
        facts: state.plannerFacts,
        options: state.planOptions,
      });
      draw();
      node.querySelector(".play-results")?.scrollIntoView({ block: "start" });
    }
    function auditView() {
      const audit = state.planResult?.audit;
      if (!audit) return "";
      const labels = {
        partial: "Efectos parciales",
        response: "Respuesta específica",
        "response-pending": "Respuesta pendiente de resolución",
        declared: "Regla estructurada",
        missing: "Efectos sin modelar",
      };
      return `<section class="play-scenario-audit"><h4>Qué se analizó y qué falta</h4><p><b>Análisis parcial del escenario.</b> ${audit.hidden} registros de información oculta o boca abajo. No se supone que el rival no tenga respuestas.</p><div class="play-audit-sides">${audit.sides
        .filter((side) => side.total)
        .map(
          (side) =>
            `<details><summary>${esc(side.name)} · ${side.modelled} de ${side.total} identidades con alguna acción o respuesta modelada</summary><p>Es cobertura parcial por carta; no acredita todos sus efectos.</p><ul>${side.cards.map((c) => `<li>${esc(c.name)} — ${labels[c.scope]}${c.positioned ? " · " + c.positioned + " copia(s) en el escenario" : ""}</li>`).join("")}</ul></details>`,
        )
        .join(
          "",
        )}</div>${audit.knownResponses.length ? `<p><b>Respuestas conocidas en mano rival:</b> ${audit.knownResponses.map((c) => esc(c.name) + (c.cid === 14741 ? " (Nibiru: conteo y resolución pendientes)" : "")).join(", ")}.</p>` : ""}${audit.unresolvedField.length ? `<p><b>Cartas en campo con efectos pendientes:</b> ${audit.unresolvedField.map((c) => esc(c.name)).join(", ")}. Revisa sus protecciones, bloqueos y disparos antes de usar una propuesta.</p>` : ""}<details><summary>Límites del cálculo</summary><ul>${audit.limits.map((t) => `<li>${esc(t)}</li>`).join("")}</ul>${audit.invalidRules.map((r) => `<p>Regla ${esc(r.id)} sin ejecutar: ${esc(r.message)}</p>`).join("")}</details></section>`;
    }
    function responsesView(plan, renderState) {
      const rows = plan.responses || [];
      return `<details class="play-response-branches"><summary>Si responde el rival · ${rows.length} casos conocidos${plan.responsesLimited ? " · límite de búsqueda alcanzado" : ""}</summary>${rows.length ? rows.map((row) => `<article><h5>Paso ${row.step}: ${esc(row.card)}</h5><p>${row.status === "unresolved" ? "Resolución pendiente de modelar" : "Rama condicional calculada"}</p><p>${esc(row.reason)}</p><ol>${row.chain.map((t) => `<li>${esc(t)}</li>`).join("")}</ol>${row.state ? `<details><summary>Estado tras esta respuesta, incluidos costes pagados</summary>${renderState(row.state)}</details>` : ""}${row.continuations.map((next, i) => `<details><summary>Continuación ${i + 1} desde ese estado</summary><ol>${next.steps.map((step) => `<li>${esc(step.label)}</li>`).join("")}</ol>${renderState(next.final)}</details>`).join("")}${row.continuationReason ? `<p>${esc(row.continuationReason)}</p>` : ""}<p>${row.limits.map(esc).join(" ")}</p><a href="${esc(row.source)}" target="_blank" rel="noopener">Texto oficial de la respuesta</a></article>`).join("") : "<p>No se calculó una respuesta con las cartas conocidas y ventanas implementadas. Esto no demuestra que no existan respuestas.</p>"}</details>`;
    }
    function battleView(battle) {
      if (!battle) return "";
      const entries = battle.attacks || [];
      return `<section class="play-scenario-audit"><h4>Estudio de combate (condicional)</h4><p>${esc(battle.reason || "Comparación aritmética de ATK y DEF conocidos.")}</p>${battle.directDamageCeiling !== null ? `<p>Daño directo hipotético máximo de un ataque por monstruo: <b>${battle.directDamageCeiling} LP</b>${battle.possibleLethalArithmetic ? " · supera los LP rivales, pero no certifica OTK" : ""}.</p>` : ""}${entries.length ? `<ul>${entries.map((a) => `<li>${esc(a.attackerName || "Atacante #" + a.attacker)} ${a.target ? "contra " + esc(a.targetName || "carta #" + a.target) : "· ataque directo"}: ${a.status === "unknown" ? "indeterminado" : a.damage + " LP aritméticos"}. ${esc(a.reason)}</li>`).join("")}</ul>` : ""}<details><summary>Límites del estudio de combate</summary><ul>${battle.assumptions.map((a) => `<li>${esc(a)}</li>`).join("")}</ul></details></section>`;
    }
    function plannerView() {
      if (state.planBusy)
        return '<p role="status">Calculando secuencias y estados posteriores en tu equipo...</p><button type="button" class="btn btn-outline-info" data-planner-cancel>Cancelar cálculo</button>';
      const result = state.planResult;
      if (!result)
        return "<p>Completa el tablero y las restricciones; pulsa Calcular opciones para buscar secuencias nuevas desde ese estado.</p>";
      const pics = (cards) =>
        cards
          .map((c) => {
            const def = E.definition(state.scenario, c.key, c.owner),
              url = def && imageUrl(def);
            return `<figure>${url ? `<img src="${esc(url)}" alt="${esc(c.name || def.name)}" loading="lazy">` : ""}<figcaption>${esc(c.name || def?.name || "Carta desconocida")}${c.from ? `<small>${esc(zoneName(c.from))} → ${esc(zoneName(c.to))}</small>` : ""}</figcaption></figure>`;
          })
          .join("");
      const renderState = (after) =>
        ["self", "opponent"]
          .map(
            (side) =>
              `<section><h5>${sideName[side]}</h5>${[
                "field",
                "monsters",
                "spells",
                "hand",
                "graveyard",
                "banished",
                "materials",
              ]
                .map((zone) => {
                  const cards = after.filter(
                    (t) =>
                      t.controller === side &&
                      (zone === "monsters"
                        ? E.isMonster(t.zone)
                        : zone === "spells"
                          ? /^s[1-5]$/.test(t.zone)
                          : zone === "materials"
                            ? t.zone.startsWith("material:")
                            : t.zone === zone),
                  );
                  return cards.length
                    ? `<div><h6>${{ monsters: "Monstruos", spells: "Magias/Trampas", materials: "Materiales Xyz" }[zone] || zoneName(zone)}</h6><div class="play-plan-photos">${pics(cards)}</div><p>${cards.map((c) => `${esc(c.name)} · ${esc(zoneName(c.zone))} · ${c.position === "set" ? "boca abajo" : c.position === "defense" ? "defensa" : "boca arriba"}`).join("<br>")}</p></div>`
                    : "";
                })
                .join("")}</section>`,
          )
          .join("");
      return `${battleView(result.battle)}<h4>Secuencias calculadas desde tu estado</h4>${result.assessment ? `<p><b>Estado de cálculo: ${esc(result.assessment.label)}.</b> ${esc(result.assessment.explanation)}</p>` : ""}<p><b>Evaluación del estado capturado:</b> ${result.explored} estados explorados · ${result.plans.length} alternativas con acciones modeladas${result.limited ? " · búsqueda limitada, no exhaustiva" : ""}. La puntuación solo ordena escenarios observables.</p>${result.reason ? `<p>${esc(result.reason)}</p>` : ""}<details open><summary>Supuestos y límites de estas propuestas</summary><ul>${result.assumptions.map((t) => `<li>${esc(t)}</li>`).join("")}</ul></details>${result.plans.map((p, i) => `<article class="play-computed-plan"><details><summary><b>Opción ${i + 1}: ${p.removed > 0 ? "retirar o tomar " + p.removed + " carta(s) rival(es)" : "desarrollar campo y recursos"}</b></summary><p>${p.steps.length} acciones · prioridad heurística ${p.priority} · LP propios ${p.lp.self}${p.xyzOnly ? " · Extra limitado a Xyz" : ""}.</p>${p.metrics ? `<p><b>Indicadores observables:</b> ${p.metrics.removed} cartas rivales retiradas · ${p.metrics.hand} cartas propias en mano · ${p.metrics.actions} acciones. No equivalen a una probabilidad de victoria.</p>` : ""}${p.responseAudit ? `<p><b>Respuestas conocidas exploradas:</b> ${p.responseAudit.modeled} ramas condicionales, ${p.responseAudit.unresolved} sin resolver, ${p.responseAudit.noContinuation} sin continuación calculada${p.responseAudit.truncated ? "; presupuesto agotado" : ""}. Esto no mide la seguridad real de la línea.</p>` : ""}<ol>${p.steps.map((step) => `<li><p>${esc(step.label)}</p><div class="play-plan-photos">${pics(step.cards)}</div><a href="${esc(step.source)}" target="_blank" rel="noopener">Fuente de la acción</a><details><summary>Estado después de esta acción</summary><div class="play-plan-state">${renderState(step.after)}</div></details></li>`).join("")}</ol>${responsesView(p, renderState)}<details><summary>Comparar con tu estado inicial y ver el resultado final</summary><h5>Estado capturado</h5><div class="play-plan-state">${renderState(state.scenario.tokens)}</div><h5>Resultado propuesto</h5><div class="play-plan-state">${renderState(p.final)}</div><p>Normal restante: ${p.normalAvailable ? "sí" : "no"}. Esta vista previa no modifica el tablero.</p></details></details></article>`).join("")}<details><summary>Cartas presentes con efectos aún sin modelar (${result.unsupported.length})</summary><p>Solo se enumeran cartas visibles en tu estado capturado (mano, campo, Cementerio o destierro) cuyos efectos específicos aún no están modelados. No se sugieren Invocaciones Normales genéricas como estrategia sin una regla acreditada.</p><ul>${result.unsupported.map((c) => `<li>${esc(c.name)}</li>`).join("")}</ul></details>`;
    }
    function recommendations() {
      if (!state.analysis)
        return "<p>Cuando termines de cargar la situación, pulsa «Calcular opciones».</p>";
      const rows = E.suggestions(state.scenario, state.routes);
      const plan = state.matchups.filter(
        (r) => Number(r.deckId) === state.scenario.opponent.id && r.plan,
      );
      const totals = { blocked: 0, conditional: 0, confirmed: 0 };
      rows.forEach((r) => totals[r.status]++);
      return `<p class="play-analysis-summary">${rows.length} rutas revisadas: ${totals.blocked} con requisitos incumplidos, ${totals.conditional} por comprobar y ${totals.confirmed} con inicio confirmado por ti. El tablero permanece igual.</p>${plan.length ? `<details><summary>Plan documentado ante ${esc(state.scenario.opponent.name)}</summary>${[...new Set(plan.map((p) => p.plan))].map((p) => `<p>${esc(p)}</p>`).join("")}</details>` : ""}${rows.length ? rows.map((r) => `<article class="play-recommendation is-${r.status}"><header><span>${{ blocked: "Requisitos incumplidos", conditional: "Por comprobar", confirmed: "Inicio confirmado por ti" }[r.status]}</span><h4>${esc(r.title)}</h4></header>${r.matched.length ? `<p><b>Condiciones reconocidas:</b> ${esc(r.matched.join(", "))}</p>` : ""}${r.missing.length ? `<ul>${r.missing.map((m) => `<li>${esc(m)}</li>`).join("")}</ul>` : ""}<details ${r.status === "confirmed" ? "open" : ""}><summary>Condiciones, pasos y por qué se propone</summary><p>${esc(r.conditions)}</p><p>${esc(r.sourceStatus)}</p><ul>${r.pending.map((m) => `<li>${esc(m)}</li>`).join("")}</ul><ol>${r.steps.map((text) => `<li>${esc(text)}</li>`).join("")}</ol><p>${esc(r.result)}</p><p>${esc(r.warning)}</p></details>${r.status !== "blocked" ? `<label class="play-confirm"><input type="checkbox" data-play-confirm="${esc(r.id)}" ${r.confirmed ? "checked" : ""}> He comprobado en el duelo los requisitos textuales y las condiciones que el calculador no verifica.</label>` : ""}</article>`).join("") : "<p>No hay rutas documentadas locales para esta lista. El tablero sigue disponible; no se inventan jugadas.</p>"}`;
    }
    function handPractice() {
      const s = state.scenario,
        H = root.YgoHandPractice;
      const hand = s.tokens.filter(
        (t) => t.owner === "self" && t.zone === "hand",
      );
      const left = H?.remaining(s).length || 0;
      return `<section class="play-hand-practice"><header><h4>${s.practice ? "Prueba tu mano sin oponente" : "Practicar aperturas"}</h4><span>${hand.length} en mano · ${left} en Main</span></header><p>Generar una apertura reinicia este tablero al turno 1 y toma ${s.format === "rush_duel" ? 4 : 5} cartas del Main según sus copias. Los robos de fase son manuales; Extra, Side, Reserva y Skills no se roban.</p><div class="play-actions"><button class="btn btn-primary" type="button" data-play-random-hand>Generar mano aleatoria</button><button class="btn btn-outline-info" type="button" data-play-draw-card ${left ? "" : "disabled"}>Robar 1 carta</button><button class="btn btn-outline-info" type="button" data-play-explore-hand ${hand.length ? "" : "disabled"}>Explorar líneas de esta mano</button></div><div class="play-opening-cards">${hand.map((t) => cardMarkup(E.definition(s, t.key, t.owner), t.owner, t)).join("") || "<p>Genera una apertura o coloca cartas en tu mano.</p>"}</div></section>`;
    }
    function turnBar(s) {
      const chains = (s.chain || []).map((link) => `<li>CL${link.link} · ${link.side === "self" ? "Tú" : "Rival"}${link.cardName ? " · " + esc(link.cardName) : ""}: ${esc(link.effect)} <button type="button" data-chain-remove="${link.link}" aria-label="Eliminar eslabón ${link.link}">Eliminar</button></li>`).join("");
      return `<section class="play-turn-bar" aria-label="Turno del duelo"><label>Número de turno<input class="form-control" type="number" min="1" max="999" data-play-setting="turn" value="${s.turn}"></label><label>Quién tiene el turno<select class="form-select" data-play-setting="active"><option value="self" ${s.active === "self" ? "selected" : ""}>${esc(s.self.name)} - tu lado</option>${s.practice ? "" : `<option value="opponent" ${s.active === "opponent" ? "selected" : ""}>${esc(s.opponent.name)} - rival</option>`}</select></label><strong>Turno ${s.turn} - ${esc(s[s.active].name)}</strong><details class="play-chain-capture"><summary>Cadena pendiente · ${(s.chain || []).length} eslabones</summary><p>Selecciona una carta del tablero o catálogo si conoces su identidad. Captura los eslabones en orden de activación; esta sección no resuelve sus efectos.</p><ol>${chains}</ol><label>Jugador<select class="form-select" data-chain-side><option value="self">Yo</option>${s.practice ? "" : '<option value="opponent">Rival</option>'}</select></label><label>Activación o efecto pendiente<input class="form-control" maxlength="300" data-chain-effect placeholder="Ej.: activa efecto al ser invocado"></label><button type="button" class="btn btn-outline-info" data-chain-add>Añadir eslabón</button></details></section>`;
    }
    function chainControls(s) {
      const unique = new Map();
      for (const rule of state.plannerFacts?.rules || [])
        if (rule.deckId === s.self.id && rule.format === s.format && rule.activation && !unique.has(rule.id))
          unique.set(rule.id, rule.label);
      const actors = s.tokens.filter((t) => t.controller === "self");
      const preview = state.chainPreview;
      return `<div class="play-chain-executable"><p><b>Resolución condicional:</b> selecciona el efecto acreditado de CL1 y su copia. Para CL2 registra solo una negación que hayas comprobado. No se valida automáticamente la carta rival.</p><label>Regla verificada de CL1<select data-chain-rule><option value="">Sin regla ejecutable</option>${[...unique].map(([id, label]) => `<option value="${esc(id)}">${esc(label)}</option>`).join("")}</select></label><label>Copia de CL1<select data-chain-actor><option value="">Selecciona copia</option>${actors.map((t) => `<option value="${t.uid}">#${t.uid} · ${esc(E.definition(s, t.key, t.owner)?.name || t.key)} (${esc(zoneName(t.zone))})</option>`).join("")}</select></label><label>Resultado conocido de CL2<select data-chain-outcome><option value="">Sin resolución acreditada</option><option value="negate-effect">Niega el efecto (declarado)</option><option value="negate-activation">Niega la activación (declarado)</option></select></label><button type="button" class="btn btn-outline-info" data-chain-preview>Calcular eslabones acreditados</button>${preview ? `<p>${esc(preview.status === "conditional" ? preview.warning : preview.reason)}</p>${preview.branches.map((b, i) => `<div><span>${esc(b.label)} · ${b.effectNegated ? "Efecto negado" : "Efecto resuelto"}</span> <button type="button" class="btn btn-outline-info" data-chain-apply="${i}">Aplicar estado ${i + 1}</button></div>`).join("")}` : ""}</div>`;
    }
    function draw() {
      if (instances.get(node) !== state) return;
      const s = state.scenario;
      node.innerHTML = `<header class="play-heading"><div><span class="learn-kicker">Herramienta local de estudio</span><h3>Calculador de jugadas · ${esc(native.name)}</h3><p>Reproduce lo que conoces del duelo. La captura del tablero no ejecuta cartas ni valida automáticamente sus efectos.</p></div><span class="play-offline">${navigator.onLine ? "Cálculo local" : "Sin conexión · cálculo local"}</span></header>${state.externalMode ? "" : `<nav class="play-mode-tabs" aria-label="Modo del calculador"><button type="button" class="btn btn-outline-info" data-play-mode="duel" aria-pressed="${state.mode === "duel"}">Duelo</button><button type="button" class="btn btn-outline-info" data-play-mode="practice" aria-pressed="${state.mode === "practice"}">Práctica</button></nav>`}${state.mode === "duel" ? `<label class="play-opponent">Deck oponente<select class="form-select" data-play-opponent><option value="">Selecciona un oponente</option>${state.opponents.map((o) => opponentOption(o, s)).join("")}</select></label>` : '<p class="play-practice-intro">Práctica sin oponente: genera una mano y explora tus opciones.</p>'}<p data-play-message role="${state.error ? "alert" : "status"}">${esc(state.message)}</p>${s ? `${turnBar(s)}<details class="play-context"><summary>Turno ${s.turn} · ${s.active === "self" ? "Tu turno" : "Turno rival"} · Ajustar estado del duelo</summary><section class="play-setup"><label>Orden inicial<select class="form-select" data-play-setting="goesFirst"><option value="true" ${s.goesFirst ? "selected" : ""}>Empiezo primero</option><option value="false" ${s.goesFirst ? "" : "selected"}>Empiezo segundo</option></select></label><label>Fase<select class="form-select" data-play-setting="phase">${[["draw", "Robo"], ["standby", "Espera"], ["main1", "Main / principal"], ["battle", "Batalla"], ...(s.format === "rush_duel" ? [] : [["main2", "Main 2"]]), ["end", "Final"]].map(([k, v]) => `<option value="${k}" ${s.phase === k ? "selected" : ""}>${v}</option>`).join("")}</select></label><label>Mis LP<input class="form-control" type="number" min="0" max="999999" data-play-lp="self" value="${s.lp.self}"></label><label>LP rival<input class="form-control" type="number" min="0" max="999999" data-play-lp="opponent" value="${s.lp.opponent}"></label><label><input type="checkbox" data-play-setting="normalAvailable" ${s.normalAvailable ? "checked" : ""}> Puedo usar la Invocación Normal</label><label>Mano rival desconocida<input class="form-control" type="number" min="0" max="99" data-play-unknown="hand" value="${s.unknown.hand}"></label><label>Desterradas desconocidas<input class="form-control" type="number" min="0" max="99" data-play-unknown="banished" value="${s.unknown.banished}"></label><label><input type="checkbox" data-play-setting="opponentHandComplete" ${s.opponentHandComplete ? "checked" : ""}> Conozco toda la mano rival</label></section></details>${state.mode === "practice" ? handPractice() : ""}<div class="play-workspace">${positionTools()}<div class="play-board-scroll" tabindex="0" aria-label="Tablero de duelo"><div class="play-board">${s.practice ? '<p class="play-free-field">Práctica libre · sin campo rival</p>' : half("opponent")}${["tcg_advanced", "tcg_traditional", "ocg", "ocg_advanced"].includes(s.format) ? `<div class="play-shared">${slot("self", "emz1")}${slot("self", "emz2")}</div>` : '<div class="play-center">' + (s.format === "rush_duel" ? "RUSH DUEL · 3 zonas por fila" : "GOAT · 5 zonas por fila") + "</div>"}${half("self")}</div></div><aside class="play-tools">${catalogView()}${selectedView()}</aside></div><section class="play-notes"><label>Efectos ya utilizados y restricciones conocidas<textarea class="form-control" maxlength="2000" data-play-setting="usedEffects">${esc(s.usedEffects)}</textarea></label><label>Notas del estado físico<textarea class="form-control" maxlength="4000" data-play-setting="notes">${esc(s.notes)}</textarea></label></section>${plannerSettings()}<div class="play-actions"><button class="btn btn-primary" type="button" data-play-analyze>Analizar escenario y opciones</button><button class="btn btn-outline-info" type="button" data-play-undo ${state.history.length ? "" : "disabled"}>Deshacer</button><button class="btn btn-outline-danger" type="button" data-play-reset>Vaciar tablero</button></div><section class="play-results" aria-live="polite"><h3>Opciones según el estado aportado</h3><p>El motor busca secuencias con las acciones implementadas desde tu mano y campo, y muestra sus estados posteriores. Las respuestas rivales, protecciones y efectos sin modelar requieren comprobación; no se calculan todas las jugadas legales.</p>${auditView()}${plannerView()}<details><summary>Rutas documentadas de referencia</summary>${recommendations()}</details></section>` : '<div class="play-empty"><h4>Elige práctica libre o un rival para preparar el tablero</h4><p>En práctica libre puedes generar aperturas con tu Main y explorar líneas sin seleccionar un oponente. Al elegir un rival se cargan las cartas de ambos decks.</p></div>'}`;
      if (s) node.querySelector(".play-chain-capture")?.insertAdjacentHTML("beforeend", chainControls(s));
      if (s && !s.practice) node.querySelector(".play-turn-bar")?.insertAdjacentHTML("beforeend",
        `<button type="button" class="btn btn-outline-info" data-play-observe-opponent-add
        ${s.phase === "draw" || (s.chain || []).length ? "disabled" : ""}>Confirmar adición rival desde Main Deck</button>
        <small>Úsalo solo tras observar la adición, fuera de Robo. Cualquier edición posterior cierra esta ventana.</small>`);

      if (s) node.querySelector(".play-turn-bar")?.insertAdjacentHTML("beforeend",
        `<button type="button" class="btn btn-outline-info" data-play-next-turn ${s.turn >= 999 || (s.chain || []).length ? "disabled" : ""}>Siguiente turno · sin robo automático</button>`);
      if (s) node.querySelector(".play-turn-bar")?.insertAdjacentHTML("beforeend",
        `<button type="button" class="btn btn-outline-info" data-play-next-phase ${(s.phase === "end" || (s.chain || []).length) ? "disabled" : ""}>Siguiente fase · manual</button>`);
      if (s) node.querySelector(".play-chain-executable")?.insertAdjacentHTML("afterbegin",
        "<p>Hasta CL20 con reglas propias verificadas; CL2–CL20 necesitan velocidad 2 acreditada. Las negaciones rivales se simulan únicamente como declaraciones condicionales y no se inventan sus efectos.</p>");
      if (s?.practice) {
        for (const selector of [
          '[data-play-lp="opponent"]',
          "[data-play-unknown]",
          '[data-play-setting="opponentHandComplete"]',
        ])
          node.querySelectorAll(selector).forEach((el) => {
            el.closest("label").hidden = true;
          });
        node
          .querySelectorAll(
            '[data-play-setting="active"] option[value="opponent"], [data-play-control] option[value="opponent"]',
          )
          .forEach((el) => el.remove());
      }
    }
    function move(zone, controller) {
      const sel = state.selection;
      if (!sel) throw Error("Selecciona primero una carta.");
      commit(
        E.place(state.scenario, {
          ...sel,
          zone,
          controller: controller || sel.controller || sel.owner,
        }),
      );
    }
    {
      node.addEventListener(
        "click",
        (event) => {
          const current = instances.get(node);
          if (current !== state) return;
          try {
            const b = event.target.closest("button");
            if (!b) return;
            if (b.dataset.playMode) {
              state.setMode(b.dataset.playMode);
              return;
            }
            if (b.hasAttribute("data-play-next-turn")) {
              commit(E.advanceTurn(state.scenario));
              notice("Turno avanzado. Declara manualmente el robo y los efectos de transición.");
              return;
            }
            if (b.hasAttribute("data-play-next-phase")) {
              commit(E.advancePhase(state.scenario));
              notice("Fase avanzada. No se ejecutaron efectos ni acciones automáticas.");
              return;
            }
            if (b.hasAttribute("data-play-observe-opponent-add")) {
              commit(E.observeOpponentMainDeckAdd(state.scenario));
              notice("Adición rival desde el Main Deck confirmada para esta ventana. Analiza sin modificar más el tablero.");
              return;
            }
            if (b.hasAttribute("data-chain-add")) {
              const side = node.querySelector("[data-chain-side]").value;
              const selected = state.selection;
              const selectedToken = state.scenario.tokens.find((t) => t.uid === selected?.uid);
              const selectedSide = selectedToken?.controller || selected?.owner;
              const card = selectedSide === side && selected ? E.definition(state.scenario, selected.key, selected.owner) : null;
              const ruleId = side === "self" ? node.querySelector("[data-chain-rule]")?.value : "";
              const actorUid = Number(node.querySelector("[data-chain-actor]")?.value);
              const outcome = side === "opponent" ? node.querySelector("[data-chain-outcome]")?.value : "";
              commit(E.appendChain(state.scenario, {
                side,
                cardName: card?.name === "Carta rival desconocida" ? "" : card?.name || "",
                effect: node.querySelector("[data-chain-effect]").value,
                ...(ruleId ? { ruleId, actorUid } : {}),
                ...(outcome ? { outcome } : {}),
              }));
              return;
            }
            if (b.hasAttribute("data-chain-preview")) {
              state.chainPreview = root.YgoPlayChain?.resolve(state.scenario, state.plannerFacts || { rules: [], bindings: {} }) ||
                { status: "unresolved", reason: "Resolución estructurada no disponible", branches: [] };
              draw();
              return;
            }
            if (b.hasAttribute("data-chain-apply")) {
              const choice = state.chainPreview?.branches[Number(b.dataset.chainApply)];
              if (!choice) throw Error("La opción de cadena ya no está disponible");
              commit(E.clone(choice.state));
              notice("Resolución condicional aplicada. Comprueba los efectos del rival antes de continuar.");
              return;
            }
            if (b.hasAttribute("data-chain-remove")) {
              commit(E.removeChain(state.scenario, Number(b.dataset.chainRemove)));
              return;
            }
            if (b.hasAttribute("data-play-random-hand")) {
              const next = root.YgoHandPractice.opening(state.scenario);
              commit(next);
              state.planOptions = { used: [], blockedZones: [] };
              draw();
              notice(
                "Mano aleatoria cargada. Puedes mover sus cartas, explorar líneas o generar otra apertura; Deshacer recupera el tablero anterior.",
              );
            } else if (b.hasAttribute("data-play-draw-card")) {
              commit(root.YgoHandPractice.draw(state.scenario));
              notice(
                "Carta robada del Main restante. El turno y la fase no cambian automáticamente.",
              );
            } else if (b.hasAttribute("data-play-explore-hand")) {
              calculate();
            } else if (b.hasAttribute("data-planner-cancel")) {
              invalidatePlan();
              draw();
            } else if (b.hasAttribute("data-play-quick-position")) {
              const selected = state.selection;
              commit(
                E.position(
                  state.scenario,
                  selected.uid,
                  b.dataset.playQuickPosition,
                ),
              );
              state.selection = selected;
              draw();
              node
                .querySelector(
                  `[data-play-quick-position="${b.dataset.playQuickPosition}"]`,
                )
                ?.focus({ preventScroll: true });
            } else if (b.hasAttribute("data-play-card")) {
              state.selection = {
                key: b.dataset.playCard,
                owner: b.dataset.playOwner,
                uid: b.dataset.playToken ? Number(b.dataset.playToken) : null,
              };
              draw();
            } else if (b.hasAttribute("data-play-page")) {
              state.page = Math.max(0, state.page + Number(b.dataset.playPage));
              draw();
            } else if (b.hasAttribute("data-play-pile")) {
              state.pile = {
                zone: b.dataset.playPile,
                side: b.dataset.playSide,
              };
              draw();
            } else if (b.hasAttribute("data-play-unknown-card")) {
              state.selection = {
                key: "unknown",
                owner: "opponent",
                uid: null,
              };
              draw();
            } else if (b.hasAttribute("data-play-place"))
              move(b.dataset.playPlace, b.dataset.playSide);
            else if (b.hasAttribute("data-play-move"))
              move(
                node.querySelector("[data-play-destination]").value,
                node.querySelector("[data-play-control]").value,
              );
            else if (b.hasAttribute("data-play-analyze")) {
              calculate();
            } else if (b.hasAttribute("data-play-undo")) {
              if (state.history.length) {
                state.scenario = state.history.pop();
                invalidatePlan();
                state.selection = null;
                draw();
              }
            } else if (b.hasAttribute("data-play-reset")) {
              if (
                confirm(
                  "¿Vaciar este tablero local? Las listas y resultados de la plataforma no se modifican.",
                )
              )
                commit(
                  E.create(
                    native,
                    state.scenario.practice ? null : state.scenario.opponent,
                  ),
                );
            }
          } catch (e) {
            notice(e.message, true);
          }
        },
        { signal: state.controller.signal },
      );
      node.addEventListener(
        "change",
        async (event) => {
          if (instances.get(node) !== state) return;
          const t = event.target;
          try {
            if (t.matches("[data-play-opponent]")) return choose(t.value);
            if (t.matches("[data-play-pool]")) {
              state.side = t.value;
              state.page = 0;
              draw();
              return;
            }
            if (t.matches("[data-play-section]")) {
              state.section = t.value;
              state.page = 0;
              draw();
              return;
            }
            if (
              t.matches(
                "[data-planner-flag], [data-planner-used], [data-planner-zones], [data-planner-goal]",
              )
            ) {
              if (t.matches("[data-planner-goal]"))
                state.planOptions.goal = t.value;
              else if (t.dataset.plannerFlag)
                state.planOptions[t.dataset.plannerFlag] = t.checked;
              else if (t.dataset.plannerUsed)
                state.planOptions.used = t.checked
                  ? [
                      ...new Set([
                        ...state.planOptions.used,
                        t.dataset.plannerUsed,
                      ]),
                    ]
                  : state.planOptions.used.filter(
                      (id) => id !== t.dataset.plannerUsed,
                    );
              else
                state.planOptions.blockedZones = t.value
                  .split(",")
                  .map((z) => z.trim())
                  .filter((z) => E.zones(state.scenario).includes(z));
              invalidatePlan();
              draw();
              return;
            }
            if (t.matches("[data-play-position]"))
              return commit(
                E.position(state.scenario, state.selection.uid, t.value),
              );
            if (t.matches("[data-play-setting]")) {
              const value =
                t.type === "checkbox"
                  ? t.checked
                  : t.dataset.playSetting === "turn"
                    ? Number(t.value)
                    : t.dataset.playSetting === "goesFirst"
                      ? t.value === "true"
                      : t.value;
              if (
                t.dataset.playSetting === "turn" &&
                value !== state.scenario.turn
              )
                state.planOptions.used = [];
              return commit(
                E.patch(state.scenario, { [t.dataset.playSetting]: value }),
              );
            }
            if (t.matches("[data-play-lp]"))
              return commit(
                E.patch(state.scenario, {
                  lp: { [t.dataset.playLp]: Number(t.value) },
                }),
              );
            if (t.matches("[data-play-unknown]"))
              return commit(
                E.patch(state.scenario, {
                  unknown: { [t.dataset.playUnknown]: Number(t.value) },
                }),
              );
            if (t.matches("[data-play-confirm]")) {
              state.scenario = E.confirm(
                state.scenario,
                t.dataset.playConfirm,
                state.routes,
              );
              draw();
              return;
            }
          } catch (e) {
            notice(e.message, true);
          }
        },
        { signal: state.controller.signal },
      );
      node.addEventListener(
        "input",
        (e) => {
          if (
            instances.get(node) !== state ||
            !e.target.matches("[data-play-search]")
          )
            return;
          state.query = e.target.value;
          state.page = 0;
          const pos = e.target.selectionStart;
          draw();
          const input = node.querySelector("[data-play-search]");
          input.focus({ preventScroll: true });
          input.setSelectionRange(pos, pos);
        },
        { signal: state.controller.signal },
      );
      node.addEventListener(
        "dragstart",
        (e) => {
          if (instances.get(node) !== state) return;
          const b = e.target.closest("[data-play-card]");
          if (!b) return;
          const selection = {
            key: b.dataset.playCard,
            owner: b.dataset.playOwner,
            uid: b.dataset.playToken ? Number(b.dataset.playToken) : null,
          };
          state.selection = selection;
          e.dataTransfer.setData(
            "application/x-stationx-play-card",
            JSON.stringify(selection),
          );
          e.dataTransfer.effectAllowed = "move";
        },
        { signal: state.controller.signal },
      );
      node.addEventListener(
        "dragover",
        (e) => {
          if (e.target.closest("[data-play-zone]")) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
          }
        },
        { signal: state.controller.signal },
      );
      node.addEventListener(
        "drop",
        (e) => {
          if (instances.get(node) !== state) return;
          const zone = e.target.closest("[data-play-zone]");
          if (!zone) return;
          e.preventDefault();
          try {
            const incoming = JSON.parse(
              e.dataTransfer.getData("application/x-stationx-play-card"),
            );
            if (
              !state.selection ||
              JSON.stringify(incoming) !== JSON.stringify(state.selection)
            )
              throw Error("Arrastra una carta de este escenario.");
            move(zone.dataset.playZone, zone.dataset.playSide);
          } catch (error) {
            notice(error.message, true);
          }
        },
        { signal: state.controller.signal },
      );
    }
    node.addEventListener(
      "error",
      (event) => {
        const img = event.target;
        if (img.matches?.(".play-original-image")) {
          const card = img.closest(".play-card");
          const key = card?.dataset.playCard;
          const failedUrl = img.getAttribute('src');
          if (!key || !failedUrl) return;
          state.failedImageUrls.add(key + '|' + failedUrl);
          // Re-render with the next verified source; after all sources fail,
          // cardMarkup displays its explicit unavailable-image fallback.
          queueMicrotask(() => {
            if (instances.get(node) === state) draw();
          });
        }
      },
      { capture: true, signal: state.controller.signal },
    );
    imageIndexPromise ||= fetch(
      "./assets/cards/originals/index.json?v=20261103-board-129",
    )
      .then((r) => {
        if (!r.ok) throw Error("Image index unavailable");
        return r.json();
      })
      .catch(() => {
        imageIndexPromise = null;
        return { cards: {} };
      });
    imageIndexPromise.then((index) => {
      if (instances.get(node) !== state) return;
      state.images = index.cards || {};
      if (state.scenario) draw();
    });
    // AbortController removes previous listeners when the selected deck changes.
    const savedRoutes = S.routes(native.id);
    if (
      savedRoutes?.compiler === 2 &&
      savedRoutes.binding === native.signature
    ) {
      state.routes = savedRoutes.routes;
      state.matchups = savedRoutes.matchups || [];
    }
    root.YgoPlayCardMenu?.create(node, {
      getScenario: () => state.scenario,
      getFacts: () => state.plannerFacts,
      onChange: commit,
      signal: state.controller.signal,
    });
    if (state.mode === "practice") choose("free");
    else if (opponent) choose(opponent.id);
    else draw();
    if (!state.scenario) prepareFacts();
  }
  root.YgoPlayCalculator = {
    unmount(node) {
      const state = instances.get(node);
      state?.planWorker?.terminate();
      state?.controller.abort();
      instances.delete(node);
      if (node) node.replaceChildren();
    },
    setMode(node, mode) {
      return instances.get(node)?.setMode?.(mode) ?? false;
    },
    practice(node, hand) {
      instances.get(node)?.practice?.(hand);
    },
    mount(node, deck, data, options) {
      try {
        mount(node, deck, data, options);
      } catch (error) {
        if (node) {
          node.textContent =
            "No se pudo preparar el calculador: " + error.message;
          node.setAttribute("role", "alert");
        }
      }
    },
  };
})(window);
