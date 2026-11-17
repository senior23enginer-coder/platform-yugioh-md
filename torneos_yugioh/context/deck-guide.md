# Guía de juego por deck

## Reconstrucción de los tableros · 13 de noviembre de 2026

La revisión activa `20261113-endboards-161` representa las 105 rutas mediante 114 estados separados. `curated_endboards.py` extrae las tablas de estado A10 y aplica las correspondencias explícitas de `assets/editorial/endboard-bindings.json` a las rutas suplementarias y variantes. Los materiales Xyz se muestran bajo su propietario; las Fusiones/Sincronías/Enlaces conservan sus destinos reales, sin materiales acoplados ficticios.

`endboard.js/css` dibuja Monstruos, M/T, Campo, mano, Cementerio, destierro, cartas devueltas al Deck y Extra boca arriba. Rush utiliza tres zonas y mantiene la Skill externa. Los Enlaces representados usan Zona Extra; las columnas obligatorias se conservan y las demás casillas se identifican como distribución ilustrativa. Una posición de batalla, identidad o cantidad ausente en la fuente se indica sin inventarla. Esto completa la representación de los resultados documentados, sin certificar reglas ni fijar todas las decisiones libres de una partida real.

Arcanite, Dark Armed, Charles, Linkuriboh y las fases de I:P/Emperador tienen estados alternativos separados. Las dos copias de Rampage ocupan casillas diferentes con sus restricciones propias. Cada estado se contrasta con las copias nativas; no modifica cartas, cantidades, resultados ni el carácter histórico de 14 rutas. Informe por ruta: `context/gpt/03_COMPROBACIONES_Y_NO_IMPORTAR/TABLEROS_FINALES_20261113.json`.

## Entrega V16 · importación inicial del 13 de noviembre de 2026

El manifiesto activo `assets/editorial/20261113-v16/manifest.json` conserva 11 decks, 370 identidades y 105 rutas/511 pasos, 45 decisiones y cuatro Skills externas. `curated-visual.js/css` presenta la secuencia vertical completa con fotografías, acciones, flechas oscuras y ventanas documentadas. Las 14 referencias históricas se agrupan en un desplegable independiente de las 91 líneas documentales restantes; las tarjetas no muestran alertas de banlist ni instrucciones internas.

«Estrategia y decisiones» incorpora 24 situaciones frente al rival con recursos, plan, coste y ruta. «Práctica y mejora» prioriza 11 perfiles de fortalezas/puntos ciegos y propuestas de cantidades no aplicadas; los 33 ejercicios quedan como material secundario desplegable. Ninguna recomendación cambia las listas nativas.

Fenrir + Nacimiento dispone de tablero espacial explícito con cuatro cartas y materiales Fenrir/Ogre bajo Shangri-Ira. Las otras 104 rutas mantienen las ubicaciones y resúmenes documentados; posiciones/materiales ausentes se indican sin determinar. No son 105 tableros completos ni 105 certificaciones. Informe y diferencias de fuente: `context/gpt/03_COMPROBACIONES_Y_NO_IMPORTAR/IMPORTACION_V16_20261113.json`.

## Apartados 01–07 · integración del 4 de noviembre de 2026

`assets/editorial/20261104-study-seven/manifest.json` contiene los resultados de los siete apartados de cada uno de los 11 decks. `study-sections.js` los presenta dentro de Estilo y estrategia mediante siete accesos: Empieza aquí (01), Identidad y versión (02), Lista y cantidades (03), Fuentes y zonas (04), Cartas y funciones (05), Motores y sinergias (06), Práctica y reglas (07). La cabecera heredada de comparación con otro rival permanece oculta.

Los originales de cada apartado se conservan completos en el registro, y el contenido de juego se presenta con títulos humanos y detalles desplegables. El rol del asistente y las notas administrativas permanecen como contexto. Se mantienen las 370 fichas previas y los inventarios; se incorporan 58 mapas documentales de A06, comprobando que cada imagen pertenece a una carta del mismo deck o a su Skill separada. Este vínculo de identidad no certifica efectos, cadenas ni combos. Las cuatro Skills conservan fotografías y requisitos recibidos, fuera del Main y de la mano.

