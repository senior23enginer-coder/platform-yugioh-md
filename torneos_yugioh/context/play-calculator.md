# Calculador de jugadas

## Pilotos Memento y Crystron/Horus — contrato común de análisis (20-11-2026)

La misma puerta `scripts/verify-pilot-decks.cjs` verifica en ambos pilotos
identidad del deck, formato, 39 CIDs de carta, inventario separado,
fotografías por paso, números y acciones de las rutas, diagramas de resultado
ilustrativos, origen/identidad de las reglas portables y, cuando está disponible
el clon hermano, paridad exacta del contrato de calculadora y de los CIDs/acciones
del paquete `stationx-deck.json`. No importa archivos del otro deck ni
reescribe inventarios o revisiones del editor.

- **671 Memento:** 41 Main, 15 Extra, 14 Side; 12 rutas con 69 pasos
  sincronizados, 12 diagramas de resultado ilustrativos; 321 variantes
  portables agrupadas en 55 IDs de efecto. Son 30/39 identidades con al menos una regla de actor; faltan 9, y las
  9/12 rutas (C01–C09) con replay acotado; C09 Invoca Princesa Necroequipo mediante procedimiento propio con Réquiem equipado y Goblin de mano, sin robo automático; C08 exige activación rival observada y no resuelve su efecto original; C07 usa Quimera Guardián. Quedan 3 rutas sin replay completo certificado. No se certifican cadenas ni legalidad universal.
- **677 Crystron/Horus:** 40 Main, 15 Extra, 15 Side; 14 rutas con 60 pasos,
  14 diagramas de resultado ilustrativos; 13 variantes portables:
  2 procedimientos Horus en GY, 2 acciones Smiger, 5 alternativas de búsqueda
  de Thystvern desde GY, 2 alternativas de destrucción en Campo que traen
  Citree o Tristaros con bloqueo temporal de Extra y 2 alternativas de Sarcófago del Rey.
- **Sarcófago del Rey:** `677:sarcophagus:send-horus` opera desde la zona
  de Magias/Trampas boca arriba, envía una carta real de la mano al GY como
  coste y un Horus acreditado del Deck al GY como efecto. Todas las variantes
  y copias comparten `maxUsesPerTurn: 4` con contador por nombre y turno.
  Reanimar a Amset/Hapi desde GY es una regla propia del monstruo, no una
  acción de Sarcófago. Fuente oficial:
  https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid=19200&ope=2.
  El motor rechaza un quinto uso y no simula su protección continua ni
  efectos de Damage Step no implementados.

**Separación de evidencias:** fotos y cartas de resultado verificables no
equivalen a casillas precisas ni a la ejecución causal A09. Los diagramas
sin posicionamiento acreditado muestran `spatial_verified=false` y un aviso
visible en móvil/escritorio. `REPLAYS_NOT_CERTIFIED` no puede convertirse
en `TERMINADO` solo porque el ZIP importe o la vista dibuje un tablero.
La puerta `--require-finished` falla deliberadamente mientras ese estado
no sea cierto. Pruebas específicas:
`calculator_horus_sarcophagus_four_uses_test.cjs`,
`calculator_crystron_thystvern_test.cjs`,
`study_presentation_ui_test.cjs`,
`study_crystron_visual_ui_test.cjs` y verificación de importación real
en el workflow de integración. La suite global mantiene su propio gate.


## Auditoría del núcleo genérico — contratos de integración

El flujo vigente es `study.js` (identidades y reglas del estudio) →
`rules.js` (validación y acciones declarativas) → `planner.js` (exploración
acotada); `chain.js` reutiliza `rules.js` para preparar costes y resolver
en orden inverso. `engine.js` valida y modifica copias del escenario sin DOM.

**Hallazgos verificados en el código:**

1. **Entrada:** `study.calculator.rules` se incorpora por `deckId` y formato,
   pero aún se complementa desde `verified-rules.js`. La guía A01–A10, por sí
   sola, no acredita operaciones ejecutables. El contrato importable
   `station-x-calculator/1` existe y es opcional.
2. **Reglas:** `rules.js` rechaza operadores desconocidos y separa costes de
   resolución, pero su validación contiene restricciones ligadas a CID y
   recetas específicas (por ejemplo, equipamiento y superposición). No es
   todavía un intérprete universal de todos los efectos.
3. **Búsqueda:** `planner.js` combina `R.actions()` con acciones heredadas
   codificadas por CID. La búsqueda tiene límites de profundidad, anchura y
   estados, y no garantiza enumerar todas las líneas legales.
