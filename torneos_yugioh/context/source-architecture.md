# Organizacion y limites del codigo fuente

## Donde editar

| Responsabilidad | Ubicacion |
| --- | --- |
| Entrada web y composicion de la solicitud | `index.php` |
| Politicas puras de emparejamiento y entidad temporada | `src/Domain/` |
| Caso de uso de temporada y puerto de lectura | `src/Application/Seasons/` |
| Adaptador de temporadas y persistencia heredada | `src/Infrastructure/` |
| Servicios existentes de eventos, calendario, equipos, partidas y configuracion | `includes/<feature>/` |
| Carga y datos iniciales de la solicitud | `includes/bootstrap/` |
| Respuestas HTTP y exportaciones | `includes/http/` |
| Proyecciones para presentacion | `includes/presentation/read-model.php` |
| Cabecera, navegacion, dialogos y pie | `includes/presentation/layout/`, `includes/presentation/dialogs/` |
| Pantallas PHP | `includes/presentation/screens/` |
| Fuentes editables del frontend existente | `assets/source/javascript/`, `assets/source/styles/` |
| Funciones frontend independientes y motores puros nuevos | `assets/modules/<feature>/` |
| Bundles publicos generados | `assets/app.js`, `assets/app.css` |

## Dependencias y arquitectura hexagonal

El dominio no conoce PDO, HTTP, sesiones ni archivos de configuracion. `GetCurrentSeason` depende de `SeasonRepository`, no de una consulta SQL. La composicion inyecta `LoadedSeasonRepository`, que adapta datos ya leidos sin repetir consultas. Las pruebas utilizan otra implementacion del mismo puerto.

Los algoritmos de emparejamiento viven en clases de dominio independientes. Las funciones publicas originales permanecen como delegados para no romper llamadas existentes. No se agregan interfaces a algoritmos puros solo para aumentar el numero de clases.

Los modulos procedurales heredados son adaptadores de compatibilidad; **no son una conversion completa a arquitectura hexagonal**. Conservan su API y parte de la mezcla historica entre reglas y PDO. No introducir dependencias de esos adaptadores dentro del dominio. Migrar cada nueva regla a un caso de uso y un puerto cuando requiera persistencia o servicios externos.

## Frontend y compilacion

`npm run build` reconstruye los dos bundles publicos a partir de `assets/source/manifest.json`. `npm run build:check` comprueba que no exista deriva. No editar directamente los bundles: los cambios se perderian al compilar.

Los fragmentos JavaScript del adaptador existente conservan un cierre compartido y un orden explicito. No son ES modules independientes ni deben agregarse individualmente al HTML. Esto preserva el estado y la inicializacion mientras cada controlador se migra a un modulo con dependencias explicitas. Los modulos nuevos de `assets/modules/` siguen siendo archivos independientes.

Los estilos conservan reglas completas y el orden original de cascada. Sus nombres indican el area dominante; algunos segmentos contienen overrides de compatibilidad. Consolidarlos requiere pruebas visuales de escritorio y movil.

## Transacciones, rutas y contratos

- Los adaptadores heredados conservan sus bloqueos y transacciones. En Mundos, el repositorio conserva los bloqueos y el adaptador transaccional controla exclusivamente la transaccion que inicia.
- `src/legacy-functions.php` carga declaraciones; no inicia conexiones ni ejecuta comandos.
- `src/autoload.php` registra clases y rutas estables de aplicacion; no inicializa HTTP ni persistencia.
- Las vistas usan `YGO_APP_ROOT` cuando necesitan incluir otra vista, conservando el significado de las rutas antiguas.
- `context/source-function-map.json` permite localizar las funciones trasladadas y comprobar sus cuerpos normalizados. Si una funcion cambia de comportamiento, revisar sus pruebas y actualizar deliberadamente ese contrato; no borrar la comprobacion para ocultar una diferencia.
- Los comentarios explican responsabilidades y restricciones; los contratos de parametros y retornos permanecen tipados.

## Validacion y limites

La reorganizacion usa extracciones incrementales. Para cada modulo y vista aceptados se comprobo sintaxis y equivalencia del HTML completo antes y despues en una prueba aislada sin acceso a base de datos. Los algoritmos de dominio se comparan con casos de caracterizacion obtenidos antes de extraerlos.

`npm test` ejecuta el control de compilacion, los limites del dominio, los contratos de funciones y la suite funcional existente. Las pruebas con base de datos real y los escenarios de produccion no equivalen a esas pruebas locales. La reorganizacion no modifica inventarios, resultados, temporadas ni formulas de ranking.

