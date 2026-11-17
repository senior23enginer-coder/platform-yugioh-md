(function (root) {
  "use strict";
  let indexPromise;
  const records = new Map();
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
  async function load(id) {
    id = Number(id);
    const index = await (indexPromise ||
      (indexPromise = fetch(
        "./assets/editorial/deck-deliveries.json?v=20261102-r10-128",
        { cache: "no-cache" },
      )
        .then((r) => {
          if (!r.ok) throw Error("No se pudo consultar la entrega vigente.");
          return r.json();
        })
        .catch((e) => {
          indexPromise = null;
          throw e;
        })));
    const row = index.decks[String(id)];
    if (!row) return null;
    if (!records.has(id))
      records.set(
        id,
        fetch(row.url, { cache: "no-cache" })
          .then((r) => {
            if (!r.ok)
              throw Error("No se pudo cargar la información de este deck.");
            return r.json();
          })
          .then((record) => {
            const sourceId = record.original_record?.deck?.deck_id;
            // Rush source IDs are editorial strings; deckId is the confirmed platform mapping.
            if (
              Number(record.deckId) !== id ||
              (typeof sourceId === "number" && sourceId !== id)
            )
              throw Error(
                "La entrega recibida no corresponde al deck seleccionado.",
              );
            return { ...record, record_url: row.url };
          })
          .catch((e) => {
            records.delete(id);
            throw e;
          }),
      );
    return records.get(id);
  }
  const labels = {
    route_id: "Identificador de la jugada",
    source_entry_id: "Referencia en el documento",
    title: "Título",
    initial_state: "Mano, campo y recursos iniciales",
    steps: "Pasos de la jugada",
    result: "Resultado esperado y condiciones",
    failure_branch: "Si te interrumpen o falta una pieza",
    status: "Estado de la información",
    executable_with_current_build: "Ejecutable con esta construcción",
    legacy_verified: "Verificación histórica",
    alt_text: "Descripción del diagrama",
    official_sources: "Fuentes oficiales",
    verified_effect_scope: "Efectos cotejados y límites",
    tactical_full_prompt_detail: "Decisiones y recursos por paso",
    source_scope_2026_11: "Alcance de las fuentes",
    publication_status: "Estado de publicación",
    publish_even_if_unverified: "Publicación con verificación pendiente",
    import_blocked_by_rule_verification:
      "Importación bloqueada por verificación",
    card_name: "Carta",
    copies: "Copias documentadas",
    section: "Sección de la lista",
    quantity: "Cantidad",
    related_verified_route_ids: "Jugadas relacionadas",
    individual_steps_documented: "Pasos documentados",
    eighteen_obligatory_fields: "Cómo utilizar esta carta",
    official_reference_urls: "Referencias oficiales",
    information_level: "Nivel de documentación",
    official_card_id: "Identificador de Konami",
    official_card_name: "Nombre oficial",
    individual_official_source: "Texto oficial de la carta",
    official_effect_indicators: "Mecánicas identificadas",
    records: "Registros completos",
    chapter: "Tema",
    number: "Número",
    preparation_status: "Estado de preparación",
    content_points: "Explicación",
    claims_are_not_automatically_rules_verified:
      "Requiere contrastar las reglas",
    scenario: "Ejemplo de mano",
    turn_order: "Orden de turno",
    cards: "Cartas",
    analysis_status: "Alcance del análisis",
    classification: "Clasificación de la mano",
    legal_combo_line_id: "Jugada vinculada",
    precise_next_play: "Siguiente jugada",
    starter_guide: "Cómo comenzar con esta mano",
    visible_starters: "Iniciadores presentes",
    required_cards_visible_in_hand: "Cartas necesarias presentes en mano",
    opening_action_to_assess: "Primera acción que puedes evaluar",
    unverified_beyond_hand: "Condiciones que aún debes comprobar",
    route_initial_state: "Estado inicial requerido",
    initial_reading: "Lectura de la mano",
    do_not_conclude: "Lo que esta muestra no demuestra",
    simulation_performed: "Simulación realizada",
    confidence: "Confianza y límites",
    named_card: "Carta cuyo acceso se calcula",
    N: "Cartas del Main Deck",
    note: "Aclaración",
    at_least_one_in_5: "Probabilidad de al menos una copia en 5 cartas",
    formula_5: "Fórmula para 5 cartas",
    at_least_one_in_6: "Probabilidad de al menos una copia en 6 cartas",
    formula_6: "Fórmula para 6 cartas",
    deck: "Información del deck",
    format: "Formato",
    deck_name: "Nombre del deck",
    deck_id: "ID del deck",
    inventory: "Lista documentada",
    inventory_counts: "Cantidad por sección",
    card_index: "Índice de cartas",
    routes: "Jugadas documentadas",
    route_count: "Número de jugadas",
    direct_matchups: "Planes contra rivales documentados",
    catalog_matchups: "Planes ante los rivales del catálogo",
    study_36: "Estudio completo del deck",
    opening_hands_ten_reproducible_draws: "Manos de ejemplo",
    opening_access_probability_example: "Probabilidades de apertura",
    prompt_36_audit: "Cobertura de los temas documentados",
    editorial_entry_index: "Referencias del contenido",
    publication: "Procedencia y publicación",
    source_order_note: "Orden del documento original",
    effect: "Efecto",
    condition: "Condición",
    cost: "Coste",
    costs: "Costes",
    target: "Objetivo",
    timing: "Cuándo se utiliza",
    restrictions: "Restricciones",
    restriction: "Restricción",
    response: "Respuesta",
    recovery: "Continuación posible",
    consequence: "Consecuencia",
    objective: "Objetivo de la jugada",
    preconditions: "Requisitos previos",
    extra_restriction: "Restricciones del Extra Deck",
    active_restrictions: "Restricciones activas",
    movement: "Movimiento de la carta",
    from: "Zona de origen",
    to: "Zona de destino",
    hand_change: "Cambio en la mano",
    end_state: "Estado final",
    opponent_interruption: "Interrupción del rival",
    error_to_avoid: "Error que debes evitar",
    sources: "Fuentes",
    source: "Fuente",
    checked_scope: "Qué se comprobó",
    verified_on: "Fecha de comprobación",
    main: "Main Deck",
    extra: "Extra Deck",
    side: "Side Deck",
    reserve: "Reserva de cartas",
  };
  Object.assign(labels, {
    scope: "Alcance del análisis",
    source_method: "Método de consulta de fuentes",
    rules_engine_simulated: "Simulación con motor de reglas",
    all_36_prompt_sections_complete: "Comprobación integral de los temas",
    minimum_opening_hand: "Cartas mínimas en mano",
    additional_required_resources: "Otros recursos necesarios",
    steps_detailed: "Costes, efectos y restricciones de cada paso",
    field: "Campo",
    strength: "Ventaja de la jugada",
    weakness: "Riesgo de la jugada",
    official_card_sources_associated: "Fuentes oficiales asociadas",
    source_identity_check: "Comprobación de identidad y fuentes",
    game_state_execution_certified: "Ejecución del estado de juego certificada",
    card: "Carta que utilizas",
    chain: "Cadena de efectos",
    official_atk: "ATK oficial",
    official_def: "DEF oficial",
    official_attribute: "Atributo oficial",
    official_classification: "Tipo oficial",
    official_level_or_rank: "Nivel o Rango oficial",
    deck_predicates: "Requisitos de construcción",
    deck_predicate_verification: "Comprobación de los requisitos",
    extra_conditions_for_execution: "Condiciones adicionales para ejecutar",
    extra_deck_materials_alert: "Materiales requeridos en el Extra Deck",
    source_provenance: "Procedencia del contenido",
    skills: "Habilidades",
    skill_relation: "Relación con la habilidad",
    skill_id: "Identificador de la habilidad",
    rule_engine_verified: "Comprobado con motor de reglas",
    rules_verified: "Reglas verificadas",
    extra_status: "Estado del Extra Deck",
    official_sources_associated: "Fuentes oficiales asociadas",
    source_identity_check: "Comprobación de identidad de las fuentes",
    evidence_gaps: "Evidencia pendiente",
    limitations_unverified: "Límites y verificaciones pendientes",
    validation_method: "Método de comprobación",
    physical_missing_in_extra: "Cartas ausentes del Extra Deck",
    skill_steps: "Pasos que utilizan la habilidad",
    steps_detailed: "Decisiones por paso",
    source_verified: "Fuente comprobada",
    opponent: "Rival",
    plan_text: "Tu plan para este rival",
    turn_order: "Orden de turno",
    skill_not_in_draw_pool: "La habilidad queda fuera del conjunto de robo",
  });
  const label = (s) => {
    s = String(s).replace(/_v\d+$/, "");
    return (
      labels[s] ||
      s
        .replace(/^\d+_/, "")
        .replace(/_/g, " ")
        .replace(/^./, (c) => c.toUpperCase())
    );
  };
  function diagram(svg, title = "Diagrama documental") {
    return typeof svg === "string" && svg.trim().startsWith("<svg")
      ? `<img class="learn-delivery-diagram" src="data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}" alt="${esc(title)}" loading="lazy">`
      : "";
  }
  function structured(v, depth = 0) {
    if (v === null || v === undefined)
      return '<span class="learn-missing">No documentado</span>';
    if (typeof v === "boolean") return esc(v ? "Sí" : "No");
    if (typeof v !== "object") {
      const s = String(v);
      return /^https:\/\//.test(s) && !/[\s<>]/.test(s)
        ? `<a href="${esc(s)}" target="_blank" rel="noopener noreferrer">Consultar fuente</a>`
        : `<span class="learn-delivery-text">${esc(s)}</span>`;
    }
    if (Array.isArray(v)) {
      if (!v.length)
        return '<span class="learn-missing">Sin entradas registradas</span>';
      return `<ul class="learn-delivery-list">${v.map((x, i) => `<li>${x && typeof x === "object" ? `<details><summary>${esc(x.title || x.nombre || x.card_name || x.card || x.combo_id || x.route_id || "Registro " + (i + 1))}</summary>${structured(x, depth + 1)}</details>` : structured(x, depth + 1)}</li>`).join("")}</ul>`;
    }
    return `<dl class="learn-delivery-fields">${Object.entries(v)
      .map(
        ([k, x]) =>
          `<div class="learn-delivery-field"><dt>${esc(label(k))}</dt><dd>${k === "svg_inline" || k === "diagram_svg" ? `<details><summary>Ver diagrama</summary>${diagram(x)}</details>` : k === "mermaid_flowchart" ? `<details><summary>Ver código del diagrama original</summary><pre>${esc(x)}</pre></details>` : structured(x, depth + 1)}</dd></div>`,
      )
      .join("")}</dl>`;
  }
  Object.assign(labels, {
    main_deck: "Main Deck",
    extra_deck: "Extra Deck",
    side_deck: "Side Deck",
    reserva: "Reserva de cartas",
    main_count: "Total de cartas del Main Deck",
    main_unique_names: "Nombres distintos en el Main Deck",
    extra_status: "Estado del Extra Deck",
    source_images: "Imágenes recibidas",
    linked_photographs: "Fotografías vinculadas",
    individual_card_profiles: "Fichas de las cartas",
    perfiles_18_campos: "Fichas individuales de 18 campos",
    capitulos_36: "Los 36 temas de estudio",
    chapters: "Temas de estudio",
    manos_10: "Diez manos de ejemplo",
    combos: "Jugadas documentadas",
    probabilidades: "Probabilidades y sus supuestos",
    skills: "Habilidades",
    skill_rules_layer: "Reglas de la habilidad",
    skill_not_in_draw_pool: "Habilidad separada del conjunto de robo",
    skill_status: "Estado de la habilidad",
    notes: "Notas del documento",
    change_history: "Historial del documento",
    version_note: "Nota de esta versión",
    nonblocking_publication_policy: "Criterios de publicación del documento",
    source_index: "Índice de fuentes",
    consolidated_metadata: "Contexto del análisis consolidado",
    audit_metadata: "Criterios y alcance de las comprobaciones",
    index_metadata: "Criterios del índice de fuentes",
    historical_audit: "Comprobación previa · referencia histórica",
    official_rule_references: "Fuentes oficiales de reglas",
    source_traceability: "Enlace a la fuente individual",
    required_work: "Qué falta revisar",
    current_status: "Estado declarado en la fuente",
    source_scope: "Alcance de la fuente",
    official_source_verified: "Fuente oficial localizada",
    official_individual_card_text_certified: "Texto individual certificado",
    eighteen_fields_present: "Los 18 campos están presentes",
    exact_effect_text_in_card_index: "Texto exacto del efecto en el índice",
    documentary_status: "Estado de la ruta documentada",
    official_sources_listed: "Fuentes oficiales indicadas",
    fully_simulated_and_certified: "Ruta simulada y certificada íntegramente",
    remaining: "Comprobaciones pendientes",
    chapters_present: "Temas presentes",
    hands_present: "Manos presentes",
    profiles_present: "Fichas presentes",
    routes_present: "Rutas presentes",
    routes_fully_certified: "Rutas certificadas íntegramente",
    legal_certification_complete: "Certificación reglamentaria integral",
    structure_complete: "Estructura documental completa",
    counts: "Cantidades por sección",
    official_source_pct: "Porcentaje con fuente enlazada",
    official_card_text_pct: "Porcentaje de texto certificado",
    certified_route_pct: "Porcentaje de rutas certificadas",
    documentary_integrity_issues: "Observaciones de integridad documental",
    skill_global_predicate_verified:
      "Requisitos globales de la habilidad comprobados",
    chapter_coverage_pass: "Cobertura de los temas",
    skill_id: "Identificador de la habilidad",
    skill_name: "Nombre de la habilidad",
    name: "Nombre",
    japanese_name: "Nombre japonés oficial",
    level: "Nivel",
    kind: "Clase de carta",
    type: "Tipo",
    attribute: "Atributo",
    atk: "ATK",
    def: "DEF",
  });
  root.YgoDeckDeliveries = { load, structured, diagram, label };
})(window);