4. **Cadenas:** `chain.js` prepara costes y resuelve efectos propios
   acreditados de forma inversa; las respuestas rivales son declaraciones
   condicionales. No hay reproducción general certificada de respuestas a
   respuestas ni de ventanas y efectos completos del adversario.
5. **Estado y causalidad:** `engine.js` valida zonas, copias y control;
   `rules.js` conserva eventos causales y `planner.js` los incluye en su clave
   de deduplicación. No existe aún un contrato unificado y versionado para
   todos los eventos, restricciones temporales y efectos simultáneos.

**Integridad del contrato portable (Memento, noviembre 2026):** un efecto puede
tener varias reglas de ejecución correspondientes a objetivos, materiales o
zonas alternativas. El contrato `station-x-calculator/1` admite varias
definiciones *distintas* con un mismo `id` cuando coinciden identidad del
actor, límite de uso por turno/nombre, condición de activación y velocidad.
Rechaza definiciones idénticas duplicadas o variantes con políticas
incompatibles. La importación compara definiciones completas y conserva
las variantes de respaldo que no estén incluidas en el paquete. La regresión
`calculator_portable_variant_preservation_test.cjs` comprueba las 42
alternativas verificadas de Fiesta de Huesos, tanto con importación parcial
como completa; esta prueba no certifica las 12 rutas A09.

**Entrega portable activa de Memento (20-11-2026):** la instantánea editorial
`20261117-folder-delivery-v40/671.json` y la entrega canónica del repositorio
de prompts contienen ahora las mismas **315 variantes acotadas con 53 IDs
de efecto**. `Study.bind` acepta esas reglas sin duplicar los respaldos.
El importador PHP permite hasta 1000 definiciones diferentes por paquete,
valida los IDs compartidos y el procedimiento especial TY-PHON, y rechaza
duplicados o actores ajenos. Las pruebas focales incluyen carga e
interpretación de la secuencia Goblin → Desvantasma → recuperación desde el
estudio real. **No existe certificación integral de los 34 controles A07 ni
reproducción certificada de las 3 rutas A09 restantes**, y la suite nativa completa
debe ejecutarse antes de desplegar a producción.

**Criterios de entrada para la siguiente tarea (contrato universal):**

- Definir una única identidad de deck/formato, inventario exacto y referencias
  oficiales por efecto; los alias no sustituyen un CID acreditado.
- Publicar un esquema versionado con selectores, guardas, costes, resolución,
  disparadores, límites, suposiciones, evidencia y resultado de cobertura.
- Validar las referencias cruzadas y rechazar operaciones no reconocidas,
  sin ejecutar texto libre ni aceptar casos parcialmente modelados como completos.
- Probar con al menos dos decks sintéticos que importar reglas de operaciones
  ya soportadas no exige cambiar `engine.js`, `rules.js` o `planner.js`.
- Distinguir las reglas declarativas del estudio de los adaptadores heredados,
  con plan de compatibilidad antes de retirar cualquiera de ellos.

Esta auditoría no certifica el motor ni cambia inventarios, efectos o rutas.

**Avance controlado de turno (módulo genérico):** el botón «Siguiente turno» alterna
el jugador activo en Duelo (en Práctica conserva el lado propio), incrementa
el turno, establece la fase de Robo y restituye la Invocación Normal.
Retira únicamente las negaciones declaradas para el turno anterior, conservando
las de duración manual. Rechaza avanzar con cadena pendiente o después del
turno 999. No roba cartas, ejecuta efectos de End/Standby, vence automáticamente
otras restricciones declarativas ni altera LP; esas acciones requieren captura
explícita. El cambio conserva el tablero y puede deshacerse.

**Avance de fase:** «Siguiente fase» sigue el orden Robo → Espera → Main 1
→ Batalla → Main 2 → Final para TCG/GOAT; Rush omite Espera y Main 2. El botón se
bloquea si hay una cadena pendiente o si ya se alcanzó Final. No decide
automáticamente si el jugador puede entrar a Batalla, ni ejecuta disparadores,
robos o efectos; sirve exclusivamente como reloj de captura manual.

Sección independiente de la ficha del deck, junto a la Guía de juego. El módulo es una herramienta de captura y estudio del duelo físico. No escribe decklists, resultados, LP oficiales ni tablas de competición.

## Uso

