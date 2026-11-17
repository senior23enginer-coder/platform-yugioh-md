# Creador de combos y estrategias

Herramientas → **Creador de combos y estrategias**. Permite construir guías propias con las cartas realmente cargadas de un deck. No importa las imágenes de referencia como combos ni interpreta sus jugadas como reglas vigentes.

## Flujo de uso

Las guías documentadas se convierten al motor nativo del creador, no solo al lector. Al elegir uno de los once decks se cargan sus rutas en el selector «Combos y estrategias del deck» y aparece la primera en el lienzo. El grupo «Documentados · editar y guardar» contiene las bases que todavía no tienen una versión propia guardada. Desde «Mis combos y estrategias», elegir una ruta abre sus nodos y conexiones en el editor. «Nueva creación» sigue preparando un lienzo vacío.

Las 105 rutas y sus 114 estados/variantes se convierten sin sustituir cartas por semejanza de nombre: se usan los IDs nativos vinculados al inventario del mismo deck. Se mantienen los pasos, costes y condiciones documentados; los resultados alternativos salen de un nodo de decisión y sus recursos se distribuyen en partes de hasta ocho cartas cuando lo requiere el modelo. Los fragmentos de texto largos se conservan en nodos de detalle. Las cuatro Skills quedan fuera de la mano, y los catorce registros históricos conservan el estado no ejecutable. Las identidades desconocidas en la fuente siguen descritas en texto, sin asignarles otra carta.

Abrir una base no escribe en la base de datos. «Guardar en el deck» utiliza la persistencia, validación de inventario e historial existentes. El ID de cada base es estable por deck y ruta: la versión guardada aparece una sola vez y tiene prioridad al reabrir, sin restablecer las ediciones ni cambiar el JSON editorial original.

La ficha «Mis combos y estrategias» reúne la biblioteca documentada y las creaciones propias. La primera usa la misma fuente activa, filtros, guías con cartas y campos finales de «Estilo y estrategia»; abre inicialmente «Aprende jugadas». Las referencias históricas conservan su apartado separado. Las creaciones manuales mantienen su edición e historial. Cada fuente carga de forma independiente: un fallo de lectura de creaciones no oculta las guías, y un fallo de guías permite reintentar sin afectar las creaciones. La consulta no duplica registros en la base de datos ni cambia inventarios.

1. Elegir un deck con Main cargado. El catálogo contiene exclusivamente sus cartas, separadas en Main, Extra, Side y Reserva, con nombres, cantidades e imágenes originales cuando están disponibles.
2. Escribir título, tipo de creación y orden de turno. Desplegar **Objetivo, requisitos y límites** para documentar el contexto técnico.
3. Añadir mano inicial, jugadas, decisiones y resultado. Arrastrar cartas al lienzo crea pasos; soltarlas sobre un paso existente agrupa las piezas. También se puede seleccionar una carta y utilizar los botones para añadirla como paso o agruparla.
4. Mover los pasos por su cabecera; el teclado permite moverlos con flechas. Pulsar **Conectar** en el origen y **↓** en el destino. Seleccionar una flecha permite explicar su condición o retirarla. Escape cancela una conexión pendiente.
5. Explicar acción, costes, restricciones, origen/destino y consecuencias de cada paso. Se permite reutilizar una carta en distintos pasos para mostrar su recorrido. No se supera su cantidad cargada dentro de un mismo paso; la mano inicial admite solo Main.
6. Consultar **Guía paso a paso** y **Matriz de jugadas**. La matriz compara las creaciones del mismo deck, incluida la edición abierta. Las ramas conservan sus conexiones y explicaciones en el editor y sus procedencias en la guía.
7. **Guardar en el deck** confirma el guardado en la base de la plataforma. Reabrir mediante el selector de creaciones guardadas o desde **Ficha del deck → Mis combos y estrategias**. Desde la ficha se puede volver al editor.

