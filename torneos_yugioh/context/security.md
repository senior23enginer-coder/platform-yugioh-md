# Integridad y operacion segura

## Alcance

La instalacion tiene un unico operador. No se requiere un sistema de jugadores, jueces, usuarios ni permisos por roles. La seguridad de esta especificacion se concentra en proteger datos, resultados y vistas publicas.

## Controles obligatorios

- Validar en servidor toda accion POST y todo identificador recibido.
- Escapar texto y atributos al renderizar contenido guardado.
- Usar consultas preparadas para acceso a MySQL.
- Validar extension, MIME, tamano y destino de logos o multimedia.
- Mantener credenciales y configuracion sensible fuera del repositorio y de los informes.
- Exigir confirmacion para eliminar eventos, amistosos y contenido.
- Registrar en auditoria cambios de resultados y acciones destructivas.
- Aplicar revision optimista al duelo en vivo para evitar sobrescrituras concurrentes.
- Hacer idempotentes los reintentos offline mediante un identificador de solicitud.
- Mantener `public_live` y `public_season` estrictamente en modo lectura.
- No exponer errores SQL, trazas, rutas internas ni payloads sensibles al navegador.

## Integridad competitiva

- Una pantalla A o B solo modifica su propio LP y solo finaliza su turno cuando corresponde.
- El juez conserva el control del resultado, dados, inicio, reloj y cierre.
- Los turnos guardados son append-only y no se eliminan ni reducen.
- Un resultado finalizado solo cambia mediante una correccion auditada.
- Las reglas aplicadas se versionan para que una edicion futura no altere eventos anteriores.
- Los historicos inferidos nunca se presentan como capturas exactas.

## Recuperacion

Ante conflicto o perdida de red, el cliente debe conservar la operacion, recuperar la revision actual y permitir una resolucion visible. Nunca debe declarar exito antes de recibir confirmacion del servidor.
