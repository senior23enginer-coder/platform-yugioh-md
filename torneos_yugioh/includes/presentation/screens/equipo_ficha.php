    <section class="screen" data-screen="equipo_ficha" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.equipo_ficha', 'title', 'Ficha del equipo')); ?></h1><p>Identidad, decks, rendimiento competitivo y palmares del equipo.</p></div>
            <div class="profile-header-actions">
                <a class="btn btn-outline-info" data-team-export-pdf href="#"><i class="bi bi-file-earmark-pdf"></i> Informe PDF</a>
                <button class="btn btn-outline-info" type="button" data-share-team-profile><i class="bi bi-share"></i> Compartir</button>
                <button class="btn btn-outline-info" type="button" data-team-calendar><i class="bi bi-calendar3"></i> Calendario</button>
                <button class="btn btn-outline-light" type="button" data-route="equipos"><i class="bi bi-arrow-left"></i> Volver a equipos</button>
            </div>
        </div>
        <section class="panel">
            <div class="panel-head"><div><h2>Datos del equipo</h2><p>Ficha editable separada del listado principal.</p></div><span class="status-chip">CRUD equipo</span></div>
            <form method="post" class="gestion-form team-sheet-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_team">
                <input type="hidden" name="team_id" data-gestion-team-id>
                <label>Editar equipo existente<select class="form-select" data-gestion-team-select><option value="">Nuevo equipo</option><?php foreach ($teams as $team): ?><option value="<?php echo e((string) $team['id']); ?>"><?php echo e((string) $team['name']); ?></option><?php endforeach; ?></select></label>
                <div class="team-sheet-layout">
                    <div class="team-logo-preview" data-team-logo-preview><i class="bi bi-shield"></i></div>
                    <div class="team-fields-grid">
                        <label>Nombre del equipo<input class="form-control" name="team_name" data-gestion-team-name required></label>
                        <label>Codigo<input class="form-control" name="team_code" data-gestion-team-code maxlength="12" placeholder="HSE"></label>
                        <label>Simbolo<input class="form-control" name="team_symbol" data-gestion-team-symbol maxlength="24" placeholder="HS"></label>
                        <label>Ciudad<input class="form-control" name="team_city" data-gestion-team-city placeholder="Tulua"></label>
                        <label class="team-logo-field">Subir logo de equipo<input class="form-control" type="file" name="team_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>PNG, JPG, WEBP o GIF. Si subes uno nuevo, reemplaza el logo actual.</small></label>
                    </div>
                </div>
                <div class="team-sheet-actions">
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-logo-history="team"><i class="bi bi-images"></i> Historial de logos</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-team-decks><i class="bi bi-link-45deg"></i> Asociar / desasociar decks</button>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar equipo</button>
                </div>
            </form>
        </section>
        <section class="team-performance-grid mt-3">
            <article class="panel team-stats-panel">
                <div class="panel-head"><div><h2>Estadisticas generales</h2><p>Resultados acumulados de todos los decks asociados.</p></div><span class="status-chip" data-team-stats-record>0-0-0</span></div>
                <div class="team-stat-grid" data-team-stat-grid>
                    <span><small>Eventos</small><b data-team-stat-events>0</b></span>
                    <span><small>Matches</small><b data-team-stat-matches>0</b></span>
                    <span><small>Ganados</small><b data-team-stat-wins>0</b></span>
                    <span><small>Perdidos</small><b data-team-stat-losses>0</b></span>
                    <span><small>Empates</small><b data-team-stat-draws>0</b></span>
                    <span><small>Duelos ganados</small><b data-team-stat-duels-won>0</b></span>
                    <span><small>Duelos perdidos</small><b data-team-stat-duels-lost>0</b></span>
                    <span><small>Duelos empatados</small><b data-team-stat-duels-drawn>0</b></span>
                    <span><small>Winrate</small><b data-team-stat-winrate>0%</b></span>
                    <span><small>Manos brick</small><b data-team-stat-bricks>0</b></span>
                    <span><small>Promedio de turnos</small><b data-team-stat-turns>0</b></span>
                </div>
                <div class="tier-history-summary mt-3" data-team-tier-history></div>
                <div class="team-rival-grid mt-3">
                    <section><h3>Decks que mas ha vencido</h3><div class="profile-list" data-team-top-beaten></div></section>
                    <section><h3>Decks que mas lo han derrotado</h3><div class="profile-list" data-team-top-lost-to></div></section>
                </div>
            </article>
            <article class="panel team-honors-panel">
                <div class="panel-head"><div><h2>Vitrina de logros</h2><p>Medallas obtenidas al ganar eventos.</p></div><div class="team-honor-actions"><span class="status-chip" data-team-honor-count>0 logros</span><button class="btn btn-outline-warning btn-sm" type="button" data-open-gestion-module="resumen"><i class="bi bi-plus-circle"></i> Registrar</button></div></div>
                <div class="team-honor-shelf" data-team-honor-shelf><p class="note">Selecciona un equipo para consultar su palmares.</p></div>
            </article>
        </section>
        <section class="panel mt-3 team-event-history-panel">
            <div class="panel-head"><div><h2>Rendimiento por evento</h2><p>Matches y duelos separados por competencia.</p></div></div>
            <div class="table-responsive">
                <table class="table app-table compact mobile-card-table team-event-history-table">
                    <thead><tr><th>Evento</th><th>Formato</th><th>MJ</th><th>MG</th><th>MP</th><th>DG</th><th>DP</th><th>Winrate</th></tr></thead>
                    <tbody data-team-event-history><tr><td colspan="8">Selecciona un equipo.</td></tr></tbody>
                </table>
            </div>
        </section>
    </section>

