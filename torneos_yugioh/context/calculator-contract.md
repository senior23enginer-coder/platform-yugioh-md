# Contrato de entrada de la Calculadora — `station-x-calculator/1`

## Alcance

El estudio editorial de cada deck puede incluir `calculator` con un bloque
declarativo independiente de sus rutas A01–A10. Este bloque describe **efectos
ejecutables**; un combo escrito en prosa no se convierte automáticamente en
regla. La ausencia del bloque conserva la guía documental y significa cero
reglas declarativas aportadas por esa entrega.

La forma raíz de la versión 1 es:

```json
{
  "schema": "station-x-calculator/1",
  "deck_id": 9001,
  "format": "tcg_advanced",
  "rules": []
}
```

- `schema`: valor exacto; otra versión se rechaza, sin conversión implícita.
- `deck_id`: entero positivo, idéntico al estudio e inventario nativo.
- `format`: `tcg_advanced`, `goat` o `rush_duel`, idéntico al estudio.
- `rules`: hasta 1000 reglas. Los ID son únicos dentro del paquete.

Cada regla usa el contrato vigente de `rules.js`: `version: 1`, `id`,
`deckId`, `format`, `scope: "single-effect"`, `label`, `source` oficial,
`assumptions`, `actor`, `guards`, `select`, `costs` y `resolve`, además de
límites y etiquetas opcionales admitidos por el intérprete. La estructura
exacta de selectores, guardas y operadores se valida con `Rules.validate`.
Las referencias locales (`actor` y cada `select[].ref`) deben existir antes
de ser utilizadas como materiales, objetivos o costes.

## Frontera de validación

`assets/modules/play-calculator/contract.js` valida el bloque completo,
incluidos identidad, formato, unicidad y reglas compatibles. Con un conjunto
de CID acreditados del inventario también comprueba que el actor propio
pertenece al deck, salvo procedimientos especiales admitidos explícitamente
por el intérprete. Es una segunda barrera junto a la validación de importación
PHP; no modifica el documento ni ejecuta reglas.

Los datos rechazados no deben degradarse a efectos «aproximados»: permanecen
como información documental con un motivo verificable. No se evalúa JavaScript
ni se interpreta texto libre. El Side y la Reserva no forman parte de un duelo
sin intercambio registrado; las cartas rivales ocultas nunca se identifican
por simple pertenencia al inventario.

## Compatibilidad y evolución

La versión 1 continúa siendo el contrato de importación actual. Cualquier
ampliación incompatible necesita una nueva versión, una transformación
explícita probada y soporte simultáneo durante la migración. Los adaptadores
particulares existentes en `verified-rules.js` se conservan hasta que haya
paridad funcional y pruebas que permitan sustituirlos por datos declarativos.

## Registro de operaciones (tarea general 03)

`assets/modules/play-calculator/operations.js` define el catálogo inmutable
de 21 nombres de operaciones de `costs` y `resolve`. `Rules.validate` consulta
este registro antes de validar la estructura particular de cada operación.
Los nombres desconocidos y las operaciones ubicadas en un bloque prohibido se
rechazan sin ejecutar el efecto. El navegador carga el registro antes de
`rules.js`; Node lo importa como dependencia explícita.

`coverage: "generic"` indica que el operador no contiene en su validación
un requisito fijo de identidad de carta; **no** certifica todos los casos ni
la legalidad de una jugada. `coverage: "restricted"` señala un operador con
límites expresos de cartas, recetas o procedimientos que todavía requieren
generalización y pruebas. En particular `shuffle`, `link`, `xyzOverlay`,
`fusion`, `equip`, `counter` y `banishExtraFaceDown` son restringidos.
El registro documenta la deuda, sin eliminar las barreras actuales ni
modificar resultados de efectos existentes.

## Registro de condiciones (tarea general 04)

`assets/modules/play-calculator/guards.js` registra las nueve clases de
condiciones admitidas: `count`, `distinctCount`, `onlyCids`, `event`,
`phaseStart`, `extraSummonHistory`, `inventoryTarget`, `state` y `flag`.
`Rules.validate` rechaza clases no registradas antes de evaluar sus detalles.
El navegador carga `guards.js` antes de `rules.js` y Node lo importa como
dependencia. `event` y `extraSummonHistory` mantienen cobertura restringida:
el historial disponible sólo acredita determinados eventos y ventanas. Este
catálogo no interpreta automáticamente texto de cartas ni prueba condiciones
de activación que todavía no están modeladas.

## Recursos y costes (tarea general 05)

`assets/modules/play-calculator/resources.js` encapsula el cálculo puro de
LP para `payLP`, `damage` y `recoverLP`, sin modificar la entrada. Pagar LP
requiere conservar al menos un LP; el daño nunca produce LP negativos; la
recuperación no supera el máximo explícito de escenario (999999). El motor
de reglas utiliza esta función tanto durante el pago de costes como durante
la resolución. No se modifican las reglas de materiales, tributos, zonas o
costes específicos de cartas; su generalización requiere evidencias y pruebas
adicionales. El módulo no sustituye la comprobación de legalidad de cartas.

`requireTokens` comprueba copias físicas seleccionadas por UID sin asociarlas
a ningún deck. Rechaza referencias inexistentes, duplicadas o que no cumplen
la condición de zona/controlador definida por el operador. Las operaciones
`shuffle`, `link`, `xyz`, `fusion`, `tribute` y `banishExtraFaceDown` usan esta función
antes de mover materiales. Sus recetas y restricciones siguen vigentes.

## Destinos y transiciones (tarea general 06)

`assets/modules/play-calculator/transitions.js` extrae comprobaciones puras
de destinos bloqueados, ocupación de zona por controlador y retorno del
control al propietario en `deck`, `extra`, `graveyard` y `banished`.
`rules.js` utiliza estas políticas en movimientos, invocaciones desde mano y
cambios de control. Se conservan las restricciones individuales de ventanas,
materiales y procedimientos; la biblioteca no pretende resolver por sí sola
las reglas de cada tipo de Invocación ni las sustituciones de destino.

`tests/calculator_contract_test.cjs` prueba el mismo contrato con dos decks
sintéticos diferentes, ejecuta una operación compartida sin cambiar código
por deck, y rechaza versión, identidad, campos, duplicados y operaciones
inválidas. No supone cobertura universal de todas las cartas.
