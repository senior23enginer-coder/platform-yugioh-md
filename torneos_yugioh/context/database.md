# Especificacion de base de datos

## Conexion y propiedad

El modulo utiliza la conexion MySQL existente de Station X. Las tablas propias se identifican con el prefijo `sx_ygo_`; no se crea una base separada ni se guardan resultados definitivos en el navegador.

## Grupos de tablas

### Nucleo competitivo

- `sx_ygo_seasons`, `sx_ygo_divisions`.
- `sx_ygo_teams`, `sx_ygo_decks`.
- `sx_ygo_competitions`, `sx_ygo_competition_decks`.
- `sx_ygo_competition_dependencies`, `sx_ygo_competition_status_history`.
- `sx_ygo_matches`, `sx_ygo_match_results`.

### Configuracion y reglas

- `sx_ygo_catalog_items`.
- `sx_ygo_rulesets`, `sx_ygo_ruleset_versions`.
- `sx_ygo_game_rules`, `sx_ygo_game_rule_conditions`.
- `sx_ygo_match_types`.
- `sx_ygo_settings`, `sx_ygo_ui_labels`.

### Duelo en vivo, auditoria y offline

- `sx_ygo_live_duel_state`: estado vigente, revision y payload del duelo.
- `sx_ygo_turn_audit`: historial append-only de turnos.
- `sx_ygo_sync_requests`: idempotencia y control de solicitudes repetidas.
- `sx_ygo_audit_log`: cambios administrativos y de resultados.
- `sx_ygo_checkins`: registro de llegada cuando el evento lo utiliza.

### Historia e inteligencia competitiva

- `sx_ygo_match_snapshots`, `sx_ygo_standing_snapshots`.
- `sx_ygo_global_ranking_history`, `sx_ygo_elo_history`.
- `sx_ygo_meta_history`.
- `sx_ygo_event_summaries`, `sx_ygo_honors`.
- `sx_ygo_medal_progress`, `sx_ygo_medal_unlocks`.

### Contenido

- `sx_ygo_deck_cards`: Main, Extra y Side Deck.
- `sx_ygo_deck_media`.
- `sx_ygo_logo_history`.

Existen tablas heredadas de eventos y jugadores, pero el modulo de jugadores no es parte del producto solicitado.

## Datos estructurados

Algunos detalles variables se almacenan como JSON controlado: historial de turnos, estado de sincronizacion, snapshots de deck/cartas, desglose estadistico y resumen final. Deben validarse al escribir y exponerse siempre mediante modelos normalizados.

## Invariantes

- `sx_ygo_seasons` conserva `period_type` (annual, semiannual o quarterly), `starts_on`, `ends_on` y `closed_at`.
- Todo evento requiere una temporada existente; la clave foránea `fk_ygo_competition_season` impide referencias huérfanas y eliminar temporadas con eventos.
- Las fechas de eventos y partidas deben estar dentro del período de la temporada.
- Finalizar una temporada cambia los eventos aún abiertos a `closed`, conserva los resultados y bloquea nuevas capturas, cambios de configuración y operaciones de duelo en vivo. Los eventos ya finalizados o anulados conservan su estado deportivo.
- El vencimiento se comprueba al acceder a la aplicación: una temporada con fecha de fin anterior al día actual queda finalizada y sus eventos abiertos se cierran.
- Solo se eliminan temporadas vacías, sin eventos, divisiones ni registros de calendario asociados.
- Las seis ligas y Copa Leyendas reorganizadas el 2026-10-15 siguen vinculadas a Temporada 2026, ciclo anual del 2026-01-01 al 2026-12-31.

- Todo deck activo pertenece a un equipo y a un formato identificable.
- Todo resultado referencia el match y evento correctos.
- Un duelo finalizado conserva ganador, LP, cierre, decks participantes y procedencia disponibles.
- El numero de turno nunca puede ser inferior al ya persistido.
- Una revision antigua no sobrescribe una sesion mas reciente.
- Un snapshot exacto no se reemplaza por datos actuales del deck o equipo.
- Los historicos inferidos permanecen marcados como inferidos.
- El cierre del evento congela clasificacion, campeon, subcampeon, metagame y mejores estadisticas.

## Estado de datos observado

La fotografia de produccion del 27 de septiembre de 2026 se encuentra en `platform-specification.md`. Los vacios conocidos son listas de cartas, multimedia, honores y resumenes finales persistidos; deben cargarse o generarse, no inventarse.
