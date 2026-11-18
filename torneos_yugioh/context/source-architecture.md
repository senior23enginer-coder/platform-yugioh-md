# Organizacion y limites del codigo fuente

## Donde editar

| Responsabilidad | Ubicacion |
| --- | --- |
| Entrada web y composicion de la solicitud | `index.php` |
| Politicas puras de emparejamiento y entidad temporada | `src/Domain/` |
| Caso de uso de temporada y puerto de lectura | `src/Application/Seasons/` |
| Adaptador de temporadas y persistencia heredada | `src/Infrastructure/` |
| Servicios existentes de eventos, calendario, equipos, partidas y configuracion | `includes/<feature>/` |
| Carga y datos iniciales de la solicitud | `includes/bootstrap/` |
| Respuestas HTTP y exportaciones | `includes/http/` |
| Proyecciones para presentacion | `includes/presentation/read-model.php` |
| Cabecera, navegacion, dialogos y pie | `includes/presentation/layout/`, `includes/presentation/dialogs/` |
| Pantallas PHP | `includes/presentation/screens/` |
| Fuentes editables del frontend existente | `assets/source/javascript/`, `assets/source/styles/` |
| Funciones frontend independientes y motores puros nuevos | `assets/modules/<feature>/` |
| Bundles publicos generados | `assets/app.js`, `assets/app.css` |

## Dependencias y arquitectura hexagonal

El dominio no conoce PDO, HTTP, sesiones ni archivos de configuracion. `GetCurrentSeason` depende de `SeasonRepository`, no de una consulta SQL. La composicion inyecta `LoadedSeasonRepository`, que adapta datos ya leidos sin repetir consultas. Las pruebas utilizan otra implementacion del mismo puerto.

Los algoritmos de emparejamiento viven en clases de dominio independientes. Las funciones publicas originales permanecen como delegados para no romper llamadas existentes. No se agregan interfaces a algoritmos puros solo para aumentar el numero de clases.

Los modulos procedurales heredados son adaptadores de compatibilidad; **no son una conversion completa a arquitectura hexagonal**. Conservan su API y parte de la mezcla historica entre reglas y PDO. No introducir dependencias de esos adaptadores dentro del dominio. Migrar cada nueva regla a un caso de uso y un puerto cuando requiera persistencia o servicios externos.

## Frontend y compilacion

`npm run build` reconstruye los dos bundles publicos a partir de `assets/source/manifest.json`. `npm run build:check` comprueba que no exista deriva. No editar directamente los bundles: los cambios se perderian al compilar.

Los fragmentos JavaScript del adaptador existente conservan un cierre compartido y un orden explicito. No son ES modules independientes ni deben agregarse individualmente al HTML. Esto preserva el estado y la inicializacion mientras cada controlador se migra a un modulo con dependencias explicitas. Los modulos nuevos de `assets/modules/` siguen siendo archivos independientes.

Los estilos conservan reglas completas y el orden original de cascada. Sus nombres indican el area dominante; algunos segmentos contienen overrides de compatibilidad. Consolidarlos requiere pruebas visuales de escritorio y movil.

## Transacciones, rutas y contratos

- Los bloqueos, transacciones y limites de concurrencia permanecen en sus funciones originales.
- `src/legacy-functions.php` carga declaraciones; no inicia conexiones ni ejecuta comandos.
- `src/autoload.php` registra clases y rutas estables de aplicacion; no inicializa HTTP ni persistencia.
- Las vistas usan `YGO_APP_ROOT` cuando necesitan incluir otra vista, conservando el significado de las rutas antiguas.
- `context/source-function-map.json` permite localizar las funciones trasladadas y comprobar sus cuerpos normalizados. Si una funcion cambia de comportamiento, revisar sus pruebas y actualizar deliberadamente ese contrato; no borrar la comprobacion para ocultar una diferencia.
- Los comentarios explican responsabilidades y restricciones; los contratos de parametros y retornos permanecen tipados.

## Validacion y limites

La reorganizacion usa extracciones incrementales. Para cada modulo y vista aceptados se comprobo sintaxis y equivalencia del HTML completo antes y despues en una prueba aislada sin acceso a base de datos. Los algoritmos de dominio se comparan con casos de caracterizacion obtenidos antes de extraerlos.

`npm test` ejecuta el control de compilacion, los limites del dominio, los contratos de funciones y la suite funcional existente. Las pruebas con base de datos real y los escenarios de produccion no equivalen a esas pruebas locales. La reorganizacion no modifica inventarios, resultados, temporadas ni formulas de ranking.

La deuda que sigue existiendo es visible: algunos controladores heredados todavia comparten variables, el despachador POST mantiene acciones grandes y ciertas consultas necesitan repositorios especificos. No describir este estado como 100% SOLID o hexagonal. La estructura actual permite migrarlos sin volver a mezclar el nucleo con los adaptadores.

## Recorrido de una solicitud

`index.php` mantiene primero el rechazo del API de Reportes retirado, antes de cargar dependencias. Luego registra clases y funciones de compatibilidad, carga dependencias y defaults, procesa la solicitud y lee datos (`bootstrap/load-state.php`), construye las proyecciones, despacha respuestas HTTP y finalmente renderiza `presentation/page.php`. Los includes mantienen el ambito original: no envolverlos en funciones ni reordenarlos sin caracterizar los datos compartidos.

El mapa cubre 132 funciones publicas extraidas y hay 21 pantallas separadas. Las 37 fuentes JavaScript comparten el cierre anterior; los 37 segmentos CSS conservan la cascada. La separacion no implica que cada adaptador procedimental sea un repositorio o caso de uso independiente.
