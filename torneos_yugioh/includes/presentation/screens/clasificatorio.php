    <section class="screen" data-screen="clasificatorio" data-qualifier-event-id="<?php echo e($showQualifierDetail ? (string) $displayQualifierId : ''); ?>" hidden>
        <div data-qualifier-directory <?php echo $showQualifierDetail ? 'hidden' : ''; ?>>
            <div class="page-title with-actions module-title qualifier-directory-title">
                <div><span class="tag">Clasificatorios</span><h1>Eventos clasificatorios</h1><p>Selecciona un clasificatorio para consultar su ranking, combates y cierre.</p></div>
                <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear clasificatorio</button>
            </div>
            <section class="panel event-filter-panel mt-3">
                <details class="mobile-collapsible event-filter-shell" open>
                    <summary><span><i class="bi bi-funnel"></i> Filtros de clasificatorios</span><i class="bi bi-chevron-down"></i></summary>
                    <div class="event-search-filters qualifier-directory-filters">
                        <label>Buscar evento<input class="form-control" type="search" placeholder="Nombre, codigo, formato, regla o banlist" data-qualifier-directory-search></label>
                        <label>Tipo<select class="form-select" disabled><option>Clasificatorio</option></select></label>
                        <label>Estado<select class="form-select" data-qualifier-directory-status><option value="">Todos</option><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                        <label>Formato<select class="form-select" data-qualifier-directory-format><option value="">Todos</option><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></select></label>
                        <button class="btn btn-outline-light" type="button" data-qualifier-directory-clear><i class="bi bi-x-circle"></i> Limpiar</button>
                    </div>
                </details>
            </section>
            <section class="panel mt-3">
                <div class="panel-head"><div><h2>Tabla de clasificatorios</h2><p>Consulta estado, formato, participantes y avance antes de abrir un evento.</p></div><span class="status-chip" data-qualifier-directory-count><?php echo count($qualifierCompetitions); ?> eventos</span></div>
                <div class="table-responsive">
                    <table class="table app-table compact event-sort-table mobile-card-table qualifier-directory-table" data-qualifier-directory-table>
                        <thead><tr><th><button type="button" data-qualifier-directory-sort="name">Evento <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-qualifier-directory-sort="status">Estado <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-qualifier-directory-sort="format">Formato <i class="bi bi-arrow-down-up"></i></button></th><th>Match</th><th><button type="button" data-qualifier-directory-sort="start">Inicio <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-qualifier-directory-sort="participants">Participantes <i class="bi bi-arrow-down-up"></i></button></th><th>Avance</th><th>Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($qualifierCompetitions as $competition): ?>
                            <?php
                            $directoryId = (int) ($competition['id'] ?? 0);
                            $directoryStatus = ygo_normalize_status_key((string) ($competition['status'] ?? 'pending_configuration'));
                            $directoryStatusClass = ygo_event_status_class($directoryStatus);
                            $directoryFormat = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
                            $directoryParticipants = count((array) ($competition['selected_deck_ids'] ?? []));
                            $directoryStats = $qualifierStatsByCompetition[$directoryId] ?? ['matches' => 0, 'finished' => 0];
                            $directoryStart = (string) (($competition['starts_on'] ?? '') ?: '');
                            $directorySearch = strtolower(implode(' ', [(string) ($competition['name'] ?? ''), (string) ($competition['code'] ?? ''), $directoryFormat, (string) ($competition['ruleset_version'] ?? ''), ygo_event_status_label($directoryStatus)]));
                            ?>
                            <tr class="<?php echo e($directoryStatusClass); ?>" data-qualifier-directory-row data-qualifier-status="<?php echo e($directoryStatus); ?>" data-qualifier-format="<?php echo e($directoryFormat); ?>" data-qualifier-search="<?php echo e($directorySearch); ?>" data-sort-name="<?php echo e(strtolower((string) ($competition['name'] ?? ''))); ?>" data-sort-status="<?php echo e($directoryStatus); ?>" data-sort-format="<?php echo e($directoryFormat); ?>" data-sort-start="<?php echo e($directoryStart !== '' ? $directoryStart : '9999-12-31'); ?>" data-sort-participants="<?php echo e((string) $directoryParticipants); ?>">
                                <td data-label="Evento"><div class="event-table-title"><span class="event-table-logo"><?php if (!empty($competition['logo_url'])): ?><img loading="lazy" decoding="async" src="<?php echo e((string) $competition['logo_url']); ?>" alt="<?php echo e((string) ($competition['name'] ?? 'Clasificatorio')); ?>"><?php else: ?><i class="bi bi-diagram-3"></i><?php endif; ?></span><span><strong><?php echo e((string) ($competition['name'] ?? 'Clasificatorio')); ?></strong><br><small><?php echo e((string) ($competition['code'] ?? '')); ?></small></span></div></td>
                                <td data-label="Estado"><span class="status-chip <?php echo e($directoryStatusClass); ?>"><?php echo e(ygo_event_status_label($directoryStatus)); ?></span></td>
                                <td data-label="Formato"><?php echo e((string) ($formatSelectOptions[$directoryFormat] ?? strtoupper($directoryFormat))); ?><br><small><?php echo e((string) (($competition['ruleset_version'] ?? '') ?: 'Sin version')); ?></small></td>
                                <td data-label="Match"><?php echo e(strtoupper((string) (($competition['match_format'] ?? '') ?: '1v1'))); ?> | BO<?php echo e((string) (($competition['best_of'] ?? '') ?: 3)); ?></td>
                                <td data-label="Inicio"><?php echo e($directoryStart !== '' ? $directoryStart : 'Sin fecha'); ?></td>
                                <td data-label="Participantes"><?php echo e((string) $directoryParticipants); ?> decks</td>
                                <td data-label="Avance"><strong><?php echo e((string) $directoryStats['finished']); ?> / <?php echo e((string) $directoryStats['matches']); ?></strong><small class="d-block">combates finalizados</small></td>
                                <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-qualifier-directory-open="<?php echo e((string) $directoryId); ?>"><i class="bi bi-eye"></i> Ver</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$qualifierCompetitions): ?><tr><td colspan="8"><div class="empty-state">No hay clasificatorios registrados.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div data-qualifier-detail <?php echo $showQualifierDetail ? '' : 'hidden'; ?>>
        <header class="qualifier-workspace-header">
            <div class="qualifier-workspace-title">
                <div><span class="tag">Clasificatorio</span><span class="status-chip <?php echo $qualifierFinished ? 'ok' : ''; ?>"><?php echo e($qualifierStatusLabel); ?></span></div>
                <h1><?php echo e((string) (($displayQualifierCompetition['name'] ?? '') ?: 'Sin clasificatorio en BD')); ?></h1>
                <p><strong><?php echo e((string) (($displayQualifierCompetition['code'] ?? '') ?: 'Sin codigo')); ?></strong><span><?php echo e((string) (($displayQualifierCompetition['event_ruleset'] ?? '') ?: 'Sin reglamento')); ?></span><span><?php echo e((string) (($displayQualifierCompetition['starts_on'] ?? '') ?: 'Sin fecha')); ?></span></p>
            </div>
            <div class="qualifier-title-actions">
                <button class="btn btn-outline-light fw-bold" type="button" data-qualifier-directory-back><i class="bi bi-arrow-left"></i> Clasificatorios</button>
                <?php if ($displayQualifierId > 0): ?><button class="btn btn-outline-info fw-bold" type="button" data-event-open="<?php echo e((string) $displayQualifierId); ?>"><i class="bi bi-sliders"></i> Editar ficha</button><?php endif; ?>
                <button class="btn btn-warning fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#qualifierStatusModal"><i class="bi bi-flag-fill"></i> Estado del evento</button>
                <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Nuevo</button>
            </div>
        </header>
        <section class="qualifier-overview" aria-label="Resumen del clasificatorio">
            <label class="qualifier-event-picker"><span>Evento</span><select class="form-select" data-qualifier-event-switch><?php foreach ($qualifierCompetitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>" <?php echo (int) $competition['id'] === $displayQualifierId ? 'selected' : ''; ?>><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?><?php if (!$qualifierCompetitions): ?><option value="">Sin clasificatorios</option><?php endif; ?></select></label>
            <article><span>Participantes</span><strong><?php echo count($displayQualifierDecks); ?></strong><small>decks</small></article>
            <article><span>Programados</span><strong><?php echo count($displayQualifierMatches); ?></strong><small>combates</small></article>
            <article><span>Finalizados</span><strong><?php echo $displayQualifierFinishedCount; ?></strong><small>de <?php echo count($displayQualifierMatches); ?></small></article>
            <article><span>Requisito</span><strong><?php echo $displayQualifierRequiredDuels; ?></strong><small>combates por deck</small></article>
        </section>
        <section class="qualifier-view-navigation" aria-label="Vistas del evento seleccionado">
            <header class="qualifier-tab-context">
                <span>Estas tres vistas pertenecen a</span>
                <strong><?php echo e((string) (($displayQualifierCompetition['name'] ?? '') ?: 'Sin clasificatorio seleccionado')); ?></strong>
                <small><?php echo e((string) (($displayQualifierCompetition['code'] ?? '') ?: 'Sin codigo')); ?> · ID <?php echo e((string) $displayQualifierId); ?></small>
            </header>
            <nav class="qualifier-action-tabs" aria-label="Vistas de <?php echo e((string) ($displayQualifierCompetition['name'] ?? 'clasificatorio')); ?>">
                <button class="active" type="button" data-qualifier-tab="configurar"><i class="bi bi-table"></i><span>Ranking</span></button>
                <button type="button" data-qualifier-tab="jugar"><i class="bi bi-controller"></i><span>Combates</span></button>
                <button type="button" data-qualifier-tab="finalizar"><i class="bi bi-trophy"></i><span>Finalizar evento</span></button>
            </nav>
        </section>
        <section class="panel mb-3 qualifier-panel active" data-qualifier-panel="configurar">
            <div class="panel-head"><div><h2>Clasificacion del clasificatorio</h2><p>Orden oficial: puntos, diferencia de duelos, duelos ganados y siembra inicial. Cada deck debe completar <?php echo $displayQualifierRequiredDuels; ?> combates.</p></div><div class="qualifier-ranking-controls"><label>Orden<select class="form-select" data-qualifier-ranking-order><option value="asc">1 al ultimo</option><option value="desc">Ultimo al 1</option></select></label><span class="status-chip"><?php echo $qualifierReadyCount; ?> / <?php echo count($qualifierProgress); ?> con combates completos</span></div></div>
            <div class="qualifier-progress-meter"><span style="width: <?php echo count($qualifierProgress) ? round(($qualifierReadyCount / count($qualifierProgress)) * 100) : 0; ?>%"></span></div>
            <div class="table-responsive">
                <table class="table app-table compact mobile-card-table audit-mobile-table" data-qualifier-ranking-table>
                    <thead><tr><th>#</th><th>Equipo / Deck</th><th>PTS</th><th>PJ</th><th>PG / E / PP</th><th>CG / CP</th><th>DIF</th><th>Combates</th></tr></thead>
                    <tbody data-qualifier-ranking-body>
                    <?php foreach ($qualifierProgress as $row): ?>
                        <tr class="clickable-row" data-qualifier-deck-row data-deck-name="<?php echo e($row['deck']); ?>" data-qualifier-position="<?php echo e((string) $row['rank']); ?>">
                            <td data-label="#"><?php echo e((string) $row['rank']); ?></td>
                            <td data-label="Equipo / Deck"><span class="qualifier-team-identity"><span class="team-inline-badge<?php echo !empty($row['team_logo']) ? ' has-image' : ''; ?>"><?php if (!empty($row['team_logo'])): ?><img loading="lazy" decoding="async" src="<?php echo e((string) $row['team_logo']); ?>" alt="<?php echo e($row['team']); ?>"><?php else: ?><?php echo e((string) ($row['code'] ?: substr($row['team'], 0, 3))); ?><?php endif; ?></span><span><strong><?php echo e($row['team']); ?></strong><small><?php echo e($row['deck']); ?></small></span></span></td>
                            <td data-label="PTS"><strong><?php echo e((string) $row['pts']); ?></strong></td>
                            <td data-label="PJ"><?php echo e((string) $row['pj']); ?></td>
                            <td data-label="PG / E / PP"><?php echo e($row['pg'] . ' / ' . $row['pe'] . ' / ' . $row['pp']); ?></td>
                            <td data-label="CG / CP"><?php echo e($row['cg'] . ' / ' . $row['cp']); ?></td>
                            <td data-label="DIF"><strong><?php echo $row['dp'] > 0 ? '+' : ''; ?><?php echo e((string) $row['dp']); ?></strong></td>
                            <td data-label="Combates"><div class="qualifier-combat-progress <?php echo $row['ready'] ? 'is-complete' : 'is-pending'; ?>"><strong><?php echo e((string) $row['played']); ?> / <?php echo $displayQualifierRequiredDuels; ?></strong><small><?php echo $row['ready'] ? 'Completo' : 'Faltan ' . e((string) $row['remaining']); ?></small><span><i style="width:<?php echo e((string) min(100, round(($row['played'] / max(1, $displayQualifierRequiredDuels)) * 100))); ?>%"></i></span></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$qualifierProgress): ?><tr><td colspan="8"><div class="empty-state">El clasificatorio no tiene decks seleccionados en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="qualifier-stage">
                <section class="panel qualifier-panel" data-qualifier-panel="jugar" hidden>
                    <div class="panel-head">
                        <div><h2>Combates del clasificatorio</h2><p>Consulta la programacion, filtra y abre un combate para registrar o revisar su resultado D1-D5.</p></div>
                        <span class="status-chip"><?php echo count($displayQualifierMatches); ?> combates</span>
                    </div>
                    <div class="result-filter-grid mb-3">
                        <input class="form-control" data-qualifier-match-search placeholder="Buscar por deck, equipo, ID, ronda, mesa o fecha">
                        <select class="form-select" data-qualifier-match-status>
                            <option value="">Todo estado</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="EN CURSO">En curso</option>
                            <option value="FINALIZADA">Finalizada</option>
                        </select>
                        <select class="form-select" data-qualifier-match-round><option value="">Todas las rondas</option></select>
                        <select class="form-select" data-qualifier-match-order><option value="pending_first">Pendientes primero</option><option value="finished_first">Finalizados primero</option><option value="date_asc">Fecha: menor a mayor</option><option value="date_desc">Fecha: mayor a menor</option><option value="round_asc">Ronda: menor a mayor</option><option value="round_desc">Ronda: mayor a menor</option></select>
                        <button class="btn btn-outline-light" type="button" data-qualifier-clear-filters><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table app-table compact">
                            <thead><tr><th>Fecha</th><th>Hora</th><th>Ronda</th><th>Mesa</th><th>Combate</th><th>Estado</th><th>Resultado</th><th></th></tr></thead>
                            <tbody data-qualifier-match-list>
                            <?php foreach ($displayQualifierMatches as $match): ?>
                                <tr class="clickable-row" data-qualifier-match-row data-match-id="<?php echo e((string) $match['id']); ?>">
                                    <td><?php echo e((string) (($match['scheduled_on'] ?? '') ?: 'Sin fecha')); ?></td>
                                    <td><?php echo e((string) (!empty($match['starts_at']) ? substr((string) $match['starts_at'], 0, 5) : '')); ?></td>
                                    <td>R<?php echo e((string) ($match['round_number'] ?? '')); ?></td>
                                    <td>M<?php echo e((string) ($match['mesa'] ?? '')); ?></td>
                                    <td><?php echo e($deckMatchLabel((string) ($match['a'] ?? '')) . ' vs ' . $deckMatchLabel((string) ($match['b'] ?? ''))); ?></td>
                                    <td><span class="status-chip"><?php echo e((string) ($match['estado'] ?? 'PENDIENTE')); ?></span></td>
                                    <td><?php echo e((string) (($match['score'] ?? '') ?: '-')); ?></td>
                                    <td><button class="btn btn-outline-info btn-sm" type="button" data-open-match-sheet data-match-id="<?php echo e((string) $match['id']); ?>"><i class="bi bi-eye"></i> Ficha</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$displayQualifierMatches): ?><tr><td colspan="8"><div class="empty-state">Este clasificatorio no tiene duelos programados.</div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="panel qualifier-panel" data-qualifier-panel="finalizar" hidden>
                    <div class="panel-head">
                        <div><h2>Finalizar <?php echo e((string) ($displayQualifierCompetition['name'] ?? 'clasificatorio')); ?></h2><p>Esta vista comprueba que todos los combates y requisitos estén completos antes de cerrar definitivamente el evento. No sirve para capturar resultados.</p></div>
                        <span class="status-chip <?php echo $qualifierFinalizeCheck['ok'] ? 'ok' : 'warn'; ?>"><?php echo $qualifierFinalizeCheck['ok'] ? 'Listo para cerrar' : 'Cierre bloqueado'; ?></span>
                    </div>
                    <div class="rule-note mb-3 <?php echo $qualifierFinalizeCheck['ok'] ? 'ok' : 'warn'; ?>">
                        <i class="bi <?php echo $qualifierFinalizeCheck['ok'] ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i>
                        <p><?php echo e($qualifierFinalizeCheck['ok'] ? 'Todos los requisitos de este evento estan completos.' : implode(' ', $qualifierFinalizeCheck['reasons'])); ?></p>
                    </div>
                    <?php if ($dbStatus['connected']): ?>
                        <form method="post" class="qualifier-finalize-action">
                            <input type="hidden" name="action" value="set_qualifier_status">
                            <input type="hidden" name="qualifier_competition_id" value="<?php echo e((string) $displayQualifierId); ?>">
                            <button class="btn btn-warning fw-bold" name="status" value="completed" type="submit" <?php echo $qualifierFinalizeCheck['ok'] ? '' : 'disabled'; ?>><i class="bi bi-flag-fill"></i> Finalizar clasificatorio</button>
                            <small><?php echo $qualifierFinalizeCheck['ok'] ? 'El cierre guardara la clasificacion definitiva de este evento.' : 'El boton se habilitara cuando se completen los requisitos indicados.'; ?></small>
                        </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table app-table compact qualifier-completion-table mobile-card-table" data-qualifier-finalize-table>
                            <thead><tr><th>Equipo / Deck</th><th>Cumplimiento de combates</th></tr></thead>
                            <tbody>
                            <?php foreach ($qualifierProgress as $row): ?>
                                <tr class="<?php echo $row['ready'] ? 'is-complete' : 'is-pending'; ?>"><td data-label="Equipo / Deck"><span class="qualifier-team-identity"><span class="team-inline-badge<?php echo !empty($row['team_logo']) ? ' has-image' : ''; ?>"><?php if (!empty($row['team_logo'])): ?><img loading="lazy" decoding="async" src="<?php echo e((string) $row['team_logo']); ?>" alt="<?php echo e($row['team']); ?>"><?php else: ?><?php echo e((string) ($row['code'] ?: substr($row['team'], 0, 3))); ?><?php endif; ?></span><span><strong><?php echo e($row['team']); ?></strong><small><?php echo e($row['deck']); ?></small></span></span></td><td data-label="Cumplimiento"><span class="qualifier-completion-badge <?php echo $row['ready'] ? 'is-complete' : 'is-pending'; ?>"><i class="bi <?php echo $row['ready'] ? 'bi-check-circle-fill' : 'bi-hourglass-split'; ?>"></i><strong><?php echo e((string) $row['played']); ?> / <?php echo $displayQualifierRequiredDuels; ?></strong><small><?php echo $row['ready'] ? 'Todos los combates completos' : 'Faltan ' . e((string) $row['remaining']) . ' ' . ($row['remaining'] === 1 ? 'combate' : 'combates'); ?></small></span></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$qualifierProgress): ?><tr><td colspan="2"><div class="empty-state">Este clasificatorio no tiene participantes.</div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
        </div>
        </div>
    </section>

