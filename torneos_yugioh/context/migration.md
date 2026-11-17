# Migracion e importacion de datos

## Alcance

Una importacion CSV/XLSX puede incorporar catalogos de decks y equipos, divisiones, eventos, programacion, partidas y resultados D1-D5. Jugadores, jueces y arbitros quedan fuera del alcance actual.

## Proceso requerido

1. Cargar el archivo en una zona temporal sin tocar datos definitivos.
2. Validar columnas, tipos, formatos, duplicados y referencias.
3. Resolver cada nombre a una clave existente o presentarlo como alta controlada.
4. Asociar explicitamente temporada, evento, formato, equipo y deck.
5. Evaluar el resultado con la version de regla del evento.
6. Comparar la evaluacion con los totales declarados en el archivo.
7. Mostrar discrepancias y cobertura antes de confirmar.
8. Importar en una transaccion y registrar procedencia `importado`.
9. Crear snapshots historicos sin calificarlos como exactos cuando el archivo no contiene la informacion original.

## Datos de duelo

Dados, LP, turnos, duracion, brick, forma de cierre y cartas solo se importan cuando existen en el archivo. No se deben inventar historiales individuales de turnos ni listas de cartas para completar registros antiguos.

## Exportacion

CSV, XLS y PDF sirven para consulta, respaldo e intercambio. Las formulas del archivo exportado nunca son la fuente de verdad; MySQL y el evaluador versionado conservan la autoridad.
