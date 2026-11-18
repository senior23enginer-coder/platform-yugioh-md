(function (root) {
  "use strict";
  const M = root.YgoComboModel,
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
  const turnLabels = {
    any: "Cualquier orden",
    first: "Empezando primero",
    second: "Empezando segundo",
  };
  const labels = {
      opening: "Mano inicial",
      step: "Jugada",
      decision: "Decisión",
      result: "Resultado",
    },
    sections = {
      main: "Main Deck",
      extra: "Extra Deck",
      side: "Side Deck",
      reserve: "Reserva",
    };
  let imagesPromise;
  const images = () =>
    (imagesPromise ||= fetch(
      "./assets/cards/originals/identities.json?v=20261103-combos-130",
    )
      .then((r) => (r.ok ? r.json() : {}))
      .catch(() => ({})));
  async function api(deckId, body, token, history = "") {
    const response = await fetch(
      `./?api=combo_creator&deck_id=${Number(deckId)}${history ? "&history=" + encodeURIComponent(history) : ""}`,
      {
        method: body ? "POST" : "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: body
          ? { "Content-Type": "application/json", "X-CSRF-Token": token }
          : {},
        ...(body ? { body: JSON.stringify(body) } : {}),
      },
    );
    const result = await response.json();
    if (!response.ok || !result.ok)
      throw Error(result.message || "No se pudo consultar la plataforma.");
    return result;
  }
  function face(card, index, small = false) {
    if (!card)
      return '<span class="cc-missing">Carta no disponible en la lista actual</span>';
    const binding = index?.cards?.[`${card.deck_id}:${card.id}`];
    const matches =
      binding &&
      M.norm(binding.card_name) === M.norm(card.card_name) &&
      Number(binding.image_id || 0) === Number(card.ygoprodeck_id || 0);
    const url =
      (matches ? binding.file : "") ||
      (card.ygoprodeck_id
        ? `./?api=deck_builder&op=image&id=${Number(card.ygoprodeck_id)}`
        : "");
    return `<figure class="cc-face ${small ? "is-small" : ""}">${url ? `<img src="${esc(url)}" alt="${esc(card.card_name)}" loading="eager" draggable="false" width="82" height="120">` : '<span class="cc-no-image">Imagen no disponible</span>'}<figcaption>${esc(card.card_name)}</figcaption></figure>`;
  }
  const findCard = (cards, id) =>
    cards.find((c) => Number(c.id) === Number(id));
  const faces = (ids, cards, index, interactive = false) => {
    const html = ids.map((id) => face(findCard(cards, id), index)).join("");
    return interactive
      ? html.replaceAll(
          "<img ",
          '<img tabindex="0" role="button" title="Ampliar carta" ',
        )
      : html;
  };
  const detailLabels = {
    cost: "Coste",
    target: "Objetivo",
    origin: "Zona de origen",
    destination: "Zona de destino",
    timing: "Momento / cadena",
    restrictions: "Restricciones",
    remaining: "Recursos restantes",
    source: "Fuente",
  };
  const statusLabels = {
    draft: "Borrador",
    conditional: "Condicional",
    pending: "Pendiente de verificación",
    not_executable: "No ejecutable",
  };
  function zones(format) {
    const count = format === "rush" ? 3 : ["tcg", "goat"].includes(format) ? 5 : 0,
      list = {
        unknown: "Zona no documentada",
        hand: "Mano",
        gy: "Cementerio",
        banished: "Destierro",
        deck: "Main Deck",
        extra: "Extra Deck",
        side: "Side",
        reserve: "Reserva",
        field: "Campo",
      };
    for (let i = 1; i <= count; i++) {
      list["monster_" + i] = "Monstruo " + i;
      list["spell_" + i] = "Magia/Trampa " + i;
    }
    if (format === "tcg") {
      list.extra_1 = "Zona Extra 1";
      list.extra_2 = "Zona Extra 2";
    }
    return list;
  }
  function board(n, doc, cards, index) {
    if (!["opening", "result"].includes(n.kind)) return "";
    const places = n.placements || [],
      labels = zones(doc.board_format),
      groups = {};
    n.cards.forEach((id, i) => {
      const p = places.find((p) => p.card_index === i) || {
        zone: n.kind === "opening" ? "hand" : "unknown",
        position: "unknown",
      };
      (groups[p.zone] ||= []).push({ id, p });
    });
    const zone = (key) =>
      `<section class="cc-board-zone" data-cc-zone="${key}"><b>${esc(labels[key] || key)}</b>${(groups[key] || []).map(({ id, p }) => `<div class="cc-board-card is-${esc(p.position)}">${face(findCard(cards, id), index)}<small>${esc({ attack: "Ataque", defense: "Defensa", face_down: "Boca abajo · identidad documentada", unknown: "Posición no documentada" }[p.position] || "Posición no documentada")}${p.materials ? " · Materiales: " + esc(p.materials) : ""}</small></div>`).join("") || "<small>Estado no documentado</small>"}</section>`;
    const count = doc.board_format === "rush" ? 3 : ["tcg", "goat"].includes(doc.board_format) ? 5 : 0;
    return `<details class="cc-board-details" open><summary>${n.kind === "opening" ? "Tablero inicial" : "Tablero final"} · reconstrucción parcial</summary><p>Solo muestra ubicaciones indicadas por el autor. Una zona sin información no se considera vacía.</p><div class="cc-board" style="--cc-zone-count:${count}">${doc.board_format === "tcg" ? '<div class="cc-board-row">' + zone("extra_1") + zone("extra_2") + "</div>" : ""}<div class="cc-board-row">${Array.from({ length: count }, (_, i) => zone("monster_" + (i + 1))).join("")}</div><div class="cc-board-row">${Array.from({ length: count }, (_, i) => zone("spell_" + (i + 1))).join("")}</div><div class="cc-board-resources">${["field", "hand", "gy", "banished", "deck", "extra", "side", "reserve", "unknown"].map(zone).join("")}</div></div></details>`;
  }
  function technical(n) {
    const entries = Object.entries(n.details || {}).filter(([, v]) => v);
    return entries.length
      ? `<details><summary>Costes, zonas, restricciones y fuentes</summary><dl class="cc-technical">${entries.map(([k, v]) => `<dt>${esc(detailLabels[k] || k)}</dt><dd>${esc(v)}</dd>`).join("")}</dl></details>`
      : "";
  }
  function skillPanel(skill) {
    if (!skill) return "";
    const image =
      typeof skill.image === "string" &&
      /^\.\/assets\/editorial\/[A-Za-z0-9_./-]+\.(png|jpe?g|webp)$/i.test(
        skill.image,
      ) &&
      !skill.image.includes("..")
        ? `<img src="${esc(skill.image)}" alt="${esc(skill.name)}" class="cc-skill-photo">`
        : "";
    return `<aside class="cc-skill"><h4>Skill · ${esc(skill.name)}</h4>${image}<p>Fuera de la mano y del robo. Su activación depende del estado de juego.</p><b>Requisito</b><p>${esc(skill.requirements)}</p><b>Efecto documentado</b><p>${esc(skill.effect)}</p>${(skill.limits || []).map((v) => `<p>${esc(v)}</p>`).join("")}</aside>`;
  }
  function guide(doc, cards, index) {
    let nodes;
    try {
      nodes = M.ordered(doc);
    } catch (e) {
      return `<p>${esc(e.message)}</p>`;
    }
    if (!nodes.length)
      return '<div class="cc-empty">Añade cartas y conecta los pasos para ver tu guía.</div>';
    const roots = nodes.filter((n) => !doc.edges.some((e) => e.to === n.id));
    return `<article class="cc-guide" data-cc-guide><header><small>${doc.kind === "strategy" ? "Estrategia" : "Combo"} · Guía editable | ${esc(turnLabels[doc.turn_order] || turnLabels.any)}</small><h3>${esc(doc.title || "Tu nueva jugada")}</h3><p>${esc(doc.objective)}</p><p class="cc-document-status">${esc(statusLabels[doc.editorial_status] || statusLabels.draft)} · no certifica reglas ni ejecución</p>${doc.conditions ? (root.YgoStepLesson ? root.YgoStepLesson.conditions(doc.conditions,esc) : `<div class="cc-conditions"><b>Antes de empezar</b><p>${esc(doc.conditions)}</p></div>`) : ""}${skillPanel(doc.skill_snapshot)}</header><div class="cc-guide-navigation"><label>Inicio<select data-cc-guide-root>${roots.map((n) => `<option value="${esc(n.id)}">${esc(n.title)}</option>`).join("")}</select></label><button type="button" data-cc-guide-back disabled>Anterior</button><button type="button" data-cc-guide-all>Ver todos los pasos</button><span data-cc-guide-progress role="status"></span></div>${nodes
      .map((n, i) => {
        const incoming = doc.edges.filter((e) => e.to === n.id);
        const outgoing = doc.edges.filter((e) => e.from === n.id);
        return `<section class="cc-guide-step is-${n.kind}" data-cc-step="${esc(n.id)}" ${n.id !== roots[0]?.id ? "hidden" : ""}><div class="cc-step-number" aria-label="${esc(labels[n.kind])}">${n.kind === "opening" ? String.fromCharCode(9675) : n.kind === "result" ? String.fromCharCode(10003) : n.kind === "decision" ? "?" : n.title.match(/^Paso\s+(\d+)/i)?.[1] || i + 1}</div><div><small>${esc(labels[n.kind])}${n.action ? " · " + esc(n.action) : ""}</small><h4>${esc(n.title)}</h4>${incoming.length ? `<p class="cc-branch">Desde ${incoming.map((e) => esc(doc.nodes.find((p) => p.id === e.from)?.title) + (e.label ? " · " + esc(e.label) : "")).join(" / ")}</p>` : ""}<div class="cc-faces">${faces(n.cards, cards, index, true)}</div>${root.YgoStepLesson ? root.YgoStepLesson.render(root.YgoStepLesson.fromNode(n,!!doc.source_route_id),esc) : `<p class="cc-prose">${esc(n.explanation)}</p>`}${technical(n)}${board(n, doc, cards, index)}${!incoming.length && n.kind !== "opening" && nodes.length > 1 ? "<small>Paso independiente: conecta su origen si forma parte de una secuencia.</small>" : ""}<div class="cc-next-options">${outgoing.length > 1 ? "<p>Elige una continuación. Las alternativas no se ejecutan una detrás de otra; comprueba sus condiciones.</p>" : ""}${outgoing.map((e) => `<button type="button" data-cc-guide-follow="${esc(e.to)}">${outgoing.length === 1 ? "Siguiente: " : ""}${esc(e.label || doc.nodes.find((p) => p.id === e.to)?.title || "Continuar")}</button>`).join("") || "<p>Fin de esta línea documentada. No se han definido otras continuaciones.</p>"}</div></div></section>`;
      })
      .join("")}</article>`;
  }
  function matrix(records, cards, index) {
    if (!records.length)
      return '<div class="cc-empty">Los combos guardados de este deck aparecerán aquí para compararlos.</div>';
    const links = (doc, id) =>
      doc.edges
        .filter((e) => e.to === id)
        .map(
          (e) =>
            `${esc(doc.nodes.find((n) => n.id === e.from)?.title)}${e.label ? ": " + esc(e.label) : ""}`,
        )
        .join(" / ");
    return `<div class="cc-matrix-scroll"><table class="cc-matrix"><thead><tr><th>Jugada y mano inicial</th><th>Secuencia y decisiones</th><th>Resultado y condiciones</th></tr></thead><tbody>${records
      .map((r) => {
        const d = r.document,
          recordCards = r.cards_snapshot || cards,
          nodes = M.ordered(d),
          starts = nodes.filter((n) => n.kind === "opening"),
          ends = nodes.filter((n) => n.kind === "result");
        return `<tr><td><h4>${esc(d.title || "Jugada en edición")}</h4><small>${esc(turnLabels[d.turn_order] || turnLabels.any)}</small>${r.stale ? '<p class="cc-warning">La lista cambió: revisar cartas.</p>' : ""}<p>${esc(d.objective)}</p>${starts.map((n) => `<div class="cc-faces">${faces(n.cards, recordCards, index)}</div><p>${esc(n.explanation)}</p>`).join("") || "<p>Mano inicial por describir.</p>"}</td><td>${d.edges.some((e) => d.edges.filter((p) => p.from === e.from).length > 1) ? '<p class="cc-warning">Opciones alternativas: no ejecutar todos estos pasos como una sola secuencia.</p>' : ""}${nodes
          .filter((n) => !["opening", "result"].includes(n.kind))
          .map(
            (n, i) =>
              `<div class="cc-matrix-step"><b>${i + 1}. ${esc(n.title)}</b><small>${esc(n.action)}</small><p class="cc-branch">${links(d, n.id)}</p><div class="cc-faces">${faces(n.cards, recordCards, index)}</div><p>${esc(n.explanation)}</p></div>`,
          )
          .join(
            "",
          )}</td><td>${ends.map((n) => `<div class="cc-faces">${faces(n.cards, recordCards, index)}</div><b>${esc(n.title)}</b><p class="cc-branch">${links(d, n.id)}</p><p>${esc(n.explanation)}</p>`).join("") || "<p>Resultado por describir.</p>"}<p class="cc-prose">${esc(d.conditions)}</p></td></tr>`;
      })
      .join("")}</tbody></table></div>`;
  }
  function editor(node, data) {
    if (!node || node.dataset.ready) return;
    node.dataset.ready = "1";
    node.className = "combo-maker";
    const decks = [...(data.allDecks || [])].sort((a,b)=>a.name.localeCompare(b.name,'es'));
    const formatLabels={tcg_advanced:'TCG Advanced',tcg_traditional:'TCG Traditional',goat:'GOAT',rush_duel:'Rush Duel'};
    const deckHasCards=id=>(data.cards||[]).some(c=>Number(c.deck_id)===Number(id)&&Number(c.quantity)>0);
    const formatCodes=[...new Set(decks.flatMap(d=>d.format_codes||[]))].sort();
    const state = {
      deckId: 0,
      doc: null,
      revision: 0,
      inventory: "",
      cards: [],
      records: [],
      templateRoutes: {},
      index: {},
      token: "",
      selected: "",
      palette: null,
      from: "",
      edge: null,
      undo: [],
      dirty: false,
      zoom: 0.8,
      view: "canvas",
      libraryDeck: 0,
      libraryTicket: 0,
      page: 0,
      query: "",
      section: "",
      loading: 0,
      saving: false,
    };
    let activeRoute=(root.location.hash||'#dashboard').slice(1),returnRoute='dashboard';
    root.addEventListener('dml:routechange',event=>{
      const target=event.detail?.target;if(!target)return;
      if(target==='creador_combos'&&activeRoute!=='creador_combos')returnRoute=activeRoute;
      activeRoute=target;
    });
    node.innerHTML = `<header class="cc-heading"><div><button type="button" class="btn btn-outline-info cc-back" data-cc-back aria-label="Volver a la pantalla anterior">← Volver</button><span class="cc-kicker">Herramientas · Tu laboratorio de jugadas</span><h1>Creador de combos y estrategias</h1><p>Construye tus líneas con las cartas de tu deck y explica cada decisión.</p></div><span class="cc-save-state" data-cc-status role="status">Elige un deck para empezar</span></header><div class="cc-deck-filters"><label>Formato<select data-cc-format><option value="">Todos los formatos</option>${formatCodes.map(f=>`<option value="${esc(f)}">${esc(formatLabels[f]||f)}</option>`).join('')}</select></label><label>Buscar deck o equipo<input type="search" data-cc-deck-search placeholder="Nombre del deck o equipo"></label><p role="status" data-cc-deck-count></p></div><div class="cc-start"><label>Deck<select data-cc-deck><option value="">Selecciona un deck</option></select></label><label>Combos y estrategias del deck<select data-cc-saved disabled><option value="">Nueva creación</option></select></label><button type="button" class="btn btn-outline-info" data-cc-new disabled>+ Nueva creación</button></div><p class="cc-inventory-note" data-cc-inventory-note hidden></p><div data-cc-work hidden><section class="cc-meta"><label>Título<input maxlength="160" data-cc-meta="title" placeholder="Ej.: Unicorn hacia tu campo final"></label><label>Tipo<select data-cc-meta="kind"><option value="combo">Combo</option><option value="strategy">Estrategia</option></select></label><label>Cuándo jugarlo<select data-cc-meta="turn_order"><option value="any">Cualquier orden</option><option value="first">Empezando primero</option><option value="second">Empezando segundo</option></select></label><details class="cc-details"><summary>Objetivo, requisitos y l\u00edmites</summary><div><label class="cc-wide">Objetivo<textarea rows="2" maxlength="4000" data-cc-meta="objective" placeholder="Qué consigues con esta línea y cuándo conviene usarla"></textarea></label><label class="cc-wide">Requisitos y límites<textarea rows="2" maxlength="6000" data-cc-meta="conditions" placeholder="Estado inicial, costes, restricciones, turno y respuestas que debes considerar"></textarea></label></div></details></section><div class="cc-toolbar"><div><button type="button" class="btn btn-outline-info" data-cc-add="opening">+ Mano inicial</button><button type="button" class="btn btn-outline-info" data-cc-add="step">+ Paso</button><button type="button" class="btn btn-outline-info" data-cc-add="decision">+ Decisión</button><button type="button" class="btn btn-outline-info" data-cc-add="result">+ Resultado</button></div><div><button type="button" class="btn btn-outline-info" data-cc-refresh>Actualizar cartas</button><button type="button" class="btn btn-outline-light" data-cc-undo>Deshacer</button><button type="button" class="btn btn-primary" data-cc-save>Guardar en el deck</button></div></div><div class="cc-tabs" role="group" aria-label="Vista de la creación"><button type="button" data-cc-view="canvas">Editor visual</button><button type="button" data-cc-view="guide">Guía paso a paso</button><button type="button" data-cc-view="matrix">Matriz de jugadas</button><button type="button" data-cc-view="library">Guías visuales documentadas</button></div><div class="cc-editor" data-cc-canvas-view><aside class="cc-palette"><h3>Cartas de tu deck</h3><p>Arrastra al lienzo o sobre un paso para agrupar cartas.</p><label>Buscar carta<input type="search" data-cc-search placeholder="Nombre de la carta"></label><label>Sección<select data-cc-section><option value="">Todas las secciones</option>${Object.entries(
      sections,
    )
      .map(([k, v]) => `<option value="${k}">${v}</option>`)
      .join(
        "",
      )}</select></label><div data-cc-palette></div><div class="cc-page"><button type="button" data-cc-page="-1">Anterior</button><span data-cc-count></span><button type="button" data-cc-page="1">Siguiente</button></div><button type="button" class="btn btn-outline-info" data-cc-insert-card>Añadir carta como paso</button><p>También puedes seleccionar una carta y pulsar «Añadir al paso» en el panel derecho.</p></aside><div class="cc-stage-panel"><div class="cc-canvas-bar"><span data-cc-hint>Arrastra cartas para crear pasos. Usa «Conectar» para enlazarlos.</span><label>Zoom<select data-cc-zoom><option value="0.5">50%</option><option value="0.65">65%</option><option value="0.8" selected>80%</option><option value="1">100%</option></select></label></div><div class="cc-viewport" tabindex="0" aria-label="Lienzo del combo"><div class="cc-stage-space"><div class="cc-stage" data-cc-stage><svg class="cc-wires" aria-label="Conexiones entre pasos"></svg><div data-cc-nodes></div></div></div></div><p class="cc-help">Mueve los pasos por su cabecera. Cada flecha puede llevar una condición. Las conexiones organizan tu explicación.</p></div><aside class="cc-inspector" data-cc-inspector></aside></div><div class="cc-preview" data-cc-preview hidden></div><div class="cc-preview" data-cc-visual-library hidden></div></div>`;
    const $ = (s) => node.querySelector(s),
      $$ = (s) => [...node.querySelectorAll(s)];
    $(".cc-meta").insertAdjacentHTML(
      "beforeend",
      `<label>Estado documental<select data-cc-meta="editorial_status">${Object.entries(
        statusLabels,
      )
        .map(([k, v]) => `<option value="${k}">${v}</option>`)
        .join(
          "",
        )}</select></label><label>Tablero del formato<select data-cc-meta="board_format"><option value="unspecified">Por especificar</option><option value="tcg">TCG · 5 zonas</option><option value="rush">Rush · 3 zonas</option><option value="goat">GOAT · 5 zonas sin zonas Extra</option></select></label><label data-cc-skill-field hidden>Skill documentada<select data-cc-meta="skill_id"><option value="">Sin Skill seleccionada</option></select></label>`,
    );
    $(".cc-toolbar > div:last-child").insertAdjacentHTML(
      "beforeend",
      '<button type="button" class="btn btn-outline-info" data-cc-history>Historial</button><button type="button" class="btn btn-outline-info" data-cc-duplicate>Duplicar creación</button>',
    );
    $("[data-cc-work]").insertAdjacentHTML(
      "beforeend",
      "<section data-cc-history-panel hidden></section>",
    );
    function deckOptions(){
      const query=String($('[data-cc-deck-search]').value||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase().trim().split(/\s+/).filter(Boolean),format=$('[data-cc-format]').value;
      const visible=decks.filter(d=>(!format||(d.format_codes||[]).includes(format))&&query.every(word=>String(d.name+' '+(d.team||'')).normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase().includes(word)));
      const current=decks.find(d=>Number(d.id)===state.deckId),outside=current&&!visible.includes(current);
      const option=d=>`<option value="${Number(d.id)}">${esc(d.name)} - ${esc((d.format_codes||[]).map(f=>formatLabels[f]||f).join(' / ')||'Sin formato')}${deckHasCards(d.id)?'':' - sin cartas cargadas'}</option>`;
      $('[data-cc-deck]').innerHTML='<option value="">Selecciona un deck</option>'+(outside?`<optgroup label="Deck abierto (fuera del filtro)">${option(current)}</optgroup>`:'')+visible.map(option).join('');
      $('[data-cc-deck]').value=state.deckId?String(state.deckId):'';
      $('[data-cc-deck-count]').textContent=`${visible.length} de ${decks.length} decks${outside?' - el deck abierto se conserva fuera del filtro.':''}`;
    }
    $('[data-cc-format]').addEventListener('change',deckOptions);
    $('[data-cc-deck-search]').addEventListener('input',deckOptions);
    deckOptions();
    function status(text, error = false) {
      $("[data-cc-status]").textContent = text;
      $("[data-cc-status]").classList.toggle("is-error", error);
    }
    $('[data-cc-back]').addEventListener('click',()=>{
      if(state.dirty&&!root.confirm('Hay cambios sin guardar. ¿Quieres volver a la pantalla anterior?'))return;
      const target=returnRoute&&returnRoute!=='creador_combos'?returnRoute:'dashboard';
      const button=[...document.querySelectorAll('[data-route]')].find(b=>b.dataset.route===target);
      if(button)button.click();else root.location.hash=target;
    });
    function changed() {
      state.dirty = true;
      status("Cambios sin guardar");
    }
    function commit(next) {
      state.undo.push(M.clone(state.doc));
      if (state.undo.length > 40) state.undo.shift();
      state.doc = next;
      changed();
      draw();
    }
    function attempt(fn) {
      try {
        fn();
      } catch (e) {
        status(e.message, true);
      }
    }
    function savedOptions() {
      const select = $("[data-cc-saved]");
      select.innerHTML =
        '<option value="">Nueva creación</option>' +
        state.records.filter(r=>!r.imported)
          .map(
            (r) =>
              `<option value="${esc(r.id)}">${esc(r.title)}${r.stale ? " · revisar lista" : ""}</option>`,
          )
          .join("") + (state.records.some(r=>r.imported)?'<optgroup label="Documentados · editar y guardar">'+state.records.filter(r=>r.imported).map(r=>`<option value="${esc(r.id)}">${esc(r.title)}${r.document.editorial_status==='not_executable'?' · comprobar formato':''}</option>`).join('')+'</optgroup>':'');
      select.value = state.records.some((r) => r.id === state.doc?.id)
        ? state.doc.id
        : "";
    }
    function loadDocument(record) {
      state.doc = record ? M.clone(record.document) : M.create(state.deckId);
      state.doc.editorial_status ||= "draft";
      state.doc.board_format ||= state.format || "unspecified";
      state.revision = record?.revision || 0;
      state.selected = "";
      state.from = "";
      state.edge = null;
      state.undo = [];
      state.dirty = false;
      for (const input of $$("[data-cc-meta]"))
        input.value = state.doc[input.dataset.ccMeta] || "";
      $("[data-cc-history-panel]").hidden = true;
      savedOptions();
      draw();
      status(
        record?.stale
          ? "La lista cambió: revisa las cartas antes de guardar."
          : record?.imported
            ? "Combo documental cargado en el editor. Puedes modificarlo y guardar tu versión en el deck."
          : record
            ? "Guardado en este deck · revisión " + record.revision
            : "Nueva creación",
      );
    }
    async function selectDeck(id) {
      const ticket = ++state.loading;
      state.deckId = Number(id);
      state.libraryTicket++;
      state.libraryDeck = 0;
      state.view = "canvas";
      $("[data-cc-visual-library]").replaceChildren();
      deckOptions();
      $("[data-cc-inventory-note]").hidden=true;
      state.doc = null;
      state.cards = [];
      state.records = [];
      state.templateRoutes = {};
      state.palette = null;
      state.selected = "";
      state.from = "";
      state.edge = null;
      $("[data-cc-work]").hidden = true;
      $("[data-cc-saved]").disabled = true;
      $("[data-cc-new]").disabled = true;
      if (!id) return;
      status("Cargando las cartas y las creaciones del deck…");
      try {
        const result = await api(id);
        if (ticket !== state.loading) return;
        let imported={records:[],issues:[]};
        try{if(root.YgoDocumentedCombos)imported=await root.YgoDocumentedCombos.load(Number(id),result.cards,result.skills||[]);}catch(error){imported.issues.push({route:'Biblioteca documentada',reason:error.message});}
        if (ticket !== state.loading) return;
        state.cards = result.cards;
        const inventoryNote=$('[data-cc-inventory-note]');inventoryNote.hidden=state.cards.length>0;
        if(!state.cards.length)inventoryNote.innerHTML='Este deck no tiene cartas cargadas. Puedes preparar los pasos de tu estrategia; para usar cartas, carga primero su lista en <a href="?deck_id='+state.deckId+'#deck_ficha">la ficha del deck</a>.';
        state.records = [...result.records,...imported.records.filter(r=>!result.records.some(saved=>saved.id===r.id))];
        state.templateRoutes = Object.fromEntries(imported.records.map(r=>[r.source_route_id,r.id]));
        state.inventory = result.inventory_sha256;
        state.token = result.token;
        state.format = result.format;
        state.skills = result.skills || [];
        $("[data-cc-skill-field]").hidden = !state.skills.length;
        $('[data-cc-meta="skill_id"]').innerHTML =
          '<option value="">Sin Skill seleccionada</option>' +
          state.skills
            .map((s) => `<option value="${esc(s.id)}">${esc(s.name)}</option>`)
            .join("");
        state.query = "";
        state.page = 0;
        $("[data-cc-search]").value = "";
        state.section = "";
        $("[data-cc-section]").value = "";
        $("[data-cc-work]").hidden = false;
        $("[data-cc-saved]").disabled = false;
        $("[data-cc-new]").disabled = false;
        loadDocument(imported.records.length?state.records.find(r=>r.id===imported.records[0].id):null);
        node.querySelector('[data-cc-import-report]')?.remove();
        if(imported.issues.length)$(".cc-start").insertAdjacentHTML('afterend',`<details class="cc-warning" data-cc-import-report><summary>${imported.issues.length} guías requieren revisión para abrirse en el editor</summary>${imported.issues.map(i=>`<p><b>${esc(i.route)}</b>: ${esc(i.reason)}</p>`).join('')}</details>`);
      } catch (e) {
        if (ticket === state.loading) status(e.message, true);
      }
    }
    function palette() {
      const rows = state.cards.filter(
        (c) =>
          Number(c.quantity) > 0 &&
          (!state.section || c.section === state.section) &&
          M.norm(c.card_name).includes(M.norm(state.query)),
      );
      const pages = Math.max(1, Math.ceil(rows.length / 12));
      state.page = Math.max(0, Math.min(state.page, pages - 1));
      $("[data-cc-palette]").innerHTML =
        '<div class="cc-card-grid">' +
        rows
          .slice(state.page * 12, state.page * 12 + 12)
          .map(
            (c) =>
              `<button type="button" draggable="true" data-cc-card="${c.id}" aria-pressed="${state.palette === Number(c.id)}" title="${esc(c.card_name)}">${face(c, state.index)}<small>${esc(sections[c.section] || c.section)} · ×${Number(c.quantity)}</small></button>`,
          )
          .join("") +
        "</div>" +
        (rows.length ? "" : "<p>No hay cartas con ese nombre.</p>");
      $("[data-cc-count]").textContent =
        `${rows.length} cartas · ${state.page + 1}/${pages}`;
      $('[data-cc-page="-1"]').disabled = state.page === 0;
      $('[data-cc-page="1"]').disabled = state.page >= pages - 1;
      $("[data-cc-insert-card]").disabled = !state.palette;
      $("[data-cc-insert-card]").disabled = !state.cards.some(
        (c) => Number(c.id) === state.palette && Number(c.quantity) > 0,
      );
    }
    function wires() {
      if (!state.doc) return;
      const stage = $("[data-cc-stage]"),
        svg = $(".cc-wires");
      svg.innerHTML =
        '<defs><marker id="cc-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor"/></marker></defs>' +
        state.doc.edges
          .map((e, i) => {
            const a = state.doc.nodes.find((n) => n.id === e.from),
              b = state.doc.nodes.find((n) => n.id === e.to),
              el = stage.querySelector(`[data-cc-node="${a.id}"]`),
              x1 = a.x + 120,
              y1 = a.y + (el?.offsetHeight || 240),
              x2 = b.x + 120,
              y2 = b.y,
              mid = (y1 + y2) / 2;
            return `<g data-cc-edge="${i}" tabindex="0" role="button" aria-label="Editar conexión ${esc(a.title)} a ${esc(b.title)}"><path d="M${x1},${y1} C${x1},${mid} ${x2},${mid} ${x2},${y2}" marker-end="url(#cc-arrow)"/><text x="${(x1 + x2) / 2}" y="${mid - 7}" text-anchor="middle">${esc(e.label || "Continuar")}</text></g>`;
          })
          .join("");
    }
    function canvas() {
      if (!state.doc) return;
      $("[data-cc-nodes]").innerHTML = state.doc.nodes
        .map(
          (n, i) =>
            `<article class="cc-node is-${n.kind} ${state.selected === n.id ? "is-selected" : ""}" data-cc-node="${n.id}" style="left:${n.x}px;top:${n.y}px"><button type="button" class="cc-in" data-cc-target="${n.id}" aria-label="Conectar aquí: ${esc(n.title)}">↓</button><header data-cc-drag="${n.id}" tabindex="0" aria-label="Mover ${esc(n.title)} con las flechas"><span>${i + 1} · ${esc(labels[n.kind])}</span><b>${esc(n.title)}</b></header><button type="button" class="cc-node-edit" data-cc-select="${n.id}" aria-label="Editar ${esc(n.title)}"><div class="cc-faces">${faces(n.cards, state.cards, state.index) || '<span class="cc-drop">Arrastra cartas aquí</span>'}</div><strong>${esc(n.action)}</strong><p>${esc(n.explanation || "Selecciona este paso para explicar la jugada.")}</p></button><button type="button" class="cc-out" data-cc-source="${n.id}">${state.from === n.id ? "Elige el destino…" : "Conectar →"}</button></article>`,
        )
        .join("");
      const width = Math.max(1400, ...state.doc.nodes.map((n) => n.x + 300)),
        height = Math.max(900, ...state.doc.nodes.map((n) => n.y + 460));
      const stage = $("[data-cc-stage]");
      stage.style.width = width + "px";
      stage.style.height = height + "px";
      stage.style.transform = `scale(${state.zoom})`;
      $(".cc-stage-space").style.width = width * state.zoom + "px";
      $(".cc-stage-space").style.height = height * state.zoom + "px";
      $("[data-cc-hint]").textContent = state.from
        ? "Conecta con ↓ en el paso de destino, o pulsa Escape para cancelar."
        : "Arrastra cartas, agrupa piezas y conecta los pasos.";
      wires();
    }
    function inspector() {
      const box = $("[data-cc-inspector]");
      const openPanels=[...box.querySelectorAll('.cc-step-details')].map(d=>d.open);
      if (state.edge !== null && state.doc.edges[state.edge]) {
        const edge = state.doc.edges[state.edge];
        box.innerHTML = `<h3>Condición de la flecha</h3><p>${esc(state.doc.nodes.find((n) => n.id === edge.from)?.title)} → ${esc(state.doc.nodes.find((n) => n.id === edge.to)?.title)}</p><label>Cuándo seguir esta ruta<input data-cc-edge-label maxlength="160" value="${esc(edge.label)}" placeholder="Ej.: si la búsqueda resuelve"></label><button type="button" class="btn btn-outline-danger" data-cc-remove-edge>Quitar conexión</button>`;
        return;
      }
      const n = state.doc.nodes.find((n) => n.id === state.selected);
      if (!n) {
        box.innerHTML =
          "<h3>Da sentido a cada paso</h3><p>Selecciona una jugada para explicar su acción, costes, restricciones y resultado.</p><ol><li>Añade la mano inicial.</li><li>Arrastra cartas al lienzo.</li><li>Conecta y describe las decisiones.</li><li>Añade el resultado y guarda.</li></ol>";
        return;
      }
      box.innerHTML = `<h3>Editar paso</h3><label>Título<input data-cc-prop="title" maxlength="120" value="${esc(n.title)}"></label><label>Tipo<select data-cc-prop="kind">${Object.entries(
        labels,
      )
        .map(
          ([k, v]) =>
            `<option value="${k}" ${k === n.kind ? "selected" : ""}>${v}</option>`,
        )
        .join(
          "",
        )}</select></label><label>Acción<input data-cc-prop="action" maxlength="80" value="${esc(n.action)}" placeholder="Invocar, activar, buscar, enviar…"></label><label>Explicación para el jugador<textarea rows="6" maxlength="4000" data-cc-prop="explanation" placeholder="1. Qué carta utilizas y desde dónde.&#10;2. Qué haces y qué coste pagas.&#10;3. Qué carta eliges y dónde termina.&#10;4. Qué obtienes y qué límites debes respetar.">${esc(n.explanation)}</textarea></label><div class="cc-selected-cards">${n.cards.map((id, i) => `<div>${face(findCard(state.cards, id), state.index, true)}<button type="button" data-cc-remove-card="${i}" aria-label="Retirar carta del paso">×</button></div>`).join("")}</div><button type="button" class="btn btn-outline-info" data-cc-group ${state.palette ? "" : "disabled"}>Añadir carta seleccionada al paso</button><button type="button" class="btn btn-outline-danger" data-cc-remove-node>Quitar paso</button><p>Una misma carta puede aparecer en varios pasos para explicar su recorrido.</p>`;
      box.insertAdjacentHTML(
        "beforeend",
        `<details class="cc-step-details"><summary>Costes, zonas, restricciones y fuentes</summary>${Object.entries(
          detailLabels,
        )
          .map(
            ([k, v]) =>
              `<label>${v}<textarea rows="2" maxlength="1500" data-cc-detail="${k}">${esc(n.details?.[k] || "")}</textarea></label>`,
          )
          .join(
            "",
          )}</details><details class="cc-step-details"><summary>Ubicación de las cartas en este estado</summary><p>Indica las zonas conocidas. No reconstruye ni valida efectos automáticamente.</p>${n.cards
          .map((id, i) => {
            const p = n.placements?.find((p) => p.card_index === i) || {};
            return `<fieldset><legend>${esc(findCard(state.cards, id)?.card_name || "Carta pendiente")} · ${i + 1}</legend><label>Zona<select data-cc-place="zone" data-cc-card-index="${i}">${Object.entries(
              zones(state.doc.board_format),
            )
              .map(
                ([k, v]) =>
                  `<option value="${k}" ${(p.zone || (n.kind === "opening" ? "hand" : "unknown")) === k ? "selected" : ""}>${v}</option>`,
              )
              .join(
                "",
              )}</select></label><label>Posición<select data-cc-place="position" data-cc-card-index="${i}">${Object.entries(
              {
                unknown: "Sin documentar",
                attack: "Ataque",
                defense: "Defensa",
                face_down: "Boca abajo",
              },
            )
              .map(
                ([k, v]) =>
                  `<option value="${k}" ${(p.position || "unknown") === k ? "selected" : ""}>${v}</option>`,
              )
              .join(
                "",
              )}</select></label><label>Materiales y cantidades documentadas<input data-cc-place="materials" data-cc-card-index="${i}" maxlength="500" value="${esc(p.materials || "")}"></label></fieldset>`;
          })
          .join("")}</details>`,
      );
      [...box.querySelectorAll('.cc-step-details')].forEach((d,i)=>d.open=!!openPanels[i]);
    }
    function draw() {
      if (!state.doc) return;
      palette();
      canvas();
      inspector();
      $$("[data-cc-view]").forEach((b) => {
        b.classList.toggle("is-active", b.dataset.ccView === state.view);
        b.setAttribute("aria-pressed", b.dataset.ccView === state.view);
      });
      $("[data-cc-canvas-view]").hidden = state.view !== "canvas";
      $("[data-cc-preview]").hidden = state.view === "canvas" || state.view === "library";
      $("[data-cc-visual-library]").hidden = state.view !== "library";
      if (state.view !== "canvas" && state.view !== "library")
        $("[data-cc-preview]").innerHTML =
          state.view === "guide"
            ? guide(state.doc, state.cards, state.index)
            : matrix(
                [
                  { document: state.doc },
                  ...state.records.filter((r) => r.id !== state.doc.id),
                ],
                state.cards,
                state.index,
              );
      $("[data-cc-undo]").disabled = !state.undo.length;
      requestAnimationFrame(wires);
    }
    $("[data-cc-deck]").addEventListener("change", (e) => {
      if (
        state.dirty &&
        !confirm("¿Cambiar de deck y descartar la creación sin guardar?")
      ) {
        e.target.value = state.deckId;
        return;
      }
      selectDeck(e.target.value);
    });
    $("[data-cc-saved]").addEventListener("change", (e) => {
      if (
        state.dirty &&
        !confirm("¿Abrir otra creación y descartar los cambios sin guardar?")
      ) {
        savedOptions();
        return;
      }
      loadDocument(state.records.find((r) => r.id === e.target.value));
    });
    $("[data-cc-search]").addEventListener("input", (e) => {
      state.query = e.target.value;
      state.page = 0;
      palette();
    });
    $("[data-cc-section]").addEventListener("change", (e) => {
      state.section = e.target.value;
      state.page = 0;
      palette();
    });
    $("[data-cc-zoom]").addEventListener("change", (e) => {
      state.zoom = Number(e.target.value);
      canvas();
    });
    node.addEventListener("input", (e) => {
      if (e.target.dataset.ccMeta) {
        state.doc[e.target.dataset.ccMeta] = e.target.value;
        if (e.target.dataset.ccMeta === "skill_id")
          state.doc.skill_snapshot =
            (state.skills || []).find((s) => s.id === e.target.value) || null;
        if (e.target.dataset.ccMeta === "board_format") {
          if (e.target.value !== "rush") {
            delete state.doc.skill_id;
            delete state.doc.skill_snapshot;
            $('[data-cc-meta="skill_id"]').value = "";
          }
          inspector();
        }
        changed();
      }
    });
    node.addEventListener("change", (e) => {
      if (e.target.dataset.ccDetail || e.target.dataset.ccPlace) {
        const next = M.clone(state.doc),
          n = next.nodes.find((n) => n.id === state.selected);
        if (!n) return;
        if (e.target.dataset.ccDetail) {
          n.details ||= {};
          n.details[e.target.dataset.ccDetail] = e.target.value;
        } else {
          n.placements ||= [];
          const i = Number(e.target.dataset.ccCardIndex);
          let p = n.placements.find((p) => p.card_index === i);
          if (!p) {
            p = {
              card_index: i,
              zone: n.kind === "opening" ? "hand" : "unknown",
              position: "unknown",
              materials: "",
            };
            n.placements.push(p);
          }
          p[e.target.dataset.ccPlace] = e.target.value;
        }
        commit(next);
        return;
      }
      if (e.target.dataset.ccProp) {
        const next = M.clone(state.doc),
          n = next.nodes.find((n) => n.id === state.selected);
        n[e.target.dataset.ccProp] = e.target.value;
        if (
          n.kind === "opening" &&
          n.cards.some((id) => findCard(state.cards, id)?.section !== "main")
        ) {
          status(
            "Retira las cartas que no son Main antes de convertir este paso en mano inicial.",
            true,
          );
          inspector();
          return;
        }
        commit(next);
      }
      if (e.target.hasAttribute("data-cc-edge-label")) {
        const next = M.clone(state.doc);
        next.edges[state.edge].label = e.target.value;
        commit(next);
      }
    });
    node.addEventListener("click", (e) => {
      const b = e.target.closest("button");
      if (!b) return;
      attempt(() => {
        if (b.hasAttribute("data-cc-new")) {
          if (
            !state.dirty ||
            confirm("¿Empezar otra creación sin guardar estos cambios?")
          )
            loadDocument(null);
        } else if (b.dataset.ccAdd) {
          const next = M.addNode(state.doc, b.dataset.ccAdd);
          state.selected = next.nodes.at(-1).id;
          state.edge = null;
          commit(next);
        } else if (b.hasAttribute("data-cc-card")) {
          state.palette = Number(b.dataset.ccCard);
          palette();
          inspector();
        } else if (b.hasAttribute("data-cc-insert-card")) {
          if (
            !state.cards.some(
              (c) => Number(c.id) === state.palette && Number(c.quantity) > 0,
            )
          )
            return;
          const next = M.addNode(state.doc, "step", state.palette);
          state.selected = next.nodes.at(-1).id;
          state.edge = null;
          commit(next);
        } else if (b.hasAttribute("data-cc-group"))
          commit(
            M.addCard(state.doc, state.selected, state.palette, state.cards),
          );
        else if (b.dataset.ccPage) {
          state.page += Number(b.dataset.ccPage);
          palette();
        } else if (b.dataset.ccView) {
          state.view = b.dataset.ccView;
          draw();
          if (state.view === "library" && state.libraryDeck !== state.deckId) {
            const ticket = ++state.libraryTicket;
            state.libraryDeck = state.deckId;
            const host = $("[data-cc-visual-library]");
            if (root.YgoComboEditorial && root.YgoCheckedStudy) {
              root.YgoComboEditorial.mount(host, { id: state.deckId, name: decks.find(d => Number(d.id) === state.deckId)?.name || "Deck" }, () => ticket === state.libraryTicket);
            } else {
              host.textContent = "El visor documental no está disponible en esta versión de la plataforma.";
            }
          }
        } else if (b.dataset.ccSource) {
          state.from = b.dataset.ccSource;
          canvas();
        } else if (b.dataset.ccTarget) {
          if (!state.from) {
            state.selected = b.dataset.ccTarget;
            state.edge = null;
            draw();
            return;
          }
          const next = M.connect(state.doc, state.from, b.dataset.ccTarget);
          state.from = "";
          commit(next);
        } else if (b.dataset.ccSelect) {
          state.selected = b.dataset.ccSelect;
          state.edge = null;
          draw();
        } else if (b.hasAttribute("data-cc-remove-node")) {
          const next = M.removeNode(state.doc, state.selected);
          state.selected = "";
          state.edge = null;
          commit(next);
        } else if (b.hasAttribute("data-cc-remove-card")) {
          const next = M.clone(state.doc);
          next.nodes
            .find((n) => n.id === state.selected)
            .cards.splice(Number(b.dataset.ccRemoveCard), 1);
          const n = next.nodes.find((n) => n.id === state.selected),
            removed = Number(b.dataset.ccRemoveCard);
          if (n.placements)
            n.placements = n.placements
              .filter((p) => p.card_index !== removed)
              .map((p) => ({
                ...p,
                card_index:
                  p.card_index > removed ? p.card_index - 1 : p.card_index,
              }));
          commit(next);
        } else if (b.hasAttribute("data-cc-remove-edge")) {
          const next = M.clone(state.doc);
          next.edges.splice(state.edge, 1);
          state.edge = null;
          commit(next);
        } else if (b.hasAttribute("data-cc-undo")) {
          if (state.undo.length) {
            const previous = state.undo.pop();
            state.doc = {
              ...state.doc,
              nodes: previous.nodes,
              edges: previous.edges,
            };
            state.selected = "";
            state.edge = null;
            changed();
            draw();
          }
        } else if (b.hasAttribute("data-cc-refresh")) refreshCards();
        else if (b.hasAttribute("data-cc-save")) save();
        else if (b.hasAttribute("data-cc-history")) showHistory();
        else if (b.hasAttribute("data-cc-duplicate")) {
          if (
            state.dirty &&
            !confirm("¿Duplicar el trabajo actual como una nueva creación?")
          )
            return;
          const doc = M.clone(state.doc);
          doc.id = M.uid();
          delete doc.source_route_id;
          doc.title = (doc.title + " · copia").slice(0, 160);
          loadDocument({ document: doc, revision: 0 });
          state.doc = doc;
          state.revision = 0;
          changed();
          savedOptions();
        } else if (b.dataset.ccRestore) {
          if (
            state.dirty &&
            !confirm(
              "¿Cargar esta revisión y descartar los cambios sin guardar?",
            )
          )
            return;
          const old = state.history?.find(
            (h) => h.revision === Number(b.dataset.ccRestore),
          );
          const current = state.records.find((r) => r.id === state.doc.id);
          if (!old || !current) return;
          loadDocument(old.record);
          state.revision = current.revision;
          changed();
          status(
            "Revisión histórica cargada. Guardar crea una revisión nueva, sin borrar el historial.",
          );
        }
      });
    });
    node.addEventListener("click", (e) => {
      const wire = e.target.closest("[data-cc-edge]");
      if (wire) {
        state.edge = Number(wire.dataset.ccEdge);
        state.selected = "";
        inspector();
      }
    });
    node.addEventListener("dragstart", (e) => {
      const card = e.target.closest("[data-cc-card]");
      if (card) {
        e.dataTransfer.setData(
          "application/x-stationx-combo-card",
          card.dataset.ccCard,
        );
        e.dataTransfer.effectAllowed = "copy";
      }
    });
    $("[data-cc-stage]").addEventListener("dragover", (e) => {
      if (
        [...e.dataTransfer.types].includes("application/x-stationx-combo-card")
      ) {
        e.preventDefault();
        e.dataTransfer.dropEffect = "copy";
      }
    });
    $("[data-cc-stage]").addEventListener("drop", (e) => {
      e.preventDefault();
      const id = Number(
        e.dataTransfer.getData("application/x-stationx-combo-card"),
      );
      if (
        !state.cards.some((c) => Number(c.id) === id && Number(c.quantity) > 0)
      )
        return;
      attempt(() => {
        const target = e.target.closest("[data-cc-node]");
        if (target) {
          state.selected = target.dataset.ccNode;
          commit(M.addCard(state.doc, state.selected, id, state.cards));
        } else {
          const rect = $("[data-cc-stage]").getBoundingClientRect(),
            point = {
              x: Math.min(
                5000,
                Math.max(0, (e.clientX - rect.left) / state.zoom - 100),
              ),
              y: Math.min(
                5000,
                Math.max(0, (e.clientY - rect.top) / state.zoom - 40),
              ),
            };
          const next = M.addNode(state.doc, "step", id, point);
          state.selected = next.nodes.at(-1).id;
          state.edge = null;
          commit(next);
        }
      });
    });
    let dragging = null;
    node.addEventListener("pointerdown", (e) => {
      const h = e.target.closest("[data-cc-drag]");
      if (!h || e.button !== 0) return;
      const n = state.doc.nodes.find((n) => n.id === h.dataset.ccDrag);
      dragging = {
        id: n.id,
        x: e.clientX,
        y: e.clientY,
        nx: n.x,
        ny: n.y,
        before: M.clone(state.doc),
      };
      h.setPointerCapture(e.pointerId);
    });
    node.addEventListener("pointermove", (e) => {
      if (!dragging) return;
      const n = state.doc.nodes.find((n) => n.id === dragging.id);
      n.x = Math.min(
        5000,
        Math.max(0, dragging.nx + (e.clientX - dragging.x) / state.zoom),
      );
      n.y = Math.min(
        5000,
        Math.max(0, dragging.ny + (e.clientY - dragging.y) / state.zoom),
      );
      const el = node.querySelector(`[data-cc-node="${n.id}"]`);
      el.style.left = n.x + "px";
      el.style.top = n.y + "px";
      wires();
    });
    const endDrag = () => {
      if (!dragging) return;
      if (JSON.stringify(state.doc) !== JSON.stringify(dragging.before)) {
        state.undo.push(dragging.before);
        changed();
      }
      state.selected = dragging.id;
      state.edge = null;
      dragging = null;
      draw();
    };
    node.addEventListener("pointerup", endDrag);
    node.addEventListener("pointercancel", endDrag);
    node.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        state.from = "";
        canvas();
      }
      const h = e.target.closest("[data-cc-drag]");
      if (
        h &&
        ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(e.key)
      ) {
        e.preventDefault();
        const next = M.clone(state.doc),
          n = next.nodes.find((n) => n.id === h.dataset.ccDrag);
        n.x = Math.min(
          5000,
          Math.max(
            0,
            n.x +
              (e.key === "ArrowLeft" ? -20 : e.key === "ArrowRight" ? 20 : 0),
          ),
        );
        n.y = Math.min(
          5000,
          Math.max(
            0,
            n.y + (e.key === "ArrowUp" ? -20 : e.key === "ArrowDown" ? 20 : 0),
          ),
        );
        commit(next);
        node.querySelector(`[data-cc-drag="${n.id}"]`).focus();
      }
      if (
        e.target.closest("[data-cc-edge]") &&
        ["Enter", " "].includes(e.key)
      ) {
        e.preventDefault();
        state.edge = Number(e.target.closest("[data-cc-edge]").dataset.ccEdge);
        inspector();
      }
    });
    async function refreshCards() {
      if (state.saving) return;
      const id = state.deckId;
      status("Actualizando las cartas sin cerrar tu trabajo...");
      try {
        const result = await api(id);
        if (state.deckId !== id) return;
        state.cards = result.cards;
        state.inventory = result.inventory_sha256;
        state.records = [...result.records,...state.records.filter(r=>r.imported&&!result.records.some(saved=>saved.id===r.id))];
        state.token = result.token;
        state.palette = null;
        savedOptions();
        draw();
        status(
          "Cartas actualizadas. Revisa la lista y los requisitos antes de guardar.",
        );
      } catch (error) {
        status(error.message, true);
      }
    }
    async function showHistory() {
      if (!state.records.some((r) => r.id === state.doc.id && r.revision>0)) {
        status("Guarda primero esta creación para consultar sus revisiones.");
        return;
      }
      const id = state.doc.id,
        deck = state.deckId;
      try {
        const response = await api(deck, null, null, id);
        if (state.deckId !== deck || state.doc?.id !== id) return;
        state.history = response.history || [];
        const panel = $("[data-cc-history-panel]");
        panel.hidden = false;
        panel.innerHTML =
          "<h3>Historial de esta creación</h3><p>Cargar una versión no reemplaza la actual hasta guardar una revisión nueva.</p>" +
          state.history
            .map(
              (h) =>
                `<details><summary>Revisión ${h.revision} · ${esc(h.created_at)} UTC</summary><button type="button" data-cc-restore="${h.revision}">Cargar esta revisión en el editor</button>${guide(h.record.document, h.record.cards_snapshot || [], state.index)}</details>`,
            )
            .join("");
      } catch (e) {
        status(e.message, true);
      }
    }
    async function save() {
      if (state.saving) return;
      if (!state.doc.title.trim()) {
        status("Escribe un título para guardar la creación.", true);
        $('[data-cc-meta="title"]').focus();
        return;
      }
      if (!state.doc.nodes.length) {
        status("Añade al menos un paso.", true);
        return;
      }
      const sent = M.clone(state.doc);
      state.saving = true;
      $("[data-cc-save]").disabled = true;
      $("[data-cc-deck]").disabled = true;
      $("[data-cc-new]").disabled = true;
      $("[data-cc-saved]").disabled = true;
      status("Guardando en el deck…");
      try {
        const result = await api(
          state.deckId,
          {
            deck_id: state.deckId,
            revision: state.revision,
            inventory_sha256: state.inventory,
            document: sent,
          },
          state.token,
        );
        state.revision = result.record.revision;
        state.records = [
          result.record,
          ...state.records.filter((r) => r.id !== result.record.id),
        ];
        state.dirty = JSON.stringify(state.doc) !== JSON.stringify(sent);
        savedOptions();
        status(
          state.dirty
            ? "Combo guardado; tienes cambios nuevos sin guardar."
            : "Guardado en " +
                decks.find((d) => Number(d.id) === state.deckId)?.name +
                " · revisión " +
                state.revision + (result.record.document.source_route_id ? " · documento de origen actualizado" : ""),
        );
        root.dispatchEvent(
          new CustomEvent("stationx:combo-saved", {
            detail: { deckId: state.deckId },
          }),
        );
      } catch (e) {
        status(e.message, true);
      } finally {
        state.saving = false;
        $("[data-cc-save]").disabled = false;
        $("[data-cc-deck]").disabled = false;
        $("[data-cc-new]").disabled = false;
        $("[data-cc-saved]").disabled = false;
      }
    }
    root.addEventListener("beforeunload", (e) => {
      if (state.dirty) {
        e.preventDefault();
        e.returnValue = "";
      }
    });
    images().then((index) => {
      state.index = index;
      draw();
    });
    root.addEventListener("stationx:combo-edit", async (e) => {
      if (
        state.dirty &&
        !confirm("¿Abrir este combo y descartar los cambios sin guardar?")
      )
        return;
      $("[data-cc-deck]").value = e.detail.deckId;
      await selectDeck(e.detail.deckId);
      if (state.doc && e.detail.id)
        loadDocument(state.records.find((r) => r.id === e.detail.id));
      if (state.doc && e.detail.sourceId) {
        const record=state.records.find((r) => r.id === state.templateRoutes[e.detail.sourceId]);
        if(record)loadDocument(record);
        else status("No se pudo vincular esta guía con la lista actual. Revisa el aviso de importación del deck.",true);
      }
      if (state.doc && !e.detail.id && !e.detail.sourceId)
        loadDocument(null);
      document.querySelector('[data-route="creador_combos"]')?.click();
    });
  }
  const viewers = new WeakMap();
  async function mountViewer(node, deck) {
    if (!node || !deck) return;
    const ticket = {};
    viewers.set(node, ticket);
    node.querySelector('[data-cc-editorial-library] [data-checked-study]')?._curatedDispose?.();
    node.className = "combo-reader";
    node.innerHTML = `<nav class="cc-reader-head" aria-label="Biblioteca y creaciones"><button type="button" class="btn btn-outline-info" data-cc-jump="guides">Guías visuales y combos</button><button type="button" class="btn btn-outline-info" data-cc-jump="personal">Mis creaciones guardadas</button></nav><section data-cc-guides><h3>Guías visuales y combos documentados</h3><div data-cc-editorial-library></div></section><section data-cc-personal><p>Cargando tus creaciones guardadas…</p></section>`;
    node.querySelectorAll('[data-cc-jump]').forEach(button=>button.onclick=()=>{
      const target=node.querySelector(button.dataset.ccJump==='guides'?'[data-cc-guides]':'[data-cc-personal]');
      target.tabIndex=-1;target.focus({preventScroll:true});target.scrollIntoView({block:'start'});
    });
    const personal=node.querySelector('[data-cc-personal]');
    const editorial=root.YgoComboEditorial?.mount(node.querySelector('[data-cc-editorial-library]'),deck,()=>viewers.get(node)===ticket);
    try {
      const [result, index] = await Promise.all([api(deck.id), images()]);
      if (viewers.get(node) !== ticket) return;
      personal.innerHTML = `<header class="cc-reader-head"><div><h3>Mis creaciones de ${esc(deck.name)}</h3><p>Tus creaciones guardadas, con sus cartas, decisiones y condiciones.</p></div><button type="button" class="btn btn-outline-info" data-cc-create>+ Crear para este deck</button></header><div class="cc-reader-list">${result.records.map((r) => `<button type="button" data-cc-read="${esc(r.id)}"><b>${esc(r.title)}</b><span>${r.document.nodes.length} pasos · revisión ${r.revision}${r.stale ? " · Revisar lista" : ""}</span></button>`).join("") || '<div class="cc-empty">Todavía no hay creaciones guardadas para este deck.</div>'}</div><div data-cc-reading></div>`;
      if (result.cards_loaded === false) {
        node.querySelector("[data-cc-create]").disabled = true;
        personal.insertAdjacentHTML(
          "afterbegin",
          '<p class="cc-warning">Sin Main cargado. Puedes consultar las instantáneas históricas; carga cartas para crear o guardar cambios.</p>',
        );
      }
      node.onclick = (e) => {
        const b = e.target.closest("button");
        if (!b) return;
        if (b.hasAttribute("data-cc-create"))
          root.dispatchEvent(
            new CustomEvent("stationx:combo-edit", {
              detail: { deckId: Number(deck.id) },
            }),
          );
        if (b.dataset.ccRead) {
          const r = result.records.find((r) => r.id === b.dataset.ccRead);
          node.querySelector("[data-cc-reading]").innerHTML =
            `${r.stale ? '<p class="cc-warning">La lista actual cambió. Revisa los requisitos y las cartas de esta creación.</p>' : ""}<button type="button" class="btn btn-outline-info" data-cc-edit="${esc(r.id)}">Editar esta creación</button>` +
            guide(r.document, r.cards_snapshot || result.cards, index);
          if (result.cards_loaded === false)
            node.querySelector("[data-cc-edit]").disabled = true;
        }
        if (b.dataset.ccEdit)
          root.dispatchEvent(
            new CustomEvent("stationx:combo-edit", {
              detail: { deckId: Number(deck.id), id: b.dataset.ccEdit },
            }),
          );
      };
      ticket.deck = deck;
    } catch (e) {
      if (viewers.get(node) === ticket)
        personal.innerHTML = `<p class="cc-warning">${esc(e.message)}</p>`;
    }
    await editorial;
  }
  root.YgoComboCreator = { mountViewer, renderDocument: (doc,cards) => guide(doc,cards,{}) };
  function showPhoto(img) {
    let dialog = document.querySelector(".cc-photo-dialog");
    if (!dialog) {
      dialog = document.createElement("dialog");
      dialog.className = "cc-photo-dialog";
      dialog.setAttribute("aria-label", "Carta ampliada");
      dialog.innerHTML =
        '<button type="button" aria-label="Cerrar carta ampliada">Cerrar ×</button><img alt=""><p></p>';
      document.body.append(dialog);
      dialog.querySelector("button").onclick = () => dialog.close();
      dialog.addEventListener("click", (e) => {
        if (e.target === dialog) dialog.close();
      });
    }
    dialog.querySelector("img").src = img.currentSrc || img.src;
    dialog.querySelector("img").alt = img.alt;
    dialog.querySelector("p").textContent = img.alt;
    if (!dialog.open) dialog.showModal();
  }
  function guideNavigation(g, action, target) {
    const steps = [...g.querySelectorAll("[data-cc-step]")],
      root = g.querySelector("[data-cc-guide-root]");
    g._trail ||= [root.value];
    if (action === "root") {
      g._trail = [target];
      g._all = false;
    }
    if (action === "back") {
      if (g._trail.length > 1) g._trail.pop();
      g._all = false;
    }
    if (action === "all") g._all = !g._all;
    if (action === "follow") {
      const from = target.closest("[data-cc-step]").dataset.ccStep;
      if (g._trail.at(-1) !== from) g._trail = [from];
      g._trail.push(target.dataset.ccGuideFollow);
      g._all = false;
    }
    steps.forEach(
      (s) => (s.hidden = !g._all && s.dataset.ccStep !== g._trail.at(-1)),
    );
    g.querySelector("[data-cc-guide-back]").disabled = g._trail.length < 2;
    g.querySelector("[data-cc-guide-all]").textContent = g._all
      ? "Volver al recorrido"
      : "Ver todos los pasos";
    g.querySelector("[data-cc-guide-progress]").textContent = g._all
      ? "Vista completa · las alternativas se consultan por separado"
      : `${steps.find(s => s.dataset.ccStep === g._trail.at(-1))?.querySelector("h4")?.textContent || "Vista actual"} ${String.fromCharCode(183)} ${g._trail.length} etapas recorridas`;
  }
  document.addEventListener("click", (e) => {
    const image = e.target.closest(
      ".cc-guide img,.cc-matrix img,.cc-selected-cards img",
    );
    if (image) {
      showPhoto(image);
      return;
    }
    const g = e.target.closest("[data-cc-guide]");
    if (!g) return;
    const button = e.target.closest("button");
    if (!button) return;
    if (button.hasAttribute("data-cc-guide-follow"))
      guideNavigation(g, "follow", button);
    if (button.hasAttribute("data-cc-guide-back")) guideNavigation(g, "back");
    if (button.hasAttribute("data-cc-guide-all")) guideNavigation(g, "all");
  });
  document.addEventListener("change", (e) => {
    if (e.target.hasAttribute("data-cc-guide-root"))
      guideNavigation(
        e.target.closest("[data-cc-guide]"),
        "root",
        e.target.value,
      );
  });
  document.addEventListener("keydown", (e) => {
    if (
      ["Enter", " "].includes(e.key) &&
      e.target.matches(".cc-guide img,.cc-matrix img,.cc-selected-cards img")
    ) {
      e.preventDefault();
      showPhoto(e.target);
    }
  });
  document.addEventListener("dblclick", (e) => {
    if (e.target.matches(".combo-maker img,.combo-reader img"))
      showPhoto(e.target);
  });
  document.addEventListener(
    "error",
    (event) => {
      const img = event.target;
      if (
        img instanceof HTMLImageElement &&
        img.closest(".combo-maker,.combo-reader")
      ) {
        img.hidden = true;
        const fallback = document.createElement("span");
        fallback.className = "cc-no-image";
        fallback.textContent = "Imagen no disponible";
        img.after(fallback);
      }
    },
    true,
  );
  function start() {
    editor(document.querySelector("[data-combo-creator]"), root.DML_DATA || {});
    root.addEventListener("stationx:combo-saved", (e) => {
      for (const node of document.querySelectorAll(
        "[data-deck-created-combos]",
      )) {
        const state = viewers.get(node);
        if (Number(state?.deck?.id) === Number(e.detail.deckId))
          mountViewer(node, state.deck);
      }
    });
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", start);
  else start();
})(window);
