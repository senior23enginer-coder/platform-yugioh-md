    <section class="screen" data-screen="resultados" hidden>
        <div class="page-title"><h1><?php echo e($uiText('route.resultados', 'title', 'Captura de resultados')); ?></h1><p><?php echo e($uiText('route.resultados', 'subtitle', 'D1-D5 con cierre automatico, validacion, auditoria y guardado en MySQL.')); ?></p></div>
        <section class="panel result-queue-panel">
            <div class="panel-head"><div><h2>Centro de partidas</h2><p>Busca, filtra y selecciona el combate que vas a capturar.</p></div><span class="status-chip" data-result-filter-count>0 visibles</span></div>
            <div class="result-filter-grid">
                <label class="result-filter-search"><span>Buscar</span><input class="form-control" data-result-search placeholder="Deck, equipo, ronda, mesa o fecha"></label>
                <label><span>Fase</span><select class="form-select" data-result-phase>
                    <option value="">Todas las fases</option>
                    <option value="qualifier">Clasificatorio</option>
                    <option value="league">Liga</option>
                    <option value="cup">Copa</option>
                    <option value="world">Mundial</option>
                    <option value="events">Eventos</option>
                    <option value="friendly">Amistoso</option>
                </select></label>
                <label><span>Evento activo</span><select class="form-select" data-result-event-filter aria-label="Filtrar por evento activo"><option value="">Todos los eventos</option></select></label>
                <label><span>División</span><select class="form-select" data-result-league-division aria-label="Filtrar por división de liga"><option value="">Todas las divisiones</option></select></label>
                <label><span>Estado</span><select class="form-select" data-result-status>
                    <option value="">Todo estado</option>
                    <option value="PENDIENTE">Pendiente</option>
                    <option value="EN CURSO">En curso</option>
                    <option value="FINALIZADA">Finalizada</option>
                </select></label>
                <label><span>Programación</span><select class="form-select" data-result-schedule-filter aria-label="Filtrar por programación"><option value="all">Toda la programación</option><option value="overdue">Vencido</option><option value="today">Hoy</option><option value="future">Fecha futura</option><option value="unscheduled">Sin fecha</option></select></label>
                <label><span>Ronda</span><select class="form-select" data-result-round-filter><option value="">Todas las rondas</option></select></label>
                <label><span>Orden</span><select class="form-select" data-result-sort aria-label="Ordenar resultados por fecha">
                    <option value="desc" selected>Fecha: mayor a menor</option>
                    <option value="asc">Fecha: menor a mayor</option>
                </select></label>
                <label><span>Periodo</span><select class="form-select" data-result-period aria-label="Periodo de consulta"><option value="all">Todos</option><option value="today">Hoy</option><option value="day">Por dia</option><option value="month">Por mes</option><option value="range">Rango de fechas</option></select></label>
                <label data-result-period-field="day" hidden><span>Dia (opcional)</span><input class="form-control" type="date" data-result-date aria-label="Filtrar por dia"></label>
                <label data-result-period-field="month" hidden><span>Mes (opcional)</span><input class="form-control" type="month" data-result-month aria-label="Filtrar por mes"></label>
                <label data-result-period-field="range" hidden><span>Desde (opcional)</span><input class="form-control" type="date" data-result-date-from aria-label="Fecha desde"></label>
                <label data-result-period-field="range" hidden><span>Hasta (opcional)</span><input class="form-control" type="date" data-result-date-to aria-label="Fecha hasta"></label>
                <p class="text-warning mb-0" data-result-period-error role="status" hidden>La fecha desde no puede ser posterior a la fecha hasta.</p>
                <button class="btn btn-outline-light result-filter-clear" type="button" data-result-clear-filters><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <div class="table-responsive result-queue-wrap">
                <table class="table app-table compact result-queue-table mobile-card-table">
                    <thead><tr><th>Programacion</th><th>Enfrentamiento</th><th>Estado</th><th>Marcador</th><th>Accion</th></tr></thead>
                    <tbody data-result-queue></tbody>
                </table>
            </div>
        </section>
        <section class="panel match-center mt-3" hidden>
            <form method="post">
                <input type="hidden" name="action" value="save_match_result">
                <input type="hidden" name="match_id" data-result-match-id>
                <div class="control-strip"><select class="form-select" data-result-division><option value="">Todas las categorias/fases</option><?php foreach ($divisions as $key => $division): ?><option value="<?php echo e($key); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select><select class="form-select" data-result-match></select><button class="btn btn-outline-light" type="button" data-clear-result><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button></div>
                <div class="match-scoreboard">
                    <div><span class="duelist-avatar">A</span><small>Jugador A</small><strong data-result-a></strong></div>
                    <b>VS</b>
                    <div><span class="duelist-avatar">B</span><small>Jugador B</small><strong data-result-b></strong></div>
                </div>
                <div class="match-body">
                    <div>
                        <div class="duel-grid my-3"><?php for ($i = 1; $i <= 5; $i++): ?><label><span>D<?php echo $i; ?></span><select class="form-select" name="d<?php echo $i; ?>" data-duel="<?php echo $i; ?>"><option value="">-</option><option value="A">A</option><option value="E">E</option><option value="B">B</option></select></label><?php endfor; ?></div>
                        <label class="w-100 mb-3"><span class="label">Notas de auditoria</span><input class="form-control" name="result_notes" placeholder="Correccion, mesa, observacion o juez"></label>
                        <div class="result-summary" data-result-summary>Sin captura</div>
                        <button class="btn btn-primary fw-bold mt-3" type="submit" data-result-save-button><i class="bi bi-database-check"></i> Guardar resultado</button>
                    </div>
                    <aside class="match-log"><h2>Linea de match</h2><ol><li>Selecciona categoria y partida</li><li>Captura D1-D5</li><li>El sistema valida cierre</li><li>Guarda auditoria</li></ol></aside>
                </div>
            </form>
        </section>
    </section>

