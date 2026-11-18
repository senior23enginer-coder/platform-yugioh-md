    <section class="screen" data-screen="calendario" hidden>
        <div class="page-title with-actions">
            <div><h1><?php echo e($uiText('route.calendario', 'title', 'Calendario')); ?></h1><p><?php echo e($uiText('route.calendario', 'subtitle', 'Consulta eventos, jornadas y combates persistidos en base de datos.')); ?></p></div>
            <span class="status-chip" data-calendar-count>0 registros</span>
        </div>
        <div class="calendar-profile-scope" data-calendar-profile-scope hidden>
            <span class="calendar-profile-scope-icon"><i class="bi bi-calendar3"></i></span>
            <span><small data-calendar-profile-kind>Calendario filtrado</small><strong data-calendar-profile-label></strong></span>
            <button class="btn btn-outline-light btn-sm" type="button" data-calendar-profile-clear><i class="bi bi-x-lg"></i> Ver calendario general</button>
        </div>
        <section class="panel calendar-panel mb-3">
            <div class="panel-head calendar-panel-head"><div><h2>Agenda general</h2><p>Usa filtros, cambia de vista y entra al detalle del dia sin salir del calendario.</p></div></div>
            <div class="calendar-toolbar">
                <div class="calendar-month-controls">
                    <button class="icon-button" type="button" data-calendar-prev title="Mes anterior"><i class="bi bi-chevron-left"></i></button>
                    <?php
                    $calendarTitleDate = new DateTimeImmutable('first day of this month');
                    $calendarMonths = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
                    ?>
                    <strong data-calendar-title><?php echo e($calendarMonths[$calendarTitleDate->format('m')] . ' ' . $calendarTitleDate->format('Y')); ?></strong>
                    <button class="icon-button" type="button" data-calendar-next title="Mes siguiente"><i class="bi bi-chevron-right"></i></button>
                    <button class="btn btn-outline-light" type="button" data-calendar-today>Hoy</button>
                </div>
                <div class="calendar-view-tabs" role="tablist" aria-label="Vistas de calendario">
                    <button class="active" type="button" data-calendar-view="month">Mes</button>
                    <button type="button" data-calendar-view="week">Semana</button>
                    <button type="button" data-calendar-view="day">Dia</button>
                    <button type="button" data-calendar-view="year">Ano</button>
                    <button type="button" data-calendar-view="agenda">Agenda</button>
                </div>
                <div class="calendar-filters">
                    <select class="form-select" data-calendar-type><option value="">Todas las categorias de evento</option><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select>
                    <select class="form-select" data-calendar-division><option value="">Todos los formatos</option><option value="CLASIFICATORIO">CLASIFICATORIO</option><?php foreach ($displayDivisions as $key => $division): ?><option value="<?php echo e((string) (($division['code'] ?? '') ?: $key)); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select>
                    <input class="form-control" data-calendar-search placeholder="Buscar evento, deck, copa o ronda">
                </div>
            </div>
            <div class="calendar-create-toolbar mt-3">
                <button class="btn btn-primary fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#friendlyMatchModal"><i class="bi bi-plus-circle"></i> Crear combate amistoso</button>
                <span>Combate independiente: no pertenece a ningun evento, pero queda programado en calendario.</span>
            </div>
            <div class="calendar-layout mt-3">
                <div class="calendar-main">
                    <div class="fullcalendar-shell" data-fullcalendar hidden></div>
                    <div class="calendar-weekdays calendar-fallback" data-calendar-weekdays><span>Lun</span><span>Mar</span><span>Mie</span><span>Jue</span><span>Vie</span><span>Sab</span><span>Dom</span></div>
                    <div class="calendar-month calendar-fallback" data-calendar-grid>
                        <?php
                        $calendarFirst = new DateTimeImmutable('first day of this month');
                        $calendarStart = $calendarFirst->modify('-' . ((int) $calendarFirst->format('N') - 1) . ' days');
                        $calendarMonth = $calendarFirst->format('m');
                        $calendarToday = (new DateTimeImmutable())->format('Y-m-d');
                        for ($i = 0; $i < 42; $i++):
                            $calendarDay = $calendarStart->modify('+' . $i . ' days');
                            $calendarIso = $calendarDay->format('Y-m-d');
                            $calendarClasses = trim(($calendarDay->format('m') !== $calendarMonth ? ' outside' : '') . ($calendarIso === $calendarToday ? ' selected' : ''));
                        ?>
                            <button class="calendar-day <?php echo e($calendarClasses); ?>" type="button" data-calendar-day="<?php echo e($calendarIso); ?>"><b><?php echo e($calendarDay->format('j')); ?></b><span class="calendar-day-action">Ver dia</span></button>
                        <?php endfor; ?>
                    </div>
                </div>
                <aside class="calendar-side">
                    <div class="panel-head"><div><h2>Detalle del dia</h2><p>Jornadas y combates de la fecha seleccionada.</p></div></div>
                    <div class="calendar-day-detail" data-calendar-detail></div>
                </aside>
            </div>
            <div class="calendar-legend">
                <span><i style="--accent:#2f80ed"></i> Fecha incompleta</span>
                <span><i style="--accent:#22c97a"></i> Fecha completa</span>
                <span><i style="--accent:#8a8f9c"></i> Sin combates</span>
            </div>
        </section>
        <div class="control-strip">
            <select class="form-select" data-fixture-competition aria-label="Evento activo"><option value="">Cargando eventos activos...</option></select>
            <select class="form-select" data-fixture-round aria-label="Ronda del evento"></select>
            <span class="status-chip" data-fixture-bye></span>
        </div>
        <section class="panel"><div class="table-responsive"><table class="table app-table"><thead><tr><th>Mesa</th><th>Deck A</th><th>Deck B</th><th>Estado</th></tr></thead><tbody data-fixture-body></tbody></table></div></section>
    </section>

