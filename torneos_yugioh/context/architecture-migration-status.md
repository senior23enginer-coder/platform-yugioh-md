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

Se aíslan el rango de clasificación, los criterios de finalización y la asignación de posiciones, así como la normalización histórica protegida por auditoría, mediante políticas, casos de uso y puertos. No se agregan transacciones nuevas ni se ejecutan operaciones sobre datos de producción. Se conservan las huellas originales y se añaden regresiones; validación integral pendiente.

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
