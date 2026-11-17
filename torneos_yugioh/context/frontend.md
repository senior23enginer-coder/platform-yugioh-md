# Especificacion de interfaz

## Stack real

- PHP renderizado en servidor.
- JavaScript sin framework en `assets/app.js`.
- Bootstrap 5, Bootstrap Icons y CSS propio.
- PWA mediante `manifest.webmanifest` y `service-worker.js`.

## Navegacion

- Inicio/dashboard.
- Eventos competitivos, ligas, torneos, especiales y clasificatorios.
- Divisiones, calendario, resultados y copas.
- Decks y equipos.
- Estadisticas, rankings, metagame, enfrentamientos, records e historico.
- Reglas, configuracion, reportes y calidad de datos.

## Principios UX

- El evento activo siempre aparece en encabezado, selector y pestañas relacionadas.
- Pestañas de evento: tabla general, duelos programados, resultados y cierre.
- No anidar tarjetas sin necesidad ni saturar paneles con KPIs equivalentes.
- Acciones primarias visibles; acciones peligrosas con confirmacion.
- Tablas operativas densas en escritorio y formato apilado legible en movil.

## Captura de resultados

- Encabezado con evento, ronda, mesa, BO y participantes.
- Marcador e historial D1-D5 con boton `Ver`.
- Un solo duelo editable: el siguiente necesario.
- Preparacion: dados/eleccion, confirmacion de inicio y reloj.
- Ejecucion: turno, lado activo, LP, calculadora, brick y notas.
- La calculadora LP incluye suma, resta, multiplicacion y division estandar; usar el resultado solo prepara el valor y no modifica LP automaticamente.
- La pantalla A/B habilita `Rendirme` unicamente al lado activo y exige confirmacion antes de sincronizar.
- Cierre: resultado, forma de finalizacion y guardado explicito.
- Estado de guardado: cambios pendientes, guardando y guardado.
- Pantallas A/B en grupos claramente etiquetados, con abrir y copiar enlace.

## Calendario

- Mes, filtros y detalle del dia.
- Azul para incompleto, verde para completo, colores secundarios por tipo de evento.
- La tarjeta diaria no superpone logos, VS, estado ni flecha.
- Deck/equipo puede abrir su calendario filtrado.

## Estadisticas

- Tabs claras: resumen, ranking, enfrentamientos, metagame, records.
- Filtros antes del contenido y muestra/periodo visibles.
- Metagame organizado por tiers y con explicacion de formula/fuente.
- Matriz de matchups con scroll controlado y alternativa movil.
- Valores de muestra pequena identificados, nunca presentados como dominio absoluto.

## Responsive

- Breakpoint prioritario: 384 x 854 aproximado al Galaxy S25 Ultra CSS viewport.
- Validar tambien horizontal y desktop 1440 x 900.
- Sin overflow horizontal global.
- Barras inferiores no deben ocultar campos activos.
- Areas tactiles recomendadas de al menos 40 px.
- Textos largos deben envolver o truncarse con detalle accesible.
