# Almacenamiento y mantenimiento del navegador

Desde v113, el mantenimiento comprueba una vez por d?a de Colombia los datos temporales al abrir la aplicaci?n, recuperar el foco o volver a una pesta?a visible. Mientras permanece visible vuelve a comprobar cada 15 minutos. Un navegador cerrado no garantiza ejecuci?n: se realiza al siguiente acceso. El service worker recibe la solicitud y registra su propia fecha de ejecuci?n; no usa localStorage.

## localStorage

Solo elimina recibos de notificaciones `dml-notified-ID-FECHA-HORA` con fecha v?lida y m?s de 30 d?as. Conserva preferencias, filtros, sonidos, IDs de captura, resultados antiguos, estado de LP, relojes, herramientas, claves desconocidas y la cola offline ?ntegra, independientemente de su antig?edad. El marcador `stationx_storage_cleanup_v1` guarda el d?a, instante y cantidad de recibos retirados. Si el almacenamiento est? bloqueado no interrumpe la aplicaci?n.

## Cache Storage

- `yugioh-md-match-v113`: archivos de instalaci?n y p?ginas necesarias para abrir sin conexi?n. No se eliminan por antig?edad. Sus URLs exactas se actualizan al consultar online; una instalaci?n nueva completa prepara su cach? antes de retirar versiones anteriores.
- `yugioh-md-runtime-v1`: archivos adicionales solicitados bajo assets/ del m?dulo; expiran a los siete d?as seg?n Date HTTP y se limita a 150 entradas, retirando primero las m?s antiguas. Las entradas sin fecha v?lida tambi?n se retiran.
- `yugioh-md-maintenance-v1`: un registro de control diario y contadores de limpieza.

Solo se eliminan versiones anteriores cuyo nombre coincide exactamente con `yugioh-md-match-vN`. No se eliminan cach?s de otras aplicaciones. Las peticiones POST, todas las rutas `api` y las descargas `export` quedan fuera del cach?. No se guardan copias adicionales de cada p?gina con filtros; sin conexi?n, las navegaciones usan el respaldo instalado. Se conserva la estrategia de consultar internet primero.

No borra datos de MySQL ni la cach? YGOPRODeck del servidor. Este proceso pertenece a cada navegador/dispositivo.

## Verificaci?n

Prueba aislada con Chromium, service worker y Cache Storage reales: expiraci?n, l?mite, retiro de versi?n anterior, ejecuci?n diaria, reanudaci?n al d?a siguiente, ausencia de cach? para API y exportaciones, preferencias/LP/relojes/cola intactos, almacenamiento ajeno conservado y apertura/archivos recientes funcionando offline. Evidencia: outputs/ygo-storage-cleanup-20261028/.
