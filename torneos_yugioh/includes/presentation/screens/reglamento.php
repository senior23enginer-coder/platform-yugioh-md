    <section class="screen" data-screen="reglamento" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.reglamento', 'title', 'Reglas custom')); ?></h1><p><?php echo e($uiText('route.reglamento', 'subtitle', 'CRUD de reglas y condiciones que valida la plataforma al registrar resultados.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-rule-open=""><i class="bi bi-plus-circle"></i> Nueva regla</button>
        </div>
        <div class="module-kpi-strip rules-kpi-strip mb-3">
            <article><i class="bi bi-sliders"></i><span>Reglas BD</span><b><?php echo number_format(count($gameRules)); ?></b></article>
            <article><i class="bi bi-check-circle"></i><span>Activas</span><b><?php echo number_format($activeGameRuleCount); ?></b></article>
            <article><i class="bi bi-ui-checks-grid"></i><span>Condiciones</span><b><?php echo number_format(array_sum(array_map(static fn(array $rule): int => (int) ($rule['condition_count'] ?? 0), $gameRules))); ?></b></article>
            <article><i class="bi bi-diagram-3"></i><span>Eventos con regla</span><b><?php echo number_format(count(array_filter($competitions, static fn(array $competition): bool => !empty($competition['game_rule_id'])))); ?></b></article>
        </div>
        <section class="panel rules-crud-panel mb-3">
            <div class="panel-head">
                <div><h2>Reglas custom</h2><p>Primero consulta y filtra. Para crear, editar o quitar condiciones abre la ficha de la regla.</p></div>
                <span class="status-chip" data-rule-visible-count><?php echo count($gameRules); ?> reglas</span>
            </div>
            <div class="event-filter-row rule-filter-row">
                <label>Buscar<input class="form-control" type="search" data-rule-search placeholder="Nombre, codigo, tipo, efecto o condicion"></label>
                <label>Tipo<select class="form-select" data-rule-type-filter><option value="">Todos</option><option value="xp">XP</option><option value="bonus">Bonus</option><option value="penalty">Sancion</option><option value="tie_break">Desempate</option><option value="custom">Custom</option></select></label>
                <label>Estado<select class="form-select" data-rule-status-filter><option value="">Todos</option><option value="active">Activas</option><option value="inactive">Inactivas</option></select></label>
                <button class="btn btn-outline-light" type="button" data-rule-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <div class="table-responsive">
                <table class="table app-table compact rule-crud-table">
                    <thead><tr><th>Regla</th><th>Tipo</th><th>Aplica a</th><th>Condiciones</th><th>Puntos</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($gameRules as $rule): ?>
                        <?php
                            $ruleConditionsText = strtolower(implode(' ', array_map(static fn(array $condition): string => (string) (($condition['condition_label'] ?? '') . ' ' . ($condition['condition_type'] ?? '') . ' ' . ($condition['operator'] ?? '') . ' ' . ($condition['target_value'] ?? '')), (array) ($rule['conditions'] ?? []))));
                            $ruleSearch = strtolower((string) (($rule['name'] ?? '') . ' ' . ($rule['code'] ?? '') . ' ' . ($rule['rule_type'] ?? '') . ' ' . ($rule['scope'] ?? '') . ' ' . ($rule['effect'] ?? '') . ' ' . $ruleConditionsText));
                            $ruleStatus = !empty($rule['active']) ? 'active' : 'inactive';
                        ?>
                        <tr data-rule-row data-rule-id="<?php echo e((string) ($rule['id'] ?? '')); ?>" data-rule-type="<?php echo e((string) ($rule['rule_type'] ?? 'custom')); ?>" data-rule-status="<?php echo e($ruleStatus); ?>" data-rule-search="<?php echo e($ruleSearch); ?>">
                            <td data-label="Regla"><strong><?php echo e((string) ($rule['name'] ?? 'Regla')); ?></strong><br><small><?php echo e((string) ($rule['code'] ?? '')); ?></small></td>
                            <td data-label="Tipo"><?php echo e(strtoupper((string) ($rule['rule_type'] ?? 'custom'))); ?></td>
                            <td data-label="Aplica a"><?php echo e((string) (($rule['scope'] ?? '') ?: 'general')); ?></td>
                            <td data-label="Condiciones"><?php echo (int) ($rule['condition_count'] ?? 0); ?></td>
                            <td data-label="Puntos"><?php echo (int) ($rule['points_value'] ?? 0); ?></td>
                            <td data-label="Estado"><span class="status-chip <?php echo !empty($rule['active']) ? 'state-active' : 'state-cancelled'; ?>"><?php echo !empty($rule['active']) ? 'Activa' : 'Inactiva'; ?></span></td>
                            <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-rule-open="<?php echo e((string) ($rule['id'] ?? '')); ?>"><i class="bi bi-pencil-square"></i> Abrir ficha</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$gameRules): ?><tr><td colspan="7"><div class="empty-state">No hay reglas registradas en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

