    <section class="screen" data-screen="equipos" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.equipos', 'title', 'Equipos')); ?></h1><p><?php echo e($uiText('route.equipos', 'subtitle', 'Listado y filtros de equipos guardados. La ficha se abre en una pantalla aparte.')); ?></p></div>
            <button class="btn btn-primary" type="button" data-open-team-sheet=""><i class="bi bi-shield-plus"></i> Crear equipo</button>
        </div>
        <section class="panel">
            <div class="panel-head"><div><h2>Equipos creados</h2><p>Tabla principal para buscar, filtrar y abrir la ficha de equipo.</p></div><span class="status-chip"><?php echo count($teams); ?> activos</span></div>
            <div class="team-list-filters mb-3">
                <label>Buscar<input class="form-control" type="search" placeholder="Equipo, codigo o deck..." data-team-list-search></label>
                <label>Formato<select class="form-select" data-team-list-format><option value="">Todos los formatos</option><?php foreach ($eventRulesetOptions as $code => $label): ?><option value="<?php echo e((string) $code); ?>"><?php echo e((string) $label); ?></option><?php endforeach; ?></select></label>
                <label>Division<select class="form-select" data-team-list-division><option value="">Todas las divisiones</option><?php foreach ($divisions as $code => $division): ?><option value="<?php echo e((string) $code); ?>"><?php echo e((string) ($division['name'] ?? $code)); ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-outline-light" type="button" data-team-list-clear><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button>
            </div>
            <div class="table-responsive">
                <table class="table app-table entity-table mobile-card-table team-list-table">
                    <thead><tr><th>Equipo</th><th>Codigo</th><th>Formatos</th><th>Division</th><th>Decks</th><th>Partidas</th><th>Partidas G/E/P</th><th>Duelos G/E/P</th><th>Winrate</th><th>Acciones</th></tr></thead>
                    <tbody>
            <?php foreach ($teams as $team): ?>
                <?php
                $teamDecks = array_values(array_filter($allDecks, static fn(array $deck): bool => (string) ($deck['team_id'] ?? '') === (string) ($team['id'] ?? '')));
                $primary = (string) (($team['primary_color'] ?? '') ?: '#36d8ff');
                $secondary = (string) (($team['secondary_color'] ?? '') ?: '#ffd25f');
                $teamSymbol = (string) (($team['symbol'] ?? '') ?: ($team['short_code'] ?? '') ?: substr((string) $team['name'], 0, 2));
                ?>
                <tr data-team-list-row data-team-id="<?php echo e((string) ($team['id'] ?? '')); ?>" data-search="<?php echo e(strtolower((string) (($team['name'] ?? '') . ' ' . ($team['short_code'] ?? '') . ' ' . implode(' ', array_column($teamDecks, 'name'))))); ?>" style="--team-primary: <?php echo e($primary); ?>; --team-secondary: <?php echo e($secondary); ?>">
                    <td data-label="Equipo">
                        <span class="team-table-identity">
                            <?php if (!empty($team['logo_url'])): ?>
                                <span class="team-table-logo"><img src="<?php echo e((string) $team['logo_url']); ?>" alt="<?php echo e((string) $team['name']); ?>"></span>
                            <?php else: ?>
                                <span class="team-inline-badge" style="--team-primary: <?php echo e($primary); ?>; --team-secondary: <?php echo e($secondary); ?>"><?php echo e($teamSymbol); ?></span>
                            <?php endif; ?>
                            <strong><?php echo e((string) $team['name']); ?></strong>
                        </span>
                    </td>
                    <td data-label="Codigo"><?php echo e((string) (($team['short_code'] ?? '') ?: 'YMD')); ?></td>
                    <td data-label="Formatos" data-team-list-formats>Sin formato</td>
                    <td data-label="Division" data-team-list-divisions>Sin division</td>
                    <td data-label="Decks"><?php echo count($teamDecks); ?></td>
                    <td data-label="Partidas" data-team-list-matches>0</td>
                    <td data-label="Partidas G/E/P" data-team-list-match-record>0 / 0 / 0</td>
                    <td data-label="Duelos G/E/P" data-team-list-duel-record>0 / 0 / 0</td>
                    <td data-label="Winrate" data-team-list-winrate>0%</td>
                    <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-open-team-sheet="<?php echo e((string) ($team['id'] ?? '')); ?>"><i class="bi bi-shield-check"></i> Ver ficha</button></td>
                </tr>
            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

