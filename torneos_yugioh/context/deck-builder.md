# Editor de listas e inspector de cartas

La ficha del deck permite consultar las listas y abrir ?Editar decklist?. Los cambios permanecen en un borrador hasta pulsar ?Guardar decklist?. Se conservan el control de versi?n, la sesi?n y la auditor?a existentes.

## Inspector

Cada miniatura del cat?logo, de las listas guardadas y del borrador abre un inspector. Descarga una sola vez y sirve desde cach? local la imagen original grande de YGOPRODeck. Permite ajustar al espacio, tama?o original, zoom de 25?400 %, rueda, arrastre para desplazar y pellizco t?ctil. Muestra la resoluci?n real. Si falla la imagen grande, identifica expresamente que muestra la miniatura. Escape cierra solo el inspector y devuelve el foco a la carta.

`GET ?api=deck_builder&op=image&id=ID` mantiene la miniatura. `size=full` solicita la imagen grande. Solo acepta IDs catalogados, tama?os conocidos y endpoints fijos del proveedor; no recibe URLs externas.

## Movimiento

En edici?n se pueden arrastrar grupos entre Main, Extra, Side y Reserva. ?Mover a? ofrece el mismo control por teclado y en m?vil. Se mueve toda la cantidad del grupo; si existe la carta en el destino, suma sus copias y conserva las referencias para recuperar sus anotaciones al guardar. Una funci?n distinta de la carta movida queda documentada en notas, sin superar el tama?o del campo de funci?n. El l?mite t?cnico de cada fila sigue siendo 99 copias. No se agregan reglas competitivas ni se convierte autom?ticamente la reserva en Side Deck.

## B?squeda

Se muestran coincidencias totales y ?P?gina X de Y?, con 20 resultados por p?gina y botones seg?n las p?ginas restantes. El total se calcula con `ceil(total_rows / 20)`; el campo `total_pages` original del proveedor representa offsets y no se usa como n?mero de p?ginas visibles. La clave de cach? cambia para evitar respuestas antiguas sin los nuevos totales.

La API oficial admite nombres en ingl?s y c?digos. El espa?ol no est? soportado: `language=es` devuelve HTTP 400. Se comprob? con `mago` y con el c?digo 46986414. La limitaci?n est? visible en el editor; no se traducen ni se inventan nombres oficiales. Referencia: https://ygoprodeck.com/api-guide/#endpoint-languages

## Evidencia

`outputs/ygo-builder-inspector-20261028/`: pruebas de arrastre nativo y por eventos, combinaci?n de grupos, cantidades y referencias; guardado interceptado sin escrituras; API real de 133 coincidencias en siete p?ginas, ?ltima p?gina de 13 cartas; visor en la ficha real, foco dentro de la modal, imagen de 813 ? 1185 y escritorio/m?vil. Publicaci?n con verificaci?n de bytes y comparaci?n de las 44 filas de cartas originales, perfiles y asignaciones deportivas.


## Catálogo e inspector: arquitectura del backend · 18 de noviembre de 2026

El recorrido HTTP conserva `?api=deck_builder`: `includes/deck_builder.php` captura la solicitud y delega a `Infrastructure/Http/CardCatalog/CatalogController`. El controlador mapea detalle local/proveedor, búsqueda e imagen y devuelve una respuesta; el emisor es responsable de cabeceras, JSON y bytes. Las fachadas nativas mantienen las firmas utilizadas por el editor existente.

`Domain/CardCatalog` valida identidad y parámetros de búsqueda. `Application/CardCatalog` separa `SearchCards`, `LookupCard`, `InspectCachedCard` y `GetCardImage`; depende únicamente de los puertos `CatalogCache`, `CatalogProvider`, `CatalogImages` y `Clock`. `CardSummary` es una proyección del efecto original del proveedor, no un resumen estratégico inventado.

`Infrastructure/Cache/CardCatalog` implementa archivos, lectura local sin crear carpetas, bloqueo por búsqueda/imagen y reemplazo atómico. `Infrastructure/Network/CardCatalog` conserva el bloqueo y espera compartidos del proveedor, límites de bytes, TLS, tiempos y estados HTTP admitidos. `Composition/CardCatalogModule` conecta las dependencias sin I/O en constructores.

Se conservan búsqueda de veinte cartas, clave `pagination-v2`, frescura de 48 horas, recuperación de respuesta vencida únicamente ante fallo de descarga/JSON, identificación exacta, imágenes grandes y miniaturas separadas, JPEG y descargas solo para IDs catalogados. La consulta local nunca descarga ni fabrica texto. La política de caché y el reloj pueden sustituirse sin tocar el sistema de archivos.

`card_catalog_architecture_test.php` comprueba 71 condiciones con puertos en memoria y una carpeta temporal única: diez escenarios nativos, evidencia original intacta, tres comparaciones de algoritmos, validación, paginación, caché fresca/vencida, fallos, identidad, modo local, texto exacto, HTTP e imágenes. No se llamó a YGOPRODeck ni a producción. Quedan pendientes una comprobación de proveedor real y concurrencia multiproceso en el entorno objetivo; las pruebas locales no los certifican.

Esta migración abarca el catálogo del inspector/editor. El guardado SQL de listas y sus transacciones permanece separado y pendiente de migración; no se modificaron inventarios, cantidades, anotaciones o fuentes de prompts. No se cambió la interfaz ni se desplegó este refactor.
