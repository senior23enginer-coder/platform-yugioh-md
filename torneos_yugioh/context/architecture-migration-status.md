# Estado de migración de arquitectura

Fecha: 18 de noviembre de 2026. Repositorio de aplicación, rama `base`.

## Migraciones verificadas

| Área | Separación implementada | Evidencia |
| --- | --- | --- |
| Mundos | Dominio, comandos/consultas, puertos, HTTP/PDO y transacciones | Pruebas de aplicación, SQLite y navegador |
| Metadatos de decks | Resolución de estilo/arquetipo, proyección y puerto editorial | 31 comprobaciones y ocho caracterizaciones originales |
| Temporada actual | Caso de uso y repositorio de lectura inyectado | Dobles de repositorio y pruebas de arquitectura |
| Reglas deportivas y de edición | 43 algoritmos originales caracterizados y planificador de calendario | Firmas/referencias, evidencia original y comparador canónico |
| Guardado y consultas de combos | Grafo puro, puertos, reloj, transacción y repositorios | Pruebas de puertos, rollback, suite y navegador local |
| Comandos POST | 56 rutas en 55 adaptadores específicos detrás de un puerto | Comparación con las 55 ramas originales, guardas y contexto anidado |
| Calendario compartido y clasificatorios | Cuatro casos de uso, reglas de fecha/cupo y puertos de lectura/escritura/tiempo | 49 comprobaciones y 13 escenarios originales; MySQL específico pendiente |
| Noticias | Normalización, consulta, sincronización, lectura y miniaturas por puertos; adaptadores HTTP, PDO, cURL y parsers | 99 comprobaciones, 33 escenarios originales y 12 algoritmos comparados; sin producción |
| Catálogo e inspector de cartas (backend) | Búsqueda, identidad, lectura local, proveedor, caché e imágenes por puertos | 71 comprobaciones, diez escenarios y tres algoritmos originales; sin proveedor real |
| Guardado y consulta de listas | Reglas puras, catálogo por puerto, bloqueo, transacción, persistencia y auditoría | 88 comprobaciones y 15 escenarios originales; SQLite, sin producción |
| Formato | 266 fuentes seleccionadas con formato estable y control en la suite | `npm run format:architecture:check` |

## Avance del 26 de noviembre de 2026 — validación de temporadas

Migrados los flujos de consulta de temporada, guardas de apertura y validación de fechas de eventos: `SeasonSchedulePolicy` (dominio puro), `ValidateSeasonSchedule` (caso de uso), `SeasonScheduleReader` (puerto), `PdoSeasonScheduleReader` (SQL y bloqueo opcional) y `SeasonScheduleModule` (composición). Las cuatro funciones públicas de `includes/seasons.php` conservan firmas, mensajes y orden de consultas; se conserva la zona horaria PHP vigente y no se alteran los límites transaccionales. Se añaden casos de regresión a `source_architecture_test.php`.

Verificación previa aislada: 229 comprobaciones de equivalencia y puertos con PHP 8.4.24 y dobles de lectura, sin conexión a producción. La suite completa, el formateador PHP y las pruebas MySQL quedan pendientes de ejecución; no se certifica aún la migración completa de temporadas. Posteriormente se separaron guardado, cierre manual y por vencimiento, eliminación y cálculo de período mediante `SeasonLifecycle`, `SeasonDefinition`, `SeasonLifecycleStore` y su adaptador PDO. Conservan las transacciones propias/prestadas, bloqueos, auditoría y consultas nativas. Se añadieron regresiones con dobles de puertos al test de arquitectura; ejecución integral y MySQL pendientes. La resolución del contexto y las guardas de comandos también se separaron mediante `ResolveSeasonContext`, `GuardSeasonAction`, `HandleSeasonCommand` y `SeasonContextReader`; los globals HTTP y mensajes de sesión quedan en la fachada. Se conserva el orden de resolución y la temporada predeterminada. El esquema y las etiquetas de presentación permanecen fuera del dominio. La certificación integral de temporadas sigue pendiente de ejecutar la suite y MySQL.

## Ascensos y descensos configurados de ligas — 26 de noviembre de 2026