La ejecución se limita a 01–07: los apartados 08–36 de los originales no se modifican ni se incorporan a esta entrega. No se transforman mapas de recursos en rutas ejecutables ni se cambia su estado reglamentario. Los 56 decks sin estos resultados conservan el apartado vacío. `build_study_sections.py` coteja el inventario vivo y mantiene el texto fuente por apartado con su hash; `--update-context` registra la aplicación únicamente en 01–07 del archivo de cada deck. No modifica cantidades ni resultados deportivos.

## Carga selectiva de fichas comprobadas · 4 de noviembre de 2026

Tras la limpieza, el propietario autorizó cargar el material comprobado del paquete modular V8. La política `strategy-content-state.json` reactiva **solo 370 fichas individuales de 11 decks**, mediante `checked-study.js` y `assets/editorial/20261104-checked-study/manifest.json`. Los otros 56 decks mantienen el apartado vacío. No se restaura la entrega antigua.

La puerta se aplica por unidad: los checks `A08:C### [X] FICHA_A05` respaldan las fichas de cartas de A05 y su función editorial, no secuencias ni certificación de reglas. Los apartados 01–04 aportan identidad e inventario, cotejados con los registros vivos (390 entradas, incluyendo repeticiones por zona); no se muestran instrucciones del asistente ni notas administrativas como consejos de juego. A06 conserva relaciones `[~]`, y A07 no acredita combos individuales: no se publican esas relaciones, rutas candidatas, planes pendientes, Skills sin check individual ni apartados 09–36. Tres clasificaciones de Volcanic contradicen el inventario y se omiten; las fichas asociadas conservan las zonas reales, sin tocar cantidades.

La vista muestra búsqueda por texto, filtro de Main/Extra/Side/Reserva, fotografía auténtica ampliable y detalle técnico desplegable. Las imágenes proceden del paquete, se conservan por formato y CID y no se sustituyen por imágenes de otra carta. Las fichas Rush explican condiciones documentales; no se presentan como traducciones reglamentarias certificadas. No se rellenan ratings ni planes nativos con información pendiente.

`tools/build_checked_study.py --live-snapshot <DML_DATA.json>` exige identidad/formato, coincidencia exacta de inventario, cantidades, checks, vínculo CID/nombre e imagen existente. Los perfiles SQL vaciados permanecen vacíos: el contenido activo utiliza el mecanismo editorial de archivos, separado de los perfiles manuales. Pruebas: `python torneos_yugioh/tests/checked_study_test.py`, `node torneos_yugioh/tests/checked_study_test.cjs` y regresión del estado vacío en `strategy_content_clear_test.cjs`.

## Limpieza solicitada el 4 de noviembre de 2026

El propietario ordenó vaciar **Estilo y estrategia de los 67 decks**. `assets/editorial/strategy-content-state.json` registra los IDs y la revisión de limpieza; `content-state.js` impide reactivar sus guías anteriores y muestra «Sin información». Los perfiles activos se vacían mediante `save_deck_analysis_profile`, manteniendo revisiones e historial. Respaldo y evidencia local en `outputs/strategy-clear-20261104/` (excluido de Git).

Las cartas, cantidades, formatos, resultados, matchups del módulo independiente, calculador y combos propios no se limpian. Los archivos de investigación se conservan como originales; no se muestran de nuevo en este apartado. Una futura importación debe reactivar explícitamente únicamente sus IDs en el estado de contenido. Un perfil manual nuevo, posterior a la revisión de limpieza, puede mostrarse sin recuperar automáticamente la entrega editorial antigua.

La descripción siguiente documenta la organización disponible para una futura reactivación; no implica que siga cargada en el apartado limpiado.

La ficha siempre utiliza el ID del deck seleccionado. Para los 11 decks de la entrega activa, `assets/editorial/deck-deliveries.json` identifica su archivo individual. `assets/modules/deck-guide/deck-deliveries.js` valida el ID canónico antes de entregar datos a `learning-guide.js`. Los IDs editoriales de Rush son cadenas y no se confunden con los IDs confirmados de la plataforma.

## Organización de la ficha

