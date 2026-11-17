# Importar / exportar Data

La ficha de cualquier deck tiene una entrada independiente con tres acciones:

- **Exportar listado de cartas:** TXT del inventario nativo, separado por Main, Extra, Side y Reserva, con cantidades y nombres bilingües cuando están documentados. Reutiliza el exportador existente.
- **Exportar data:** ZIP con una única carpeta `DECK_<ID>_<NOMBRE>/`, que contiene su ficha curada, `imagenes/`, `guias/` y `datos/stationx-deck.json`. No hay archivos sueltos fuera de la carpeta. Un deck sin estudio también exporta una plantilla ligada a su ID e inventario real.
- **Importar data:** selector ZIP, previsualización de fichas/jugadas nuevas, modificadas y retiradas, seguida de carga explícita. Actualiza la biblioteca utilizada por Guía de juego y el creador visual después de recargar. No requiere Codex, Python ni un equipo local conectado durante la importación web.

## Flujo con ChatGPT

1. Exportar la data desde la ficha del deck.
2. Adjuntar ese ZIP a ChatGPT y solicitar la actualización. Las instrucciones están dentro de `datos/stationx-deck.json`; no se generan LEEME ni informes adicionales.
3. ChatGPT devuelve un ZIP con la misma carpeta del deck y `datos/stationx-deck.json` actualizado. **Kashtira Pure es la referencia de estructura y exhaustividad** (apartados 01–10, cartas/sinergias, planes, práctica, rutas/estados), conservando los datos propios y el formato de cada deck. Elfnote fue un ejemplo de ruta de carpeta, no la plantilla de contenido. No se copian cartas, mecánicas ni combos de Kashtira a otros decks. El objeto `record` usa el mismo modelo `station-x-curated-v62` de la biblioteca actual; las imágenes nuevas se incluyen dentro de esa misma carpeta y se referencian por su ruta exacta.
4. Importar el ZIP en esa misma ficha y revisar el resumen. Confirmar y abrir «Ver data actualizada».

La exportación utiliza `content_mode=auto`: detecta si cambió solamente `record` o solamente el Markdown y procesa la fuente modificada. Si ambos cambiaron, ChatGPT debe declarar `content_mode=structured` o `markdown` para resolver cuál es la autoridad. `structured` integra el modelo completo; `markdown` transforma las secuencias numeradas y conserva el texto completo. No se inventan estados espaciales, decisiones ni ramas ausentes. Se acepta la carpeta directa o envuelta en `01_INFORMACION_PARA_IMPORTAR/decks/`; se rechazan carpetas de otro deck o varios decks. Las carpetas históricas conocidas y sus vínculos confirmados siguen admitidos.

## Persistencia

`?api=deck_data&deck_id=ID&op=status|export|preview|commit` usa la sesión/CSRF nativa. La base se consulta con el inventario y las revisiones existentes; no hay escrituras SQL. Las cantidades y zonas del ZIP deben coincidir con el inventario actual. Las ediciones manuales del creador conservan su prioridad e historial; cuando existen se incluyen como referencia dentro del mismo JSON, sin crear otro archivo. El proceso de lectura, transformación, imágenes, versionado y activación se ejecuta en PHP en el servidor. No requiere scripts ni sincronización con Windows para importar.

La publicación crea una carpeta inmutable `assets/editorial/deck-data-ID-FECHA-ALEATORIO/` con JSON, imágenes, ZIP original y puntero anterior. Un bloqueo global y sustitución del registro por `rename` activan una sola versión cuando todos los archivos están escritos. El puntero anterior se conserva para recuperación operativa; el contenido no se borra. La concurrencia comprueba el deck, su inventario y las revisiones entre previsualización y confirmación. Otros decks y resultados deportivos quedan intactos.

Requisitos del servidor: PHP ZipArchive, permisos de escritura en `assets/editorial`, directorio temporal y límites PHP de subida suficientes para el ZIP (máximo de la aplicación: 48 MiB comprimidos; 120 MiB descomprimidos; 3000 archivos). Las rutas peligrosas, enlaces simbólicos, ejecutables, ZIP dañados, esquemas ejecutables y cambios de identidad/inventario se rechazan. Los HTML/SVG originales se conservan dentro del ZIP; no se publican como páginas ejecutables. El lector Markdown se genera escapando el texto.

## Preparación y comprobaciones locales

`tools/build_deck_data_sources.py` lee el repositorio hermano `../gpt-yugioh-promps-platform` (o `YGO_PROMPTS_ROOT`) y prepara los ZIP de originales públicos de los 16 estudios activos. Excluye `03_COMPROBACIONES_NO_IMPORTAR` y vecinos de otros decks. Deben publicarse junto con los módulos y endpoints al desplegar la función.

```powershell
C:\Python314\python.exe torneos_yugioh/tools/build_deck_data_sources.py
C:\xampp\php\php.exe -d extension=zip torneos_yugioh/tests/deck_data_test.php
node torneos_yugioh/tests/deck_data_ui_test.cjs
```

La importación conserva estados documentales. Importar una secuencia no certifica su legalidad ni ejecuta efectos de cartas.
