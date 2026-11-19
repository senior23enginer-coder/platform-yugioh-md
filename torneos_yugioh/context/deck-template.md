# Alta de decks mediante plantilla TXT — `STATIONX-DECK-TEMPLATE/1`

El botón **Crear deck** abre una modal para descargar la plantilla, subir el
archivo editado y sincronizar (recargar) el listado del servidor. La carga crea
un deck nativo y sus cartas en una única transacción; no reemplaza decks
existentes ni modifica fuentes del repositorio de Prompts.

```txt
# STATIONX-DECK-TEMPLATE/1
[DECK]
nombre=Deck nuevo
arquetipo=
estilo=
notas=

[MAIN]
3;Carta del Main

[EXTRA]
1;Carta del Extra

[SIDE]
1;Carta del Side

[RESERVA]
# Sección opcional; reserva se conserva como tech en el esquema nativo.
```

- Cada carta usa `cantidad;nombre`, entre 1 y 3 copias por línea.
- Main, Extra, Side y Reserva se guardan en `main`, `extra`, `side`, `tech`.
- El nombre no puede coincidir con otro deck activo.
- No se ejecutan reglas de calculadora ni efectos desde texto.
- El archivo debe ser UTF-8, `.txt`, de hasta 128 KiB y 150 líneas de cartas.
- **Sincronizar listado** recarga la información del servidor; no reimporta ni
  sobrescribe un deck existente.

## Pruebas y límites

`tests/deck_template_test.php` verifica parseo, secciones y rechazo de datos
inválidos. El flujo HTTP real requiere usuario editor autenticado, subida
multipart y base de pruebas; no se valida contra producción. El primer PR de
esta funcionalidad debe pasar las pruebas antes de activar el despliegue.