La deuda que sigue existiendo es visible: algunos controladores heredados todavia comparten variables, el despachador POST mantiene acciones grandes y ciertas consultas necesitan repositorios especificos. No describir este estado como 100% SOLID o hexagonal. La estructura actual permite migrarlos sin volver a mezclar el nucleo con los adaptadores.

## Recorrido de una solicitud

`index.php` mantiene primero el rechazo del API de Reportes retirado, antes de cargar dependencias. Luego registra clases y funciones de compatibilidad, carga dependencias y defaults, procesa la solicitud y lee datos (`bootstrap/load-state.php`), construye las proyecciones, despacha respuestas HTTP y finalmente renderiza `presentation/page.php`. Los includes mantienen el ambito original: no envolverlos en funciones ni reordenarlos sin caracterizar los datos compartidos.

El mapa conserva 132 contratos publicos originales; las delegaciones nuevas se cotejan con sus implementaciones y evidencias de origen. La extraccion inicial cubrio 132 funciones publicas y hay 21 pantallas separadas. Las 37 fuentes JavaScript comparten el cierre anterior; los 37 segmentos CSS conservan la cascada. La separacion no implica que cada adaptador procedimental sea un repositorio o caso de uso independiente.

## Mundos: primera migración completa de una funcionalidad con persistencia

El recorrido vigente es:

`attributes-http.php → DeckWorldsController → AssignDeckWorlds / DeckWorldsQueries → puertos → adaptadores PDO`.

- `src/Domain/DeckWorlds/`: selección ordenada de uno a tres mundos, catálogo e identidad/versionado de la asignación. Sin SQL, sesión ni HTTP.
- `src/Application/DeckWorlds/`: comando tipado, asignación y consultas. Los puertos de lectura, escritura y elegibilidad son independientes; reloj y transacción se inyectan mediante `Application/Shared`.
- `src/Infrastructure/Persistence/DeckWorlds/`: esquema explícito, consultas, bloqueos de fila e historial. `Composition/DeckWorldsModule` conecta las dependencias sin realizar consultas al construirse.
- `src/Infrastructure/Http/DeckWorlds/`: autorización y conversión de solicitudes/respuestas. El include nativo captura sesión y variables HTTP; el controlador recibe un objeto de solicitud y una fábrica diferida.
- `includes/decks/attributes.php`: compatibilidad con las funciones existentes, delegando a las capas anteriores.
- `assets/modules/deck-attributes/`: `model.js` mantiene reglas puras; `api.js` encapsula el transporte con dependencias inyectadas; `ui.js` administra la interacción y el DOM.

El comando bloquea el deck antes de consultar/modificar su asignación. Mantiene revisión esperada, guardados idempotentes e historial atómico. `PdoTransactionBoundary` confirma o revierte solo una transacción iniciada por él; si recibe una transacción del llamador, conserva su propiedad y propaga el error. No se crean tablas desde el dominio ni desde los constructores.

Pruebas aisladas: `deck_worlds_application_test.php` usa dobles de puertos sin PDO; `deck_worlds_persistence_test.php` verifica SQLite, historial y transacciones propias/prestadas; `deck_worlds_api_test.cjs` verifica el contrato de transporte sin navegador. Las pruebas previas del módulo siguen vigentes.

## Formato reproducible del código migrado

`npm run format:architecture` aplica formato; `npm run format:architecture:check` detecta deriva y forma parte de `npm test`. La lista explícita está en `scripts/format-architecture.cjs`: incluye PHP puro migrado y el módulo frontend de Mundos. Excluye plantillas mixtas PHP/HTML, bundles generados y contenido editorial. El formato consistente no sustituye la revisión de responsabilidades y contratos.

## Estilo, arquetipo e inventario visible del catálogo

El recorrido de lectura ahora es `ygo_deck_list_metadata → BuildDeckListMetadata → DeckStudyReader`. La fachada conserva su firma y los campos que consumen listado y ficha (`display_style`, `display_archetype`, `card_count`, `has_cards`). Reutiliza decks, perfiles y cartas ya cargados; no agrega consultas SQL ni escrituras.

- `Domain/DeckMetadata/DeckMetadataResolver`: decide la prioridad entre estilo manual vigente, estilo nativo y estudio identificado. Los formatos y textos vacíos administrativos no se convierten en estilos. El arquetipo nativo específico se conserva; si está vacío o repite el nombre, usa el arquetipo documentado cuando exista.
- `Application/DeckMetadata/BuildDeckListMetadata`: combina el catálogo con una instantánea editorial por ejecución. Respeta el orden recibido de perfiles, descarta las revisiones retiradas y cuenta únicamente filas guardadas con nombre y cantidad positiva, manteniendo todas las zonas como antes.
- `Application/DeckMetadata/DeckStudyReader`: puerto de lectura sustituible en pruebas; `DeckStudySnapshot` contiene perfiles identificados y la política de revisiones retiradas.
- `Infrastructure/Editorial/FilesystemDeckStudyReader`: lee el registro activo de `assets/editorial`, resuelve rutas reales y exige pertenencia a esa carpeta e igualdad de ID antes de devolver un perfil. No lee prompts ni crea cachés persistentes; cada nueva ejecución observa la versión actual del registro.