1. Abrir un deck con Main guardado y entrar en **Calculador de jugadas**.
2. Elegir **Práctica libre · sin oponente**, o un rival del mismo formato que también tenga Main guardado. Se admite un espejo del mismo deck, con copias independientes para cada lado.
3. Indicar turno, orden inicial, jugador activo, fase, LP y disponibilidad de la Invocación Normal.
4. Buscar entre las cartas de los dos participantes, seleccionar una y arrastrarla a su zona. Como alternativa accesible, seleccionar una carta y pulsar **Colocar aquí** o elegir zona y lado en el panel de selección.
5. Capturar mano, campo, Cementerio, destierro y Extra. Las cartas no ubicadas permanecen disponibles en el catálogo. Seleccionar una copia colocada y usar la barra visible del tablero para cambiar ataque/defensa y boca arriba/abajo; el panel conserva el selector de posici?n. Un monstruo boca abajo se representa horizontal con reverso. Acoplar materiales mediante el panel. Los materiales se trasladan antes de retirar su monstruo.
6. Registrar las cartas rivales conocidas; utilizar **Carta rival desconocida** para una carta oculta en el campo. Las cantidades de mano y destierro desconocidos no se convierten en identidades supuestas.
7. Pulsar **Calcular opciones** y consultar requisitos reconocidos, faltantes, condiciones pendientes y pasos documentados.

Las cartas muestran su imagen original en el buscador y el tablero. `assets/cards/originals/index.json` enlaza las filas nativas por `deck_id:card_row_id` con archivos locales y registra fuente y SHA-256. Incluye 390 entradas y 304 imagenes unicas, tambien Rush. Si falta la imagen, se muestra el nombre; nunca otra carta parecida.

El catalogo empieza vacio hasta elegir modo o rival. En práctica libre solo incluye el Main y Extra propios, sin cartas ni controles rivales. En duelo incluye Main y Extra de los dos participantes: filtros por jugador y seccion, busqueda por nombre o funcion, copias restantes y paginas de 12 cartas. Side y reserva no se incorporan automaticamente.

## Acceso desde Herramientas

**Herramientas → Calculadora de jugadas** (`#calculadora_jugadas`) utiliza el mismo motor y tablero de la ficha del deck. Permite buscar tu deck por nombre/equipo y filtrar por formato. El selector incluye todos los decks registrados, tengan o no inventario. La ausencia de Main se explica y ofrece acceso a la ficha para cargarlo; no se crean cartas para completar listas.

El selector rival también muestra todos los decks, con formato y aviso de Main ausente. Los formatos incompatibles están visibles y deshabilitados. Un rival compatible sin Main permite capturar cartas desconocidas, conservando el catálogo propio y sin inventar el inventario contrario. Práctica libre sigue disponible sin rival. TCG Traditional y otros formatos sin tablero implementado se indican expresamente; no se convierten a Advanced.

`workspace.js`/`workspace.css` coordinan los filtros y la selección sin duplicar las reglas del motor. Cambiar o deseleccionar el deck termina el worker anterior y elimina sus escuchadores. Prueba adicional: `tests/calculator_workspace_ui_test.cjs`.

### Modos Práctica y Duelo

El modo se elige antes de seleccionar tu deck. **Práctica** abre el tablero propio sin oponente y concentra las aperturas aleatorias, el robo y la exploración de manos. **Duelo** permite elegir ambos participantes y capturar manualmente la mano, campo, Cementerio y demás zonas. Cambiar de modo con cartas colocadas pide confirmar el reinicio; cancelar conserva la captura. La ficha del deck y la entrada independiente también incluyen los dos modos.

El número de turno y el jugador activo están siempre visibles cuando existe un tablero, fuera del detalle plegable. Cambiar el número de turno conserva el campo y deja de aplicar los efectos usados o negaciones que pertenecían a otro turno; no ejecuta robos ni efectos automáticamente.

### Menú por carta

Clic derecho sobre una carta colocada abre sus opciones. En móvil se selecciona la carta y se utiliza **Opciones de carta**. El menú permite mover la copia entre zonas, ataque, defensa, defensa boca abajo y declarar la negación de sus efectos con motivo (por ejemplo Infinite Impermanence) y duración: turno actual o hasta retirarla manualmente. No ofrece defensa para un Link identificado. Las cartas desconocidas conservan su estado oculto.

Cada copia muestra las marcas de negación y efectos utilizados. Los efectos implementados se identifican por su nombre; los demás pueden señalarse como **Efecto 1 / Efecto 2 · marcador manual**, sin afirmar que el motor conoce su resolución. El número identifica el marcador en el panel, no una numeración oficial del texto. Los efectos enlazados al motor se descuentan de las opciones del turno; Big Eye conserva la vinculación a su copia. Los marcadores manuales no añaden acciones al simulador.

