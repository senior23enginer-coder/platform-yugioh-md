# Copas, mundiales, amistosos y otros eventos

## Modelo comun

Todos los tipos de evento comparten ficha, formato, reglas versionadas, participantes, programacion, calendario, resultados, snapshots y cierre. El preset propone valores iniciales, pero el evento conserva su propia configuracion.

Tipos soportados por catalogo:

- Liga.
- Copa.
- Torneo.
- Clasificatorio.
- Amistoso.
- Mundial.
- Final.
- Especial.

## Copa

Puede usar eliminacion directa, grupos mas eliminacion u otra estructura definida en el preset. Los participantes pueden proceder de una division, de clasificados o de una seleccion manual. Empates, byes, siembra, numero de rondas y tercer puesto deben declararse en la regla; no se deducen del nombre de la copa.

## Mundial

Puede configurarse por grupos, eliminacion o una combinacion. No existe un numero fijo obligatorio de participantes: cupos, formatos admitidos, clasificacion, desempates y cuadro final pertenecen a la version de regla aplicada.

## Clasificatorio

Puede construir ranking para divisiones, otorgar cupos o alimentar otro evento. La cantidad de rivales, duelos requeridos, BO3/BO5, puntos y criterios de cierre se leen de la regla persistida. Cada pestana y tabla debe operar sobre el `event_id` seleccionado.

## Amistoso

- Se crean manualmente o desde una ficha de deck/equipo.
- Pueden aparecer en calendario y centro de resultados.
- No afectan ranking oficial salvo configuracion explicita.
- Un amistoso manual pendiente puede eliminarse con confirmacion.
- Conservan detalle de duelo y snapshots si se registra un resultado.

## Especial y torneo

Permiten presets editables para condiciones que no encajan en liga, copa o clasificatorio. La etiqueta del tipo no reemplaza la regla: estructura, puntuacion, cierre, participantes y avance siempre deben quedar persistidos.

## Cierre

Al finalizar cualquier evento competitivo se congela un resumen con clasificacion, campeon, subcampeon, metagame, estadisticas destacadas y PDF. Un evento amistoso puede cerrar sin palmares cuando su regla asi lo determine.
