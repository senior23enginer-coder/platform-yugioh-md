<?php
declare(strict_types=1);

/**
 * event-lifecycle: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_ruleset_division_code(string $ruleset): ?string
{
    return [
        'tcg_advanced' => 'TCG_AVANZADO',
        'master_duel' => 'TCG_AVANZADO',
        'ocg' => 'OCG',
        'goat' => 'GOAT',
        'rush_duel' => 'RUSH_DUEL',
        'edison' => 'EDISON',
        'speed_duel' => 'SPEED_DUEL',
        'genesis' => 'GENESIS',
    ][$ruleset] ?? null;
}

function ygo_slugify(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?: '');
    return trim($value, '-') ?: 'evento';
}

function ygo_normalize_status_key(string $status): string
{
    return \StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy::normalizeStatus($status);
}

function ygo_event_status_labels(): array
{
    return [
        'pending_configuration' => 'Pendiente configuracion',
        'pending_event' => 'Programado',
        'active' => 'Activo',
        'completed' => 'Finalizado',
        'closed' => 'Cerrado por temporada',
        'cancelled' => 'Anulado',
    ];
}

function ygo_event_status_class(string $status): string
{
    $status = ygo_normalize_status_key($status);
    return [
        'draft' => 'state-pending-config',
        'pending_configuration' => 'state-pending-config',
        'pendiente_configuracion' => 'state-pending-config',
        'pending_event' => 'state-pending-event',
        'pendiente_evento' => 'state-pending-event',
        'active' => 'state-active',
        'activo' => 'state-active',
        'completed' => 'state-completed',
        'finalized' => 'state-completed',
        'finalizado' => 'state-completed',
        'finalizada' => 'state-completed',
        'closed' => 'state-completed',
        'cancelled' => 'state-cancelled',
        'canceled' => 'state-cancelled',
        'anulado' => 'state-cancelled',
        'anulada' => 'state-cancelled',
    ][$status] ?? 'state-pending-config';
}

function ygo_event_status_label(string $status): string
{
    $status = ygo_normalize_status_key($status);
    return [
        'draft' => 'Pendiente configuracion',
        'pending_configuration' => 'Pendiente configuracion',
        'pendiente_configuracion' => 'Pendiente configuracion',
        'pending_event' => 'Programado',
        'pendiente_evento' => 'Pendiente evento',
        'active' => 'Activo',
        'activo' => 'Activo',
        'completed' => 'Finalizado',
        'finalized' => 'Finalizado',
        'finalizado' => 'Finalizado',
        'finalizada' => 'Finalizado',
        'closed' => 'Cerrado por temporada',
        'cancelled' => 'Anulado',
        'canceled' => 'Anulado',
        'anulado' => 'Anulado',
        'anulada' => 'Anulado',
    ][$status] ?? ($status !== '' ? str_replace('_', ' ', $status) : 'Pendiente configuracion');
}

function ygo_record_competition_status_history(PDO $pdo, int $seasonId, int $competitionId, ?string $fromStatus, string $toStatus, string $action, array $payload = []): void
{
    \StationX\Yugioh\Infrastructure\Composition\CompetitionLifecycleModule::history($pdo)->execute(
        $seasonId,
        $competitionId,
        $fromStatus,
        $toStatus,
        $action,
        $payload,
    );
}

function ygo_competition_ready_to_finish(PDO $pdo, int $competitionId): array
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionLifecycleModule::completion($pdo)->execute($competitionId);
}

function ygo_persist_competition_standings(PDO $pdo, int $seasonId, int $competitionId, string $reason = 'current'): void
{
    \StationX\Yugioh\Infrastructure\Composition\CompetitionProjectionModule::standings($pdo)->execute($seasonId, $competitionId, $reason);
}

function ygo_backfill_missing_standing_snapshots(PDO $pdo, int $seasonId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionProjectionModule::standings($pdo)->backfill($seasonId);
}

function ygo_rebuild_competition_calendar_events_from_matches(PDO $pdo, int $seasonId, int $competitionId, string $eventType): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionProjectionModule::calendar($pdo)->rebuild($seasonId, $competitionId, $eventType);
}

function ygo_persist_competition_calendar_event(PDO $pdo, int $seasonId, int $competitionId): void
{
    \StationX\Yugioh\Infrastructure\Composition\CompetitionProjectionModule::calendar($pdo)->removeStart($seasonId, $competitionId);
}

function ygo_is_pending_event_qualifier_candidate(array $competition): bool
{
    return \StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy::pendingQualifier($competition);
}

function ygo_generate_competition_code(PDO $pdo, int $seasonId, string $type, string $ruleset, string $name, int $ignoreId = 0): string
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionLifecycleModule::codes($pdo)->next($seasonId, $ignoreId);
}

function ygo_ensure_sequential_competition_codes(PDO $pdo, int $seasonId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionLifecycleModule::codes($pdo)->ensureSequential($seasonId);
}
