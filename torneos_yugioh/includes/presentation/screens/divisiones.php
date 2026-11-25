    <section class="screen" data-screen="divisiones" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.divisiones', 'title', 'Divisiones')); ?></h1><p>Divisiones del modulo de eventos: agrupan equipos/decks por liga, origen clasificatorio, rango y movimientos de ascenso/descenso.</p></div>
            <button class="btn btn-primary fw-bold" type="button" data-open-gestion-module="divisiones"><i class="bi bi-plus-circle"></i> Crear / editar division</button>
        </div>
        <div class="module-kpi-strip mb-3">
            <article><i class="bi bi-trophy"></i><span>Divisiones BD</span><b><?php echo number_format(count($displayDivisions)); ?></b></article>
            <article><i class="bi bi-collection"></i><span>Decks activos</span><b><?php echo number_format(count($allDecks)); ?></b></article>
            <article><i class="bi bi-diagram-3"></i><span>Eventos</span><b><?php echo number_format(count($competitions)); ?></b></article>
            <article><i class="bi bi-calendar2-week"></i><span>Combates</span><b><?php echo number_format($totalFixtureMatches); ?></b></article>
        </div>
        <section class="panel divisions-crud-panel mb-3">
            <div class="panel-head"><div><h2>Tabla de divisiones</h2><p>Consulta, filtra y abre la ficha de cada divisi?n y sus decks inscritos.</p></div><span class="status-chip" data-division-visible-count><?php echo count($displayDivisions); ?> divisiones</span></div>
            <div class="event-filter-row">
                <label>Buscar<input class="form-control" type="search" data-division-search placeholder="Nombre, codigo, copa o descripcion"></label>
                <label>Minimo<select class="form-select" data-division-min-filter><option value="">Todas</option><option value="ready">Con minimo 4 decks</option><option value="missing">Faltan decks</option></select></label>
                <button class="btn btn-outline-light" type="button" data-division-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <div class="table-responsive">
                <table class="table app-table compact division-crud-table">
                    <thead><tr><th>Division</th><th>Liga</th><th>Origen</th><th>Equipos</th><th>Movimiento</th><th>Creacion</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($displayDivisions as $divisionKey => $division): ?>
                        <?php
                            $divisionDeckCount = count((array) ($division['decks'] ?? []));
                            $divisionReady = $divisionDeckCount >= 4;
                            $divisionSearch = strtolower((string) (($division['short'] ?? '') . ' ' . ($division['name'] ?? '') . ' ' . ($division['cup'] ?? '') . ' ' . ($division['description'] ?? '')));
                            $targetName = static function (?int $targetId) use ($displayDivisions): string {
                                foreach ($displayDivisions as $candidate) {
                                    if ((int) ($candidate['id'] ?? 0) === (int) $targetId) {
                                        return (string) ($candidate['short'] ?? $candidate['name'] ?? 'Division');
                                    }
                                }
                                return 'Sin destino';
                            };
                            $competitionNameById = static function (?int $competitionId, string $fallback = 'Modo libre / sin liga') use ($competitions): string {
                                foreach ($competitions as $competition) {
                                    if ((int) ($competition['id'] ?? 0) === (int) $competitionId) {
                                        return (string) ($competition['name'] ?? 'Evento');
                                    }
                                }
                                return $fallback;
                            };
                            $divisionLogo = ygo_media_src((string) ($division['logo_url'] ?? ''));
                            $rankRange = ($division['rank_from'] ?? null) && ($division['rank_to'] ?? null)
                                ? 'Pos. ' . (int) $division['rank_from'] . ' a ' . (int) $division['rank_to']
                                : 'Manual';
                        ?>
                        <tr data-division-row data-division-id="<?php echo e((string) ($division['id'] ?? '')); ?>" data-division-min="<?php echo $divisionReady ? 'ready' : 'missing'; ?>" data-division-search="<?php echo e($divisionSearch); ?>">
                            <td data-label="Division">
                                <span class="division-table-identity">
                                    <span class="division-table-logo"><?php echo $divisionLogo !== '' ? '<img loading="lazy" decoding="async" src="' . e($divisionLogo) . '" alt="' . e((string) ($division['name'] ?? 'Division')) . '">' : e((string) ($division['short'] ?? 'DV')); ?></span>
                                    <span><strong><?php echo e((string) ($division['name'] ?? 'Division')); ?></strong><br><small><?php echo e((string) ($division['short'] ?? '')); ?> | <?php echo e((string) ($division['cup'] ?? '')); ?></small></span>
                                </span>
                            </td>
                            <td data-label="Liga"><?php echo e($competitionNameById($division['league_competition_id'] ?? null)); ?></td>
                            <td data-label="Origen"><small><?php echo e($competitionNameById($division['source_qualifier_id'] ?? null, 'Manual / sin clasificatorio')); ?><br><?php echo e($rankRange); ?></small></td>
                            <td data-label="Equipos"><?php echo $divisionDeckCount; ?> / <?php echo max(0, (int) ($division['deck_count'] ?? 0)); ?> objetivo<br><span class="status-chip <?php echo $divisionReady ? 'state-active' : 'state-pending-config'; ?>"><?php echo $divisionReady ? 'Lista' : 'Faltan equipos'; ?></span></td>
                            <td data-label="Movimiento"><small>Suben <?php echo (int) ($division['promotion_slots'] ?? 0); ?> a <?php echo e($targetName($division['promotion_to_division_id'] ?? null)); ?><br>Bajan <?php echo (int) ($division['relegation_slots'] ?? 0); ?> a <?php echo e($targetName($division['relegation_to_division_id'] ?? null)); ?></small></td>
                            <td data-label="Creacion"><?php echo e(substr((string) ($division['created_at'] ?? ''), 0, 10) ?: 'Sin fecha'); ?></td>
                            <td data-label="Acciones"><div class="division-row-actions"><button class="btn btn-outline-info btn-sm" type="button" data-division-open="<?php echo e((string) $divisionKey); ?>" data-division-scroll><i class="bi bi-eye"></i> Ver ficha</button><button class="btn btn-outline-light btn-sm" type="button" data-open-gestion-module="divisiones" data-gestion-division-id="<?php echo e((string) ($division['id'] ?? '')); ?>"><i class="bi bi-pencil-square"></i> Editar</button></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$displayDivisions): ?><tr><td colspan="7"><div class="empty-state">No hay divisiones registradas en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php if (!$qualifierFinished): ?><div class="alert alert-info">El clasificatorio sigue abierto. Estos formatos aun no son resultado final; se llenan oficialmente al cerrar el clasificatorio.</div><?php endif; ?>
        <div class="division-selector-grid mb-3" hidden>
            <?php foreach ($displayDivisions as $key => $division): ?>
                <?php
                $divisionDecks = $qualifierFinished ? ($division['decks'] ?? []) : [];
                $divisionRounds = $qualifierFinished ? ($fixtures[$key] ?? []) : [];
                $divisionMatches = array_sum(array_map(static fn(array $round): int => count($round['matches'] ?? []), $divisionRounds));
                ?>
                <button class="panel division-panel division-entry" type="button" data-division-open="<?php echo e($key); ?>" style="--accent: <?php echo e($division['accent']); ?>">
                    <span><?php echo e($division['short']); ?></span>
                    <strong><?php echo e($division['cup']); ?></strong>
                    <small><?php echo count($divisionDecks); ?> decks | <?php echo $divisionMatches; ?> combates</small>
                    <em><?php echo $qualifierFinished ? 'Oficial' : 'Provisional'; ?></em>
                </button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($displayDivisions as $detailKey => $division): ?>
            <?php
                $divisionDecks = $qualifierFinished ? ($division['decks'] ?? []) : [];
                $divisionRounds = $qualifierFinished ? ($fixtures[$detailKey] ?? []) : [];
                $divisionCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => (string) ($competition['division_code'] ?? '') === $detailKey));
            $divisionMatches = array_sum(array_map(static fn(array $round): int => count($round['matches'] ?? []), $divisionRounds));
            $scheduledMatches = 0;
            foreach ($divisionRounds as $round) {
                foreach (($round['matches'] ?? []) as $match) {
                    if (!empty($match['scheduled_on'])) {
                        $scheduledMatches++;
                    }
                }
            }
            ?>
            <section class="division-workbench" data-division-detail="<?php echo e($detailKey); ?>" hidden>
                <div class="division-hero panel" style="--accent: <?php echo e($division['accent']); ?>">
                    <div>
                        <span class="tag"><?php echo $qualifierFinished ? 'Formato oficial' : 'Vista pendiente'; ?></span>
                        <h2><?php echo e($division['name']); ?></h2>
                        <p><?php echo e($division['description']); ?></p>
                        <button class="btn btn-outline-light btn-sm mt-3" type="button" data-open-gestion-module="divisiones" data-gestion-division-id="<?php echo e((string) ($division['id'] ?? '')); ?>"><i class="bi bi-pencil-square"></i> Editar formato</button>
                    </div>
                    <div class="division-badge"><?php echo e($division['short']); ?></div>
                </div>
                <div class="mini-grid mb-3">
                    <span><b>Decks</b><br><?php echo count($divisionDecks); ?></span>
                    <span><b>Rondas</b><br><?php echo count($divisionRounds); ?></span>
                    <span><b>Combates</b><br><?php echo $divisionMatches; ?></span>
                    <span><b>Programados</b><br><?php echo $scheduledMatches; ?></span>
                    <span><b>Copa</b><br><?php echo e($division['cup']); ?></span>
                    <span><b>Estado</b><br><?php echo $qualifierFinished ? 'Oficial' : 'Provisional'; ?></span>
                </div>
                <section class="panel division-category-hub mb-3">
                    <div class="panel-head"><div><h2>Explorar division</h2><p>Abre solo la informacion que necesitas consultar.</p></div><span class="status-chip">4 secciones</span></div>
                    <div class="division-category-grid">
                        <button type="button" data-division-category-open="decks" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-collection"></i><span><b>Decks y equipos</b><small><?php echo count($divisionDecks); ?> participantes</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-division-category-open="processes" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-diagram-3"></i><span><b>Procesos</b><small>Tabla, calendario y resultados</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-division-category-open="events" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-trophy"></i><span><b>Eventos asociados</b><small><?php echo count($divisionCompetitions); ?> registrados</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-division-category-open="matches" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-calendar2-week"></i><span><b>Combates</b><small><?php echo $divisionMatches; ?> en el fixture</small></span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </section>
                <div class="division-process-grid">
                    <section class="panel" data-division-category-source="decks" data-division-category-owner="<?php echo e($detailKey); ?>">
                        <div class="panel-head"><div><h2>Decks y equipos</h2><p>Informacion aislada de <?php echo e($division['short']); ?>.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="deck">Abrir fichas</button></div>
                        <div class="table-responsive">
                            <table class="table app-table compact division-deck-table">
                                <thead><tr><th>Seed</th><th>Deck</th><th>Equipo</th><th>Arquetipo</th><th>Estilo</th></tr></thead>
                                <tbody>
                                <?php foreach ($divisionDecks as $deck): ?>
                                    <tr><td><?php echo e((string) $deck[0]); ?></td><td><?php echo e($deck[1]); ?></td><td><span class="team-inline-badge" style="--team-primary: <?php echo e((string) ($deck['team_primary_color'] ?? '#36d8ff')); ?>; --team-secondary: <?php echo e((string) ($deck['team_secondary_color'] ?? '#ffd25f')); ?>"><?php echo e((string) (($deck['team_code'] ?? '') ?: ($deck['team_symbol'] ?? '') ?: substr((string) ($deck['team'] ?? 'YMD'), 0, 3))); ?></span> <?php echo e((string) ($deck['team'] ?? $deck[1] . ' Club')); ?></td><td><?php echo e((string) ($deck['archetype'] ?? $deck[1])); ?></td><td><?php echo e((string) ($deck['style'] ?? 'Competitivo')); ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (!$divisionDecks): ?><tr><td colspan="5"><div class="empty-state">Sin participantes oficiales. Esta categoria se llena cuando se cierre el clasificatorio.</div></td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="panel" data-division-category-source="processes" data-division-category-owner="<?php echo e($detailKey); ?>">
                    <div class="panel-head"><div><h2>Procesos del formato</h2><p>Acciones operativas sin salir del contexto de <?php echo e($division['short']); ?>.</p></div></div>
                        <div class="process-list">
                            <button type="button" data-route="tabla"><i class="bi bi-table"></i><span>Ver tabla y zonas</span></button>
                            <button type="button" data-route="calendario"><i class="bi bi-calendar2-week"></i><span>Programar combates</span></button>
                            <button type="button" data-route="resultados"><i class="bi bi-clipboard2-check"></i><span>Registrar resultados</span></button>
                            <button type="button" data-route="copas"><i class="bi bi-trophy"></i><span>Revisar copa asociada</span></button>
                            <button type="button" data-open-gestion-module="competiciones"><i class="bi bi-diagram-3-fill"></i><span>Gestionar torneos de este formato</span></button>
                        </div>
                        <?php if (!$qualifierFinished): ?><p class="note mt-3">Estas acciones quedan preparadas, pero la categoria se vuelve oficial solo cuando el clasificatorio se cierre desde Gestion.</p><?php endif; ?>
                    </section>
                </div>
                <section class="panel mt-3" data-division-category-source="events" data-division-category-owner="<?php echo e($detailKey); ?>">
                    <div class="panel-head"><div><h2>Copas y torneos de <?php echo e($division['short']); ?></h2><p>Eventos asociados a esta categoria. Los globales se gestionan desde Eventos.</p></div><button class="btn btn-outline-warning btn-sm" type="button" data-open-gestion-module="competiciones"><i class="bi bi-plus-circle"></i> Crear copa/torneo</button></div>
                    <div class="competition-mini-grid">
                        <?php foreach ($divisionCompetitions as $competition): ?>
                            <article style="--accent: <?php echo e((string) (($competition['accent'] ?? '') ?: $division['accent'])); ?>">
                                <span class="tag"><?php echo e(strtoupper((string) ($competition['type'] ?? 'torneo'))); ?></span>
                                <h3><?php echo e((string) ($competition['name'] ?? 'Evento')); ?></h3>
                                <p><?php echo e((string) (($competition['format'] ?? '') ?: 'Formato editable')); ?> | <?php echo e((string) (($competition['match_format'] ?? '') ?: '1v1')); ?> | mejor de <?php echo e((string) (($competition['best_of'] ?? '') ?: '5')); ?></p>
                                <small><?php echo e((string) (($competition['status'] ?? '') ?: 'draft')); ?> | <?php echo e((string) (($competition['schedule_strategy'] ?? '') ?: 'manual')); ?></small>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$divisionCompetitions): ?><div class="empty-state">Esta categoria aun no tiene copas/torneos asociados. Puedes crearlos desde Gestion editable.</div><?php endif; ?>
                    </div>
                </section>
                <section class="panel mt-3" data-division-category-source="matches" data-division-category-owner="<?php echo e($detailKey); ?>">
                    <div class="panel-head"><div><h2>Combates de la categoria</h2><p>Fixture, fechas y estado solo de <?php echo e($division['short']); ?>.</p></div><span class="status-chip"><?php echo $divisionMatches; ?> combates</span></div>
                    <div class="table-responsive">
                        <table class="table app-table compact division-match-table">
                            <thead><tr><th>Ronda</th><th>Mesa</th><th>Combate</th><th>Fecha</th><th>Hora</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($divisionRounds, 0, 8) as $round): ?>
                                <?php foreach (($round['matches'] ?? []) as $match): ?>
                                    <tr><td><?php echo e((string) $round['ronda']); ?></td><td><?php echo e((string) $match['mesa']); ?></td><td><?php echo e($deckMatchLabel((string) $match['a']) . ' vs ' . $deckMatchLabel((string) $match['b'])); ?></td><td><?php echo e((string) ($match['scheduled_on'] ?? 'Pendiente')); ?></td><td><?php echo e((string) ($match['starts_at'] ?? '')); ?></td><td><?php echo e((string) ($match['estado'] ?? 'PENDIENTE')); ?></td></tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if (!$divisionRounds): ?><tr><td colspan="6"><div class="empty-state">Sin calendario oficial de categoria. Primero finaliza el clasificatorio.</div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        <?php endforeach; ?>
        <div data-division-category-staging hidden></div>
        <div class="modal fade dml-modal profile-category-modal division-category-modal" id="divisionCategoryModal" tabindex="-1" aria-hidden="true" data-division-category-modal>
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><span class="tag" data-division-category-context>Ficha de division</span><h2 class="modal-title" data-division-category-title>Detalle</h2><p data-division-category-subtitle></p></div>
                        <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" data-division-category-body></div>
                    <div class="modal-footer profile-category-modal-footer">
                        <button class="btn btn-outline-light" type="button" data-division-category-prev title="Seccion anterior"><i class="bi bi-chevron-left"></i><span>Anterior</span></button>
                        <span data-division-category-position>1 de 4</span>
                        <button class="btn btn-outline-info" type="button" data-division-category-next title="Seccion siguiente"><span>Siguiente</span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </section>