Las ocho caracterizaciones en `tests/fixtures/deck-metadata-characterization.json` se capturaron con la función original antes de sustituirla. Cubren prioridades, revisiones retiradas, claves/orden del catálogo, cantidades, fuentes ausentes o malformadas y rechazo de rutas o identidades ajenas. `deck_metadata_architecture_test.php` las ejecuta en carpetas temporales aisladas, además de probar el caso de uso con un puerto en memoria y la actualización de fuentes sin caché obsoleta.

El control arquitectónico de `source_architecture_test.php` rechaza acceso directo a archivos/red/sesión y PDO en Domain/Application, infraestructura en ambas capas y Application dentro del dominio. El formateador incluye estos nuevos archivos y el adaptador existente de temporadas.

## Reglas de dominio y comandos nativos · 18 de noviembre de 2026

Se extrajeron 42 algoritmos existentes a 19 clases de dominio: definición/ejecución/recuperación de fases, clasificación desde fuentes verificadas, alineaciones, multijugador, movimientos de división, secuencias BO1/BO3/BO5, turnos, fechas de cierre, límites de edición, detalles de pasos, cuotas de calendario, fórmulas y proyecciones estadísticas y anotaciones de cartas. Las funciones públicas conservan parámetros, referencias y valores predeterminados, y delegan al dominio. No se modificaron reglas deportivas ni fórmulas.

`context/domain-extractions.json` registra destinos y huellas originales. `tests/fixtures/domain-originals.json` conserva la evidencia previa. La prueba verifica primero esa evidencia contra las huellas intactas y luego compara ambos algoritmos con el mismo formato canónico estable, restaurando solo los nombres de llamadas extraídas. No se sustituyen las huellas originales para aceptar el formateador. Los contratos iniciales de las otras funciones siguen vigentes.

El antiguo despachador POST, de unas 1.900 líneas, ahora conserva las guardas, inicialización de temporada y bloqueo externo y delega 56 acciones a 55 adaptadores específicos. `Application/Commands` contiene el comando capturado, su puerto y el despachador sin PDO ni superglobales. `Infrastructure/Composition/NativeFormCommands` mantiene dos fases de registro: estadísticas antes de las guardas de temporada y el resto después, igual que antes.

`Infrastructure/Http/FormActions/NativeFormHandler` restaura el contexto PHP en `finally`, incluso en comandos anidados de sincronización offline. Los manejadores usan el formulario capturado y conservan SQL, auditoría y transacciones nativas. Son **adaptadores heredados segregados**, no casos de uso íntegramente migrados. Las 55 implementaciones se comparan con la evidencia original, y se comprueban las 56 rutas y su fase de autorización. El bloqueo MySQL externo no fue trasladado ni reducido.

## Creador de combos: guardado y lectura por puertos

`Application/Combos/SaveCreatedCombo` coordina inventario bloqueado, revisión esperada, validación del grafo, persistencia, Markdown de origen e historial atómico. Sus dependencias son `ComboInventory`, `ComboRepository`, `ComboSources`, `TransactionBoundary` y `Clock`. `PrepareComboDocument` valida primero con `Domain/Combos/ComboGraphPolicy` y verifica después la identidad documental mediante el puerto de fuentes. `ComboQueries` limita listado e historial al deck seleccionado.

Los adaptadores PDO están en `Infrastructure/Persistence/Combos`; los constructores no crean tablas ni abren transacciones. Se conserva UTC para fechas del editor y la política original de requerir una transacción propia al guardar: una transacción preexistente se rechaza sin confirmarla ni revertirla. Un reintento obsoleto idéntico no genera una revisión nueva; un cambio concurrente o de inventario sigue rechazándose. El historial y su fuente se guardan juntos o se revierten juntos. `NativeComboSources` adapta todavía el lector editorial y renderizador previos; su presencia hace explícita esa dependencia pendiente.

La nueva prueba `combo_ports_test.php` usa puertos en memoria y SQLite temporal. La regresión de navegador usa PHP/SQLite local y comprueba edición, drag/drop, conexiones, guía, guardado, CSRF, conflicto y móvil. Ninguna de estas pruebas certifica integración MySQL de producción.

