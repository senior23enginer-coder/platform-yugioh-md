(function (root) {
  "use strict";
  const instances = new WeakMap();
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
  const arr = (v) =>
    Array.isArray(v) ? v : typeof v === "string" && v.trim() ? [v] : [];
  const text = (v) => (typeof v === "string" ? v : "");
  const p = (v) =>
    arr(v)
      .filter((x) => typeof x === "string" && x.trim())
      .map((x) => `<p>${esc(x)}</p>`)
      .join("");
  const list = (v) =>
    arr(v).filter((x) => typeof x === "string" && x.trim()).length
      ? `<ul>${arr(v)
          .map((x) => `<li>${esc(x)}</li>`)
          .join("")}</ul>`
      : "";
  const field = (label, value) =>
    value
      ? `<div class="r10-field"><strong>${esc(label)}</strong>${p(value)}</div>`
      : "";
  const details = (label, body) =>
    body
      ? `<details class="r10-details"><summary>${esc(label)}</summary>${body}</details>`
      : "";
  const zones = {
    main: "Main Deck",
    extra: "Extra Deck",
    side: "Side Deck",
    reserva: "Reserva",
    reserve: "Reserva",
  };
  const states = {
    CONDICIONAL: "Condicional",
    PENDIENTE_DE_VERIFICACION: "Pendiente de verificación",
    PENDIENTE_VERIFICACION: "Pendiente de verificación",
    NO_EJECUTABLE: "No ejecutable con esta lista",
  };
  const badge = (status) =>
    `<span class="r10-badge">${esc(states[status] || "Por comprobar")}</span>`;
  const source = (url) =>
    /^https:\/\/(www\.)?db\.yugioh-card\.com\//.test(url || "")
      ? `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer">Consultar fuente oficial</a>`
      : "";
  const routeButton = (id, label = "Ver paso a paso") =>
    `<button type="button" class="btn btn-outline-info" data-r10-route="${esc(id)}">${esc(label)}</button>`;
  function art(c, record) {
    const ref = record.cards.find(
      (x) =>
        x.name === c.nombre &&
        x.section === ({ reserva: "reserve" }[c.zona] || c.zona),
    );
    return ref?.id
      ? `<button type="button" class="r10-art" data-card-inspect="${Number(ref.id)}" data-card-name="${esc(c.nombre)}" aria-label="Ampliar ${esc(c.nombre)}"><img src="./?api=deck_builder&op=image&id=${Number(ref.id)}" alt="${esc(c.nombre)}" loading="lazy" width="90" height="132"></button>`
      : `<div class="r10-neutral" aria-label="${esc(c.nombre)}">${esc(c.nombre)}</div>`;
  }
  function skill(record) {
    const d = record.curated,
      s = d.skills?.[0];
    if (!s) return "";
    const mapped = record.skills[0];
    return `<section class="r10-skill"><a href="${esc(mapped.image_url)}" target="_blank" rel="noopener"><img src="${esc(mapped.image_url)}" alt="Habilidad ${esc(s.nombre)}" loading="lazy"></a><div><span class="r10-badge">Habilidad externa · no se roba</span><h3>${esc(s.nombre)}</h3>${field("Requisito", s.requisito_texto)}${field("Efecto", s.efecto_texto)}${details("Cuándo puedes utilizarla", list(s.activation_conditions))}</div></section>`;
  }
  function cardDetail(c, record) {
    const a = c.analisis_18_campos || {};
    const groups = [
      [
        "Cómo funciona",
        [
          ["01_nombre_caracteristicas", "Identidad"],
          ["02_efecto_condiciones", "Efecto y condiciones"],
          ["03_funcion_principal", "Función principal"],
          ["04_funciones_secundarias", "Otras funciones"],
        ],
      ],
      [
        "Jugar y conectar piezas",
        [
          ["05_sinergias", "Sinergias"],
          ["06_combos_que_permite", "Rutas relacionadas"],
          ["07_dependencias", "Recursos necesarios"],
          ["08_restricciones", "Restricciones"],
          ["11_ir_primero", "Primero"],
          ["12_ir_segundo", "Segundo"],
          ["13_turnos_posteriores", "Turnos posteriores"],
        ],
      ],
      [
        "Ventajas y riesgos",
        [
          ["09_ventajas", "Ventajas"],
          ["10_debilidades", "Debilidades"],
          ["14_riesgo_carta_muerta", "Manos problemáticas"],
        ],
      ],
      [
        "Cantidad y alternativas",
        [
          ["15_copias_actuales", "Copias actuales"],
          ["16_copias_recomendadas", "Recomendación de copias"],
          ["17_alternativas", "Alternativas"],
          ["18_importancia_competitiva", "Importancia editorial"],
        ],
      ],
    ];
    // Exact aliases cover the declared 18-field source variants; no property-name renderer.
    const get = (k) =>
      a[k] ||
      {
        "13_turnos_posteriores": a["13_turnos_avanzados"],
        "14_riesgo_carta_muerta": a["14_carta_muerta"],
        "15_copias_actuales": a["15_cantidad_actual"],
        "16_copias_recomendadas": a["16_cantidad_recomendada"],
      }[k];
    return `<article class="r10-card-detail"><header>${art(c, record)}<div><span class="r10-badge">${esc(zones[c.zona] || c.zona)} · ${Number(c.copias)} copias</span><h3>${esc(c.nombre)}</h3>${p(c.funcion_en_el_deck)}<p>${[c.tipo, c.atributo, c.nivel_o_rango, c.ataque, c.defensa].filter(Boolean).map(esc).join(" · ")}</p></div></header>${field("Texto de la carta en la fuente", c.texto_oficial_konami?.texto)}${field("Condición", c.condicion)}${field("Efecto", c.efecto)}${field("Precauciones", c.precauciones_en_jugadas)}<div class="r10-links">${(c.combos_relacionados || []).map((id) => routeButton(id, id)).join("")}</div>${groups.map(([label, fields]) => details(label, fields.map(([k, l]) => field(l, get(k))).join(""))).join("")}${details(
      "Acciones documentadas",
      arr(c.acciones_en_combos)
        .map(
          (x) =>
            `<article>${p(x.accion_documentada)}${routeButton(x.combo, x.combo)}</article>`,
        )
        .join(""),
    )}${c.probabilidad_apertura_individual ? probability(c.probabilidad_apertura_individual) : ""}${source(c.fuente_oficial)}</article>`;
  }
  function probability(q) {
    return `<section class="r10-probability"><h4>Probabilidad de robar esta carta</h4><strong>${Number(q.probabilidad_porcentaje).toLocaleString("es", { maximumFractionDigits: 4 })}%</strong><p>${Number(q.copias_de_esta_carta_K)} copias · Main ${Number(q.total_main_N)} · mano de ${Number(q.cartas_robadas_n)} cartas</p>${details("Cómo se calculó", p(q.formula) + p(q.fraccion_exacta))}${p(q.advertencia)}</section>`;
  }
  function mount(host, deck, record) {
    instances.get(host)?.controller.abort();
    const controller = new AbortController(),
      d = record.curated,
      profile = d.perfil_y_plan_estrategico_R10;
    const s = {
      deckId: Number(deck.id),
      record,
      controller,
      tab: "intro",
      scenario: "",
      none: false,
      combo: 0,
      step: 0,
      query: "",
      status: "",
      turn: "",
      type: "",
      difficulty: "",
      cardPage: 0,
      card: 0,
      zone: "",
      hand: 0,
      chapter: 0,
      threat: 0,
      rival: 0,
      category: "",
    };
    instances.set(host, s);
    host.classList.add("learning-enabled", "r10-enabled");
    host.classList.remove("learning-documents");
    const nav = [
      ["intro", "Empieza aquí"],
      ["now", "¿Qué juego ahora?"],
      ["cards", "Cartas y funciones"],
      ["combos", "Combos paso a paso"],
      ["interrupt", "Si te interrumpen"],
      ["turn", "Primero o segundo"],
      ["risks", "Fortalezas y debilidades"],
      ["training", "Manos y probabilidades"],
      ["matchups", "Contra otros decks"],
      ["rating", "Evaluación editorial"],
      ["library", "Más recursos"],
    ];
    const style = profile.estilo_de_juego;
    function phasePlans() {
      return Object.entries({
        inicio_primero: "Al empezar primero",
        inicio_segundo: "Al jugar segundo",
        tras_negacion_o_interrupcion: "Tras una interrupción",
        sin_pieza_principal: "Sin pieza principal",
        juego_medio: "Juego medio",
        juego_tardio_y_cierre: "Juego tardío y cierre",
      })
        .map(([key, label]) => details(label, p(profile.planes_por_fase[key])))
        .join("");
    }
    function intro() {
      return `<section class="r10-intro"><h3>Cómo juega ${esc(d.nombre)}</h3>${p(style.clasificacion_razonada)}<div class="r10-grid">${field("Motor", style.motor_real_documentado)}${field("Ritmo", style.ritmo_general)}${field("Riesgo principal", style.principal_riesgo)}</div><div class="r10-links"><button class="btn btn-info" type="button" data-r10-tab="now">Elegir mi situación</button><button class="btn btn-outline-info" type="button" data-r10-tab="combos">Aprender un combo</button></div></section>${skill(record)}${details("Objetivo y recursos", list(d.plan_de_juego?.idea_de_juego) + list(d.plan_de_juego?.gestion_de_recursos) + list(d.plan_de_juego?.condiciones_de_victoria))}${details("Cómo progresa la partida", phasePlans())}${details("Side y Reserva", p(profile.side_y_reserva.decision) + (profile.side_y_reserva.cartas_activas === 0 ? "<p>No hay sustituciones registradas.</p>" : ""))}`;
    }
    function now() {
      const scenario = profile.escenarios_interactivos.find(
        (x) => x.id === s.scenario,
      );
      return `<h3>¿Qué juego ahora?</h3><p>Elige la situación que estás jugando. Comprueba mano, campo y efectos antes de tomar una línea.</p><div class="r10-scenarios">${profile.escenarios_interactivos.map((x) => `<button class="btn ${x.id === s.scenario ? "btn-info" : "btn-outline-info"}" type="button" data-r10-scenario="${esc(x.id)}" aria-pressed="${x.id === s.scenario}">${esc(x.nombre)}</button>`).join("")}</div>${!scenario ? "" : `<section class="r10-panel"><h3>${esc(scenario.pregunta)}</h3>${p(scenario.orientacion_para_el_deck)}<p class="r10-badge">Orientación editorial · estado del duelo por comprobar</p>${scenario.opciones_basadas_en_rutas_existentes.map((o) => `<article class="r10-option" data-r10-option="${esc(o.id_combo_existente)}"><header>${badge(o.estado_de_ruta)}<h4>${esc(o.titulo)}</h4></header>${details("Comprueba requisitos", p(o.requisitos_de_origen))}${field("Resultado documentado", o.resultado_documentado)}${field("Riesgos documentados", o.riesgos_documentados)}${routeButton(o.id_combo_existente)}</article>`).join("")}<button type="button" class="btn btn-outline-light" data-r10-none>Ninguna alternativa cumple</button>${s.none ? `<p role="status" class="r10-warning">${esc(scenario.si_no_se_cumple_ninguna)}</p>` : ""}</section>`}`;
    }
    function cards() {
      const rows = d.cartas
        .map((c, i) => ({ c, i }))
        .filter(
          ({ c }) =>
            (!s.zone || c.zona === s.zone) &&
            norm(c.nombre + " " + (c.funcion_en_el_deck || "")).includes(
              norm(s.query),
            ),
        );
      s.cardPage = Math.min(
        s.cardPage,
        Math.max(0, Math.ceil(rows.length / 6) - 1),
      );
      const selected = d.cartas[s.card];
      return `<h3>Cartas de esta construcción</h3><div class="r10-filters"><label>Buscar carta<input class="form-control" type="search" data-r10-query value="${esc(s.query)}"></label><label>Zona<select class="form-select" data-r10-zone><option value="">Todas</option>${[...new Set(d.cartas.map((c) => c.zona))].map((z) => `<option value="${esc(z)}" ${z === s.zone ? "selected" : ""}>${esc(zones[z] || z)}</option>`).join("")}</select></label></div><p>${rows.length} fichas · página ${s.cardPage + 1} de ${Math.max(1, Math.ceil(rows.length / 6))}</p><div class="r10-grid r10-card-list">${rows
        .slice(s.cardPage * 6, s.cardPage * 6 + 6)
        .map(
          ({ c, i }) =>
            `<button class="r10-card-choice" type="button" data-r10-card="${i}" aria-pressed="${s.card === i}"><strong>${esc(c.nombre)}</strong><span>${esc(zones[c.zona] || c.zona)} · ${Number(c.copias)} copias</span>${p(c.funcion_en_el_deck)}</button>`,
        )
        .join(
          "",
        )}</div><div class="r10-links"><button class="btn btn-outline-info" type="button" data-r10-page="-1" ${s.cardPage === 0 ? "disabled" : ""}>Anterior</button><button class="btn btn-outline-info" type="button" data-r10-page="1" ${(s.cardPage + 1) * 6 >= rows.length ? "disabled" : ""}>Siguiente</button></div>${selected ? cardDetail(selected, record) : ""}`;
    }
    function combos() {
      const classify = (c) =>
        c.tactical_full_prompt_detail_v20?.classification ||
        Object.entries(c).find(([k]) =>
          k.startsWith("tactical_full_prompt_detail"),
        )?.[1]?.classification ||
        "";
      const rows = d.combos
        .map((c, i) => ({ c, i }))
        .filter(
          ({ c }) =>
            (!s.status || c.estado === s.status) &&
            norm(
              [
                c.titulo,
                c.para_empezar,
                c.resultado_si_se_cumple,
                classify(c),
              ].join(" "),
            ).includes(norm(s.query)) &&
            (!s.turn ||
              norm(classify(c) + " " + c.para_empezar).includes(s.turn)) &&
            (!s.type ||
              norm(
                classify(c) + " " + c.para_empezar + " " + c.pasos.join(" "),
              ).includes(s.type)) &&
            (!s.difficulty || c.dificultad === s.difficulty),
        );
      if (rows.length && !rows.some((x) => x.i === s.combo)) {
        s.combo = rows[0].i;
        s.step = 0;
      }
      const c = d.combos[s.combo],
        v = c.guia_visual_estructurada || {};
      s.step = Math.min(s.step, c.pasos.length - 1);
      const tactical = v.pasos?.[s.step];
      const difficulty = [
        ...new Set(d.combos.map((c) => c.dificultad).filter(Boolean)),
      ];
      return `<h3>Combos paso a paso</h3><div class="r10-filters"><label>Carta inicial, resultado o jugada<input class="form-control" type="search" data-r10-query value="${esc(s.query)}"></label><label>Estado<select class="form-select" data-r10-status><option value="">Todos los estados</option>${Object.entries(
        states,
      )
        .filter(([k]) => d.combos.some((c) => c.estado === k))
        .map(
          ([k, l]) =>
            `<option value="${k}" ${s.status === k ? "selected" : ""}>${l}</option>`,
        )
        .join(
          "",
        )}</select></label><label>Momento<select class="form-select" data-r10-turn>${[
        ["", "Todos"],
        ["primero", "Primero"],
        ["segundo", "Segundo"],
        ["rival", "Turno rival"],
      ]
        .map(
          ([v, l]) =>
            `<option value="${v}" ${s.turn === v ? "selected" : ""}>${l}</option>`,
        )
        .join(
          "",
        )}</select></label><label>Invocación<select class="form-select" data-r10-type>${[
        ["", "Todas"],
        ["xyz", "Xyz"],
        ["fusion", "Fusión"],
        ["sincro", "Sincronía"],
        ["link", "Enlace"],
        ["sacrificio", "Sacrificio"],
      ]
        .map(
          ([v, l]) =>
            `<option value="${v}" ${s.type === v ? "selected" : ""}>${l}</option>`,
        )
        .join(
          "",
        )}</select></label>${difficulty.length ? `<label>Dificultad<select data-r10-difficulty><option value="">Todas</option>${difficulty.map((v) => `<option ${s.difficulty === v ? "selected" : ""}>${esc(v)}</option>`).join("")}</select></label>` : ""}</div><p>${rows.length} de ${d.combos.length} rutas · todas se incluyen por defecto</p>${rows.length ? `<label>Jugada<select class="form-select" data-r10-combo>${rows.map(({ c, i }) => `<option value="${i}" ${s.combo === i ? "selected" : ""}>${esc(c.titulo)}</option>`).join("")}</select></label><article class="r10-combo" data-r10-combo-id="${esc(c.id)}"><header>${badge(c.estado)}<h3>${esc(c.titulo)}</h3></header>${c.estado === "NO_EJECUTABLE" ? '<p class="r10-warning">Estudia por qué no funciona con esta lista. No se ofrece como jugada ejecutable.</p>' : ""}${field("Necesitas y estado inicial", c.para_empezar)}${list(v.requisitos_detallados_de_origen)}<nav class="r10-steps" aria-label="Pasos del combo">${c.pasos.map((_, i) => `<button class="btn ${i === s.step ? "btn-info" : "btn-outline-info"}" type="button" data-r10-step="${i}" aria-current="${i === s.step ? "step" : "false"}">${i + 1}</button>`).join("")}</nav><section class="r10-step" aria-live="polite"><span class="r10-badge">Paso ${s.step + 1} de ${c.pasos.length}</span><h4>${esc(c.pasos[s.step].replace(/^\d+[.)]\s*/, ""))}</h4>${tactical ? field("Acción", tactical.accion) + field("Carta", tactical.carta_mencionada_en_fuente) + field("Requisito", tactical.requisito) + field("Coste", tactical.coste) + field("Objetivo", tactical.objetivo) + field("Cambio de zonas", tactical.cambio_de_zonas) + field("Restricción", tactical.restriccion) + field("Cadena", tactical.cadena) : ""}<div class="r10-links"><button class="btn btn-outline-info" type="button" data-r10-step="${s.step - 1}" ${s.step === 0 ? "disabled" : ""}>Paso anterior</button><button class="btn btn-info" type="button" data-r10-step="${s.step + 1}" ${s.step === c.pasos.length - 1 ? "disabled" : ""}>Siguiente paso</button></div></section>${field("Resultado si se cumplen las condiciones", c.resultado_si_se_cumple)}${details("Si falla o te interrumpen", p(c.si_falla_o_te_interrumpen))}${details("Alternativa documentada", p(v.alternativa_documentada || c.alternativa))}${c.diagrama_svg ? details("Ver diagrama de la ruta", `<a href="${esc(record.combos[s.combo].diagram_url)}" target="_blank" rel="noopener"><img class="r10-diagram" src="${esc(record.combos[s.combo].diagram_url)}" alt="Diagrama de ${esc(c.titulo)}" loading="lazy"></a>`) : ""}${details("Fuentes y condiciones adicionales", list(c.extra_conditions_for_execution) + (c.official_sources || []).map(source).join(""))}</article>` : "<p>No hay rutas con estos filtros. Cambia la búsqueda o el estado.</p>"}`;
    }
    function threats() {
      const rows = d.estrategias_ante_amenazas || [];
      const x = rows[s.threat];
      return `<h3>Si te interrumpen</h3><label>Amenaza<select class="form-select" data-r10-threat>${rows.map((r, i) => `<option value="${i}" ${s.threat === i ? "selected" : ""}>${esc(r.amenaza)}</option>`).join("")}</select></label>${x ? `<article>${field("Qué hacer", x.decision_orientativa)}${list(x.cartas_para_evaluar_en_esta_lista)}</article>` : ""}<h4>Ramas documentadas por ruta</h4>${d.combos
        .filter((c) => c.si_falla_o_te_interrumpen)
        .map((c) =>
          details(c.titulo, p(c.si_falla_o_te_interrumpen) + routeButton(c.id)),
        )
        .join("")}`;
    }
    function turn() {
      return `<h3>Primero o segundo</h3>${phasePlans()}${details("Plan documentado al ir primero", list(d.plan_de_juego?.jugando_primero))}${details("Plan documentado al ir segundo", list(d.plan_de_juego?.jugando_segundo))}${details("Recursos y victoria", list(d.plan_de_juego?.gestion_de_recursos) + list(d.plan_de_juego?.condiciones_de_victoria))}`;
    }
    function risks() {
      return `<h3>Fortalezas y debilidades</h3>${details("Fortalezas de esta lista", list(d.plan_de_juego?.fortalezas))}${details("Riesgos y debilidades", list(d.plan_de_juego?.debilidades))}${details("Errores que debes evitar", list(d.plan_de_juego?.errores_a_evitar))}${["fortalezas", "debilidades", "bricks", "recursos", "recuperacion"].map((k) => details({ fortalezas: "Cómo aprovechar las fortalezas", debilidades: "Cómo prevenir los riesgos", bricks: "Manos problemáticas", recursos: "Administrar recursos", recuperacion: "Recuperarte" }[k], (d.estrategias_interactivas?.[k] || []).map((x) => p(x.explicacion_documentada) + (x.rutas_mencionadas_expresamente || []).map((id) => routeButton(id, id)).join("")).join(""))).join("")}`;
    }
    function training() {
      const hand = d.manos_para_practicar[s.hand];
      const options =
        hand.entradas_condicionadas || hand.primeras_opciones || [];
      return `<h3>Manos para practicar</h3><p>Muestras didácticas: no son partidas simuladas ni campos finales garantizados.</p><label>Mano<select class="form-select" data-r10-hand>${d.manos_para_practicar.map((h, i) => `<option value="${i}" ${i === s.hand ? "selected" : ""}>Mano ${h.numero}</option>`).join("")}</select></label><section class="r10-panel">${list(hand.cartas)}${field("Orden inicial", hand.orden_de_turno)}${options.map((o) => `<article>${field("Primera opción", o.jugada_inicial)}${field("Condiciones externas", o.condiciones_externas || o.condicion_adicional)}${field("Precaución", o.limite_documentado)}${routeButton(o.combo, o.combo)}</article>`).join("")}${list(hand.planes_documentados_de_origen)}${field("Skill en esta mano", hand.skill_reason || hand.motivo_condicional_de_skill)}${field("Turno 1", hand.skill_turno_1)}${field("Turno 2 con esa mano", hand.skill_turno_2)}${hand.skill_available_separately ? "<p>La Skill permanece fuera de la mano. Para un turno posterior también debes contabilizar los nuevos robos y el estado del campo.</p>" : ""}<button type="button" class="btn btn-outline-info" data-r10-calculator>Practicar en Calculador</button></section>${d.dato_de_apertura ? `<section><h3>Acceso a una carta</h3>${p(d.dato_de_apertura.carta)}<p>${d.dato_de_apertura.copias} copias · Main ${d.dato_de_apertura.cartas_main}</p><p>Mano de 5: ${(d.dato_de_apertura.probabilidad_al_menos_una_en_5 * 100).toFixed(2)}% · mano de 6: ${(d.dato_de_apertura.probabilidad_al_menos_una_en_6 * 100).toFixed(2)}%</p>${p(d.dato_de_apertura.explicacion)}</section>` : ""}${details(
        "Probabilidades por carta del Main",
        d.cartas
          .filter((c) => c.probabilidad_apertura_individual)
          .map(
            (c) =>
              `<article><h4>${esc(c.nombre)}</h4>${probability(c.probabilidad_apertura_individual)}</article>`,
          )
          .join(""),
      )}`;
    }
    function matchups() {
      const rows = d.enfrentamientos || [];
      return `<h3>Planes contra rivales documentados</h3>${rows.length ? `<p>Planes orientativos; no son resultados ni porcentajes de victoria.</p><label>Rival<select class="form-select" data-r10-rival>${rows.map((x, i) => `<option value="${i}" ${s.rival === i ? "selected" : ""}>${esc(x.rival)}</option>`).join("")}</select></label><article class="r10-panel"><h4>${esc(rows[s.rival].rival)}</h4>${p(rows[s.rival].plan)}</article>` : "<p>No hay enfrentamientos documentados en esta entrega para este deck.</p>"}`;
    }
    function rating() {
      const r = d.evaluacion_competitiva_justificada;
      return `<h3>Evaluación editorial</h3><p>Evaluación editorial provisional · pendiente de validación de juego. No modifica el ranking, Elo ni resultados.</p><p>Comparación: ${esc(r.universo_comparacion || d.formato)}</p><div class="r10-grid">${r.dimensiones.map((x) => `<article class="r10-option"><h4>${esc(x.criterio)}</h4><strong class="r10-score">${x.calificacion_1_10 ?? "Pendiente"} / 10</strong>${p(x.justificacion)}<div class="r10-links">${(x.rutas_de_respaldo || []).map((c) => routeButton(c.id, c.titulo || c.id)).join("")}</div></article>`).join("")}</div><section class="r10-panel"><h4>Media editorial: ${r.promedio_competitivo ?? "Pendiente"} / 10</h4>${p(r.clasificacion_tecnica)}<p>Media aritmética de cinco criterios con el mismo peso. No equivale al tier del metajuego ni a una tasa de victoria.</p></section>`;
    }
    function library() {
      const rows = d.apartados_del_prompt_maestro.filter((x) =>
        norm(
          x.titulo_prompt_maestro + " " + x.documentacion_existente.join(" "),
        ).includes(norm(s.query)),
      );
      return `<h3>Estudiar en profundidad</h3><label>Buscar tema<input class="form-control" type="search" data-r10-query value="${esc(s.query)}"></label><p>${rows.length} temas documentados</p>${rows.map((x) => details(x.titulo_prompt_maestro, list(x.documentacion_existente))).join("")}<a class="btn btn-outline-info" href="${esc(record.record_url)}" download>Descargar los datos de juego de este deck</a>`;
    }
    function draw() {
      const body = {
        intro,
        now,
        cards,
        combos,
        interrupt: threats,
        turn,
        risks,
        training,
        matchups,
        rating,
        library,
      }[s.tab]();
      host.innerHTML = `<section class="r10-guide"><header class="r10-hero"><span>${esc(d.formato)} · ${d.combos.length} rutas documentadas</span><h2>Aprende a jugar ${esc(d.nombre)}</h2><p>${esc(style.clasificacion_razonada)}</p></header><nav class="r10-navigation" aria-label="Temas de la guía">${nav.map(([id, label]) => `<button class="btn ${s.tab === id ? "btn-info" : "btn-outline-info"}" type="button" data-r10-tab="${id}" aria-pressed="${s.tab === id}">${label}</button>`).join("")}</nav><div class="r10-content" data-r10-view="${s.tab}">${body}</div></section>`;
    }
    function route(id) {
      const i = d.combos.findIndex((c) => c.id === id);
      if (i < 0) return;
      s.combo = i;
      s.step = 0;
      s.query = "";
      s.status = "";
      s.turn = "";
      s.type = "";
      s.difficulty = "";
      s.tab = "combos";
      draw();
    }
    host.addEventListener(
      "click",
      (e) => {
        const b = e.target.closest(
          "[data-r10-tab],[data-r10-scenario],[data-r10-route],[data-r10-none],[data-r10-card],[data-r10-page],[data-r10-step],[data-r10-calculator]",
        );
        if (!b) return;
        if (b.hasAttribute("data-r10-tab")) {
          s.tab = b.dataset.r10Tab;
          s.query = "";
          s.cardPage = 0;
        } else if (b.hasAttribute("data-r10-scenario")) {
          s.scenario = b.dataset.r10Scenario;
          s.none = false;
        } else if (b.hasAttribute("data-r10-none")) s.none = true;
        else if (b.hasAttribute("data-r10-route")) {
          route(b.dataset.r10Route);
          return;
        } else if (b.hasAttribute("data-r10-card"))
          s.card = Number(b.dataset.r10Card);
        else if (b.hasAttribute("data-r10-page"))
          s.cardPage += Number(b.dataset.r10Page);
        else if (b.hasAttribute("data-r10-step"))
          s.step = Number(b.dataset.r10Step);
        else if (b.hasAttribute("data-r10-calculator")) {
          root.dispatchEvent(
            new CustomEvent("ygo:r10-practice", {
              detail: {
                deckId: Number(deck.id),
                hand: d.manos_para_practicar[s.hand],
              },
            }),
          );
          return;
        }
        draw();
      },
      { signal: controller.signal },
    );
    host.addEventListener(
      "change",
      (e) => {
        for (const [attr, key] of [
          ["combo", "combo"],
          ["status", "status"],
          ["zone", "zone"],
          ["hand", "hand"],
          ["rival", "rival"],
          ["threat", "threat"],
          ["turn", "turn"],
          ["type", "type"],
          ["difficulty", "difficulty"],
        ])
          if (e.target.hasAttribute("data-r10-" + attr)) {
            s[key] = ["combo", "hand", "rival", "threat"].includes(key)
              ? Number(e.target.value)
              : e.target.value;
            s.step = 0;
            s.cardPage = 0;
            draw();
            break;
          }
      },
      { signal: controller.signal },
    );
    host.addEventListener(
      "input",
      (e) => {
        if (!e.target.matches("[data-r10-query]")) return;
        const caret = e.target.selectionStart;
        s.query = e.target.value;
        s.cardPage = 0;
        draw();
        const input = host.querySelector("[data-r10-query]");
        input?.focus({ preventScroll: true });
        input?.setSelectionRange(caret, caret);
      },
      { signal: controller.signal },
    );
    s.openRoute = route;
    draw();
  }
  function isCurrent(host, deck) {
    return (
      !!host &&
      instances.get(host)?.deckId === Number(deck.id) &&
      !!host.querySelector(".r10-guide")
    );
  }
  function decorate(deck, data) {
    const did = Number(deck.id);
    root.YgoDeckDeliveries.load(did)
      .then((record) => {
        if (!record?.curated_r10) return;
        if (
          Number(
            document.querySelector("[data-play-calculator]")?.dataset.r10Deck,
          ) !== did
        )
          return;
        const target = document.querySelector("[data-card-gallery]");
        if (target) {
          target.querySelector("[data-r10-decklist]")?.remove();
          const box = document.createElement("section");
          box.dataset.r10Decklist = String(did);
          box.innerHTML =
            skill(record) +
            `<p>Consulta las funciones y rutas de cada carta en Estilo y estrategia.</p>`;
          target.append(box);
        }
        const matchup = document.querySelector(".deck-matchup-section-title");
        if (matchup) {
          matchup.querySelector("[data-r10-matchups]")?.remove();
          const section = document.createElement("section");
          section.dataset.r10Matchups = String(did);
          section.className = "r10-guide";
          section.innerHTML =
            "<h3>Planes documentados de este deck</h3>" +
            (record.curated.enfrentamientos || [])
              .map((x) => details(x.rival, p(x.plan)))
              .join("");
          matchup.append(section);
        }
      })
      .catch(() => {});
  }
  root.YgoR10Guide = { mount, isCurrent, decorate, cardDetail };
})(window);
