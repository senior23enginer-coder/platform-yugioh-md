(function (root) {
  "use strict";
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
  const list = (v) => {
    if (Array.isArray(v)) return v;
    try {
      return JSON.parse(v || "[]");
    } catch (_) {
      return String(v || "")
        .split("\n")
        .filter(Boolean);
    }
  };
  const normal = (v) =>
    String(v || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  let active = null,
    sourcePromise = null;
  const sourceUrl =
    "./assets/editorial/station_x_perfiles_67_decks.txt?v=20261030-profiles-119";
  const importedAnnexes = /* IMPORTED_ANNEX_MANIFEST */ {
    684: [
      {
        id: "profile",
        label: "Perfil maestro completo",
        icon: "bi-journal-text",
        hint: "Anexo H del documento aportado.",
        document: true,
        topics: [
          {
            id: "H-intro",
            label: "ANEXO H — PERFIL MAESTRO: CYBER DRAGON / CYBERDARK — TCG",
            start: 1667,
            end: 1672,
          },
          {
            id: "H1",
            label: "H1. MAIN DECK — 42 CARTAS (DESGLOSE AUDITADO)",
            start: 1673,
            end: 1712,
          },
          {
            id: "H2",
            label: "H2. EXTRA DECK — 15 CARTAS",
            start: 1713,
            end: 1731,
          },
          {
            id: "H3",
            label: "H3. RESERVA COMPLETA — 24 CARTAS, SIN REDUCCIÓN",
            start: 1732,
            end: 1736,
          },
          {
            id: "H4",
            label: "H4. IDENTIDAD / CONDICIÓN DE VICTORIA / ESTILO",
            start: 1737,
            end: 1742,
          },
          {
            id: "H5",
            label: "H5. MAPA DE FUNCIONES INDIVIDUALES Y SINERGIAS",
            start: 1743,
            end: 1760,
          },
          {
            id: "H6",
            label: "H6. EL EXTRA DECK: ACCESIBILIDAD Y COSTE DE OPORTUNIDAD",
            start: 1761,
            end: 1770,
          },
          {
            id: "H7",
            label: "H7. NUEVE CAMPOS DE «PLAN DE JUEGO» PARA STATION X",
            start: 1771,
            end: 1781,
          },
          {
            id: "H8",
            label: "H8. COMBOS CONDICIONADOS Y LÍNEAS DE ENTRENAMIENTO",
            start: 1782,
            end: 1789,
          },
          {
            id: "H9",
            label: "H9. CHOKE POINTS / CONTINGENCIAS",
            start: 1790,
            end: 1798,
          },
          {
            id: "H10",
            label: "H10. FORTALEZAS / DEBILIDADES / CARTAS MUERTAS",
            start: 1799,
            end: 1803,
          },
          {
            id: "H11",
            label: "H11. RESERVA: VARIANTES E INTERCAMBIOS CON IGUAL NÚMERO",
            start: 1804,
            end: 1814,
          },
          {
            id: "H12",
            label:
              "H12. ESTIMACIONES MATEMÁTICAS CONTROLADAS (42 CARTAS, 5 INICIALES)",
            start: 1815,
            end: 1821,
          },
          {
            id: "H13",
            label: "H13. MATRIZ DE ENTRENAMIENTO Y MATCHUPS (HIPÓTESIS)",
            start: 1822,
            end: 1833,
          },
        ],
      },
      {
        id: "technical",
        label: "Auditoría técnica",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          {
            id: "K0",
            label:
              "K0. MATEMÁTICA EXACTA: PRESENCIA DE DOS GRUPOS DISTINTOS EN MANO INICIAL",
            start: 1999,
            end: 2012,
          },
          {
            id: "K4",
            label:
              "K4. CYBER DRAGON / CYBERDARK — VALIDACIÓN DE FUSIONES Y COLUMNA",
            start: 2041,
            end: 2050,
          },
          {
            id: "K6",
            label:
              "K6. AUDITORÍA DE 15 SLOTS DEL EXTRA: FORMATO DE RESULTADO POR CARTA",
            start: 2060,
            end: 2062,
          },
          {
            id: "K7",
            label: "K7. INDICADORES OBSERVADOS Y PRUEBAS REPRODUCIBLES",
            start: 2063,
            end: 2068,
          },
          {
            id: "K8",
            label: "K8. HALLAZGOS, LIMITACIONES Y SIGUIENTE VERIFICACIÓN",
            start: 2069,
            end: 2077,
          },
        ],
      },
      {
        id: "rotations",
        label: "Rotaciones y rivales",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          { id: "L0", label: "L0. REGLAS DE LECTURA", start: 2083, end: 2087 },
          {
            id: "L4",
            label: "L4. ROTACIONES DE CYBER DRAGON / CYBERDARK — 42/15/24",
            start: 2116,
            end: 2125,
          },
          {
            id: "L6",
            label: "L6. MATRIZ TCG — DECK OPONENTE POR DECK PROPIO",
            start: 2137,
            end: 2253,
          },
          {
            id: "L7",
            label:
              "L7. REGLA PARA USAR LA MATRIZ COMO ENTRENAMIENTO (NO SOLO LEERLA)",
            start: 2254,
            end: 2259,
          },
        ],
      },
    ],
    650: [
      {
        id: "profile",
        label: "Perfil maestro completo",
        icon: "bi-journal-text",
        hint: "Anexo F del documento aportado.",
        document: true,
        topics: [
          {
            id: "F-intro",
            label: "ANEXO F — PERFIL MAESTRO COMPLETO: ELFNOTE — TCG",
            start: 1216,
            end: 1220,
          },
          {
            id: "F1",
            label: "F1. MAIN DECK — 40 CARTAS",
            start: 1221,
            end: 1250,
          },
          {
            id: "F2",
            label: "F2. EXTRA DECK — 15 CARTAS",
            start: 1251,
            end: 1269,
          },
          {
            id: "F3",
            label: "F3. SIDE DECK — 15 CARTAS",
            start: 1270,
            end: 1281,
          },
          {
            id: "F4",
            label: "F4. IDENTIDAD: CÓMO FUNCIONA ESTA VERSIÓN",
            start: 1282,
            end: 1291,
          },
          {
            id: "F5",
            label: "F5. FUNCIONES TÉCNICAS DEL NÚCLEO",
            start: 1292,
            end: 1305,
          },
          {
            id: "F6",
            label: "F6. LOS NUEVE CAMPOS DEL FORMULARIO «PLAN DE JUEGO»",
            start: 1306,
            end: 1334,
          },
          {
            id: "F7",
            label: "F7. COMBOS Y JUGADAS PASO A PASO, CONDICIONADOS",
            start: 1335,
            end: 1375,
          },
          {
            id: "F8",
            label: "F8. RULINGS QUE DEBEN QUEDAR EN EL PERFIL",
            start: 1376,
            end: 1389,
          },
          {
            id: "F9",
            label: "F9. VENTAJAS, DEBILIDADES Y CARTAS MUERTAS",
            start: 1390,
            end: 1415,
          },
          {
            id: "F10",
            label: "F10. SUGERENCIAS DE CAMBIO Y VARIANTES — NO EJECUTADAS",
            start: 1416,
            end: 1424,
          },
          {
            id: "F11",
            label: "F11. SIMULACIONES Y ENTRENAMIENTO MASTER",
            start: 1433,
            end: 1442,
          },
          {
            id: "F12",
            label: "F12. EVALUACIÓN EDITORIAL Y CONFIANZA",
            start: 1443,
            end: 1446,
          },
          {
            id: "F13",
            label: "F13. FUENTES Y ACTUALIZACIÓN",
            start: 1447,
            end: 1459,
          },
        ],
      },
      {
        id: "advanced",
        label: "Evaluación avanzada",
        icon: "bi-clipboard-data",
        hint: "Análisis específico y pruebas documentadas.",
        document: true,
        topics: [
          {
            id: "G0",
            label: "G0. CÓMO INTERPRETAR LOS DATOS",
            start: 1465,
            end: 1471,
          },
          {
            id: "G3",
            label:
              "G3. ELFNOTE — EVALUACIÓN AVANZADA ESPECÍFICA (MAIN 40 / EXTRA 15 / SIDE 15)",
            start: 1578,
            end: 1579,
          },
          {
            id: "G3.1",
            label: "G3.1 APERTURAS Y CONSISTENCIA",
            start: 1580,
            end: 1586,
          },
          {
            id: "G3.2",
            label: "G3.2 PRIMER TURNO: EL MAPA DE LA ZONA CENTRAL",
            start: 1587,
            end: 1593,
          },
          {
            id: "G3.3",
            label: "G3.3 SEGUNDO TURNO Y RECUPERACIÓN",
            start: 1594,
            end: 1599,
          },
          {
            id: "G3.4",
            label: "G3.4 CHOKE POINTS EN AMBOS SENTIDOS",
            start: 1600,
            end: 1607,
          },
          {
            id: "G3.5",
            label: "G3.5 AUDITORÍA DEL EXTRA CON LOS 15 SLOTS EXACTOS",
            start: 1608,
            end: 1614,
          },
          {
            id: "G3.6",
            label:
              "G3.6 SIDE DE 15: PAQUETES ENTRA/SALE IGUALES Y CONDICIONALES",
            start: 1615,
            end: 1624,
          },
          {
            id: "G3.7",
            label: "G3.7 MATCHUPS EDITORIALES Y MEDICIÓN",
            start: 1625,
            end: 1631,
          },
          {
            id: "G3.8",
            label: "G3.8 TRES MANOS DE ENTRENAMIENTO",
            start: 1632,
            end: 1636,
          },
          {
            id: "G4",
            label: "G4. MATRIZ DE PRUEBAS REPRODUCIBLES PARA LOS TRES DECKS",
            start: 1637,
            end: 1659,
          },
          {
            id: "G5",
            label: "G5. REGLA FIJA PARA LOS FUTUROS DECKS DE STATION X",
            start: 1660,
            end: 1666,
          },
        ],
      },
      {
        id: "technical",
        label: "Auditoría técnica",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          {
            id: "K0",
            label:
              "K0. MATEMÁTICA EXACTA: PRESENCIA DE DOS GRUPOS DISTINTOS EN MANO INICIAL",
            start: 1999,
            end: 2012,
          },
          {
            id: "K3",
            label: "K3. ELFNOTE — VALIDACIÓN DE ZONA, NIVELES Y MOMENTO",
            start: 2032,
            end: 2040,
          },
          {
            id: "K6",
            label:
              "K6. AUDITORÍA DE 15 SLOTS DEL EXTRA: FORMATO DE RESULTADO POR CARTA",
            start: 2060,
            end: 2062,
          },
          {
            id: "K7",
            label: "K7. INDICADORES OBSERVADOS Y PRUEBAS REPRODUCIBLES",
            start: 2063,
            end: 2068,
          },
          {
            id: "K8",
            label: "K8. HALLAZGOS, LIMITACIONES Y SIGUIENTE VERIFICACIÓN",
            start: 2069,
            end: 2077,
          },
        ],
      },
      {
        id: "rotations",
        label: "Rotaciones y rivales",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          { id: "L0", label: "L0. REGLAS DE LECTURA", start: 2083, end: 2087 },
          {
            id: "L3",
            label: "L3. ROTACIONES DE ELFNOTE — 40/15/15",
            start: 2106,
            end: 2115,
          },
          {
            id: "L6",
            label: "L6. MATRIZ TCG — DECK OPONENTE POR DECK PROPIO",
            start: 2137,
            end: 2253,
          },
          {
            id: "L7",
            label:
              "L7. REGLA PARA USAR LA MATRIZ COMO ENTRENAMIENTO (NO SOLO LEERLA)",
            start: 2254,
            end: 2259,
          },
        ],
      },
    ],
    670: [
      {
        id: "profile",
        label: "Perfil maestro completo",
        icon: "bi-journal-text",
        hint: "Anexo E del documento aportado.",
        document: true,
        topics: [
          {
            id: "E-intro",
            label: "ANEXO E — PERFIL MAESTRO COMPLETO: INFERNOBLE (TCG)",
            start: 925,
            end: 930,
          },
          {
            id: "E1",
            label: "E1. TRANSCRIPCIÓN CONSOLIDADA — MAIN DECK 42",
            start: 931,
            end: 967,
          },
          { id: "E2", label: "E2. EXTRA DECK 15", start: 968, end: 982 },
          {
            id: "E3",
            label: "E3. SIDE DECK / RESERVA 12",
            start: 983,
            end: 992,
          },
          {
            id: "E4",
            label: "E4. IDENTIDAD Y ARQUITECTURA DEL DECK",
            start: 993,
            end: 1003,
          },
          {
            id: "E5",
            label: "E5. PAPEL DE LAS CARTAS PRINCIPALES",
            start: 1004,
            end: 1019,
          },
          {
            id: "E6",
            label: "E6. EXTRA DECK: FUNCIÓN REAL Y CONDICIONES",
            start: 1020,
            end: 1033,
          },
          {
            id: "E7",
            label: "E7. PLAN DE JUEGO — NUEVE CAMPOS LISTOS PARA EL FORMULARIO",
            start: 1034,
            end: 1062,
          },
          {
            id: "E8",
            label:
              "E8. RUTAS, COMBOS Y CONDICIONES — NO PROMETER CAMPOS UNIVERSALES",
            start: 1063,
            end: 1100,
          },
          {
            id: "E9",
            label: "E9. APERTURA, TOMA DE DECISIONES Y CHOKE POINTS",
            start: 1101,
            end: 1109,
          },
          {
            id: "E10",
            label: "E10. FORTALEZAS Y DEBILIDADES DE ESTA LISTA",
            start: 1110,
            end: 1127,
          },
          {
            id: "E11",
            label: "E11. AUDITORÍA DE CARTAS MUERTAS Y CANDIDATOS A REVISIÓN",
            start: 1128,
            end: 1143,
          },
          {
            id: "E12",
            label: "E12. SUGERENCIAS DE CAMBIO — NO EJECUTADAS",
            start: 1144,
            end: 1151,
          },
          {
            id: "E13",
            label:
              "E13. VARIANTES CON LAS 12 CARTAS DE SIDE/RESERVA (ENSAYOS INDEPENDIENTES)",
            start: 1152,
            end: 1152,
          },
          {
            id: "E14",
            label: "E14. ERRORES DE RULING Y PILOTAJE QUE MÁS CUESTAN",
            start: 1173,
            end: 1186,
          },
          {
            id: "E15",
            label: "E15. EVALUACIÓN EDITORIAL Y DATOS OBSERVADOS",
            start: 1187,
            end: 1191,
          },
          {
            id: "E16",
            label: "E16. PRUEBAS DE ENTRENAMIENTO 'MASTER'",
            start: 1192,
            end: 1200,
          },
          {
            id: "E17",
            label: "E17. FUENTES PRIMARIAS Y ACTUALIZACIÓN",
            start: 1201,
            end: 1215,
          },
        ],
      },
      {
        id: "advanced",
        label: "Evaluación avanzada",
        icon: "bi-clipboard-data",
        hint: "Análisis específico y pruebas documentadas.",
        document: true,
        topics: [
          {
            id: "G0",
            label: "G0. CÓMO INTERPRETAR LOS DATOS",
            start: 1465,
            end: 1471,
          },
          {
            id: "G2",
            label:
              "G2. INFERNOBLE — EVALUACIÓN AVANZADA ESPECÍFICA (MAIN 42 / EXTRA 15 / SIDE/RESERVA 12)",
            start: 1524,
            end: 1525,
          },
          {
            id: "G2.1",
            label: "G2.1 APERTURAS Y CONSISTENCIA",
            start: 1526,
            end: 1531,
          },
          {
            id: "G2.2",
            label: "G2.2 PRIMER TURNO: OBJETIVO EN CAPAS",
            start: 1532,
            end: 1538,
          },
          {
            id: "G2.3",
            label: "G2.3 SEGUNDO TURNO Y RECUPERACIÓN",
            start: 1539,
            end: 1544,
          },
          {
            id: "G2.4",
            label:
              "G2.4 INTERRUPCIONES Y RECURSOS QUE NO SE PUEDEN DESPERDICIAR",
            start: 1545,
            end: 1552,
          },
          {
            id: "G2.5",
            label: "G2.5 HERRAMIENTAS DEL EXTRA: USABILIDAD REAL",
            start: 1553,
            end: 1559,
          },
          {
            id: "G2.6",
            label: "G2.6 SIDE/RESERVA 12: PAQUETES DE ENTRADA/SALIDA IGUALADOS",
            start: 1560,
            end: 1566,
          },
          {
            id: "G2.7",
            label: "G2.7 MATCHUPS Y PRUEBAS",
            start: 1567,
            end: 1572,
          },
          {
            id: "G2.8",
            label: "G2.8 TRES MANOS DE ENTRENAMIENTO",
            start: 1573,
            end: 1577,
          },
          {
            id: "G4",
            label: "G4. MATRIZ DE PRUEBAS REPRODUCIBLES PARA LOS TRES DECKS",
            start: 1637,
            end: 1659,
          },
          {
            id: "G5",
            label: "G5. REGLA FIJA PARA LOS FUTUROS DECKS DE STATION X",
            start: 1660,
            end: 1666,
          },
        ],
      },
      {
        id: "technical",
        label: "Auditoría técnica",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          {
            id: "K0",
            label:
              "K0. MATEMÁTICA EXACTA: PRESENCIA DE DOS GRUPOS DISTINTOS EN MANO INICIAL",
            start: 1999,
            end: 2012,
          },
          {
            id: "K2",
            label: "K2. INFERNOBLE — VALIDACIÓN DE RECURSOS Y RESTRICCIONES",
            start: 2023,
            end: 2031,
          },
          {
            id: "K6",
            label:
              "K6. AUDITORÍA DE 15 SLOTS DEL EXTRA: FORMATO DE RESULTADO POR CARTA",
            start: 2060,
            end: 2062,
          },
          {
            id: "K7",
            label: "K7. INDICADORES OBSERVADOS Y PRUEBAS REPRODUCIBLES",
            start: 2063,
            end: 2068,
          },
          {
            id: "K8",
            label: "K8. HALLAZGOS, LIMITACIONES Y SIGUIENTE VERIFICACIÓN",
            start: 2069,
            end: 2077,
          },
        ],
      },
      {
        id: "rotations",
        label: "Rotaciones y rivales",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          { id: "L0", label: "L0. REGLAS DE LECTURA", start: 2083, end: 2087 },
          {
            id: "L2",
            label: "L2. ROTACIONES DE INFERNOBLE — 42/15/12",
            start: 2097,
            end: 2105,
          },
          {
            id: "L6",
            label: "L6. MATRIZ TCG — DECK OPONENTE POR DECK PROPIO",
            start: 2137,
            end: 2253,
          },
          {
            id: "L7",
            label:
              "L7. REGLA PARA USAR LA MATRIZ COMO ENTRENAMIENTO (NO SOLO LEERLA)",
            start: 2254,
            end: 2259,
          },
        ],
      },
    ],
    665: [
      {
        id: "profile",
        label: "Perfil detallado",
        icon: "bi-journal-text",
        hint: "Anexo A del documento aportado.",
        document: true,
        topics: [
          {
            id: "A-intro",
            label: "ANEXO A — PERFIL COMPLETO VALIDADO: TCG #28 KASHTIRA PURE",
            start: 391,
            end: 398,
          },
          {
            id: "A1",
            label: "A1. MAIN DECK — EXACTAMENTE 42 CARTAS",
            start: 399,
            end: 431,
          },
          {
            id: "A2",
            label: "A2. EXTRA DECK — EXACTAMENTE 15 CARTAS",
            start: 432,
            end: 452,
          },
          {
            id: "A3",
            label: "A3. RESERVA PARA SIDE DECK — EXACTAMENTE 19 CARTAS",
            start: 453,
            end: 479,
          },
          {
            id: "A4",
            label: "A4. CLASIFICACIÓN DE ESTILO — DERIVADA DE LAS 42+15 CARTAS",
            start: 480,
            end: 489,
          },
          {
            id: "A5",
            label: "A5. MOTORES Y DISTRIBUCIÓN",
            start: 490,
            end: 496,
          },
          {
            id: "A6",
            label: "A6. PLAN PRINCIPAL Y LÍNEAS REPRESENTATIVAS",
            start: 497,
            end: 519,
          },
          {
            id: "A7",
            label: "A7. APERTURA, JUEGO MEDIO Y JUEGO TARDÍO",
            start: 520,
            end: 524,
          },
          { id: "A8", label: "A8. FORTALEZAS CONCRETAS", start: 525, end: 532 },
          {
            id: "A9",
            label: "A9. DEBILIDADES Y PUNTOS DE INTERRUPCIÓN",
            start: 533,
            end: 542,
          },
          {
            id: "A10",
            label: "A10. PRIMERO VS. SEGUNDO",
            start: 543,
            end: 549,
          },
          {
            id: "A11",
            label: "A11. CONSISTENCIA TEÓRICA Y LO QUE NO SE PUEDE INFERIR",
            start: 550,
            end: 557,
          },
          {
            id: "A12",
            label:
              "A12. EVALUACIÓN EDITORIAL — SIN CONFUNDIRLA CON DESEMPEÑO OBSERVADO",
            start: 558,
            end: 567,
          },
          {
            id: "A13",
            label: "A13. HIPÓTESIS DE PRUEBA Y MÉTRICAS PARA STATION X",
            start: 568,
            end: 572,
          },
          {
            id: "A14",
            label: "A14. DECISIONES EDITORIALES Y PENDIENTES",
            start: 573,
            end: 582,
          },
        ],
      },
      {
        id: "manual",
        label: "Manual de juego",
        icon: "bi-journal-text",
        hint: "Anexo B del documento aportado.",
        document: true,
        topics: [
          {
            id: "B-intro",
            label:
              "ANEXO B — MANUAL AVANZADO DE JUEGO: KASHTIRA PURE (42/15/19)",
            start: 583,
            end: 586,
          },
          {
            id: "B1",
            label: "B1. LA IDEA QUE DEFINE EL DECK",
            start: 587,
            end: 591,
          },
          {
            id: "B2",
            label: "B2. CARTAS DEL NÚCLEO: QUÉ BUSCAR Y POR QUÉ",
            start: 592,
            end: 604,
          },
          {
            id: "B3",
            label: "B3. OBJETIVOS DE BÚSQUEDA: ÁRBOL DE DECISIÓN",
            start: 605,
            end: 612,
          },
          {
            id: "B4",
            label:
              "B4. COMBO 1 — UNICORNIO + THEOSIS Y MOTOR COMPLETO (LÍNEA ILUSTRATIVA)",
            start: 613,
            end: 626,
          },
          {
            id: "B5",
            label: "B5. COMBO 2 — FENRIR + RISEHEART, SIN BUSCADOR ADICIONAL",
            start: 627,
            end: 637,
          },
          {
            id: "B6",
            label:
              "B6. COMBO 3 — RISEHEART DESTIERRA THEOSIS EN VEZ DE BIG BANG",
            start: 638,
            end: 645,
          },
          {
            id: "B7",
            label: "B7. COMBO 4 — PRESIÓN EN STANDBY DEL OPONENTE",
            start: 646,
            end: 652,
          },
          {
            id: "B8",
            label:
              "B8. COMBO 5 — REMOCIÓN CON ARISE-HEART Y CICLO DE MATERIALES",
            start: 653,
            end: 659,
          },
          {
            id: "B9",
            label: "B9. RULINGS Y MALENTENDIDOS QUE DECIDEN DUELOS",
            start: 660,
            end: 676,
          },
          {
            id: "B10",
            label: "B10. TURNOS DE EJEMPLO COMENTADOS",
            start: 677,
            end: 686,
          },
          {
            id: "B11",
            label: "B11. CÓMO SEGUIR EL COMBATE: MATRIZ DE DECISIÓN",
            start: 687,
            end: 695,
          },
          {
            id: "B12",
            label:
              "B12. ANALIZAR Y EXPLOTAR MATCHUPS (HIPÓTESIS, NO PORCENTAJES)",
            start: 696,
            end: 704,
          },
          {
            id: "B13",
            label:
              "B13. VENTAJAS FINAS DE ESTA LISTA, NO DEL ARQUETIPO GENÉRICO",
            start: 705,
            end: 714,
          },
          {
            id: "B14",
            label: "B14. DESVENTAJAS FINAS Y CÓMO MITIGARLAS",
            start: 715,
            end: 723,
          },
          {
            id: "B15",
            label:
              "B15. ENTRENAMIENTO DE JUGADOR 'MASTER': PRÁCTICAS RECOMENDADAS",
            start: 724,
            end: 733,
          },
          {
            id: "B16",
            label: "B16. GLOSARIO COMPETITIVO DE KASHTIRA",
            start: 734,
            end: 745,
          },
          {
            id: "B17",
            label: "B17. CHECKLIST ANTES DE CADA DUELO",
            start: 746,
            end: 757,
          },
          {
            id: "B18",
            label: "B18. VERIFICACIONES DE EFECTOS Y FUENTES",
            start: 758,
            end: 769,
          },
        ],
      },
      {
        id: "optimization",
        label: "Sinergias y optimización",
        icon: "bi-journal-text",
        hint: "Anexo C del documento aportado.",
        document: true,
        topics: [
          {
            id: "C-intro",
            label: "ANEXO C — AUDITORÍA DE SINERGIAS Y PROPUESTAS DE CAMBIOS",
            start: 770,
            end: 775,
          },
          {
            id: "C1",
            label:
              "C1. DIAGNÓSTICO DE SINERGIA, CONFLICTOS Y CARTAS POTENCIALMENTE MUERTAS",
            start: 776,
            end: 797,
          },
          {
            id: "C2",
            label: "C2. CAMBIOS POSIBLES DENTRO DEL MAIN — NO APLICADOS",
            start: 798,
            end: 804,
          },
          {
            id: "C3",
            label: "C3. CAMBIOS PROPUESTOS DEL EXTRA — NO APLICADOS",
            start: 805,
            end: 812,
          },
          {
            id: "C4",
            label: "C4. CAMBIOS DESDE LA RESERVA DE 19: EJEMPLOS DE PAQUETES",
            start: 813,
            end: 860,
          },
          {
            id: "C5",
            label: "C5. CÓMO DECIDIR QUÉ SACAR AL SIDING",
            start: 861,
            end: 867,
          },
          {
            id: "C6",
            label: "C6. PRIORIDAD DE INVESTIGACIÓN Y REVISIÓN DE CARTAS",
            start: 868,
            end: 874,
          },
          {
            id: "C7",
            label: "C7. PROTOCOLO FORMAT-AWARE PARA LOS SIGUIENTES 66 DECKS",
            start: 875,
            end: 885,
          },
        ],
      },
      {
        id: "form-plan",
        label: "Plan de juego detallado",
        icon: "bi-journal-text",
        hint: "Anexo D del documento aportado.",
        document: true,
        topics: [
          {
            id: "D-intro",
            label:
              "ANEXO D — CAMPOS DEL FORMULARIO «PLAN DE JUEGO» LISTOS PARA STATION X",
            start: 886,
            end: 890,
          },
          { id: "D1", label: "D1. PLAN PRINCIPAL", start: 891, end: 893 },
          {
            id: "D2",
            label: "D2. CONDICIÓN DE VICTORIA",
            start: 894,
            end: 896,
          },
          { id: "D3", label: "D3. MOTOR DE RECURSOS", start: 897, end: 899 },
          { id: "D4", label: "D4. APERTURA", start: 900, end: 902 },
          { id: "D5", label: "D5. JUEGO MEDIO", start: 903, end: 905 },
          { id: "D6", label: "D6. JUEGO TARDÍO", start: 906, end: 908 },
          { id: "D7", label: "D7. EMPEZANDO PRIMERO", start: 909, end: 911 },
          { id: "D8", label: "D8. EMPEZANDO SEGUNDO", start: 912, end: 914 },
          { id: "D9", label: "D9. SIDE Y RESERVAS", start: 915, end: 917 },
          {
            id: "D10",
            label: "D10. INSTRUCCIÓN PERMANENTE PARA PRÓXIMOS DECKS",
            start: 918,
            end: 924,
          },
        ],
      },
      {
        id: "advanced",
        label: "Evaluación avanzada",
        icon: "bi-clipboard-data",
        hint: "Análisis específico y pruebas documentadas.",
        document: true,
        topics: [
          {
            id: "G0",
            label: "G0. CÓMO INTERPRETAR LOS DATOS",
            start: 1465,
            end: 1471,
          },
          {
            id: "G1",
            label:
              "G1. KASHTIRA PURE — EVALUACIÓN AVANZADA ESPECÍFICA (MAIN 42 / EXTRA 15 / RESERVA 19)",
            start: 1472,
            end: 1473,
          },
          {
            id: "G1.1",
            label: "G1.1 APERTURAS Y CONSISTENCIA",
            start: 1474,
            end: 1479,
          },
          {
            id: "G1.2",
            label: "G1.2 ÁRBOL DE DECISIONES EN JUEGO PRIMERO",
            start: 1480,
            end: 1486,
          },
          {
            id: "G1.3",
            label: "G1.3 JUEGO SEGUNDO Y PLAN B",
            start: 1487,
            end: 1492,
          },
          {
            id: "G1.4",
            label: "G1.4 INTERRUPCIONES: PRUEBAS OBLIGATORIAS",
            start: 1493,
            end: 1499,
          },
          {
            id: "G1.5",
            label: "G1.5 EXTRAS Y CARTAS POTENCIALMENTE MUERTAS",
            start: 1500,
            end: 1505,
          },
          {
            id: "G1.6",
            label: "G1.6 SIDE/RESERVA: ENSAYOS 'ENTRA/SALe' SIN CERRAR LAS 19",
            start: 1506,
            end: 1513,
          },
          {
            id: "G1.7",
            label: "G1.7 MATCHUPS EDITORIALES, NO WIN RATE",
            start: 1514,
            end: 1518,
          },
          {
            id: "G1.8",
            label:
              "G1.8 TRES MANOS DE ENTRENAMIENTO (ESCENARIOS, NO MUESTRAS REALES)",
            start: 1519,
            end: 1523,
          },
          {
            id: "G4",
            label: "G4. MATRIZ DE PRUEBAS REPRODUCIBLES PARA LOS TRES DECKS",
            start: 1637,
            end: 1659,
          },
          {
            id: "G5",
            label: "G5. REGLA FIJA PARA LOS FUTUROS DECKS DE STATION X",
            start: 1660,
            end: 1666,
          },
        ],
      },
      {
        id: "technical",
        label: "Auditoría técnica",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          {
            id: "K0",
            label:
              "K0. MATEMÁTICA EXACTA: PRESENCIA DE DOS GRUPOS DISTINTOS EN MANO INICIAL",
            start: 1999,
            end: 2012,
          },
          {
            id: "K1",
            label: "K1. KASHTIRA PURE — VALIDACIÓN DE JUGADAS Y CONTINGENCIAS",
            start: 2013,
            end: 2022,
          },
          {
            id: "K6",
            label:
              "K6. AUDITORÍA DE 15 SLOTS DEL EXTRA: FORMATO DE RESULTADO POR CARTA",
            start: 2060,
            end: 2062,
          },
          {
            id: "K7",
            label: "K7. INDICADORES OBSERVADOS Y PRUEBAS REPRODUCIBLES",
            start: 2063,
            end: 2068,
          },
          {
            id: "K8",
            label: "K8. HALLAZGOS, LIMITACIONES Y SIGUIENTE VERIFICACIÓN",
            start: 2069,
            end: 2077,
          },
        ],
      },
      {
        id: "rotations",
        label: "Rotaciones y rivales",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          { id: "L0", label: "L0. REGLAS DE LECTURA", start: 2083, end: 2087 },
          {
            id: "L1",
            label: "L1. ROTACIONES DE KASHTIRA PURE — 42/15/19",
            start: 2088,
            end: 2096,
          },
          {
            id: "L6",
            label: "L6. MATRIZ TCG — DECK OPONENTE POR DECK PROPIO",
            start: 2137,
            end: 2253,
          },
          {
            id: "L7",
            label:
              "L7. REGLA PARA USAR LA MATRIZ COMO ENTRENAMIENTO (NO SOLO LEERLA)",
            start: 2254,
            end: 2259,
          },
        ],
      },
    ],
    687: [
      {
        id: "profile",
        label: "Perfil maestro completo",
        icon: "bi-journal-text",
        hint: "Anexo I del documento aportado.",
        document: true,
        topics: [
          {
            id: "I-intro",
            label: "ANEXO I — PERFIL MAESTRO: VOLCANIC — TCG",
            start: 1834,
            end: 1837,
          },
          {
            id: "I0",
            label:
              'I0. Identidad: motor Volcánico de FUEGO/Pyro, efectos desde Cementerio, Blaze Accelerator, daño de efecto/burn, desgaste y limpieza de monstruos; Extra mayoritariamente Link usado como respuesta o recursos de flexibilidad. El deck tiene MAXX "C" entre sus 42 y una copia en la reserva; la aprobación preserva la fotografía, NO certifica legalidad TCG avanzada de cada carta, número o Extra en una fecha competitiva. Las recomendaciones son editoriales y no alteran nada.',
            start: 1838,
            end: 1839,
          },
          {
            id: "I1",
            label: "I1. MAIN DECK: 42 CARTAS",
            start: 1840,
            end: 1866,
          },
          {
            id: "I2",
            label: "I2. EXTRA DECK — 15 CARTAS",
            start: 1867,
            end: 1879,
          },
          {
            id: "I3",
            label: "I3. RESERVA COMPLETA — 14 CARTAS",
            start: 1880,
            end: 1892,
          },
          {
            id: "I4",
            label: "I4. FUNCIONES Y SINERGIAS DE LAS CARTAS",
            start: 1893,
            end: 1910,
          },
          {
            id: "I5",
            label: "I5. PLAN DE JUEGO Y CONDICIONES DE VICTORIA",
            start: 1911,
            end: 1917,
          },
          {
            id: "I6",
            label: "I6. COMBOS PASO A PASO, CONDICIONADOS",
            start: 1918,
            end: 1925,
          },
          {
            id: "I7",
            label: "I7. NIVELES DE PILOTAJE: NUEVE CAMPOS DEL FORMULARIO",
            start: 1926,
            end: 1936,
          },
          {
            id: "I8",
            label: "I8. EXTRA DECK REAL Y SU RELACIÓN CON EXTRAVAGANCIA",
            start: 1937,
            end: 1945,
          },
          {
            id: "I9",
            label: "I9. CHOKE POINTS / RULINGS / CARTAS MUERTAS",
            start: 1946,
            end: 1954,
          },
          {
            id: "I10",
            label: "I10. RESERVA 14 — INTERCAMBIOS PROPUESTOS, NUNCA APLICADOS",
            start: 1955,
            end: 1965,
          },
          {
            id: "I11",
            label:
              "I11. PROBABILIDADES EXACTAS DE APERTURA (42 MAIN, 5 CARTAS)",
            start: 1966,
            end: 1973,
          },
          {
            id: "I12",
            label: "I12. TRES MANOS DE PRUEBA, MATCHUPS Y DATOS",
            start: 1974,
            end: 1984,
          },
        ],
      },
      {
        id: "technical",
        label: "Auditoría técnica",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          {
            id: "K0",
            label:
              "K0. MATEMÁTICA EXACTA: PRESENCIA DE DOS GRUPOS DISTINTOS EN MANO INICIAL",
            start: 1999,
            end: 2012,
          },
          {
            id: "K5",
            label:
              "K5. VOLCANIC — VALIDACIÓN DE SCATTERSHOT Y CONTROL DE ATRIBUTOS",
            start: 2051,
            end: 2059,
          },
          {
            id: "K6",
            label:
              "K6. AUDITORÍA DE 15 SLOTS DEL EXTRA: FORMATO DE RESULTADO POR CARTA",
            start: 2060,
            end: 2062,
          },
          {
            id: "K7",
            label: "K7. INDICADORES OBSERVADOS Y PRUEBAS REPRODUCIBLES",
            start: 2063,
            end: 2068,
          },
          {
            id: "K8",
            label: "K8. HALLAZGOS, LIMITACIONES Y SIGUIENTE VERIFICACIÓN",
            start: 2069,
            end: 2077,
          },
        ],
      },
      {
        id: "rotations",
        label: "Rotaciones y rivales",
        icon: "bi-diagram-3",
        hint: "Secuencias, contingencias y entrenamiento del deck.",
        document: true,
        topics: [
          { id: "L0", label: "L0. REGLAS DE LECTURA", start: 2083, end: 2087 },
          {
            id: "L5",
            label: "L5. ROTACIONES DE VOLCANIC — 42/15/14",
            start: 2126,
            end: 2136,
          },
          {
            id: "L6",
            label: "L6. MATRIZ TCG — DECK OPONENTE POR DECK PROPIO",
            start: 2137,
            end: 2253,
          },
          {
            id: "L7",
            label:
              "L7. REGLA PARA USAR LA MATRIZ COMO ENTRENAMIENTO (NO SOLO LEERLA)",
            start: 2254,
            end: 2259,
          },
        ],
      },
    ],
  };
  async function sourceText() {
    if (!sourcePromise)
      sourcePromise = fetch(sourceUrl, { cache: "no-cache" })
        .then((r) => {
          if (!r.ok) throw new Error("No se pudo cargar el documento.");
          return r.text();
        })
        .catch((e) => {
          sourcePromise = null;
          throw e;
        });
    return sourcePromise;
  }
  function title(text) {
    return String(text)
      .replace(/^[A-Z]\d+(?:\.\d+)*(?:\.)?\s*/, "")
      .trim();
  }
  function paragraphs(text) {
    const lines = String(text || "")
      .split(/\r?\n/)
      .filter(
        (l) =>
          !/^\s*[#=_-]{3,}\s*$/.test(l) && !/^\s*FIN ANEXO [A-Z]\s*$/.test(l),
      );
    let html = "",
      kind = "";
    const close = () => {
      if (kind) {
        html += `</${kind}>`;
        kind = "";
      }
    };
    for (const raw of lines) {
      const line = raw.trim();
      if (!line) {
        close();
        continue;
      }
      const ordered = line.match(/^\d+[).]\s+(.+)/),
        bullet = line.match(/^(?:- |\[ \]\s*)(.+)/);
      if (ordered || bullet) {
        const next = ordered ? "ol" : "ul";
        if (kind !== next) {
          close();
          html += `<${next}>`;
          kind = next;
        }
        html += `<li>${esc((ordered || bullet)[1])}</li>`;
      } else {
        close();
        html += `<p>${esc(line)}</p>`;
      }
    }
    close();
    return (
      html ||
      '<p class="strategy-empty">Pendiente de documentar para este deck.</p>'
    );
  }
  const textTopic = (id, label, text) => ({ id, label, text: text || "" });
  function annexes(notes) {
    const matches = [
        ...String(notes || "").matchAll(
          /^(ANEXO ([A-Z]) .+|([A-Z]\d+(?:\.\d+)*)(?:\.)? .+)$/gm,
        ),
      ],
      groups = { A: [], B: [], C: [] };
    for (let i = 0; i < matches.length; i++) {
      const m = matches[i],
        key = m[2] || m[3][0];
      (groups[key] ??= []).push({
        id: m[3] || key + "-intro",
        label: m[3]
          ? m[3] + " · " + title(m[0])
          : "Presentación del anexo " + key,
        text: notes.slice(
          m.index + m[0].length,
          matches[i + 1]?.index ?? notes.length,
        ),
      });
    }
    return {
      groups,
      preface: matches.length ? notes.slice(0, matches[0].index) : notes,
    };
  }
  function render(deck, profile, observed) {
    const host = document.querySelector(".deck-strategy-profile");
    if (!host) return;
    for (const [selector, value] of [
      ["[data-deck-analysis-pace]", profile?.pace],
      ["[data-deck-analysis-complexity]", profile?.complexity],
    ]) {
      const select = document.querySelector(selector);
      if (select && value) {
        if (![...select.options].some((o) => o.value === value))
          select.appendChild(new Option(String(value), String(value)));
        select.value = value;
      }
    }
    const same = active?.id === Number(deck.id),
      signature = JSON.stringify([
        deck.id,
        profile?.revision,
        profile?.updated_at,
      ]);
    if (same && active.signature === signature) return;
    if (active) restoreEditor(active);
    host.classList.add("strategy-organized");
    document
      .querySelector(".deck-analysis-header")
      ?.classList.add("strategy-legacy-heading");
    host.querySelector(".strategy-workspace")?.remove();
    host.querySelector(".strategy-reader")?.remove();
    const parsed = annexes(profile?.analysis_notes || ""),
      format =
        (deck.format_codes || [])
          .map(
            (c) =>
              ({ tcg_advanced: "TCG", rush_duel: "RUSH DUEL", goat: "GOAT" })[
                c
              ] || c,
          )
          .join(" · ") || "Por confirmar";
    const state = {
      host,
      id: Number(deck.id),
      signature,
      deck,
      profile,
      observed,
      groups: [],
      current: null,
      index: 0,
      token: 0,
      editor: host.querySelector(".deck-strategy-editor"),
      editorHome: null,
      trigger: null,
    };
    active = state;
    const groups = state.groups;
    groups.push({
      id: "plan",
      label: "Plan de juego",
      icon: "bi-diagram-3",
      hint: "Objetivo, recursos y decisiones durante la partida.",
      topics: [
        textTopic("principal", "Plan principal", profile?.primary_game_plan),
        textTopic("victoria", "Condición de victoria", profile?.win_condition),
        textTopic("recursos", "Motor de recursos", profile?.resource_engine),
        textTopic("apertura", "Apertura", profile?.opening_plan),
        textTopic("medio", "Juego medio", profile?.mid_game_plan),
        textTopic("tardio", "Juego tardío", profile?.late_game_plan),
        textTopic("primero", "Empezando primero", profile?.going_first_plan),
        textTopic("segundo", "Empezando segundo", profile?.going_second_plan),
        textTopic("reservas", "Side y reservas", profile?.side_deck_strategy),
      ],
    });
    groups.push({
      id: "risks",
      label: "Fortalezas y riesgos",
      icon: "bi-shield-check",
      hint: "Ventajas, debilidades y puntos que conviene vigilar.",
      topics: [
        {
          id: "fortalezas",
          label: "Fortalezas",
          items: list(profile?.strengths_json),
        },
        {
          id: "riesgos",
          label: "Debilidades y riesgos",
          items: list(profile?.weaknesses_json),
        },
        {
          id: "etiquetas",
          label: "Etiquetas estratégicas",
          items: list(profile?.strategy_tags_json),
        },
      ],
    });
    if (importedAnnexes[String(deck.id)]) {
      // Ranges refer to the complete original document. Load only the reader
      // content on demand; large annexes never crowd the profile overview.
      groups.push(
        ...importedAnnexes[String(deck.id)].map((g) => ({
          ...g,
          topics: g.topics.map((t) => ({ ...t })),
        })),
      );
    } else {
      for (const [id, key, label, icon, hint] of [
        [
          "profile",
          "A",
          "Perfil detallado",
          "bi-journal-text",
          "Construcción, listas, recursos y criterios del anexo A.",
        ],
        [
          "manual",
          "B",
          "Manual de juego",
          "bi-controller",
          "Líneas de juego, decisiones, práctica y consultas del anexo B.",
        ],
        [
          "optimization",
          "C",
          "Sinergias y optimización",
          "bi-sliders",
          "Conflictos, alternativas y propuestas de cambio del anexo C.",
        ],
      ])
        if (parsed.groups[key].length)
          groups.push({ id, label, icon, hint, topics: parsed.groups[key] });
    }
    groups.push({
      id: "evaluation",
      label: "Evaluación editorial",
      icon: "bi-clipboard-data",
      hint: "Calificaciones documentadas y alcance del análisis.",
      topics: [
        {
          id: "notas",
          label: "Valoración editorial",
          scores: [
            ["Consistencia", "consistency_score"],
            ["Techo de poder", "ceiling_score"],
            ["Resiliencia", "resilience_score"],
            ["Interacción", "interaction_score"],
            ["Recuperación", "recovery_score"],
            ["Primero", "first_score"],
            ["Segundo", "second_score"],
          ].map(([label, key]) => ({
            label,
            value: Number(profile?.[key])
              ? `${Number(profile[key])}/5`
              : "Sin calificar",
          })),
        },
        textTopic("procedencia", "Fuente y revisión", parsed.preface),
      ],
    });
    groups.push({
      id: "source",
      label: "Documento fuente",
      icon: "bi-file-earmark-text",
      hint: "Criterios generales, los 67 perfiles y los anexos originales.",
      topics: [],
      source: true,
    });
    groups.push({
      id: "edit",
      label: "Editar perfil",
      icon: "bi-pencil-square",
      hint: "Actualizar los campos editoriales y guardar una nueva revisión.",
      topics: [],
      editor: true,
    });
    const workspace = document.createElement("section");
    workspace.className = "strategy-workspace";
    workspace.innerHTML = `<div class="strategy-summary"><div class="strategy-summary-top"><span class="strategy-eyebrow">Perfil editorial · ${profile ? "Revisión " + Number(profile.revision) : "Pendiente de documentar"}</span><span class="strategy-format">${esc(format)}</span></div><h3>${esc(deck.name)}</h3><p class="strategy-summary-plan">${esc(profile?.primary_game_plan || "Completa el perfil para documentar el estilo y el plan de este deck.")}</p><div class="strategy-identity-grid">${[
      ["Estilo", profile?.play_style],
      ["Ritmo", profile?.pace],
      ["Complejidad", profile?.complexity],
    ]
      .map(
        ([label, value]) =>
          `<div><span>${label}</span><strong>${esc(value || "Por documentar")}</strong></div>`,
      )
      .join(
        "",
      )}</div></div><div class="strategy-section-heading"><h4>Explorar estrategia</h4><p>Elige un tema para consultar su contenido.</p></div><div class="strategy-topic-grid">${groups.map((g) => `<button type="button" class="strategy-topic-card" data-strategy-open="${g.id}"><span class="strategy-topic-icon"><i class="bi ${g.icon}" aria-hidden="true"></i></span><span class="strategy-topic-copy"><b>${g.label}</b><small>${g.hint}</small><em>${g.editor ? "Formulario de edición" : g.source ? "Lectura y descarga" : g.topics.length + " apartados"}</em></span><i class="bi bi-chevron-right" aria-hidden="true"></i></button>`).join("")}</div>`;
    const reader = document.createElement("section");
    reader.className = "strategy-reader";
    reader.hidden = true;
    reader.innerHTML = `<div class="strategy-reader-heading"><button type="button" class="btn btn-outline-info" data-strategy-back><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al resumen</button><span data-strategy-reader-position></span></div><div class="strategy-reader-title"><span class="strategy-eyebrow">${esc(deck.name)}</span><h3 data-strategy-reader-title></h3><p data-strategy-reader-description></p></div><div class="strategy-reader-controls" data-strategy-controls><label>Buscar apartado<input class="form-control" type="search" data-strategy-chapter-search placeholder="Título o palabra clave"></label><label>Ir a un apartado<select class="form-select" data-strategy-chapter></select></label></div><p class="strategy-reader-status" data-strategy-status role="status" aria-live="polite"></p><article class="strategy-reading" data-strategy-reading></article><div class="strategy-reader-navigation" data-strategy-navigation><button type="button" class="btn btn-outline-info" data-strategy-previous>Apartado anterior</button><button type="button" class="btn btn-outline-info" data-strategy-next>Siguiente apartado</button></div>`;
    host.append(workspace, reader);
    state.workspace = workspace;
    state.reader = reader;
    workspace.querySelectorAll("[data-strategy-open]").forEach(
      (button) =>
        (button.onclick = () => {
          state.trigger = button;
          open(state, button.dataset.strategyOpen);
        }),
    );
    reader.querySelector("[data-strategy-back]").onclick = () => back(state);
    reader.querySelector("[data-strategy-chapter]").onchange = (e) => {
      state.index = Number(e.target.value);
      drawTopic(state, true);
    };
    reader.querySelector("[data-strategy-chapter-search]").oninput = () =>
      fillOptions(state);
    const step = (delta) => {
      const values = [
          ...reader.querySelector("[data-strategy-chapter]").options,
        ].map((o) => Number(o.value)),
        position = values.indexOf(state.index),
        next = values[position + delta];
      if (next !== undefined) {
        state.index = next;
        drawTopic(state, true);
      }
    };
    reader.querySelector("[data-strategy-previous]").onclick = () => step(-1);
    reader.querySelector("[data-strategy-next]").onclick = () => step(1);
  }
  function scrollTop(state) {
    const body = state.host.closest(".modal-body");
    if (body) body.scrollTop = 0;
  }
  function restoreEditor(state) {
    if (state.editorHome && state.editor) {
      state.editorHome.appendChild(state.editor);
      state.editor.open = false;
      state.editorHome = null;
    }
  }
  function back(state) {
    state.token++;
    restoreEditor(state);
    state.reader.hidden = true;
    state.workspace.hidden = false;
    scrollTop(state);
    state.trigger?.focus({ preventScroll: true });
  }
  async function open(state, id) {
    if (active !== state) return;
    restoreEditor(state);
    state.current = state.groups.find((g) => g.id === id);
    state.index = 0;
    const g = state.current,
      reader = state.reader,
      token = ++state.token;
    state.workspace.hidden = true;
    reader.hidden = false;
    reader.querySelector("[data-strategy-reader-title]").textContent = g.label;
    reader.querySelector("[data-strategy-reader-description]").textContent =
      g.hint;
    reader.querySelector("[data-strategy-chapter-search]").value = "";
    reader.querySelector("[data-strategy-status]").textContent = "";
    reader.querySelector("[data-strategy-chapter-search]").disabled = false;
    reader.querySelector("[data-strategy-controls]").hidden = !!g.editor;
    reader.querySelector("[data-strategy-navigation]").hidden = !!g.editor;
    const reading = reader.querySelector("[data-strategy-reading]");
    reading.innerHTML = "";
    if (g.editor) {
      state.editorHome = state.editor?.parentElement;
      if (state.editor) {
        reading.appendChild(state.editor);
        state.editor.open = true;
      } else reading.textContent = "Editor no disponible.";
      reader.querySelector("[data-strategy-reader-position]").textContent =
        "Edición del perfil";
      scrollTop(state);
      return;
    }
    if ((g.source && !g.topics.length) || (g.document && !g.loaded)) {
      reading.hidden = false;
      reading.innerHTML = "<p>Cargando documento…</p>";
      reader.querySelector("[data-strategy-chapter]").replaceChildren();
      reader.querySelector("[data-strategy-chapter]").disabled = true;
      reader.querySelector("[data-strategy-chapter-search]").disabled = true;
      reader.querySelector("[data-strategy-navigation]").hidden = true;
      scrollTop(state);
      try {
        const raw = await sourceText();
        if (active !== state || token !== state.token) return;
        if (g.document) {
          const lines = raw.split(/\r?\n/);
          g.topics = g.topics.map((t) => ({
            ...t,
            text: lines.slice(t.start, t.end).join("\n"),
          }));
          g.loaded = true;
        } else g.topics = sourceTopics(raw);
        reader.querySelector("[data-strategy-chapter-search]").disabled = false;
      } catch (e) {
        if (token !== state.token) return;
        reading.innerHTML = `<p role="alert">${esc(e.message)}</p><button type="button" class="btn btn-outline-info" data-strategy-retry>Reintentar</button><p><a href="${sourceUrl}" download>Descargar documento original</a></p>`;
        reading.querySelector("[data-strategy-retry]").onclick = () =>
          open(state, id);
        return;
      }
    }
    fillOptions(state);
    drawTopic(state, true);
  }
  function sourceTopics(raw) {
    const re =
        /^(?:\d{2}\. [^\n]+|ANEXO [A-Z] [^\n]+|[A-Z]\d+(?:\.\d+)*(?:\.)? [^\n]+|TAREAS PENDIENTES PARA VALIDACIÓN FINAL|TCG — \d+ DECKS|RUSH DUEL — \d+ DECKS|GOAT — \d+ DECKS)\s*$/gm,
      matches = [...raw.matchAll(re)];
    return [
      textTopic(
        "general",
        "Criterios generales del documento",
        raw.slice(0, matches[0]?.index ?? raw.length),
      ),
      ...matches.map((m, i) =>
        textTopic(
          "source-" + i,
          m[0].trim(),
          raw.slice(m.index + m[0].length, matches[i + 1]?.index ?? raw.length),
        ),
      ),
    ];
  }
  function fillOptions(state) {
    const reader = state.reader,
      query = normal(
        reader.querySelector("[data-strategy-chapter-search]").value.trim(),
      ),
      select = reader.querySelector("[data-strategy-chapter]");
    select.innerHTML = "";
    state.current.topics.forEach((topic, i) => {
      if (
        !query ||
        normal(topic.label + " " + (topic.text || "")).includes(query)
      )
        select.appendChild(new Option(topic.label, String(i)));
    });
    reader.querySelector("[data-strategy-reading]").hidden =
      !select.options.length;
    reader.querySelector("[data-strategy-navigation]").hidden =
      !select.options.length;
    reader.querySelector("[data-strategy-status]").textContent = select.options
      .length
      ? `${select.options.length} apartados disponibles`
      : "No hay apartados con esta búsqueda.";
    if ([...select.options].some((o) => Number(o.value) === state.index)) {
      select.value = String(state.index);
      drawTopic(state, false);
    } else if (select.options.length) {
      state.index = Number(select.options[0].value);
      drawTopic(state, false);
    }
    select.disabled = !select.options.length;
  }
  function drawTopic(state, resetScroll) {
    const reader = state.reader,
      g = state.current,
      topic = g.topics[state.index];
    if (!topic) return;
    reader.querySelector("[data-strategy-reader-position]").textContent =
      `${state.index + 1} de ${g.topics.length} apartados`;
    const select = reader.querySelector("[data-strategy-chapter]");
    if ([...select.options].some((o) => Number(o.value) === state.index))
      select.value = String(state.index);
    const content = topic.items
      ? topic.items.length
        ? `<ul>${topic.items.map((v) => `<li>${esc(v)}</li>`).join("")}</ul>`
        : paragraphs("")
      : topic.scores
        ? `<div class="strategy-evaluation-grid">${topic.scores.map((s) => `<div><span>${esc(s.label)}</span><b>${esc(s.value)}</b></div>`).join("")}</div><p class="strategy-reading-note">Estas valoraciones pertenecen al perfil editorial. Consulta los resultados reales en Rendimiento.</p><button type="button" class="btn btn-outline-info" data-strategy-performance>Ver rendimiento</button>`
        : paragraphs(topic.text);
    reader.querySelector("[data-strategy-reading]").innerHTML =
      `<h4 tabindex="-1" data-strategy-chapter-title>${esc(topic.label)}</h4>${content}${g.source ? `<p class="strategy-source-download"><a class="btn btn-outline-info" href="${sourceUrl}" download>Descargar TXT original completo</a></p>` : ""}`;
    reader
      .querySelector("[data-strategy-performance]")
      ?.addEventListener("click", () => {
        back(state);
        document
          .querySelector('[data-deck-category-open="performance"]')
          ?.click();
      });
    const indices = [...select.options].map((o) => Number(o.value)),
      position = indices.indexOf(state.index);
    reader.querySelector("[data-strategy-previous]").disabled = position <= 0;
    reader.querySelector("[data-strategy-next]").disabled =
      position < 0 || position >= indices.length - 1;
    if (resetScroll) {
      scrollTop(state);
      reader
        .querySelector("[data-strategy-chapter-title]")
        .focus({ preventScroll: true });
    }
  }
  document
    .querySelector("[data-deck-category-modal]")
    ?.addEventListener("hidden.bs.modal", () => {
      if (active) back(active);
    });
  document
    .querySelectorAll(
      "[data-deck-category-open],[data-deck-category-prev],[data-deck-category-next]",
    )
    .forEach((b) =>
      b.addEventListener("click", () => {
        if (active) back(active);
      }),
    );
  async function studyTopics(deckId) {
    const current = active;
    if (!current || current.id !== Number(deckId)) return [];
    const groups = current.groups.filter((g) => !g.source && !g.editor);
    const raw = groups.some((g) => g.document) ? await sourceText() : null;
    const lines = raw?.split(/\r?\n/);
    const identity = {
      id: "identity",
      key: "plan:identity",
      group: "plan",
      label: "Identidad y dificultad del deck",
      items: [
        ["Estilo", current.profile?.play_style],
        ["Ritmo", current.profile?.pace],
        ["Complejidad", current.profile?.complexity],
      ].map(([k, v]) => k + ": " + (v || "Por documentar")),
    };
    return [
      identity,
      ...groups.flatMap((g) =>
        g.topics.map((t) => ({
          ...t,
          key: g.id + ":" + t.id,
          group: g.id,
          text: g.document
            ? lines.slice(t.start, t.end).join("\n")
            : t.text || "",
        })),
      ),
    ];
  }
  root.YgoDeckStrategy = { render, annexes, sourceTopics, studyTopics };
})(window);