El lienzo y los cambios pendientes permanecen en memoria durante la edición. No se guardan automáticamente en localStorage ni se exportan/importan escenarios. Cerrar la página con cambios pendientes activa el aviso del navegador. Sin conexión, un fallo al guardar conserva la edición abierta y no anuncia un éxito falso. El guardado necesita conexión con la plataforma.

## Persistencia e integridad

### Recorridos y edición técnica

La guía comienza en una raíz elegida y avanza únicamente por una conexión seleccionada. Ante varias salidas muestra sus condiciones como alternativas; no las concatena en una secuencia. **Anterior** regresa por el recorrido y **Ver todos los pasos** conserva una vista de consulta con las procedencias explícitas, sin flechas ficticias entre alternativas.

Las fotografías de guía y matriz se amplían al pulsarlas; la guía también admite Enter/Espacio sobre la fotografía. En el catálogo/lienzo se conserva la selección y el arrastre, con doble clic para ampliar. La matriz usa bloques apilados en móvil.

Cada paso puede conservar campos técnicos separados (coste, objetivo, origen, destino, momento/cadena, restricciones, recursos y fuente). Para las cartas se pueden indicar zona, posición y materiales documentados. La mano inicial y el resultado muestran un tablero parcial; las zonas desconocidas se etiquetan como no documentadas. El tablero conserva 5 zonas TCG/GOAT y 3 Rush; GOAT/Rush no muestran zonas de Monstruos Extra compartidas. No calcula efectos ni completa ubicaciones automáticamente.

El estado documental permite **Borrador**, **Condicional**, **Pendiente de verificación** y **No ejecutable**. No existe una opción que certifique legalidad desde el editor. Las Skills Rush disponibles se leen del registro editorial publicado del mismo ID y se seleccionan aparte: requisito, efecto, fotografía y límites; nunca se incorporan a las cartas ni a la mano. Si no existe información publicada, no se inventa una Skill.

**Historial** permite consultar las revisiones e importar una de ellas al editor. Guardarla añade una revisión nueva sin borrar las anteriores. **Duplicar creación** prepara otro documento con ID nuevo, que solo se persiste al guardar. Los registros antiguos siguen siendo legibles aunque no tengan los campos opcionales nuevos. Si el Main se vacía, la ficha conserva la lectura histórica con sus cartas de la instantánea, pero bloquea crear/guardar hasta que exista un Main cargado.

- `sx_ygo_created_combos` conserva la versión activa por ID estable y deck; `sx_ygo_created_combo_revisions` conserva cada revisión completa, incluida la lista de cartas utilizada.
- El módulo valida cartas por ID nativo y pertenencia al deck en el servidor. Rechaza identificadores ajenos, manos con Extra/Side, cantidades excesivas por paso, referencias inexistentes y ciclos de conexiones.
- Cada guardado compara la revisión y el hash del inventario, bloquea el deck/lista dentro de la transacción e inserta la revisión junto a la actualización activa. Un reintento con el mismo contenido no duplica creaciones ni revisiones.
- Si la lista cambia, la ficha avisa y conserva la instantánea anterior para leer la guía original. **Actualizar cartas** refresca el catálogo sin borrar el trabajo del editor; los pasos incompatibles requieren corrección antes de guardar.
- No se trasladan combos guardados entre decks. Una edición concurrente produce un conflicto visible; la versión del servidor no se sobrescribe silenciosamente.
- El módulo no escribe decklists, cantidades, inventarios editoriales, resultados, LP, calendario ni clasificaciones. Una creación manual no certifica efectos, legalidad, OTK ni ejecución de la secuencia.

## Organización

- `assets/modules/combo-creator/model.js`: documento, operaciones puras y orden de conexiones.
- `assets/modules/combo-creator/ui.js` y `styles.css`: catálogo, lienzo con SVG, edición, vistas y lector de ficha.
- `includes/decks/combo-creator.php`: validación, transacciones y revisiones.
- `includes/decks/combo-creator-details.php`: contexto editorial de formato/Skill y validación de campos técnicos/zonas opcionales; no altera inventarios ni importa entregas.
- `includes/decks/combo-creator-http.php`: consulta GET y guardado POST en `?api=combo_creator&deck_id=ID`. Usa sesión nativa, token CSRF y respuestas sin caché. No permite combinar este endpoint con las vistas públicas.
- `includes/decks/combo-creator-view.php`: pantalla del menú Herramientas.

