# Mundos de decks

Ubicación: **Decks y equipos → Mundos**, ruta `#mundos`.

La tabla lista los seis mundos (Oscuridad, Tierra, Luz, Agua, Viento y Fuego), sus decks asociados y cuántos los tienen como principal. «Ver mundo» abre `?world=dark#mundo_decks` (código correspondiente) con todos sus integrantes, tanto principales como secundarios.

Solo se admiten decks TCG Advanced o TCG Traditional, con o sin cartas cargadas. Se reutiliza la identificación nativa de formatos, también en el servidor. GOAT, Rush y OCG quedan fuera. Las asignaciones históricas de otros formatos no se borran de la base; no se muestran ni se editan en Mundos.

En cada mundo se puede buscar y añadir un deck, quitarlo exclusivamente de ese mundo y promoverlo como principal. Estos cambios se guardan directamente en la plataforma, conservando los otros mundos y el historial. Cada deck debe conservar entre uno y tres mundos distintos; el primer elemento determina su mundo principal. Al quitarlo, el siguiente pasa a principal. No se permite quitar el último: se debe añadir un sustituto antes de retirarlo.

«Asignar deck» es una acción compacta. El formulario separado `?world_deck=650#mundos_deck_editar` permite gestionar hasta tres mundos y consultar el historial. Las rutas antiguas `#atributos_decks` y `?attribute_deck=650#atributos_deck_editar` se redirigen a la pantalla correspondiente.

Se conserva el contrato interno `?api=deck_attributes`, los códigos de categoría y las tablas `sx_ygo_deck_attributes` / `sx_ygo_deck_attribute_revisions`. GET añade `eligible_deck_ids`; POST valida el formato real dentro de la transacción, mínimo uno, máximo tres, unicidad, CSRF y revisión esperada. Los cambios concurrentes devuelven 409 sin sobrescribir la selección local.

No se modifican cartas, inventarios, ligas, resultados ni atributos individuales de las cartas. La asignación inicial de los 56 decks TCG actuales se basa en la naturaleza de su arquetipo o nombre, por orden de afinidad. Se documenta en `deck-worlds-nature.json`; no se fuerza equilibrio numérico ni se supone que todas sus cartas tengan esos atributos. Las elecciones manuales previas se conservan.

Pruebas: `tests/deck_attributes_test.php`, `tests/deck_attributes_model_test.cjs`, `tests/deck_worlds_ui_test.cjs`. La última utiliza PHP y SQLite aislados y revisa tabla, miembros secundarios, altas/bajas, principal, límites, TCG, historial, conflictos, enlaces anteriores y móvil/escritorio. Las verificaciones públicas son de consulta y no guardan asignaciones de prueba.

## Organización de implementación

El backend está separado en `src/Domain/DeckWorlds`, `src/Application/DeckWorlds` e infraestructura HTTP/PDO. Los includes conservan la API nativa. La documentación de dependencias, bloqueos e historial está en `source-architecture.md`. El cliente HTTP frontend es `assets/modules/deck-attributes/api.js`, inyectado por la UI; las reglas puras permanecen en `model.js`.
