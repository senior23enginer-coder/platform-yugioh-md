    <section class="screen" data-screen="torneos" hidden>
        <div class="page-title with-actions module-title"><div><h1><?php echo e($uiText('route.torneos', 'title', 'Eventos competitivos')); ?></h1><p><?php echo e($uiText('route.torneos', 'subtitle', 'Administra eventos desde la tabla principal.')); ?></p></div><button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear evento</button></div>
        <p class="alert alert-warning" role="alert" data-event-open-error hidden></p>
        <nav class="event-module-navigation" aria-label="Modulos de eventos competitivos">
            <button class="btn btn-outline-info" type="button" data-route="torneos" data-event-type-shortcut="league"><i class="bi <?php echo e($routes['ligas'][1]); ?>"></i> <span><?php echo e($routes['ligas'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="torneos" data-event-type-shortcut="tournament"><i class="bi bi-trophy"></i> <span>Torneos</span></button>
            <button class="btn btn-outline-info" type="button" data-route="torneos" data-event-type-shortcut="special"><i class="bi <?php echo e($routes['eventos_especiales'][1]); ?>"></i> <span><?php echo e($routes['eventos_especiales'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="clasificatorio"><i class="bi <?php echo e($routes['clasificatorio'][1]); ?>"></i> <span><?php echo e($routes['clasificatorio'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="copas"><i class="bi <?php echo e($routes['copas'][1]); ?>"></i> <span><?php echo e($routes['copas'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="mundial"><i class="bi <?php echo e($routes['mundial'][1]); ?>"></i> <span><?php echo e($routes['mundial'][0]); ?></span></button>
            <button class="btn btn-outline-info fw-bold" type="button" data-route="divisiones"><i class="bi bi-diagram-3"></i> Divisiones</button>
        </nav>
        <section class="panel event-filter-panel mt-3">
            <details class="mobile-collapsible event-filter-shell" open>
                <summary><span><i class="bi bi-funnel"></i> Filtros de busqueda</span><i class="bi bi-chevron-down"></i></summary>
            <div class="event-search-filters">
                <label>Buscar evento<input class="form-control" type="search" placeholder="Nombre, codigo, formato, regla o banlist" data-event-search-filter></label>
                <label>Tipo<select class="form-select" data-event-type-filter><option value="">Todos</option><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select></label>
                <label>Estado<select class="form-select" data-event-status-filter><option value="">Todos</option><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                <label>Formato<select class="form-select" data-event-division-filter><option value="">Todos</option><option value="division:General">General</option><optgroup label="Formatos disponibles"><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="ruleset:<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></optgroup><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="division:<?php echo e((string) (($division['code'] ?? '') ?: ($division['short'] ?? ''))); ?>"><?php echo e((string) (($division['short'] ?? '') ?: ($division['code'] ?? 'Formato'))); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
                <button class="btn btn-outline-light" type="button" data-event-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            </details>
        </section>
        <section class="panel mt-3">
            <div class="panel-head"><div><h2>Tabla de eventos</h2><p>Consulta general de estado, formato, equipos y programacion persistida.</p></div><span class="status-chip"><?php echo count($competitions); ?> eventos</span></div>
            <div class="table-responsive">
                <table class="table app-table compact event-sort-table" data-event-sort-table>
                    <thead><tr><th><button type="button" data-event-sort="name">Evento <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="type">Tipo <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="status">Estado <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="division">Formato <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="match">Match <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="start">Inicio <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="progress">Progreso <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="decks">Divisiones / equipos <i class="bi bi-arrow-down-up"></i></button></th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($competitions as $competition): ?>
                        <?php
                        $tableType = (string) ($competition['type'] ?? 'special');
                        $tableStatus = (string) ($competition['status'] ?? 'pending_configuration');
                        $tableStatusKey = ygo_normalize_status_key($tableStatus);
                        $tableStatusLabels = ygo_event_status_labels() + ['draft' => 'Pendiente configuracion', 'finalized' => 'Finalizado'];
                        $tableStatusClass = ygo_event_status_class($tableStatus);
                        $tableDivision = (string) (($competition['division_code'] ?? '') ?: 'General');
                        $tableRuleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
                        $tableFormat = (string) ($rulesetSelectOptions[$tableRuleset] ?? $eventRulesetOptions[$tableRuleset] ?? 'Por definir');
                        $tableProgress = $competition['progress'] ?? ['done'=>0,'total'=>0,'round'=>null,'ratio'=>0];
                        $tableAccent = (string) (($competitionTypeOptions[$tableType]['accent'] ?? '') ?: '#00d9ff');
                        $tableTeamCount = (int) ($competition['participant_team_count'] ?? 0);
                        $tableStructureCount = $tableType === 'league' ? (int) ($competition['division_count'] ?? 0) : $tableTeamCount;
                        $tableStructureLabel = $tableType === 'league'
                            ? $tableStructureCount . ($tableStructureCount === 1 ? ' división' : ' divisiones')
                            : $tableTeamCount . ($tableTeamCount === 1 ? ' equipo' : ' equipos');
                        ?>
                        <tr class="<?php echo e($tableStatusClass); ?>" style="--event-accent: <?php echo e($tableAccent); ?>; --accent: <?php echo e($tableAccent); ?>;" data-event-card data-event-season="<?php echo (int) $competition['season_id']; ?>" data-event-type="<?php echo e($tableType); ?>" data-event-ruleset="<?php echo e((string) ($competition['event_ruleset'] ?? 'custom')); ?>" data-event-version="<?php echo e((string) ($competition['ruleset_version'] ?? '')); ?>" data-event-status="<?php echo e($tableStatusKey); ?>" data-event-division="<?php echo e($tableDivision); ?>" data-sort-name="<?php echo e(strtolower((string) ($competition['name'] ?? ''))); ?>" data-sort-type="<?php echo e(strtolower((string) (($competitionTypeOptions[$tableType]['label'] ?? '') ?: $tableType))); ?>" data-sort-status="<?php echo e($tableStatusKey); ?>" data-sort-division="<?php echo e(strtolower($tableFormat)); ?>" data-sort-match="<?php echo e(strtolower((string) (($competition['match_format'] ?? '') ?: '1v1'))); ?>" data-sort-start="<?php echo e((string) (($competition['starts_on'] ?? '') ?: '9999-12-31')); ?>" data-sort-progress="<?php echo e((string)$tableProgress['ratio']); ?>" data-sort-decks="<?php echo e((string) $tableStructureCount); ?>" data-event-search="<?php echo e(strtolower((string) (($competition['name'] ?? '') . ' ' . $tableFormat . ' ' . ($competition['code'] ?? '') . ' ' . ($competition['division_code'] ?? '') . ' ' . ($competition['event_ruleset'] ?? '') . ' ' . ($competition['ruleset_version'] ?? '') . ' ' . ygo_event_status_label($tableStatus)))); ?>">
                            <td data-label="Evento">
                                <div class="event-table-title">
                                    <span class="event-table-logo"><?php if (!empty($competition['logo_url'])): ?><img loading="lazy" decoding="async" src="<?php echo e((string) $competition['logo_url']); ?>" alt="<?php echo e((string) ($competition['name'] ?? 'Evento')); ?>"><?php else: ?><i class="bi bi-trophy"></i><?php endif; ?></span>
                                    <span><strong><?php echo e((string) ($competition['name'] ?? 'Evento')); ?></strong><br><small><?php echo e((string) ($competition['code'] ?? '')); ?></small></span>
                                </div>
                            </td>
                            <td data-label="Tipo"><?php echo e((string) (($competitionTypeOptions[$tableType]['label'] ?? '') ?: $tableType)); ?></td>
                            <td data-label="Estado"><span class="status-chip <?php echo e($tableStatusClass); ?>"><?php echo e($tableStatusLabels[$tableStatus] ?? ygo_event_status_label($tableStatus)); ?></span></td>
                            <td data-label="Formato"><?php echo e($tableFormat); ?></td>
                            <td data-label="Match"><?php echo e(strtoupper((string) (($competition['match_format'] ?? '') ?: '1v1'))); ?></td>
                            <td data-label="Inicio"><?php echo e((string) (($competition['starts_on'] ?? '') ?: 'Sin fecha')); ?></td>
                            <td data-label="Progreso"><strong><?php echo (int)$tableProgress['done']; ?>/<?php echo (int)$tableProgress['total']; ?></strong><small class="d-block"><?php echo $tableProgress['round'] !== null ? 'Ronda ' . (int)$tableProgress['round'] . ' | ' : ''; ?><?php echo (int)$tableProgress['total'] > 0 ? 'combates completados' : 'Sin combates programados'; ?></small></td>
                            <td data-label="<?php echo $tableType === 'league' ? 'Divisiones' : 'Equipos'; ?>"><?php echo e($tableStructureLabel); ?></td>
                            <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-event-open="<?php echo e((string) ($competition['id'] ?? '')); ?>"><i class="bi bi-eye"></i> Ver</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