## Inventario y deuda pendiente

`php scripts/source-inventory.php --summary` permite revisar funciones nativas y dependencias sin ejecutar el arranque ni abrir la BD. Es un inventario estático: la ausencia de I/O directo no certifica por sí sola separación de dominio. El seguimiento completo y los límites pendientes están en `architecture-migration-status.md`.

## Calendario compartido y coordinación de clasificatorios

El reflujo automático, la asignación explícita de cupos y la prioridad de clasificatorios tienen casos de uso en `Application/Calendar`, reglas deterministas en `Domain/Calendar` y adaptadores PDO/configuración/tiempo en Infrastructure. Las fachadas existentes conservan sus firmas y los llamadores siguen siendo dueños de bloqueos y transacciones. El detalle del contrato, pruebas y límites está en `calendar-scheduling.md`.

`calendar_architecture_test.php` contrasta 13 escenarios originales y comprueba puertos en memoria, orden de operaciones y reversión por el llamador. La comparación de firmas y algoritmos originales incluye ahora 43 entradas. No se reemplazó ninguna huella anterior. El formateador controla 186 fuentes migradas.


## Backend hexagonal de Noticias · 18 de noviembre de 2026

- `Domain/News`: texto, clasificación, filtros, fusión/huella de contenido y política de URLs. Los hosts se inyectan; no conoce PDO, cURL, DOM, sesiones ni archivos.
- `Application/News`: descubrir, normalizar, recopilar, sincronizar, consultar, marcar lectura/favoritos y obtener miniaturas. Sus puertos separan catálogo de proveedores, transporte, documentos, tiempo de publicaciones, consultas, escritura, bloqueo, estado de fuentes y caché.
- `Infrastructure/Configuration/News`: configuración nativa de las cuatro fuentes. `Network/News`, `Syndication`, `Time` y `Cache/News` adaptan cURL, libxml, fechas y archivos; `Persistence/News` conserva SQL y esquema; `Http/News` recibe solicitudes capturadas y emite respuestas.
- `Composition/NewsModule` conecta dependencias sin consultas ni creación de tablas. `includes/news.php` y `news_http.php` mantienen las funciones/endpoints existentes; únicamente el arranque HTTP captura sesión y conecta la BD.

El intento de fuente se registra antes de consultar el proveedor. La importación completa y el estado de éxito comparten una transacción, bajo el mismo bloqueo MySQL. Se conservan los límites, reintentos, filtros, estados de lectura/favoritos y presupuesto de dos enriquecimientos. Las miniaturas mantienen bloqueo por archivo, MIME, límite de descarga y reemplazo atómico.

Corrección explícita de propiedad: una sincronización requiere transacción propia. Si un llamador ya abrió una, se rechaza esa importación y se conserva abierta para su dueño. El código anterior podía revertir indebidamente la transacción prestada en un error; ese comportamiento no se reproduce. El endpoint nativo utiliza una conexión sin transacción iniciada.

`news_architecture_test.php` comprueba 99 condiciones, incluidos 33 resultados capturados antes de sustituir las fachadas y 12 comparaciones canónicas de algoritmos originales. También verifica rollback, idempotencia, preservación de estado, filtros parametrizados, presupuesto de enriquecimiento fallido, errores HTTP, token y liberación de sesión, caché y aislamiento de URLs. Las huellas originales se conservan en `tests/fixtures/news-originals.json`; el mapa de destinos está en `context/news-extractions.json`.

Se usan puertos en memoria, SQLite y una carpeta temporal aislada, sin proveedores externos ni producción. SQLite no certifica `GET_LOCK`, `DATE_ADD`/`DATE_SUB`, `INSERT IGNORE` o concurrencia MySQL. Esas expresiones se preservan y el esquema/transportes/parsers se comparan con la evidencia original; la integración MySQL sigue pendiente. No se cambió el frontend ni se desplegó esta reorganización.


## Catálogo e inspector: arquitectura del backend · 18 de noviembre de 2026

El recorrido HTTP conserva `?api=deck_builder`: `includes/deck_builder.php` captura la solicitud y delega a `Infrastructure/Http/CardCatalog/CatalogController`. El controlador mapea detalle local/proveedor, búsqueda e imagen y devuelve una respuesta; el emisor es responsable de cabeceras, JSON y bytes. Las fachadas nativas mantienen las firmas utilizadas por el editor existente.