Separación de configuración y aplicación de movimientos mediante `ConfigureDivisionMovements`, `ApplyDivisionMovements`, puertos segregados de lectura/escritura, adaptadores PDO y `DivisionMovementPlan` puro. Se conserva el bloqueo de competición, orden de guardas, idempotencia por auditoría, ranking original y selección de participantes con resultados, transacción propia/prestada, auditorías y actualización de contadores. El adaptador de lectura reutiliza la clasificación nativa; los demás flujos de ligas y la certificación MySQL siguen pendientes.

## Inscripción y planificación de ligas — 26 de noviembre de 2026

Se separaron elegibilidad de formato, exclusividad por temporada, bloqueo ordenado de decks y planificación round-robin por división. `LeagueRosterPolicy` y `LeagueSchedulePolicy` son independientes de I/O; `ValidateFormatRoster`, `LeagueRoster` y `PlanLeagueSchedule` usan puertos segregados. Se mantiene el agrupador nativo de equipos como adaptación de compatibilidad. `includes/league_configuration.php` conserva sus firmas sin SQL directo. Regresiones añadidas; ejecución completa y concurrencia MySQL pendientes.

## Configuración y sincronización de equipos — 26 de noviembre de 2026

Se separaron consultas y persistencia de configuración y etiquetas, así como la identificación de perfiles y sincronización de equipos. Se conservaron las firmas y huellas originales (ocho comprobadas); diez comprobaciones aisladas de configuración y creación de equipos pasan en PHP 8.4.24 con dobles de puertos. Se añadieron regresiones de sincronización y perfiles a la suite existente; su ejecución integral queda pendiente. No se ejecutaron escrituras de producción.

## Estructura de ligas y clasificación histórica — 26 de noviembre de 2026

Se separaron sincronización de categorías, mantenimiento GOAT, asignación provisional por ranking y cálculo histórico de standings. `LeagueStructureModule` compone casos de uso y adaptadores PDO; el dominio conserva las fórmulas y ordenación. Se preservan consultas, auditoría y transacción propia del mantenimiento GOAT. Se verificaron las cuatro huellas originales sin reescribirlas. Ocho comprobaciones aisladas de puntos, desempates y categorías pasan en PHP 8.4.24; se comprobó identidad de los dos archivos de dominio con GitHub. Validación integral de MySQL y formato pendiente.

## Elegibilidad de decks y validación de partidas — 26 de noviembre de 2026

Se extraen la selección de decks permitidos, validación de participantes 1v1/2v2/3v3, agrupación por equipos y bloqueo de eliminación por historial a casos de uso, políticas puras y puertos de lectura. Los adaptadores conservan las consultas, la precedencia de admisión y la compatibilidad con formatos, resultados y clasificaciones nativos. Se mantienen las firmas; las cuatro huellas originales fueron verificadas. Nueve comprobaciones aisladas de selección y agrupación pasan en PHP 8.4.24 y los archivos de dominio coinciden con GitHub. La suite integral, formato y pruebas de MySQL siguen pendientes.

## Dependencias y guardas de eventos — 26 de noviembre de 2026

Se extraen la evaluación de prerrequisitos, la guarda de competición y la proyección de resultados a políticas, casos de uso y puertos segregados. Los adaptadores PDO conservan las consultas y la fachada mantiene la caché por competición y la invalidación explícita. Se verificaron las seis huellas originales. Ocho comprobaciones aisladas de prerrequisitos y cierre de temporada pasan en PHP 8.4.24; los tres archivos probados coinciden con GitHub. Validación integral, formato y MySQL pendientes.

## Validaciones de fechas y capacidad de partidas — 26 de noviembre de 2026

Se extraen las guardas de fecha de resultados y programación de partidas a casos de uso con reloj y puertos de lectura. La política pura conserva los estados, días habilitados, horarios, capacidad y sedes; la fachada captura la selección manual y los adaptadores contienen SQL y servicios heredados. Se conserva la prioridad de ocupación multijugador, disponibilidad, conflictos, día/hora, capacidad y sede. Se verificaron las tres huellas originales. Diez comprobaciones aisladas de fechas, horarios, capacidad y sedes pasan en PHP 8.4.24; los dos archivos de dominio coinciden con GitHub. Validación integral, formato y MySQL pendientes.

