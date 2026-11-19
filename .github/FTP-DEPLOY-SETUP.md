# Despliegue autom?tico al integrar en base

Un pull request integrado en `base` publica su commit por FTP. No se repiten
pruebas de importaci?n, calculadora ni la suite general despu?s del merge.
Las pruebas de integraci?n se mantienen antes del merge cuando cambian fuentes;
la prueba TXT individual queda disponible manualmente, porque la integraci?n ya la ejecuta.

## Flujo de publicaci?n

1. Leer el commit integrado y generar `app.js` / `app.css` con Node 22.
   El generador usa ?nicamente m?dulos nativos: no necesita `npm ci`.
2. Sincronizar `torneos_yugioh/` por FTP, puerto 21, con el estado incremental
   existente. Nunca usar `dangerous-clean-slate`.
3. Publicar `release.json` al terminar, comprobar sus bytes por FTP y verificar
   que la URL p?blica devuelve el mismo commit y versi?n. Estas comprobaciones
   detectan una ruta remota equivocada; no son pruebas funcionales de la aplicaci?n.

No instalar PHP/Python/Playwright ni ejecutar `npm test` dentro del despliegue.
No crear GitHub Releases: su registro no es necesario para publicar la web.
El manifiesto y el pie de la aplicaci?n conservan la versi?n `v0.1.<run number>`.
Las publicaciones no se ejecutan en paralelo ni se interrumpen entre s?.

## Configuraci?n

Usar los secretos de Actions `FTP_HOST` (sin protocolo), `FTP_USERNAME`,
`FTP_PASSWORD`. La ruta p?blica, que no es secreta, se fija en la variable
`FTP_SERVER_DIR` del workflow; no se usa el secreto antiguo con la ruta incorrecta. No escribir credenciales en el repositorio.
El entorno `production` no debe tener revisores obligatorios si se desea
publicaci?n inmediata. El protocolo actual del hosting es FTP, no SFTP.

**Ruta FTP comprobada el 19 de noviembre de 2026:**
`/domains/stationxtulua.com/public_html/yugioh/`.
La sesi?n inicia en `/public_html`: la ruta relativa `public_html/yugioh/`
escrib?a en `/public_html/public_html/yugioh/`, fuera de la web p?blica.
La variable `FTP_SERVER_DIR` usa esa ruta absoluta, incluido el `/` final.
No eliminar la copia equivocada como parte de un despliegue.

Se excluyen uploads, cach?s, configuraci?n privada, tests, documentaci?n,
herramientas, fuentes del bundle y artefactos locales. Se publican `src/`,
`includes/`, m?dulos independientes, assets editoriales, `.htaccess`, manifest,
service worker y entradas p?blicas. El bridge compartido `store-lib.php`
permanece en el servidor, fuera de `/yugioh/`; no se reemplaza.
No ejecutar seed ni importaciones de datos durante el pipeline.

## Reintentar

Desde Actions se puede ejecutar manualmente este workflow sobre `base`.
Esto vuelve a publicar ese commit sin crear otro merge. Los pushes normales
no disparan despliegue; los merges de PR s?. No es necesario abrir un PR
para cada reintento por una ca?da del hosting.

Una subida parcial no se revierte autom?ticamente: el job falla y no publica
un manifiesto nuevo si la transferencia falla. La comprobaci?n del manifiesto
no certifica todos los flujos de negocio ni sustituye pruebas previas al merge.

## Integraci?n con el checkout local

Al reunir el pipeline remoto con las migraciones locales se conserv? la acci?n
`import_deck_template` antes de las acciones tempranas y del guard de temporada,
tal como estaba en el remoto. El contrato actual del dispatcher registra esa
adici?n; la huella y el cuerpo originales anteriores a la extracci?n se conservan.
La suite local verifica ambas evidencias y los contratos del importador.

