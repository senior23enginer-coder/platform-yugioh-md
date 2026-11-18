# Estado de migración de arquitectura

Fecha: 18 de noviembre de 2026. Repositorio de aplicación, rama `base`.

## Migraciones verificadas

| Área | Separación implementada | Evidencia |
| --- | --- | --- |
| Mundos | Dominio, comandos/consultas, puertos, HTTP/PDO y transacciones | Pruebas de aplicación, SQLite y navegador |
| Metadatos de decks | Resolución de estilo/arquetipo, proyección y puerto editorial | 31 comprobaciones y ocho caracterizaciones originales |
| Temporada actual | Caso de uso y repositorio de lectura inyectado | Dobles de repositorio y pruebas de arquitectura |
| Reglas deportivas y de edición | 43 algoritmos originales caracterizados y planificador de calendario | Firmas/referencias, evidencia original y comparador canónico |
| Guardado y consultas de combos | Grafo puro, puertos, reloj, transacción y repositorios | Pruebas de puertos, rollback, suite y navegador local |
| Comandos POST | 56 rutas en 55 adaptadores específicos detrás de un puerto | Comparación con las 55 ramas originales, guardas y contexto anidado |
| Calendario compartido y clasificatorios | Cuatro casos de uso, reglas de fecha/cupo y puertos de lectura/escritura/tiempo | 49 comprobaciones y 13 escenarios originales; MySQL específico pendiente |
| Noticias | Normalización, consulta, sincronización, lectura y miniaturas por puertos; adaptadores HTTP, PDO, cURL y parsers | 99 comprobaciones, 33 escenarios originales y 12 algoritmos comparados; sin producción |
| Catálogo e inspector de cartas (backend) | Búsqueda, identidad, lectura local, proveedor, caché e imágenes por puertos | 71 comprobaciones, diez escenarios y tres algoritmos originales; sin proveedor real |
| Formato | 253 fuentes seleccionadas con formato estable y control en la suite | `npm run format:architecture:check` |

## Trabajo todavía pendiente para completar toda la plataforma

Estos elementos siguen siendo deuda real; no están certificados como migración completa:

1. `src/Infrastructure/Persistence/legacy-read-model.php` y `includes/bootstrap/load-state.php`: cargador amplio con numerosas proyecciones, inicializaciones y variables compartidas. Separar consultas por módulo y composición de arranque.
2. Calendario, configuración/generación de eventos y temporadas: el reflujo automático, los cupos explícitos y la prioridad de clasificatorios ya están separados (`calendar-scheduling.md`). Faltan generación integral de fixtures, migraciones históricas, activación, cambios manuales y separación del escritor de proyecciones, además de los flujos de temporadas. Preservar bloqueos MySQL, fechas, transacciones y auditoría.
3. Manejadores POST: ahora tienen límites por acción, pero varios aún realizan validación y coordinación dentro del adaptador. Migrar cada flujo restante a puertos y casos de uso; no mover el mismo cuerpo a Application con un PDO inyectado.
4. Importación/exportación de decks y fuentes del editor: separar validación del paquete, sistema de archivos, BD y coordinación de reversión, preservando identidades, historial y sincronización con el repositorio independiente de prompts.
5. Estadísticas: varias fórmulas están en dominio; acumulación/publicación y consultas todavía requieren separación de tiempo, fuentes y persistencia.
6. Noticias y catálogo del inspector: backend separado. Quedan por certificar los bloqueos, intervalos y concurrencia de Noticias en MySQL de pruebas y la integración actual de ambos proveedores. La edición/persistencia de listas del deck sigue siendo un flujo nativo distinto; no se considera migrada por reorganizar el catálogo.
7. Frontend legado de `assets/source/javascript`: conserva 37 fragmentos en un cierre compartido. Extraer estado, transporte y controladores por funcionalidad con pruebas; dividir archivos no equivale a módulos independientes.
8. Plantillas mixtas PHP/HTML y puente `store-lib.php`: mantener composición y presentación fuera del dominio, y sustituir gradualmente las dependencias nativas. No aplicar masivamente un formateador experimental a plantillas.

## Condición de cierre

Cada flujo debe tener responsabilidades y dependencias explícitas, reglas independientes de I/O, adaptadores sustituibles, contratos preservados y pruebas apropiadas. Se deben verificar las transacciones y concurrencia en un MySQL de pruebas antes de certificar esa integración. Una suite local correcta no equivale a un despliegue ni a certificación de producción. No existe un porcentaje global de cumplimiento inferido del número de archivos movidos.
