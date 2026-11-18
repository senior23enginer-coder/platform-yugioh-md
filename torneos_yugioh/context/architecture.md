# Arquitectura de la plataforma YUGIOH

## Estado actual

La reorganizacion vigente y sus limites estan descritos en `source-architecture.md`. El nucleo extraido vive en `src/Domain`, `src/Application` y `src/Infrastructure`; los modulos procedurales restantes son adaptadores de compatibilidad. Las pantallas se separan en `includes/presentation/screens` y el frontend se compila desde `assets/source`.

La aplicacion es un modulo PHP monolitico, renderizado en servidor y enriquecido con JavaScript. No usa Angular ni expone una API REST general. Comparte la conexion MySQL de Station X, pero mantiene sus datos funcionales en tablas con prefijo `sx_ygo_`.

## Componentes principales

- `index.php`: composicion de la solicitud; delega bootstrap, proyecciones de lectura, endpoints y presentacion en archivos separados.
- `assets/source/`: fuentes editables por area del frontend heredado; `assets/app.js` es el bundle generado.
- `assets/source/styles/`: estilos editables en orden de cascada; `assets/app.css` es el bundle generado.
- `includes/catalogs.php`: catalogos editables y normalizacion.
- `includes/turns.php`: invariantes del contador e historial de turnos.
- `includes/competitive_intelligence.php`: rankings, Elo, tiers, metagame, snapshots y resumenes.
- `includes/simple_pdf.php`: generacion de informes PDF descargables.
- `assets/player-duel.js` y `assets/player-duel.css`: pantallas moviles independientes de los lados A y B.
- `service-worker.js` y `manifest.webmanifest`: instalacion PWA, cache y soporte offline.

## Capas de ejecucion

1. Bootstrap y migracion idempotente del esquema.
2. Lectura de configuracion, catalogos, eventos y reglas versionadas.
3. Validacion de acciones en servidor y escritura transaccional.
4. Construccion del modelo de lectura para la pantalla solicitada.
5. Renderizado PHP y comportamiento interactivo en JavaScript.
6. Sincronizacion incremental de sesiones de duelo mediante revision.

## Sesion de duelo

El juez controla el flujo completo desde la captura de resultados. Cada match puede abrir dos vistas moviles con `?duel_player=A|B&match_id=ID`. Las tres pantallas comparten `sx_ygo_live_duel_state`.

- Cada cambio incrementa la revision.
- Un cliente desactualizado recibe conflicto y debe refrescar su estado.
- `sx_ygo_turn_audit` conserva los turnos agregados y su lado.
- El contador de turnos es monotono: nunca puede disminuir.
- Las operaciones de LP y fin de turno pasan por el servidor.

## Navegacion

La aplicacion usa parametros de consulta para seleccionar entidades y hashes para abrir modulos, por ejemplo `?event_id=15#evento_ficha`. Las vistas publicas son de solo consulta:

- `?public_live=1`: marcador de partidas activas y proximas.
- `?public_season=ID`: pagina publica de una temporada.

## Principios

- La base de datos es la fuente de verdad.
- Las reglas del evento se congelan por version y no se reinterpretan silenciosamente.
- Los resultados historicos conservan snapshots y procedencia.
- La interfaz no decide por si sola si un match termino; esa decision corresponde al evaluador de reglas del servidor.
- Jugadores, jueces y autorizacion por roles no forman parte del alcance: existe un unico operador administrativo.
