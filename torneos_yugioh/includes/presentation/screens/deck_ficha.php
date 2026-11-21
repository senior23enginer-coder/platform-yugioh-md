    <section class="screen" data-screen="deck_ficha" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.deck_ficha', 'title', 'Ficha del deck')); ?></h1><p><?php echo e($uiText('route.deck_ficha', 'subtitle', 'Detalle, estadisticas, cartas, eventos y edicion del deck seleccionado.')); ?></p></div>
            <div class="profile-header-actions">
                <button class="btn btn-outline-info" type="button" data-profile-calendar><i class="bi bi-calendar3"></i> Calendario</button>
                <button class="btn btn-outline-light" type="button" data-route="deck"><i class="bi bi-arrow-left"></i> Volver a decks</button>
            </div>
        </div>
        <select data-deck-select hidden aria-hidden="true" tabindex="-1"></select>
        <div class="deck-profile-layout" data-deck-profile-view="summary">
            <div class="deck-profile-aside">
                <section class="panel profile-panel deck-identity-panel">
                    <div class="deck-cover" data-profile-cover><div class="deck-avatar" data-profile-initials></div></div>
                    <h2 data-profile-name></h2>
                    <p data-profile-division></p>
                    <span class="status-chip" data-profile-rank></span>
                    <p class="note mt-2" data-profile-rank-detail></p>
                    <div class="profile-meta mt-3">
                        <span class="deck-team-profile-card"><b>Equipo asociado</b><small data-profile-team></small></span>
                        <span><b>Arquetipo</b><small data-profile-archetype></small></span>
                        <span><b>Estilo</b><small data-profile-style></small></span>
                        <span><b>Plan de juego</b><small data-profile-gameplan>Consulta la guía del deck para conocer sus líneas y recursos.</small></span>
                        <span data-profile-attributes></span>
                    </div>
                </section>
            </div>
            <div class="deck-profile-main">
                <section class="panel deck-category-hub">
                    <div class="panel-head"><div><h2>Explorar ficha</h2><p>Abre una categoria sin recorrer toda la pagina.</p></div><span class="status-chip deck-last-updated" data-deck-last-updated aria-live="polite">Consultando última actualización…</span></div>
                    <div class="deck-category-grid">
                        <button type="button" data-deck-category-open="performance"><i class="bi bi-graph-up-arrow"></i><span><b>Rendimiento</b><small data-deck-category-metric="performance">Partidas y duelos</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="history"><i class="bi bi-clock-history"></i><span><b>Historial y agenda</b><small data-deck-category-metric="history">Partidas registradas</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="events"><i class="bi bi-calendar2-event"></i><span><b>Historial de eventos</b><small data-deck-category-metric="events">Participaciones e inscripciones</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="decklist"><i class="bi bi-collection"></i><span><b>Decklist y multimedia</b><small data-deck-category-metric="decklist">Main, Extra y Side</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="ranking"><i class="bi bi-bar-chart-line"></i><span><b>Ranking y tier</b><small data-deck-category-metric="ranking">Posicion e historico</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="achievements"><i class="bi bi-trophy"></i><span><b>Logros</b><small data-deck-category-metric="achievements">Titulos y medallas</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="strategy"><i class="bi bi-diagram-3"></i><span><b>Estilo y estrategia</b><small data-deck-category-metric="strategy">Plan competitivo</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="created-combos"><i class="bi bi-bezier2"></i><span><b>Mis combos y estrategias</b><small>Guías visuales, combos y creaciones del deck</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="play-calculator"><i class="bi bi-calculator"></i><span><b>Calculador de jugadas</b><small data-deck-category-metric="play-calculator">Tablero interactivo · cálculo local</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="matchups"><i class="bi bi-grid-3x3-gap"></i><span><b>Matchups</b><small data-deck-category-metric="matchups">Teoria contra realidad</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="edit"><i class="bi bi-sliders"></i><span><b>Editar deck</b><small data-deck-category-metric="edit">Datos, logo y notas</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-data-open><i class="bi bi-arrow-down-up"></i><span><b>Importar / exportar Data</b><small>Listado de cartas y contenido del deck en ZIP</small></span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </section>
                <section class="panel deck-analysis-progress-panel" data-deck-analysis-progress hidden>
                    <div class="panel-head">
                        <div><h2>Seguimiento A01–A10A</h2><p>Control de tareas y tiempos del deck asignado. No ejecuta IA automáticamente.</p></div>
                        <span class="status-chip" data-deck-progress-headline>Sin asignación</span>
                    </div>
                    <div data-deck-progress-content aria-live="polite"></div>
                </section>
                <?php require YGO_APP_ROOT . '/includes/decks/play-calculator-view.php'; ?>
                <section class="panel deck-created-combos-panel"><div data-deck-created-combos></div></section>
                <section class="panel deck-editor-panel">
                    <div class="panel-head"><div><h2>Crear / editar deck</h2><p>El equipo asociado se consulta aqui. Asociar o desasociar decks se hace desde la ficha del equipo.</p></div><span class="status-chip">Ficha deck</span></div>
                    <form method="post" class="gestion-form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_deck">
                        <input type="hidden" name="deck_id" data-gestion-deck-id>
                        <div class="deck-form-grid">
                            <label class="span-2">Editar deck existente<select class="form-select" data-gestion-deck-select><option value="">Nuevo deck</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                            <label class="span-2">Nombre del deck<input class="form-control" name="deck_name" data-gestion-deck-name required></label>
                            <label>Equipo asociado<input class="form-control" data-gestion-deck-team-label value="Sin equipo" readonly></label>
                            <label>Division<select class="form-select" name="division_id" data-gestion-deck-division><option value="">Sin division</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                            <label>Semilla interna<input class="form-control" type="number" min="1" name="seed_rank" data-gestion-deck-rank value="999"><small>Sólo se usa para programación; no representa el ranking global.</small></label>
                            <label>Estilo<input class="form-control" name="style" data-gestion-deck-style placeholder="Combo, control, midrange"></label>
                            <label class="span-2">Arquetipo<input class="form-control" name="archetype" data-gestion-deck-archetype></label>
                            <label class="span-2">Motivo / resumen<input class="form-control" name="reason" data-gestion-deck-reason></label>
                            <label>Cartas clave<textarea class="form-control" name="key_cards" data-gestion-deck-cards rows="3"></textarea></label>
                            <label>Variaciones<textarea class="form-control" name="variations" data-gestion-deck-variations rows="3"></textarea></label>
                            <label class="span-2">Notas / ficha<textarea class="form-control" name="notes" data-gestion-deck-notes rows="3"></textarea></label>
                            <input type="hidden" name="cover_url" data-gestion-deck-cover>
                            <label class="span-2 deck-upload-field">Subir logo del deck<input class="form-control" type="file" name="deck_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>PNG, JPG, WEBP o GIF. Si subes uno nuevo, reemplaza la imagen actual del deck.</small></label>
                        </div>
                        <div class="deck-form-actions">
                            <button class="btn btn-outline-info fw-bold" type="button" data-open-logo-history="deck"><i class="bi bi-images"></i> Historial de logos</button>
                            <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar deck</button>
                        </div>
                    </form>
                    <form method="post" class="deck-delete-form">
                        <input type="hidden" name="action" value="remove_deck">
                        <input type="hidden" name="deck_id" data-gestion-remove-deck-id>
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Quitar deck seleccionado</button>
                    </form>
                </section>
                <section class="panel deck-profile-detail-panel">
                    <div class="panel-head"><div><h2>Rendimiento competitivo</h2><p>Partidas y duelos se contabilizan por separado.</p></div></div>
                    <div class="deck-record-overview">
                        <article class="match-record-card"><span>Partidas G / E / P</span><strong data-profile-match-record>0 / 0 / 0</strong><small data-profile-match-rate>0% de victorias</small></article>
                        <article class="duel-record-card"><span>Duelos G / E / P</span><strong data-profile-duel-record>0 / 0 / 0</strong><small data-profile-duel-rate>0% de victorias</small></article>
                        <article><span>Actividad</span><strong data-profile-total-scheduled>0</strong><small data-profile-pending>0 pendientes</small></article>
                    </div>
                    <div class="mini-grid profile-stats" data-profile-stats></div>
                    <div class="deck-builder-rivals mt-3" data-profile-rivals>
                        <article><h3>Rivales a los que más ha vencido</h3><div class="profile-list" data-profile-top-beaten></div></article>
                        <article><h3>Rivales contra los que más ha perdido</h3><div class="profile-list" data-profile-top-lost-to></div></article>
                    </div>
                    <div class="tier-history-summary mt-3" data-profile-tier-history></div>
                    <div class="card-gallery mt-3" data-card-gallery></div>
                    <div class="deck-detail-grid mt-3">
                        <article><h3>Cartas clave</h3><p data-profile-cards></p></article>
                        <article><h3>Variaciones</h3><p data-profile-variations></p></article>
                        <article><h3>Notas</h3><p data-profile-notes></p></article>
                        <article><h3>Cartas registradas</h3><div class="profile-list" data-profile-card-list></div></article>
                        <article><h3>Media</h3><div class="profile-media" data-profile-media-list></div></article>
                    </div>
                    <div class="deck-achievement-layout mt-3">
                        <section><div class="panel-head"><div><h3>Logros</h3><p>Titulos y subcampeonatos obtenidos en eventos.</p></div></div><div class="deck-honor-list" data-profile-honors></div></section>
                        <section><div class="panel-head"><div><h3>Campeonatos y logros</h3><p>Consulta los requisitos, las medallas obtenidas y el progreso de tu equipo.</p></div></div><div class="stats-v8-profile" data-profile-medals></div></section>
                    </div>
                    <section class="deck-events-section" data-profile-events></section>
                    <section class="deck-history-section mt-3">
                        <div class="panel-head"><div><h3>Historial de partidas</h3><p>Programacion, rival, resultado de la partida y récord de sus duelos.</p></div><span class="status-chip" data-profile-history-count>0 partidas</span></div>
                        <div class="table-responsive"><table class="table app-table compact deck-history-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Ronda / mesa</th><th>Rival</th><th>Estado</th><th>Resultado</th><th>Duelos G/E/P</th><th></th></tr></thead><tbody data-profile-history></tbody></table></div>
                    </section>
                </section>
            </div>
        </div>
        <section class="deck-analysis-screen" data-deck-profile-view="analysis" hidden>
            <header class="deck-analysis-header">
                <div><span class="eyebrow">Analisis particular</span><h2 data-matchup-analysis-title>Teoria vs. realidad</h2><p>La compatibilidad estrategica es editorial. Los resultados se recalculan exclusivamente desde partidas persistidas.</p></div>
                <div class="analysis-source-legend"><span><i class="bi bi-pencil-square"></i> EDITORIAL / ANALYTICAL</span><span><i class="bi bi-database-check"></i> OBSERVED / CALCULATED</span></div>
            </header>
            <section class="deck-strategy-profile">
                <div class="panel-head"><div><span class="eyebrow">Perfil general</span><h3>Identidad y estilo de juego</h3><p>Describe como funciona el deck. Este bloque es editorial y no cambia por una racha de resultados.</p></div><span class="source-badge editorial" data-deck-analysis-source>EDITORIAL</span></div>
                <div class="deck-strategy-identity" data-deck-strategy-identity></div>
                <div class="deck-strategy-content">
                    <div data-deck-strategy-plans></div>
                    <div data-deck-strategy-strengths></div>
                    <div data-deck-strategy-scores></div>
                </div>
                <details class="deck-strategy-editor">
                    <summary><span><i class="bi bi-pencil-square"></i> Editar perfil estrategico completo</span><i class="bi bi-chevron-down"></i></summary>
                    <form method="post" data-deck-analysis-profile-form>
                        <input type="hidden" name="action" value="save_deck_analysis_profile">
                        <input type="hidden" name="deck_analysis_deck_id" data-deck-analysis-form-id>
                        <div class="deck-strategy-form-grid">
                            <label>Estilo de juego<input class="form-control" name="deck_analysis_play_style" data-deck-analysis-play-style placeholder="Combo, control, midrange, stun, OTK..."></label>
                            <label>Ritmo<select class="form-select" name="deck_analysis_pace" data-deck-analysis-pace><option value="">Pendiente</option><option value="EXPLOSIVO">Explosivo</option><option value="RAPIDO">Rapido</option><option value="MEDIO">Medio</option><option value="LENTO">Lento</option><option value="ADAPTABLE">Adaptable</option></select></label>
                            <label>Complejidad<select class="form-select" name="deck_analysis_complexity" data-deck-analysis-complexity><option value="">Pendiente</option><option value="BAJA">Baja</option><option value="MEDIA">Media</option><option value="ALTA">Alta</option><option value="MUY_ALTA">Muy alta</option></select></label>
                            <label>Confianza<select class="form-select" name="deck_analysis_confidence" data-deck-analysis-confidence><option>BAJA</option><option selected>MEDIA</option><option>ALTA</option></select></label>
                            <label>Fuente<select class="form-select" name="deck_analysis_source_type" data-deck-analysis-source-type><option>EDITORIAL</option><option>ANALYTICAL</option></select></label>
                            <label class="span-2">Plan principal<textarea class="form-control" name="deck_analysis_primary_game_plan" data-deck-analysis-primary-plan rows="3"></textarea></label>
                            <label class="span-2">Condicion de victoria<textarea class="form-control" name="deck_analysis_win_condition" data-deck-analysis-win-condition rows="3"></textarea></label>
                            <label class="span-2">Motor de recursos<textarea class="form-control" name="deck_analysis_resource_engine" data-deck-analysis-resource-engine rows="3"></textarea></label>
                            <label>Plan de apertura<textarea class="form-control" name="deck_analysis_opening_plan" data-deck-analysis-opening-plan rows="3"></textarea></label>
                            <label>Plan de juego medio<textarea class="form-control" name="deck_analysis_mid_game_plan" data-deck-analysis-mid-plan rows="3"></textarea></label>
                            <label>Plan de juego tardio<textarea class="form-control" name="deck_analysis_late_game_plan" data-deck-analysis-late-plan rows="3"></textarea></label>
                            <label>Plan empezando primero<textarea class="form-control" name="deck_analysis_going_first_plan" data-deck-analysis-first-plan rows="3"></textarea></label>
                            <label>Plan empezando segundo<textarea class="form-control" name="deck_analysis_going_second_plan" data-deck-analysis-second-plan rows="3"></textarea></label>
                            <label>Estrategia de Side Deck<textarea class="form-control" name="deck_analysis_side_deck_strategy" data-deck-analysis-side-strategy rows="3"></textarea></label>
                            <label>Fortalezas<textarea class="form-control" name="deck_analysis_strengths" data-deck-analysis-strengths rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Debilidades<textarea class="form-control" name="deck_analysis_weaknesses" data-deck-analysis-weaknesses rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Etiquetas estrategicas<textarea class="form-control" name="deck_analysis_strategy_tags" data-deck-analysis-tags rows="4" placeholder="Una por linea"></textarea></label>
                        </div>
                        <fieldset class="deck-strategy-score-editor"><legend>Valoracion editorial de 0 a 5</legend>
                            <label>Consistencia<input type="number" class="form-control" min="0" max="5" name="deck_analysis_consistency_score" data-deck-analysis-consistency></label>
                            <label>Techo de poder<input type="number" class="form-control" min="0" max="5" name="deck_analysis_ceiling_score" data-deck-analysis-ceiling></label>
                            <label>Resiliencia<input type="number" class="form-control" min="0" max="5" name="deck_analysis_resilience_score" data-deck-analysis-resilience></label>
                            <label>Interaccion<input type="number" class="form-control" min="0" max="5" name="deck_analysis_interaction_score" data-deck-analysis-interaction></label>
                            <label>Recuperacion<input type="number" class="form-control" min="0" max="5" name="deck_analysis_recovery_score" data-deck-analysis-recovery></label>
                            <label>Primero<input type="number" class="form-control" min="0" max="5" name="deck_analysis_first_score" data-deck-analysis-first-score></label>
                            <label>Segundo<input type="number" class="form-control" min="0" max="5" name="deck_analysis_second_score" data-deck-analysis-second-score></label>
                        </fieldset>
                        <div class="deck-strategy-form-grid mt-3">
                            <label class="span-2">Notas del analisis<textarea class="form-control" name="deck_analysis_notes" data-deck-analysis-notes rows="3"></textarea></label>
                            <label class="span-2">Motivo de esta revision<input class="form-control" name="deck_analysis_change_reason" placeholder="Cambio de soporte, banlist o revision editorial"></label>
                        </div>
                        <div class="matchup-form-actions"><span data-deck-analysis-editor-state>Perfil estrategico pendiente.</span><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar perfil estrategico</button></div>
                    </form>
                </details>
            </section>
            <div class="deck-matchup-section-title"><span class="eyebrow">Analisis por rival</span><h3>Matchups: teoria vs. resultados</h3><p>Selecciona el rival y el alcance de los resultados que deseas comparar.</p></div>
            <div class="deck-analysis-filters">
                <label>Rival<select class="form-select" data-matchup-opponent></select></label>
                <label>Periodo<select class="form-select" data-matchup-period><option value="all">Todo el historico</option><option value="30">Ultimos 30 dias</option><option value="90">Ultimos 90 dias</option><option value="year">Ano actual</option><option value="season">Temporada actual</option></select></label>
                <label>Evento<select class="form-select" data-matchup-event><option value="">Todos los eventos</option></select></label>
                <label>Division<select class="form-select" data-matchup-division><option value="">Todas las divisiones</option></select></label>
                <label>Banlist / reglas<select class="form-select" data-matchup-ruleset-version><option value="">Todas las versiones</option></select></label>
                <label>Version propia<select class="form-select" data-matchup-version><option value="">Todas las versiones</option></select></label>
                <label>Version rival<select class="form-select" data-matchup-opponent-version><option value="">Todas las versiones</option></select></label>
                <label>Posicion<select class="form-select" data-matchup-position><option value="">Primero y segundo</option><option value="first">Empezando primero</option><option value="second">Empezando segundo</option></select></label>
            </div>
            <div class="deck-analysis-kpis" data-matchup-overview></div>
            <div class="deck-analysis-columns">
                <section class="analysis-pane is-strategic">
                    <div class="panel-head"><div><span class="eyebrow">Nivel 1 - Teoria</span><h3>Compatibilidad estrategica</h3><p>No se deriva del winrate y solo cambia mediante una revision editorial.</p></div><span class="source-badge editorial">EDITORIAL</span></div>
                    <div data-matchup-strategic-summary></div>
                </section>
                <section class="analysis-pane is-observed">
                    <div class="panel-head"><div><span class="eyebrow">Nivel 2 - Realidad</span><h3>Rendimiento observado</h3><p>Calculado al instante con el alcance seleccionado.</p></div><span class="source-badge calculated">CALCULATED</span></div>
                    <div data-matchup-observed-summary></div>
                </section>
            </div>
            <section class="analysis-comparison-band">
                <div><span class="eyebrow">Nivel 3 - Analisis</span><h3>Comparacion cualitativa</h3></div>
                <div data-matchup-comparison></div>
            </section>
            <div class="deck-analysis-lower-grid">
                <section class="panel matchup-editor-panel">
                    <div class="panel-head"><div><h3>Editar analisis estrategico</h3><p>Una direccion no copia automaticamente la direccion inversa. Cada revision queda en el historico.</p></div></div>
                    <form method="post" data-matchup-analysis-form>
                        <input type="hidden" name="action" value="save_matchup_analysis">
                        <input type="hidden" name="matchup_deck_id" data-matchup-form-deck-id>
                        <input type="hidden" name="matchup_opponent_deck_id" data-matchup-form-opponent-id>
                        <div class="matchup-form-grid">
                            <label>Score estrategico<select class="form-select" name="strategic_score" data-matchup-form-score><option value="2">+2 Muy favorable</option><option value="1">+1 Favorable</option><option value="0" selected>0 Equilibrado</option><option value="-1">-1 Desfavorable</option><option value="-2">-2 Muy desfavorable</option></select></label>
                            <label>Confianza<select class="form-select" name="matchup_confidence" data-matchup-form-confidence><option>BAJA</option><option selected>MEDIA</option><option>ALTA</option></select></label>
                            <label>Fuente<select class="form-select" name="matchup_source_type" data-matchup-form-source><option>EDITORIAL</option><option>ANALYTICAL</option></select></label>
                            <label>Banlist / reglas<input class="form-control" name="matchup_ruleset_version" data-matchup-form-ruleset placeholder="General si queda vacio"></label>
                            <label>Version propia<input class="form-control" name="matchup_deck_version" data-matchup-form-deck-version placeholder="General si queda vacio"></label>
                            <label>Version rival<input class="form-control" name="matchup_opponent_version" data-matchup-form-opponent-version placeholder="General si queda vacio"></label>
                            <label class="span-3">Razon estrategica<textarea class="form-control" name="matchup_reason" data-matchup-form-reason rows="3" placeholder="Describe interacciones mecanicas, recursos y condiciones del matchup."></textarea></label>
                            <label>Ventajas clave<textarea class="form-control" name="matchup_advantages" data-matchup-form-advantages rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Desventajas clave<textarea class="form-control" name="matchup_disadvantages" data-matchup-form-disadvantages rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Choke points<textarea class="form-control" name="matchup_choke_points" data-matchup-form-choke-points rows="4" placeholder="Uno por linea"></textarea></label>
                            <label>Plan empezando primero<textarea class="form-control" name="matchup_going_first_plan" data-matchup-form-first-plan rows="3"></textarea></label>
                            <label>Plan empezando segundo<textarea class="form-control" name="matchup_going_second_plan" data-matchup-form-second-plan rows="3"></textarea></label>
                            <label>Plan de Side Deck<textarea class="form-control" name="matchup_side_plan" data-matchup-form-side-plan rows="3"></textarea></label>
                            <label class="span-3">Notas de revision<textarea class="form-control" name="matchup_review_notes" data-matchup-form-review-notes rows="2"></textarea></label>
                            <label class="span-3">Motivo de este cambio<input class="form-control" name="matchup_change_reason" placeholder="Soporte nuevo, banlist, nueva version o revision del analista"></label>
                        </div>
                        <div class="matchup-form-actions"><span data-matchup-editor-state>Sin analisis guardado para este alcance.</span><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar revision estrategica</button></div>
                    </form>
                </section>
                <section class="panel matchup-history-panel">
                    <div class="panel-head"><div><h3>Historico editorial</h3><p>Versiones anteriores del matchup seleccionado.</p></div><span class="status-chip" data-matchup-history-count>0 revisiones</span></div>
                    <div data-matchup-history></div>
                </section>
            </div>
            <section class="panel mt-3">
                <div class="panel-head"><div><h3>Mapa de rivales del deck</h3><p>Todos los rivales con evidencia real y su analisis editorial independiente.</p></div><span class="status-chip" data-matchup-table-count>0 rivales</span></div>
                <div class="table-responsive"><table class="table app-table mobile-card-table"><thead><tr><th>Rival</th><th>Teoria</th><th>Muestra</th><th>Partidas G/E/P</th><th>WR partidas</th><th>WR duelos</th><th>Comparacion</th><th>Revision</th></tr></thead><tbody data-matchup-rivals-table></tbody></table></div>
            </section>
        </section>
        <div data-deck-category-staging hidden></div>
        <div class="modal fade dml-modal deck-category-modal" id="deckCategoryModal" tabindex="-1" aria-hidden="true" data-deck-category-modal>
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><span class="tag">Ficha del deck</span><h2 class="modal-title" data-deck-category-title>Detalle</h2><p data-deck-category-subtitle></p></div>
                        <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" data-deck-category-body></div>
                    <div class="modal-footer deck-category-modal-footer">
                        <button class="btn btn-outline-light" type="button" data-deck-category-prev title="Categoria anterior"><i class="bi bi-chevron-left"></i><span>Anterior</span></button>
                        <span data-deck-category-position>1 de 9</span>
                        <button class="btn btn-outline-info" type="button" data-deck-category-next title="Categoria siguiente"><span>Siguiente</span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </section>

