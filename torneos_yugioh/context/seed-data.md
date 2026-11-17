# Datos iniciales y catalogos

## Principios

- Todo seed debe ser idempotente.
- Solo se inserta un registro inicial cuando no existe una clave funcional equivalente.
- Nunca se reemplazan resultados, snapshots, turnos ni historicos de produccion.
- Los nombres visibles pueden editarse; las claves internas permanecen estables.
- Formato, equipo y division deben guardarse como relaciones explicitas.

## Catalogo de decks

El inventario vigente contiene 54 decks activos y se detalla en `registered-decks.md`:

- 43 de TCG Advanced, identificadores 646 a 688.
- 7 de GOAT, identificadores 697 a 703.
- 4 de Rush Duel, identificadores 704 a 707.

Los seeds historicos equivalentes fueron 1-43 para TCG, 501-507 para GOAT y 601-604 para Rush. La aplicacion no debe depender de esos numeros antiguos para determinar el formato.

## Catalogos funcionales

- Formatos: Master Duel, TCG Advanced, OCG, GOAT, Edison, Speed Duel, Rush Duel, Genesis, Duel Links y Custom.
- Tipos de evento: liga, copa, torneo, clasificatorio, amistoso, mundial, final y especial.
- Tipos de match: 1v1, 2v2, 3v3 y configuracion de cinco decks cuando la regla lo indique.
- Estados, frecuencias, procedencias, cierres y opciones visuales se administran desde `sx_ygo_catalog_items`.

## Divisiones y eventos

No se deben recrear cuatro divisiones fijas ni nombres de fantasia como requisito tecnico. Cada liga elige su division y formato; un clasificatorio puede alimentar divisiones mediante rangos configurados. Los presets son editables y siempre se copian/versionan al evento.

## Pendientes de carga

- La asociacion de formato de los siete decks GOAT debe quedar persistida, no inferida solo por su estilo.
- No existen cartas ni multimedia cargadas en la fotografia del 27 de septiembre de 2026.
- Los datos imposibles de reconstruir deben permanecer vacios o marcados como inferidos.
