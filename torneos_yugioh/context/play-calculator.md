# Calculador de jugadas

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
