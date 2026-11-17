# Ranking global de decks TCG/OCG

La ficha y la pestana Ranking global usan el catalogo completo `allDecks`, no solo las filas estadisticas con puesto. El total cuenta una vez cada identidad TCG/OCG (TCG Advanced, TCG Traditional y OCG), independientemente de liga, division, inventario o partidas. Rush y GOAT no entran en este conjunto.

Las puntuaciones siguen procediendo de resultados confirmados. Se ordenan las filas clasificadas por total, rating, campeonatos y desempate estable de equipo/deck. Se mantiene el minimo de dos partidas; los decks provisionales, inactivos o sin partidas cuentan en el catalogo pero no reciben un puesto artificial ni un rating inventado.

Una identidad con varios formatos se cuenta una vez y usa la primera metrica disponible segun su orden de formatos declarados; no se suman ratings ni se duplican partidas. Ranking por formato y metagame conservan su separacion. No se modifican resultados, temporadas, Elo persistido ni medallas.

Motor compartido: `assets/modules/statistics/global-ranking.js`. La actualizacion automatica existente alimenta tanto la ficha como Estadisticas y tops. Pruebas: `global_ranking_scope_test.cjs`, `profile_global_rank_test.cjs` y `statistics_workspace_test.cjs`.
