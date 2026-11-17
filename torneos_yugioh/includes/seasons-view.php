<?php $seasonRows = $appData['seasons'] ?? []; ?>
<section class="screen" data-screen="temporadas" hidden>
    <div class="page-title with-actions module-title">
        <div><h1>Temporadas</h1><p>Organiza los eventos por ciclos anuales, semestrales o trimestrales.</p></div>
        <button class="btn btn-primary fw-bold" type="button" data-season-new><i class="bi bi-plus-circle"></i> Crear temporada</button>
    </div>
    <section class="panel">
        <div class="panel-head"><div><h2>Temporadas registradas</h2><p>Al finalizar una temporada se cierran sus eventos y se conserva el historial.</p></div><span class="status-chip"><?php echo count($seasonRows); ?> <?php echo count($seasonRows) === 1 ? 'temporada' : 'temporadas'; ?></span></div>
        <div class="season-filters">
            <label>Buscar<input class="form-control" type="search" data-season-search placeholder="Nombre de la temporada"></label>
            <label>Estado<select class="form-select" data-season-status-filter><option value="">Todos</option><option value="draft">Borrador</option><option value="active">Activa</option><option value="completed">Finalizada</option><option value="archived">Archivada</option></select></label>
        </div>
        <div class="table-responsive">
            <table class="table app-table entity-table mobile-card-table seasons-table">
                <thead><tr><th>Temporada</th><th>Ciclo</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Eventos</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($seasonRows as $season): ?>
                    <?php $seasonClosed = in_array($season['status'], ['completed','archived'], true); ?>
                    <tr data-season-row="<?php echo (int) $season['id']; ?>" data-name="<?php echo e($season['name']); ?>" data-status="<?php echo e($season['status']); ?>">
                        <td data-label="Temporada"><strong><?php echo e($season['name']); ?></strong></td>
                        <td data-label="Ciclo"><?php echo e(ygo_season_periods()[$season['period_type']][0] ?? 'Anual'); ?></td>
                        <td data-label="Inicio"><?php echo e((string) $season['starts_on']); ?></td>
                        <td data-label="Fin"><?php echo e((string) $season['ends_on']); ?></td>
                        <td data-label="Estado"><span class="status-chip <?php echo $seasonClosed ? 'state-completed' : ($season['status'] === 'active' ? 'state-active' : ''); ?>"><?php echo e(ygo_season_status_label($season['status'])); ?></span></td>
                        <td data-label="Eventos"><?php echo (int) $season['event_count']; ?></td>
                        <td data-label="Acciones"><div class="season-actions">
                            <button class="btn btn-outline-info btn-sm" type="button" data-season-open="<?php echo (int) $season['id']; ?>">Ver ficha</button>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="note">Las temporadas con eventos o divisiones se conservan para consultar su historial. Las temporadas vacías se pueden eliminar.</p>
    </section>
    <div class="modal fade dml-modal" id="seasonEditorModal" tabindex="-1" aria-labelledby="seasonEditorTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <form method="post" class="gestion-form d-flex flex-column overflow-hidden" style="min-height:0" data-season-form>
                <input type="hidden" name="action" value="save_season"><input type="hidden" name="season_id" value="0">
                <div class="modal-header"><h2 class="modal-title" id="seasonEditorTitle">Crear temporada</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body">
                    <label>Nombre<input class="form-control" name="season_name" maxlength="120" required placeholder="Ej.: Temporada 2027"></label>
                    <div class="two-col">
                        <label>Ciclo<select class="form-select" name="season_period" required><?php foreach (ygo_season_periods() as $code=>$period): ?><option value="<?php echo e($code); ?>"><?php echo e($period[0]); ?></option><?php endforeach; ?></select></label>
                        <label>Estado<select class="form-select" name="season_status"><option value="draft">Borrador</option><option value="active">Activa</option></select></label>
                        <label>Fecha de inicio<input class="form-control" type="date" name="season_starts_on" required></label>
                        <label>Fecha de cierre<input class="form-control" type="date" name="season_ends_on" required></label>
                    </div>
                    <p class="note">El ciclo propone una fecha de cierre que puedes ajustar. Al finalizar la temporada se cierran sus eventos y se conserva el historial.</p>
                    <div class="season-detail-summary" data-season-detail hidden></div>
                    <section data-season-events hidden class="mt-4">
                        <div class="panel-head compact-head"><div><h3>Eventos de esta temporada</h3><p data-season-events-summary></p></div></div>
                        <div class="table-responsive"><table class="table app-table mobile-card-table"><thead><tr><th>Evento</th><th>Tipo</th><th>Estado</th><th>Inicio</th><th>Cierre programado</th><th>Cierre registrado</th><th>Acciones</th></tr></thead><tbody data-season-events-rows></tbody></table></div>
                    </section>
                </div>
                <div class="modal-footer"><button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-outline-danger" type="button" data-season-delete hidden>Eliminar temporada</button><button class="btn btn-outline-warning" type="submit" name="season_finalize" value="1" data-season-finalize hidden>Finalizar temporada</button><button class="btn btn-primary" type="submit" data-season-save>Guardar temporada</button></div>
            </form>
            <form method="post" data-season-delete-form hidden><input type="hidden" name="action" value="delete_season"><input type="hidden" name="season_id" value="0"></form>
        </div></div>
    </div>
</section>