- Empieza aquí: plan y piezas del deck; la entrega activa prevalece sobre los campos de un perfil anterior.
- Cartas y funciones: únicamente las cartas documentadas de ese deck, con Main, Extra, Side y reserva separados.
- Combos paso a paso: requisitos legibles, imágenes de referencia, resultado condicionado y pasos interactivos. El texto distingue las cartas en mano de los recursos del Deck y Cementerio; una miniatura no demuestra que la carta deba estar en mano.
- Contra otros decks: los planes del deck propio ante rivales. Las referencias rivales pertenecen a este apartado y no convierten la ficha en un catálogo de otras guías.
- Si te interrumpen; Primero o segundo; Manos y probabilidades: contenido específico de la misma construcción.
- Profundización: todos los temas documentados del deck, con sus condiciones y fuentes.

El diagrama opcional pertenece a la ruta seleccionada. El atlas HTML compartido de siete decks se conserva como fuente editorial, pero no se incrusta en ninguna ficha individual. La descarga de la ficha es su `deck-ID.json`, que conserva su registro original completo, y no el `source.json` compartido.

Los detalles técnicos usan etiquetas en español y pares de concepto/contenido, sin un acordeón para cada campo escalar. Los bloques extensos, fuentes y código original del diagrama siguen disponibles como profundización. No se eliminan ni reescriben los originales editoriales para cambiar su presentación.

Una respuesta de otro ID se rechaza. Los errores permiten recargar el mismo deck; no abren una fuente global como sustituto. Una carga tardía de la ficha anterior no puede reemplazar la selección actual. Los decks sin entrega activa conservan su recorrido anterior.

## Verificación local

`npm run test:guide` prueba los 11 registros y las 126 rutas con Playwright, incluyendo escritorio, móvil, descarga por deck, identidad, prioridad de la entrega activa, fallo/reintento y respuestas fuera de orden. También verifica la biblioteca y los 18 campos de las 390 fichas, incluidas las 16 fichas de Royal Rebel’s que proceden del registro original. No conecta con producción ni con MySQL. Las capturas y el informe se generan en `outputs/guide-scope/`, excluido de Git.

## Biblioteca completa recibida

`assets/modules/deck-guide/` separa carga/validación, vistas de estudio y coordinación de la guía. **Biblioteca y fuentes** permite buscar en los documentos, el registro íntegro, las comprobaciones por carta y por ruta y el índice de referencias del deck seleccionado. El atlas se extrae a contenido nativo sin scripts, menús de otros decks ni iframes. La guía legible se divide por deck y los cuatro originales de Skills conservan sus imágenes.

La vista de cartas organiza los 18 campos en función, jugadas, riesgos y cantidades. Las rutas muestran costes, recursos, decisiones y fuentes; las manos enlazan con las rutas que cita la documentación. Los planes contra rivales conservan sus diagramas y condiciones.

`tools/build_deck_library.py` genera los archivos de `assets/editorial/deck-library/` sin modificar las fuentes aprobadas. La descarga individual incluye el registro original completo, los documentos del deck, sus comprobaciones y referencias previas. Los criterios transversales y las notas históricas se muestran aparte, con su alcance identificado; las declaraciones antiguas de importación no se presentan como el estado operativo actual.

El manifiesto registra los hashes de los 20 archivos recibidos y la cobertura: 396 temas, 390 fichas, 126 rutas, 110 manos y cuatro Skills. `python tools/build_deck_library.py --check`, desde `torneos_yugioh/`, comprueba que los derivados sean reproducibles. Las pruebas `deck_library_test.py` verifican fuentes, campos y seguridad de los documentos extraídos.

## Archivo documental 01–11 · primera carga del 7 de noviembre de 2026

La primera carga usó `assets/editorial/20261107-study-eleven/`: 11 decks, 121 apartados recibidos, 370 fichas de cartas, 78 rutas canónicas A09 y 373 pasos. Este directorio se conserva como archivo histórico; sus rutas sin certificación ya no son contenido activo. Estas cifras corresponden a esa fuente, no al catálogo histórico de 126 rutas.

Destino en **Ficha del deck → Estilo y estrategia**:

- 01 permanece como contexto interno; 03 sirve de soporte para cotejar el inventario sin duplicar Decklist.
- 02: identidad y versión. 04: fuentes y zonas, con las cuatro Skills independientes. 05: cartas y funciones. 06: motores y sinergias. 07: práctica y reglas.
- 08: interacciones y piezas iniciales, conservando checks y pendientes individuales.
- 09: selector de fichas canónicas, mano/requisitos, resultado y expediente íntegro.
- 10: mapa de acciones con fotos, navegación por pasos y estados de zonas/campo final. Las posiciones no especificadas siguen desconocidas y los materiales se leen en la traza, sin simularlos.
- 11: cadenas y resolución asociadas por ID cuando existe un vínculo inequívoco, más cobertura general y casos pendientes.

