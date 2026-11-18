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
    $status = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $status) ?: $status;
    $status = strtolower(trim($status));
    $status = preg_replace('/[^a-z0-9]+/', '_', $status) ?: '';
    return trim($status, '_');
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
    if ($competitionId <= 0 || trim($toStatus) === '') {
        return;
    }
    $from = $fromStatus !== null ? ygo_normalize_status_key($fromStatus) : null;
    $to = ygo_normalize_status_key($toStatus);
    if ($from !== null && $from === $to) {
        return;
    }
    $pdo->prepare('
        INSERT INTO sx_ygo_competition_status_history (season_id, competition_id, from_status, to_status, action, payload)
        VALUES (?, ?, ?, ?, ?, ?)
    ')->execute([
        $seasonId,
        $competitionId,
        $from,
        $to,
        $action,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
}

function ygo_competition_ready_to_finish(PDO $pdo, int $competitionId): array
{
    $workflowQuery=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');$workflowQuery->execute([$competitionId]);$workflowCompetition=$workflowQuery->fetch(PDO::FETCH_ASSOC);
    if($workflowCompetition){$workflow=ygo_workflow_load($pdo,$workflowCompetition);if($workflow!==null && empty($workflow['complete']))return ['ok'=>false,'total'=>count($workflow['history'])+count($workflow['pending']??[]),'finalized'=>count($workflow['history']),'pending'=>count($workflow['pending']??[]),'reason'=>'Completa el reglamento mediante Crear siguiente fase; terminar una ronda no termina el evento.'];}
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_matches,
            SUM(CASE WHEN status = 'FINALIZADA' THEN 1 ELSE 0 END) AS finalized_matches
        FROM sx_ygo_matches
        WHERE competition_id = ?
    ");
    $stmt->execute([$competitionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int) ($row['total_matches'] ?? 0);
    $finalized = (int) ($row['finalized_matches'] ?? 0);
    return [
        'ok' => $total > 0 && $total === $finalized,
        'total' => $total,
        'finalized' => $finalized,
        'pending' => max(0, $total - $finalized),
    ];
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
    if ((string) ($competition['type'] ?? '') !== 'qualifier') {
        return false;
    }
    $status = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
    if (in_array($status, ['pending_event', 'pendiente_evento'], true)) {
        return true;
    }
    $selectedDeckIds = $competition['selected_deck_ids'] ?? [];
    $selectedDeckCount = is_array($selectedDeckIds) ? count($selectedDeckIds) : 0;
    $deckCount = max($selectedDeckCount, (int) ($competition['deck_pool_count'] ?? 0));
    return $status === 'pending_configuration' && $deckCount >= 4;
}

function ygo_generate_competition_code(PDO $pdo, int $seasonId, string $type, string $ruleset, string $name, int $ignoreId = 0): string
{
    $rows = $pdo->prepare("SELECT code FROM sx_ygo_competitions WHERE season_id = ? AND code LIKE 'YU-%' AND id <> ?");
    $rows->execute([$seasonId, $ignoreId]);
    $max = 0;
    foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (preg_match('/^YU-(\d+)$/', (string) $code, $match)) {
            $max = max($max, (int) $match[1]);
        }
    }
    $counter = $max + 1;
    $stmt = $pdo->prepare('SELECT id FROM sx_ygo_competitions WHERE season_id = ? AND code = ? AND id <> ? LIMIT 1');
    while (true) {
        $candidate = 'YU-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
        $stmt->execute([$seasonId, $candidate, $ignoreId]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $counter++;
    }
}

function ygo_ensure_sequential_competition_codes(PDO $pdo, int $seasonId): int
{
    $rows = $pdo->prepare('SELECT id, code FROM sx_ygo_competitions WHERE season_id = ? ORDER BY id');
    $rows->execute([$seasonId]);
    $competitions = $rows->fetchAll(PDO::FETCH_ASSOC);
    $used = [];
    $max = 0;
    foreach ($competitions as $competition) {
        $code = (string) ($competition['code'] ?? '');
        if (preg_match('/^YU-(\d+)$/', $code, $match)) {
            $number = (int) $match[1];
            $max = max($max, $number);
            $used['YU-' . str_pad((string) $number, 2, '0', STR_PAD_LEFT)] = true;
        } elseif ($code !== '') {
            $used[$code] = true;
        }
    }

    $updated = 0;
    $counter = $max + 1;
    $update = $pdo->prepare('UPDATE sx_ygo_competitions SET code = ? WHERE id = ? AND season_id = ?');
    foreach ($competitions as $competition) {
        $currentCode = (string) ($competition['code'] ?? '');
        if (preg_match('/^YU-\d{2,}$/', $currentCode)) {
            continue;
        }
        do {
            $candidate = 'YU-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        } while (isset($used[$candidate]));
        $used[$candidate] = true;
        $update->execute([$candidate, (int) $competition['id'], $seasonId]);
        $updated++;
    }
    return $updated;
}