## Evaluación de partidas y reglas — 26 de noviembre de 2026

Se separan la resolución de reglas de juego, prioridad de reglas por partida/evento, evaluación de duelos y políticas de empate/D5 por modalidad mediante puertos de lectura, adaptadores PDO y dominio independiente. La fachada conserva las firmas; se verificaron las siete huellas históricas y ocho comprobaciones aisladas de políticas con PHP 8.4.24, con identidad de ambos archivos de dominio respecto a GitHub. La validación integral, formato y MySQL siguen pendientes.

## Ciclo de vida y códigos de competición — 26 de noviembre de 2026

Separados el historial de transiciones, la comprobación de cierre, los candidatos clasificatorios y la numeración de códigos en políticas, casos de uso y adaptadores. Se conserva el orden de consultas, los criterios de cierre y la numeración existente sin abrir transacciones nuevas. Se verificaron las seis huellas originales y nueve comprobaciones aisladas de dominio con PHP 8.4.24. Los dos archivos de dominio coinciden con los probados. Validación integral, formato y MySQL pendientes.

## Proyecciones de clasificaciones y calendario — 26 de noviembre de 2026

Separadas la publicación de snapshots y sus recuperaciones, la reconstrucción de jornadas y la baja de eventos de inicio mediante políticas de dominio, casos de uso, puertos de lectura/escritura y adaptadores PDO. Se conserva la identidad de los snapshots y las jornadas, las consultas de clasificación nativas y los límites transaccionales del llamador. Se verificaron las cuatro huellas originales y cinco comprobaciones aisladas de dominio con PHP 8.4.24; se confirmó la identidad de ambos archivos probados. Las pruebas integrales, el formato y MySQL siguen pendientes.

## Clasificación por competición — 26 de noviembre de 2026

La clasificación de competición se separa en una política pura de puntos, ordenación, proyección de workflow y equipos; el caso de uso coordina los puertos de lectura. Los filtros, puntajes, orden y llamadas a adaptadores nativos se mantienen. Se comprobó la huella original y ocho comprobaciones aisladas con PHP 8.4.24. Validación integral, formato y MySQL pendientes.

## Eliminación de competiciones — 26 de noviembre de 2026

La confirmación y restricción por estado se separan de la eliminación SQL; el caso de uso recibe el puerto de persistencia y un límite transaccional propio. Se conserva la secuencia de eliminación y auditoría, sin ejecutar eliminación real durante esta migración. Se comprobó la huella original y cuatro pruebas aisladas del caso de uso con un puerto falso, sin conexión a la base de datos. Configuración integral de eventos, concurrencia y pruebas MySQL pendientes.

## Configuración de competiciones — 26 de noviembre de 2026

Se separa el flujo de configuración mediante puertos de persistencia, contexto, participantes, multimedia y calendario. Las políticas de bloqueo de plantel, cupos y transición quedan independientes de I/O. Se conserva la fachada HTTP y el límite transaccional original. Los adaptadores de servicios todavía reutilizan operaciones nativas. Se verificó la huella histórica de la fachada y 2.168 comprobaciones aisladas de las políticas de configuración, incluidas 2.160 combinaciones de transición de estado contra una caracterización del flujo anterior; el archivo de dominio probado coincide con GitHub. La ejecución completa del caso de uso, el formato y MySQL quedan pendientes.

## Aplicación de divisiones desde clasificatorios y normalización histórica — 26 de noviembre de 2026

Se aíslan el rango de clasificación, los criterios de finalización y la asignación de posiciones, así como la normalización histórica protegida por auditoría, mediante políticas, casos de uso y puertos. No se agregan transacciones nuevas ni se ejecutan operaciones sobre datos de producción. Se verificaron las dos huellas originales y siete comprobaciones aisladas de dominio y normalización con PHP 8.4.24; los tres archivos probados coinciden con GitHub. Validación integral y MySQL pendientes.

## Reconstrucción histórica del calendario clasificatorio — 26 de noviembre de 2026

