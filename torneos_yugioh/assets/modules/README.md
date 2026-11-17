# Módulos de interfaz

## `deck-data/`

`ui.js` / `styles.css`: modal por deck con exportación TXT, ZIP de documentos/datos/imágenes e importación ZIP con previsualización. Persistencia versionada en `includes/decks/data-package.php` y `data-http.php`, mediante el registro editorial activo; conserva inventarios, resultados y revisiones nativas del editor. Contrato y despliegue en `context/deck-data.md`.

## `card-inspector/`

- `model.js`, `ui.js`, `styles.css`: Inspector de cartas en Herramientas, con búsqueda local por inventario de todos los decks y consulta al catálogo API existente. Muestra fotografía ampliable, efecto y decks asociados con copias por zona. Separa CID Konami de ID YGOPRODeck. Documentación: `context/card-inspector.md`. Pruebas: `tests/card_inspector_test.cjs` y `tests/card_inspector_ui_test.cjs`.

## `statistics/`

- `workspace.js` / `workspace.css`: «Estadísticas y tops» se limita a Ranking por formato, Ranking global, Metagame y Tablas. Consume los ratings y cortes existentes, manteniendo cada formato separado. Integra el nodo de tablas nativo y sus eventos sin recrear el selector al cambiar de pestaña. `#tabla` se redirige a Estadísticas/Tablas. Las categorías anteriores y el Top 4 con puntos de ejemplo se retiran de esta interfaz; no se borran datos deportivos ni sus cálculos. Prueba: `tests/statistics_workspace_test.cjs`.

## `navigation/`

- `global-search.js` / `global-search.css`: busca decks, equipos y eventos reales por nombre y código, ignora tildes y permite varias palabras. La navegación usa la identidad encontrada para abrir su ficha nativa; no guarda cambios. La búsqueda ampliada reutiliza el mismo motor.

- `sidebar.css`: alinea los controles del menú contraído de escritorio y superpone los contadores sin desplazar sus iconos. Se limita a pantallas mayores de 1200 px; conserva el menú expandido y el cajón móvil. Las etiquetas accesibles y ayudas de los grupos se actualizan mediante el control nativo de `app.js`.

## `cup-bracket/`

- `navigation.js` y `navigation.css`: apertura de combates desde la llave por clic o teclado. Los registrados usan la ficha existente; las proyecciones con dos integrantes definidos abren una ficha informativa sin inventar ID, fecha o resultado. Espera al cierre del modal de programación antes de abrir una ficha registrada.

El código nuevo se agrupa por función. Las fuentes editoriales y los datos generados permanecen en `assets/editorial/`; no son archivos de implementación y no deben formatearse ni mezclarse con los scripts.

## `dashboard/`

- `ranking-scopes.js`: genera opciones por evento y división a partir de las relaciones reales. Filtra filas de clasificación y partidas antes de sumar equipos, LP y estadísticas; conserva las fórmulas del panel y no modifica resultados.

## `deck-guide/`

- `league-position.js`: proyecta la posición del deck dentro de su liga activa y división desde el orden de la clasificación enviada por el servidor. No usa ranking global ni semilla; una división sin resultados no recibe puestos inventados. El listado muestra esta columna en lugar de los dos resúmenes de duelos.

- `list-metadata.css`: estilo/arquetipo en la lista e indicador verde de cartas guardadas. `includes/decks/public-metadata.php` resuelve datos nativos y perfil documental activo sin modificar registros; la ficha reutiliza los mismos campos de presentación.

`combo-creator/editorial-library.js` conecta la biblioteca documentada activa con «Mis combos y estrategias», reutilizando este lector con identificadores de interfaz separados de la ficha de estrategia. Conserva la carga y edición independiente de las creaciones manuales.

`combo-creator/documented-import.js` convierte las 105 rutas activas al modelo nativo del editor: cartas por ID de inventario, nodos, conexiones, decisiones y posiciones/materiales finales. El selector del creador muestra las guías editables de cada deck y «Mis combos» las abre directamente en ese editor. El primer guardado crea una versión propia con ID estable; los siguientes mantienen el historial y las ediciones guardadas prevalecen sobre la plantilla documental. Las fuentes permanecen intactas.

- `functional-role.js`: presenta la función de cada carta separada de sus conexiones con el motor y de su disponibilidad por zona. Staples y respuestas conservan su utilidad en Reserva; los destinos del Extra pueden ser recursos para Prosperity/Extravagance presentes en Main, sin suponer una Invocación ni efectos de Cementerio al desterrarlos boca abajo. Proyección de lectura: no modifica fuentes, cartas, cantidades ni rutas.

