<?php
declare(strict_types=1);

/**
 * standings: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_competition_standings(PDO $pdo, int $competitionId, ?string $phaseFilter = null): array
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionStandingsModule::create($pdo)->execute($competitionId, $phaseFilter);
}

function ygo_audit_label(string $action): string
{
    return [
        'configure_competition' => 'Configuración del evento guardada',
        'save_competition' => 'Evento guardado',
        'save_event_logo' => 'Logo del evento guardado',
        'apply_qualifier_divisions' => 'Divisiones aplicadas desde el clasificatorio',
        'save_team_lineups' => 'Equipos y alineaciones guardados',
        'save_event_admission' => 'Fuentes y clasificados guardados',
        'workflow_stage' => 'Fase creada',
        'workflow_revert' => 'Fase anterior corregida',
        'division_movements_applied' => 'Ascensos y descensos aplicados',
        'manual_honor_confirmed' => 'Campeón confirmado',
        'create_ffa' => 'Mesa multijugador creada',
        'finalize_ffa' => 'Evento multijugador finalizado',
        'delete_competition' => 'Evento eliminado',
        'blocked_create_match_by_deck_pool' => 'Bloqueo por pool de decks',
        'blocked_default_event_seed' => 'Bloqueo al sembrar eventos base',
        'blocked_create_match_by_rules' => 'Bloqueo por reglas de calendario',
        'blocked_schedule_by_rules' => 'Bloqueo al programar',
        'blocked_result_by_date' => 'Bloqueo de resultado por fecha',
        'turn_added' => 'Turno registrado',
        'save_dependency' => 'Prerequisito guardado',
        'delete_dependency' => 'Prerequisito eliminado',
        'advance_competition_phase' => 'Avance de fase',
        'division_promotion' => 'Ascenso de division',
        'division_relegation' => 'Descenso de division',
        'apply_from_qualifier' => 'Division aplicada desde clasificatorio',
        'blocked_finalize_competition' => 'Bloqueo al finalizar evento',
        'finalize_competition' => 'Evento finalizado',
        'recalculate_standings' => 'Ranking recalculado',
        'archive_qa_codex_data' => 'QA Codex archivado',
        'save_result' => 'Resultado guardado',
    ][$action] ?? $action;
}
