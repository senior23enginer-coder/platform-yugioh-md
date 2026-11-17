> Actualizado el 14/11/2026: el módulo ahora se llama Mundos, solo admite TCG y está en Decks y equipos. Ver [documentación vigente](deck-worlds.md). El contenido siguiente describe la versión anterior.

# Atributos de decks

En Herramientas → Atributos de decks se gestiona la clasificación organizativa de todos los decks. No requiere cartas cargadas. No modifica atributos oficiales de cartas, inventarios, equipos, ligas, divisiones ni resultados. Las propuestas conversacionales no se aplican como asignaciones automáticas.

## Pantallas

- `#atributos_decks`: tabla de consultas con búsqueda por deck/equipo/atributo, categoría principal, cualquier atributo, formato y asignación. Contadores por categoría principal y decks sin asignar; actualizar, crear, editar y quitar asignaciones.
- `?attribute_deck=ID#atributos_deck_editar`: formulario separado para crear o actualizar. La categoría principal es siempre el primer elemento de la lista ordenada. «Hacer principal» promueve un atributo ya seleccionado. Quitar el principal promueve el siguiente.

De uno a tres valores diferentes entre OSCURIDAD, TIERRA, LUZ, AGUA, VIENTO y FUEGO. Guardar una lista vacía no se permite; la operación «Quitar todos» deja el deck sin categoría tras confirmación. Los atributos se muestran también en la tabla de decks y en la ficha, con acceso al editor.

## Persistencia y seguridad

`includes/decks/attributes.php` gestiona dos tablas independientes: `sx_ygo_deck_attributes` contiene la lista ordenada, revisión y fecha; `sx_ygo_deck_attribute_revisions` contiene las revisiones inmutables, incluidas eliminaciones. El registro vacío se conserva como tombstone para impedir que una pestaña antigua recree una asignación sin detectar el conflicto.

La escritura bloquea el deck en MySQL, verifica su existencia y la revisión esperada, y guarda estado e historial en una transacción. Un guardado idéntico no crea revisión. Una edición obsoleta se rechaza sin sobrescribir. Las fechas se registran en America/Bogota. Los datos son globales por ID de deck y no se reinician al cambiar de temporada.

`?api=deck_attributes` reutiliza la sesión y el permiso nativo de edición. GET devuelve catálogo, asignaciones, permiso y token; con `deck_id` incluye hasta 25 revisiones. POST recibe JSON (`action`, `deck_id`, `expected_revision`, `attributes`) y exige `X-CSRF-Token`. Acciones `save` y `remove`. Respuestas 403 para falta de autorización/CSRF, 409 para conflicto, 422 para datos inválidos y 503 para error de servicio sin exponer SQL. No se habilita escritura en consultas públicas de temporada/en vivo.

## Pruebas

- `tests/deck_attributes_test.php`: altas, cambios, principal, máximo tres, duplicados, identidad, revisión, borrado/recreación, historial, rollback y preservación de otros datos mediante SQLite aislado.
- `tests/deck_attributes_model_test.cjs`: reglas del modelo, agrupación y filtros sin depender de inventarios.
- `tests/deck_attributes_ui_test.cjs`: prueba optativa contra fixture local, creación/guardado/reapertura, conflicto que conserva el borrador, CSRF, validación del servidor y escritorio/móvil. No ejecutar operaciones de prueba contra producción.

Archivos de UI: `assets/modules/deck-attributes/{model.js,ui.js,style.css}`. Vista PHP: `includes/decks/attributes-view.php`; API: `attributes-http.php`. No se usa almacenamiento del navegador para persistir asignaciones.