`Domain/CardCatalog` valida identidad y parámetros de búsqueda. `Application/CardCatalog` separa `SearchCards`, `LookupCard`, `InspectCachedCard` y `GetCardImage`; depende únicamente de los puertos `CatalogCache`, `CatalogProvider`, `CatalogImages` y `Clock`. `CardSummary` es una proyección del efecto original del proveedor, no un resumen estratégico inventado.

`Infrastructure/Cache/CardCatalog` implementa archivos, lectura local sin crear carpetas, bloqueo por búsqueda/imagen y reemplazo atómico. `Infrastructure/Network/CardCatalog` conserva el bloqueo y espera compartidos del proveedor, límites de bytes, TLS, tiempos y estados HTTP admitidos. `Composition/CardCatalogModule` conecta las dependencias sin I/O en constructores.

Se conservan búsqueda de veinte cartas, clave `pagination-v2`, frescura de 48 horas, recuperación de respuesta vencida únicamente ante fallo de descarga/JSON, identificación exacta, imágenes grandes y miniaturas separadas, JPEG y descargas solo para IDs catalogados. La consulta local nunca descarga ni fabrica texto. La política de caché y el reloj pueden sustituirse sin tocar el sistema de archivos.

`card_catalog_architecture_test.php` comprueba 71 condiciones con puertos en memoria y una carpeta temporal única: diez escenarios nativos, evidencia original intacta, tres comparaciones de algoritmos, validación, paginación, caché fresca/vencida, fallos, identidad, modo local, texto exacto, HTTP e imágenes. No se llamó a YGOPRODeck ni a producción. Quedan pendientes una comprobación de proveedor real y concurrencia multiproceso en el entorno objetivo; las pruebas locales no los certifican.

Esta migración abarca el catálogo del inspector/editor. El guardado SQL de listas y sus transacciones permanece separado y pendiente de migración; no se modificaron inventarios, cantidades, anotaciones o fuentes de prompts. No se cambió la interfaz ni se desplegó este refactor.


## Guardado y consulta de listas · 18 de noviembre de 2026

La fachada `ygo_deck_builder_save` delega a `Application/Decklists/SaveDecklist`. El caso de uso depende de `DecklistReader`, `DecklistWriter`, `DeckCardCatalog`, `EventWriteLock` y `TransactionBoundary`; no recibe PDO ni accede a sesión, archivos o red. `Domain/Decks/DecklistRules` valida versión esperada, filas, cantidades técnicas, secciones, pertenencia, duplicados, nombres documentales y anotaciones. Los límites y el orden de las validaciones anteriores se conservan.

La validación de una fila tiene dos fases explícitas: preparar antes de resolver su ID de catálogo y completar después de resolverlo. Así se mantiene el orden de errores y de consultas externas. El nombre documental no se sustituye por el nombre del catálogo de imágenes; Main, Extra, Side y Reserva siguen independientes. Se conserva la combinación de anotaciones ya implementada en `CardAnnotations`.

`Infrastructure/Persistence/Decklists/PdoDecklists` conserva `FOR UPDATE`, el orden de filas, reemplazo y auditoría antes/después. `NativeEventWriteLock` es un puente al bloqueo MySQL reentrante existente, sin cambiar su conexión ni propietario. Se mantiene el orden completo: bloqueo externo → transacción → snapshot bloqueado → versión/filas/catálogo → reemplazo → snapshot final → auditoría → commit propio → liberar bloqueo. Si la transacción es prestada, solo su llamador confirma o revierte. Los fallos de catálogo ocurren antes de borrar cartas; un fallo posterior de persistencia/auditoría revierte toda la transacción propia.

`Infrastructure/Http/Decklists/DecklistController` valida método, permiso, límite de cuerpo y token antes de guardar. El include nativo captura la sesión y el cuerpo; los errores de BD/JSON mantienen sus mensajes públicos existentes. `DecklistModule` compone sin inicializar esquema ni abrir transacciones.

`decklist_architecture_test.php` ejecuta 88 comprobaciones, incluidas 15 caracterizaciones capturadas antes de sustituir las fachadas (respuesta, error, inventario y auditoría). Prueba además orden de puertos, identidad de catálogo, otros decks intactos, rollback, transacciones prestadas, versión obsoleta, HTTP y composición sin I/O. Sus originales y huellas permanecen en `tests/fixtures/decklist-originals.json`. No se accedió a producción ni a proveedores. La prueba SQLite elimina únicamente `FOR UPDATE` en un adaptador de pruebas y usa un sustituto del bloqueo MySQL; no certifica concurrencia MySQL real.

La importación/exportación de paquetes ZIP y la sincronización de fuentes editoriales siguen siendo flujos distintos pendientes de migración. El frontend del editor también permanece en su fuente compartida; esta entrega no lo presenta como modularizado.
