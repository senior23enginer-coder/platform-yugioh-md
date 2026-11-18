    <section class="screen" data-screen="dashboard">
        <div class="dashboard-pro">
            <section class="dashboard-hero-pro">
                <div class="dashboard-hero-copy">
                    <span class="hero-mark"></span>
                    <p class="eyebrow">YUGIOH MD</p>
                    <h1>Bienvenido a YUGIOH MD</h1>
                    <p>Organiza y administra eventos activos por formato con datos reales de la base: calendario, participantes, resultados y estados actuales.</p>
                </div>
                <div class="dashboard-hero-status">
                    <article>
                        <i class="bi bi-shield-shaded"></i>
                        <small>Temporada actual</small>
                        <strong><?php echo e($dashboardSeasonName); ?></strong>
                    </article>
                    <article>
                        <i class="bi bi-calendar2-event"></i>
                        <small>Proximo foco</small>
                        <strong><?php echo e((string) ($nextDashboardEvent['starts_on'] ?? date('Y-m-d'))); ?></strong>
                    </article>
                </div>
            </section>

            <section class="news-dashboard" data-raw-text><div class="news-dashboard-head"><div><h2><i class="bi bi-newspaper"></i> En el radar</h2><p>Novedades de las cuatro fuentes que sigues.</p></div><button type="button" class="btn btn-outline-info btn-sm" data-route="noticias">Ver noticias ↗</button></div><div class="news-dashboard-grid" data-news-dashboard-grid><p>Preparando las publicaciones guardadas…</p></div></section>
            <div class="dashboard-command-layout">
                <div class="dashboard-main-stack">
                    <section class="panel dashboard-active-panel">
                        <div class="panel-head">
                            <div><h2>Eventos activos ahora</h2><p>Torneos y eventos en ventana real. Sigue la accion, revisa rondas y resultados.</p></div>
                            <button class="btn btn-outline-info btn-sm" type="button" data-route="torneos">Ver todos <i class="bi bi-arrow-right"></i></button>
                        </div>
                        <div class="dashboard-format-tabs" aria-label="Formatos activos">
                            <button class="active" type="button" data-dashboard-format-filter=""><i class="bi bi-stars"></i> Todos <b><?php echo $activeEventCount; ?></b></button>
                            <?php foreach ($currentEventsByFormat as $formatName => $formatEvents): ?>
                                <button type="button" data-dashboard-format-filter="<?php echo e((string) $formatName); ?>"><?php echo e($formatName); ?> <b><?php echo count($formatEvents); ?></b></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="dashboard-event-card-grid">
                            <?php foreach (array_slice($activeDashboardEvents, 0, 5) as $index => $event): ?>
                                <?php
                                    $eventType = (string) (($event['type'] ?? '') ?: 'special');
                                    $eventMeta = $competitionTypeOptions[$eventType] ?? $competitionTypeOptions['special'];
                                    $rulesetCode = (string) (($event['event_ruleset'] ?? '') ?: 'custom');
                                    $rulesetLabel = (string) (($eventRulesetOptions[$rulesetCode] ?? '') ?: 'Libre');
                                    $deckPoolCode = (string) (($event['deck_pool'] ?? '') ?: 'all_active');
                                    $deckPoolLabel = (string) (($eventDeckPoolOptions[$deckPoolCode] ?? '') ?: $deckPoolCode);
                                    $deckPoolCount = $deckPoolCode === 'all_active' ? count($allDecks) : (int) (($event['deck_pool_count'] ?? '') ?: 0);
                                    $eventId = (int) ($event['id'] ?? 0);
                                    $eventStats = $dashboardMatchStatsByCompetition[$eventId] ?? ['total' => 0, 'finalized' => 0, 'pending' => 0];
                                    if ($eventStats['total'] === 0 && isset($dashboardMatchStatsByPhase[$eventType])) {
                                        $eventStats = $dashboardMatchStatsByPhase[$eventType];
                                    }
                                    $eventFormatName = (string) (($event['division_code'] ?? '') ?: (($eventRulesetOptions[$rulesetCode] ?? '') ?: 'General'));
                                    $progress = $eventStats['total'] > 0 ? (int) round(($eventStats['finalized'] / $eventStats['total']) * 100) : 0;
                                ?>
                                <article class="dashboard-event-card-pro" data-dashboard-event-card data-dashboard-format="<?php echo e($eventFormatName); ?>" style="--accent: <?php echo e((string) ($eventMeta['accent'] ?? '#895cff')); ?>; --i: <?php echo $index; ?>">
                                    <div class="event-card-cover<?php echo !empty($event['logo_url']) ? ' has-logo' : ''; ?>">
                                        <?php if (!empty($event['logo_url'])): ?><img src="<?php echo e((string) $event['logo_url']); ?>" alt="<?php echo e((string) ($event['name'] ?? 'Evento')); ?>"><?php endif; ?>
                                        <span><?php echo e((string) ($eventMeta['label'] ?? 'Evento')); ?></span>
                                        <em><?php echo e((string) (($event['status'] ?? '') ?: 'active')); ?></em>
                                    </div>
                                    <div class="event-card-body">
                                        <h3><?php echo e((string) ($event['name'] ?? 'Evento YUGIOH MD')); ?></h3>
                                        <p><i class="bi bi-diagram-3"></i> <?php echo e((string) (($event['format'] ?? '') ?: 'Formato editable')); ?></p>
                                        <p class="<?php echo $deckPoolCount === 0 ? 'event-warning-line' : ''; ?>"><i class="bi bi-people"></i> <?php echo $deckPoolCount > 0 ? e((string) $deckPoolCount) . ' decks habilitados' : 'Pool sin decks habilitados'; ?></p>
                                        <p><i class="bi bi-layers"></i> <?php echo e($deckPoolLabel); ?></p>
                                        <p><i class="bi bi-bookmark-star"></i> <?php echo e($rulesetLabel); ?></p>
                                        <p><i class="bi bi-list-check"></i> <?php echo (int) $eventStats['finalized']; ?>/<?php echo (int) $eventStats['total']; ?> duelos cerrados</p>
                                        <div class="event-progress"><span style="width: <?php echo $progress; ?>%"></span></div>
                                        <button class="btn btn-primary btn-sm w-100" type="button" data-event-open="<?php echo e((string) $eventId); ?>">Abrir ficha <i class="bi bi-card-checklist"></i></button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$activeDashboardEvents): ?>
                                <article class="dashboard-empty-state"><i class="bi bi-calendar-x"></i><strong>No hay eventos activos hoy</strong><span>Los eventos en borrador quedan en administracion hasta que entren en ventana activa.</span><button class="btn btn-primary btn-sm" type="button" data-route="crear_evento">Crear evento</button></article>
                            <?php endif; ?>
                        </div>
                        <div class="dashboard-active-support">
                            <section>
                                <h3>Duelos de hoy</h3>
                                <div class="dashboard-next-match-list">
                                    <?php foreach ($upcomingDashboardMatches as $match): ?>
                                        <?php
                                        $matchStatus = strtoupper((string) (($match['status'] ?? '') ?: 'PENDIENTE'));
                                        $matchStatusClass = in_array($matchStatus, ['FINALIZADA', 'FINALIZADO'], true) ? 'ok' : (in_array($matchStatus, ['EN CURSO', 'REQUIERE D5'], true) ? 'warn' : 'blocked');
                                        $matchStatusLabel = in_array($matchStatus, ['FINALIZADA', 'FINALIZADO'], true) ? 'Finalizado' : (in_array($matchStatus, ['EN CURSO', 'REQUIERE D5'], true) ? 'En curso' : 'Pendiente');
                                        ?>
                                        <article class="dashboard-next-match" role="button" tabindex="0" data-dashboard-match-open data-match-id="<?php echo e((string) ($match['id'] ?? '')); ?>">
                                            <b><?php echo e(substr((string) $match['time'], 0, 5)); ?></b>
                                            <span><?php echo e($match['label']); ?></span>
                                            <em class="status-chip <?php echo e($matchStatusClass); ?>"><?php echo e($matchStatusLabel); ?></em>
                                            <small><?php echo e($match['versus']); ?></small>
                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                        </article>
                                    <?php endforeach; ?>
                                    <?php if (!$upcomingDashboardMatches): ?><p class="note">No hay duelos programados para hoy.</p><?php endif; ?>
                                </div>
                            </section>
                            <section>
                                <h3>Eventos guardados</h3>
                                <div class="dashboard-state-grid">
                                    <?php foreach ($eventSummaryByStatus as $statusName => $statusCount): ?>
                                        <span><b><?php echo e((string) $statusCount); ?></b><?php echo e(ygo_event_status_label((string) $statusName)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>
                    </section>

                    <section class="panel dashboard-results-panel">
                        <div class="panel-head"><div><h2>Ultimos resultados de duelos</h2><p>Resultados recientes en tiempo real de los eventos activos.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="resultados">Ver todos <i class="bi bi-arrow-right"></i></button></div>
                        <div class="table-responsive">
                            <table class="table app-table dashboard-results-table">
                                <thead><tr><th>Evento</th><th>Jugador A</th><th>VS</th><th>Jugador B</th><th>Resultado</th></tr></thead>
                                <tbody>
                                <?php foreach ($recentResults as $result): ?>
                                    <?php $duelists = explode(' vs ', (string) $result['versus'], 2); ?>
                                    <tr><td><?php echo e($result['label']); ?></td><td><?php echo e($duelists[0] ?? 'Deck A'); ?></td><td><?php echo e($result['score']); ?></td><td><?php echo e($duelists[1] ?? 'Deck B'); ?></td><td><span class="status-chip ok"><?php echo e($result['status']); ?></span></td></tr>
                                <?php endforeach; ?>
                                <?php if (!$recentResults): ?><tr><td colspan="5">Todavia no hay resultados guardados en BD. Revisa proximos duelos en el panel superior.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </div>

                <aside class="dashboard-side-stack">
                    <section class="panel dashboard-ranking-panel">
                        <div class="panel-head"><div><h2><?php echo $dashboardLiveRanking ? 'Rankings en vivo' : 'Actividad registrada'; ?></h2><p data-dashboard-ranking-source><?php echo $dashboardLiveRanking ? e($dashboardRankingSource) : 'Sin ranking activo por resultados todavia.'; ?></p></div></div>
                        <label class="dashboard-ranking-event-label">Evento y división<select class="form-select" data-dashboard-ranking-event></select></label>
                        <label class="dashboard-ranking-event-label">Clasificacion
                            <select class="form-select" data-dashboard-ranking-metric>
                                <option value="general_stats">Estadisticas Generales</option>
                                <option value="points">Puntaje General</option>
                                <option value="duels">Duelos</option>
                                <option value="damage_dealt">Dano Hecho</option>
                                <option value="damage_received">Dano Recibido</option>
                                <option value="surrenders">Rendicion</option>
                                <option value="lp_losses">Puntos de Vida</option>
                            </select>
                        </label>
                        <h3 data-dashboard-ranking-title>Equipos con mas puntos</h3>
                        <p class="dashboard-ranking-coverage" data-dashboard-lp-coverage hidden></p>
                        <div class="dashboard-rank-list" data-dashboard-ranking-list>
                            <?php foreach ($topDashboardTeams as $index => $team): ?>
                                <?php
                                $teamName = (string) ($team['name'] ?? 'Equipo');
                                $teamLogo = ygo_media_src((string) ($team['logo_url'] ?? ''));
                                $teamMark = strtoupper(substr((string) (($team['symbol'] ?? '') ?: (($team['short_code'] ?? '') ?: $teamName)), 0, 3));
                                ?>
                                <article>
                                    <b><?php echo $index + 1; ?></b>
                                    <span class="rank-avatar media-avatar"><?php if ($teamLogo !== ''): ?><img src="<?php echo e($teamLogo); ?>" alt="<?php echo e($teamName); ?>"><?php else: ?><?php echo e($teamMark); ?><?php endif; ?></span>
                                    <strong><?php echo e($teamName); ?></strong>
                                    <em><?php echo (int) ($teamDeckCounts[$teamName] ?? 0); ?> decks</em>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
        <div class="legacy-dashboard" hidden>
        <div class="hero-panel">
            <div>
                <p class="eyebrow">Yu-Gi-Oh competitivo</p>
                <h1>YUGIOH MD</h1>
                <p>Centro operativo para crear eventos independientes: clasificatorios, torneos, copas, ligas, Goat, TCG Avanzado, OCG y formatos especiales con reglas propias.</p>
            </div>
            <div class="hero-kpis">
                <div><strong><?php echo count($allDecks); ?></strong><span>decks activos</span></div>
                <div><strong><?php echo count($displayDivisions); ?></strong><span>formatos editables</span></div>
                <div><strong>3</strong><span>combates minimos</span></div>
                <div><strong><?php echo count($competitions); ?></strong><span>eventos configurados</span></div>
            </div>
        </div>
        <div class="phase-strip mt-3">
            <article class="phase-card active"><span>1</span><b>Eventos</b><small><?php echo count($competitions); ?> activos</small></article>
            <article class="phase-card <?php echo $qualifierFinished ? 'active' : ''; ?>"><span>2</span><b>Prerequisitos</b><small><?php echo count($competitionDependencies); ?> reglas</small></article>
            <article class="phase-card"><span>3</span><b>Reglamentos</b><small><?php echo count($eventSummaryByRuleset); ?> pools</small></article>
            <article class="phase-card"><span>4</span><b>Combates</b><small>Victoria / derrota / empate</small></article>
        </div>
        <?php if ($flashMessage): ?><div class="alert <?php echo e($flashClass); ?> mt-3 app-flash-message" data-flash-message><?php echo e($flashMessage); ?></div><?php endif; ?>

        <div class="dashboard-live-grid mt-3">
            <section class="panel">
                <div class="panel-head"><div><h2>Eventos activos por formato</h2><p>Vista inmediata de lo que esta abierto ahora.</p></div><button class="btn btn-outline-info btn-sm" type="button" data-route="torneos"><i class="bi bi-grid"></i> Ver eventos</button></div>
                <div class="dashboard-event-list">
                    <?php foreach ($currentEventsByFormat as $formatName => $formatEvents): ?>
                        <article>
                            <b><?php echo e($formatName); ?></b>
                            <div>
                                <?php foreach (array_slice($formatEvents, 0, 4) as $event): ?>
                                    <span><?php echo e((string) ($event['name'] ?? 'Evento')); ?> <small><?php echo e((string) (($event['status'] ?? '') ?: 'draft')); ?></small></span>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$currentEventsByFormat): ?><p class="note">No hay eventos en ventana activa para hoy.</p><?php endif; ?>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><div><h2>Ultimos resultados</h2><p>Eventos y duelos cerrados recientemente.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="resultados"><i class="bi bi-list-check"></i> Resultados</button></div>
                <div class="dashboard-result-list">
                    <?php foreach ($recentResults as $result): ?>
                        <article><span><?php echo e($result['label']); ?></span><b><?php echo e($result['score']); ?></b><small><?php echo e($result['versus']); ?></small></article>
                    <?php endforeach; ?>
                    <?php if (!$recentResults): ?><p class="note">Todavia no hay resultados guardados.</p><?php endif; ?>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><div><h2>Tops</h2><p>Decks base y equipos con mayor presencia.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="estadisticas"><i class="bi bi-bar-chart"></i> Estadisticas</button></div>
                <div class="dashboard-top-list">
                    <?php foreach (array_slice($allDecks, 0, 5) as $index => $deck): ?>
                        <article><b>#<?php echo $index + 1; ?></b><span><?php echo e((string) $deck['name']); ?></span><small><?php echo e((string) (($deck['team'] ?? '') ?: 'Sin equipo')); ?> | <?php echo e((string) $deck['division']); ?></small></article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="hifi-kpi-strip mt-3">
            <article class="hifi-kpi"><span><i class="bi bi-collection"></i></span><small>Decks registrados</small><strong><?php echo number_format(count($allDecks)); ?></strong><em>Base activa</em></article>
            <article class="hifi-kpi"><span><i class="bi bi-trophy-fill"></i></span><small>Eventos activos</small><strong><?php echo number_format(count(array_filter($competitions, static fn($c): bool => in_array((string) ($c['status'] ?? ''), ['active', 'draft'], true)))); ?></strong><em>Liga, copas y mundial</em></article>
            <article class="hifi-kpi"><span><i class="bi bi-crosshair"></i></span><small>Rondas cargadas</small><strong><?php echo number_format(array_sum(array_map(static fn($f): int => count($f), $fixtures))); ?></strong><em>Fixture editable</em></article>
            <article class="hifi-kpi"><span><i class="bi bi-calendar2-week-fill"></i></span><small>Proximos eventos</small><strong><?php echo number_format(count($competitions)); ?></strong><em>Ver calendario</em></article>
        </div>

        <section class="panel event-command-center mt-3">
            <div class="panel-head"><div><h2>Mapa de eventos</h2><p>Clasificatorios, ligas, copas y formatos especiales se administran como eventos independientes.</p></div><button class="btn btn-outline-info btn-sm" type="button" data-route="gestion"><i class="bi bi-plus-circle"></i> Crear evento</button></div>
            <div class="event-type-grid">
                <?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?>
                    <article style="--accent: <?php echo e((string) $eventTypeMeta['accent']); ?>">
                        <i class="bi <?php echo e((string) $eventTypeMeta['icon']); ?>"></i>
                        <span><?php echo e((string) $eventTypeMeta['label']); ?></span>
                        <strong><?php echo (int) ($eventSummaryByType[$eventType] ?? 0); ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="ruleset-strip">
                <?php foreach ($eventRulesetOptions as $rulesetCode => $rulesetLabel): ?>
                    <span><?php echo e($rulesetLabel); ?> <b><?php echo (int) ($eventSummaryByRuleset[$rulesetCode] ?? 0); ?></b></span>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="row g-3 mt-1">
            <?php foreach ($displayDivisions as $key => $division): ?>
                <div class="col-xl-4">
                    <article class="league-card" style="--accent: <?php echo e($division['accent']); ?>">
                        <div class="d-flex justify-content-between gap-3">
                            <div><span class="tag"><?php echo e($division['short']); ?></span><h2><?php echo e($division['name']); ?></h2></div>
                            <span class="division-emblem"><?php echo e($division['short']); ?></span>
                        </div>
                        <p><?php echo e($division['description']); ?></p>
                        <div class="mini-grid">
                            <span><b><?php echo count($division['decks']); ?></b> decks</span>
                            <span><b><?php echo count($fixtures[$key]); ?></b> rondas</span>
                            <span><b><?php echo array_sum(array_map(static fn($r): int => count($r['matches']), $fixtures[$key])); ?></b> partidas</span>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <section class="panel">
                    <div class="panel-head"><div><h2>Estado maestro</h2><p>Replica visual del Dashboard_Maestro y Dashboard de liga.</p></div><span class="status-chip">PENDIENTE / EN CURSO</span></div>
                    <div class="table-responsive">
                        <table class="table app-table">
                            <thead><tr><th>Indicador</th><th>Valor</th><th>Detalle</th></tr></thead>
                            <tbody>
                            <tr><td>Ligas</td><td><?php echo (int) ($eventSummaryByType['league'] ?? 0); ?></td><td>Ligas configuradas en YUGIOH MD</td></tr>
                            <tr><td>Clasificatorio</td><td><?php echo e($qualifierStatusLabel); ?></td><td>Se administra, se juega, se registran resultados y luego se finaliza</td></tr>
                            <tr><td>Divisiones</td><td><?php echo count($displayDivisions); ?></td><td><?php echo e($divisionModeLabel); ?> dentro del flujo de eventos</td></tr>
                            <tr><td>Partidas de liga</td><td><?php echo array_sum(array_map(static fn($f): int => array_sum(array_map(static fn($r): int => count($r['matches']), $f)), $fixtures)); ?></td><td>Calendario todos contra todos</td></tr>
                            <tr><td>Corte divisional</td><td>Configurable</td><td>Segun ranking final del clasificatorio y reglas vigentes</td></tr>
                            <tr><td>Base de datos</td><td><?php echo $dbStatus['connected'] ? 'Activa' : 'Pendiente'; ?></td><td><?php echo e($dbStatus['message']); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-xl-4">
                <section class="panel h-100">
                    <div class="panel-head"><div><h2>Flujo de temporada</h2><p>Orden competitivo definido.</p></div></div>
                    <ol class="timeline-list"><li>Torneo clasificatorio</li><li>Asignacion de formatos</li><li>Amistosos previos</li><li>Liga regular</li><li>Copas por formato</li><li>World Duel Championship</li><li>Copa Eternal</li></ol>
                    <?php if ($dbStatus['connected']): ?>
                        <div class="mini-grid mt-3">
                            <span><b><?php echo e((string) ($dbStatus['counts']['decks'] ?? 0)); ?></b> decks DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['matches'] ?? 0)); ?></b> partidas DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['divisions'] ?? 0)); ?></b> divisiones DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['teams'] ?? 0)); ?></b> equipos DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['competitions'] ?? 0)); ?></b> eventos DB</span>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <section class="panel management-shortcuts mt-3">
            <div class="panel-head">
                <div><h2>Gestion editable</h2><p>Las opciones de configuracion estan disponibles como parte normal del aplicativo.</p></div>
                <button class="btn btn-primary fw-bold" type="button" data-route="gestion"><i class="bi bi-sliders"></i> Abrir gestion</button>
            </div>
            <div class="gestion-command-grid">
                <article><i class="bi bi-people"></i><small>Equipos</small><strong>Nombre, codigo, colores y logo</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Editar</button></article>
                <article><i class="bi bi-journal-richtext"></i><small>Decks</small><strong>Ficha, arquetipo, cartas y media</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Editar</button></article>
                <article><i class="bi bi-trophy"></i><small>Torneos</small><strong>Ligas, copas, mundial y estados</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Editar</button></article>
                <article><i class="bi bi-calendar-event"></i><small>Calendario</small><strong>Fechas, horas, mesas y sede</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="calendario">Programar</button></article>
            </div>
            <div class="pending-decks mt-3">
                <h3>Decks pendientes para cerrar</h3>
                <div>
                    <?php foreach (array_filter($qualifierProgress, static fn(array $row): bool => !$row['ready']) as $row): ?>
                        <span><?php echo e($row['deck']); ?> <small><?php echo e((string) $row['played']); ?>/3 combates completados</small></span>
                    <?php endforeach; ?>
                    <?php if ($qualifierReadyCount === count($qualifierProgress)): ?><span>Todos los decks cumplen el minimo competitivo.</span><?php endif; ?>
                </div>
            </div>
        </section>
        <div class="dashboard-arena mt-3">
            <section class="panel">
                <div class="panel-head"><div><h2>Top decks</h2><p>Vista tipo ranking competitivo.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="tabla">Ver todos</button></div>
                <div class="arena-ranking">
                    <?php foreach (array_slice($allDecks, 0, 5) as $index => $deck): ?>
                        <article><b><?php echo $index + 1; ?></b><span class="mini-card-art" style="--i:<?php echo $index; ?>"></span><strong><?php echo e($deck['name']); ?></strong><small><?php echo e($deck['division']); ?></small><em><?php echo e((string) max(6, 18 - ($index * 3))); ?>%</em></article>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><div><h2>Top jugadores</h2><p>Equipos con decks registrados.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Gestion completa</button></div>
                <div class="arena-ranking players">
                    <?php foreach (array_slice($teams, 0, 5) as $index => $team): ?>
                        <article><b><?php echo $index + 1; ?></b><span class="player-medal"><?php echo e(substr((string) $team['name'], 0, 1)); ?></span><strong><?php echo e((string) $team['name']); ?></strong><small><?php echo e((string) ($team['city'] ?: 'Station X')); ?></small><em><?php echo number_format(2450 - ($index * 135)); ?> pts</em></article>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="panel season-progress">
                <div class="panel-head"><div><h2>Progreso de temporada</h2><p>Estado actual de la liga.</p></div><span class="status-chip">Nivel 42</span></div>
                <div class="rank-emblem">YMD</div>
                <div class="xp-bar"><span style="width:68%"></span></div>
                <p>Clasificatorio abierto, divisiones editables y calendario listo para programar combates.</p>
                <button class="btn btn-outline-light w-100" type="button" data-route="clasificatorio">Ver fase actual</button>
            </section>
        </div>
        </div>
    </section>

    <?php require YGO_APP_ROOT.'/includes/decks/calculator-workspace-view.php'; ?>
    <?php require YGO_APP_ROOT.'/includes/decks/card-inspector-view.php'; ?>
    <?php require YGO_APP_ROOT.'/includes/decks/attributes-view.php'; ?>
    <?php require YGO_APP_ROOT.'/includes/decks/combo-creator-view.php'; ?>
    <?php require YGO_APP_ROOT.'/includes/library-view.php'; ?>
