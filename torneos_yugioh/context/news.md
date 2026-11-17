# Centro de noticias

La sección `#noticias`, el acceso del encabezado y «En el radar» de la portada muestran publicaciones de las cuatro fuentes solicitadas. La información se consulta al visitar el sitio. No se instalaron cron, servicios persistentes, temporizadores de noticias ni tareas con el navegador cerrado.

## Presentación

Tarjetas con miniatura disponible, fuente, fecha, formato/juego, tema y estado de lectura. Filtros por fuente, texto, formato/juego, tema y publicación; vistas Explorar, Sin leer y Guardadas; paginación de doce publicaciones. El lector en modal presenta el título y la descripción originales, coincidencias textuales con decks de la plataforma y acceso al artículo original. El contenido no se presenta como traducción ni como resumen generado.

Los indicadores de noticias son independientes de las alertas de duelos. No se generan notificaciones push ni ventanas automáticas por cada importación. El lector se abre por una acción del visitante. Las modales y sus contenidos permiten desplazamiento vertical en móvil; las fuentes se desplazan horizontalmente.

## Fuentes y conectores

- **YGOrganization:** RSS canónico `https://ygorganization.com/feed` (la variante con barra final redirige con HTTP 308), complementado con las miniaturas públicas de su portada.
- **YGOPRODeck:** enlaces y extractos de artículos de su portada pública. Se extraen miniaturas y se enriquecen hasta dos publicaciones por revisión con metadatos públicos, incluidas fechas de JSON-LD. No se confunde la API de cartas o el RSS de decks con un feed editorial.
- **Master Duel Meta:** tarjetas públicas de artículos de su portada, con título, fecha visible y miniaturas. Hasta dos artículos por revisión se enriquecen mediante metadatos públicos. No se utiliza su endpoint de artículos, que devolvió HTTP 500 durante la comprobación.
- **Yu-Gi-Oh! Meta:** catálogo JSON público que utiliza su propia portada, observado en una navegación normal: `/api/v1/articles` con artículos visibles, límite veinte, orden de fecha y exclusión de markdown. Es un contrato público no documentado y puede cambiar; su fallo se aísla del resto.

Cada fuente tiene estado propio, última consulta correcta, último intento, próxima disponibilidad, cantidad guardada y último error. Los errores de conexión o de estructura conservan las publicaciones existentes.

## Actualización por visita

`assets/news.js` obtiene primero las publicaciones guardadas. Tras la primera consulta correcta de la visita, solicita secuencialmente hasta cuatro comprobaciones. El servidor selecciona una fuente vencida por solicitud, con un bloqueo MySQL compartido que evita importaciones simultáneas. Reutiliza los resultados durante treinta minutos. No espera a tener abierta la sección Noticias para comprobar las fuentes.

El botón «Comprobar novedades» repite la comprobación; las fuentes correctas conservan la ventana de treinta minutos. Las fallidas pueden reintentarse manualmente después de un minuto. El reintento normal queda disponible después de cinco minutos, en una visita posterior. Si nadie visita el sitio, no se efectúa ninguna recopilación.

No hay llamadas al proveedor desde cada tarjeta del navegador: las miniaturas pasan por el servidor y quedan en su caché local. Se admiten únicamente HTTPS y una lista fija de hosts de las fuentes. No se siguen redirecciones ni se aceptan destinos proporcionados por el cliente.

## Persistencia y contrato

- `sx_ygo_news_sources`: control de consultas, errores y estado de cada fuente.
- `sx_ygo_news_items`: URL única por hash, fuente, título, descripción breve, imagen, fecha y precisión, formato, tema, huella de contenido, primera detección, actualización, lectura y guardado.

No se almacena el cuerpo completo del artículo. La importación no duplica URLs ni restablece los estados de lectura/guardado al actualizar un contenido. Una publicación se marca como leída al abrirla o por una acción explícita. El estado queda en MySQL y es compartido por los dispositivos del operador único de la plataforma.

`GET ?api=news&op=list` entrega publicaciones paginadas, filtros, contadores y estado de las cuatro fuentes. `GET ?api=news&op=image&id=ID` sirve una miniatura catalogada. `POST ?api=news&op=sync|read|bookmark|read_all` requiere el token de la sesión. Los POST aceptan JSON acotado y los filtros SQL usan parámetros o valores validados. La sesión PHP se libera antes de consultar proveedores.

Las fechas con zona se guardan en UTC y se presentan en Colombia. Cuando la fuente solo permite conocer el día, se conserva esa precisión. Las fechas desconocidas no se inventan y quedan fuera de los filtros por período de publicación.

Las clasificaciones son orientativas. Master Duel permanece como entorno independiente de TCG/OCG; las coincidencias con nombres de decks no constituyen una recomendación o certificación de compatibilidad. Ninguna noticia modifica decks, reglamentos, resultados, clasificaciones o asignaciones deportivas.

## Verificación de la entrega

Evidencia en `outputs/ygo-news-center-20261028/`:

- Parseo de documentos reales de las cuatro fuentes: veinte, ocho, ocho y veinte publicaciones.
- Validación de dominios, destinos, fechas, metadatos y separación de Master Duel/RUSH DUEL.
- Interfaz probada con respuestas simuladas basadas en documentos reales: fuentes, filtros, paginación, lector, guardados, lectura y móvil.
- Importación real en producción: **56 publicaciones**, las cuatro fuentes con consulta correcta.
- Prueba de persistencia real de lectura/guardado, restaurando al terminar los estados originales del artículo usado.
- Modales móviles con desplazamiento, ausencia de desbordamiento horizontal de página y ausencia de errores de JavaScript.
- Publicación acotada con comparación de bytes y conservación de las 44 filas de cartas, perfiles y asignaciones deportivas existentes.
