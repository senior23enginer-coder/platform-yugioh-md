# Station X · Yu-Gi-Oh

Este repositorio contiene únicamente la plataforma Yu-Gi-Oh y sus recursos de ejecución. **No contiene una carpeta `gpt`.** Los prompts y entregas pertenecen al repositorio independiente [gpt-yugioh-promps-platform](https://github.com/senior23enginer-coder/gpt-yugioh-promps-platform). No se incluyen credenciales, dependencias instaladas ni archivos privados de trabajo. Los recursos editoriales ya publicados permanecen en `assets/editorial/`, porque la plataforma los utiliza para sus guías y exportaciones.

Las herramientas activas de importación y sincronización leen por defecto el clon hermano `../gpt-yugioh-promps-platform/`. Para ubicarlo en otro lugar, definir `YGO_PROMPTS_ROOT` con la raíz de ese repositorio. La importación ZIP desde la web sigue funcionando íntegramente en el servidor, sin necesitar ninguno de los clones locales.

Repositorio oficial de la plataforma: https://github.com/senior23enginer-coder/platform-yugioh-md. Todo cambio de código, pruebas, documentación y contenido editorial de Yu-Gi-Oh se mantiene aquí.

## Contrato único para entregas generadas por IA

El idioma de explicación, guías y diagnósticos es **español**; los nombres oficiales de cartas y las claves de esquemas JSON no se traducen. El módulo «Importar / exportar Data» utiliza **una carpeta por deck**, conservando el nombre original `DECK_*` que entrega la exportación, incluso cuando tenga la forma histórica `DECK_NUEVO_12_*`.

- **Crear deck nuevo:** usar primero la plantilla TXT nativa `# STATIONX-DECK-TEMPLATE/1` para dar de alta la identidad y cartas; luego abrir el deck y **exportar Data**. El paquete exportado provee un `deck_id` y una huella `inventory_sha256` reales. Una IA no debe inventarlos.
- **Actualizar deck existente:** exportar Data desde el deck que se desea actualizar; entregar el ZIP a la IA, que debe devolver el mismo árbol con `datos/stationx-deck.json` y los archivos A01–A10 pertinentes. No cambiar la carpeta de origen, los IDs de cartas, CIDs, slots, inventario, Skills ni los IDs de las rutas.
- **Fuente interactiva:** `station-x-deck-package/1` contiene `record.schema=station-x-curated-v62` con `profile`, `cards`, `skills`, `summary`, `engine_maps`, `routes`, `decisions` y `practice`. Los documentos Markdown y HTML son complementarios. Para editar el JSON, usar `content_mode=structured`; el modo `markdown` solo transforma una parte del material.
- **Calculadora:** `record.calculator` es opcional y solo puede declarar `station-x-calculator/1` con reglas verificadas para ese deck y formato. La ausencia de reglas no impide documentar o importar, pero prohíbe afirmar que todos sus combos son ejecutables.
- **Seguridad:** no sobrescribir inventarios ni revisiones manuales; el ZIP debe conservar `package_root`, `inventory`, `inventory_sha256`, `source_document` y `baseline` sin falsear hashes. Previsualizar y validar antes de confirmar la importación. La importación de contenidos y el despliegue de código son operaciones separadas.

Las pruebas de regresión de `torneos_yugioh/tests/deck_data_test.php` incluyen los pilotos Memento (ID 671; 41/15/14/0; 12 rutas) y Crystron/Horus (ID 677; 40/15/15/0; 14 rutas). Estas pruebas validan estructura y paridad de datos, **no** jugadas completas del motor.

## Estructura

- `torneos_yugioh/`: aplicación PHP, frontend, recursos, pruebas y herramientas editoriales.
- `torneos_yugioh/context/README.md`: índice de documentación funcional y técnica.
- `torneos_yugioh/context/editorial-sync-state.json`: referencia del contenido editorial activo y sus fuentes.
- `../gpt-yugioh-promps-platform/`: repositorio independiente de prompts, entregas e investigación; no forma parte de este árbol.
- `outputs/ygo-editorial-sync-20261030/`: contexto de sincronización y base histórica para los demás decks.
- `outputs/ygo-import-11-decks-20261101/`: inventario y evidencias de la integración de los 11 decks.
- `station_x_perfiles_67_decks.txt`: fuente histórica; no sustituye las referencias activas del estado editorial.
- `store-lib.php`: compatibilidad independiente para las tres funciones compartidas utilizadas por la aplicación: sesión/CSRF, escape HTML y conexión PDO. No incorpora el código de la tienda.

## Configuración local

Requiere PHP 8.3 con PDO MySQL, mbstring y cURL, y una base MySQL separada para desarrollo. Para las pruebas locales también se necesita PDO SQLite, Python 3 y Node.js. Las pruebas de navegador usan Playwright.

1. Copia `config/database.example.php` a `config/database.local.php` y configura tu base de desarrollo. El archivo local está excluido de Git.
2. Alternativamente, define `YGO_DB_HOST`, `YGO_DB_PORT`, `YGO_DB_NAME`, `YGO_DB_USER`, `YGO_DB_PASSWORD`, `YGO_DB_PREFIX` y `YGO_DB_ENTITY`. Nombre de base y usuario son obligatorios.
3. Desde la raíz del repositorio ejecuta:

   ```sh
   php -S 127.0.0.1:8080 -t torneos_yugioh
   ```

La aplicación inicializa/evoluciona sus tablas al conectarse: usa una base aislada. Los datos reales de operación no se incluyen en Git. En un hosting Station X existente, el puente admite su configuración privada de Dolibarr mediante `STATION_X_DOLIBARR_CONFIG` o `htdocs/conf/conf.php`, sin versionarla. La configuración local tiene prioridad, seguida de Dolibarr y después las variables de entorno.

En producción la ruta pública es `/yugioh/`; la carpeta del repositorio conserva el nombre `torneos_yugioh`. Si se publica en otra ruta, revisar `.htaccess`, `RewriteBase` y la configuración documentada de despliegue.

## Verificación

```sh
npm ci
npm test
npx playwright install chromium
npm run test:guide
npm run test:calculator
npm run test:export
```

`npm test` ejecuta las pruebas unitarias PHP, las paridades JS/PHP y la sincronización editorial en archivos temporales; no conecta con producción. Si PHP o Python no están en PATH, definir `PHP_BINARY` y `PYTHON_BINARY` con sus ejecutables. Las comprobaciones históricas que necesitan entregas externas se ejecutan solo con `RUN_EDITORIAL_SOURCE_TESTS=1` y sus entradas presentes en el repositorio independiente.

Los escenarios de navegador y las pruebas de integración con MySQL tienen requisitos propios: consultar `torneos_yugioh/context/testing.md` y cada prueba antes de ejecutarla. No ejecutar pruebas de mutación sobre producción. La prueba de ciclo de temporadas requiere una base de prueba específica y no forma parte del comando anterior.

`npm run test:guide` verifica las fichas de los 11 decks y sus 126 rutas en un servidor temporal local: aislamiento por ID, descarga individual, cambio rápido entre decks, recuperación de errores y diseño móvil. Usa datos editoriales reales e imágenes sustituidas para probar el diseño sin acceder a servicios externos ni a la base de datos.

## Contenido editorial

La entrega completa de 11 decks reemplaza el contenido activo anterior de esos IDs; los otros decks conservan sus fuentes. Consultar el estado editorial antes de importar. Mantener Main, Extra, Side, reserva y Skills separados, y conservar condiciones y estados de verificación. Un ID de Konami no equivale a un ID de imagen de YGOPRODeck.

El contrato, inventarios y manifiestos guían las siguientes entregas de ChatGPT. Los originales que tienen hashes se conservan byte a byte mediante `.gitattributes`. La sincronización editorial prepara candidatos locales; no publica automáticamente ni importa un JSON arbitrario a MySQL.

## Publicación y seguridad

No versionar credenciales, configuraciones privadas, sesiones, copias de bases de datos, cachés ni `node_modules`. Este repositorio contiene recursos públicos y fuentes editoriales, no una copia de la base de datos de producción. Un commit/push actualiza GitHub; el despliegue al sitio se realiza por separado cuando esté autorizado.

La copia antigua en `xstation/torneos_yugioh` queda como referencia de migración. El trabajo futuro se realiza en este repositorio.

## Módulos de guía y calculador

Consultar [el mapa del código por módulos](torneos_yugioh/assets/modules/README.md). La guía mantiene la biblioteca completa por deck, y [Calculador de jugadas](torneos_yugioh/context/play-calculator.md) añade captura de mano/campo/Cementerio, rivales y consulta conservadora de rutas locales. Su entrada autónoma `play-calculator.html` permite reabrir los escenarios preparados con la red desactivada. El motor no simula todas las reglas de cartas ni certifica jugadas completas.

## Codigo fuente y arquitectura

La guia de edicion y limites de dependencia esta en [source-architecture.md](torneos_yugioh/context/source-architecture.md). El nucleo PHP esta en `torneos_yugioh/src/`; los adaptadores funcionales permanecen en `includes/<feature>/` y las pantallas en `includes/presentation/screens/`.

Las fuentes del frontend existente se editan en `torneos_yugioh/assets/source/`. Ejecuta `npm run build` antes de publicar y `npm run build:check` para detectar bundles desactualizados. `assets/app.js` y `assets/app.css` son salidas publicas generadas; los modulos independientes de `assets/modules/` se editan directamente.

El recorrido hexagonal de Mundos, sus puertos y las reglas de transacción están documentados en `torneos_yugioh/context/source-architecture.md`. Usa `npm run format:architecture` y `npm run format:architecture:check` para el formato de las fuentes migradas; no aplican a plantillas mixtas ni a bundles generados.