`tools/build_study_eleven.py --live-snapshot ARCHIVO.json` conserva el texto completo de 01–11, refresca las fichas A05 y extrae únicamente campos permitidos del dato incrustado en los visores A10. No despliega el HTML de referencia ni importa ramas A12/A13. Mantiene Main/Extra/Side/Reserva, los IDs y las fotografías acreditadas de cada deck. No modifica MySQL, resultados, rankings ni creaciones manuales. Los estados documentales y la verificación integral pendiente siguen visibles; importar documentación no certifica la ejecución de una jugada.

Comprobaciones de este lector: `python tests/study_eleven_test.py` y `node tests/checked_study_test.cjs`, desde `torneos_yugioh/`. La segunda usa un sitio local interceptado, sin peticiones ni escrituras de producción.

## Archivo anterior · filtro individual de la guía Codex

La revisión anterior apuntaba a `assets/editorial/20261107-checked-content/`. La política histórica `checked-only-v1` habilitaba **370 fichas A05 con check individual A08, 2 interacciones o limitaciones comprobadas y 14 casos puntuales A11 con referencia inequívoca y fuentes primarias vinculadas**. Este archivo se conserva para trazabilidad, pero no es el contenido activo actual.

La primera carga no respetaba el filtro estricto: conservaba la incertidumbre, pero mostraba material que la guía exige excluir. La corrección deja fuera del lector activo las 78 rutas no certificadas, los mapas A06 sin check individual, el contenido A07 sin caso comprobado y los textos completos de apartados que mezclan unidades pendientes. Los originales y el archivo previo permanecen intactos. El apartado 01 es interno; 03 solo sirve para cotejo y no se envía al nuevo registro público. Las fichas Rush conservan sus cartas; no se publica como regla adoptada una Skill local cuya adopción por la liga sigue pendiente.

`tools/import_checked_content.py --live-snapshot ARCHIVO.json` coteja identidades y Main/Extra/Side/Reserva actuales, extrae checks por unidad y no reescribe los documentos de decks. El comando histórico `build_study_eleven.py` delega ahora en este filtro para evitar nuevas cargas permisivas. No se ejecutan apartados posteriores al 11, no se crean tablas, endpoints, bitácoras externas ni informes de entrega. Las versiones existentes de Git y del registro editorial conservan trazabilidad; esto no afirma que exista una bitácora editorial nativa nueva en MySQL.

El lector muestra únicamente pestañas con contenido habilitado y reutiliza las fichas, búsqueda, fotos, ampliación y navegación existentes. El inventario sigue en Decklist y multimedia; Side y Reserva no se mezclan. Una comprobación puntual de costes/resolución nunca se presenta como un combo completo certificado.

Pruebas actuales: `python tests/checked_content_test.py` y `node tests/checked_study_test.cjs` desde `torneos_yugioh/`. La comprobación del archivo histórico 01–11 sigue separada de la elegibilidad de publicación.

## Contenido activo · carga documental completa 01–10

Orden posterior y explícita del propietario: importar **todo lo recibido hasta el apartado 10** y retirar el 11 por falta de certificación. Esta orden reemplaza el filtro de publicación de la revisión anterior para este rango; no acredita legalidad ni cambia el documento guía del autor.

El registro existente `assets/editorial/strategy-content-state.json` apunta ahora a `assets/editorial/20261107-study-ten/`: **11 decks, 110 apartados, 370 fichas, 78 rutas, 373 pasos y 4 Skills independientes**. Los apartados 01 y 03 se conservan como contexto y soporte; las vistas para el jugador corresponden a 02 y 04–10. La información técnica completa 01–10 permanece en los registros de cada deck. Las rutas mantienen su aviso documental y verificación integral pendiente.

