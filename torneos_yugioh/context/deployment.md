# Despliegue y verificacion

## Rutas

- Repositorio: `https://github.com/senior23enginer-coder/platform-yugioh-md`.
- Repositorio local: `C:\Users\senio\OneDrive\Documents\xstation\platform-yugioh-md`.
- Aplicacion dentro del repositorio: `torneos_yugioh/`.
- Produccion: `https://stationxtulua.com/yugioh/`.
- Raiz remota habitual: `domains/stationxtulua.com/public_html/yugioh/`.

La ruta remota debe verificarse desde la raiz FTP antes de publicar; no debe asumirse `public_html/` sin comprobar el arbol del proveedor.

El commit y push a GitHub no despliegan el sitio. La copia antigua de la aplicacion en `xstation/torneos_yugioh` queda como referencia; el trabajo futuro se guarda en el nuevo repositorio. Ver `README.md` en la raiz para la configuracion privada local y el puente de compatibilidad `store-lib.php`.

## Artefactos

Se publican PHP, `includes/`, `assets/`, manifiesto, service worker y recursos usados por la aplicacion. No existe una compilacion Angular. La conexion de base de datos proviene de la configuracion existente de Station X y no debe copiarse a la documentacion ni al repositorio.

## Procedimiento

1. Revisar el arbol Git y conservar cambios ajenos.
2. Ejecutar validaciones estaticas y pruebas relacionadas con el cambio.
3. Publicar solamente los archivos necesarios.
4. Incrementar las versiones de assets cuando el navegador o service worker pueda conservar cache anterior.
5. Verificar HTTP 200, consola, peticiones y persistencia real.
6. Ejecutar Playwright en escritorio y movil sobre el flujo afectado.
7. Retirar scripts temporales de diagnostico.

## Criterios de salida

- La pantalla modificada carga sin errores JavaScript o PHP.
- El cambio persiste tras recargar.
- Los estados visuales coinciden con la base de datos.
- Los endpoints publicos permanecen en modo consulta.
- No se exponen credenciales, rutas internas ni trazas.
- Los resultados reales no se alteran durante una validacion de solo lectura.

Una validacion estatica, un HTTP 200 y una prueba funcional certifican capas distintas. El reporte de despliegue debe indicar por separado archivos publicados, pruebas ejecutadas y validacion manual pendiente.
