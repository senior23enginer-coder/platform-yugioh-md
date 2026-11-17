# Migración al repositorio de Yu-Gi-Oh

El 2 de noviembre de 2026 se preparó este repositorio independiente a partir de la aplicación existente de Station X. Incluye código PHP/JavaScript/CSS, documentación, pruebas, herramientas, fuentes editoriales y recursos públicos. Se recuperaron 140 imágenes públicas ausentes de la copia local, verificando su tamaño y hash. La carpeta original se conservó.

La compatibilidad con la tienda se limita a las tres funciones compartidas utilizadas por la aplicación, con configuración privada externa. No se incluyen la tienda completa, credenciales, copias de bases de datos, sesiones, cachés ni dependencias instaladas. El inventario editorial activo y los manifiestos históricos necesarios se conservan con sus rutas relativas.

Verificación realizada en la copia nueva:

- Sintaxis: 62 archivos PHP y 72 archivos JavaScript/CJS, sin errores.
- Suite local: 13 pruebas PHP, tres regresiones/paridades JavaScript, 11 casos de sincronización editorial Python y la prueba de reemplazo/auditoría de decks con SQLite en memoria; todas aprobadas.
- Puente compartido: sesión/CSRF, escape HTML y rechazo de inicio sin configuración de base de datos comprobados.
- Escaneo de patrones de credenciales y revisión de nombres privados: sin hallazgos en los archivos preparados para Git.
- Ningún archivo alcanza el límite de 100 MiB de GitHub. Las fuentes editoriales con hashes conservan sus bytes en el índice de Git.

Los únicos ajustes a archivos de aplicación existentes fueron documentar la nueva ubicación del repositorio y permitir que la prueba de estadísticas cree su carpeta de resultados cuando aún no existe. Se conservaron los demás archivos de código y contenido. Los avisos de espacios en blanco heredados de `index.php` y `context/api.md` no se reescribieron durante esta migración.

Esta verificación no ejecutó las pruebas de navegador sobre producción ni las pruebas que escriben en MySQL. No se modificaron resultados, listas guardadas ni configuración del sitio durante la migración. El push del repositorio no constituye un despliegue a producción.
