    <section class="screen" data-screen="crear_evento" hidden>
        <div class="page-title"><h1><?php echo e($uiText('route.crear_evento', 'title', 'Crear evento')); ?></h1><p><?php echo e($uiText('route.crear_evento', 'subtitle', 'Pantalla unica para crear clasificatorios, copas, torneos, ligas y eventos especiales.')); ?></p></div>
        <div class="event-create-layout">
            <section class="panel event-create-main">
                <div class="panel-head"><div><h2>Nuevo evento</h2><p>Configura los datos y las reglas del evento.</p></div><span class="status-chip">Unico formulario</span></div>
                <form method="post" class="gestion-form event-create-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_competition">
                    <?php $seasonFieldMode = "edit"; require YGO_APP_ROOT . "/includes/season-event-field.php"; ?>
                    <input type="hidden" name="competition_id" data-gestion-competition-id>
                    <input type="hidden" name="competition_code" data-gestion-competition-code>
                    <select hidden data-gestion-competition-select aria-hidden="true"><option value="">Nuevo evento</option></select>
                    <label class="span-all">Nombre del evento<input class="form-control" name="competition_name" data-gestion-competition-name required placeholder="Ej: Clasificatorio TCG Avanzado"></label>
                    <div class="event-form-row two-col">
                        <label>Tipo de evento<select class="form-select" name="competition_type" data-gestion-competition-type>
                            <?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?>
                                <option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option>
                            <?php endforeach; ?>
                        </select><small>Define el modulo base: clasificatorio, torneo, liga, copa, mundial, final, amistoso o especial.</small></label>
                        <label>Modelo preconfigurado del evento<select class="form-select" name="competition_prompt_template" data-gestion-competition-template>
                            <option value="">Personalizado / sin plantilla</option>
                            <?php foreach (ygo_event_available_templates() as $template): ?>
                                <option value="<?php echo e((string) $template['code']); ?>" data-template-type="<?php echo e((string) ($template['type'] ?? 'tournament')); ?>"><?php echo e((string) ($template['label'] . ' | ' . $template['system'] . ' | ' . $template['rules'])); ?></option>
                            <?php endforeach; ?>
                        </select><small>Rellena una configuracion coherente sin borrar el tipo de evento elegido.</small></label>
                    </div>
                    <input type="hidden" name="competition_event_system" data-gestion-competition-event-system>
                    <div class="event-form-row two-col">
                        <label>Formato / division principal<select class="form-select" name="competition_division_id" data-gestion-competition-division><option value="">General</option><optgroup label="Formatos disponibles"><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="format:<?php echo e((string) $rulesetCode); ?>" data-ruleset="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></optgroup><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>" data-ruleset="<?php echo e((string) ($division['ruleset'] ?? $division['event_ruleset'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select><small>Para una liga, esta sera la division principal.</small></label>
                        <label data-gestion-league-divisions hidden>Divisiones asociadas a la liga<select class="form-select" name="competition_division_ids[]" data-gestion-league-divisions-select multiple size="4"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e((string) (($division['name'] ?? '') ?: ($division['short'] ?? 'Division'))); ?></option><?php endforeach; ?></select><small>Selecciona una o varias divisiones al crear la liga. La principal debe quedar incluida.</small></label>
                    </div>
                    <div class="event-form-row two-col">
                        <label>Sistema competitivo<select class="form-select" name="competition_execution_model" data-gestion-competition-execution-model><?php foreach ($eventModelOptions['competition_system'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select><small>Controla calendario, fases, avance y validaciones de resultado.</small></label>
                        <label>Participacion del match<select class="form-select" name="competition_match_format" data-gestion-competition-match-format required><option value="">Selecciona participacion</option><option value="ffa" data-participants="1">3-4 jugadores independientes</option><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>" data-participants="<?php echo e((string) ($matchType['participants_per_side'] ?? 1)); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select><small>Define cuantos equipos/decks participan por lado del combate.</small></label>
                    </div>
                    <div class="alert alert-warning span-all" data-event-model-diagnostic hidden></div>
                    <div class="alert alert-info span-all" data-event-workflow-policy hidden></div>
                    <input type="hidden" data-gestion-competition-execution-preview value="manual">
<section class="event-model-box"><h3>Parámetros de ejecución</h3><p>Reglamento interno Station X 2026 v1: puntos 3/1/0, BYE 3 sin duelo ni XP, desempate por puntos, Buchholz, diferencia y orden inicial. Las finales decisivas requieren ganador. Estos valores no certifican un reglamento oficial. Si dejas un parámetro vacío, se conserva el valor de esta política; los valores elegidos quedan fijados al programar.</p><div class="event-form-row three-col"><label>Rondas suizas<input class="form-control" type="number" min="1" max="64" name="competition_parameter_swiss_rounds" data-parameter="swiss_rounds" placeholder="Política interna mostrada arriba"></label><label>Clasificados al corte<input class="form-control" type="number" min="1" max="64" name="competition_parameter_top_n" data-parameter="top_n" placeholder="Política interna mostrada arriba"></label><label>Máximo por grupo<input class="form-control" type="number" min="1" max="64" name="competition_parameter_group_max_size" data-parameter="group_max_size" placeholder="Política interna mostrada arriba"></label><label>Clasificados por grupo<input class="form-control" type="number" min="1" max="64" name="competition_parameter_group_qualifiers" data-parameter="group_qualifiers" placeholder="Política interna mostrada arriba"></label><label>Matches de la serie (impar)<input class="form-control" type="number" min="1" max="64" name="competition_parameter_series_matches" data-parameter="series_matches" placeholder="Política interna mostrada arriba"></label></div></section>
                    <section class="event-model-box">
                        <div class="panel-head compact-head"><div><h3>Reglas operativas del evento</h3><p>Elige como se clasifica y como se resuelve cada enfrentamiento. La lectura del evento explica cada valor.</p></div></div>
                        <div hidden aria-hidden="true">
                            <select class="form-select" name="competition_participation" data-gestion-competition-participation><?php foreach ($eventModelOptions['participation'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_construction" data-gestion-competition-construction><?php foreach ($eventModelOptions['construction'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_participant_source" data-gestion-competition-participant-source><?php foreach ($eventModelOptions['participant_source'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_phase_structure" data-gestion-competition-phase-structure><?php foreach ($eventModelOptions['phase_structure'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_event_flow" data-gestion-competition-event-flow><?php foreach ($eventModelOptions['event_flow'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_ranking_impact" data-gestion-competition-ranking-impact><?php foreach ($eventModelOptions['ranking_impact'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_deck_policy" data-gestion-competition-deck-policy><?php foreach ($eventModelOptions['deck_policy'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                        </div>
                        <div hidden aria-hidden="true">
                            <div hidden aria-hidden="true"><select class="form-select" name="competition_environment" data-gestion-competition-environment><?php foreach ($eventModelOptions['environment'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></div>
                            <div hidden aria-hidden="true"><select class="form-select" name="competition_level" data-gestion-competition-level><?php foreach ($eventModelOptions['level'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></div>
                            <div hidden aria-hidden="true"><select class="form-select" name="competition_access_type" data-gestion-competition-access-type><?php foreach ($eventModelOptions['access_type'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="event-form-row two-col">
                            <label>Regla de avance<select class="form-select" name="competition_advancement_rule" data-gestion-competition-advancement-rule><?php foreach ($eventModelOptions['advancement_rule'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></label>
                            <label>Politica de empate<select class="form-select" name="competition_draw_policy" data-gestion-competition-draw-policy><?php foreach ($eventModelOptions['draw_policy'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <div class="event-form-row two-col">
                            <label>Formato de partida / Mejor de<input class="form-control" type="number" min="1" max="5" name="competition_best_of" data-gestion-competition-best-of value="3"><small>1 = BO1, 3 = BO3, 5 = BO5; otro valor funciona como serie personalizada.</small></label>
                        </div>
                        <input type="hidden" name="qualifier_duels_per_deck" value="3" data-gestion-competition-qualifier-duels>
                        <input type="hidden" name="qualifier_matches_per_day" value="1" data-gestion-competition-matches-per-day>
                        
                    </section>
                    <div class="event-form-row two-col">
                        <label>Regla del evento<select class="form-select" name="competition_game_rule_id" data-gestion-competition-game-rule><option value="">Usar regla compatible por defecto</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['event', 'all_matches', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="event-logo-gallery" data-event-logo-gallery>
                        <div class="event-logo-gallery-head">
                            <span>Logo de galeria</span>
                            <small data-event-logo-gallery-count></small>
                        </div>
                        <input type="hidden" name="competition_gallery_logo" data-event-logo-gallery-input>
                        <div class="event-logo-gallery-grid" data-event-logo-gallery-grid></div>
                    </div>
                    <div class="event-form-row two-col">
                        <label>Subir logo nuevo<input class="form-control" type="file" name="competition_logo" accept="image/png,image/jpeg,image/webp,image/gif" data-gestion-competition-logo data-event-logo-upload><small>PNG, JPG, WEBP o GIF. Si subes uno, reemplaza el logo de galeria.</small></label>
                    </div>
                    <div class="event-form-row two-col">
                        <label>Tipo de reglas / formato base<select class="form-select" name="competition_event_ruleset" data-gestion-competition-ruleset><?php foreach ($rulesetSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e($rulesetCode); ?>"><?php echo e($rulesetLabel); ?></option><?php endforeach; ?></select><small>Define el entorno de reglas principal: TCG, OCG, GOAT, Edison, Speed Duel, Master Duel o custom.</small></label>
                        <label>Reglamento / banlist<select class="form-select" name="competition_ruleset_version" data-gestion-competition-ruleset-version><option value="">Selecciona reglamento / por definir</option>
                            <?php foreach ($rulesetVersionCatalog as $versionOption): ?>
                                <?php $versionLabel = (string) ($versionOption['version_label'] ?? $versionOption['version'] ?? ''); ?>
                                <option value="<?php echo e($versionLabel); ?>" data-system="<?php echo e((string) $versionOption['system']); ?>" data-date="<?php echo e((string) $versionOption['date_label']); ?>" data-category="<?php echo e((string) $versionOption['category']); ?>"><?php echo e((string) ($versionOption['system'] . ' - ' . $versionLabel . ' - ' . $versionOption['date_label'])); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    </div>
                    <input type="hidden" name="competition_scope" data-gestion-competition-scope value="division">
                    <input type="hidden" name="competition_format" data-gestion-competition-format value="editable">
                    <input type="hidden" name="competition_status" data-gestion-competition-status value="pending_configuration">
                    <input type="hidden" name="starts_on" data-gestion-competition-start value="">
                    <input type="hidden" name="ends_on" data-gestion-competition-end value="">
                    <label class="span-all">Modo de participantes<select class="form-select" name="competition_deck_pool" data-gestion-competition-deck-pool>
                        <option value="all_active">Libre: mostrar todos los decks/equipos activos</option>
                        <option value="division">Division completa: usar solo decks/equipos de la division elegida</option>
                    </select><small>Para 2v2 y 3v3 se mostraran equipos con la cantidad minima de decks en la division.</small></label>
                    <section class="league-movement-box" data-gestion-league-movement hidden>
                        <div class="panel-head compact-head"><div><h3>Movimiento de divisiones</h3><p>Solo para ligas: ascensos, descensos y bloqueo de movimiento.</p></div></div>
                        <div class="two-col">
                            <label>Equipos que suben<input class="form-control" type="number" min="0" max="20" name="competition_promotion_slots" data-gestion-competition-promotion-slots value="0"></label>
                            <label>Suben hacia<select class="form-select" name="competition_promotion_to" data-gestion-competition-promotion-to><option value="">Sin ascenso</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <div class="two-col">
                            <label>Equipos que bajan<input class="form-control" type="number" min="0" max="20" name="competition_relegation_slots" data-gestion-competition-relegation-slots value="0"></label>
                            <label>Bajan hacia<select class="form-select" name="competition_relegation_to" data-gestion-competition-relegation-to><option value="">Sin descenso</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <label class="toggle-line"><input type="checkbox" name="competition_lock_division_moves" value="1" data-gestion-competition-lock-moves> Bloquear mover decks entre divisiones para esta liga</label>
                    </section>
                    <input type="hidden" name="competition_participants_per_side" data-gestion-competition-participants value="1">
                    <input type="hidden" name="competition_schedule_strategy" data-gestion-competition-schedule value="manual">
                    <label class="span-all">Notas del evento<textarea class="form-control" name="competition_rules" data-gestion-competition-rules rows="3" placeholder="Notas internas, sede, politica de ronda o aclaraciones del organizador"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar evento</button>
                </form>
            </section>
            <aside class="event-create-side" data-event-create-guide>
                <h2>Lectura del evento</h2>
                <p>Asi funcionara el evento con los valores actuales del formulario.</p>
                <div data-event-guide-content style="display:grid;gap:.85rem"></div>
            </aside>
        </div>
    </section>

