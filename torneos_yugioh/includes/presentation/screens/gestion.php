    <section class="screen" data-screen="gestion" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.gestion', 'title', 'Configuracion')); ?></h1><p><?php echo e($uiText('route.gestion', 'subtitle', 'Administracion central de eventos, formatos, reglas, calendario, resultados, decks y equipos.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear evento</button>
        </div>
        <?php if (!$dbStatus['connected']): ?><div class="alert alert-warning">La edicion requiere conexion a MySQL.</div><?php endif; ?>
        <div class="module-kpi-strip gestion-overview-strip mb-3">
            <article><i class="bi bi-diagram-3"></i><span>Eventos</span><b><?php echo number_format(count($competitions)); ?></b></article>
            <article><i class="bi bi-trophy"></i><span>Formatos</span><b><?php echo number_format(count($displayDivisions)); ?></b></article>
            <article><i class="bi bi-collection"></i><span>Decks</span><b><?php echo number_format(count($allDecks)); ?></b></article>
            <article><i class="bi bi-shield"></i><span>Equipos</span><b><?php echo number_format(count($teams)); ?></b></article>
            <article><i class="bi bi-journal-check"></i><span>Reglamentos</span><b><?php echo number_format(count($rulesets)); ?></b></article>
            <article><i class="bi bi-sliders"></i><span>Reglas</span><b><?php echo number_format(count($gameRules)); ?></b></article>
        </div>
        <div class="gestion-mode-badge mb-3"><i class="bi bi-sliders"></i><span>Edicion habilitada para toda la interfaz</span></div>
        <section class="gestion-command-panel panel mb-3">
            <div class="panel-head">
                <div><h2>Centro de mando</h2><p>Gestiona el flujo completo: clasificatorio vivo, divisiones oficiales, calendario y resultados.</p></div>
                <span class="status-chip"><?php echo e($qualifierStatusLabel); ?></span>
            </div>
            <div class="gestion-command-grid">
                <article><i class="bi bi-diagram-3"></i><small>Clasificatorio</small><strong><?php echo e($qualifierStatusLabel); ?></strong><button class="btn btn-outline-light btn-sm" type="button" data-route="clasificatorio">Abrir</button></article>
                <article><i class="bi bi-collection"></i><small>Decks / equipos</small><strong><?php echo count($allDecks); ?> / <?php echo count($teams); ?></strong><button class="btn btn-outline-light btn-sm" type="button" data-route="deck">Fichas</button></article>
                <article><i class="bi bi-calendar2-week"></i><small>Calendario</small><strong><?php echo e((string) ($dbStatus['counts']['matches'] ?? 0)); ?> combates</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="calendario">Programar</button></article>
            </div>
        </section>
        <nav class="gestion-tabs mb-3" aria-label="Modulos de gestion">
            <button class="active" type="button" data-gestion-tab="resumen"><i class="bi bi-grid"></i> Resumen</button>
            <button type="button" data-gestion-tab="competiciones"><i class="bi bi-diagram-3"></i> Eventos</button>
            <button type="button" data-gestion-tab="calendario"><i class="bi bi-calendar2-week"></i> Calendario</button>
            <button type="button" data-gestion-tab="resultados"><i class="bi bi-plus-circle"></i> Resultados</button>
            <button type="button" data-gestion-tab="textos"><i class="bi bi-type"></i> Textos / opciones</button>
            <button type="button" data-gestion-tab="cartas"><i class="bi bi-image"></i> Cartas/media</button>
            <button type="button" data-gestion-tab="auditoria"><i class="bi bi-clock-history"></i> Auditoria</button>
        </nav>
        <div class="gestion-grid">
            <section class="panel gestion-module" data-gestion-module="textos">
                <div class="panel-head">
                    <div>
                        <h2><?php echo e($uiText('gestion.labels_title', 'title', 'Titulos y opciones dinamicas')); ?></h2>
                        <p><?php echo e($uiText('gestion.labels_title', 'subtitle', 'Edita textos visibles y catalogos de opciones persistidos en base de datos.')); ?></p>
                    </div>
                    <span class="status-chip"><?php echo count($uiLabels); ?> textos</span>
                </div>
                <form method="post" class="gestion-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_ui_labels">
                    <div class="table-responsive">
                        <table class="table table-dark table-sm align-middle admin-table">
                            <thead><tr><th>Clave</th><th>Titulo visible</th><th>Descripcion</th><th>Activo</th></tr></thead>
                            <tbody>
                                <?php foreach ($uiLabels as $label): ?>
                                    <tr>
                                        <td><code><?php echo e((string) ($label['key'] ?? '')); ?></code><input type="hidden" name="ui_label_key[]" value="<?php echo e((string) ($label['key'] ?? '')); ?>"></td>
                                        <td><input class="form-control form-control-sm" name="ui_label_title[]" value="<?php echo e((string) ($label['title'] ?? '')); ?>" required></td>
                                        <td><input class="form-control form-control-sm" name="ui_label_subtitle[]" value="<?php echo e((string) ($label['subtitle'] ?? '')); ?>"></td>
                                        <td class="text-center"><input type="checkbox" name="ui_label_active[]" value="<?php echo e((string) ($label['key'] ?? '')); ?>" <?php echo !empty($label['active']) ? 'checked' : ''; ?>></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar titulos</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="save_catalog_items">
                    <div class="panel-head compact-head">
                        <div><h3>Catalogos de opciones</h3><p>Estas opciones alimentan selectores de eventos, estados, reglas y patrones.</p></div>
                        <span class="status-chip"><?php echo array_sum(array_map('count', $catalogGroups)); ?> opciones</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm align-middle admin-table">
                            <thead><tr><th>Catalogo</th><th>Codigo</th><th>Etiqueta</th><th>Icono</th><th>Color</th><th>Orden</th><th>Activo</th></tr></thead>
                            <tbody>
                                <?php foreach ($catalogGroups as $catalogKey => $catalogItems): ?>
                                    <?php foreach ($catalogItems as $catalogItem): ?>
                                        <tr>
                                            <td><code><?php echo e((string) $catalogKey); ?></code><input type="hidden" name="catalog_id[]" value="<?php echo e((string) ($catalogItem['id'] ?? 0)); ?>"></td>
                                            <td><code><?php echo e((string) ($catalogItem['code'] ?? '')); ?></code></td>
                                            <td><input class="form-control form-control-sm" name="catalog_label[]" value="<?php echo e((string) ($catalogItem['label'] ?? '')); ?>" required></td>
                                            <td><input class="form-control form-control-sm" name="catalog_icon[]" value="<?php echo e((string) ($catalogItem['icon'] ?? '')); ?>" placeholder="bi-trophy"></td>
                                            <td><input class="form-control form-control-sm" name="catalog_accent[]" value="<?php echo e((string) ($catalogItem['accent'] ?? '')); ?>" placeholder="#36d8ff"></td>
                                            <td><input class="form-control form-control-sm" type="number" min="0" name="catalog_sort_order[]" value="<?php echo e((string) ($catalogItem['sort_order'] ?? 100)); ?>"></td>
                                            <td class="text-center"><input type="checkbox" name="catalog_active[]" value="<?php echo e((string) ($catalogItem['id'] ?? 0)); ?>" <?php echo !empty($catalogItem['active']) ? 'checked' : ''; ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="panel-head compact-head"><div><h3>Nueva opcion</h3><p>Agrega una opcion a cualquier catalogo existente o nuevo.</p></div></div>
                    <div class="three-col">
                        <label>Catalogo<input class="form-control" name="new_catalog_key" placeholder="competition_type"></label>
                        <label>Codigo<input class="form-control" name="new_catalog_code" placeholder="friendly"></label>
                        <label>Etiqueta<input class="form-control" name="new_catalog_label" placeholder="Amistoso"></label>
                    </div>
                    <div class="three-col">
                        <label>Icono<input class="form-control" name="new_catalog_icon" placeholder="bi-controller"></label>
                        <label>Color<input class="form-control" name="new_catalog_accent" placeholder="#36d8ff"></label>
                        <label>Orden<input class="form-control" type="number" min="0" name="new_catalog_sort_order" value="100"></label>
                    </div>
                    <button class="btn btn-warning fw-bold" type="submit"><i class="bi bi-save"></i> Guardar opciones</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="equipos">
                <div class="panel-head"><div><h2>Equipos</h2><p>Un equipo puede representar uno o varios decks.</p></div><span class="status-chip"><?php echo count($teams); ?> activos</span></div>
                <div class="team-deck-save-row">
                    <span>La creacion, edicion y asociacion de decks se gestiona desde la ficha modal.</span>
                    <button class="btn btn-primary fw-bold" type="button" data-open-team-sheet=""><i class="bi bi-shield-plus"></i> Abrir ficha de equipo</button>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="decks">
                <div class="panel-head"><div><h2>Decks</h2><p>Ficha editable: equipo, formato, cartas, variaciones, estado y notas.</p></div><span class="status-chip"><?php echo count($allDecks); ?> activos</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_deck">
                    <input type="hidden" name="deck_id" data-gestion-deck-id>
                    <label>Editar deck existente<select class="form-select" data-gestion-deck-select><option value="">Nuevo deck</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <label>Nombre del deck<input class="form-control" name="deck_name" data-gestion-deck-name required></label>
                    <div class="two-col">
                        <label>Equipo asociado<input class="form-control" data-gestion-deck-team-label value="Sin equipo" readonly></label>
                        <label>Division<select class="form-select" name="division_id" data-gestion-deck-division><option value="">Sin division</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Semilla interna<input class="form-control" type="number" min="1" name="seed_rank" data-gestion-deck-rank value="999"><small>Sólo se usa para programación; no representa el ranking global.</small></label>
                        <label>Estilo<input class="form-control" name="style" data-gestion-deck-style placeholder="Combo, control, midrange"></label>
                    </div>
                    <label>Arquetipo<input class="form-control" name="archetype" data-gestion-deck-archetype></label>
                    <label>Motivo / resumen<input class="form-control" name="reason" data-gestion-deck-reason></label>
                    <label>Cartas clave<textarea class="form-control" name="key_cards" data-gestion-deck-cards rows="2"></textarea></label>
                    <label>Variaciones<textarea class="form-control" name="variations" data-gestion-deck-variations rows="2"></textarea></label>
                    <label>Notas / ficha<textarea class="form-control" name="notes" data-gestion-deck-notes rows="3"></textarea></label>
                    <input type="hidden" name="cover_url" data-gestion-deck-cover>
                    <label>Subir logo del deck<input class="form-control" type="file" name="deck_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>PNG, JPG, WEBP o GIF. Si subes uno nuevo, reemplaza la imagen actual del deck.</small></label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar deck</button>
                    </div>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="remove_deck">
                    <input type="hidden" name="deck_id" data-gestion-remove-deck-id>
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Quitar deck seleccionado</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="competiciones">
                <div class="panel-head"><div><h2>Eventos competitivos</h2><p>Administra clasificatorios, copas, torneos, ligas y eventos por reglamento con prerequisitos y reglas propias.</p></div><span class="status-chip"><?php echo count($competitions); ?> activos</span></div>
                <div class="event-admin-redirect">
                    <i class="bi bi-plus-circle"></i>
                    <div><strong>La creacion de eventos ahora esta en una pantalla unica.</strong><span>Desde aqui administra prerequisitos y archivo de eventos existentes.</span></div>
                    <button class="btn btn-primary btn-sm" type="button" data-route="crear_evento">Crear / editar evento</button>
                </div>
                <form method="post" class="gestion-form" hidden>
                    <input type="hidden" name="action" value="save_competition">
                    <?php $seasonFieldMode = "edit"; require YGO_APP_ROOT . "/includes/season-event-field.php"; ?>
                    <input type="hidden" name="competition_id" data-gestion-competition-id>
                    <label>Editar evento<select class="form-select" data-gestion-competition-select><option value="">Nuevo evento</option><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Codigo<input class="form-control" name="competition_code" data-gestion-competition-code required></label>
                        <label>Nombre<input class="form-control" name="competition_name" data-gestion-competition-name required></label>
                    </div>
                    <div class="two-col">
                        <label>Tipo de evento<select class="form-select" name="competition_type" data-gestion-competition-type><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select></label>
                        <label>Alcance<select class="form-select" name="competition_scope" data-gestion-competition-scope><option value="division">Asociada a formato</option><option value="global">Global / sin formato</option><option value="cross_division">Cruza formatos</option><option value="team_event">Por equipos</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Formato asociado / division principal<select class="form-select" name="competition_division_id" data-gestion-competition-division><option value="">General</option><optgroup label="Formatos disponibles"><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="format:<?php echo e((string) $rulesetCode); ?>" data-ruleset="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></optgroup><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>" data-ruleset="<?php echo e((string) ($division['ruleset'] ?? $division['event_ruleset'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
                        <label data-gestion-league-divisions hidden>Divisiones asociadas a la liga<select class="form-select" name="competition_division_ids[]" data-gestion-league-divisions-select multiple size="4"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e((string) (($division['name'] ?? '') ?: ($division['short'] ?? 'Division'))); ?></option><?php endforeach; ?></select><small>Selecciona todas las divisiones que pertenecen a esta liga.</small></label>
                        <label>Sistema de torneo<input class="form-control" name="competition_format" data-gestion-competition-format placeholder="liga, suizo, eliminacion directa, grupos"></label>
                    </div>
                    <div class="two-col">
                        <label>Categoria base<select class="form-select" name="competition_event_ruleset" data-gestion-competition-ruleset><?php foreach ($eventRulesetOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e($rulesetCode); ?>"><?php echo e($rulesetLabel); ?></option><?php endforeach; ?></select></label>
                        <label>Modo de participantes<select class="form-select" name="competition_deck_pool" data-gestion-competition-deck-pool><option value="all_active">Libre: todos los decks/equipos</option><option value="division">Division completa</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Formato de match<select class="form-select" name="competition_match_format" data-gestion-competition-match-format><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>" data-participants="<?php echo e((string) ($matchType['participants_per_side'] ?? 1)); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select></label>
                        <label>Mejor de<input class="form-control" type="number" min="1" max="5" name="competition_best_of" data-gestion-competition-best-of value="5"></label>
                    </div>
                    <div class="two-col">
                        <label>Participantes por lado<input class="form-control" type="number" min="1" max="3" name="competition_participants_per_side" data-gestion-competition-participants value="1"></label>
                        <label>Programacion<select class="form-select" name="competition_schedule_strategy" data-gestion-competition-schedule><option value="manual">Manual</option><option value="auto_round_robin">Liga completa automatica</option><option value="auto_knockout">Llave automatica</option><option value="auto_swiss">Rondas suizas</option><option value="friendly_rotation">Amistosos rotativos</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Estado<select class="form-select" name="competition_status" data-gestion-competition-status><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                        <label>Color / acento<input class="form-control" name="competition_accent" data-gestion-competition-accent placeholder="#36d8ff"></label>
                    </div>
                    <div class="two-col">
                        <label>Inicio<input class="form-control" type="date" name="starts_on" data-gestion-competition-start></label>
                        <label>Fin<input class="form-control" type="date" name="ends_on" data-gestion-competition-end></label>
                    </div>
                    <div class="three-col">
                        <label>Condicion de victoria<input class="form-control" name="competition_victory_condition" data-gestion-competition-victory placeholder="Dos victorias consecutivas o ventaja al cierre"></label>
                        <label>Condicion de derrota<input class="form-control" name="competition_defeat_condition" data-gestion-competition-defeat placeholder="Menor marcador al cierre"></label>
                        <label>Condicion de empate<input class="form-control" name="competition_draw_condition" data-gestion-competition-draw placeholder="D5 empatado o dos empates consecutivos"></label>
                    </div>
                    <label>Reglas<textarea class="form-control" name="competition_rules" data-gestion-competition-rules rows="3"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar evento</button>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="archive_competition">
                    <input type="hidden" name="competition_id" data-gestion-archive-competition-id>
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-archive"></i> Archivar evento seleccionado</button>
                </form>
                <div class="dependency-board mt-3">
                    <div class="panel-head compact-head"><div><h3>Prerequisitos de ejecucion</h3><p>Define que torneo, copa o liga debe terminar antes de habilitar otra competencia.</p></div></div>
                    <form method="post" class="gestion-form dependency-form">
                        <input type="hidden" name="action" value="save_competition_dependency">
                        <label>Evento a bloquear<select class="form-select" name="dependency_competition_id" required><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Debe cumplirse primero<select class="form-select" name="dependency_depends_on_id" required><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Condicion<select class="form-select" name="dependency_condition"><option value="completed_with_results">Finalizada con resultados</option><option value="completed">Solo finalizada</option><option value="has_results">Con algun resultado</option><option value="top_n">Top N entra al evento</option><option value="winner_to_final">Ganador entra a final</option><option value="defending_champion">Campeon defensor habilita evento</option><option value="creates_divisions">Clasificatorio crea divisiones</option></select></label>
                        <label>Cantidad Top N<input class="form-control" type="number" min="0" max="128" name="dependency_take_count" value="4"><small>Solo aplica cuando la condicion sea Top N.</small></label>
                        <button class="btn btn-warning fw-bold" type="submit"><i class="bi bi-link-45deg"></i> Guardar regla</button>
                    </form>
                    <div class="dependency-list">
                        <?php foreach ($competitions as $competition): ?>
                            <?php foreach (($competition['dependencies'] ?? []) as $dependency): ?>
                                <article class="<?php echo !empty($dependency['ok']) ? 'ready' : 'blocked'; ?>">
                                    <div>
                                        <b><?php echo e((string) $competition['name']); ?></b>
                                        <span>depende de <?php echo e((string) $dependency['name']); ?> | <?php echo e((string) $dependency['condition_type']); ?><?php echo !empty($dependency['take_count']) ? ' | Top ' . e((string) $dependency['take_count']) : ''; ?></span>
                                    </div>
                                    <em><?php echo !empty($dependency['ok']) ? 'OK' : 'Pendiente'; ?></em>
                                    <form method="post">
                                        <input type="hidden" name="action" value="delete_competition_dependency">
                                        <input type="hidden" name="dependency_id" value="<?php echo e((string) $dependency['id']); ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if (!$competitionDependencies): ?><p class="note">Aun no hay prerequisitos. Ejemplo: Torneo XY puede depender de que el clasificatorio termine con resultados.</p><?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="divisiones">
                <div class="panel-head"><div><h2>Divisiones</h2><p>CRUD de divisiones: nombre, orden, cupos, ascensos/descensos y decks asignados.</p></div><span class="status-chip"><?php echo count($displayDivisions); ?> configuradas</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_division">
                    <label>Editar division<select class="form-select" name="division_id" data-gestion-division-select><option value="">Nueva division</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) $division['id']); ?>"><?php echo e($division['short']); ?> - <?php echo e($division['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Codigo<input class="form-control" name="division_code" data-gestion-division-code required></label>
                        <label>Orden<input class="form-control" type="number" min="1" name="division_tier" data-gestion-division-tier required></label>
                    </div>
                    <label>Nombre<input class="form-control" name="division_name" data-gestion-division-name required></label>
                    <label>Nombre competitivo / copa asociada<input class="form-control" name="division_cup" data-gestion-division-cup required></label>
                    <div class="two-col">
                        <label>Liga asociada<select class="form-select" name="division_league_competition_id" data-gestion-division-league><option value="">Modo libre / sin liga</option><?php foreach ($competitions as $competition): ?><?php if ((string) ($competition['type'] ?? '') !== 'league') { continue; } ?><option value="<?php echo e((string) ($competition['id'] ?? '')); ?>"><?php echo e((string) ($competition['name'] ?? 'Liga')); ?></option><?php endforeach; ?></select><small>La liga es el contenedor principal; la division agrupa equipos dentro de esa liga.</small></label>
                        <label>Clasificatorio origen<select class="form-select" name="division_source_qualifier_id" data-gestion-division-source><option value="">Manual / sin clasificatorio</option><?php foreach ($competitions as $competition): ?><?php if ((string) ($competition['type'] ?? '') !== 'qualifier') { continue; } ?><option value="<?php echo e((string) ($competition['id'] ?? '')); ?>"><?php echo e((string) ($competition['name'] ?? 'Clasificatorio')); ?> | <?php echo e(ygo_event_status_label((string) ($competition['status'] ?? ''))); ?></option><?php endforeach; ?></select><small>Usalo cuando la division nace del resultado final de un clasificatorio.</small></label>
                    </div>
                    <div class="two-col">
                        <label>Desde posicion<input class="form-control" type="number" min="1" name="division_rank_from" data-gestion-division-rank-from placeholder="Ej: 1"></label>
                        <label>Hasta posicion<input class="form-control" type="number" min="1" name="division_rank_to" data-gestion-division-rank-to placeholder="Ej: 10"></label>
                    </div>
                    <div class="two-col">
                        <label>Cupos objetivo<input class="form-control" type="number" min="0" name="division_size" data-gestion-division-size></label>
                        <label>Color<input class="form-control" name="division_accent" data-gestion-division-accent placeholder="#36d8ff"></label>
                    </div>
                    <label>Logo de division<input class="form-control" type="file" name="division_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>Se guarda en el servidor dentro de assets/uploads/divisions/division-ID.</small></label>
                    <div class="two-col">
                        <label>Inicio<input class="form-control" type="date" name="division_starts_on" data-gestion-division-start></label>
                        <label>Fin<input class="form-control" type="date" name="division_ends_on" data-gestion-division-end></label>
                    </div>
                    <div class="two-col">
                        <label>Cupos de ascenso<input class="form-control" type="number" min="0" name="division_promotion_slots" data-gestion-division-promotion-slots></label>
                        <label>Cupos de descenso<input class="form-control" type="number" min="0" name="division_relegation_slots" data-gestion-division-relegation-slots></label>
                    </div>
                    <div class="two-col">
                        <label>Sube hacia<select class="form-select" name="division_promotion_to" data-gestion-division-promotion-to><option value="">Sin ascenso</option><?php foreach ($displayDivisions as $targetDivision): ?><option value="<?php echo e((string) $targetDivision['id']); ?>"><?php echo e($targetDivision['short']); ?></option><?php endforeach; ?></select></label>
                        <label>Baja hacia<select class="form-select" name="division_relegation_to" data-gestion-division-relegation-to><option value="">Sin descenso</option><?php foreach ($displayDivisions as $targetDivision): ?><option value="<?php echo e((string) $targetDivision['id']); ?>"><?php echo e($targetDivision['short']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label>Descripcion<textarea class="form-control" name="division_description" data-gestion-division-description rows="3"></textarea></label>
                    <div class="division-deck-admin">
                        <div class="panel-head compact-head"><div><h3>Decks de la division</h3><p>Selecciona minimo 4 si vas a reasignar decks a esta division.</p></div><span class="status-chip" data-division-selected-count>0 seleccionados</span></div>
                        <input class="form-control" type="search" data-division-deck-search placeholder="Buscar deck o equipo">
                        <div class="deck-picker-row">
                            <select class="form-select" data-division-deck-picker>
                                <option value="">Selecciona un deck</option>
                                <?php foreach ($allDecks as $deck): ?>
                                    <option value="<?php echo e((string) ($deck['id'] ?? '')); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-search="<?php echo e(strtolower((string) (($deck['name'] ?? '') . ' ' . ($deck['team'] ?? '') . ' ' . ($deck['division'] ?? '')))); ?>"><?php echo e((string) (($deck['team'] ?? 'Sin equipo') . ' - ' . ($deck['name'] ?? 'Deck') . ' | ' . ($deck['division'] ?? 'Sin division'))); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="button" data-division-deck-add><i class="bi bi-plus-circle"></i> Agregar</button>
                        </div>
                        <div class="selected-deck-list" data-division-selected-decks></div>
                        <div data-division-hidden-decks hidden></div>
                    </div>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar division</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="calendario">
                <div class="panel-head"><div><h2>Calendario de combates</h2><p>Programa fecha, hora, sede, juez y notas para cualquier combate.</p></div><span class="status-chip"><?php echo count($matchOptions); ?> combates</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_match_schedule">
                    <label>Combate<select class="form-select" name="schedule_match_id" data-gestion-match-select><?php foreach ($matchOptions as $option): ?><?php if ($option['id'] > 0): ?><option value="<?php echo e((string) $option['id']); ?>"><?php echo e($option['label']); ?></option><?php endif; ?><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Fecha<input class="form-control" type="date" name="scheduled_on" data-gestion-match-date></label>
                        <label>Hora<input class="form-control" type="time" name="starts_at" data-gestion-match-time></label>
                    </div>
                    <div class="two-col">
                        <label>Sede<input class="form-control" name="venue" data-gestion-match-venue placeholder="Station X Tulua"></label>
                        <label>Juez / encargado<input class="form-control" name="judge" data-gestion-match-judge></label>
                    </div>
                    <label>Notas<textarea class="form-control" name="match_notes" data-gestion-match-notes rows="3"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-calendar-check"></i> Guardar fecha</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="panel-head compact-head"><div><h3>Reglas de ejecucion de duelos</h3><p>Controla si se pueden registrar resultados fuera de la fecha programada. Se guarda en MySQL por temporada.</p></div></div>
                    <label class="toggle-line"><input type="hidden" name="result_date_guard" value="0"><input type="checkbox" name="result_date_guard" value="1" <?php echo ygo_setting_enabled($settings, 'result_date_guard') ? 'checked' : ''; ?>> Validar fecha programada al guardar resultados</label>
                    <label class="toggle-line"><input type="hidden" name="allow_early_results" value="0"><input type="checkbox" name="allow_early_results" value="1" <?php echo ygo_setting_enabled($settings, 'allow_early_results') ? 'checked' : ''; ?>> Permitir adelantar duelos antes de su fecha</label>
                    <label class="toggle-line"><input type="hidden" name="allow_overdue_results" value="0"><input type="checkbox" name="allow_overdue_results" value="1" <?php echo ygo_setting_enabled($settings, 'allow_overdue_results') ? 'checked' : ''; ?>> Permitir registrar duelos vencidos</label>
                    <label>Dias para alerta de proximos duelos<input class="form-control" type="number" min="1" max="30" name="alert_days_ahead" value="<?php echo e((string) (($settings['alert_days_ahead'] ?? '') ?: '3')); ?>"></label>
                    <?php $validWeekdays = array_filter(array_map('trim', explode(',', (string) ($settings['valid_weekdays'] ?? '')))); ?>
                    <div class="weekday-picker">
                        <span>Dias validos de juego</span>
                        <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 0 => 'Dom'] as $dayValue => $dayLabel): ?>
                            <label><input type="checkbox" name="valid_weekdays[]" value="<?php echo e((string) $dayValue); ?>" <?php echo in_array((string) $dayValue, $validWeekdays, true) ? 'checked' : ''; ?>> <?php echo e($dayLabel); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="two-col">
                        <label>Hora minima<input class="form-control" type="time" name="allowed_start_time" value="<?php echo e(substr((string) (($settings['allowed_start_time'] ?? '') ?: '10:00'), 0, 5)); ?>"></label>
                        <label>Hora maxima<input class="form-control" type="time" name="allowed_end_time" value="<?php echo e(substr((string) (($settings['allowed_end_time'] ?? '') ?: '22:00'), 0, 5)); ?>"></label>
                    </div>
                    <label>Capacidad global de combates por dia<input class="form-control" type="number" min="1" max="3" name="max_matches_per_day" value="<?php echo e((string) (($settings['max_matches_per_day'] ?? '') ?: '3')); ?>"><small>El clasificatorio cede cupos cuando comparte fecha con otros eventos.</small></label>
                    <label class="toggle-line"><input type="hidden" name="allow_special_days" value="0"><input type="checkbox" name="allow_special_days" value="1" <?php echo ygo_setting_enabled($settings, 'allow_special_days') ? 'checked' : ''; ?>> Permitir dias especiales fuera de los dias validos</label>
                    <label>Dias especiales<textarea class="form-control" name="special_days" rows="2" placeholder="2026-09-08, 2026-09-15"><?php echo e((string) ($settings['special_days'] ?? '')); ?></textarea></label>
                    <label>Sedes disponibles<textarea class="form-control" name="venues" rows="2" placeholder="Station X Tulua"><?php echo e((string) ($settings['venues'] ?? 'Station X Tulua')); ?></textarea></label>
                    <div class="panel-head compact-head mt-3"><div><h3>Formulas competitivas</h3><p>Parametros usados por ranking Elo, metagame, tiers, actualizacion automatica y alertas.</p></div></div>
                    <div class="settings-metric-grid">
                        <label>Muestra minima<input class="form-control" type="number" min="1" max="100" name="stats_minimum_sample" value="<?php echo e((string) ($settings['stats_minimum_sample'] ?? '3')); ?>"></label>
                        <label>Muestra minima por tier<input class="form-control" type="number" min="1" max="100" name="tier_sample_minimum" value="<?php echo e((string) ($settings['tier_sample_minimum'] ?? '3')); ?>"></label>
                        <label>Partidas para mejor debut<input class="form-control" type="number" min="1" max="100" name="best_debut_min_matches" value="<?php echo e((string) ($settings['best_debut_min_matches'] ?? '3')); ?>"></label>
                        <label>Peso de presencia (%)<input class="form-control" type="number" min="0" max="100" name="meta_presence_weight" value="<?php echo e((string) ($settings['meta_presence_weight'] ?? '50')); ?>"></label>
                        <label>Tier Zero: presencia (%)<input class="form-control" type="number" min="0" max="100" name="tier_zero_presence" value="<?php echo e((string) ($settings['tier_zero_presence'] ?? '35')); ?>"></label>
                        <label>Tier Zero: winrate (%)<input class="form-control" type="number" min="0" max="100" name="tier_zero_winrate" value="<?php echo e((string) ($settings['tier_zero_winrate'] ?? '65')); ?>"></label>
                        <label>Tier 1: indice minimo<input class="form-control" type="number" min="0" max="100" name="tier_one_score" value="<?php echo e((string) ($settings['tier_one_score'] ?? '70')); ?>"></label>
                        <label>Tier 2: indice minimo<input class="form-control" type="number" min="0" max="100" name="tier_two_score" value="<?php echo e((string) ($settings['tier_two_score'] ?? '45')); ?>"></label>
                        <label>Factor K de Elo<input class="form-control" type="number" min="1" max="128" name="elo_k_factor" value="<?php echo e((string) ($settings['elo_k_factor'] ?? '32')); ?>"></label>
                        <label>Actualizar centro (segundos)<input class="form-control" type="number" min="5" max="30" name="live_refresh_seconds" value="<?php echo e((string) ($settings['live_refresh_seconds'] ?? '15')); ?>"></label>
                        <label>Aviso previo (minutos)<input class="form-control" type="number" min="1" max="30" name="notification_minutes_before" value="<?php echo e((string) ($settings['notification_minutes_before'] ?? '30')); ?>"></label>
                    </div>
                    <button class="btn btn-outline-warning fw-bold" type="submit"><i class="bi bi-shield-check"></i> Guardar reglas de ejecucion</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="auto_schedule_qualifier">
                    <div class="panel-head compact-head"><div><h3>Programar clasificatorio automaticamente</h3><p>Asigna fechas a todos los combates clasificatorios pendientes.</p></div></div>
                    <div class="two-col">
                        <label>Clasificatorio<select class="form-select" name="qualifier_competition_id"><?php foreach ($qualifierCompetitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Fecha inicial<input class="form-control" type="date" name="qualifier_schedule_start" value="<?php echo e((new DateTimeImmutable())->format('Y-m-d')); ?>"></label>
                    </div>
                    <div class="two-col">
                        <label>Hora inicial<input class="form-control" type="time" name="qualifier_schedule_time" value="19:00"></label>
                        <label>Mesas por dia<input class="form-control" type="number" min="1" max="12" name="qualifier_schedule_tables" value="6"></label>
                        <label>Minutos por mesa<input class="form-control" type="number" min="20" max="180" name="qualifier_schedule_minutes" value="45"></label>
                    </div>
                    <button class="btn btn-outline-info fw-bold" type="submit"><i class="bi bi-magic"></i> Autoprogramar clasificatorio</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="rebuild_qualifier_calendar">
                    <div class="panel-head compact-head"><div><h3>Reconstruir clasificatorio oficial</h3><p>65 combates unicos: inicia hoy y corre todas las fechas hacia adelante.</p></div></div>
                    <div class="two-col">
                        <label>Clasificatorio<select class="form-select" name="qualifier_competition_id"><?php foreach ($qualifierCompetitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Fecha inicial<input class="form-control" type="date" name="qualifier_schedule_start" value="<?php echo e((new DateTimeImmutable())->format('Y-m-d')); ?>"></label>
                    </div>
                    <div class="two-col">
                        <label>Hora inicial<input class="form-control" type="time" name="qualifier_schedule_time" value="19:00"></label>
                        <label>Combates por jornada<input class="form-control" type="number" min="1" max="5" name="qualifier_matches_per_day" value="1"></label>
                        <label>Intervalo en dias<input class="form-control" type="number" min="1" max="7" name="qualifier_day_interval" value="1"></label>
                    </div>
                    <input type="hidden" name="qualifier_schedule_minutes" value="45">
                    <button class="btn btn-warning fw-bold" type="submit"><i class="bi bi-calendar2-check"></i> Aplicar calendario oficial</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="resumen">
                <div class="panel-head"><div><h2>Generadores</h2><p>Crea estructuras base sin escribir SQL: amistosos, copas y mundial.</p></div><span class="status-chip">Automatizacion</span></div>
                <div class="generator-actions">
                    <form method="post"><input type="hidden" name="action" value="generate_friendlies"><button class="btn btn-outline-info w-100" type="submit"><i class="bi bi-shuffle"></i> Generar amistosos</button></form>
                    <form method="post"><input type="hidden" name="action" value="generate_leagues"><button class="btn btn-outline-success w-100" type="submit"><i class="bi bi-calendar2-range"></i> Generar ligas completas</button></form>
                    <form method="post"><input type="hidden" name="action" value="generate_cups"><button class="btn btn-outline-warning w-100" type="submit"><i class="bi bi-diagram-3"></i> Generar copas</button></form>
                    <form method="post"><input type="hidden" name="action" value="generate_world"><button class="btn btn-outline-light w-100" type="submit"><i class="bi bi-globe-americas"></i> Generar mundial</button></form>
                </div>
                <button class="btn btn-outline-info mt-3" type="button" data-route="temporadas">Administrar temporadas</button>
            </section>

            <section class="panel gestion-module" data-gestion-module="resultados">
                <div class="panel-head"><div><h2>Crear partido manual</h2><p>Agrega cruces puntuales para desempate, repechaje, copa o amistoso.</p></div><span class="status-chip">Manual</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="create_match">
                    <div class="two-col">
                        <label>Formato<select class="form-select" name="manual_division_id"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) $division['id']); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                        <label>Fase<select class="form-select" name="manual_phase"><option value="qualifier">Clasificatorio</option><option value="league">Liga</option><option value="friendly">Amistoso</option><option value="cup">Copa</option><option value="world">Mundial</option><option value="eternal">Eternal</option><option value="tiebreaker">Desempate</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Evento<select class="form-select" name="manual_competition_id" data-manual-competition-select><option value="">Sin asociar / general</option><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>" data-type="<?php echo e((string) ($competition['type'] ?? '')); ?>" data-ruleset="<?php echo e((string) ($competition['event_ruleset'] ?? '')); ?>" data-deck-pool="<?php echo e((string) ($competition['deck_pool'] ?? 'all_active')); ?>" data-division-id="<?php echo e((string) ($competition['division_id'] ?? '')); ?>" data-match-format="<?php echo e((string) ($competition['match_format'] ?? '1v1')); ?>" data-best-of="<?php echo e((string) ($competition['best_of'] ?? 5)); ?>" data-participants="<?php echo e((string) ($competition['participants_per_side'] ?? 1)); ?>"><?php echo e((string) $competition['name']); ?> <?php echo !empty($competition['division_code']) ? '(' . e((string) $competition['division_code']) . ')' : '(Global)'; ?></option><?php endforeach; ?></select></label>
                        <label>Formato<select class="form-select" name="manual_match_format"><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Mejor de<input class="form-control" type="number" min="1" max="10" name="manual_best_of" value="5"></label>
                        <label>Participantes por lado<input class="form-control" type="number" min="1" max="3" name="manual_participants_per_side" value="1"></label>
                    </div>
                    <label>Regla para partido sin evento<select class="form-select" name="manual_game_rule_id"><option value="">Usar regla compatible por defecto</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['all_matches', 'friendly', 'match', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select><small>Si eliges un evento, manda la regla del evento. Si queda sin evento o amistoso, usa esta regla.</small></label>
                    <div class="two-col">
                        <label>Ronda<input class="form-control" type="number" min="1" name="manual_round" value="1"></label>
                        <label>Mesa<input class="form-control" type="number" min="1" name="manual_table" value="1"></label>
                    </div>
                    <div class="two-col">
                        <label>Lado A - Deck 1<select class="form-select" name="manual_deck_a_id" data-manual-deck-select><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Lado B - Deck 1<select class="form-select" name="manual_deck_b_id" data-manual-deck-select><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Lado A - Deck 2<select class="form-select" name="manual_deck_c_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Lado B - Deck 2<select class="form-select" name="manual_deck_d_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Lado A - Deck 3<select class="form-select" name="manual_deck_e_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Lado B - Deck 3<select class="form-select" name="manual_deck_f_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <p class="note" data-manual-deck-pool-note>Selecciona un evento para aplicar su pool de decks.</p>
                    <div class="two-col">
                        <label>Fecha<input class="form-control" type="date" name="manual_scheduled_on"></label>
                        <label>Hora<input class="form-control" type="time" name="manual_starts_at"></label>
                    </div>
                    <div class="two-col">
                        <label>Sede<input class="form-control" name="manual_venue" placeholder="Station X Tulua"></label>
                        <label>Juez<input class="form-control" name="manual_judge"></label>
                    </div>
                    <label>Notas<textarea class="form-control" name="manual_notes" rows="2"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-plus-circle"></i> Crear partido</button>
                </form>
                <div class="generator-actions mt-3">
                    <form method="post"><input type="hidden" name="action" value="advance_phase"><input type="hidden" name="phase" value="cup"><button class="btn btn-outline-warning w-100" type="submit"><i class="bi bi-chevron-double-right"></i> Avanzar copa</button></form>
                    <form method="post"><input type="hidden" name="action" value="advance_phase"><input type="hidden" name="phase" value="world"><button class="btn btn-outline-light w-100" type="submit"><i class="bi bi-chevron-double-right"></i> Avanzar mundial</button></form>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="cartas">
                <div class="panel-head"><div><h2>Cartas y media del deck</h2><p>Registra main deck, side deck, variantes, logos e imagenes.</p></div><span class="status-chip">Ficha avanzada</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_deck_card">
                    <label>Deck<select class="form-select" name="card_deck_id"><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Carta<input class="form-control" name="card_name" required></label>
                        <label>Rol<input class="form-control" name="card_role" placeholder="Starter, extender, handtrap"></label>
                    </div>
                    <div class="two-col">
                        <label>Cantidad<input class="form-control" type="number" min="1" max="3" name="card_quantity" value="1"></label>
                        <label>Seccion<select class="form-select" name="card_section"><option value="main">Main</option><option value="side">Side</option><option value="extra">Extra</option><option value="tech">Tech</option></select></label>
                    </div>
                    <label>Notas<input class="form-control" name="card_notes"></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-card-list"></i> Agregar carta</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="import_deck_cards">
                    <div class="panel-head compact-head"><div><h3>Importar lista real completa</h3><p>Una carta por linea: seccion;cantidad;nombre;rol;notas. No se generan cartas ficticias.</p></div></div>
                    <label>Deck<select class="form-select" name="import_card_deck_id"><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <label>Lista de cartas<textarea class="form-control" name="deck_card_rows" rows="8" placeholder="main;3;Nombre exacto;Starter;Notas&#10;extra;1;Nombre exacto;Boss;"></textarea></label>
                    <label class="toggle-line"><input type="checkbox" name="replace_deck_cards" value="1"> Reemplazar la lista actual de este deck</label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-file-earmark-arrow-up"></i> Importar lista</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="save_deck_media">
                    <label>Deck<select class="form-select" name="media_deck_id"><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Tipo<select class="form-select" name="media_type"><option value="image">Imagen</option><option value="logo">Logo</option><option value="video">Video</option></select></label>
                        <label>Titulo<input class="form-control" name="media_title"></label>
                    </div>
                    <label>URL<input class="form-control" name="media_url" required></label>
                    <button class="btn btn-outline-info fw-bold" type="submit"><i class="bi bi-image"></i> Agregar media</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="resumen">
                <div class="panel-head"><div><h2>Palmares</h2><p>Guarda campeones, subcampeones y cierre historico.</p></div><span class="status-chip"><?php echo count($honors); ?> registros</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_honor">
                    <label>Titulo<input class="form-control" name="honor_title" placeholder="Campeon Copa Apex" required></label>
                    <label>Evento<select class="form-select" name="honor_competition_id"><option value="">General</option><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Campeon<select class="form-select" name="honor_champion_deck_id"><option value="">Por definir</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Subcampeon<select class="form-select" name="honor_runner_deck_id"><option value="">Por definir</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label>Notas<textarea class="form-control" name="honor_notes" rows="2"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-trophy"></i> Guardar palmares</button>
                </form>
            </section>

            <section class="panel audit-panel gestion-module" data-gestion-module="auditoria">
                <div class="panel-head"><div><h2>Auditoria</h2><p>Ultimas acciones registradas del sistema.</p></div><span class="status-chip"><?php echo count($audit); ?> eventos</span></div>
                <div class="audit-summary-grid mb-3">
                    <?php foreach (array_slice($auditSummary, 0, 6, true) as $action => $count): ?>
                        <article class="<?php echo str_contains((string) $action, 'blocked') ? 'danger' : ''; ?>"><span><?php echo e(ygo_audit_label((string) $action)); ?></span><b><?php echo e((string) $count); ?></b></article>
                    <?php endforeach; ?>
                    <?php if (!$auditSummary): ?><article><span>Sin auditoria</span><b>0</b></article><?php endif; ?>
                </div>
                <div class="result-filter-grid mb-3">
                    <input class="form-control" data-audit-search placeholder="Buscar accion, entidad o detalle">
                    <select class="form-select" data-audit-kind>
                        <option value="">Todas las acciones</option>
                        <option value="blocked">Bloqueos</option>
                        <option value="save">Guardados</option>
                        <option value="schedule">Calendario</option>
                        <option value="result">Resultados</option>
                    </select>
                    <select class="form-select" data-audit-entity>
                        <option value="">Todas las entidades</option>
                        <option value="competition">Eventos</option>
                        <option value="match">Combates</option>
                        <option value="deck">Decks</option>
                        <option value="division">Divisiones</option>
                        <option value="qa">QA</option>
                    </select>
                    <select class="form-select" data-audit-action>
                        <option value="">Todas las acciones especificas</option>
                        <?php foreach (array_keys($auditSummary) as $auditActionOption): ?>
                            <option value="<?php echo e((string) $auditActionOption); ?>"><?php echo e(ygo_audit_label((string) $auditActionOption)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table app-table compact mobile-card-table audit-mobile-table">
                        <thead><tr><th>Fecha</th><th>Entidad</th><th>Tipo</th><th>Accion</th><th>Lectura</th><th>Detalle tecnico</th></tr></thead>
                        <tbody data-audit-body><?php foreach (array_slice($audit, 0, 40) as $row): ?><?php $auditAction = (string) $row['action']; $auditEntity = (string) ($row['entity_type'] ?? ''); $auditTone = str_contains($auditAction, 'blocked') ? 'danger' : (str_contains($auditAction, 'save') || str_contains($auditAction, 'recalculate') ? 'ok' : 'warn'); $auditLabel = ygo_audit_label($auditAction); ?><tr data-audit-row data-audit-entity="<?php echo e($auditEntity); ?>" data-audit-action="<?php echo e($auditAction); ?>" data-audit-text="<?php echo e(strtolower((string) $row['created_at'] . ' ' . $auditEntity . ' ' . $row['entity_id'] . ' ' . $auditAction . ' ' . $auditLabel . ' ' . $row['payload'])); ?>"><td data-label="Fecha"><?php echo e((string) $row['created_at']); ?></td><td data-label="Entidad"><?php echo e($auditEntity); ?> #<?php echo e((string) $row['entity_id']); ?></td><td data-label="Tipo"><span class="status-chip <?php echo e($auditTone); ?>"><?php echo e($auditTone === 'danger' ? 'Alerta' : ($auditTone === 'ok' ? 'Guardado' : 'Cambio')); ?></span></td><td data-label="Accion"><?php echo e($auditLabel); ?></td><td data-label="Lectura"><?php echo e($auditTone === 'danger' ? 'Revisar evento, prerequisito, fecha o pool antes de repetir la accion.' : 'Accion registrada correctamente.'); ?></td><td data-label="Detalle" data-raw-text><?php echo e(substr((string) $row['payload'], 0, 180)); ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="resumen">
                <h2>Reglas vivas de configuracion</h2>
                <ul class="rule-list">
                    <li>Titan, Legend, Elite y Challenger son datos editables, no codigo fijo.</li>
                    <li>Los equipos se crean aparte y pueden representar varios decks.</li>
                    <li>Los eventos controlan fechas para el calendario visual.</li>
                    <li>El clasificatorio se juega y al finalizar redistribuye decks en los formatos oficiales.</li>
                    <li>La UI usa estilo competitivo oscuro inspirado en Master Duel: paneles, brillos, emblemas, ranking y cartas.</li>
                </ul>
            </section>
        </div>
    </section>
</main>
</div>

<div class="modal fade dml-modal" id="alertCenterModal" tabindex="-1" aria-hidden="true" data-alert-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <span class="tag">Notificaciones</span>
                    <h2 class="modal-title">Centro de alertas</h2>
                    <p>En curso primero; después vencidos, hoy, mañana y sin fecha.</p>
                </div>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <section class="alert-center compact" data-alert-center="modal"></section>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="qualifierStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Clasificatorio</span>
                    <h2 class="modal-title"><?php echo e((string) ($displayQualifierCompetition['name'] ?? 'Estado del clasificatorio')); ?></h2>
                    <p><?php echo e((string) (($displayQualifierCompetition['code'] ?? '') ?: 'Sin codigo')); ?> · solo se modificara este evento.</p>
                </div>
                <span class="status-chip"><?php echo e($qualifierStatusLabel); ?></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <ul class="rule-list">
                    <?php foreach ($qualifierRules as $rule): ?>
                        <li><?php echo e($rule); ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="rule-note mt-3 <?php echo $qualifierFinalizeCheck['ok'] ? 'ok' : 'warn'; ?>">
                    <i class="bi <?php echo $qualifierFinalizeCheck['ok'] ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i>
                    <p>
                        <strong><?php echo $qualifierFinalizeCheck['ok'] ? 'Clasificatorio listo para finalizar.' : 'Cierre bloqueado.'; ?></strong><br>
                        <?php echo e($qualifierFinalizeCheck['ok'] ? 'Primera ronda completa, sin rondas en curso y minimo deportivo cumplido.' : implode(' ', $qualifierFinalizeCheck['reasons'])); ?>
                    </p>
                </div>
            </div>
            <div class="modal-footer modal-actions-row">
                <button class="btn btn-primary" type="button" data-route="resultados" data-bs-dismiss="modal"><i class="bi bi-pencil-square"></i> Registrar combate</button>
                <button class="btn btn-outline-light" type="button" data-route="gestion" data-bs-dismiss="modal"><i class="bi bi-sliders"></i> Gestionar torneo</button>
                <?php if ($dbStatus['connected']): ?>
                    <form method="post" class="d-inline-flex gap-2">
                        <input type="hidden" name="action" value="set_qualifier_status">
                        <input type="hidden" name="qualifier_competition_id" value="<?php echo e((string) $displayQualifierId); ?>">
                        <button class="btn btn-outline-info" name="status" value="active" type="submit"><i class="bi bi-play-circle"></i> Marcar en curso</button>
                        <button class="btn btn-warning fw-bold" name="status" value="completed" type="submit" <?php echo $qualifierFinalizeCheck['ok'] ? '' : 'disabled'; ?>><i class="bi bi-flag-fill"></i> Finalizar clasificatorio</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="teamSheetModal" tabindex="-1" aria-hidden="true" data-team-sheet-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Decks</span>
                    <h2 class="modal-title">Asociar decks al equipo</h2>
                    <p>Agrega decks sin equipo o quita decks asociados sin salir de la ficha.</p>
                </div>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body team-sheet-modal-body">
                <section class="panel">
                    <div class="panel-head"><div><h2>Decks del equipo</h2><p>Busca un deck sin equipo para asociarlo o marca asociados para quitarlos.</p></div><span class="status-chip" data-team-sheet-deck-count>0 decks</span></div>
                    <form method="post" class="gestion-form team-deck-link-form">
                        <input type="hidden" name="action" value="save_team_deck_links">
                        <input type="hidden" name="team_id" data-team-link-team-id>
                        <div class="team-deck-selector-row">
                            <label>Buscar deck para asociar<input class="form-control" list="teamAvailableDeckOptions" placeholder="Escribe nombre, formato o arquetipo..." data-team-deck-picker></label>
                            <datalist id="teamAvailableDeckOptions" data-team-deck-picker-options></datalist>
                            <button class="btn btn-outline-info fw-bold" type="button" data-team-add-picked-deck><i class="bi bi-plus-circle"></i> Agregar</button>
                        </div>
                        <select class="visually-hidden" name="team_add_deck_ids[]" multiple data-team-add-decks></select>
                        <select class="visually-hidden" name="team_remove_deck_ids[]" multiple data-team-remove-decks></select>
                        <div class="team-deck-save-row">
                            <span data-team-link-summary>Selecciona decks para asociar o quitar.</span>
                            <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-link-45deg"></i> Guardar asociaciones</button>
                        </div>
                    </form>
                    <div class="profile-list mt-3" data-team-sheet-current-decks></div>
                </section>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="matchSheetModal" tabindex="-1" aria-hidden="true" data-match-sheet-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag" data-match-sheet-phase>Combate</span>
                    <h2 class="modal-title" data-match-sheet-title>Ficha del combate</h2>
                    <p data-match-sheet-meta></p>
                    <div class="match-header-identity" data-match-sheet-identity></div>
                </div>
                <div class="modal-top-actions">
                    <button class="btn btn-primary fw-bold" type="button" data-match-sheet-capture><i class="bi bi-clipboard2-check"></i> Cargar resultado</button>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="match-sheet-grid">
                    <section class="match-sheet-duel">
                        <article>
                            <span class="match-sheet-logo" data-match-sheet-logo-a>A</span>
                            <small data-match-sheet-team-a>Equipo A</small>
                            <strong data-match-sheet-a>Deck A</strong>
                            <span class="match-league-position" data-match-sheet-league-a hidden></span>
                        </article>
                        <b>VS</b>
                        <article>
                            <span class="match-sheet-logo" data-match-sheet-logo-b>B</span>
                            <small data-match-sheet-team-b>Equipo B</small>
                            <strong data-match-sheet-b>Deck B</strong>
                            <span class="match-league-position" data-match-sheet-league-b hidden></span>
                        </article>
                    </section>
                    <section class="match-sheet-stats">
                        <h3>Estado competitivo</h3>
                        <div class="mini-grid">
                            <span><b>ID</b><br><small data-match-sheet-id>-</small></span>
                            <span><b>Estado</b><br><small data-match-sheet-status>-</small></span>
                            <span><b>Marcador</b><br><small data-match-sheet-score>-</small></span>
                            <span><b>Resultado</b><br><small data-match-sheet-winner>-</small></span>
                        </div>
                        <div class="stat-bars mt-3" data-match-sheet-duel-bars></div>
                    </section>
                </div>
                <div class="match-sheet-details mt-3">
                    <span><b>Fecha programada</b><small data-match-sheet-date></small></span>
                    <span><b>Finalizacion del duelo</b><small data-match-sheet-completed></small></span>
                    <span><b>Inicio</b><small data-match-sheet-turn></small></span>
                    <span><b>Notas</b><small data-match-sheet-notes></small></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="resultCaptureModal" tabindex="-1" aria-hidden="true" data-result-capture-modal>
    <div class="modal-dialog modal-xl result-capture-dialog">
        <form method="post" class="modal-content result-capture-form" data-result-capture-form>
            <input type="hidden" name="action" value="save_match_result">
            <input type="hidden" name="match_id" data-result-capture-match-id>
            <input type="hidden" name="result_dice_roll" data-result-capture-dice-json>
            <input type="hidden" name="result_turn_choice" data-result-capture-turn-choice>
            <input type="hidden" name="result_duel_details" data-result-capture-details-json>
            <input type="hidden" name="result_correction_version" data-result-correction-version>
            <div class="modal-header result-console-head">
                <div class="result-console-title">
                    <span class="tag" data-result-capture-phase>Resultado</span>
                    <h2 class="modal-title" data-result-capture-title>Capturar resultado</h2>
                    <p data-result-capture-meta></p>
                    <div class="match-header-identity" data-result-capture-identity></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body result-console-body">
                <div class="match-sheet-duel result-capture-duel result-versus-hero">
                    <article class="result-side-card result-side-a" data-result-capture-side-a>
                        <span class="result-capture-team-logo" data-result-capture-logo-a>A</span>
                        <span class="result-side-copy">
                            <small>Equipo A</small>
                            <b data-result-capture-team-a>Equipo A</b>
                            <strong data-result-capture-a>Deck A</strong>
                            <span class="match-league-position" data-result-capture-league-a hidden></span>
                        </span>
                    </article>
                    <b class="result-vs-core">
                        <span><em data-result-capture-score-a>0</em><i>VS</i><em data-result-capture-score-b>0</em></span>
                        <small data-result-capture-bestof>Mejor de 3</small>
                    </b>
                    <article class="result-side-card result-side-b" data-result-capture-side-b>
                        <span class="result-capture-team-logo" data-result-capture-logo-b>B</span>
                        <span class="result-side-copy">
                            <small>Equipo B</small>
                            <b data-result-capture-team-b>Equipo B</b>
                            <strong data-result-capture-b>Deck B</strong>
                            <span class="match-league-position" data-result-capture-league-b hidden></span>
                        </span>
                    </article>
                </div>
                <section class="result-player-screens" data-result-player-screens>
                    <div><small>Pantallas sincronizadas</small><strong>Control del juez</strong><span data-result-live-sync>Preparando sesión en vivo...</span></div>
                    <button class="btn btn-outline-info result-terminals-open" type="button" data-result-terminals-open><i class="bi bi-phone"></i> Terminales</button>
                    <div class="result-terminals-sheet" data-result-terminals-sheet aria-hidden="true">
                        <button class="result-terminals-backdrop" type="button" data-result-terminals-close aria-label="Cerrar terminales"></button>
                        <section class="result-terminals-panel" role="dialog" aria-modal="true" aria-labelledby="resultTerminalsTitle">
                            <header>
                                <div><small>Acceso para cada lado</small><h3 id="resultTerminalsTitle">Terminales</h3></div>
                                <button class="btn btn-outline-light btn-sm" type="button" data-result-terminals-close aria-label="Cerrar terminales"><i class="bi bi-x-lg"></i></button>
                            </header>
                            <div class="result-player-screen-actions">
                                <div class="result-player-screen-group">
                                    <strong>Terminal A</strong>
                                    <a class="btn btn-outline-info btn-sm" href="#" target="_blank" rel="noopener" data-result-player-link="A"><i class="bi bi-phone"></i> Abrir</a>
                                    <button class="btn btn-outline-light btn-sm result-player-copy" type="button" data-result-player-copy="A" title="Copiar enlace de la terminal A" aria-label="Copiar enlace de la terminal A"><i class="bi bi-copy"></i></button>
                                </div>
                                <div class="result-player-screen-group">
                                    <strong>Terminal B</strong>
                                    <a class="btn btn-outline-info btn-sm" href="#" target="_blank" rel="noopener" data-result-player-link="B"><i class="bi bi-phone"></i> Abrir</a>
                                    <button class="btn btn-outline-light btn-sm result-player-copy" type="button" data-result-player-copy="B" title="Copiar enlace de la terminal B" aria-label="Copiar enlace de la terminal B"><i class="bi bi-copy"></i></button>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
                <section class="result-step result-dice-panel">
                    <span class="result-step-index">1</span>
                    <div class="result-dice-copy">
                        <i class="bi bi-dice-5"></i>
                        <span>
                            <h3>Resumen del match</h3>
                            <p data-result-capture-dice-summary>Sin lanzamiento registrado.</p>
                        </span>
                    </div>
                    <h3 class="result-sidebar-subtitle">Tirada de inicio</h3>
                    <div class="result-dice-stage" aria-hidden="true" data-result-dice-stage>
                        <div class="result-dice-player-label result-dice-player-a">Equipo A</div>
                        <div class="result-dice-player-label result-dice-player-b">Equipo B</div>
                        <canvas class="result-dice-3d-canvas" data-result-dice-canvas></canvas>
                        <div class="result-die result-die-cube" data-result-die-a data-value="1">
                            <span class="die-face die-face-1"><i></i></span>
                            <span class="die-face die-face-2"><i></i><i></i></span>
                            <span class="die-face die-face-3"><i></i><i></i><i></i></span>
                            <span class="die-face die-face-4"><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-5"><i></i><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-6"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        </div>
                        <strong>VS</strong>
                        <div class="result-die result-die-cube" data-result-die-b data-value="1">
                            <span class="die-face die-face-1"><i></i></span>
                            <span class="die-face die-face-2"><i></i><i></i></span>
                            <span class="die-face die-face-3"><i></i><i></i><i></i></span>
                            <span class="die-face die-face-4"><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-5"><i></i><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-6"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        </div>
                    </div>
                    <button class="btn btn-warning fw-bold" type="button" data-result-roll-dice><i class="bi bi-dice-5"></i> Lanzar dado</button>
                    <div class="result-turn-choice" data-result-turn-choice-panel hidden>
                        <span data-result-turn-choice-question></span>
                        <button class="btn btn-outline-info btn-sm" type="button" data-result-turn-select="first">Elige primero</button>
                        <button class="btn btn-outline-info btn-sm" type="button" data-result-turn-select="second">Elige segundo</button>
                    </div>
                </section>
                <section class="result-step result-duel-section">
                    <span class="result-step-index">2</span>
                    <header class="result-duel-section-head">
                        <div>
                            <h3><i class="bi bi-crosshair"></i> Duelo actual <span data-result-current-duel>D1</span></h3>
                            <p>Registra únicamente el duelo en curso. Al guardarlo se habilitará el siguiente si todavía es necesario.</p>
                        </div>
                        <div class="result-duel-progress">
                            <span>Duelos completados</span>
                            <i><b data-result-duel-progress-bar></b></i>
                            <strong data-result-duel-progress-text>0 / 5</strong>
                        </div>
                    </header>
                    <button class="btn btn-outline-info result-history-open" type="button" data-result-history-open><i class="bi bi-clock-history"></i> Historial de duelos</button>
                    <div class="result-history-sheet" data-result-history-sheet aria-hidden="true">
                        <button class="result-history-backdrop" type="button" data-result-history-close aria-label="Cerrar historial"></button>
                        <div class="result-duel-history" role="dialog" aria-modal="true" aria-labelledby="resultHistoryTitle">
                            <header>
                                <div><h4 id="resultHistoryTitle">Historial de duelos</h4><p>Resultados ya registrados en este match.</p></div>
                                <button class="btn btn-outline-light btn-sm result-history-close" type="button" data-result-history-close aria-label="Cerrar historial"><i class="bi bi-x-lg"></i></button>
                            </header>
                            <div class="result-duel-history-list" data-result-capture-duel-list></div>
                        </div>
                    </div>
                    <div class="result-duel-detail-grid">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <section class="result-duel-detail-card" data-result-duel-card="<?php echo $i; ?>">
                                <header>
                                    <h3>D<?php echo $i; ?></h3>
                                    <span data-result-duel-status="<?php echo $i; ?>">Pendiente</span>
                                </header>
                                <div class="result-duel-setup">
                                    <div class="result-duel-start-box">
                                        <small>Resultado del dado</small>
                                        <div>
                                            <b data-result-duel-dice-a="<?php echo $i; ?>">-</b>
                                            <b data-result-duel-dice-b="<?php echo $i; ?>">-</b>
                                        </div>
                                        <button class="btn btn-warning btn-sm result-draw-reroll" type="button" data-result-duel-reroll="<?php echo $i; ?>" hidden><i class="bi bi-dice-5"></i> Lanzar dados por empate</button>
                                        <small>Empieza primero</small>
                                        <span data-result-duel-first="<?php echo $i; ?>">Sin definir</span>
                                        <small>Empieza segundo</small>
                                        <span data-result-duel-second="<?php echo $i; ?>">Sin definir</span>
                                    </div>
                                    <div class="result-turn-ledger" data-result-duel-turn-ledger="<?php echo $i; ?>">
                                        <span data-result-duel-turn-owner="<?php echo $i; ?>">Define el inicio del duelo.</span>
                                        <div class="result-turn-ledger-actions">
                                            <button class="btn btn-outline-info btn-sm" type="button" data-result-duel-turn-select="<?php echo $i; ?>" data-choice="first">Elige empezar</button>
                                            <button class="btn btn-outline-info btn-sm" type="button" data-result-duel-turn-select="<?php echo $i; ?>" data-choice="second">Elige segundo</button>
                                        </div>
                                        <input type="hidden" data-result-duel-turn-choice="<?php echo $i; ?>">
                                    </div>
                                    <div class="result-duel-launch" data-result-duel-launch="<?php echo $i; ?>" hidden>
                                        <span><small>Preparacion completa</small><strong>Confirma para comenzar D<?php echo $i; ?></strong></span>
                                        <button class="btn btn-primary fw-bold" type="button" data-result-duel-begin="<?php echo $i; ?>"><i class="bi bi-play-fill"></i> Comenzar duelo</button>
                                    </div>
                                </div>
                                <div class="result-duel-live" data-result-duel-live="<?php echo $i; ?>" hidden>
                                    <section class="result-turn-tracker">
                                        <span>Turno actual</span>
                                        <strong data-result-turn-current="<?php echo $i; ?>">Turno 1</strong>
                                        <b data-result-turn-player="<?php echo $i; ?>">Esperando inicio</b>
                                        <div>
                                            <button class="btn btn-primary fw-bold" type="button" data-result-turn-end="<?php echo $i; ?>"><i class="bi bi-check2-circle"></i> Terminar turno</button>
                                        </div>
                                        <small data-result-turn-total="<?php echo $i; ?>">0 turnos completados</small>
                                        <div class="result-turn-history" data-result-turn-history="<?php echo $i; ?>"></div>
                                        <input type="hidden" data-result-capture-turns="<?php echo $i; ?>" value="0">
                                    </section>
                                    <div class="result-duel-timer" data-result-duel-timer="<?php echo $i; ?>">
                                        <span>Tiempo del duelo</span><strong data-result-duel-clock="<?php echo $i; ?>">20:00</strong>
                                        <input type="hidden" data-result-capture-duration="<?php echo $i; ?>" value="0">
                                        <div><button class="btn btn-outline-info btn-sm" type="button" data-result-timer-toggle="<?php echo $i; ?>"><i class="bi bi-pause-fill"></i> Pausar reloj</button></div>
                                        <small data-result-duel-alarm="<?php echo $i; ?>">Limite 20 min. Aviso cuando falten 5 min.</small>
                                    </div>
                                </div>
                                <button class="btn btn-outline-info result-outcome-open" type="button" data-result-outcome-open="<?php echo $i; ?>"><i class="bi bi-flag"></i> Registrar resultado</button>
                                <div class="result-outcome-sheet" data-result-outcome-sheet="<?php echo $i; ?>" aria-hidden="true">
                                    <button class="result-outcome-backdrop" type="button" data-result-outcome-close="<?php echo $i; ?>" aria-label="Cerrar resultado"></button>
                                    <section class="result-outcome-panel" role="dialog" aria-modal="true" aria-labelledby="resultOutcomeTitle<?php echo $i; ?>">
                                        <header>
                                            <div><small>Duelo D<?php echo $i; ?></small><h3 id="resultOutcomeTitle<?php echo $i; ?>">Resultado y cierre</h3></div>
                                            <button class="btn btn-outline-light btn-sm" type="button" data-result-outcome-close="<?php echo $i; ?>" aria-label="Cerrar resultado"><i class="bi bi-x-lg"></i></button>
                                        </header>
                                        <div class="result-duel-outcome-grid">
                                            <label>Resultado del duelo<select class="form-select" name="d<?php echo $i; ?>" data-result-capture-duel="<?php echo $i; ?>"><option value="">Sin jugar</option><option value="A">Gana A</option><option value="E">Empate</option><option value="B">Gana B</option></select></label>
                                            <label>Como termino<select class="form-select" data-result-capture-method="<?php echo $i; ?>"><option value="">Sin definir</option><option value="life_points">Derrota definitiva por LP</option><option value="surrender">Rendicion del oponente</option><option value="time_limit">Tiempo finalizado</option><option value="deck_out">Deck sin cartas</option><option value="effect_win">Condicion de victoria por efecto</option></select></label>
                                        </div>
                                        <button class="btn btn-primary result-outcome-done" type="button" data-result-outcome-close="<?php echo $i; ?>"><i class="bi bi-check2"></i> Listo</button>
                                    </section>
                                </div>
                                <fieldset class="result-brick-fieldset" data-result-brick-fieldset="<?php echo $i; ?>">
                                    <legend>Mano brick <small>Opcional; aplica al primer turno de cada jugador.</small></legend>
                                    <label data-result-brick-side="A"><input class="form-check-input" type="checkbox" data-result-capture-brick-a="<?php echo $i; ?>"> Lado A tuvo brick</label>
                                    <label data-result-brick-side="B"><input class="form-check-input" type="checkbox" data-result-capture-brick-b="<?php echo $i; ?>"> Lado B tuvo brick</label>
                                </fieldset>
                                <div class="result-brick-summary" data-result-brick-summary="<?php echo $i; ?>" hidden><i class="bi bi-hand-index-thumb"></i><span>Manos iniciales</span><strong data-result-brick-summary-text="<?php echo $i; ?>">A: No | B: No</strong></div>
                                <div class="result-lp-grid">
                                    <label>LP final A<input class="form-control" type="number" min="0" max="99999" step="1" data-result-capture-lp-a="<?php echo $i; ?>" placeholder="0" readonly></label>
                                    <label>LP final B<input class="form-control" type="number" min="0" max="99999" step="1" data-result-capture-lp-b="<?php echo $i; ?>" placeholder="0" readonly></label>
                                </div>
                                <div class="result-lp-grid result-participant-grid" data-result-participant-grid="<?php echo $i; ?>">
                                    <label>Deck participante A<select class="form-select" data-result-capture-deck-a="<?php echo $i; ?>"></select></label>
                                    <label>Deck participante B<select class="form-select" data-result-capture-deck-b="<?php echo $i; ?>"></select></label>
                                    <label>Jugador A<select class="form-select" data-result-capture-player-a="<?php echo $i; ?>"><option value="">Sin asignar</option></select></label>
                                    <label>Jugador B<select class="form-select" data-result-capture-player-b="<?php echo $i; ?>"><option value="">Sin asignar</option></select></label>
                                </div>
                                <button class="btn btn-outline-info btn-sm result-lp-open" type="button" data-result-lp-open="<?php echo $i; ?>"><i class="bi bi-calculator"></i> Calculadora LP</button>
                                <input type="hidden" data-result-capture-origin="<?php echo $i; ?>">
                                <label class="result-duel-note-field">Notas del combate<input class="form-control" maxlength="500" data-result-capture-duel-note="<?php echo $i; ?>" placeholder="Sin notas..."></label>
                                <div class="result-duel-card-summary" data-result-duel-card-summary="<?php echo $i; ?>">Pendiente de registrar</div>
                            </section>
                        <?php endfor; ?>
                    </div>
                </section>
                <section class="result-step result-notes-section">
                    <span class="result-step-index">3</span>
                    <div class="result-notes-title">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>
                            <h3>Notas de auditoria</h3>
                            <p>Agrega correcciones, observaciones o el nombre del juez.</p>
                        </span>
                    </div>
                    <label><input class="form-control" name="result_notes" maxlength="500" data-result-capture-notes placeholder="Escribe aqui..."></label>
                    <small><span data-result-notes-count>0</span> / 500</small>
                </section>
                <section class="result-step result-save-section">
                    <span class="result-step-index">4</span>
                    <div class="result-validation-title">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>
                            <h3>Estado y validacion</h3>
                            <p>Revisa la informacion antes de guardar.</p>
                        </span>
                    </div>
                    <div class="result-score-pill">
                        <span>Marcador del match</span>
                        <strong data-result-capture-score-pill>0 - 0</strong>
                    </div>
                    <div class="result-validation-pill" data-result-validation-pill>
                        <strong data-result-capture-validation-title>Validacion OK</strong>
                        <small data-result-capture-summary>Sin captura</small>
                    </div>
                </section>
                <section class="result-lp-calculator" data-result-lp-calculator hidden aria-label="Calculadora de puntos de vida">
                    <header>
                        <div><span class="tag">Duelo <b data-result-lp-duel>D1</b></span><h3>Calculadora de puntos de vida</h3></div>
                        <button class="btn btn-outline-info btn-sm" type="button" data-result-lp-sound-toggle aria-pressed="true" aria-label="Silenciar sonido de puntos de vida" title="Silenciar sonido de puntos de vida"><i class="bi bi-volume-up-fill" aria-hidden="true"></i></button>
                        <button class="icon-button" type="button" data-result-lp-close title="Cerrar calculadora"><i class="bi bi-x-lg"></i></button>
                    </header>
                    <div class="result-lp-timer">
                        <button type="button" data-result-lp-timer-toggle title="Iniciar o pausar"><i class="bi bi-play-fill"></i></button>
                        <strong data-result-lp-timer>00:00:00</strong>
                        <button type="button" data-result-lp-timer-reset title="Reiniciar cronometro"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                    <div class="result-lp-grid result-lp-start-grid">
                        <label>LP inicial A<input class="form-control" type="number" min="0" step="100" value="8000" data-result-lp-start="a"></label>
                        <label>LP inicial B<input class="form-control" type="number" min="0" step="100" value="8000" data-result-lp-start="b"></label>
                        <button class="btn btn-outline-info" type="button" data-result-lp-start-apply>Aplicar LP inicial</button>
                    </div>
                    <div class="result-lp-scoreboard">
                        <button class="result-lp-side active" type="button" data-result-lp-side="a"><small>Equipo A</small><span data-result-lp-name="a">Equipo A</span><strong data-result-lp-value="a">8000</strong></button>
                        <span class="result-lp-versus">VS</span>
                        <button class="result-lp-side" type="button" data-result-lp-side="b"><small>Equipo B</small><span data-result-lp-name="b">Equipo B</span><strong data-result-lp-value="b">8000</strong></button>
                    </div>
                    <div class="result-lp-workbench">
                        <div class="result-lp-entry"><span>Valor</span><strong data-result-lp-entry>0</strong></div>
                        <div class="result-lp-keypad">
                            <?php foreach (['7', '8', '9', '4', '5', '6', '1', '2', '3', '0', '00', '000'] as $key): ?><button type="button" data-result-lp-key="<?php echo e($key); ?>"><?php echo e($key); ?></button><?php endforeach; ?>
                        </div>
                        <div class="result-lp-operations">
                            <button type="button" data-result-lp-op="clear"><i class="bi bi-backspace"></i><span>Borrar</span></button>
                            <button type="button" data-result-lp-op="subtract"><i class="bi bi-dash-lg"></i><span>Restar</span></button>
                            <button type="button" data-result-lp-op="add"><i class="bi bi-plus-lg"></i><span>Sumar</span></button>
                            <button type="button" data-result-lp-op="half"><b>1/2</b><span>Mitad</span></button>
                            <button type="button" data-result-lp-op="undo"><i class="bi bi-arrow-counterclockwise"></i><span>Deshacer</span></button>
                            <button type="button" data-result-lp-op="reset"><i class="bi bi-arrow-repeat"></i><span>LP inicial</span></button>
                        </div>
                    </div>
                    <button class="btn btn-outline-info" type="button" data-result-standard-open><i class="bi bi-calculator"></i> Abrir calculadora auxiliar</button>
                    <div class="result-lp-history" data-result-lp-history>Sin movimientos registrados.</div>
                    <footer>
                        <button class="btn btn-outline-light" type="button" data-result-lp-close>Cerrar</button>
                        <button class="btn btn-primary fw-bold" type="button" data-result-lp-apply><i class="bi bi-check2-circle"></i> Aplicar LP al duelo</button>
                    </footer>
                </section>
            </div>
            <section class="lp-workspace-modal" data-result-standard-modal hidden role="dialog" aria-modal="true" aria-labelledby="lpAuxTitle">
                <button class="lp-workspace-backdrop" type="button" data-dialog-close aria-label="Cerrar calculadora auxiliar"></button>
                <article class="lp-workspace-panel">
                    <header><div><small>Cálculo auxiliar</small><h3 id="lpAuxTitle">Calculadora</h3></div><button class="btn btn-outline-light" type="button" data-dialog-close aria-label="Cerrar calculadora">×</button></header>
                    <section class="result-standard-calculator" data-result-standard-calculator>
                        <header><div><small>Calculo auxiliar</small><h4>Calculadora estandar</h4></div><span data-result-standard-expression>0</span><strong data-result-standard-display>0</strong></header>
                        <div class="result-standard-keypad">
                            <?php foreach (['7','8','9','divide','4','5','6','multiply','1','2','3','subtract','clear','0','equals','add'] as $key): ?>
                                <?php $label = ['divide' => '&divide;', 'multiply' => '&times;', 'subtract' => '&minus;', 'add' => '+', 'clear' => 'C', 'equals' => '='][$key] ?? $key; ?>
                                <button type="button" data-result-standard-key="<?php echo e($key); ?>"><?php echo $label; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-outline-info" type="button" data-result-standard-use>Usar resultado como valor LP</button>
                    </section>
                </article>
            </section>
            <section class="lp-workspace-modal" data-result-lp-victory-modal hidden role="dialog" aria-modal="true" aria-labelledby="lpVictoryTitle">
                <button class="lp-workspace-backdrop" type="button" data-dialog-close aria-label="Volver sin otorgar victoria"></button>
                <article class="lp-workspace-panel">
                    <header><h3 id="lpVictoryTitle">Victoria por puntos de vida</h3><button class="btn btn-outline-light" type="button" data-dialog-close aria-label="Cerrar confirmación">×</button></header>
                    <p data-result-lp-victory-text></p>
                    <footer><button class="btn btn-outline-light" type="button" data-dialog-close data-dialog-focus>Seguir sin otorgar victoria</button><button class="btn btn-primary" type="button" data-result-lp-victory-confirm>Otorgar victoria y guardar</button></footer>
                </article>
            </section>
            <section class="result-duel-read-modal" data-result-duel-read-modal hidden role="dialog" aria-modal="true" aria-labelledby="resultDuelReadTitle">
                <button class="result-duel-read-backdrop" type="button" data-result-duel-read-close aria-label="Cerrar detalle"></button>
                <article class="result-duel-read-dialog">
                    <header>
                        <div><span class="tag">Detalle del duelo</span><h2 id="resultDuelReadTitle" data-result-duel-read-title>D1</h2><p data-result-duel-read-subtitle>Informacion persistida</p></div>
                        <button class="icon-button" type="button" data-result-duel-read-close title="Cerrar"><i class="bi bi-x-lg"></i></button>
                    </header>
                    <div class="result-duel-read-body" data-result-duel-read-body></div>
                    <footer><button class="btn btn-outline-light" type="button" data-result-duel-read-close><i class="bi bi-x-lg"></i> Cerrar</button></footer>
                </article>
            </section>
            <div class="modal-footer result-console-footer">
                <span class="result-save-state" data-result-save-state data-state="saved"><i class="bi bi-cloud-check"></i> Turnos guardados</span>
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-outline-info fw-bold" type="button" data-modify-captured-match><i class="bi bi-pencil-square"></i> Modificar</button>
                <button class="btn btn-primary fw-bold" type="submit" data-result-capture-save><i class="bi bi-database-check"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade dml-modal" id="qualifierDeckSheetModal" tabindex="-1" aria-hidden="true" data-qualifier-deck-sheet-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Ficha clasificatorio</span>
                    <h2 class="modal-title" data-qualifier-deck-title>Deck</h2>
                    <p data-qualifier-deck-meta></p>
                </div>
                <div class="modal-top-actions">
                    <button class="btn btn-primary fw-bold" type="button" data-qualifier-deck-profile><i class="bi bi-journal-richtext"></i> Ver ficha deck</button>
                    <button class="btn btn-outline-light" type="button" data-route="resultados" data-bs-dismiss="modal"><i class="bi bi-clipboard2-check"></i> Captura general</button>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="result-dashboard">
                    <article class="panel result-kpi"><span>Programados</span><strong data-qualifier-deck-scheduled>0</strong><small>Combates asignados</small></article>
                    <article class="panel result-kpi"><span>Finalizados</span><strong data-qualifier-deck-played>0</strong><small>Jugados</small></article>
                    <article class="panel result-kpi"><span>Rivales</span><strong data-qualifier-deck-rivals>0</strong><small>Diferentes</small></article>
                    <article class="panel result-kpi"><span>Estado</span><strong data-qualifier-deck-status>0%</strong><small>Avance minimo</small></article>
                </div>
                <div class="table-responsive">
                    <table class="table app-table compact mobile-card-table qualifier-history-table">
                        <thead><tr><th>ID</th><th>Fecha</th><th>Ronda</th><th>Mesa</th><th>Rival</th><th>Condicion</th><th>Estado</th><th>Resultado</th><th></th></tr></thead>
                        <tbody data-qualifier-deck-matches></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