Se separan emparejamientos, distribución de jornadas, consolidación del último combate y planificación de fechas mediante políticas de dominio y un caso de uso con reloj y puertos de lectura/escritura. Se conserva el alcance de borrado original de combates pendientes, la actualización previa del límite diario y la propiedad transaccional existente. No se ejecutan reconstrucciones de producción. Se verificó la huella original y 7.603 comprobaciones aisladas con PHP 8.4.24, incluyendo emparejamientos sin duplicados para 2 a 40 decks y distribución de fechas para límites diarios 1 a 5; ambos archivos de dominio coinciden con GitHub. Validación integral y MySQL pendientes.

## Generación de combates suizos, de copa y por modelo — 26 de noviembre de 2026

Se separan los casos de uso de programación, las reglas de planificación y los puertos de lectura, escritura y servicios. Los adaptadores preservan las consultas, registros de generación, jornadas y reglas de admisión. Las fachadas conservan la apertura/propiedad de transacciones y el bloqueo serializado original. Se verificaron tres huellas originales y 8.024 comprobaciones aisladas con PHP 8.4.24, incluyendo cupos impares, alternancia de lados, identidades de división y mesa, ligas de 2 a 20 grupos y calendarios de ida/vuelta. Los tres archivos de dominio utilizados coinciden con GitHub. Las dependencias de motores nativos, el formato, las pruebas integrales y MySQL siguen pendientes.

## Generación histórica de eventos — 26 de noviembre de 2026

Se separan generación de ligas, copas, amistosos y mundial, lectura de divisiones/decks y persistencia de combates mediante un plan de dominio y puertos. Los adaptadores preservan el alcance de limpieza y las filas de los algoritmos existentes; no se ejecuta generación ni limpieza sobre datos reales. Se verificaron nueve huellas originales y 1.608 comprobaciones aisladas con PHP 8.4.24 para ligas, copas, amistosos y grupos, incluidos planteles de 2 a 24 decks. Los archivos probados coinciden con GitHub. Validación integral, formato y MySQL pendientes.

## Avance de fases de competición

Se extraen la coordinación serializada, las consultas de fase y las guardas de ganadores a casos de uso y políticas independientes de PDO. La fachada externa conserva el bloqueo por evento; el límite transaccional conserva transacciones prestadas. La creación de rondas, honores y motor de workflow aún se consumen mediante adaptadores nativos. Se verificaron cinco huellas históricas y 518 comprobaciones aisladas de la política de fases con PHP 8.4.24; el blob probado coincide con GitHub. Validación integral y MySQL pendientes.

## Cierre de clasificatorios y registro de honores

Separados la selección de clasificatorio, la comprobación de rondas completas, el reparto de clasificados por división y la selección de campeón/subcampeón. Los casos de uso consumen puertos de lectura y escritura; las fachadas conservan sus firmas. No se ejecuta cierre ni se registra un campeón real. Se verificaron cuatro huellas originales y 70.986 comprobaciones aisladas de invariantes de dominio en PHP 8.4.24, principalmente reparto de 0–100 decks entre 1–12 divisiones y posiciones; los dos blobs probados coinciden con GitHub. La regeneración de liga y el honor de workflow permanecen detrás de adaptadores; pruebas integrales y MySQL pendientes.

## Rondas eliminatorias, avance histórico y programación automática

Se extraen los últimos tres flujos con SQL directo de `includes/events/generation.php`: planificación de ronda, avance histórico por división y programación automática de clasificatorios. Las reglas de emparejamiento y fechas quedan en dominio; se mantienen los puertos para servicios nativos y auditoría. La preparación de la sentencia de inserción de ronda ocurre dentro del adaptador después de resolver la temporada; las transacciones del llamador no se alteran. Se comprobaron las tres huellas originales y 2.202 invariantes aisladas de dominio con PHP 8.4.24; los tres blobs probados coinciden con GitHub. Validación integral y MySQL pendientes.

## Comandos de calendario, equipos y jugadores

