# YUGIOH MD - Especificaciones de la plataforma

Documentacion vigente de la plataforma competitiva Yu-Gi-Oh de Station X Tulua.

## Fuente principal

- `platform-specification.md`: alcance completo y estado real de la plataforma.
- `registered-decks.md`: catalogo historico; para sincronizacion editorial usar los 67 IDs del manifiesto editorial confirmado.
- `editorial-sync.txt`: contrato con ChatGPT, organizacion de la Guia de juego y entregas incrementales sin perdida de contenido.
- `editorial-sync-state.json`: referencia de la instantanea editorial confirmada, su manifiesto y bloques completos. Actualizar esta referencia solo despues de integrar y verificar una revision.
- `requirements.md`: requisitos funcionales y no funcionales.
- `rules.md`: reglas de eventos, partidas y duelos D1-D5.
- `architecture-migration-status.md`: migraciones verificadas y deuda real que falta para completar toda la plataforma.
- `source-architecture.md`: fuentes editables, capas, dependencias, compatibilidad y validacion de la reorganizacion.
- `architecture.md`: arquitectura implementada.
- `global-ranking.md`: catalogo completo TCG/OCG, alcance compartido de ficha y ranking global; excluye Rush y GOAT.
- `database.md`: modelo de persistencia real.
- `api.md`: interfaz HTTP implementada.
- `frontend.md`: pantallas, navegacion y comportamiento responsive.
- `testing.md`: estrategia y pruebas existentes.
- `news.md`: fuentes de noticias, lector, filtros y actualizacion al visitar el sitio.
- `browser-storage.md`: almacenamiento local, cach?s y limpieza diaria.
- `deck-worlds.md`: Mundos, dentro de Decks y equipos; tabla de mundos, integrantes TCG, asignaciones dinámicas y principal (mínimo uno, máximo tres).
- `deck-builder.md`: editor, inspector con zoom, movimiento de cartas y cat?logo.
- `deck-guide.md`: organización de la Guía de juego, contenido aislado por deck y fuentes completas.
- `combo-creator.md`: editor visual de combos propios, guardado por deck, conexiones, guia y matriz de jugadas.
- `chatgpt-connection.md`: consulta autenticada de decks, cartas, guías y combos propios desde ChatGPT mediante MCP de solo lectura.
- `play-calculator.md`: tablero de captura física, requisitos locales de jugadas, escenarios y entrada offline.
- `acceptance-criteria.md`: criterios verificables de aceptacion.
- `roadmap.md`: implementado, deuda vigente y siguientes entregas.

## Documentos de apoyo

- `cups-world-and-friendlies.md`: modelos de copas, mundial y amistosos.
- `modalidades.txt`: catalogo amplio de modalidades posibles; no implica que todas esten activas.
- `seed-data.md`: datos iniciales y reglas de idempotencia.
- `deployment.md`: publicacion y operacion.
- `security.md`: modelo de operador unico.
- `migration.md`: compatibilidad y evolucion de datos.

## Regla de precedencia

Si un documento historico contradice `platform-specification.md`, prevalece la especificacion maestra y despues el comportamiento persistido en base de datos. Los nombres, fechas, formatos, reglas y participantes son configurables; no deben interpretarse como constantes solo porque aparezcan en datos iniciales.

Ultima consolidacion: 2026-09-27.
