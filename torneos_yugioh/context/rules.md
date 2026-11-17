# Reglas de competencia y duelo

## Autoridad

Las reglas se almacenan en `sx_ygo_game_rules` y `sx_ygo_game_rule_conditions`. El backend PHP evalua el resultado y el frontend replica la evaluacion solo para respuesta inmediata. Ante diferencia, manda el backend.

## Valores y estados

- Resultado de duelo: `A`, `B`, `E` o vacio.
- Estado de partida: `PENDIENTE`, `EN CURSO`, `REQUIERE D5`, `FINALIZADA`, `INVALID`.
- Mejor de N: normalmente BO3 o BO5, ampliado automaticamente si una condicion exacta usa mas duelos.

## Inicio

1. D1 lanza dados.
2. Dados iguales se repiten hasta tener ganador.
3. El ganador elige `primero` o `segundo`.
4. En D2-D5 el perdedor del duelo anterior elige.
5. Si el duelo anterior empato, ambos vuelven a lanzar dados solo para definir la eleccion del siguiente duelo.
6. No se puede comenzar sin `chosen_by`, decision y `starts_player` validos.

## Ejecucion

- Antes de iniciar se confirma reloj de 20 minutos o sin reloj.
- El modo de reloj no cambia durante el duelo.
- El turno 1 pertenece a `starts_player`; luego alterna A/B.
- `Terminar turno` agrega una entrada de auditoria y aumenta el contador.
- El servidor usa el mayor contador/historial valido en conflictos; nunca disminuye.
- LP se limitan a valores no negativos y mantienen historial de operacion.
- Un jugador solo puede rendirse durante su propio turno. La rendicion identifica ese lado, entrega la victoria al rival y queda pendiente de confirmacion del operador.
- La calculadora LP incluye una calculadora estandar para sumar, restar, multiplicar y dividir; el resultado se prepara antes de aplicarlo a los LP.

## Cierre

La regla puede declarar cierre por:

- secuencia exacta;
- dos victorias consecutivas;
- umbral de BO3/BO5;
- dos empates consecutivos;
- ventaja al finalizar D4;
- D5;
- condicion personalizada.

No se habilitan duelos posteriores al cierre. Un empate final puede ser valido, requerir desempate, muerte subita, nuevo match o ser invalido segun `draw_policy` y fase.

## Puntos y tabla

Configuracion por defecto: victoria 3, empate 1, derrota 0. Amistosos y eventos con `ranking_impact=NONE` no alteran puntos oficiales.

Orden por defecto: puntos, diferencia de duelos, duelos ganados y enfrentamiento directo. Si persiste un empate relevante, la regla del evento define desempate.

## Eventos

- Clasificatorio: ordena participantes y puede crear divisiones.
- Liga: acumula tabla, jornadas y movimientos entre divisiones.
- Copa/final: avanza ganadores por llave o Top Cut.
- Mundial: grupos y/o eliminacion configurable.
- Amistoso: conserva historial sin impacto oficial por defecto.
- Especial: condiciones definidas por su regla.

Las reglas se versionan y los snapshots impiden reinterpretar resultados anteriores cuando cambie una formula.
