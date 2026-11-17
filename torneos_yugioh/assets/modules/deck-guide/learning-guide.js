(function (root) {
  "use strict";
  const legacy = root.YgoDeckStrategy;
  if (!legacy) return;
  const original = legacy.render,
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
  const normalize = (v) =>
    String(v || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  const arr = (v) => {
    try {
      return Array.isArray(v) ? v : JSON.parse(v || "[]");
    } catch (_) {
      return [];
    }
  };
  const short = (v, n = 210) => {
    v = String(v || "")
      .replace(/\s+/g, " ")
      .trim();
    return v.length > n ? v.slice(0, n).replace(/\s+\S*$/, "") + "…" : v;
  };
  const paragraphs = (v) =>
    String(v || "")
      .split(/\n+/)
      .filter(Boolean)
      .map((p) => `<p>${esc(p)}</p>`)
      .join("");
  let dataPromise = null,
    state = null;
  const load = () =>
    dataPromise ||
    (dataPromise = fetch(
      "./assets/guides/learning-guides.json?v=20261030-guide-120",
      { cache: "no-cache" },
    )
      .then((r) => {
        if (!r.ok) throw Error("No se pudo cargar la guía.");
        return r.json();
      })
      .catch((e) => {
        dataPromise = null;
        throw e;
      }));
  const tabs = [
    ["intro", "Empieza aquí", "bi-compass"],
    ["cards", "Cartas y funciones", "bi-stack"],
    ["combos", "Combos paso a paso", "bi-signpost-split"],
    ["matchups", "Contra otros decks", "bi-shield-shaded"],
    ["interrupt", "Si te interrumpen", "bi-exclamation-diamond"],
    ["turn", "Primero o segundo", "bi-arrow-left-right"],
  ];
  function format(d) {
    return (d.format_codes || [])
      .map(
        (f) =>
          ({ tcg_advanced: "TCG", rush_duel: "RUSH DUEL", goat: "GOAT" })[f] ||
          f,
      )
      .join(" · ");
  }
  function cardInfo(id) {
    return state.guide?.replacement
      ? state.guide.card_metadata?.[String(id)]
      : state.data?.cards[String(id)];
  }
  function card(id, small = false) {
    const c = cardInfo(id);
    return c
      ? `<button type="button" class="learn-card ${small ? "is-small" : ""}" data-card-inspect="${Number(c.id)}" data-card-name="${esc(c.name)}" title="Ampliar ${esc(c.name)}"><img src="./?api=deck_builder&op=image&id=${Number(c.id)}" alt="${esc(c.name)}" loading="lazy" width="100" height="146"><span>${esc(c.name)}</span><i class="bi bi-zoom-in" aria-hidden="true"></i></button>`
      : "";
  }
  function cards(ids, small = false) {
    return `<div class="learn-cards">${[...new Set(ids || [])].map((id) => card(id, small)).join("")}</div>`;
  }
  function studyText(text, limit = 360) {
    text = String(text || "");
    return text.length > limit
      ? paragraphs(short(text, limit)) +
          `<details class="learn-more"><summary>Ver explicación completa</summary>${paragraphs(text)}</details>`
      : paragraphs(text);
  }
  function sourceTools() {
    return `<footer class="learn-source-tools"><span>¿Quieres revisar o completar la información?</span><button type="button" data-learn-document>Ver información completa</button><button type="button" data-learn-edit>Editar información</button></footer>`;
  }
  function overview() {
    const s = state,
      p = s.profile,
      g = s.guide;
    const core = (g?.cards || [])
      .filter((c) => c.section === "main")
      .slice(0, 4);
    const references = core.length ? core : (g?.cards || []).slice(0, 4);
    return `${skillView()}<div class="learn-intro-grid"><section class="learn-panel"><span class="learn-kicker">1 · Entiende tu objetivo</span><h3>¿Cómo gana ${esc(s.deck.name)}?</h3>${studyText(p.primary_game_plan || p.win_condition || "Todavía no hay un plan documentado.")}<div class="learn-actions"><button class="btn btn-info" type="button" data-learn-tab="combos"><i class="bi bi-play-circle"></i> Aprender un combo</button><button class="btn btn-outline-info" type="button" data-learn-tab="matchups">Preparar un rival</button></div></section><section class="learn-panel"><span class="learn-kicker">2 · Reconoce tus piezas</span><h3>Cartas para comenzar a estudiar</h3>${
      references.length
        ? cards(
            references.map((c) => c.id),
            true,
          )
        : `<p>${esc(p.resource_engine || "Aún faltan referencias de cartas para esta guía.")}</p>`
    }<button type="button" class="learn-link" data-learn-tab="cards">Ver cartas y funciones <i class="bi bi-arrow-right"></i></button></section></div><div class="learn-intro-grid"><section class="learn-panel"><h3>Lo que aprovechas</h3><ul>${
      arr(p.strengths_json)
        .map((v) => `<li>${esc(v)}</li>`)
        .join("") || "<li>Aún no hay fortalezas documentadas.</li>"
    }</ul></section><section class="learn-panel"><h3>Lo que debes cuidar</h3><ul>${
      arr(p.weaknesses_json)
        .map((v) => `<li>${esc(v)}</li>`)
        .join("") || "<li>Aún no hay riesgos documentados.</li>"
    }</ul></section></div>`;
  }
  function cardsView() {
    const rows = state.guide?.cards || [];
    return `<div class="learn-section-heading"><div><h3>Reconoce cada carta y su función</h3><p>Selecciona una imagen para verla en detalle. Estas son referencias de estudio; tu lista guardada se edita en Decklist.</p></div><label>Buscar carta<input class="form-control" type="search" data-learn-card-search placeholder="Nombre o función"></label></div><div class="learn-card-functions">${rows.map((c) => `<article class="learn-function" data-learn-card-row data-search="${esc(normalize(c.name + " " + c.role + " " + JSON.stringify(c.detail)))}">${card(c.id) || `<div class="learn-card-placeholder"><i class="bi bi-card-text"></i><span>Ficha documental<br>Sin imagen disponible</span></div>`}<div><span class="learn-kicker">${{ main: "Main Deck", extra: "Extra Deck", side: "Side Deck", reserve: "Reserva de cartas", reference: "Referencia del perfil" }[c.section] || "Referencia"}${c.quantity ? " · ×" + c.quantity : ""}</span><h4>${esc(c.name)}</h4>${c.detail ? `<details><summary>Cómo utilizar esta carta · ficha completa</summary>${state.library ? root.YgoDeckStudy.cardDetail(c, state.guide, state.library) : deliveryData(c.detail)}</details>` : ""}${c.official_source ? `<a href="${esc(c.official_source)}" target="_blank" rel="noopener noreferrer">Fuente oficial · CID ${esc(c.konami_cid)}</a>` : ""}${c.role ? paragraphs(c.role) : "<p>El perfil menciona esta carta. Aún falta documentar su función individual.</p>"}</div></article>`).join("") || '<div class="learn-empty"><h4>Faltan cartas identificadas para esta guía</h4><p>El plan del deck sigue disponible en «Empieza aquí» y «Primero o segundo».</p></div>'}</div><p class="learn-search-empty" hidden>No hay cartas con esa búsqueda.</p>`;
  }
  function verb(text) {
    const t = normalize(text);
    return /invoc|superpon|hacer link|^normal /.test(t)
      ? "Invoca"
      : /busca|anade|anadir/.test(t)
        ? "Busca"
        : /activa|efecto/.test(t)
          ? "Activa"
          : /si |comprobar|confirm/.test(t)
            ? "Comprueba"
            : "Prepara";
  }
  function comboView() {
    const combos = state.guide?.combos || [];
    if (!combos.length)
      return `<div class="learn-empty"><i class="bi bi-signpost-split"></i><h3>Este deck todavía no tiene combos paso a paso documentados</h3><p>La guía básica está disponible. Para mostrar rutas con cartas, materiales y resultado hacen falta secuencias específicas de esta lista.</p><button class="btn btn-outline-info" type="button" data-learn-tab="turn">Estudiar el plan primero / segundo</button></div>`;
    const c = combos[Math.min(state.combo, combos.length - 1)];
    state.combo = Math.min(state.combo, combos.length - 1);
    state.step = Math.min(state.step, c.steps.length - 1);
    const step = c.steps[state.step];
    return `<div class="learn-section-heading"><div><h3>Elige una jugada y recórrela</h3><p>Las rutas pertenecen a la construcción documentada. Comprueba las condiciones de cada línea.</p></div><label>Jugada<select class="form-select" data-learn-combo>${combos.map((x, i) => `<option value="${i}" ${i === state.combo ? "selected" : ""}>${esc(x.title)}</option>`).join("")}</select></label></div>${state.guide.replacement ? `<p class="learn-warning"><b>Estado de la ruta:</b> ${esc(c.status)}${c.executable === false ? " · NO EJECUTABLE CON ESTA CONSTRUCCIÓN" : ""}. Publicada como documentación; no equivale a certificación de ejecución.</p>` : ""}<div class="learn-combo-overview"><section><span class="learn-kicker">Estado inicial y requisitos</span>${c.conditions ? `<div class="learn-start-conditions">${paragraphs(c.conditions)}</div>` : "<p>Prepara los recursos indicados en cada paso.</p>"}${cards(c.startCards, true)}<small>Imágenes de referencia: consulta el texto para distinguir mano, Deck y Cementerio.</small></section><div class="learn-combo-arrow" aria-hidden="true">→</div><section><span class="learn-kicker">Resultado de la jugada</span>${cards(c.endCards, true)}${paragraphs(c.detail?.result || c.steps.at(-1).text)}</section></div><div class="learn-flow" aria-label="Secuencia del combo">${c.steps.map((x, i) => `<button type="button" data-learn-step="${i}" class="learn-flow-node ${i === state.step ? "is-current" : ""}" aria-current="${i === state.step ? "step" : "false"}"><span>Paso ${i + 1} · ${verb(x.text)}</span>${cardInfo(x.cards[0]) ? `<img src="./?api=deck_builder&op=image&id=${x.cards[0]}" alt="${esc(cardInfo(x.cards[0])?.name || "")}" loading="lazy" width="70" height="102">` : '<i class="bi bi-arrow-repeat" aria-hidden="true"></i>'}<small>${esc(short(x.text, 95))}</small><b aria-hidden="true">→</b></button>`).join("")}</div><section class="learn-step-detail" aria-live="polite"><div class="learn-step-art">${cards(step.cards)}</div><div><span class="learn-kicker">Paso ${state.step + 1} de ${c.steps.length}</span><h3>${verb(step.text)} y resuelve</h3>${paragraphs(step.text)}</div><div class="learn-step-controls"><button class="btn btn-outline-info" type="button" data-learn-step-prev ${state.step === 0 ? "disabled" : ""}>← Paso anterior</button><span>${state.step + 1} / ${c.steps.length}</span><button class="btn btn-info" type="button" data-learn-step-next ${state.step === c.steps.length - 1 ? "disabled" : ""}>Siguiente paso →</button></div></section>${c.diagram_svg ? `<details class="learn-route-diagram"><summary>Ver diagrama de la ruta</summary>${root.YgoDeckDeliveries.diagram(c.diagram_svg, c.title)}</details>` : ""}${c.detail ? `<details class="learn-technical-data"><summary>Condiciones, fuentes y registro completo de la ruta</summary>${routeDetails(c)}</details>` : ""}${c.warning ? `<section class="learn-warning"><h4>Si la jugada se corta</h4>${paragraphs(c.warning)}</section>` : ""}`;
  }
  function matchupView() {
    const rows = state.guide?.matchups || [];
    const rivals = (root.DML_DATA?.allDecks || []).filter(
      (d) =>
        Number(d.id) !== Number(state.deck.id) &&
        (d.format_codes || []).some((f) =>
          (state.deck.format_codes || []).includes(f),
        ),
    );
    const options = rows.length
      ? rows
      : rivals.map((d) => ({ name: d.name, deckId: d.id }));
    return `<div class="learn-section-heading"><div><h3>Prepara cómo jugar contra un rival</h3><p>Elige un deck del mismo formato. Verás el plan documentado para tu cruce, sin porcentajes de victoria supuestos.</p></div><label>Deck rival<select class="form-select" data-learn-rival><option value="">Selecciona un rival</option>${options.map((r, i) => `<option value="${i}" ${String(i) === state.rival ? "selected" : ""}>${esc(r.name)}</option>`).join("")}</select></label></div><div data-learn-matchup-detail>${matchupDetail(options)}</div>`;
  }
  function rivalOverview(r) {
    const profile = (root.DML_DATA?.deckAnalysisProfiles || []).find(
      (p) => Number(p.deck_id) === Number(r.deckId),
    );
    const refs = (state.data?.guides[String(r.deckId)]?.cards || []).slice(
      0,
      3,
    );
    return profile
      ? `<section class="learn-panel"><h4>Qué intenta hacer tu rival</h4>${studyText(profile.primary_game_plan || profile.resource_engine, 260)}${cards(
          refs.map((c) => c.id),
          true,
        )}</section>`
      : "";
  }
  function matchupDetail(options) {
    const r = options[Number(state.rival)];
    if (state.rival === "" || !r)
      return `<div class="learn-empty"><i class="bi bi-shield-shaded"></i><h4>¿Qué rival vas a enfrentar?</h4><p>Selecciona su nombre para preparar el enfrentamiento.</p></div>`;
    return `<section class="learn-matchup"><header><span class="learn-kicker">${esc(state.deck.name)} contra</span><h3>${esc(r.name)}</h3></header>${r.plan ? `<div class="learn-intro-grid">${state.guide.replacement ? "" : rivalOverview(r)}<section class="learn-panel"><h4>Tu plan para este cruce</h4>${paragraphs(r.plan)}<h4>Respuestas que menciona tu plan</h4>${(r.cards || []).length ? cards(r.cards) : "<p>Esta entrada se centra en decisiones y ventanas de juego; no especifica cartas de respuesta.</p>"}</section></div>${state.library ? root.YgoDeckStudy.matchupDetail(r) : ""}<div class="learn-actions"><button class="btn btn-outline-info" type="button" data-learn-tab="turn">Revisar mi plan primero / segundo</button><button class="btn btn-outline-info" type="button" data-learn-tab="interrupt">Preparar una interrupción</button></div>` : `${rivalOverview(r)}<div class="learn-empty"><h4>Aún no hay un plan específico para este cruce</h4><p>El perfil general del rival está disponible; no lo presentamos como una estrategia ya validada para tu deck.</p><button class="btn btn-outline-info" type="button" data-learn-open-rival="${Number(r.deckId)}">Estudiar ${esc(r.name)}</button></div>`}</section>`;
  }
  function interruptView() {
    const documented = state.guide?.interruptions || [];
    const rows = documented.length
      ? documented
      : arr(state.profile.weaknesses_json).map((text) => ({ text, cards: [] }));
    return `<div class="learn-section-heading"><div><h3>Reconoce dónde se puede cortar tu juego</h3><p>Estudia el recurso que pierdes y la continuación que permite tu mano.</p></div></div><div class="learn-interruptions">${rows.map((r, i) => `<article class="learn-interruption"><span class="learn-number">${i + 1}</span><div>${cards(r.cards, true)}${paragraphs(r.text)}</div></article>`).join("") || "<p>Todavía no hay interrupciones documentadas para este deck.</p>"}</div>`;
  }
  function turnView() {
    const p = state.profile;
    return `<div class="learn-intro-grid"><section class="learn-panel"><span class="learn-kicker">Vas primero</span><h3>Prepara interacción y seguimiento</h3>${studyText(p.going_first_plan || "Aún no hay un plan documentado.")}</section><section class="learn-panel"><span class="learn-kicker">Vas segundo</span><h3>Abre paso antes de comprometer recursos</h3>${studyText(p.going_second_plan || "Aún no hay un plan documentado.")}</section></div><div class="learn-intro-grid"><section class="learn-panel"><h3>Si la partida continúa</h3>${studyText([p.mid_game_plan, p.late_game_plan].filter(Boolean).join("\n") || "Aún falta documentar la recuperación.")}</section><section class="learn-panel"><h3>Ajustes entre duelos</h3>${studyText(p.side_deck_strategy || "No hay una selección de Side documentada para esta lista.")}</section></div>`;
  }
  const studyAreas = [
    [
      "plan",
      "Plan y recursos",
      "bi-diagram-3",
      "Objetivo, apertura, recursos y evolución de la partida.",
    ],
    [
      "build",
      "Construcción y Side",
      "bi-stack",
      "Listas documentadas, funciones, sinergias y cambios propuestos.",
    ],
    [
      "consistency",
      "Consistencia y evaluación",
      "bi-bar-chart",
      "Probabilidades, condiciones de cálculo y valoración editorial.",
    ],
    [
      "practice",
      "Práctica y reglas",
      "bi-controller",
      "Manos de prueba, decisiones, rulings y límites del análisis.",
    ],
  ];
  function areaOf(t) {
    if (t.area) return t.area;
    const n = normalize(t.label);
    if (
      t.group === "evaluation" ||
      /probabili|matemat|consisten|indicadores|calific|valoracion|puntuacion/.test(
        n,
      )
    )
      return "consistency";
    if (
      t.group === "plan" ||
      t.group === "risks" ||
      /plan de juego|condicion.*victoria|motor de recursos|pilotaje|identidad/.test(
        n,
      )
    )
      return "plan";
    if (
      /main deck|extra deck|side|reserva|slots|sinerg|constru|lista|funciones|optimiz|intercambio|carta.*muerta|conflicto/.test(
        n,
      )
    )
      return "build";
    return "practice";
  }
  function friendlyTitle(t) {
    let s = t.label.replace(/^[A-Z]\d+(?:\.\d+)*\.?\s*[—·:]?\s*/, "").trim();
    const n = normalize(s);
    if (/^anexo |^presentacion del anexo/.test(n))
      return "Qué incluye esta guía y para qué lista";
    if (n === "reglas de lectura")
      return "Cómo interpretar los planes contra rivales";
    if (n.startsWith("matematica exacta:"))
      return "Probabilidad de abrir con dos grupos de cartas";
    s = s
      .replace(/AUDITORÍA DE /i, "Revisión de ")
      .replace(/ \(DESGLOSE AUDITADO\)/i, "")
      .replace(
        /ESTIMACIONES MATEMÁTICAS CONTROLADAS/i,
        "Probabilidades de la mano inicial",
      );
    return (
      s.charAt(0).toLocaleUpperCase("es") + s.slice(1).toLocaleLowerCase("es")
    );
  }
  function topicText(t) {
    return [
      t.label,
      t.text,
      ...(t.items || []),
      ...(t.scores || []).map((s) => s.label + " " + s.value),
      JSON.stringify(t.data || ""),
    ].join("\n");
  }
  function richStudy(text) {
    const lines = String(text || "")
      .split(/\n+/)
      .map((s) => s.trim())
      .filter(Boolean);
    let html = "",
      table = [];
    const flush = () => {
      if (table.length) {
        html += `<div class="learn-table-scroll" tabindex="0" aria-label="Tabla del documento"><table class="learn-study-table"><tbody>${table
          .map(
            (row) =>
              `<tr>${row
                .split("|")
                .map((c) => `<td>${esc(c.trim())}</td>`)
                .join("")}</tr>`,
          )
          .join("")}</tbody></table></div>`;
        table = [];
      }
    };
    for (const line of lines) {
      if (line.includes("|")) {
        table.push(line);
        continue;
      }
      flush();
      const m = line.match(/^([^:]{3,85}):\s+([\s\S]+)$/);
      html += `<div class="learn-study-block">${m ? `<h4>${esc(m[1])}</h4><p>${esc(m[2])}</p>` : `<p>${esc(line)}</p>`}</div>`;
    }
    flush();
    return (
      html || "<p>Este apartado todavía no tiene contenido documentado.</p>"
    );
  }
  function topicDetail(t) {
    if (!t) return "<p>Selecciona un tema para estudiar su contenido.</p>";
    let body = "";
    if (t.scores) {
      body = `<p>Valoraciones editoriales de 1 a 5. No representan tasas de victoria ni resultados de partidas.</p><div class="learn-score-grid">${t.scores
        .map((s) => {
          const n = parseFloat(s.value),
            valid = Number.isFinite(n) && n >= 1 && n <= 5;
          return `<section><strong>${esc(s.label)}</strong><span>${esc(s.value)}</span>${valid ? `<meter min="0" max="5" value="${n}" aria-label="${esc(s.label)}"></meter>` : ""}</section>`;
        })
        .join("")}</div>`;
    } else if (t.items) {
      body = `<ul class="learn-study-list">${t.items.map((v) => `<li>${esc(v)}</li>`).join("") || "<li>Sin datos documentados.</li>"}</ul>`;
    } else body = richStudy(t.text);
    const refs = (state.guide?.cards || [])
      .filter((c) => normalize(topicText(t)).includes(normalize(c.name)))
      .map((c) => c.id)
      .slice(0, 6);
    return `<header><span class="learn-kicker">Contenido documentado · sin recortes</span><h3>${esc(friendlyTitle(t))}</h3><details class="learn-provenance"><summary>Referencia del contenido</summary><p>${esc(t.label)} · Revisión ${Number(state.profile.revision) || "no indicada"}</p></details></header>${refs.length ? cards(refs, true) : ""}${areaOf(t) === "consistency" && !t.scores ? '<p class="learn-warning">Lee el tamaño del Main, la mano inicial y los grupos de cartas usados en cada cálculo. Una probabilidad de apertura no es una probabilidad de ganar.</p>' : ""}${body}${state.library ? root.YgoDeckStudy.chapterLinks(t, state.guide) : ""}${t.data ? `<details class="learn-technical-data"><summary>Datos, condiciones y registros completos del apartado</summary>${deliveryData(t.data)}</details>` : ""}`;
  }
  function studyView() {
    const id = state.tab,
      area = studyAreas.find((a) => a[0] === id),
      all = state.topics || [],
      rows = all.filter((t) => areaOf(t) === id),
      query = normalize(state.studyQuery || ""),
      filtered = rows.filter((t) => normalize(topicText(t)).includes(query));
    const selected =
      filtered.find((t) => t.key === state.studyKey) || filtered[0];
    if (selected) state.studyKey = selected.key;
    return `<div class="learn-section-heading"><div><h3>${esc(area[1])}</h3><p>${esc(area[3])} Selecciona un tema para consultar todos sus detalles.</p></div><label>Buscar en esta área<input class="form-control" type="search" data-learn-study-search value="${esc(state.studyQuery || "")}" placeholder="Carta, regla, recurso o fórmula"></label></div><p role="status" data-study-count>${filtered.length} de ${rows.length} temas</p><div class="learn-study-layout"><nav class="learn-study-index" aria-label="Temas de ${esc(area[1])}">${filtered.map((t) => `<button type="button" data-study-topic="${esc(t.key)}" class="${selected?.key === t.key ? "is-active" : ""}" aria-pressed="${selected?.key === t.key}">${esc(friendlyTitle(t))}<small>${t.scores ? "Valoración editorial" : t.items ? "Ficha del perfil" : "Lectura técnica"}</small></button>`).join("") || "<p>No hay temas que coincidan con la búsqueda.</p>"}</nav><article class="learn-study-content" aria-live="polite">${topicDetail(selected)}</article></div>`;
  }
  function studyNavigation() {
    return `<section class="learn-depth-heading"><h3>Profundiza en tu deck</h3><p>El análisis completo, organizado para consultarlo mientras aprendes.</p></section><nav class="learn-depth-navigation" aria-label="Análisis y preparación">${studyAreas
      .map(([id, label, icon, hint]) => {
        const count = (state.topics || []).filter(
          (t) => areaOf(t) === id,
        ).length;
        return `<button type="button" data-learning-nav="${id}" title="${esc(hint)}"><i class="bi ${icon}" aria-hidden="true"></i><span>${label}<small>${count} temas</small></span></button>`;
      })
      .join("")}</nav>`;
  }

  function deliveryData(v) {
    return (
      root.YgoDeckDeliveries?.structured(v) || paragraphs(JSON.stringify(v))
    );
  }
  function routeDetails(c) {
    if (state.library) return root.YgoDeckStudy.routeDetail(c, state.library);
    const shown = new Set([
      "title",
      "initial_state",
      "steps",
      "result",
      "failure_branch",
      "svg_inline",
      "diagram_svg",
      "alt_text",
      "mermaid_flowchart",
      "status",
      "executable_with_current_build",
    ]);
    const remaining = Object.fromEntries(
      Object.entries(c.detail || {}).filter(([key]) => !shown.has(key)),
    );
    return (
      deliveryData(remaining) +
      (c.detail?.mermaid_flowchart
        ? `<details class="learn-provenance"><summary>Código original del diagrama</summary><pre>${esc(c.detail.mermaid_flowchart)}</pre></details>`
        : "")
    );
  }
  function fullDeliveryView() {
    return state.library
      ? root.YgoDeckStudy.library(
          state.guide,
          state.library,
          state.libraryQuery || "",
          state.libraryKey || "",
        )
      : `<section class="learn-panel learn-complete-record"><h3>Registro íntegro de ${esc(state.guide.name)}</h3>${deliveryData(state.guide.original_record)}</section>`;
  }
  function generalView() {
    if (state.general) return root.YgoDeckStudy.generalView(state.general);
    const current = state;
    if (!state.generalLoading) {
      state.generalLoading = true;
      root.YgoDeckStudy.general()
        .then((data) => {
          if (state !== current) return;
          state.general = data;
          if (state.tab === "general") draw();
        })
        .catch((e) => {
          if (state !== current) return;
          state.generalError = e.message;
          if (state.tab === "general") draw();
        });
    }
    return `<p role="status">${esc(state.generalError || "Cargando los criterios generales de la entrega…")}</p>`;
  }

  function skillView() {
    return (state.guide?.skills || [])
      .map(
        (s) =>
          `<section class="learn-skill-panel"><img src="${esc(s.image_url)}" alt="${esc(s.nombre)}" loading="lazy"><div><span class="learn-kicker">Habilidad externa · ${esc(s.skill_id)}</span><h3>${esc(s.nombre)}</h3><p>No forma parte del Main, de la mano inicial ni del conjunto de cartas que se roba.</p><p><b>Requisito:</b> ${esc(s.requisito_texto || "Consultar el registro")}</p><p><b>Efecto:</b> ${esc(s.efecto_texto || "Consultar el registro")}</p><details><summary>Condiciones, estado y fuentes de la habilidad</summary>${deliveryData(s)}</details></div></section>`,
      )
      .join("");
  }
  function trainingView() {
    const hands = state.guide?.hands || [];
    if (!hands.length)
      return '<div class="learn-empty"><h3>No hay manos de entrenamiento en esta entrega.</h3></div>';
    const hand = hands[state.hand || 0],
      names = hand.cards || hand.mano || [];
    const ids = names
      .map(
        (n) =>
          (state.guide.cards || []).find(
            (c) => normalize(c.name) === normalize(n),
          )?.id,
      )
      .filter(Boolean);
    return `<div class="learn-section-heading"><div><h3>Manos de ejemplo y probabilidades</h3><p>Son muestras documentadas, no partidas completas ni garantías de victoria.</p></div><label>Mano de estudio<select class="form-select" data-learn-hand>${hands.map((h, i) => `<option value="${i}" ${i === (state.hand || 0) ? "selected" : ""}>${esc(h.id || "Mano " + (i + 1))}</option>`).join("")}</select></label></div><section class="learn-panel"><span class="learn-kicker">${names.length} cartas de Main · Skill separada cuando corresponda</span>${cards(ids)}<ul>${names.map((n) => `<li>${esc(n)}</li>`).join("")}</ul><h4>Decisiones y límites documentados</h4>${state.library ? root.YgoDeckStudy.handDetail(hand, state.guide) : deliveryData(hand)}</section><section class="learn-panel"><h3>Probabilidades de esta construcción</h3><p>El cálculo usa el inventario de la entrega; no es una tasa de victoria.</p>${state.library ? root.YgoDeckStudy.probability(state.guide.probability) : deliveryData(state.guide.probability)}</section>`;
  }

  function draw() {
    const s = state;
    if (!s?.host.isConnected) return;
    s.host.querySelectorAll("[data-learning-nav]").forEach((b) => {
      b.classList.toggle("is-active", b.dataset.learningNav === s.tab);
      b.setAttribute("aria-selected", String(b.dataset.learningNav === s.tab));
    });
    s.pane.innerHTML = (
      {
        intro: overview,
        cards: cardsView,
        combos: comboView,
        matchups: matchupView,
        interrupt: interruptView,
        turn: turnView,
        training: trainingView,
        delivery: fullDeliveryView,
        general: generalView,
      }[s.tab] || studyView
    )();
  }
  function navigate(tab) {
    state.tab = tab;
    state.studyQuery = "";
    state.studyKey = "";
    state.host.classList.remove("learning-documents");
    state.el.hidden = false;
    draw();
    const body = state.host.closest(".modal-body");
    if (body) {
      if (studyAreas.some((a) => a[0] === tab))
        state.pane.scrollIntoView({ block: "start", behavior: "instant" });
      else body.scrollTop = 0;
    }
  }
  function documents(id) {
    state.host.classList.add("learning-documents");
    state.el.hidden = true;
    const target = state.host.querySelector(
      '.strategy-workspace [data-strategy-open="' + id + '"]',
    );
    target?.click();
    const reader = state.host.querySelector(".strategy-reader");
    if (reader && !reader.querySelector("[data-learn-return]")) {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "btn btn-info learn-return";
      b.dataset.learnReturn = "";
      b.textContent = "← Volver a la guía visual";
      b.onclick = () => {
        state.host.querySelector("[data-strategy-back]")?.click();
        navigate(state.tab);
      };
      reader.prepend(b);
    }
  }
  function bind(host) {
    host.addEventListener("click", (e) => {
      const b = e.target.closest(
        "[data-study-topic],[data-learn-tab],[data-learning-nav],[data-learn-step],[data-learn-step-prev],[data-learn-step-next],[data-learn-document],[data-learn-edit],[data-learn-open-rival],[data-learn-retry],[data-library-entry],[data-learn-route]",
      );
      if (!b || !state || state.host !== host) return;
      if (b.hasAttribute("data-library-entry")) {
        state.libraryKey = b.dataset.libraryEntry;
        draw();
        return;
      }
      if (b.hasAttribute("data-learn-route")) {
        const index = state.guide.combos.findIndex(
          (c) => c.id === b.dataset.learnRoute,
        );
        if (index >= 0) {
          state.combo = index;
          state.step = 0;
          navigate("combos");
        }
        return;
      }
      if (b.hasAttribute("data-learn-retry")) {
        const current = state;
        state.signature = null;
        legacy.render(current.deck, current.profile, {});
        return;
      }
      if (b.hasAttribute("data-study-topic")) {
        state.studyKey = b.dataset.studyTopic;
        draw();
        if (innerWidth < 901)
          state.pane
            .querySelector(".learn-study-content")
            ?.scrollIntoView({ block: "start", behavior: "instant" });
        return;
      }
      if (b.hasAttribute("data-learn-document"))
        return state.guide?.replacement
          ? navigate("delivery")
          : documents("source");
      if (b.hasAttribute("data-learn-edit")) return documents("edit");
      if (b.hasAttribute("data-learn-open-rival")) {
        const deck = (root.DML_DATA?.allDecks || []).find(
          (d) => Number(d.id) === Number(b.dataset.learnOpenRival),
        );
        const select = document.querySelector("[data-deck-select]");
        if (deck && select) {
          select.value = deck.name;
          select.dispatchEvent(new Event("change"));
        }
        return;
      }
      if (b.dataset.learnTab || b.dataset.learningNav)
        return navigate(b.dataset.learnTab || b.dataset.learningNav);
      if (b.hasAttribute("data-learn-step"))
        state.step = Number(b.dataset.learnStep);
      else state.step += b.hasAttribute("data-learn-step-next") ? 1 : -1;
      draw();
      state.pane
        .querySelector(".learn-flow-node.is-current")
        ?.scrollIntoView({ block: "nearest", inline: "nearest" });
    });
    host.addEventListener("change", (e) => {
      if (state?.host !== host) return;
      if (e.target.matches("[data-learn-hand]")) {
        state.hand = Number(e.target.value);
        draw();
      }
      if (e.target.matches("[data-learn-combo]")) {
        state.combo = Number(e.target.value);
        state.step = 0;
        draw();
      }
      if (e.target.matches("[data-learn-rival]")) {
        state.rival = e.target.value;
        draw();
      }
    });
    host.addEventListener("input", (e) => {
      if (state?.host !== host) return;
      if (e.target.matches("[data-library-search]")) {
        const pos = e.target.selectionStart;
        state.libraryQuery = e.target.value;
        state.libraryKey = "";
        draw();
        const input = host.querySelector("[data-library-search]");
        input.focus({ preventScroll: true });
        input.setSelectionRange(pos, pos);
        return;
      }
      if (e.target.matches("[data-learn-study-search]")) {
        const pos = e.target.selectionStart;
        state.studyQuery = e.target.value;
        draw();
        const input = host.querySelector("[data-learn-study-search]");
        input.focus({ preventScroll: true });
        input.setSelectionRange(pos, pos);
        return;
      }
      if (!e.target.matches("[data-learn-card-search]")) return;
      const q = normalize(e.target.value);
      let count = 0;
      host.querySelectorAll("[data-learn-card-row]").forEach((row) => {
        row.hidden = !row.dataset.search.includes(q);
        if (!row.hidden) count++;
      });
      host.querySelector(".learn-search-empty").hidden = !!count;
    });
  }
  legacy.render = function (deck, profile, observed) {
    const existingHost = document.querySelector(".deck-strategy-profile");
    if (root.YgoR10Guide?.isCurrent(existingHost, deck)) return;
    original(deck, profile, observed);
    const host = document.querySelector(".deck-strategy-profile");
    if (!host) return;
    const signature = JSON.stringify([
      deck.id,
      profile?.revision,
      profile?.updated_at,
    ]);
    if (
      state?.host === host &&
      state.signature === signature &&
      state.el.isConnected
    )
      return;
    host.querySelector(".learning-guide")?.remove();
    host.classList.add("learning-enabled");
    host.classList.remove("learning-documents");
    const el = document.createElement("section");
    el.className = "learning-guide";
    state = {
      host,
      el,
      signature,
      deck,
      profile: profile || {},
      tab: "intro",
      combo: 0,
      step: 0,
      rival: "",
      data: null,
      guide: null,
      library: null,
      libraryQuery: "",
      libraryKey: "",
    };
    el.innerHTML = `<header class="learn-hero"><div><span class="learn-kicker">Guía de juego · ${esc(format(deck))}</span><h2>Aprende a jugar ${esc(deck.name)}</h2><p>Conoce tus piezas, practica tus jugadas y prepara el próximo rival.</p></div><div class="learn-style"><span>Tu estilo de juego</span><strong>${esc(profile?.play_style || "Por documentar")}</strong></div></header><nav class="learn-navigation" role="tablist" aria-label="Temas de la guía">${tabs.map(([id, label, icon]) => `<button type="button" role="tab" data-learning-nav="${id}"><i class="bi ${icon}" aria-hidden="true"></i>${label}</button>`).join("")}</nav><div class="learn-pane" role="tabpanel" aria-label="Contenido de la guía"><p role="status">Preparando cartas y jugadas…</p></div>${sourceTools()}`;
    host.append(el);
    state.pane = el.querySelector(".learn-pane");
    if (!host.dataset.learningBound) {
      host.dataset.learningBound = "1";
      bind(host);
    }
    const current = state;
    Promise.all([
      load(),
      root.YgoDeckDeliveries ? root.YgoDeckDeliveries.load(deck.id) : null,
    ])
      .then(async ([data, replacement]) => {
        if (state !== current) return;
        if (replacement?.curated_r10) {
          state = null;
          root.YgoR10Guide.mount(host, deck, replacement);
          return;
        }
        const supplement = replacement
          ? await root.YgoDeckStudy.load(deck.id)
          : null;
        const topics = replacement
          ? replacement.topics
          : await legacy.studyTopics(deck.id);
        if (state !== current) return;
        state.data = { ...data, cards: { ...data.cards } };
        state.topics = topics;
        state.library = supplement;
        state.guide = (replacement
          ? root.YgoDeckStudy.enrich(replacement, supplement)
          : null) ||
          data.guides[String(deck.id)] || {
            cards: [],
            combos: [],
            matchups: [],
          };
        if (replacement) {
          state.profile = { ...(profile || {}), ...replacement.profile };
          state.topics = replacement.topics;
          Object.assign(state.data.cards, replacement.card_metadata);
          state.el.querySelector(".learn-style strong").textContent =
            state.profile.play_style;
          state.el.querySelector("h2").textContent =
            "Aprende a jugar " + replacement.name;
          state.el
            .querySelector(".learn-navigation")
            .insertAdjacentHTML(
              "beforeend",
              '<button type="button" role="tab" data-learning-nav="training"><i class="bi bi-journal-check"></i>Manos y probabilidades</button><button type="button" role="tab" data-learning-nav="delivery"><i class="bi bi-journal-text"></i>Biblioteca y fuentes</button>',
            );
          state.el
            .querySelector(".learn-source-tools")
            .insertAdjacentHTML(
              "beforeend",
              `<a href="${esc(supplement.url)}" download>Descargar biblioteca de este deck</a>`,
            );
        }
        state.pane.insertAdjacentHTML("beforebegin", studyNavigation());
        draw();
      })
      .catch((e) => {
        if (state !== current) return;
        state.pane.innerHTML = `<div role="alert" class="learn-empty"><p>${esc(e.message)}</p><button class="btn btn-info" type="button" data-learn-retry>Volver a cargar este deck</button></div>`;
      });
  };
  document
    .querySelector("[data-deck-category-modal]")
    ?.addEventListener("hidden.bs.modal", () => {
      if (state) {
        state.host.classList.remove("learning-documents");
        state.el.hidden = false;
      }
    });
})(window);
