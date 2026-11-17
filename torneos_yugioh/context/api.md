# Interfaces HTTP y acciones

## Criterio general

La plataforma usa formularios PHP y algunos endpoints JSON especializados. Esta lista documenta el contrato existente; no representa una API REST publica.

## Lectura y sincronizacion

- `GET ?api=duel_session&match_id=ID`: obtiene la sesion sincronizada del match.
- `POST ?api=duel_session&match_id=ID`: aplica una operacion con actor, revision y payload.
- Operaciones de sesion: `sync`, `end_turn`, `lp_change` y `surrender`.
- `GET ?api=live`: entrega las mesas activas y proximas para actualizacion automatica.
- `GET ?public_live=1`: marcador publico de partidas.
- `GET ?public_season=ID`: consulta publica de una temporada.
- `GET ?duel_player=A|B&match_id=ID`: pantalla movil del lado indicado.

Una revision desactualizada debe responder `409 Conflict`; el cliente recarga el estado vigente y no pisa cambios concurrentes.

## Exportaciones

- `GET ?export=TIPO&format=csv|xls|pdf`: exportacion de listados soportados.
- `GET ?export=entity_report&entity_type=deck|team|event|season&entity_id=ID&format=pdf`: informe individual completo.

Los informes individuales deben incluir identidad, estadisticas, historial, medallas, matchups y snapshots disponibles. Deben indicar cobertura y procedencia cuando falten datos.

## Acciones POST principales

### Decks y equipos

- `save_deck`, `remove_deck`.
- `save_team`, `save_team_deck_links`.
- Acciones de cartas, multimedia, logo y palmares implementadas en la ficha correspondiente.

### Eventos y programacion

- `save_competition`, `configure_competition`.
- `save_match_schedule`, `create_match`.
- `save_competition_dependency`, `delete_competition_dependency`.
- `archive_competition`, `delete_competition`.
- `delete_manual_friendly`.
- `advance_competition`, `advance_phase`, `finalize_competition`.
- `set_qualifier_status`, `apply_qualifier_divisions`, `apply_league_movements`.
- `recalculate_competition_standings` para procesos internos o administrativos, no como accion prominente en la ficha.

### Resultados y configuracion

- `save_match_result`.
- `save_division`, `save_ruleset`, `save_game_rule`, `archive_game_rule`.
- `save_catalog_items`, `save_settings`, `save_ui_labels`.
- `sync_offline_form` para reproducir una operacion encolada.

## Validaciones obligatorias

- El evento del formulario debe coincidir con el match afectado.
- El servidor evalua BO3, BO5 y reglas personalizadas antes de abrir otro duelo o cerrar el match.
- Seleccionar un resultado en la interfaz no guarda ni recarga automaticamente; el operador completa los campos y confirma.
- LP, turnos y cierre se persisten juntos con el resultado del duelo.
- Las acciones destructivas requieren confirmacion y dejan auditoria.
- Las vistas publicas nunca admiten escritura.
# Temporadas (2026-10-15)

El módulo `#temporadas` permite crear, consultar, editar, finalizar y eliminar temporadas vacías. `Divisiones` se abre desde el botón de `#torneos` y deja de ser una entrada del menú lateral.

Acciones POST: `save_season`, `close_season` y `delete_season`. El alta/edición recibe `season_id`, `season_name`, `season_period`, `season_starts_on` y `season_status`; el servidor calcula la fecha final según el ciclo. El cierre es transaccional e idempotente. No reabre temporadas finalizadas ni asigna campeones a eventos incompletos.

Crear un evento exige `competition_season_id`, enviado internamente desde la temporada actual. La interfaz muestra su nombre como campo de solo lectura. Una vez creado, su temporada se conserva; las divisiones asociadas deben pertenecer a la misma temporada. `?season_context=<id>` selecciona el contexto administrativo. La tabla de eventos no muestra un selector de temporada. Los enlaces públicos `?public_season=<id>` conservan su función.

Los cambios de resultados, programación y configuración se rechazan en temporadas cerradas. La API de duelo en vivo devuelve HTTP 409 con `read_only=true` cuando la temporada no admite ejecución.

