# Swagger y OpenAPI del módulo Deck

Abrir `/yugioh/api-docs/`. El contrato descargable está en `api-docs/openapi.json`.
La página, contrato y recursos Swagger UI se sirven sin iniciar PHP, sesión ni BD.
Los recursos de Swagger UI se copian desde la versión fijada en package-lock.json;
`npm run build:swagger` actualiza la copia distribuida, incluida su licencia.
No se usa CDN ni validador remoto.

La primera integración expone 13 operaciones existentes mediante 10 rutas en
`api/index.php/...`. `api/routes.php` declara métodos y parámetros admitidos.
El adaptador no tiene SQL ni reglas nuevas: delega en los controladores nativos,
conservando cuerpo, sesión, permisos, bloqueos, versiones e historial.
El front existente sigue usando `?api=...` y no cambia en esta entrega.
Las rutas desconocidas y métodos no permitidos responden 404/405 antes del bootstrap.
Apache conserva PATH_INFO mediante una regla específica en `.htaccess`.

Consultar primero la lista, combos, Mundos o estado ZIP para recibir su token.
Swagger mantiene las cookies y reutiliza los tokens solo en memoria, únicamente
para las rutas API del mismo origen. Decklist lleva token dentro del JSON;
combos, Mundos y ZIP usan X-CSRF-Token. No hay token global de ejemplo ni secretos.
Cada ejecución requiere pulsar Try it out / Execute: no se guardan datos al abrir.

Las propuestas de 37 endpoints REST no se consideran implementadas.
El CRUD general de decks, multimedia y otras pantallas de ficha sigue siendo
el flujo nativo de formularios. Esta entrega no añade permisos ni borra inventarios.

Validación: `npm run test:swagger` verifica OpenAPI, rutas/métodos, recursos
vendorizados y Swagger en escritorio/móvil mediante un PHP local sin BD.
Nunca ejecuta guardados ni se conecta a producción.