Las negaciones declaradas impiden proponer los efectos implementados de esa copia en campo; no impiden usarla como material. Dejar el campo elimina la negación de esa instancia, pero devolver una carta al Deck no borra el uso de un efecto por nombre durante ese turno. Todo cambio participa en Deshacer e invalida el cálculo anterior. No se registra un escenario en el navegador ni se modifican inventarios.

Implementación: `card-menu.js`/`card-menu.css`, estado validado en `engine.js` y consumo de marcas en `planner.js`. Pruebas: `calculator_card_status_test.cjs`, `calculator_workspace_ui_test.cjs` y la regresión completa `play_calculator_ui_test.cjs`.

## Práctica libre y aperturas aleatorias

`hand-practice.js` genera manos por copias físicas del Main, sin reemplazo. **Generar mano aleatoria** inicia un tablero nuevo al turno 1; usa cinco cartas iniciales en TCG/GOAT y cuatro en Rush. Los robos de fase se realizan con **Robar 1 carta**, sin avanzar turno o fase automáticamente. Rush/GOAT conservan la fase de Robo al cargar su apertura; el jugador ajusta el estado después del robo. Las Skills, Extra, Side y Reserva permanecen fuera del conjunto de robo.

El panel muestra las fotografías y copias de la mano real del tablero. Permite seleccionar una copia, moverla a su zona y pulsar **Explorar líneas de esta mano**. Esa acción utiliza el mismo worker local y sus efectos implementados; las rutas documentadas se consultan por separado. Generar una mano no añade nuevos efectos al motor ni certifica las líneas de cartas sin modelar.

**Robar 1 carta** descuenta las copias que ya están en mano/campo/Cementerio/destierro/materiales. Las cartas devueltas al Deck vuelven al conjunto de robo. Se rechaza un robo sin cartas suficientes, conservando el estado previo. **Deshacer** recupera la captura anterior. Generación, edición y cálculo funcionan sin conexión con los recursos previamente preparados; no se guarda el escenario.

