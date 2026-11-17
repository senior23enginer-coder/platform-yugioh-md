# Requisitos funcionales y no funcionales

Fuente de alcance: `platform-specification.md`.

## Configuracion general

- La plataforma debe administrar multiples temporadas, formatos y eventos sin nombres rigidos.
- Los catalogos de tipo, reglamento, estado, pool y programacion deben ser editables.
- El operador debe poder crear, editar, programar, comenzar, avanzar, finalizar, anular y eliminar eventos cuando las reglas de integridad lo permitan.
- Una liga debe estar asociada a una division.
- Un evento puede depender de otro y recibir clasificados, campeon, Top N o participantes de una division.
- No se implementan usuarios, jugadores, jueces ni permisos por rol; existe un unico operador.

## Formatos y participantes

- Deben existir Master Duel, TCG Avanzado, OCG, GOAT, Edison, Rush Duel, Duel Links, Speed Duel, Genesis y Personalizado.
- Las tablas y selectores de decks/equipos deben filtrar por formato.
- Cada deck activo debe tener equipo.
- Cada deck debe conservar nombre, arquetipo, variante, formato, seed, imagen, notas, cartas y estado.
- La aplicacion debe soportar 1v1, 2v2 y 3v3; el modelo admite hasta cinco decks por lado cuando una regla lo requiera.

## Eventos

- Tipos: clasificatorio, torneo, liga, copa, mundial, final, amistoso y especial.
- Sistemas configurables: suizo, round robin, liga, grupos, Top Cut, eliminacion simple/doble, serie, flights, ladder, gauntlet, king of the hill, manual y custom.
- Pool: todos activos, division, clasificados, Top Cut o seleccion manual.
- El selector y las pestañas siempre deben mostrar el evento seleccionado.
- Programacion debe persistir fecha, hora, frecuencia, ronda, mesa, sede y regla.
- `Comenzar` solo se habilita cuando la fecha/hora lo permite.
- Eliminar exige confirmacion; amistosos manuales son eliminables.

## Partidas y duelos

- Cada partida usa una regla persistida y versionada.
- D1 requiere dados; empate de dados obliga a repetir.
- El ganador del dado elige primero/segundo.
- En D2-D5 el perdedor previo elige; si hubo empate, se lanzan dados nuevamente.
- `Comenzar duelo` exige confirmacion y decision de reloj.
- La decision de reloj queda bloqueada al comenzar.
- El turno actual siempre avanza; nunca se reduce ni se sobrescribe silenciosamente.
- Dos pestañas/dispositivos deben resolver concurrencia por revision y monotonicidad.
- LP, turnos y calculadora deben persistir y sincronizarse.
- La rendicion solo puede solicitarla el lado que tiene el turno activo y debe sincronizarse con la consola del operador.
- La calculadora LP debe incluir suma, resta, multiplicacion y division estandar sin modificar LP hasta usar el resultado.
- Cada duelo guarda resultado, cierre, brick, LP, historial LP, turnos, historial de turnos, duracion, decks, notas y origen.
- Un resultado no debe recargar/cerrar antes de que el operador complete y guarde todos los campos.
- La regla decide si habilita D4/D5 o finaliza el match.

## Calendario y resultados

- Calendario mensual con filtro de evento/formato y detalle diario.
- Azul: fecha con partidas incompletas. Verde: fecha completamente cerrada.
- Resultados con filtros de evento, estado, ronda, fecha y busqueda.
- Orden por fecha ascendente o descendente; por defecto, mas recientes primero.
- Cada D1-D5 finalizado ofrece `Ver` y abre detalle completo.

## Rankings y metagame

- Rankings global, temporada, historico, formato, tipo de evento, division, equipos, Elo, power ranking y consistencia.
- El historico guarda posicion, puntos y movimiento real por fecha/temporada.
- El metagame usa solo resultados persistidos y partidas finalizadas.
- Tiers Zero/1/2/3 con formula y muestra minima configurables.
- Rendimiento separado por tier, matriz de matchups y evolucion temporal.
- Cada ficha muestra tier actual, mejor tier, fecha y cambios.

## Estadisticas, tops y medallas

- Estadisticas de partidas, duelos, posicion, dado, brick, LP, dano, turnos, duracion, cierres, remontadas y fuerza de rivales.
- Tops con muestra minima y nombre del rival cuando corresponda.
- Medallas persistidas con progreso, rareza, fecha, evento, rival y duelo.
- Palmares y resumen final se congelan al cerrar un evento.

## Historico y calidad

- Snapshot de equipo, deck, arquetipo, variante, cartas, formato y banlist por partida/evento.
- Dato capturado, agregado o inferido debe mostrarse como tal.
- Datos antiguos imposibles de reconstruir permanecen ausentes.
- Centro de calidad con filtros para registros incompletos.

## Contenido compartible, publico y offline

- Informes individuales de las fichas; centro de Reportes y exportaciones de listados retirados el 13/11/2026.
- Vista publica de temporada y marcador publico de partidas.
- Tarjetas compartibles de resultados, eventos, ranking, medallas, deck y equipo.
- PWA y cola offline para resultados y formularios editables.
- Pantallas A/B sincronizadas con control del juez.

## Calidad visual

- Desktop y Galaxy S25 Ultra vertical/horizontal sin solapamientos ni scroll horizontal accidental.
- Controles tactiles utilizables, texto legible y estados acompañados de etiquetas.
- Las tablas densas deben transformarse en filas/tarjetas legibles en movil.
