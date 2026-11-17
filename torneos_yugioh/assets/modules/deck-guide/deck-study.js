(function (root) {
  "use strict";
  const cache = new Map();
  let generalPromise;
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
  const norm = (v) =>
    String(v ?? "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  const label = (k) => root.YgoDeckDeliveries.label(k);
  const structured = (v) => root.YgoDeckDeliveries.structured(v);
  const paragraphs = (v) =>
    String(v ?? "")
      .split(/\n+/)
      .filter(Boolean)
      .map((x) => `<p>${esc(x)}</p>`)
      .join("");
  async function load(id) {
    id = Number(id);
    if (!cache.has(id))
      cache.set(
        id,
        fetch(
          `./assets/editorial/deck-library/deck-${id}.json?v=20261102-library-125`,
          { cache: "no-cache" },
        )
          .then((r) => {
            if (!r.ok)
              throw Error("No se pudo cargar la biblioteca de este deck.");
            return r.json();
          })
          .then((data) => {
            if (data.deck_id !== id)
              throw Error("La biblioteca no corresponde al deck seleccionado.");
            return {
              ...data,
              url: `./assets/editorial/deck-library/deck-${id}.json?v=20261102-library-125`,
            };
          })
          .catch((e) => {
            cache.delete(id);
            throw e;
          }),
      );
    return cache.get(id);
  }
  function general() {
    return (
      generalPromise ||
      (generalPromise = fetch(
        "./assets/editorial/deck-library/general.json?v=20261102-library-125",
        { cache: "no-cache" },
      )
        .then((r) => {
          if (!r.ok)
            throw Error("No se pudieron cargar los criterios generales.");
          return r.json();
        })
        .catch((e) => {
          generalPromise = null;
          throw e;
        }))
    );
  }
  function enrich(guide, supplement) {
    if (
      guide.deckId !== supplement.deck_id ||
      guide.source_sha256 !== supplement.source_sha256
    )
      throw Error(
        "La biblioteca y la entrega activa no tienen la misma versión.",
      );
    return {
      ...guide,
      cards: guide.cards.map((c) => {
        const detail =
          supplement.card_profiles.find(
            (p) => p.name === c.name && p.section === c.section,
          )?.profile || c.detail;
        const fields =
          detail.eighteen_obligatory_fields || detail.campos_18 || {};
        return {
          ...c,
          detail,
          role: c.role || fields.funcion_principal || "",
          official_source: c.official_source || detail.official_source,
          konami_cid: c.konami_cid || detail.official_card_id,
        };
      }),
    };
  }
  const groups = [
    ["Conoce la carta", [1, 2, 3, 4]],
    ["Construye tus jugadas", [5, 6, 7, 8, 11, 12, 13]],
    ["Evalúa sus ventajas y riesgos", [9, 10, 14, 18]],
    ["Cantidades y alternativas", [15, 16, 17]],
  ];
  const fieldNames = [
    "Nombre y características",
    "Efecto y condiciones de activación",
    "Función principal",
    "Funciones secundarias",
    "Sinergias",
    "Combos que permite",
    "Dependencias",
    "Restricciones",
    "Ventajas",
    "Debilidades",
    "Al jugar primero",
    "Al jugar segundo",
    "En turnos posteriores",
    "Riesgo de carta muerta",
    "Cantidad actual",
    "Cantidad recomendada",
    "Alternativas",
    "Importancia competitiva",
  ];
  function routeLink(id, guide) {
    const found = guide.combos.find((c) => c.id === id);
    return found
      ? `<button type="button" class="learn-link" data-learn-route="${esc(id)}">Estudiar ${esc(found.title)}</button>`
      : "";
  }
  function cardDetail(c, guide, supplement) {
    const d = c.detail || {},
      fields = Object.entries(
        d.eighteen_obligatory_fields || d.campos_18 || {},
      );
    const related =
      d.related_verified_route_ids || d.campos_18?.combos_que_permite || [];
    const check = supplement?.card_checks.find(
      (x) => x.card_name === c.name && norm(x.section) === norm(c.section),
    );
    const official = supplement?.official_card_texts[c.name];
    return `<div class="learn-card-study" data-card-fields="${fields.length}">${groups
      .map(
        ([title, indices]) =>
          `<section><h5>${title}</h5><dl class="learn-fact-grid">${indices
            .filter((i) => fields[i - 1])
            .map(
              (i) =>
                `<div data-card-field="${i}"><dt>${fieldNames[i - 1]}</dt><dd>${structured(fields[i - 1][1])}</dd></div>`,
            )
            .join("")}</dl></section>`,
      )
      .join(
        "",
      )}${Array.isArray(related) ? related.map((id) => routeLink(id, guide)).join("") : ""}
   ${official ? `<section><h5>Identidad y efecto consultados en la fuente</h5>${structured(official)}</section>` : ""}
   ${check ? `<details><summary>Qué se comprobó de esta carta y qué falta</summary>${structured(check)}</details>` : ""}
   <details><summary>Procedencia y registro original de la carta</summary>${structured(Object.fromEntries(Object.entries(d).filter(([k]) => !["eighteen_obligatory_fields", "campos_18"].includes(k))))}</details></div>`;
  }
  function routeDetail(c, supplement) {
    const d = c.detail || {},
      tactical = Object.entries(d).find(([k]) =>
        k.startsWith("tactical_full_prompt_detail"),
      )?.[1];
    const check = supplement?.route_checks.find((r) => r.route_id === c.id);
    const exclude = new Set([
      "title",
      "initial_state",
      "steps",
      "result",
      "failure_branch",
      "svg_inline",
      "diagram_svg",
      "mermaid_flowchart",
      "status",
    ]);
    if (tactical)
      exclude.add(
        Object.keys(d).find((k) => k.startsWith("tactical_full_prompt_detail")),
      );
    return `<div class="learn-route-study">${tactical ? `<section><h4>Recursos, costes y decisiones</h4>${structured(tactical)}</section>` : ""}
   ${d.fallback_no_skill ? `<section><h4>Cómo continuar sin la habilidad</h4>${structured(d.fallback_no_skill)}</section>` : ""}
   ${d.evidence_gaps ? `<section><h4>Condiciones aún pendientes de comprobar</h4>${structured(d.evidence_gaps)}</section>` : ""}
   ${check ? `<details><summary>Fuentes y comprobación de esta ruta</summary>${structured(check)}</details>` : ""}
   <details><summary>Restricciones, procedencia y detalles restantes</summary>${structured(Object.fromEntries(Object.entries(d).filter(([k]) => !exclude.has(k))))}</details>
   ${d.mermaid_flowchart ? `<details><summary>Código original del diagrama</summary><pre>${esc(d.mermaid_flowchart)}</pre></details>` : ""}</div>`;
  }
  function probability(value) {
    const metrics = Object.entries(value || {}).filter(
      ([k, v]) =>
        typeof v === "number" && v >= 0 && v <= 1 && /^(p_|at_least)/.test(k),
    );
    return `<div class="learn-probability-cards">${metrics.map(([k, v]) => `<section><span>${esc(label(k))}</span><strong>${(v * 100).toLocaleString("es", { maximumFractionDigits: 2 })} %</strong><meter min="0" max="1" value="${v}" aria-label="${esc(label(k))}"></meter></section>`).join("")}</div><details class="learn-technical-data" open><summary>Evento calculado, fórmula y supuestos completos</summary>${structured(value)}</details>`;
  }
  function handDetail(hand, guide) {
    const starters = hand.starter_guide?.visible_starters || [];
    return `<div class="learn-hand-study">${hand.turn_order ? `<p class="learn-kicker">${hand.turn_order === "first" ? "Ejemplo para jugar primero" : hand.turn_order === "second" ? "Ejemplo para jugar segundo" : esc(hand.turn_order)}</p>` : ""}
   ${starters.length ? `<h4>Entradas que reconoce el documento</h4>${starters.map((s) => `<section class="learn-study-block"><h5>${esc(s.route_id)}</h5>${paragraphs(s.opening_action_to_assess)}${paragraphs(s.route_initial_state)}${paragraphs(s.unverified_beyond_hand)}${routeLink(s.route_id, guide)}</section>`).join("")}` : ""}
   ${hand.skill_usable_at_this_turn ? `<section><h4>¿Puedes utilizar la habilidad?</h4>${structured(hand.skill_usable_at_this_turn)}${paragraphs(hand.skill_reason)}</section>` : ""}
   ${hand.planes || hand.sin_skill ? `<section><h4>Decisiones con estas cartas</h4>${structured(hand.planes || hand.sin_skill)}</section>` : ""}
   <details><summary>Condiciones y registro completo de esta mano</summary>${structured(hand)}</details></div>`;
  }
  function matchupDetail(row) {
    return `${row.status ? `<p class="learn-kicker">${esc(row.status)}</p>` : ""}${row.detail?.svg_inline ? `<details class="learn-route-diagram"><summary>Diagrama de este enfrentamiento</summary>${root.YgoDeckDeliveries.diagram(row.detail.svg_inline, row.name)}</details>` : ""}
   ${row.detail ? `<details class="learn-technical-data"><summary>Condiciones y fuente del plan ante este rival</summary>${structured(row.detail)}</details>` : ""}`;
  }
  function chapterLinks(topic, guide) {
    const id = String(topic.id),
      buttons = {
        5: ["cards", "Estudiar las cartas y sus 18 campos"],
        8: ["combos", "Recorrer las jugadas documentadas"],
        9: ["combos", "Ver pasos, costes y restricciones"],
        10: ["combos", "Abrir los diagramas de las rutas"],
        12: ["interrupt", "Preparar interrupciones"],
        13: ["interrupt", "Estudiar continuaciones"],
        14: ["turn", "Consultar el plan primero / segundo"],
        15: ["turn", "Consultar el plan primero / segundo"],
        24: ["matchups", "Preparar un rival"],
        27: ["training", "Consultar probabilidades y supuestos"],
        28: ["training", "Practicar con las manos documentadas"],
      };
    const target = buttons[id];
    return target
      ? `<div class="learn-actions"><button type="button" class="btn btn-info" data-learn-tab="${target[0]}">${target[1]}</button></div>`
      : "";
  }
  function inline(text) {
    return esc(text)
      .replace(
        /\[([^\]]+)\]\((https:\/\/[^\s)]+)\)/g,
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
      )
      .replace(/`([^`]+)`/g, "<code>$1</code>")
      .replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  }
  function markdown(text) {
    const lines = String(text).split(/\r?\n/);
    let result = "",
      table = [],
      code = null;
    const flush = () => {
      if (table.length) {
        result += `<div class="learn-table-scroll"><table class="learn-study-table"><tbody>${table
          .filter((l) => !/^\s*\|?\s*:?-{3}/.test(l))
          .map(
            (l) =>
              `<tr>${l
                .replace(/^\||\|$/g, "")
                .split("|")
                .map((c) => `<td>${inline(c.trim())}</td>`)
                .join("")}</tr>`,
          )
          .join("")}</tbody></table></div>`;
        table = [];
      }
    };
    for (const line of lines) {
      if (/^```/.test(line)) {
        flush();
        if (code === null) code = [];
        else {
          result += `<pre>${esc(code.join("\n"))}</pre>`;
          code = null;
        }
        continue;
      }
      if (code !== null) {
        code.push(line);
        continue;
      }
      if (line.includes("|") && /^\s*\|/.test(line)) {
        table.push(line);
        continue;
      }
      flush();
      const heading = line.match(/^(#{1,6})\s+(.+)$/);
      if (heading)
        result += `<h${Math.min(heading[1].length + 1, 6)}>${inline(heading[2])}</h${Math.min(heading[1].length + 1, 6)}>`;
      else if (/^\s*[-*]\s+/.test(line))
        result += `<p class="learn-original-bullet">• ${inline(line.replace(/^\s*[-*]\s+/, ""))}</p>`;
      else if (line.trim()) result += `<p>${inline(line)}</p>`;
    }
    flush();
    if (code !== null) result += `<pre>${esc(code.join("\n"))}</pre>`;
    return result;
  }
  function entries(guide, supplement) {
    return [
      ...supplement.documents.map((d) => ({
        ...d,
        id: "doc:" + d.id,
        group: "Documentos del deck",
      })),
      ...Object.entries(guide.original_record.deck).map(([key, value]) => ({
        id: "record:" + key,
        title: label(key),
        group: "Registro completo recibido",
        data: value,
      })),
      {
        id: "checks",
        title: "Qué se comprobó y qué está pendiente",
        group: "Fuentes y comprobaciones",
        data: supplement.verification,
      },
      {
        id: "card-checks",
        title: "Comprobaciones de las cartas",
        group: "Fuentes y comprobaciones",
        data: supplement.card_checks,
      },
      {
        id: "route-checks",
        title: "Comprobaciones de las jugadas",
        group: "Fuentes y comprobaciones",
        data: supplement.route_checks,
      },
      {
        id: "index",
        title: "Índice de cartas, rutas y enlaces oficiales",
        group: "Fuentes y comprobaciones",
        data: supplement.source_index,
      },
      {
        id: "official-texts",
        title: "Identidades y efectos de las fuentes consultadas",
        group: "Fuentes y comprobaciones",
        data: supplement.official_card_texts,
      },
      ...supplement.historical_records.map((r, i) => ({
        id: "previous:" + i,
        title: r.title,
        group: "Referencias previas",
        status: "historical",
        data: r.data,
      })),
    ];
  }
  function library(guide, supplement, query = "", selected = "") {
    const all = entries(guide, supplement),
      q = norm(query),
      filtered = all.filter((e) =>
        norm(
          e.title +
            " " +
            e.group +
            " " +
            (e.search_text || e.text || JSON.stringify(e.data || "")),
        ).includes(q),
      );
    const entry = filtered.find((e) => e.id === selected) || filtered[0];
    const body = !entry
      ? "<p>No hay contenido que coincida con la búsqueda.</p>"
      : entry.kind === "html"
        ? entry.html
        : entry.kind === "markdown"
          ? markdown(entry.text)
          : structured(entry.data);
    return `<div class="learn-section-heading"><div><h3>Biblioteca de ${esc(guide.name)}</h3><p>Documentos originales, información completa y fuentes del deck seleccionado.</p></div><label>Buscar en toda la biblioteca<input class="form-control" type="search" data-library-search value="${esc(query)}" placeholder="Carta, jugada, regla o frase del documento"></label></div>
   <div class="learn-coverage" aria-label="Contenido del deck">${[
     ["cards", "cartas"],
     ["routes", "rutas"],
     ["chapters", "temas"],
     ["hands", "manos"],
   ]
     .map(
       ([k, l]) =>
         `<span><strong>${supplement.coverage[k]}</strong> ${l}</span>`,
     )
     .join("")}</div>
   <p role="status">${filtered.length} apartados encontrados · texto completo, sin reemplazar las fuentes</p>
   <div class="learn-study-layout"><nav class="learn-study-index" aria-label="Documentos y registros del deck">${filtered.map((e) => `<button type="button" data-library-entry="${esc(e.id)}" class="${entry?.id === e.id ? "is-active" : ""}" aria-pressed="${entry?.id === e.id}">${esc(e.title)}<small>${esc(e.group)}</small></button>`).join("")}</nav>
   <article class="learn-study-content learn-original-document" data-library-content aria-live="polite">${entry ? `<header><span class="learn-kicker">${esc(entry.group)}</span><h3>${esc(entry.title)}</h3>${entry.source ? `<p>Fuente: ${esc(entry.source)}</p>` : ""}${entry.status === "historical" || entry.status === "reference" ? "<p>Referencia original: ante diferencias, consulta la entrega activa y sus condiciones. Las notas de importación describen el documento recibido.</p>" : ""}</header>` : ""}${body}</article></div>
   <div class="learn-actions"><a class="btn btn-outline-info" href="${esc(supplement.url)}" download>Descargar biblioteca completa de este deck</a><button type="button" data-learn-tab="general" class="btn btn-outline-info">Criterios generales de la entrega</button></div>`;
  }
  function generalView(data) {
    return `<section class="learn-panel"><h3>Criterios generales de la entrega</h3><p>Documentación transversal del paquete recibido. Estos datos no son el estado actual de publicación del sitio ni una guía de otro deck.</p><button type="button" class="btn btn-outline-info" data-learn-tab="delivery">Volver a la biblioteca del deck</button>
   ${data.documents.map((d) => `<details class="learn-technical-data"><summary>${esc(d.title)}</summary><article class="learn-original-document">${markdown(d.text)}</article></details>`).join("")}
   ${Object.entries(data)
     .filter(([k]) => !["title", "documents"].includes(k))
     .map(
       ([k, v]) =>
         `<details class="learn-technical-data"><summary>${esc(label(k))}</summary>${structured(v)}</details>`,
     )
     .join("")}</section>`;
  }
  root.YgoDeckStudy = {
    load,
    general,
    enrich,
    cardDetail,
    routeDetail,
    probability,
    handDetail,
    matchupDetail,
    chapterLinks,
    library,
    generalView,
    entries,
  };
})(window);
