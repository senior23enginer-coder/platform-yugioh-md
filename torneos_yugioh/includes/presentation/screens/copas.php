    <section class="screen" data-screen="copas" hidden>
        <div class="page-title with-actions">
            <div><h1><?php echo e($uiText('route.copas', 'title', 'Copas')); ?></h1><p><?php echo e($uiText('route.copas', 'subtitle', 'Consulta solo copas y finales persistidas en base de datos, con su llave real y fechas programadas.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear copa</button>
        </div>
        <div class="cup-db-stats">
            <article><span>Copas en BD</span><b><?php echo number_format(count($cupCompetitions)); ?></b></article>
            <article><span>Activas</span><b><?php echo number_format($cupActiveCount); ?></b></article>
            <article><span>Combates</span><b><?php echo number_format($cupTotalMatches); ?></b></article>
            <article><span>Finalizadas/anuladas</span><b><?php echo number_format($cupCompletedCount); ?></b></article>
        </div>
        <section class="panel event-filter-panel cup-filter-panel">
            <details class="mobile-collapsible" open>
                <summary><span><i class="bi bi-funnel"></i> Filtros de copas</span><i class="bi bi-chevron-down"></i></summary>
                <div class="event-filter-row">
                    <label>Buscar<input class="form-control" type="search" data-cup-search placeholder="Nombre, codigo, formato o estado"></label>
                    <label>Estado<select class="form-select" data-cup-status-filter><option value="">Todos</option><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                    <label>Reglamento<select class="form-select" data-cup-ruleset-filter><option value="">Todos</option><?php foreach ($rulesetSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></select></label>
                    <button class="btn btn-outline-light" type="button" data-cup-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
                </div>
            </details>
        </section>
        <section class="panel mt-3">
            <div class="panel-head"><div><h2>Tabla de copas</h2><p>Consulta, filtra y abre la ficha de cada copa persistida.</p></div><span class="status-chip" data-cup-visible-count><?php echo count($cupCompetitions); ?> copas</span></div>
            <div class="table-responsive">
                <table class="table app-table compact cup-sort-table" data-cup-sort-table>
                    <thead><tr><th><button type="button" data-cup-sort="name">Copa <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="status">Estado <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="ruleset">Reglamento <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="start">Inicio <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="decks">Decks <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="matches">Combates <i class="bi bi-arrow-down-up"></i></button></th><th>Acciones</th></tr></thead>
                    <tbody>
            <?php foreach ($cupCompetitions as $cupCompetition): ?>
                <?php
                    $cupId = (int) ($cupCompetition['id'] ?? 0);
                    $cupType = (string) (($cupCompetition['type'] ?? '') ?: 'cup');
                    $cupAccent = (string) (($competitionTypeOptions[$cupType]['accent'] ?? '') ?: '#ff2bd6');
                    $cupStatusClass = ygo_event_status_class((string) ($cupCompetition['status'] ?? 'pending_configuration'));
                    $cupStatusLabel = ygo_event_status_label((string) ($cupCompetition['status'] ?? 'pending_configuration'));
                    $cupRuleset = (string) (($rulesetSelectOptions[(string) ($cupCompetition['event_ruleset'] ?? '')] ?? '') ?: (($cupCompetition['event_ruleset'] ?? '') ?: 'Sin reglamento'));
                    $cupStats = $cupStatsByCompetition[$cupId] ?? ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0, 'dates' => []];
                    $cupStatusKey = ygo_normalize_status_key((string) ($cupCompetition['status'] ?? 'pending_configuration'));
                    $cupSearch = strtolower((string) (($cupCompetition['name'] ?? '') . ' ' . ($cupCompetition['code'] ?? '') . ' ' . ($cupCompetition['event_ruleset'] ?? '') . ' ' . $cupStatusLabel));
                ?>
                <tr class="<?php echo e($cupStatusClass); ?>" style="--event-accent: <?php echo e($cupAccent); ?>; --accent: <?php echo e($cupAccent); ?>;" data-cup-row data-cup-status="<?php echo e($cupStatusKey); ?>" data-cup-ruleset="<?php echo e((string) ($cupCompetition['event_ruleset'] ?? '')); ?>" data-cup-search="<?php echo e($cupSearch); ?>" data-sort-name="<?php echo e(strtolower((string) ($cupCompetition['name'] ?? ''))); ?>" data-sort-status="<?php echo e($cupStatusKey); ?>" data-sort-ruleset="<?php echo e(strtolower($cupRuleset)); ?>" data-sort-start="<?php echo e((string) (($cupCompetition['starts_on'] ?? '') ?: '9999-12-31')); ?>" data-sort-decks="<?php echo e((string) ((int) ($cupCompetition['deck_pool_count'] ?? 0))); ?>" data-sort-matches="<?php echo e((string) ((int) ($cupStats['total'] ?? 0))); ?>">
                    <td data-label="Copa"><div class="event-table-title"><span class="event-table-logo"><?php if (!empty($cupCompetition['logo_url'])): ?><img src="<?php echo e((string) $cupCompetition['logo_url']); ?>" alt="<?php echo e((string) ($cupCompetition['name'] ?? 'Copa')); ?>"><?php else: ?><i class="bi bi-award"></i><?php endif; ?></span><span><strong><?php echo e((string) ($cupCompetition['name'] ?? 'Copa')); ?></strong><br><small><?php echo e((string) ($cupCompetition['code'] ?? '')); ?></small></span></div></td>
                    <td data-label="Estado"><span class="status-chip <?php echo e($cupStatusClass); ?>"><?php echo e($cupStatusLabel); ?></span></td>
                    <td data-label="Reglamento"><?php echo e($cupRuleset); ?><br><small><?php echo e((string) (($cupCompetition['event_ruleset'] ?? '') ?: 'custom')); ?></small></td>
                    <td data-label="Inicio"><?php echo e((string) (($cupCompetition['starts_on'] ?? '') ?: 'Sin fecha')); ?></td>
                    <td data-label="Decks"><?php echo e((string) ((int) ($cupCompetition['deck_pool_count'] ?? 0))); ?></td>
                    <td data-label="Combates"><?php echo e((string) ((int) ($cupStats['total'] ?? 0))); ?></td>
                    <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-event-open="<?php echo e((string) $cupId); ?>"><i class="bi bi-card-checklist"></i> Abrir ficha</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cupCompetitions): ?><tr><td colspan="7"><div class="empty-state">No hay copas ni finales registradas en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