Referencias para el número inicial de cartas: [reglamento oficial TCG](https://img.yugioh-card.com/en/downloads/rulebook/SD_RuleBook_EN_10.pdf) y [reglas oficiales Rush Duel](https://www.konami.com/yugioh/rushduel/howto/). El robo adicional es explícito para no confundir una apertura con una mano de una fase posterior.

El tablero presenta lados enfrentados, manos, filas de monstruos y Magias/Trampas, zonas laterales y zonas Extra compartidas cuando corresponden. Permite arrastrar o seleccionar y colocar. El panel muestra la imagen ampliable, posicion, control y materiales. Cementerio, destierro y Extra muestran pilas compactas.

## Motor de secuencias locales

`planner.js` construye acciones a partir del estado real capturado y explora estados posteriores; no necesita que exista una ruta documental con esa mano. `planner-worker.js` realiza la búsqueda fuera del hilo visual, con cancelación, presupuesto de estados, profundidad y deduplicación. Nunca cambia el tablero ni escribe datos.

La versión actual consulta metadatos locales de 243 identidades TCG enlazadas a 320 filas por el identificador nativo exacto. La generación reutiliza metadatos e identidades registrados; no interpreta prosa como código ni llama a una IA. El catálogo de efectos específicos comprende acciones de 13 cartas: Fenrir, Unicorn, Ogre, Riseheart (Invocación desde mano), Wraitsoth, Birth, Theosis, Shangri-Ira (procedimiento Xyz), Big Eye, Dark Hole, Feather Duster, Cosmic Cyclone y Book of Moon. No significa que todos los efectos de esas cartas estén modelados.

Puede encadenar búsquedas, Invocaciones, procedimientos de Sacrificio e Xyz, recuperación y retirada/control rival. Comprueba copias disponibles, zonas, identidad de materiales, control/propietario, Normal restante, atributos, costes de LP, límites por turno y bloqueos declarados. Los materiales Xyz permanecen acoplados y se consumen al desacoplar. No usa como objetivo identificable una carta desterrada boca abajo. Marca las restricciones del Extra y conserva instantáneas después de cada acción.

El jugador declara efectos ya usados, zonas bloqueadas y restricciones de Especial/Extra/Cementerio/efectos. El texto libre no se convierte en permisos: si hay restricciones libres sin estructurar, el motor pide revisarlas. Cambiar el estado invalida la vista previa y cancela la búsqueda anterior.

La salida muestra hasta seis alternativas con fotos, movimientos, fuente por acción, costes, LP y comparación inicial/final. La prioridad heurística valora recursos y retirada rival; no es win rate ni prueba de optimalidad. La búsqueda es limitada y no exhaustiva.

**Límites actuales:** no hay motor universal de cartas, cadenas, combate, respuestas ocultas o protecciones/disparos no implementados. Las líneas son condicionales a la ausencia de respuestas y de efectos adicionales no modelados. Arise-Heart boca arriba bloquea esta versión porque su reemplazo y disparos aún no se simulan. Rush/Skills no se evalúan con reglas TCG: se informa expresamente de su falta de cobertura. El motor enumera las cartas cuyos efectos específicos no utiliza; algunos monstruos solo tienen procedimiento básico de Normal. No se presenta como certificación reglamentaria integral.

Las rutas documentales previas siguen disponibles en un desplegable independiente de las secuencias calculadas. Su comparador de requisitos y confirmación manual no equivale a búsqueda de efectos.

## Sin conexión

Las listas y rutas se preparan al visitar la ficha con conexion y se almacenan para consulta bajo `stationx:playcalc:v1:`. No se guarda el estado del duelo, ultimo rival, mano, campo ni Cementerio. Cada apertura o cambio de rival inicia un tablero vacio. No hay controles para importar, exportar o guardar escenarios. Se mantienen Calcular opciones, Deshacer y Vaciar tablero.

`play-calculator.html?deck=ID` es la entrada independiente preparada por el service worker. Usa recursos propios y catalogos locales, sin Bootstrap/CDN ni consultas externas durante el calculo. El service worker conserva las imagenes consultadas como recursos estaticos. Una primera visita sin conexion no puede disponer de listas o imagenes nunca descargadas.

`tools/prepare_card_images.py` comprueba y recupera imagenes desde el indice de identidades revisado. Requiere requests y Pillow; no modifica listas ni busca imagenes por parecido.

## Organización y verificación

Código en `assets/modules/play-calculator/`: motor, almacenamiento, UI, arranque offline y estilos. Vista PHP en `includes/decks/play-calculator-view.php`. Los puntos de integración en `app.js` se limitan a la nueva categoría y a llamar `mount`.

Reglas ejecutables verificadas: `verified-rules.js` incorpora casos estrictamente acotados con fuente oficial de Memento (Hoja Oscura se destruye e Invoca Goblin del Deck; Goblin se destruye y envía dos nombres acreditados del Deck al Cementerio; Angbruja busca monstruos Memento tras una Invocación registrada; Desvantasma recupera una carta Memento al ser Invocado y puede revivir tras un envío identificado por Goblin; Maza se destruye a sí misma y busca una carta Memento) y Kashtira (Fenrir busca Riseheart). Se cargan mediante `study.js`, se someten a condiciones de fase, zonas, eventos causales y disponibilidad de copias, y no autorizan elecciones o cadenas no modeladas. Las variantes de un mismo efecto comparten un límite por turno y un único marcador en el tablero. Las Invocaciones Normales incluidas de Angbruja, Maza y Desvantasma son procedimientos distintos de la activación de sus efectos. `calculator_verified_rules_test.cjs` prueba el estudio editorial real, el bloqueo de adición al Deck, usos compartidos y la continuación Goblin → Desvantasma → recuperación. El resto de efectos, respuestas y replay de rutas A07–A10 sigue pendiente de certificación.

Creación Combinada (Memento) añade un procedimiento verificable de Invocación Especial desde mano o Cementerio: el motor exige cinco monstruos Memento **distintos**, de identidad conocida y en mano/Cementerio, los baraja al Deck y comprueba la disponibilidad de la zona. No permite invocarla con cuatro nombres, cinco copias del mismo nombre o bajo bloqueo de Invocación Especial. Es un procedimiento, no una activación de efecto. El soporte está acotado a las cinco identidades Memento auditadas de este estudio; las demás variantes y efectos continuos siguen pendientes.

Tatsunootoshigo añade dos efectos propios separados: Invocación Especial desde mano sólo si los monstruos propios boca arriba son Memento identificados (el desconocido o ajeno falla cerrado), y destrucción de Angbruja Nivel 3 con envío verificado de Desvantasma Nivel 2, sola o junto a Goblin Nivel 1. Ambos efectos tienen marcas de uso independientes; la segunda alternativa conserva causalidad para la Invocación de Desvantasma. Los demás niveles, víctimas y envíos de Tatsunootoshigo no se consideran aún implementados.

Fiesta de Huesos tiene opciones ejecutables restringidas a cinco parejas verificadas de monstruos Memento de nombres diferentes, desde mano o campo propio boca arriba: destruir el primero y añadir el segundo del Deck o Invocarlo Especialmente en Defensa en una zona legal. La Mágica Rápida se consume al activarse, conserva su ida al Cementerio cuando se niega el efecto y registra el evento de Invocación; las parejas restantes, su efecto de perforación desde Cementerio y las demás ventanas rivales aún no están modeladas.

Dormiveja posee tres efectos ejecutables acotados e independientes: Invocación Especial desde mano únicamente tras una destrucción de monstruo propio por efecto registrada **en el turno actual**; envío de Angbruja o Desvantasma del Deck tras destrucción propia por efecto; y Fusión de Rey Creación sólo mediante Dormiveja + Angbruja + Desvantasma conocidos en mano/campo. No se presenta como cobertura de todas las recetas, destrucción por batalla, ventanas de respuesta ni efectos posteriores del monstruo de Fusión. El guardia de destrucción incluye propietario y turno para no atribuir a un jugador eventos de otro.

Fusión Mementotlán dispone de una receta verificable de Rey Creación usando Angbruja, Desvantasma y Dormiveja en mano/campo, y una variante de Cementerio que se destierra como coste, destruye una Angbruja propia y añade Mementomictlán o Fiesta de Huesos. Rey Creación puede desterrarse desde Cementerio como coste para buscar Mementomictlán, o destruirse a sí mismo junto con un monstruo rival boca arriba identificado mediante su efecto rápido. Las demás recetas, envíos del Extra, grupos de objetivos, prioridades de cadena y efectos simultáneos quedan explícitamente fuera de estos casos estrictos.

La misma Fusión Mementotlán también permite una receta exacta de Dragón Gemelo con Angbruja y Desvantasma; cuando aparece por Fusión se acredita su variante de destruir Dormiveja y añadir Tatsunootoshigo y Goblin del Deck. Mementomictlán se activa desde mano si la zona de Campo está disponible y, al final de tu turno, puede colocar Fiesta de Huesos o Fusión Mementotlán desde el Cementerio con historial de colocación. Su bloqueo continuo de Magias/Trampas durante batalla y su efecto de Invocación posterior a una destrucción siguen sin simulación automática.

Dragón de Cuerno puede Invocarse desde mano con tres nombres Memento distintos en Cementerio; si fue destruido en campo por efecto existe un caso verificable de tres objetivos boca arriba. Akhíron recupera una carta Memento del Main en Cementerio/destierro después de su propia destrucción por efecto. S:P Pequeña Caballera tiene procedimiento Link-2 exacto con Angbruja y Tatsun de Main, sin atribuir el destierro al Invocarla (requiere material del Extra); Réquiem del Demoniherrero puede Sacrificarse como coste y traer Grabador del Deck en zona libre. Todos estos casos son parciales y se comprueban sin interpretar prosa como código ni extender sus efectos no acreditados.

La familia Demoniherrero incorpora dos procedimientos Link exactos desde Luna del Cielo Cerrado (a Réquiem o a Secuencia con Goblin), el equipamiento de Réquiem desde Cementerio a Grabador, el envío conjunto del equipo realmente vinculado y un monstruo rival desde campo al Cementerio por efecto de Grabador, y la Invocación Especial de Grabador tras devolver Réquiem del Cementerio al Extra como coste. Secuencia puede barajar Grabador y Goblin del Cementerio al Deck para Fusionar al Duque de la Defunción; el Duque puede desterrarse desde Cementerio como coste para recuperar Grabador. Dragostapelia coloca un contador Depredador sobre un monstruo rival boca arriba; aún no se aplica la reducción de Nivel ni la negación continua asociada al contador. Son recetas parciales y verificadas por tests del estudio real, no resolución universal de cadenas. **Atención a la legalidad del formato:** la base oficial muestra Luna del Cielo Cerrado y Llamado por la Tumba como Prohibidas en TCG a fecha de consulta; la captura del deck no constituye un certificado de lista de prohibiciones vigente.

Bloque 1, seguimiento de efectos: los efectos declarativos con varias zonas o materiales comparten una sola marca de uso por turno en el panel y no generan casillas duplicadas. Se conserva el registro manual `rule:<id>` del turno actual y se restaura el permiso en el turno siguiente. Las listas de CID y la exigencia de nombres diferentes son validadas en el adaptador; no se aceptan identificadores ni selecciones libres sin acreditar. La captura CL1–CL20 sigue siendo descriptiva: si hay una cadena pendiente, la búsqueda se detiene hasta actualizar el estado; aún no hay un solucionador general de cadenas.

La identidad de búsqueda también conserva los eventos causales `_ruleEvents` (destrucción, envío por efecto, materiales, disparadores), incluso cuando dos ramas llegan al mismo tablero visible. Los puntos de coste pagado conservan esos eventos sin mutar el estado original. Esto evita que una rama sin disparador acreditado herede acciones de otra rama causalmente distinta.

La captura estructurada permite **reproducir condicionalmente** efectos propios acreditados y negaciones de efecto o activación declaradas por el jugador. El motor `chain.js` usa el adaptador de reglas para conservar costes, comprobar las copias exactas y ofrecer estados resultantes aplicables al tablero. Si el efecto, la copia, los costes o la semántica de negación no están acreditados, se rechaza el replay.

Extensión de cadenas: el motor puede procesar hasta 20 eslabones estructurados cuando las activaciones propias tienen reglas verificadas, velocidad 2 en los eslabones posteriores al primero y coste pagable en el estado de activación. Primero paga costes CL1→CL20 y después resuelve efectos CL20→CL1; las negaciones rivales declaradas afectan únicamente a la activación propia inmediata anterior. Se conservan los límites expresos de uso tras negar activación. No certifica todavía cartas, costes, objetivos ni velocidades del rival ni respuestas a respuestas rivales; esas cadenas se rechazan. El caso CL1–CL4 del test demuestra resolución inversa, bloqueos y negación selectiva.

- `npm run test:planner`: secuencias nuevas, HOPT, costes, materiales, zonas, información oculta, aislamiento de formato e inmutabilidad.
- `npm test`: pruebas puras del motor, además de las suites de la plataforma y conservación editorial.
- `npm run test:calculator`: Playwright local, arrastre real, selección de zonas, cartas desconocidas, cambio a Rush, independencia de escenarios, diseño móvil y reapertura con red desactivada mediante un service worker real. Sin solicitudes externas ni acceso a producción.
- `npm run test:guide`: regresión de los 11 decks y 126 rutas tras reorganizar la guía.

Los resultados de navegador quedan en `outputs/play-calculator/`, excluido de Git. No equivalen a publicación ni a validación manual del sitio de producción.

## Análisis de escenario por todos los decks — 18-11-2026

La calculadora consulta `strategy-content-state.json`, igual que la biblioteca vigente, y valida el ID de cada estudio. Se retiró su dependencia del índice histórico de once decks. La comprobación local cubre 16 estudios y 193 rutas; el alcance se adapta al índice, no a una lista fija de IDs. Los slots nativos y alias exactos enlazan identidades. Un cambio de carta en un slot invalida la correspondencia anterior.

`study.js` adapta el estudio actual, conserva sus condiciones textuales y usa como mano explícita solo las fotos rotuladas «En mano». Las imágenes que solo mencionan una carta en un paso no se convierten en mano mínima. Las listas y referencias se preparan para consulta offline; el escenario sigue siendo efímero.

`rules.js` interpreta contratos `station-x-calculator/1` asociados al deck/formato. Tiene selectores y condiciones enumeradas y operadores de movimiento, obtención de recursos, coste LP, consumo de Normal y restricciones. Las operaciones desconocidas se rechazan. Costes y resolución son fases separadas; no hay `eval` ni traducción automática de prosa a efectos. `data-package.php` conserva el bloque JSON opcional del documento canónico al importar por ZIP y comprueba identidad/formato. La herramienta de importación local admite el mismo contrato.

`scenario.js` añade cobertura por ambos decks, cartas sin efectos modelados, campo con interacciones pendientes e información oculta. Para las activaciones implementadas calcula ramas condicionales con Ash conocida en mano, Impermanence conocida en mano y campo rival vacío, o Droll tras una adición. Conserva costes/usos y busca hasta dos continuaciones desde ese estado. Droll mantiene la primera adición y bloquea las posteriores. Una carta únicamente presente en la lista rival no genera una respuesta.

Se estudia una respuesta por rama, con hasta doce ramas entre todas las propuestas y búsqueda acotada de continuaciones. No acredita cadenas arbitrarias, inmunidades, efectos simultáneos ni una segunda respuesta. Nibiru se identifica como respuesta pendiente: no se inventa el conteo de Invocaciones, Sacrificios ni Ficha. Las Skills y efectos Rush siguen requiriendo sus contratos y operadores específicos; no se aplican reglas TCG por analogía.

Los datos actuales aportan **cero reglas declarativas**: el nuevo intérprete está probado, pero la documentación previa no equivale a su implementación. `node torneos_yugioh/tools/calculator_coverage.cjs` produce un informe local por cada deck y la lista de efectos faltantes; no conecta con la BD, no modifica inventarios y no escribe en la carpeta importable de prompts. `scenario_analysis_test.cjs` verifica los 16 estudios, respuestas, atomicidad, identidades y separación de formatos. `calculator_import_test.php` verifica el contrato ZIP/Markdown.
# Priorización estratégica local (rama calculator-combo-strategy)

El jugador puede elegir entre objetivos **equilibrado**, **romper campo**,
**desarrollar campo**, **conservar recursos** y **resistencia a respuestas
conocidas** antes de analizar un escenario.
El mismo explorador acotado ordena estados mediante ponderaciones explícitas:
cartas rivales retiradas del campo, valoración aproximada de monstruos propios,
cartas propias conservadas en mano y número de acciones. El resultado muestra
los indicadores observables por alternativa y la puntuación heurística relativa
al estado inicial. Este orden **no** certifica legalidad integral, OTK,
interrupciones disponibles, seguridad frente a respuestas desconocidas ni
probabilidad de victoria. No debe etiquetarse como «mejor jugada» absoluta.

Los efectos no modelados siguen excluidos y los estados con restricciones
críticas sin interpretación se rechazan. Las rutas A08–A10 del repositorio
externo son referencias documentales: solo una regla ejecutable con identidad,
fuente, guardas, coste y resolución verificados puede incorporarse al motor.

**Contrato verificable del objetivo estratégico:** `npm run test:strategy`
comprueba los cinco objetivos (`balanced`, `removal`, `board`, `resources`,
`resilience`), ordenación correcta para cada uno, cálculo determinista,
inmutabilidad del tablero, trazabilidad de estados y fuentes por paso,
identidad oculta no inventada y valor predeterminado para objetivos inválidos.
Los tres tests puros del calculador (`calculator_strategy_test.cjs`,
`calculator_battle_test.cjs`, `calculator_rule_operations_test.cjs`) forman
parte de `npm test`. El criterio funcional de **selección y ordenación de
objetivos** queda cubierto por esas pruebas; la inspección visual con
Playwright sigue siendo un control independiente y obligatorio antes de
declarar certificación integral de interfaz.

`battle.js` estudia aritméticamente ataques con ATK/DEF acreditados durante la
Battle Phase TCG/GOAT; no ejecuta ataques, revela cartas colocadas, modifica LP,
afirma OTK ni omite efectos de carta. Si no hay monstruos rivales calcula el
techo hipotético de un ataque directo por monstruo. Rush permanece sin cálculo
de combate hasta acreditar sus particularidades. `scenario.js` conserva las
respuestas conocidas, presenta ramas sin resolución y permite un objetivo de
resistencia que penaliza las respuestas modeladas sin continuación; no asume
que el rival carezca de otras interrupciones.

El intérprete `rules.js` admite ahora operaciones estructuradas de daño,
recuperación de LP, negación con duración y cambio de posición, con validación
de tipos y condiciones de zona. Aplica intrínsecamente el bloqueo de añadir del
Deck a la mano y rechaza Invocaciones directas desde Extra sin un procedimiento
acreditado. Esto amplía el lenguaje de efectos, **no crea automáticamente las
reglas individuales de las cartas**. La prueba
`tests/calculator_rule_operations_test.cjs` comprueba estos contratos.

Los estados explorados incluyen ambos totales de LP en su clave de
deduplicación. Cuando una regla verificada deja LP rivales en cero, la
búsqueda prioriza ese estado y no genera acciones posteriores al final del
duelo; una cifra de daño **condicional** no se presenta como victoria segura.

La captura física ahora dispone de **cadena pendiente estructurada**:
eslabones CL1–CL20 con jugador, identidad conocida opcional y descripción de
la activación, eliminación individual y deshacer. No se inventa la identidad
de una carta desconocida. `planner.js` se detiene con explicación mientras
quede un eslabón pendiente: las cadenas deben resolverse y el estado debe
actualizarse antes de calcular otras líneas. La prueba
`calculator_chain_capture_test.cjs` cubre integridad, límites, inmutabilidad,
numeración y bloqueo del planificador.

**Robo propio desconocido C07:** Quimera Guardián registra `selfUnknownHand` separado de `unknown.hand` rival. La mano propia visualiza `robo propio por identificar`, sin crear una copia ficticia ni atribuirle un CID. Hasta registrar su identidad real desde la mano, las búsquedas y envíos de cartas concretas del Deck fallan cerrado: una carta desconocida podría ser el objetivo buscado.
