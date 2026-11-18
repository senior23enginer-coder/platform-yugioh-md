<section class="screen event-config-screen" data-screen="evento_ficha" hidden data-competition-config-modal>
        <form method="post" class="panel event-config-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="configure_competition">

            <input type="hidden" name="competition_id" data-competition-config-id>
            <input type="hidden" name="config_mode" value="save" data-competition-config-mode>
            <div class="event-config-hero">
                <div class="event-config-nav">
                    <button type="button" class="btn btn-outline-light btn-sm" data-route="torneos"><i class="bi bi-arrow-left"></i> Eventos</button>
                    <span class="tag" data-competition-config-status-chip>Pendiente configuracion</span>
                </div>
                <div class="event-config-title-block">
                    <span class="tag">Ficha del evento</span>
                    <div class="event-config-title-row">
                        <span class="event-config-logo" data-competition-config-logo-wrap><img src="" alt="Logo del evento" data-competition-config-logo></span>
                        <div>
                            <h2 class="modal-title" data-competition-config-title>Ficha del evento</h2>
                            <p data-competition-config-meta>Configuracion, decks, calendario y prerequisitos persistidos en base de datos.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div class="event-config-stack">
                    <section class="panel compact-panel event-form-panel">
                        <div class="panel-head compact-head"><div><h3>Datos del evento</h3><p>Informacion principal persistida en base de datos.</p></div><div class="event-data-actions"><button class="btn btn-outline-info fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#eventLogoModal"><i class="bi bi-image"></i> Logo</button><button class="btn btn-outline-warning fw-bold" type="button" data-event-programming-open><i class="bi bi-calendar2-week"></i> Programar</button><button class="btn btn-outline-info fw-bold" type="button" data-league-movement-open hidden><i class="bi bi-arrow-down-up"></i> Ascenso y descenso</button></div></div>
                        <div class="event-main-fields">
                            <?php $seasonFieldMode = "config"; require YGO_APP_ROOT . "/includes/season-event-field.php"; ?>
                            <label class="span-2">Nombre del evento<input class="form-control" name="competition_name" data-competition-config-name placeholder="Nombre del evento"></label>
                            <label>Estado<select class="form-select" name="competition_status" data-competition-config-status><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><option value="<?php echo e($statusCode); ?>" <?php echo $statusCode === 'resume' ? 'data-resume-option hidden' : ''; ?>><?php echo e($statusLabel); ?></option><?php endforeach; ?></select><small data-competition-status-note></small></label>
                            <label>Tipo de evento<select class="form-select" name="competition_type" data-competition-config-type data-locked-always="1" disabled><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select><small>El tipo se define al crear el evento y no se edita desde la ficha.</small></label>
                            <label>Formato<select class="form-select" name="competition_event_ruleset" data-competition-config-format-label data-competition-config-ruleset><?php foreach ($rulesetSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e($rulesetCode); ?>"><?php echo e($rulesetLabel); ?></option><?php endforeach; ?></select></label>
                            <input type="hidden" name="competition_division_id" data-competition-config-division>
                            <label>Modo de participantes<select class="form-select" name="competition_deck_pool" data-competition-config-deck-pool><?php foreach (['all_active'=>'Seleccionar decks del formato','division'=>'Todos los decks del formato'] as $poolCode => $poolLabel): ?><option value="<?php echo e($poolCode); ?>"><?php echo e($poolLabel); ?></option><?php endforeach; ?></select><small data-competition-deck-pool-note></small></label>
                            <label>Logo del evento<input class="form-control" type="file" name="competition_logo" accept="image/png,image/jpeg,image/webp,image/gif" data-competition-config-logo-input data-event-logo-upload><small>Opcional. Si subes uno nuevo, reemplaza el logo actual.</small></label>
                            <label class="toggle-line"><input type="checkbox" name="competition_remove_logo" value="1" data-competition-config-remove-logo> Quitar imagen actual del evento</label>
                            <div class="event-logo-gallery span-2" data-event-logo-gallery>
                                <div class="event-logo-gallery-head">
                                    <span>Logo de galeria</span>
                                    <small data-event-logo-gallery-count></small>
                                </div>
                                <input type="hidden" name="competition_gallery_logo" data-event-logo-gallery-input>
                                <div class="event-logo-gallery-grid" data-event-logo-gallery-grid></div>
                            </div>
                            <label>Reglamento / banlist<select class="form-select" name="competition_ruleset_version" data-competition-config-ruleset-version><option value="">Por definir</option><?php foreach ($rulesetVersionCatalog as $versionOption): ?><?php $versionValue = (string) (($versionOption['version_label'] ?? '') ?: ($versionOption['version'] ?? '')); if ($versionValue === '') { continue; } ?><option value="<?php echo e($versionValue); ?>" data-ruleset="<?php echo e((string) (($versionOption['ruleset_code'] ?? '') ?: ($versionOption['system'] ?? ''))); ?>"><?php echo e(ygo_ruleset_version_option_label($versionOption)); ?><?php echo !empty($versionOption['effective_on']) ? ' (' . e((string) $versionOption['effective_on']) . ')' : ''; ?></option><?php endforeach; ?></select></label>
                            <div class="span-2"><h3>Reglas elegidas al crear el evento</h3><p>Valores guardados del evento. Se muestran para consulta; la programacion conserva estas reglas.</p></div>
                            <label data-event-main-field="template">Modelo preconfigurado del evento<input class="form-control" type="text" readonly data-event-main-choice="template" aria-label="Modelo preconfigurado del evento"></label>
                            <label>Parametros de ejecucion guardados<input class="form-control" type="text" readonly data-event-main-choice="parameters" aria-label="Parametros de ejecucion guardados"></label>
                            <label data-event-main-field="execution">Sistema competitivo<input class="form-control" type="text" readonly data-event-main-choice="execution" aria-label="Sistema competitivo"></label>
                            <label data-event-main-field="division">Formato / division principal<input class="form-control" type="text" readonly data-event-main-choice="division" aria-label="Formato / division principal"></label>
                            <label data-event-main-field="divisions">Divisiones asociadas a la liga<input class="form-control" type="text" readonly data-event-main-choice="divisions" aria-label="Divisiones asociadas a la liga"></label>
                            <label data-event-main-field="match">Participacion del match<input class="form-control" type="text" readonly data-event-main-choice="match" aria-label="Participacion del match"></label>
                            <label data-event-main-field="best-of">Formato de partida / Mejor de<input class="form-control" type="text" readonly data-event-main-choice="best-of" aria-label="Formato de partida / Mejor de"></label>
                            <label data-event-main-field="advancement">Regla de avance<input class="form-control" type="text" readonly data-event-main-choice="advancement" aria-label="Regla de avance"></label>
                            <label data-event-main-field="draw">Politica de empate<input class="form-control" type="text" readonly data-event-main-choice="draw" aria-label="Politica de empate"></label>
                            <label data-event-main-field="rule">Regla del evento<input class="form-control" type="text" readonly data-event-main-choice="rule" aria-label="Regla del evento"></label>
                            <label class="span-2 event-notes-field">Notas del evento<textarea class="form-control" name="competition_rules" rows="3" data-competition-config-rules placeholder="Notas internas, sede o aclaraciones del organizador"></textarea></label>
                        </div>
                        <input type="hidden" name="competition_accent" data-competition-config-accent>
                        <div class="event-config-actions mt-3"><button class="btn btn-primary fw-bold" type="button" data-competition-config-submit="save"><i class="bi bi-save"></i> Guardar ficha</button></div>
                    </section>
                    <div class="event-config-actions mb-3"><button class="btn btn-outline-info" type="button" data-open-event-saved-reading><i class="bi bi-info-circle"></i> Ver configuración guardada</button></div>
                    <section class="panel compact-panel event-form-panel" data-event-programming-panel>
                        <div class="panel-head compact-head"><div><h3>Programacion</h3><p>La plataforma calcula la fecha de cada duelo a partir de estos datos.</p></div></div>
                        <div class="event-schedule-fields">
                            <label>Dia de inicio<input class="form-control" type="date" name="competition_start_on" data-competition-config-start min="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e(date('Y-m-d')); ?>"></label>
                            <label>Tipo de match<select class="form-select" name="competition_match_format" data-competition-config-match><option value="ffa" data-participants="1">3-4 jugadores independientes</option><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>" data-participants="<?php echo e((string) ($matchType['participants_per_side'] ?? 1)); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select></label>
                            <label data-competition-config-qualifier-duels-wrap>Combates requeridos por deck<select class="form-select" name="qualifier_duels_per_deck" data-competition-config-qualifier-duels><option value="2">2 combates</option><option value="3">3 combates</option><option value="4">4 combates</option><option value="5">5 combates</option></select><small>Define cuantos combates debe finalizar cada deck para completar el clasificatorio.</small></label>
                            <label data-competition-config-matches-per-day-wrap>Duelos por dia<select class="form-select" name="qualifier_matches_per_day" data-competition-config-matches-per-day><option value="1">1 duelo por dia</option><option value="2">2 duelos por dia</option><option value="3">3 duelos por dia</option><option value="4">4 duelos por dia</option><option value="5">5 duelos por dia</option></select><small>Maximo 5. Controla cuantas partidas quedan en la misma fecha.</small></label>
                            <label>Regla del evento<select class="form-select" name="competition_game_rule_id" data-competition-config-rule><option value="">Sin regla especial</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['event', 'all_matches', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select></label>
                            <label>Frecuencia de duelos<select class="form-select" name="competition_schedule_pattern" data-competition-config-pattern><?php foreach ($schedulePatternOptions as $patternCode => $patternLabel): ?><option value="<?php echo e($patternCode); ?>"><?php echo e($patternLabel); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <article class="event-rule-applied-card" data-competition-rule-card>
                            <div>
                                <span>Regla aplicada a los duelos</span>
                                <strong data-competition-rule-name>Sin regla especial</strong>
                                <small data-competition-rule-code>Se usara la validacion compatible por defecto.</small>
                            </div>
                            <p data-competition-rule-effect>Selecciona una regla para ver que validaciones se aplicaran al cargar resultados.</p>
                            <button class="btn btn-outline-info btn-sm" type="button" data-open-event-rule-modal><i class="bi bi-list-check"></i> Ver detalle</button>
                        </article>
                        <div class="event-config-actions event-program-actions">
                            <button class="btn btn-primary fw-bold" type="button" data-competition-config-submit="save_schedule"><i class="bi bi-save"></i> Guardar programacion</button>
                            <button class="btn btn-warning fw-bold" type="button" data-competition-config-submit="schedule_qualifier" data-competition-config-qualifier-action><i class="bi bi-diagram-3"></i> Programar duelos clasificatorios</button>
                            <button class="btn btn-warning fw-bold" type="button" data-competition-config-submit="schedule_cup" data-competition-config-cup-action hidden><i class="bi bi-trophy"></i> Programar copa</button>
                            <button class="btn btn-outline-info fw-bold" type="button" data-open-event-ranking-modal data-requires-active-event disabled><i class="bi bi-table"></i> Ver tabla general</button>
                            <button class="btn btn-outline-info fw-bold" type="button" data-open-event-schedule-modal data-requires-active-event disabled><i class="bi bi-diagram-3"></i> Ver duelos programados</button>
                        </div>
                    </section>
                </div>
                <div class="modal fade dml-modal" id="eventLogoModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
                        <div class="modal-header"><div><span class="tag">Identidad visual</span><h2 class="modal-title">Logo del evento</h2><p>Sube una imagen o elige una de la galeria.</p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <div class="modal-body"><div class="event-logo-modal-fields" data-event-logo-modal-fields></div><button class="btn btn-outline-info mt-3" type="button" data-open-logo-history="competition"><i class="bi bi-clock-history"></i> Ver historial de logos</button></div>
                        <div role="status" aria-live="polite" class="px-3" data-event-logo-message></div>
                        <div class="modal-footer"><button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" data-event-logo-save>Guardar logo</button></div>
                    </div></div>
                </div>
                <div class="modal fade dml-modal" id="eventProgrammingModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"><div class="modal-content">
                        <div class="modal-header"><div><span class="tag">Programacion</span><h2 class="modal-title">Programar evento</h2><p>Estos datos se guardan al programar y organizan los cruces automaticamente.</p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <div class="modal-body" data-event-programming-modal-body></div>
                    </div></div>
                </div>
                <div class="modal fade dml-modal event-decks-modal" id="competitionDecksModal" tabindex="-1" aria-hidden="true" data-competition-decks-modal>
                    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <span class="tag">Participantes</span>
                                    <h2 class="modal-title" data-participant-modal-title>Decks participantes</h2>
                                    <p>Busca, agrega o quita decks activos sin repetir seleccion.</p>
                                </div>
                                <span class="status-chip" data-competition-config-count>0 seleccionados</span>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <section class="panel compact-panel event-deck-panel">
                                    <div class="deck-picker-tools">
                                        <div class="participant-division-buttons" data-participant-division-buttons aria-label="Divisiones de la liga" hidden></div>
                                        <input class="form-control" type="search" placeholder="Escribir deck..." data-competition-deck-search>
                                        <div class="deck-picker-row">
                                            <select class="form-select" aria-label="Deck y formato" aria-describedby="participantFormatRestriction" data-competition-deck-picker></select>
                                            <button class="btn btn-primary" type="button" data-competition-deck-add><i class="bi bi-plus-circle"></i> Agregar</button>
                                        </div>
                                        <p class="note mb-0" id="participantFormatRestriction" role="status" aria-live="polite" data-competition-format-restriction></p>
                                    </div>
                                    <div class="table-responsive selected-deck-table-wrap">
                                        <table class="table app-table compact selected-deck-table mobile-card-table">
                                            <thead><tr><th>#</th><th>Deck</th><th>Equipo</th><th>Formato</th><th>Accion</th></tr></thead>
                                            <tbody data-competition-selected-decks></tbody>
                                        </table>
                                    </div>
                                    <div class="deck-hidden-inputs" data-competition-hidden-decks hidden>
                                        <?php foreach ($allDecks as $deck): ?>
                                            <label data-config-deck-row data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>">
                                                <input type="checkbox" name="competition_deck_ids[]" value="<?php echo e((string) ($deck['id'] ?? '')); ?>" data-competition-config-deck>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade dml-modal qualifier-load-modal" id="qualifierLoadModal" tabindex="-1" aria-hidden="true" data-qualifier-load-modal>
                    <div class="modal-dialog modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <span class="tag">Cargar clasificatorio</span>
                                    <h2 class="modal-title">Clasificatorios finalizados</h2>
                                    <p>Selecciona un rango par de posiciones para cargar decks a esta copa o torneo.</p>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="qualifier-load-layout">
                                    <section class="qualifier-load-table">
                                        <input class="form-control" type="search" placeholder="Buscar clasificatorio, formato o fecha..." data-qualifier-load-search>
                                        <div class="table-responsive">
                                            <table class="table app-table compact">
                                                <thead><tr><th>Clasificatorio</th><th>Formato</th><th>Finalizacion</th><th>Participantes</th><th>Ranking</th><th>Accion</th></tr></thead>
                                                <tbody data-qualifier-load-list></tbody>
                                            </table>
                                        </div>
                                    </section>
                                    <section class="qualifier-load-panel">
                                        <h3 data-qualifier-load-title>Selecciona un clasificatorio</h3>
                                        <p data-qualifier-load-meta>Solo se listan clasificatorios finalizados con ranking calculado.</p>
                                        <div class="two-col">
                                            <label>Desde posicion<input class="form-control" type="number" min="1" step="1" data-qualifier-load-from value="1"></label>
                                            <label>Hasta posicion<input class="form-control" type="number" min="1" step="1" data-qualifier-load-to value="4"></label>
                                        </div>
                                        <p class="note" data-qualifier-load-note>El total debe ser par y minimo 4.</p>
                                        <div class="qualifier-preview-summary" data-qualifier-load-preview></div>
                                        <button class="btn btn-warning fw-bold w-100" type="button" data-qualifier-load-apply disabled><i class="bi bi-upload"></i> Cargar decks seleccionados</button>
                                    </section>
                                </div>
                                <section class="qualifier-ranking-section">
                                    <div class="panel-head compact-head">
                                        <div><h3>Ranking completo del clasificatorio</h3><p>Todas las posiciones disponibles para elegir el rango exacto.</p></div>
                                    </div>
                                    <div class="table-responsive qualifier-ranking-table-wrap">
                                        <table class="table app-table compact">
                                            <thead><tr><th>Posicion</th><th>Deck</th><th>Equipo</th><th>Formato</th><th>PJ</th><th>Pts</th></tr></thead>
                                            <tbody data-qualifier-load-ranking></tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer event-config-footer-actions">
                <div class="event-footer-left">
                    <button class="btn btn-outline-light" type="button" data-route="torneos"><i class="bi bi-arrow-left"></i> Volver</button>
                </div>
                <div class="event-footer-tools">
                    <button class="btn btn-outline-warning fw-bold" type="button" data-qualifier-load-open hidden><i class="bi bi-upload"></i> Cargar clasificatorio</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-competition-decks-open><i class="bi bi-collection"></i> Decks participantes</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-team-lineups hidden>Equipos y alineaciones</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-team-matches hidden>Resultados por equipo</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-event-admission>Fuentes y clasificados</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-open-event-recovery hidden>Corregir fase anterior</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-event-rule-modal><i class="bi bi-list-check"></i> Ver regla aplicada</button>
                    <button class="btn btn-outline-light fw-bold" type="button" data-open-event-audit><i class="bi bi-clock-history"></i> Auditoría</button>
                </div>
                <div class="event-action-preview" data-competition-action-preview>
                    <strong>Lectura preventiva</strong>
                    <span>Selecciona un evento activo para ver si puede avanzar, aplicar divisiones o mover equipos.</span>
                </div>
                <div class="event-footer-main">
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-action-submit="advance_competition" data-requires-active-event hidden disabled><i class="bi bi-chevron-double-right"></i> Crear siguiente fase</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-action-submit="apply_qualifier_divisions" data-requires-active-event disabled><i class="bi bi-diagram-3"></i> Aplicar divisiones</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-action-submit="apply_league_movements" data-requires-active-event hidden disabled><i class="bi bi-arrow-down-up"></i> Aplicar ascensos/descensos</button>
                    <button class="btn btn-outline-danger fw-bold" type="button" data-competition-delete-open disabled><i class="bi bi-trash"></i> Eliminar evento</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-finalize-submit hidden disabled><i class="bi bi-flag-fill"></i> Finalizar evento</button>
                    <button class="btn btn-primary fw-bold" type="button" data-competition-config-submit="start"><i class="bi bi-play-circle"></i> Comenzar</button>
                </div>
            </div>

            <div class="modal fade dml-modal" id="leagueMovementModal" tabindex="-1" aria-labelledby="leagueMovementTitle" aria-hidden="true" data-league-movement-modal>
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                    <div class="modal-header"><div><h2 class="modal-title" id="leagueMovementTitle">Ascenso y descenso</h2><p data-league-movement-summary></p></div><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                    <div class="modal-body"><p class="note">Configura un mismo numero de ascensos y descensos entre divisiones consecutivas. La primera division solo desciende y la ultima solo asciende. Los movimientos se aplican al finalizar la liga con resultados.</p><div data-league-movement-rows></div><label class="toggle-line mt-3"><input type="checkbox" name="competition_lock_division_moves" value="1" data-competition-config-lock-moves> Bloquear movimientos entre divisiones</label></div>
                    <div class="modal-footer"><button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-primary" type="button" data-competition-config-submit="save">Guardar ascenso y descenso</button></div>
                </div></div>
            </div>
        </form>
</section>

