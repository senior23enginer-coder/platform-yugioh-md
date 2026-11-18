<div class="modal fade dml-modal" id="calendarDayModal" tabindex="-1" aria-hidden="true" data-calendar-day-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Agenda del dia</span>
                    <h2 class="modal-title" data-calendar-day-modal-title>Fecha</h2>
                    <p data-calendar-day-modal-meta></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="calendar-day-modal-list" data-calendar-day-modal-events></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="friendlyMatchModal" tabindex="-1" aria-hidden="true" data-friendly-match-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form method="post" class="modal-form">
                <div class="modal-header">
                    <div>
                        <span class="tag">Amistoso</span>
                        <h2 class="modal-title">Crear combate amistoso</h2>
                        <p>Combate independiente sin evento asociado. Se guardara en BD y aparecera en calendario.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_match">
                    <input type="hidden" name="manual_competition_id" value="">
                    <input type="hidden" name="manual_phase" value="friendly">
                    <input type="hidden" name="manual_match_format" value="1v1">
                    <input type="hidden" name="manual_division_id" value="" data-friendly-division-value>
                    <input type="hidden" name="manual_ruleset_code" value="" data-friendly-ruleset-value>
                    <input type="hidden" name="manual_best_of" value="5">
                    <input type="hidden" name="manual_participants_per_side" value="1">
                    <input type="hidden" name="manual_round" value="1">
                    <input type="hidden" name="manual_table" value="1">
                    <div class="config-grid compact">
                        <label>Formato<select class="form-select" data-friendly-division><option value="">General (todos los decks activos)</option><?php if ($formatSelectOptions): ?><optgroup label="Formatos"><?php foreach ($formatSelectOptions as $formatCode => $formatLabel): ?><option value="ruleset:<?php echo e((string) $formatCode); ?>"><?php echo e((string) $formatLabel); ?></option><?php endforeach; ?></optgroup><?php endif; ?><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="division:<?php echo e((string) $division['id']); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select><small>Al elegir un formato solo se cargan sus decks y equipos asociados.</small></label>
                        <label>Fecha<input class="form-control" type="date" name="manual_scheduled_on" value="<?php echo e((new DateTimeImmutable())->format('Y-m-d')); ?>" required></label>
                        <label>Hora<input class="form-control" type="time" name="manual_starts_at" value="19:00" required></label>
                        <label>Sede<input class="form-control" name="manual_venue" value="Station X Tulua"></label>
                    </div>
                    <label class="mt-3">Regla para este amistoso<select class="form-select" name="manual_game_rule_id"><option value="">Usar regla compatible por defecto</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['all_matches', 'friendly', 'match', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col mt-3">
                        <label>Deck A<select class="form-select" name="manual_deck_a_id" required data-friendly-deck-select><?php foreach ($allDecks as $deckIndex => $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-format-codes="<?php echo e(implode(',', $deck['format_codes'] ?? [])); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Deck B<select class="form-select" name="manual_deck_b_id" required data-friendly-deck-select><?php foreach ($allDecks as $deckIndex => $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-format-codes="<?php echo e(implode(',', $deck['format_codes'] ?? [])); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" <?php echo $deckIndex === 1 ? 'selected' : ''; ?>><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <p class="form-hint mt-2" data-friendly-pool-summary></p>
                    <label class="mt-3">Motivo / notas<textarea class="form-control" name="manual_notes" rows="3" placeholder="Practica, exhibicion, desempate informal, reto comunitario..."></textarea></label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-calendar-plus"></i> Crear amistoso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="duelTimerConfirmModal" tabindex="-1" aria-hidden="true" data-duel-timer-confirm-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><span class="tag">Inicio del duelo</span><h2 class="modal-title" data-duel-start-confirm-title>¿Comenzar el duelo?</h2></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"><p data-duel-start-confirm-copy>Confirma el inicio y decide si usaras el limite opcional de 20 minutos. Esta decision quedara bloqueada durante el duelo.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-duel-start-without-timer>Comenzar sin reloj</button>
                <button type="button" class="btn btn-primary fw-bold" data-duel-start-with-timer><i class="bi bi-play-fill"></i> Comenzar con 20 minutos</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="deleteFriendlyMatchModal" tabindex="-1" aria-hidden="true" data-delete-friendly-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" data-delete-friendly-form>
                <input type="hidden" name="action" value="delete_manual_friendly">
                <input type="hidden" name="friendly_match_id" data-delete-friendly-match-id>
                <div class="modal-header">
                    <div><span class="tag danger">Eliminar amistoso</span><h2 class="modal-title">¿Eliminar este combate?</h2></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>Se eliminara el amistoso y también su resultado, turnos, LP, estado en vivo y snapshots asociados.</p>
                    <strong data-delete-friendly-label>Combate amistoso</strong>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger fw-bold" type="submit"><i class="bi bi-trash3"></i> Eliminar amistoso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="calendarEventModal" tabindex="-1" aria-hidden="true" data-calendar-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag" data-calendar-modal-type>Evento</span>
                    <h2 class="modal-title" data-calendar-modal-title>Detalle del calendario</h2>
                    <p data-calendar-modal-meta></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="calendar-modal-grid">
                    <section>
                        <h3>Combates</h3>
                        <div data-calendar-modal-matches></div>
                    </section>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require YGO_APP_ROOT.'/includes/match-editor-view.php'; ?>
<?php require YGO_APP_ROOT.'/includes/event-audit-view.php'; ?>