## Verificación

`npm test` incluye la prueba PHP local de persistencia, historial, conflictos, ciclos, inventario y conservación de listas. `npm run test:combos` utiliza Playwright y el handler PHP real con una base SQLite temporal local: arrastre, movimiento, conexiones, guardado/reapertura, guía, matriz, ficha, aislamiento por deck, CSRF y móvil. Configurar `PHP_BINARY` si PHP no está en PATH.

Las pruebas no escriben en producción. Las capturas y resultados se generan en `outputs/combo-creator-20261103/`, fuera de Git. El frontend no necesita bibliotecas de diagramas externas. Una publicación del módulo usa la creación nativa e idempotente de sus dos tablas, sin alterar tablas existentes.

`npm run test:combos:regression` reproduce los fallos corregidos: aislamiento de selección entre decks, recorrido excluyente de ramas, ampliación por teclado, campos y tablero parciales, historial/restauración como revisión nueva, rechazo de historial ajeno, duplicación en memoria, matriz móvil y lectura histórica con Main vacío. Evidencias en `outputs/combo-creator-fixes-20261103/`.


## Sincronización automática de combos documentados

Al guardar una ruta documental en el editor, su documento nativo y su prompt actualizado se versionan en la misma transacción. El vínculo se valida contra el ID estable del deck/ruta; duplicar una creación rompe ese vínculo y conserva el original.

Las revisiones vigentes se sincronizan a `01_INFORMACION_A_IMPORTAR/revisiones_editor/`. Antes de analizar o reimportar, leer su `index.json` y los documentos enlazados: tienen prioridad sobre la secuencia histórica de la misma ruta. `GUIAS_Y_DECISIONES_V16.json` enlaza la revisión vigente sin borrar el original. Conservar nodos, cartas, explicaciones, condiciones, zonas y ramas del grafo; no volver a la versi?n histórica ni aplanar alternativas.

`tools/combo_source_sync.py --watch` consulta la API nativa cada diez segundos con ETag y actualiza archivos locales de forma idempotente. Si el equipo está desconectado, la revisión queda guardada en la plataforma y se recupera al reconectar. Historial local inmutable por revisión; conflictos y respuestas ajenas detienen la sincronización sin sobrescribir las fuentes. `tools/import_curated_v62.py` incorpora estas revisiones en las futuras importaciones. Los inventarios no cambian.

Instalación local automática: `tools/install_combo_source_sync.ps1`. La tarea `StationX-ComboSourceSync` arranca al iniciar sesión. Estado y errores en `outputs/combo-source-sync/status.json` (fuera de Git). La API GET existente admite `source_prompts=1`; no devuelve token de sesión ni realiza guardados de combos.
# Selector completo de decks y formatos

El creador lista todos los decks de `DML_DATA.allDecks`, sin excluir los que carecen de Main o de inventario. Incluye filtro de formato basado en los códigos reales y búsqueda por nombre de deck/equipo. Los decks sin cartas se identifican en el selector; al abrirlos se explica cómo cargar su lista desde la ficha, sin inventar cartas ni modificar el inventario.

Filtrar conserva la creación y el deck abiertos. Si el deck no coincide con el filtro, aparece separado como «Deck abierto (fuera del filtro)». Cambiar efectivamente de deck conserva la confirmación de descarte para cambios sin guardar. Los accesos a un combo desde su ficha mantienen su ID aunque exista un filtro activo. Prueba: `tests/combo_creator_picker_ui_test.cjs`; guardado/historial/navegación: `tests/combo_creator_back_ui_test.cjs`.

