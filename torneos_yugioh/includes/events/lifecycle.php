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
    if ($competitionId <= 0) {
        return;
    }
    $phaseStmt = $pdo->prepare('SELECT DISTINCT phase FROM sx_ygo_matches WHERE competition_id = ? ORDER BY phase');
    $phaseStmt->execute([$competitionId]);
    $snapshotScopes = ['current' => null];
    foreach (array_filter(array_map('strval', $phaseStmt->fetchAll(PDO::FETCH_COLUMN))) as $phase) {
        $snapshotScopes['phase:' . $phase] = $phase;
    }
    foreach ($snapshotScopes as $snapshotKey => $phase) {
        $standings = ygo_competition_standings($pdo, $competitionId, $phase);
        if (!$standings) {
            $pdo->prepare('DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ? AND snapshot_key = ?')
                ->execute([$competitionId, $snapshotKey]);
            continue;
        }
        $maxRoundStmt = $phase === null
            ? $pdo->prepare('SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ?')
            : $pdo->prepare('SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
        $maxRoundStmt->execute($phase === null ? [$competitionId] : [$competitionId, $phase]);
        $roundNumber = (int) $maxRoundStmt->fetchColumn();
        $upsert = $pdo->prepare('
            INSERT INTO sx_ygo_standing_snapshots
                (season_id, competition_id, snapshot_key, round_number, deck_id, position, points, played, wins, draws, losses, duels_won, duels_lost, difference, payload)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                round_number=VALUES(round_number),
                position=VALUES(position),
                points=VALUES(points),
                played=VALUES(played),
                wins=VALUES(wins),
                draws=VALUES(draws),
                losses=VALUES(losses),
                duels_won=VALUES(duels_won),
                duels_lost=VALUES(duels_lost),
                difference=VALUES(difference),
                payload=VALUES(payload)
        ');
        $seenDeckIds = [];
        foreach ($standings as $index => $row) {
            $deckId = (int) ($row['id'] ?? 0);
            if ($deckId <= 0) {
                continue;
            }
            $seenDeckIds[] = $deckId;
            $upsert->execute([
                $seasonId,
                $competitionId,
                $snapshotKey,
                $roundNumber,
                $deckId,
                $index + 1,
                (int) ($row['pts'] ?? 0),
                (int) ($row['pj'] ?? 0),
                (int) ($row['pg'] ?? 0),
                (int) ($row['pe'] ?? 0),
                (int) ($row['pp'] ?? 0),
                (int) ($row['cg'] ?? 0),
                (int) ($row['cp'] ?? 0),
                (int) ($row['dp'] ?? 0),
                json_encode(['reason' => $reason, 'phase' => $phase, 'row' => $row], JSON_UNESCAPED_UNICODE),
            ]);
        }
        if ($seenDeckIds) {
            $placeholders = implode(',', array_fill(0, count($seenDeckIds), '?'));
            $pdo->prepare("DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ? AND snapshot_key = ? AND deck_id NOT IN ($placeholders)")
                ->execute(array_merge([$competitionId, $snapshotKey], $seenDeckIds));
        }
    }
}

function ygo_backfill_missing_standing_snapshots(PDO $pdo, int $seasonId): int
{
    if (!ygo_table_exists($pdo, 'sx_ygo_standing_snapshots')) {
        return 0;
    }
    $stmt = $pdo->prepare("
        SELECT c.id
        FROM sx_ygo_competitions c
        WHERE c.season_id = ? AND c.active = 1
          AND EXISTS (
              SELECT 1
              FROM sx_ygo_matches m
              JOIN sx_ygo_match_results r ON r.match_id = m.id
              WHERE m.competition_id = c.id AND r.result IS NOT NULL AND r.result <> ''
          )
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_standing_snapshots s
              WHERE s.competition_id = c.id AND s.snapshot_key = 'current'
          )
        ORDER BY c.id
        LIMIT 12
    ");
    $stmt->execute([$seasonId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($ids as $competitionId) {
        ygo_persist_competition_standings($pdo, $seasonId, $competitionId, 'backfill_missing_snapshot');
    }
    return count($ids);
}

function ygo_rebuild_competition_calendar_events_from_matches(PDO $pdo, int $seasonId, int $competitionId, string $eventType): int
{
    if ($competitionId <= 0) {
        return 0;
    }
    $competitionStmt = $pdo->prepare('SELECT id, division_id, name, type FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
    $competitionStmt->execute([$competitionId, $seasonId]);
    $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition) {
        return 0;
    }
    $pdo->prepare('UPDATE sx_ygo_events SET active = 0 WHERE season_id = ? AND competition_id = ? AND event_type = ?')
        ->execute([$seasonId, $competitionId, $eventType]);
    $rows = $pdo->prepare("
        SELECT scheduled_on, MIN(starts_at) AS starts_at, COUNT(*) AS match_count, MIN(venue) AS venue
        FROM sx_ygo_matches
        WHERE competition_id = ? AND scheduled_on IS NOT NULL
        GROUP BY scheduled_on
        ORDER BY scheduled_on
    ");
    $rows->execute([$competitionId]);
    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_events (season_id, competition_id, division_id, code, title, event_type, status, event_date, starts_at, venue, notes, active)
        VALUES (?, ?, ?, ?, ?, ?, "scheduled", ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE title=VALUES(title), status=VALUES(status), event_date=VALUES(event_date), starts_at=VALUES(starts_at), venue=VALUES(venue), notes=VALUES(notes), active=1
    ');
    $created = 0;
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $journey = $index + 1;
        $insert->execute([
            $seasonId,
            $competitionId,
            $competition['division_id'] !== null ? (int) $competition['division_id'] : null,
            'competition-' . $competitionId . '-' . $eventType . '-jornada-' . $journey,
            (string) ($competition['name'] ?? 'Evento') . ' - Jornada ' . $journey,
            $eventType,
            (string) $row['scheduled_on'],
            $row['starts_at'] ?: null,
            (string) ($row['venue'] ?: 'Station X Tulua'),
            (int) $row['match_count'] . ' combates programados desde duelos persistidos.',
        ]);
        $created++;
    }
    return $created;
}

function ygo_persist_competition_calendar_event(PDO $pdo, int $seasonId, int $competitionId): void
{
    if ($competitionId <= 0) {
        return;
    }
    $pdo->prepare("UPDATE sx_ygo_events SET active = 0 WHERE season_id = ? AND competition_id = ? AND code = ?")
        ->execute([$seasonId, $competitionId, 'competition-' . $competitionId . '-inicio']);
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