- `endboard.js` / `endboard.css`: presenta los 105 tableros A10 y 114 resultados/variantes, con materiales Xyz, copias separadas, recursos fuera del campo y datos desconocidos explícitos. Las casillas ilustrativas se distinguen de las columnas exigidas por la guía; no simula efectos ni modifica el estado del duelo.

- `curated-visual.js` / `curated-visual.css`: representación V16 de las guías verticales con cartas/acciones, tablero explícito cuando existe y posiciones desconocidas sin inferencia; perfiles de mejora y decisiones frente al rival. Mantiene los controles del lector nativo y el visor de imágenes independiente.

- `content-state.js`: aplica la limpieza y las reactivaciones explícitas por deck; impide que reaparezcan entregas antiguas.
- `checked-study.js` / `checked-study.css`: lector de fichas editoriales con check individual, fotografías, búsqueda, filtro de zona y detalle. No convierte clasificaciones en combos ni restaura relaciones parciales.
- `curated-study.js` / `curated-study.css`: lector de los once decks desde `context/gpt/01_INFORMACION_A_IMPORTAR`. Perfil/listas nativas, 370 fichas con acción, condición y coste, 105 rutas documentales con seguimiento, 45 decisiones y 33 ejercicios locales. Recomendaciones de construcción separadas del inventario; cobertura de etiquetas documentales sin confundirla con probabilidades ni certificación. Las rutas TCG históricas tienen aviso de restricción. Informe fuera del runtime: `context/gpt/03_COMPROBACIONES_Y_NO_IMPORTAR/IMPORTACION_V14_20261109.json`.
- `photo-viewer.js`: visor independiente de fotografías originales con ajuste, tamaño natural, zoom, rueda, arrastre y pellizco. Conserva la resolución real y devuelve el foco al cerrar; no genera ni interpola imágenes de cartas.
- `study-sections.js` / `study-sections.css`: lector histórico de apartados. La entrega V62 lo sustituye para sus once IDs; no se borran las instantáneas previas. 01 es contexto interno y 03 soporte del inventario.
- `deck-deliveries.js`: carga y validación del registro activo de cada deck; etiquetas y presentación estructurada de respaldo.
- `deck-study.js`: biblioteca, fuentes originales y vistas de estudio de cartas, rutas, manos y probabilidades.
- `learning-guide.js`: navegación y coordinación de la ficha visual.
- `learning-guide.css`: estilos y diseño adaptable de la guía.
- `strategy-workspace.js`: editor y lector previo para los decks sin nueva entrega.
- `editorial-reference.css`: estilos del lector editorial.

## `play-calculator/`

- `workspace.js` / `workspace.css`: pantalla `#calculadora_jugadas` en Herramientas. Selección de deck con filtros por nombre/equipo/formato y catálogo completo de participantes. Reutiliza `ui.js`/`engine.js`; los rivales sin Main admiten solo cartas desconocidas y los formatos incompatibles se explican sin ocultar decks. Prueba: `tests/calculator_workspace_ui_test.cjs`.

- `hand-practice.js`: aperturas aleatorias y robos del Main por copias físicas, sin reemplazo; funciona sobre estados de duelo y de práctica libre sin oponente. No modifica listas ni guarda escenarios.

- `planner.js` / `planner-worker.js`: búsqueda local limitada de acciones y estados posteriores, presupuesto y cancelación; conserva captura y separa efectos sin modelar.
- `card-facts.json`: metadatos exactos generados por `tools/build_planner_facts.py`; sin IA o consultas remotas durante el cálculo.

- `engine.js`: funciones puras para el estado físico, copias, zonas, validación y requisitos de las rutas. Sin DOM, HTTP ni acceso a bases de datos; admite pruebas en Node.
- `storage.js`: cache de listas y rutas bajo `stationx:playcalc:v1:`; no guarda el escenario del duelo.
- `ui.js`: tablero, catálogo, arrastre, selección accesible y recomendaciones.
- `offline.js`: arranque de la entrada autónoma `play-calculator.html` con las listas guardadas.
- `styles.css`: presentación del tablero en escritorio y móvil.

## `combo-creator/`

- `step-lesson.js` / `step-lesson.css`: lectura pedagógica común a todos los pasos del editor. Separa acciones, función de cartas, costes/objetivos explícitos, restricciones y estado posterior; explica abreviaturas y conserva el texto completo del autor. No modifica el documento ni deduce efectos o zonas que falten.

