    <section class="screen rule-detail-screen" data-screen="regla_ficha" hidden>
        <div class="page-title with-actions module-title">
            <div><h1 data-rule-detail-heading>Nueva regla</h1><p>Ficha editable de la regla y sus condiciones persistidas en base de datos.</p></div>
            <button class="btn btn-outline-light" type="button" data-route="reglamento"><i class="bi bi-arrow-left"></i> Volver al listado</button>
        </div>
        <form method="post" class="panel rule-detail-form" data-game-rule-form>
            <input type="hidden" name="action" value="save_game_rule">
            <input type="hidden" name="game_rule_id" data-game-rule-id>
            <div class="rule-detail-grid">
                <section class="rule-info-section">
                    <div class="panel-head compact-head">
                        <div><h2>Datos de la regla</h2><p>Define donde aplica, cuando se dispara y que efecto tiene antes de configurar sus condiciones.</p></div>
                    </div>
                    <div class="rule-info-grid">
                        <label>Codigo<input class="form-control" name="game_rule_code" data-game-rule-code placeholder="CUSTOM_XP" required></label>
                        <label>Nombre<input class="form-control" name="game_rule_name" data-game-rule-name required placeholder="CUSTOM XP"></label>
                        <label>Tipo<select class="form-select" name="game_rule_type" data-game-rule-type><option value="xp">XP</option><option value="bonus">Bonus</option><option value="penalty">Sancion</option><option value="tie_break">Desempate</option><option value="custom">Custom</option></select></label>
                        <label>Aplica a<select class="form-select" name="game_rule_scope" data-game-rule-scope><option value="event">Evento</option><option value="all_matches">Eventos y amistosos</option><option value="friendly">Duelos amistosos</option><option value="match">Duelo manual</option><option value="deck">Deck</option><option value="team">Equipo</option><option value="season">Temporada</option><option value="general">General</option></select></label>
                        <label>Prioridad<input class="form-control" type="number" min="1" name="game_rule_priority" data-game-rule-priority value="100"></label>
                        <label>Valor XP / puntos<input class="form-control" type="number" name="game_rule_points" data-game-rule-points value="0"></label>
                        <label class="span-2">Cuando ocurre<input class="form-control" name="game_rule_trigger" data-game-rule-trigger placeholder="Resultado de duelo validado"></label>
                        <label class="rule-active-field toggle-line"><input type="checkbox" name="game_rule_active" value="1" data-game-rule-active checked> Regla activa</label>
                        <label class="span-2">Efecto<textarea class="form-control" name="game_rule_effect" data-game-rule-effect rows="3" placeholder="Describe que hace la regla y como se calcula"></textarea></label>
                        <label>Notas<textarea class="form-control" name="game_rule_notes" data-game-rule-notes rows="3" placeholder="Restricciones, excepciones o referencias internas"></textarea></label>
                    </div>
                </section>
                <section class="rule-conditions-section">
                    <div class="panel-head compact-head">
                        <div><h2>Condiciones</h2><p>Define las condiciones que se aplican al validar los resultados.</p></div>
                        <button class="btn btn-outline-info btn-sm" type="button" data-rule-condition-add><i class="bi bi-plus-circle"></i> Agregar condicion</button>
                    </div>
                    <div class="table-responsive rule-condition-table-wrap">
                        <table class="table app-table compact rule-condition-table">
                            <thead>
                                <tr>
                                    <th>Condicion</th>
                                    <th>Operador</th>
                                    <th>Secuencia / valor</th>
                                    <th>Resultado</th>
                                    <th>Estado</th>
                                    <th>Motivo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-rule-condition-list></tbody>
                        </table>
                    </div>
                    <template data-rule-condition-template>
                        <tr class="rule-condition-row">
                            <td data-label="Condicion">
                                <strong data-condition-label-text>-</strong>
                                <small data-condition-type-text>-</small>
                            </td>
                            <td data-label="Operador" data-condition-operator-text>-</td>
                            <td data-label="Secuencia / valor"><code data-condition-target-text>-</code></td>
                            <td data-label="Resultado" data-condition-result-text>-</td>
                            <td data-label="Estado"><span class="status-chip" data-condition-status-text>-</span></td>
                            <td data-label="Motivo"><small data-condition-message-text>-</small></td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline-info btn-sm" type="button" data-condition-edit><i class="bi bi-pencil-square"></i></button>
                                    <button class="btn btn-outline-danger btn-sm" type="button" data-condition-remove><i class="bi bi-trash"></i></button>
                                </div>
                                <input type="hidden" name="condition_id[]" data-condition-id>
                                <input type="hidden" name="condition_type[]" data-condition-type>
                                <input type="hidden" name="condition_label[]" data-condition-label>
                                <input type="hidden" name="condition_operator[]" data-condition-operator>
                                <input type="hidden" name="condition_target[]" data-condition-target>
                                <input type="hidden" name="condition_result[]" data-condition-result>
                                <input type="hidden" name="condition_status[]" data-condition-status>
                                <input type="hidden" name="condition_score[]" data-condition-score>
                                <input type="hidden" name="condition_failure_message[]" data-condition-message>
                                <input type="hidden" name="condition_active[]" value="1" data-condition-active>
                            </td>
                        </tr>
                    </template>
                </section>
            </div>
            <div class="modal fade rule-condition-modal" tabindex="-1" data-rule-condition-modal aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div><h2 class="modal-title" data-condition-modal-title>Condicion de regla</h2><p>Edita una condicion sin perder de vista la tabla completa.</p></div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" data-condition-modal-row>
                            <div class="two-col">
                                <label>Tipo<select class="form-select" data-condition-modal-type><option value="result_case">Caso D1-D5</option><option value="victory">Victoria</option><option value="defeat">Derrota</option><option value="draw">Empate</option><option value="validation">Validacion</option><option value="score">Puntaje / XP</option><option value="availability">Disponibilidad</option><option value="custom">Custom</option></select></label>
                                <label>Condicion<input class="form-control" data-condition-modal-label placeholder="Ej: cierre D2 por victorias consecutivas"></label>
                            </div>
                            <div class="two-col">
                                <label>Operador<select class="form-select" data-condition-modal-operator><option value="sequence_equals">Secuencia exacta</option><option value="defined">Debe estar definido</option><option value="winner_in">Ganador en lista</option><option value="winner_equals">Ganador igual a</option><option value="score_min">Marcador minimo</option><option value="score_max">Marcador maximo</option><option value="points_value">Valor de puntos</option><option value="requires_previous">Requiere resultado previo</option><option value="blocks_when">Bloquea cuando</option><option value="custom">Custom</option></select></label>
                                <label>Secuencia / valor<input class="form-control" data-condition-modal-target placeholder="A,A / A,B,E,E / texto"></label>
                            </div>
                            <div class="three-col">
                                <label>Resultado<select class="form-select" data-condition-modal-result><option value="">Sin ganador</option><option value="A">Gana A</option><option value="B">Gana B</option><option value="E">Empate</option></select></label>
                                <label>Estado<select class="form-select" data-condition-modal-status><option value="FINALIZADA">Finalizada</option><option value="REQUIERE D5">Requiere D5</option><option value="EN CURSO">En curso</option><option value="PENDIENTE">Pendiente</option><option value="INVALID">Invalida</option></select></label>
                                <label>Marcador<input class="form-control" data-condition-modal-score placeholder="2-0 / 1-1 / -"></label>
                            </div>
                            <label>Mensaje de validacion<input class="form-control" data-condition-modal-message placeholder="Mensaje cuando no cumple"></label>
                            <label class="toggle-line mt-2"><input type="checkbox" data-condition-modal-active checked> Condicion activa</label>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary fw-bold" type="button" data-condition-modal-save><i class="bi bi-save"></i> Aplicar condicion</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rule-detail-actions">
                <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar regla</button>
                <button class="btn btn-outline-light" type="button" data-route="reglamento">Cancelar</button>
            </div>
        </form>
        <form method="post" class="rule-archive-form" data-game-rule-archive-form>
            <input type="hidden" name="action" value="archive_game_rule">
            <input type="hidden" name="game_rule_id" data-game-rule-archive-id>
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-archive"></i> Desactivar regla</button>
        </form>
    </section>

