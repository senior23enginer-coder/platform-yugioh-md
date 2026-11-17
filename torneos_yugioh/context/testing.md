# Estrategia de pruebas

## Objetivo

Las pruebas deben demostrar que las reglas persistidas, el flujo visual y el estado de base de datos permanecen alineados. Un HTTP 200 o una pantalla visible no certifican por si solos un resultado competitivo.

## Suite existente

La carpeta `tests/` contiene regresiones para:

- BO3 y reglas de cierre: `best_of_rule_regression_test.js`, `bo3_production_guard_test.js`.
- Reapertura y avance: `d4_reopened_setup_test.js`, `draw_to_next_duel_test.js`.
- Turnos concurrentes: `turn_monotonic_test.php`, `turn_recovery_multitab_test.js`.
- Pantallas A/B: `live_duel_player_screens_test.js`, `player_duel_mobile_ui_test.js`, `judge_player_bar_test.js`.
- LP: `lp_calculator_immediate_apply_test.js`.
- Matriz de modalidades: `match_format_matrix_test.js`.
- Movil: `mobile_turn_flow_test.js`.
- Calendario y amistosos: `calendar_completion_colors_test.js`, `manual_friendly_delete_test.js`.
- Inteligencia y pendientes: `competitive_intelligence_ui_test.js`, `pending_real_complete_test.js`.
- PDF: `simple_pdf_test.php`.

## Matriz minima

1. Validacion estatica con `node --check` para JavaScript modificado.
2. `php -l` para PHP cuando el ejecutable este disponible.
3. Pruebas Playwright en escritorio de 1440 x 900.
4. Pruebas moviles de 384 x 854, equivalentes al Galaxy S25 Ultra en CSS pixels.
5. Retrato, paisaje, recarga, cambio de red y dos pestanas concurrentes.
6. Lectura de produccion sin modificar resultados reales, salvo autorizacion explicita.

## Casos criticos

- Un contador persistido en 6 nunca vuelve a 5.
- Dos solicitudes con la misma revision no pueden perder un turno.
- Un empate de dados obliga a relanzar y no define quien comienza.
- La decision de usar reloj queda bloqueada al comenzar el duelo.
- Elegir ganador no envia el formulario por si solo.
- Una rendicion guarda ganador, LP, forma de cierre y auditoria.
- Una rendicion fuera del turno del jugador es rechazada en interfaz y servidor.
- La calculadora estandar produce resultados correctos y no desborda en Galaxy S25 Ultra.
- BO3 cierra al alcanzar dos victorias; BO5 aplica la regla versionada del evento.
- Una regla personalizada puede requerir D4 o D5 aunque exista una mayoria ordinaria.
- Al cerrar el match no se abre un duelo adicional.
- La clasificacion se recalcula con el evento correcto y muestra puntos y posicion.
- Las fechas incompletas son azules y las completas verdes.
- Un amistoso creado manualmente puede eliminarse con confirmacion.

## Certificacion pendiente

Debe mantenerse una corrida completa y repetible para 1v1, 2v2, 3v3 y cinco decks, incluyendo inicio, dados, turnos, LP, rendicion, empate, cierre, tabla, ranking y snapshot. Cada defecto corregido en estos flujos debe agregar una prueba de regresion.