Se separan cuatro manejadores POST: reprogramar partida, vincular decks, guardar equipo y guardar jugador. Se preservan códigos de respuesta, auditorías, orden de operaciones y transacción propia del jugador. Se elimina del guardado de equipo una condición GOAT que referenciaba dos variables de competición no definidas en ese comando (no corresponde a su responsabilidad). La evidencia histórica del comparador de formularios se conserva, y las nuevas fachadas se verifican por tokens además de pruebas de puertos. Se ejecutaron 18 comprobaciones aisladas con dobles de puertos, verificando guardas, orden de operaciones y transacciones; los 14 blobs usados coinciden con GitHub. La ejecución integral y MySQL quedan pendientes.

## Guardado de resultados individuales

Se extraen normalización de resultados, protección de versiones y auditoría de turnos, caso de uso de guardado, publicación de snapshots y puertos de reglas, efectos y persistencia. El límite transaccional propio conserva la atomicidad y revierte explícitamente si falla el flujo. Los adaptadores preservan el orden lógico de consultas y operaciones; la preparación de sentencias de snapshots se realiza en cada lectura/escritura. Se añade evidencia histórica del manejador y regresiones. La validación integral, MySQL y mbstring quedan pendientes.

## Configuración de equipos y alineaciones por evento

Se separan el contrato JSON versionado, la validación de modalidad, formato y participantes, la configuración y el congelamiento de alineaciones, y el enlace de los puestos de combate. El caso de uso `TeamEventConfiguration` usa puertos de persistencia y reglas; la fachada conserva el bloqueo de escritura y el adaptador transaccional respeta las transacciones prestadas. Se guardan los cuerpos anteriores para revisión y se añaden regresiones. Se separan asimismo los resultados colectivos, revisión optimista, puestos fijos, proyección individual y efectos estadísticos mediante `SaveTeamMatchResult`, `TeamResultPolicy` y `ExpandTeamIndividualResults`. Se agrega una guarda explícita ante competición desaparecida después del bloqueo. Los servicios nativos permanecen detrás de puertos; falta ejecutar la suite, el formato y las pruebas MySQL.

## Paquetes de importación y exportación de decks

Se separan las políticas puras de ruta, carpeta, inventario, contrato de calculadora, Markdown, presentación y validación, así como la preparación y construcción de exportaciones mediante el puerto de medios. Los lectores JSON/ZIP y de archivos locales permanecen en infraestructura; las funciones públicas originales son fachadas compatibles. Se conserva una copia de las funciones originales para revisión. Se separan también el coordinador de publicación atómica bajo bloqueo, la validación optimista, la transformación de imágenes y los lectores de registros, fuentes e inventario/revisiones. La escritura de archivos, ZIP y reemplazo del puntero permanece en adaptadores locales. Se separa el coordinador de status/export/preview/commit con puerto de sesión, reloj, staging, ZIP e inventario; HTTP conserva captura, CSRF, límites de subida, errores y descarga. Se preservan el TTL de 1.800 segundos, validación optimista de inventario y revisiones, historial inmutable y lock de publicación. Se añade cierre del temporal si falla la preparación. Falta verificar formato, regresión integral y filesystem/BD de pruebas.

## Comandos de inventario y ficha de deck

Se extraen alta manual de carta, importación CSV, guardado del perfil, enlace con equipo, portada, medios y baja lógica con bloqueo por eventos. Se mantienen zonas y límites heredados (sin modificar inventarios reales), respuestas HTTP, transacción propia de importación y auditorías. Los puertos de persistencia y medios conservan los servicios existentes. Regresiones añadidas; pendiente CI integral y MySQL.

## Fuentes documentales del editor de combos

Se separan la identidad estable de rutas, la referencia documental y la proyección de skills/formato mediante `ComboSourcePolicy`. El adaptador editorial lee rutas y contextos sin invocar funciones globales. El feed usa un puerto de lectura y un caso de uso que conserva el ETag, la respuesta 304, el esquema y el Markdown de revisión; HTTP y SQL permanecen en adaptadores. Se mantienen rutas y datos existentes. Validación integral y MySQL pendientes.

## Acumulación y reproducción de estadísticas (H10–H12, primera fase)