- `model.js`: documento y operaciones puras de pasos, cartas y conexiones sin ciclos.
- `ui.js`: editor visual, catalogo del deck, guia, matriz y lector de creaciones en la ficha.
- `styles.css`: lienzo y vistas adaptables; conexiones SVG sin bibliotecas externas.
- Persistencia y validacion en `includes/decks/combo-creator*.php`. Cada creacion guarda revisiones e inventario; versiona el prompt de las rutas documentales; no modifica inventarios ni resultados. Ver `context/combo-creator.md`.

## `match-sheet/`

- `layout.css`: distribución de la ficha del combate desde 761 px; conserva el flujo móvil existente y utiliza fondo opaco, cabecera separada, resumen equilibrado y notas a todo el ancho.
- `navigation.js`: coordina la apertura de ficha/captura con Bootstrap; cierra la ventana anterior y espera `hidden.bs.modal` antes de mostrar la siguiente. No escribe resultados ni datos del calendario.
- `tests/match_sheet_layout_test.cjs`: prueba HTTP/Playwright de solo lectura, con POST bloqueados. `MATCH_SHEET_PREVIEW=1` inyecta los dos archivos locales para previsualizar sin publicar; sin esa variable comprueba los recursos desplegados.

## `deck-export/`

- `export.js`: descarga TXT por deck o del catálogo completo sin aplicar filtros de pantalla. Usa cantidades y secciones guardadas; la entrega editorial del mismo deck solo aporta nombres bilingües por coincidencia exacta. Los nombres ausentes se indican como no documentados. No importa datos ni modifica listas, pese a las etiquetas «Importar» solicitadas para los botones.

`index.php` y `assets/app.js` mantienen el arranque y la navegación existentes. Los módulos reciben el deck y los datos mediante `mount`/`render`; no deben añadir lógica de cartas al controlador general. No crear archivos de parche con dependencias implícitas por orden de carga.

El código heredado de otros dominios aún permanece en `assets/` e `includes/`. Su extracción requiere actualizar consumidores y probar el dominio correspondiente; esta organización no afirma que los monolitos históricos ya estén separados por completo.

## Estadísticas en vivo

`statistics/live.js` refresca la API actual al entrar/volver y cada 30 segundos con la vista relevante visible; conserva datos ante fallo y emite cambios para ficha y workspace. No consulta cortes ni crea nuevas publicaciones. Rating y mínimos conservan las reglas deportivas existentes.

El ranking global conserva Elo/bonos y se presenta con cuatro columnas: posición, equipo/deck, puntuación y partidas. La revisión 8.4 requiere dos partidas elegibles, sin exigir tres rivales; los mínimos del metagame y la reactivación permanecen independientes.

`deck-guide/league-position.js` también expone `forMatch`: usa el evento y división del combate para ficha, captura y alertas. Respeta el orden del servidor, contempla participantes adicionales y no usa el ranking global ni muestra puestos de liga en copas. Su CSS comparte el indicador responsive entre las tres vistas.

## `deck-attributes/`

`model.js` mantiene las seis categorías, máximo tres, principal ordenado y filtros. `ui.js` integra tabla y formulario separados, historial, guardado en API con CSRF/revisión y etiquetas en lista/ficha. `style.css` adapta controles y tarjetas a móvil/escritorio. Persistencia transaccional e historial en `includes/decks/attributes.php`, sin tocar inventarios ni resultados.

## Mundos (14/11/2026)

`deck-attributes/` conserva los identificadores internos y ahora presenta Mundos en Decks y equipos: tabla de seis mundos y vista de integrantes TCG con altas/bajas directas, principal y formulario separado. Hasta tres mundos por deck; validación real de formato, revisiones e historial en el servidor. Ver `context/deck-worlds.md` y `tests/deck_worlds_ui_test.cjs`.

Mundos exige ahora al menos uno y como máximo tres por deck TCG. Se bloquea quitar el último tanto en UI como en servidor, y el primero conserva la prioridad. La asignación inicial por naturaleza está documentada en `context/deck-worlds-nature.json`.

## Ficha de carta y participación (15/11/2026)

`deck-guide/card-detail.js` organiza las acciones, uso primero/segundo y requisitos documentados. Indexa todos los pasos por CID o imagen inequívoca y distingue acciones, materiales, objetivos y contexto; las revisiones nativas prevalecen sobre referencias históricas. `curated-study.js` enlaza al paso, resalta la carta y permite volver a su ficha conservando los filtros. No cambia efectos ni inventarios. Pruebas: `tests/card_detail_test.cjs` y `tests/card_detail_ui_test.cjs`; evidencia publicada: `outputs/card-detail-20261115/`.
