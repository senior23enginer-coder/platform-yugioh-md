    <section class="screen" data-screen="reglas" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.reglas', 'title', 'Reglas especiales')); ?></h1><p><?php echo e($uiText('route.reglas', 'subtitle', 'CRUD de reglas custom para juegos, experiencia, bonus, sanciones y condiciones operativas.')); ?></p></div>
            <button class="btn btn-outline-light" type="button" data-route="reglamento"><i class="bi bi-journal-check"></i> Ver reglamentos</button>
        </div>
        <div class="module-kpi-strip rules-kpi-strip mb-3">
            <article><i class="bi bi-sliders"></i><span>Reglas BD</span><b><?php echo number_format(count($gameRules)); ?></b></article>
            <article><i class="bi bi-check-circle"></i><span>Activas</span><b><?php echo number_format($activeGameRuleCount); ?></b></article>
            <article><i class="bi bi-pause-circle"></i><span>Inactivas</span><b><?php echo number_format(max(0, count($gameRules) - $activeGameRuleCount)); ?></b></article>
            <article><i class="bi bi-diagram-3"></i><span>Eventos</span><b><?php echo number_format(count($competitions)); ?></b></article>
        </div>
        <div class="game-rules-layout">
            <section class="panel game-rule-editor">
                <div class="panel-head"><div><h2>Configurar regla</h2><p>Las reglas se guardan en base de datos y pueden aplicarse a eventos o juegos.</p></div><span class="status-chip"><?php echo count($gameRules); ?> reglas</span></div>
                <form method="post" class="gestion-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_game_rule">
                    <input type="hidden" name="game_rule_id" data-game-rule-id>
                    <label>Editar regla<select class="form-select" data-game-rule-select><option value="">Nueva regla</option><?php foreach ($gameRules as $rule): ?><option value="<?php echo e((string) $rule['id']); ?>"><?php echo e((string) $rule['name']); ?><?php echo empty($rule['active']) ? ' (inactiva)' : ''; ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Codigo<input class="form-control" name="game_rule_code" data-game-rule-code placeholder="CUSTOM_XP"></label>
                        <label>Nombre<input class="form-control" name="game_rule_name" data-game-rule-name required placeholder="CUSTOM XP"></label>
                    </div>
                    <div class="three-col">
                        <label>Tipo<select class="form-select" name="game_rule_type" data-game-rule-type><option value="xp">XP</option><option value="bonus">Bonus</option><option value="penalty">Sancion</option><option value="tie_break">Desempate</option><option value="custom">Custom</option></select></label>
                        <label>Aplica a<select class="form-select" name="game_rule_scope" data-game-rule-scope><option value="event">Evento</option><option value="all_matches">Eventos y amistosos</option><option value="friendly">Duelos amistosos</option><option value="match">Duelo manual</option><option value="deck">Deck</option><option value="team">Equipo</option><option value="season">Temporada</option><option value="general">General</option></select></label>
                        <label>Prioridad<input class="form-control" type="number" min="1" name="game_rule_priority" data-game-rule-priority value="100"></label>
                    </div>
                    <label>Cuando ocurre<input class="form-control" name="game_rule_trigger" data-game-rule-trigger placeholder="Resultado de duelo validado"></label>
                    <label>Efecto<textarea class="form-control" name="game_rule_effect" data-game-rule-effect rows="4" placeholder="Describe que hace la regla y como se calcula"></textarea></label>
                    <div class="two-col">
                        <label>Valor XP / puntos<input class="form-control" type="number" name="game_rule_points" data-game-rule-points value="0"></label>
                        <label class="toggle-line"><input type="checkbox" name="game_rule_active" value="1" data-game-rule-active checked> Regla activa</label>
                    </div>
                    <label>Notas<textarea class="form-control" name="game_rule_notes" data-game-rule-notes rows="3" placeholder="Restricciones, excepciones o referencias internas"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar regla</button>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="archive_game_rule">
                    <input type="hidden" name="game_rule_id" data-game-rule-archive-id>
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-archive"></i> Desactivar regla seleccionada</button>
                </form>
            </section>
            <section class="panel game-rule-list-panel">
                <div class="panel-head"><div><h2>Reglas guardadas</h2><p>Consulta las reglas competitivas configuradas.</p></div></div>
                <div class="game-rule-cards">
                    <?php foreach ($gameRules as $rule): ?>
                        <article class="<?php echo !empty($rule['active']) ? 'active' : 'inactive'; ?>">
                            <div><strong><?php echo e((string) $rule['name']); ?></strong><small><?php echo e((string) $rule['code']); ?></small></div>
                            <span><?php echo e(strtoupper((string) $rule['rule_type'])); ?> | <?php echo e((string) $rule['scope']); ?></span>
                            <p><?php echo e((string) (($rule['effect'] ?? '') ?: 'Sin efecto configurado.')); ?></p>
                            <footer><em><?php echo !empty($rule['active']) ? 'Activa' : 'Inactiva'; ?></em><b><?php echo (int) ($rule['points_value'] ?? 0); ?> XP</b></footer>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$gameRules): ?><p class="note">No hay reglas registradas en BD.</p><?php endif; ?>
                </div>
            </section>
        </div>
    </section>