El apartado 11 no está en los nuevos registros, ni aparece como pestaña, detalle, selector de cadenas o enlace desde los pasos. Sus comprobaciones anteriores no se mezclan con 01–10. Los originales y las versiones históricas permanecen intactos; tampoco se activan 12–36. Los costes y condiciones que ya pertenecen a una ficha A09 se conservan como parte de esa ficha.

`tools/import_study_ten.py --live-snapshot ARCHIVO.json` utiliza el mapeo existente y coteja el inventario vivo. No modifica Main/Extra/Side/Reserva, Skills, resultados ni perfiles nativos. Pruebas del alcance: `python tests/study_ten_test.py` y `node tests/checked_study_test.cjs`.

## Actualización de vínculos del 8 de noviembre de 2026

La revisión activa `assets/editorial/20261108-links/manifest.json` integra los documentos actuales de `context/gpt/01_INFORMACION_A_IMPORTAR`, hasta A10: 370 identidades, 61 rutas disponibles y 300 pasos, 44 decisiones y 33 prácticas. Conserva las cuatro Skills como recursos separados y los inventarios nativos sin escrituras de base de datos.

Cada ficha presenta «Qué hace», «Cuándo la uso» y «Jugadas y alternativas». Los enlaces se resuelven por identidad y deck, con acceso al paso relacionado; Dark Armed y Garura abren sus nuevos procedimientos de Invocación. Las guías compactas conservan los costes, restricciones y recursos del detalle anterior. Los mapas de motores enlazan a las rutas disponibles.

El expediente contiene 80 rutas documentales: 78 históricas y dos adicionales. Se mantiene la exclusión previa de 19 rutas Rush por dependencias de Skill/reglas sin resolver; esta actualización no las certifica. Las cuatro imágenes de ejemplo de Kashtira son referencias internas, no cuatro rutas nuevas.

El índice del Extra contiene 100 entradas, pero dos son de otras zonas nativas: Olla de la Extravagancia (Main de Volcanic) y Olla de la Prosperidad (Reserva de Volcanic). Se conservan esas zonas y sus enlaces de consulta. El total real es 98 identidades del Extra. La diferencia y los hashes de las fuentes quedan en `context/gpt/03_COMPROBACIONES_Y_NO_IMPORTAR/IMPORTACION_VINCULOS_20261108.json`; los informes internos no se cargan en la interfaz.

## Importación documental completa autorizada

Ante la instrucción expresa del propietario de importar las 19 rutas restantes, la revisión `assets/editorial/20261108-complete/manifest.json` contiene las 80 rutas y 381 pasos del paquete. Pyro incorpora dos rutas, Royal cuatro, Hero seis y Dark Magician siete. Se publican como estudios documentales con Skill local y un aviso visible sobre sus condiciones; no se ratifican las Skills ni se certifica legalidad. No se añade lógica de ejecución al calculador ni se modifica el inventario. Las Skills siguen fuera del Main, Extra y mano. El alcance continúa en A01–A10 y el apartado 11 permanece excluido.
# Actualización de contenido del 9 de noviembre de 2026

La revisión activa `20261109-v14-159` integra A01–A10 de `context/gpt/01_INFORMACION_A_IMPORTAR`: 11 decks, 370 identidades con acción/condición/coste, 105 rutas documentales y 511 pasos, 45 decisiones, 33 ejercicios y cuatro Skills externas. Infernoble incorpora una quinta decisión con tres alternativas. Los inventarios nativos no se modifican.

En «Cartas y sinergias» se separa la lista aprobada de las recomendaciones de construcción. Cada propuesta conserva zona, copias actuales/propuestas, razón y referencias; no hay aplicación automática. La cobertura de etiquetas documentales distingue directa/indirecta y muestra su denominador de identidades del Main; no equivale a consistencia de manos, calidad, legalidad ni win rate. Las 14 rutas que usan prohibidas TCG se leen como referencias históricas, con aviso en biblioteca y lección; las menciones negativas no bloquean otras rutas.

Las ampliaciones conservan su guía técnica, alternativas y condiciones iniciales en desplegables. Las posiciones o zonas finales no indicadas permanecen parciales; importar una secuencia con fotos no certifica su ejecución reglamentaria. Los tres Gaia de Dark Magician explican los materiales ausentes; Donner Kashtira aparece como material de Diosa sin atribuirle una ruta propia de Invocación. Apartado 11 y documentos de comprobaciones quedan fuera del contenido público.
