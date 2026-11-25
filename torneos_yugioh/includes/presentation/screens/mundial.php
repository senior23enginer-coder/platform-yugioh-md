    <section class="screen" data-screen="mundial" hidden>
        <div class="page-title with-actions">
            <div><h1><?php echo e($uiText('route.mundial', 'title', 'Mundial')); ?></h1><p><?php echo e($uiText('route.mundial', 'subtitle', 'Eventos tipo Mundial registrados en base de datos, con estado, decks y combates asociados.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear mundial</button>
        </div>
        <div class="cup-db-stats world-db-stats">
            <article><span>Mundiales en BD</span><b><?php echo number_format(count($worldCompetitions)); ?></b></article>
            <article><span>Activos</span><b><?php echo number_format($worldActiveCount); ?></b></article>
            <article><span>Combates</span><b><?php echo number_format($worldTotalMatches); ?></b></article>
            <article><span>Decks registrados</span><b><?php echo number_format(array_sum(array_map(static fn(array $competition): int => (int) ($competition['deck_pool_count'] ?? 0), $worldCompetitions))); ?></b></article>
        </div>
        <section class="panel mt-3">
            <div class="panel-head"><div><h2>Tabla de mundiales</h2><p>Consulta y entra a la ficha de cada Mundial persistido.</p></div><span class="status-chip"><?php echo count($worldCompetitions); ?> mundiales</span></div>
            <div class="table-responsive">
                <table class="table app-table compact event-sort-table">
                    <thead><tr><th>Evento</th><th>Estado</th><th>Reglamento</th><th>Inicio</th><th>Decks</th><th>Combates</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($worldCompetitions as $worldCompetition): ?>
                        <?php
                        $worldId = (int) ($worldCompetition['id'] ?? 0);
                        $worldStatusClass = ygo_event_status_class((string) ($worldCompetition['status'] ?? 'pending_configuration'));
                        $worldRuleset = (string) (($rulesetSelectOptions[(string) ($worldCompetition['event_ruleset'] ?? '')] ?? '') ?: (($worldCompetition['event_ruleset'] ?? '') ?: 'Sin reglamento'));
                        $worldStats = $worldStatsByCompetition[$worldId] ?? ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0];
                        ?>
                        <tr class="<?php echo e($worldStatusClass); ?>" style="--event-accent: <?php echo e((string) ($competitionTypeOptions['world']['accent'] ?? '#9b5cff')); ?>;">
                            <td data-label="Evento"><div class="event-table-title"><span class="event-table-logo"><?php if (!empty($worldCompetition['logo_url'])): ?><img loading="lazy" decoding="async" src="<?php echo e((string) $worldCompetition['logo_url']); ?>" alt="<?php echo e((string) ($worldCompetition['name'] ?? 'Mundial')); ?>"><?php else: ?><i class="bi bi-globe-americas"></i><?php endif; ?></span><span><strong><?php echo e((string) ($worldCompetition['name'] ?? 'Mundial')); ?></strong><br><small><?php echo e((string) ($worldCompetition['code'] ?? '')); ?></small></span></div></td>
                            <td data-label="Estado"><span class="status-chip <?php echo e($worldStatusClass); ?>"><?php echo e(ygo_event_status_label((string) ($worldCompetition['status'] ?? 'pending_configuration'))); ?></span></td>
                            <td data-label="Reglamento"><?php echo e($worldRuleset); ?></td>
                            <td data-label="Inicio"><?php echo e((string) (($worldCompetition['starts_on'] ?? '') ?: 'Sin fecha')); ?></td>
                            <td data-label="Decks"><?php echo e((string) ((int) ($worldCompetition['deck_pool_count'] ?? 0))); ?></td>
                            <td data-label="Combates"><?php echo e((string) ((int) ($worldStats['total'] ?? 0))); ?></td>
                            <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-event-open="<?php echo e((string) $worldId); ?>"><i class="bi bi-card-checklist"></i> Abrir ficha</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$worldCompetitions): ?><tr><td colspan="7"><div class="empty-state">No hay eventos tipo Mundial registrados en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