Se separan los cálculos puros de elegibilidad, acumulación individual y Tag, Elo multijugador, reproducción cronológica, metagame, logros, cortes, filtros y exportación. El caso de uso recibe un reloj; se conservan las firmas, las versiones de algoritmo, bases de rating y fórmulas existentes. Se guardan cuerpos históricos de las funciones intervenidas. Se extraen además la proyección de hechos deportivos desde filas, la validación de identidades colectivas, la sincronización de revisiones y revocaciones, el hash canónico, el bloqueo estadístico y los puertos de persistencia. Se conserva el retorno temprano dentro de transacciones deportivas. Se separan también la configuración inmutable, bootstrap, esquema y vista de estadísticas, dejando las funciones históricas como fachadas. La publicación de cortes continúa retirada. Comprobación integral y MySQL pendientes.

## Comandos de perfiles de análisis y enfrentamientos (H13)

Se separan normalización de listas y puntuaciones, persistencia, transacción propia/prestada, auditoría y revisiones de perfiles y matchup. Se corrige la captura ausente de `$post` en las dos funciones anónimas que normalizaban listas: antes podían perder el texto enviado; se añade una regresión explícita. No se alteran datos existentes. Se añaden también comandos de dependencias, check-in y selección de logos históricos con puertos, preservando el origen QR/manual, auditorías y lista de destinos permitidos. Validación integral pendiente.

## Reglamentos y configuración administrativa (H13)

Se extraen seis comandos de escritura adicionales: reglas, condiciones, archivo de reglas, reglamentos, preferencias, etiquetas y catálogo. Se preservan límites, auditorías, orden de persistencia y efectos estadísticos; SQL queda en adaptadores y el dominio normaliza datos sin acceso externo. Validación CI pendiente.

## Mantenimiento de eventos y amistosos (H13)

Se extraen siete comandos de archivo, cierre, cambio de estado de clasificatorio, recalculo, movimientos, asignación de divisiones y baja de amistosos. Se conservan respuestas, auditorías y alcance de operaciones; los servicios deportivos siguen consumidos mediante puertos. La eliminación se prueba únicamente en fixtures; no se ejecuta sobre datos reales. Validación CI pendiente.

## Escritura de divisiones y honores manuales (H13)

Se extraen validaciones, normalización, asignación y escritura de divisiones, y la concesión manual de honores con identidad y evidencia confirmadas. Se conserva la propiedad transaccional del llamador y el bloqueo de evento del honor. No se escriben datos deportivos reales. CI pendiente.

## Sincronización offline, FFA y archivo QA (H13)

Se separan la deduplicación y control optimista de formularios offline, normalización y aplicación de acciones FFA, y archivo transaccional de fixtures QA. La ejecución de comandos anidados mantiene el guardado y restauración de contexto HTTP en infraestructura. No se invoca ninguna operación de archivo real. CI pendiente.

## Consultas HTTP de estadísticas, marcador en vivo e historial FFA (H14)

Se separan los filtros y recálculo analítico del controlador, la consulta de publicaciones y la proyección del marcador y del historial FFA. Los casos de uso reciben datos, reloj y puertos; HTTP conserva cabeceras, estados y descarga. Se conserva evidencia de los bloques originales. Las vistas HTML y exportaciones de fichas continúan pendientes. CI pendiente.

## Creación de partidos manuales (H13)

Se separan normalización del formato, validación de participantes y calendario, selección de reglamento, restricciones del modelo de evento, auditoría y escritura de partido. SQL y consultas de servicios quedan en adaptadores, preservando resultados y alcance. CI pendiente.

## Definición de competiciones (H13)

Se separan selección de reglamento/divisiones, formato, movimientos y parámetros operativos; el caso de uso coordina puertos de validación, persistencia, historial y medios. El controlador conserva los datos de entrada y la configuración visual de tipos. Se inicializa explícitamente el identificador de destino cuando no hay una definición válida para guardar, evitando una variable indefinida. No se alteran datos de producción; CI pendiente.

## Cargador de datos y proyecciones (H15)

Se extrae el cargador de datos completo a un caso de uso con puertos de lectura y servicios. Las proyecciones de divisiones/decks y fixtures quedan en dominio, los SQL y normalización de URL en infraestructura, y el reloj se inyecta. Se conserva el backfill existente, la secuencia de consultas, campos y firma pública; las dos entradas fallback históricamente no utilizadas permanecen en la fachada. Regresión integral pendiente.

