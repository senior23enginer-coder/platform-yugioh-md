# Especificacion maestra de YUGIOH MD

Version documental: 2026-10-15.

## 1. Objetivo y alcance

YUGIOH MD administra temporadas competitivas de Station X Tulua: formatos, equipos, decks, divisiones, eventos, programacion, partidas, duelos, resultados, rankings, metagame, medallas, reportes e historicos.

La plataforma opera con un unico usuario administrador. Jugadores, jueces y seguridad por roles no forman parte del alcance funcional requerido.

## 2. Estado verificado de produccion

Datos verificados en base de datos y aplicacion el 2026-10-15, despues del reinicio solicitado:

- 1 temporada.
- 66 decks activos: 55 TCG Avanzado, 7 GOAT y 4 Rush Duel.
- 6 ligas y 8 divisiones: Apex (10+10), Elite (10+10), Vanguard (6), Ascension (9), Rush Dominion (4) y Ancient (7).
- Una unica copa: `Copa  Leyendas 2026 inaguracion temporada`, con 12 participantes y seis cruces pendientes.
- 0 clasificatorios; Copa Rush Duel eliminada.
- Competiciones pendientes de configuracion; no se asignaron nuevas fechas a las ligas.
- 0 resultados, snapshots competitivos, historicos de ranking/Elo/metagame, medallas y palmares.
- Las fichas, imagenes, analisis base, reglas y auditoria administrativa se conservaron.
- Filtros de formato verificados con las inscripciones persistidas de las nuevas ligas.

Las cifras son una fotografia operativa, no limites del sistema. El detalle de asignaciones y perfiles estimados se encuentra en `registered-decks.md`.

## 3. Formatos

Catalogo configurable: Master Duel, TCG Avanzado, OCG, GOAT, Edison, Rush Duel, Duel Links, Speed Duel, Genesis y Personalizado.

Un deck puede pertenecer a uno o varios formatos por su participacion en eventos/reglamentos. El listado vigente esta en `registered-decks.md`.

Los siete decks GOAT estan vinculados al reglamento GOAT mediante Ancient League; los 55 TCG Avanzado y cuatro Rush Duel estan vinculados mediante sus respectivas ligas.

## 4. Tipos y configuracion de eventos

Tipos base: clasificatorio, torneo, liga, copa, mundial, final, amistoso y evento especial.

Cada evento conserva nombre, codigo, temporada, tipo, estado, formato/reglamento, sistema de competencia, tipo de match, mejor de N, participantes por lado, origen de participantes, pool de decks, regla aplicada, fechas, programacion, dependencia, avance, impacto de ranking y notas.

Sistemas admitidos segun el tipo: manual, suizo, suizo con Top Cut, eliminacion simple o doble, grupos, grupos con eliminacion, round robin, doble round robin, liga por puntos, playoffs, serie, flights, ladder, king of the hill, gauntlet y personalizado.

Estados de evento: pendiente de configuracion, pendiente de evento, activo, finalizado, anulado y reanudable.

## 5. Ciclo de un evento

1. Crear ficha y seleccionar tipo.
2. Definir reglamento, pool y modelo de ejecucion.
3. Vincular division cuando sea liga o pool por division.
4. Seleccionar decks/equipos o recibir clasificados de un evento previo.
5. Guardar programacion y generar cruces.
6. Comenzar solo cuando la fecha programada lo permita.
7. Registrar resultados y recalcular tabla desde datos persistidos.
8. Completar fases o generar la siguiente.
9. Finalizar y congelar clasificacion, campeon, subcampeon, metagame, estadisticas y PDF final.

No se permite recalcular manualmente un ranking desde la ficha como accion ordinaria. La eliminacion exige confirmacion. Un amistoso creado manualmente puede eliminarse mientras cumpla las restricciones de integridad.

## 6. Flujo de partida y duelo

La partida usa una regla persistida, no una condicion fija del frontend. Puede manejar D1-D5 y BO3/BO5; la longitud efectiva tambien se obtiene de las condiciones activas de la regla.

