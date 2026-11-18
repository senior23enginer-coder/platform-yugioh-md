# Calendario: reflujo, cupos y prioridad

## Recorrido implementado

Las funciones nativas de `includes/schedule_capacity.php` conservan sus firmas y delegan mediante `Infrastructure/Composition/CalendarModule`:

- `ReflowPendingSchedule`: redistribuye los combates pendientes de un evento activo.
- `AssignExplicitSchedule`: aplica las fechas y cupos en el orden suministrado.
- `ReflowActiveQualifiers`: conserva el orden del catálogo y el máximo entre fecha configurada y mínimo solicitado.
- `RebalanceSchedule`: al programar un evento que no es clasificatorio, libera primero fechas pendientes de clasificatorios, programa el evento y finalmente redistribuye los clasificatorios.

Los casos de uso solo conocen puertos: lectura, escritura, capacidad, proyección de eventos y tiempo. No reciben PDO ni leen superglobales. `Domain/Calendar/MatchDatePattern`, `ScheduleTimePolicy` y `PendingSchedulePlanner` calculan fechas y turnos desde datos explícitos. `NativeCalendarTime` mantiene la interpretación de fechas relativas y zona horaria configurada de PHP fuera del dominio.

## Contratos preservados

Se conserva el orden nativo por `display_order`, ronda, mesa e ID; la capacidad global de uno a tres combates; el límite por evento; los días bloqueados; los horarios preferidos; las cadencias existentes y el límite de 730 días candidatos. Las cuotas explícitas no se ordenan automáticamente. Los predicados de estado y actividad permanecen iguales, incluso donde no coinciden con otros normalizadores de la plataforma.

Los helpers nunca fueron propietarios de una transacción y continúan sin iniciar, confirmar ni revertir una. Los bloqueos externos tampoco se trasladan. El planificador entrega asignaciones incrementalmente para conservar el comportamiento de error original: sin transacción del llamador, un error puede dejar fechas liberadas o asignaciones parciales; dentro de una transacción, el llamador puede revertir todo. Modificar esa política sería otro cambio funcional.

La proyección de eventos solo se reconstruye después de asignar todos los combates y actualizar la fecha final. Un evento ausente o sin pendientes termina anticipadamente en el flujo automático. La programación explícita vacía conserva su limpieza y reconstrucción original.

## Adaptadores y alcance

`PdoScheduleReader`, `PdoScheduleWriter` y `PdoQualifierSchedules` contienen el SQL. `NativeScheduleCapacity` adapta la configuración existente y `NativeScheduleEvents` adapta el escritor de proyecciones del calendario. Los constructores no consultan ni crean tablas. El desplazamiento masivo de fechas de clasificatorios conserva la sentencia MySQL con `UPDATE ... JOIN`.

Esta migración no comprende todavía toda la generación de fixtures, las migraciones históricas de octubre, la activación automática, las modificaciones manuales de agenda ni la separación interna del escritor de proyecciones. El estado general permanece en `architecture-migration-status.md`.

## Verificación

`calendar_architecture_test.php` prueba puertos en memoria y SQLite aislado. Sus 49 comprobaciones incluyen 13 escenarios capturados antes de sustituir las funciones: devuelve los mismos resultados, errores, filas modificadas y llamadas de proyección. También comprueba prioridad de clasificatorios, filtros por temporada, orden, fallos de escritura y propiedad de transacciones. No se accede a producción.

La regla original de cadencia se añadió a `domain-extractions.json` únicamente después de comprobar su huella real contra `source-function-map.json`. Las 42 entradas anteriores y sus huellas permanecen intactas. La suite compara ahora 43 algoritmos originales y sus firmas. La ejecución específica de `UPDATE ... JOIN` y la concurrencia entre conexiones siguen requiriendo un MySQL de pruebas; SQLite no certifica esas operaciones.
