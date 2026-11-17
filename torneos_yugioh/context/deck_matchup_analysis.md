# Analisis particular del deck

## Alcance

Cada deck activo dispone de una subpantalla `Analisis del deck` dentro de su ficha. La pantalla contiene el perfil estrategico general del deck y compara una evaluacion editorial de cada matchup con los resultados persistidos, sin mezclar las fuentes.

## Perfil estrategico general

Se versionan estilo de juego, ritmo, complejidad, plan principal, condicion de victoria, motor de recursos, planes de apertura/juego medio/juego tardio, primero/segundo, Side Deck, fortalezas, debilidades y etiquetas. Tambien admite valoraciones editoriales de consistencia, techo de poder, resiliencia, interaccion, recuperacion y desempeno teorico primero/segundo.

Los indicadores observados de ritmo, posicion y brick se muestran al lado, pero nunca modifican el perfil editorial automaticamente.

### Base editorial inicial

- Los 54 decks activos cuentan con una base inicial versionada en `includes/deck_analysis_baselines.php`.
- La base completa identidad, ritmo, complejidad, plan principal, condicion de victoria, motor de recursos, planes por fase, plan primero/segundo, Side Deck, fortalezas, debilidades, etiquetas y siete valoraciones editoriales.
- El sembrado usa `INSERT IGNORE`: solo crea perfiles ausentes y nunca reemplaza una ficha editada.
- Cada alta genera la revision 1 en `sx_ygo_deck_analysis_profile_history` con motivo `Base editorial inicial`.
- Los nombres ambiguos o listas no confirmadas quedan con confianza `BAJA` y nota de revision; no se presentan como hechos observados.
- Los indicadores de resultados permanecen separados y se calculan solo desde duelos finalizados guardados en la plataforma.

## Fuentes

- `EDITORIAL` o `ANALYTICAL`: evaluacion manual y direccional de un deck contra otro.
- `CALCULATED`: partidas, duelos y posiciones calculados desde resultados finalizados.
- Un winrate observado nunca crea ni modifica `strategic_score`.
- Los ejemplos del documento funcional son ilustrativos y no se cargan como datos reales.

## Alcances soportados

- Deck contra deck.
- Version de deck contra version rival.
- Banlist o version de reglas.
- Todo el historico, 30 dias, 90 dias, ano o temporada actual.
- Evento, division y posicion al comenzar.

Si una lista o version historica no existe en `sx_ygo_match_snapshots`, la interfaz no la infiere. Los snapshots marcados como inferidos se contabilizan separadamente de los exactos.

## Comparacion cualitativa

La capa de comparacion usa tres tendencias: positiva, neutral y negativa. El resultado puede ser `ALIGNED`, `OVERPERFORMING`, `UNDERPERFORMING` o `INSUFFICIENT`.

La muestra minima usa `stats_minimum_sample`. Una revision se recomienda solo cuando existe analisis editorial, la tendencia difiere y hay al menos dos veces la muestra minima. La recomendacion no altera automaticamente la teoria.

## Persistencia

- `sx_ygo_matchup_analyses`: estado editorial vigente por direccion y alcance.
- `sx_ygo_matchup_analysis_history`: snapshot inmutable de cada revision.
- `sx_ygo_audit_log`: registra el guardado y la revision creada.

La direccion inversa es independiente: guardar A contra B no crea ni modifica B contra A.