Flujo obligatorio:

1. D1 lanza dados. Si empatan, se repite el lanzamiento.
2. El ganador del dado elige empezar o ir segundo.
3. Antes de D2-D5, el perdedor del duelo anterior elige. Si el duelo anterior empato, se lanzan dados nuevamente solo para ese inicio.
4. Se confirma `Comenzar duelo` y se decide con reloj de 20 minutos o sin reloj.
5. Una vez comenzado, la decision de reloj no cambia.
6. Cada `Terminar turno` agrega una entrada inmutable; el contador nunca disminuye.
7. Los LP se modifican con calculadora y mantienen historial.
8. La calculadora LP integra una calculadora estandar; su resultado se prepara como valor antes de sumar o restar LP.
9. Un jugador solo puede rendirse durante su turno; la pantalla A/B pide confirmacion y sincroniza la solicitud con el operador.
10. Se registra ganador A/B/empate, forma de cierre, brick, decks participantes, LP finales, turnos, duracion, notas y procedencia del dato.
11. Al guardar, la regla decide si el match termino o si habilita el siguiente duelo.

El sistema impide comenzar sin lado inicial, avanzar despues del cierre, saltar duelos o reducir turnos. La pantalla del juez controla todo el duelo y existen pantallas sincronizadas independientes para los lados A y B.

## 7. Reglas y resultados

Valores de duelo: `A`, `B`, `E` o vacio. Estados de partida: `PENDIENTE`, `EN CURSO`, `REQUIERE D5`, `FINALIZADA` e `INVALID`.

La regla puede cerrar por secuencia exacta, umbral BO3/BO5, victorias consecutivas, empates, ventaja al cierre, D5 o condicion personalizada. Las reglas del evento se copian/versionan para que un cambio posterior no reinterprete resultados historicos.

Los puntos oficiales por defecto son victoria 3, empate 1 y derrota 0, salvo que el evento configure otro impacto. Los amistosos pueden registrarse sin afectar ranking oficial.

## 8. Calendario y resultados

El calendario permite filtrar por evento, formato y fecha. Las fechas con partidas incompletas se muestran en azul y las completamente cerradas en verde. El detalle diario muestra evento, jornada, hora, ronda, mesa, participantes y estado.

Resultados permite filtrar por evento, estado, ronda, fecha y texto; ordenar del mas reciente al mas antiguo o en orden ascendente; abrir la captura y consultar resultados persistidos.

## 9. Decks y equipos

Cada deck activo debe estar asociado a un equipo. La ficha soporta nombre, arquetipo, variante/estilo, formato, equipo, imagen, notas, cartas clave, Main/Extra/Side, estadisticas, historial, matchups, medallas, ranking, tier y calendario propio.

La ficha de equipo agrega sus decks, estadisticas, palmares, medallas, calendario y exportacion. Las tablas de equipos y decks deben filtrar por formato.

Los cambios futuros de nombre, equipo, cartas o formato no deben modificar la lectura historica de una partida: se usa snapshot por evento/partida.

## 10. Rankings

Vistas requeridas: global actual, temporada, historico, formato, tipo de evento, division, equipos, power ranking de ultimas 5/10 partidas, Elo y consistencia con muestra minima.

El historico conserva fecha, temporada, posicion, puntos y movimiento real, por ejemplo `#8 -> #5`. Los filtros deben dejar claro el evento/temporada al que pertenecen.

## 11. Metagame

El metagame se calcula solo con partidas finalizadas y resultados persistidos en esta plataforma. Inscripciones pendientes no cuentan.

Debe permitir deck registrado, arquetipo o variante; filtros por temporada, periodo, formato, division y evento; presencia, rendimiento, muestra, tendencia, entradas/salidas y evolucion historica.

