# Carga inicial y caché del navegador

## Problema observado (25/11/2026)

Una navegación de solo lectura con Chromium nuevo produjo HTML de 6.467.356 bytes
sin comprimir (1.084.531 transferidos), DOMContentLoaded alrededor de 7 s y evento
load alrededor de 79 s. Numerosos logos PNG de 2–3 MB en tablas ocultas siguieron
descargándose durante unos 70 s. Es una muestra desde un equipo/red concretos,
no un SLA ni una medición general de todos los usuarios.

## Cambios

- Imágenes de tablas/pantallas y logos generados por el frontend heredado usan
  `loading="lazy"` y `decoding="async"`; los dos logos de navegación siguen eager.
- El manifiesto de 24 scripts del calculador queda en un `template` inerte.
  `assets/modules/startup/resources.js` lo activa al abrir su ruta, incluyendo
  enlaces directos. Precarga en paralelo, ejecuta en orden, comparte las cargas
  concurrentes y detiene las dependencias posteriores ante un fallo; permite
  reintentar sin inicializar de nuevo las partes ya ejecutadas.
- Decklist, guías y combos del perfil se montan al abrir su categoría. No se
  monta además un tablero oculto: el calculador tiene su propia pantalla.
- Se retira la descarga de FullCalendar: su renderizador nativo retorna antes
  de utilizarlo; el calendario existente usa su representación alternativa.
- El service worker se registra después de `load`. Conserva la preparación
  offline en segundo plano; por eso puede descargar herramientas después de
  la carga inicial para tenerlas disponibles sin conexión.
- Recursos estáticos con versión y logos subidos se reutilizan desde caché.
  Navegaciones y JSON editorial sin versión siguen consultando la red; API,
  exportaciones y POST no se interceptan. Se mantiene caducidad de siete días
  para recursos de ejecución y el límite de 150 entradas.

## Validación

`node torneos_yugioh/tests/startup_resources_test.cjs` cubre escritorio/móvil,
logos ocultos, entrada directa, orden, fallo/reintento, concurrencia, caché,
JSON fresco y fallback offline. También forma parte de `npm test`. Su alta en
el inventario P06 es una regresión general de infraestructura, sin nuevos
programas ni análisis por carta/deck.

No se cambiaron migraciones, transacciones, inventarios ni fórmulas estadísticas.
El HTML global todavía incluye múltiples pantallas y datos; dividir esa respuesta
requiere una migración adicional. No se atribuye toda la espera a SQL ni se promete
una reducción porcentual sin medir la versión desplegada en condiciones comparables.
