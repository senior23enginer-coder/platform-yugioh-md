# Consultar Station X desde ChatGPT

Conector MCP de solo lectura, separado del código de la ficha y de los importadores editoriales. ChatGPT consulta la plataforma directamente; el servidor PHP no invoca modelos ni necesita una API key de OpenAI. Las consultas siguen los límites y permisos de la cuenta de ChatGPT. Desarrollar este conector en Codex sí utiliza Codex.

## Activación por el propietario

1. En **ChatGPT web → Plugins → + → Agregar servidor MCP personalizado**, introducir el nombre `Station X Yu-Gi-Oh` y la URL `https://stationxtulua.com/yugioh/mcp/`.
2. Seleccionar OAuth y descubrimiento de cliente CIMD o registro dinámico DCR. No hay un client secret estático.
3. Completar la autorización en `stationxtulua.com` con el código privado entregado al propietario. No es la contraseña de ChatGPT ni debe pegarse en el chat.
4. Instalar el plugin creado y seleccionarlo con `@` en una conversación.

La disponibilidad depende de la cuenta y los permisos del espacio. Si no aparece el menú, la publicación del servidor no habilita por sí sola esa función en ChatGPT. La cuenta no queda conectada hasta completar su autorización. La interfaz de ChatGPT puede cambiar: [documentación oficial de conexión](https://developers.openai.com/api/docs/guides/custom-mcp-server).

Ejemplo: «Usa Station X para consultar las cartas actuales de Kashtira Pure y explicar una ruta documentada. Comprueba requisitos y no añadas cartas que no estén cargadas».

## Datos y límites

- `list_decks` / `get_deck`: catálogo nativo, cartas reales por sección, cantidades, perfil e inventario SHA-256. Un deck sin Main aparece como no cargado y no habilita `get_guide`.
- `get_guide`: secciones de la entrega publicada señalada en `assets/editorial/deck-deliveries.json`, con versión, fuentes, estado documental y paginación de texto sin descartar fragmentos. También permite leer `documents`, `card_profiles`, `card_checks`, `route_checks`, `source_index`, `verification`, `coverage`, `official_card_texts` e `historical_records` de la biblioteca detallada, solo cuando su ID y hash de fuente coinciden con la entrega activa. Continuar con `next_offset` hasta `null`. Hay que contrastar la lista documental con el inventario vivo antes de recomendar rutas.
- `list_combos` / `get_combo`: diagramas del usuario, revisión, instantánea de cartas y aviso si cambió el inventario.
- `search` / `fetch`: búsqueda de nombres de decks y títulos de combos, y lectura por IDs estables. No se promete búsqueda de texto completo.
- `list_leagues`: ligas activas, divisiones activas e inventario de decks inscritos, con filtro de temporada y paginación.
- `list_matches`: partidas registradas de liga con participantes directos A/B/C, fechas y horas reales (null si faltan), filtros por liga/división/deck/temporada y totales de programación.
- `get_deck_schedule`: identidad, división actual y calendario de liga de un deck. No infiere partidos no generados. Los equipos/FFA y sus participantes indirectos no están cubiertos por esta lectura.
- No modifica cartas, guías, diagramas, calendario, partidas, resultados ni historial. No ejecuta simulación de reglas ni certifica legalidad. El acceso autorizado es al conjunto del catálogo y combos del único operador; no es un modelo multiusuario.

## Implementación y publicación

PHP 8.3: `includes/chatgpt/{auth,data,protocol,http}.php`; entrada `mcp/index.php` y reglas Apache propias. No inicializa `index.php` ni ejecuta migraciones. La conexión PDO dedicada abre una transacción MySQL `READ ONLY` y revierte al terminar. SQLite de pruebas utiliza `query_only`.

OAuth: código de autorización de un solo uso (90 s), PKCE S256, consentimiento ligado a sesión, scope `stationx:read`, tokens opacos hasheados y ligados al recurso/cliente, acceso 1 h, refresh 30 días con rotación. Revocar un token invalida su familia. No se afirma detección/revocación automática de toda la familia por reuso de un refresh antiguo. Los intentos y registros están limitados; el almacenamiento privado usa bloqueo y reemplazo atómico. CIMD solo consulta documentos de cliente HTTPS en rutas permitidas de `chatgpt.com`, sin seguir redirecciones.

Preparar código privado y configuración (salida ignorada por Git):

```powershell
python torneos_yugioh/tools/prepare_chatgpt.py --php C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe
```

Publicar `outputs/chatgpt-connection-20261103/yugioh-chatgpt-config.php` en el directorio del dominio **fuera de `public_html`**, como `yugioh-chatgpt-config.php`. PHP genera allí `yugioh-chatgpt-state/`; no publicar esa carpeta ni incluirla en Git. Alternativamente `STATION_X_CHATGPT_CONFIG` identifica un archivo privado. El código en texto plano queda únicamente en `CONEXION_CHATGPT_PRIVADA.txt`, local e ignorado. No subirlo al servidor.

Publicar los cuatro includes y dos archivos `mcp/` en `/yugioh/`. Publicar `deploy/chatgpt-discovery/index.php` únicamente como `/.well-known/oauth-authorization-server/yugioh/mcp/oauth/index.php` en el web root verificado. Este último solo devuelve metadatos; no modifica la tienda ni su `.htaccess`.

Diagnóstico público: `/yugioh/mcp/status`, `/yugioh/mcp/connect`, `/yugioh/mcp/.well-known/oauth-protected-resource`. Los datos requieren Bearer; initialize/listado de herramientas exponen solo capacidades. HTTP Streamable es stateless, sin SSE persistente. No enviar credenciales en query strings.

Para desconectar: retirar el plugin en ChatGPT. Para invalidar todos los accesos del servidor, detener temporalmente el endpoint, retirar su estado OAuth privado y generar un nuevo código/hash si se requiere rotarlo; esto elimina autorizaciones, no datos de decks. La configuración y los archivos de autorización necesitan respaldo privado independiente del repositorio.

## Verificación

`npm run test:mcp` levanta PHP y una base SQLite aislada, usa el SDK oficial de MCP y comprueba negociación, herramientas, aislamiento por deck, paginación, restricciones de lectura, rechazo de orígenes, sesión/PKCE/audiencia, código reutilizado, rotación/revocación y ausencia de secretos en texto plano. No ejecutarlo contra producción. Publicar y comprobar HTTP son pasos distintos de conectar la cuenta real de ChatGPT.