Tiers: Tier Zero, Tier 1, Tier 2 y Tier 3. Cada ficha muestra tier actual, mejor tier historico, fecha y movimientos. El rendimiento contra tiers se presenta por separado y con muestra minima configurable. La matriz completa de matchups cruza todos los decks y muestra volumen y winrate.

## 12. Estadisticas y tops

Incluye PTS, partidas y duelos G/E/P, winrate, primero/segundo, dado, brick, LP, dano, turnos, duracion, forma de cierre, remontadas, cierres por marcador, fuerza de rivales, sede, estabilidad e intervalo de confianza.

Los tops cubren decks/equipos por temporada, tiers, primero/segundo, remontadas, victorias perfectas, brick, rapidez, turnos, dano, D5, debut, movimiento de ranking, rivalidades y matchups. Todo top sensible exige muestra minima configurable y muestra el rival cuando corresponde.

## 13. Medallas y palmares

Las medallas guardan progreso, fecha, evento, rival, duelo, rareza y formato. Existen medallas normales y secretas, con notificacion y tarjeta compartible. El palmares conserva campeonatos y subcampeonatos congelados al cerrar eventos.

## 14. Calidad e historico

El centro de calidad distingue datos capturados durante el duelo, agregados posteriormente e inferidos. Muestra cobertura de dados, LP, turnos, historial de turnos, duracion, brick, cierre, deck y resultado.

Los snapshots antiguos inferidos no deben convertirse artificialmente en exactos. Cartas, dados, LP o turnos que no puedan reconstruirse permanecen ausentes.

## 15. Operacion conectada y offline

- Centro publico de partidas con actualizacion automatica.
- Vista publica de temporada mediante `?public_season=ID`.
- Pantallas A/B sincronizadas por partida.
- PWA instalable y cola offline.
- Sincronizacion monotona de turnos y deteccion de conflictos.
- Avisos de proximas partidas y llamados de mesa.
- Check-in QR disponible en el modelo.

La cola offline debe cubrir resultados y formularios editables de decks, equipos, eventos y programacion sin perder cambios.

## 16. Informes por entidad y contenido compartible

El centro de Reportes y sus consultas/exportaciones de listados fueron retirados por solicitud del usuario el 13/11/2026. Las fichas conservan sus informes individuales y los recursos PDF compartidos; no hay menú, vista ni API de Reportes.

Tarjetas compartibles: ranking, scouting, resultado de partida, resultado de evento, medalla, perfil de deck y perfil de equipo.

## 17. Responsive y accesibilidad

La interfaz debe funcionar en escritorio y movil, con validacion prioritaria en Galaxy S25 Ultra vertical y horizontal. No debe tener scroll horizontal accidental, solapamientos, textos recortados ni controles fuera de pantalla. Los estados no dependen solo del color y los botones tactiles conservan tamanos utilizables.

## 18. Pendientes de datos verificables

- Asociar los siete decks GOAT al reglamento GOAT.
- Cargar Main, Extra y Side reales y multimedia.
- Cerrar eventos para generar palmares y resumenes finales persistidos.
- Completar datos historicos solo cuando exista evidencia; no inventarlos.
- Ampliar la cola offline a todas las fichas editables y resolver conflictos de entidad.

## 19. Integracion estadistica colectiva y multijugador (18-10-2026)

El calculo STATION_X_STATS_8.1 incorpora los tres resultados reales por puesto de 3v3 al Ranking Global individual. No vuelve a sumar el marcador colectivo. En Tag 2v2, cada integrante recibe el resultado compartido usando el Elo medio previo del lado contrario y el K fijado para el evento. Los duelos compartidos no conceden victorias de duelo, dano ni partidas perfectas individuales.

Las identidades equipo/deck/formato se capturan al fijar alineaciones o crear una mesa. Los resultados antiguos sin esta evidencia quedan pendientes; cambiar el equipo actual de un deck no reescribe su identidad historica.