## Bootstrap (H16, avance)

Se separan las tres operaciones históricas registradas en auditoría mediante un puerto de mantenimiento y un caso de uso que conserva su orden, registro e idempotencia secuencial. La resolución de rutas POST deja de depender de las variables globales. También se separan inicialización de esquema, semillas, contexto de temporada y catálogos, y mantenimiento de configuración/temporada en dos casos de uso con puertos explícitos. Se preservan el orden y los contadores, así como el cálculo intermedio de opciones de catálogo. La composición de respuesta sigue pendiente. CI pendiente.

## HTTP y presentación (H14/H23, avance)

La proyección de exportación de fichas queda como caso de uso puro con entradas explícitas y fecha de generación; conserva filas, cálculos, textos y formato de salida. Las pantallas de jugador y marcador público pasan a vistas dedicadas; el controlador mantiene las cabeceras, validación de identidad y estados. Se añaden pruebas de resultados, duelos y winrate. CI pendiente.

## Frontend (H19/H20, avance)

La cola offline y la generación de identificadores de petición ya no dependen del cierre compartido para su lógica. Se exponen mediante una fábrica independiente con puertos de almacenamiento, reloj, aleatoriedad y UUID; el bootstrap sólo conecta adaptadores del navegador. Se mantiene la clave y el formato persistido, incluida la lectura histórica. Se añade caracterización y se regenerará el bundle mediante el build oficial. Quedan los demás fragmentos, transporte y estado por funcionalidad. CI pendiente.

## Trabajo todavía pendiente para completar toda la plataforma

Estos elementos siguen siendo deuda real; no están certificados como migración completa:

1. `src/Infrastructure/Persistence/legacy-read-model.php` y `includes/bootstrap/load-state.php`: cargador amplio con numerosas proyecciones, inicializaciones y variables compartidas. Separar consultas por módulo y composición de arranque.
2. Calendario, configuración/generación de eventos y temporadas: el reflujo automático, los cupos explícitos y la prioridad de clasificatorios ya están separados (`calendar-scheduling.md`). Faltan generación integral de fixtures, migraciones históricas, activación, cambios manuales y separación del escritor de proyecciones, además de los flujos de temporadas. Preservar bloqueos MySQL, fechas, transacciones y auditoría.
3. Manejadores POST: ahora tienen límites por acción, pero varios aún realizan validación y coordinación dentro del adaptador. Migrar cada flujo restante a puertos y casos de uso; no mover el mismo cuerpo a Application con un PDO inyectado.
4. Importación/exportación de decks y fuentes del editor: separar validación del paquete, sistema de archivos, BD y coordinación de reversión, preservando identidades, historial y sincronización con el repositorio independiente de prompts.
5. Estadísticas: varias fórmulas están en dominio; acumulación/publicación y consultas todavía requieren separación de tiempo, fuentes y persistencia.
6. Noticias y catálogo del inspector: backend separado. Quedan por certificar los bloqueos, intervalos y concurrencia de Noticias en MySQL de pruebas y la integración actual de ambos proveedores. El guardado/consulta de listas también tiene separación propia; queda por migrar su frontend compartido.
7. Frontend legado de `assets/source/javascript`: conserva 37 fragmentos en un cierre compartido. Extraer estado, transporte y controladores por funcionalidad con pruebas; dividir archivos no equivale a módulos independientes.
8. Plantillas mixtas PHP/HTML y puente `store-lib.php`: mantener composición y presentación fuera del dominio, y sustituir gradualmente las dependencias nativas. No aplicar masivamente un formateador experimental a plantillas.

## Condición de cierre

Cada flujo debe tener responsabilidades y dependencias explícitas, reglas independientes de I/O, adaptadores sustituibles, contratos preservados y pruebas apropiadas. Se deben verificar las transacciones y concurrencia en un MySQL de pruebas antes de certificar esa integración. Una suite local correcta no equivale a un despliegue ni a certificación de producción. No existe un porcentaje global de cumplimiento inferido del número de archivos movidos.