Battle Royale tiene un dominio independiente STATION_X_FFA_ELO_1.0 por deck y formato. Solo admite mesas reales de 3 o 4 participantes y un superviviente ganador. Base 1000; K=20; peso de victoria 10^(Elo/600), normalizado por la suma de pesos de la mesa. Cambio=20*(resultado-probabilidad), donde el ganador recibe resultado 1 y los demas 0. La suma de cambios es cero. Una mesa suma una participacion por jugador y no genera enfrentamientos 1v1. Requiere 10 mesas y 3 rivales para clasificar; inactividad tras 90 dias y reactivacion tras 3 mesas. No suma bonos de medallas al Elo FFA.

Ambos calculos se reproducen cronologicamente al corregir resultados, sin modificar tablas deportivas. Los cortes publicados conservan sus valores y las correcciones requieren otra revision. Pantalla, API, CSV y PDF consultan la misma proyeccion. No se valida contenido de cartas, limites, pools ni elecciones de Draft.

## 20. Biblioteca de consulta (18-10-2026)

Ruta #biblioteca, en Herramientas. Incluye guias de uso, terminologia, tipos de evento, sistemas competitivos, modelos preconfigurados, opciones del formulario, formatos, estadisticas y fichas de eventos, reglas, decks y equipos de la consulta actual. Las fichas de modelos se derivan del mismo catalogo usado al crear eventos; las de registros se proyectan desde appData, sin consultas nuevas ni escrituras deportivas.

includes/library-content.json conserva guias y definiciones revisadas. includes/library.php construye la proyeccion y library-view.php la integra en la pantalla. library-search.js normaliza acentos y codigos, busca en el contenido completo, pondera titulo y nombres, expande sinonimos y tolera errores acotados. No utiliza un proveedor de IA ni genera respuestas ajenas al contenido registrado. library.js muestra categorias, resultados, articulos relacionados y enlaces compartibles con library, library_q y library_category en la URL.

La Biblioteca escapa el texto y protege el JSON incrustado; las notas registradas son contenido, nunca instrucciones ejecutables. PWA v48 incluye los recursos nuevos. La consulta sin conexion requiere haber cargado antes la aplicacion y sus datos. El modulo no modifica ni reactiva validaciones de cartas. Cada articulo muestra su procedencia, distingue valores iniciales de modelos de configuracion guardada y conserva pendientes reales de evidencia.

## 21. Modificacion confirmada de partidas (20-10-2026)

El detalle de calendario muestra los combates y un boton Modificar; la agenda se edita en un modal separado. La disponibilidad se consulta en el servidor y se revalida dentro de la escritura serializada: formato de fecha/hora, dias y horas permitidos, ocupacion de decks, mesas FFA, capacidad diaria, sede y periodos del evento y la temporada. La fecha de una partida finalizada conserva su proteccion historica.

La consola individual de duelo elimina el selector visible de procedencia y Compartir resultado. Modificar abre el editor de D1-D5, LP, jugador inicial/actual, numero de turno, duracion, brick, notas, agenda y ganador. El resultado y ganador deben coincidir con el evaluador real; no se permite forzar un cierre contradictorio. Los equipos conservan su editor por puestos o serie Tag. Los eventos cerrados, temporadas cerradas y resultados consumidos por fases posteriores mantienen sus restricciones.

La confirmacion es explicita en navegador y obligatoria en el servidor. API match_editor: snapshot de lectura, comprobacion de agenda y POST JSON con token de sesion y version de los datos. Un cambio concurrente bloquea la confirmacion. La transaccion conserva antes/despues en auditoria y actualiza resultado, estado vivo y tablas derivadas. Una correccion puede reducir el contador efectivo de turnos; el historial anterior permanece en auditoria. Los incrementos normales siguen siendo monotonicos. Una marca de correccion permite refrescar otras consolas e impide que formularios anteriores sobrescriban el cambio. Las correcciones quedan con reloj pausado y no inventan lanzamientos de dados. La PWA v50 no guarda en cache el API de edicion. Los LP en cero se conservan como cero al recargar.
