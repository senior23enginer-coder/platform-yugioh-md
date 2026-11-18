<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

function ygo_ensure_schedule_capacity_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_schedule_blackouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NOT NULL,
            blackout_date DATE NOT NULL,
            reason VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_schedule_blackout (competition_id, blackout_date),
            KEY idx_ygo_schedule_blackout_date (season_id, blackout_date, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ygo_schedule_capacity_limit(PDO $pdo): int
{
    $settings = ygo_settings($pdo);
    return max(1, min(3, (int) ($settings['max_matches_per_day'] ?? 3)));
}

function ygo_schedule_available_quota(int $globalLimit, int $occupied, int $eventLimit, int $remaining): int
{
    return \StationX\Yugioh\Domain\Calendar\ScheduleQuota::availableQuota($globalLimit, $occupied, $eventLimit, $remaining);
}

function ygo_activate_due_scheduled_competitions(PDO $pdo): int
{
    $stmt = $pdo->query("
        SELECT c.id
        FROM sx_ygo_competitions c
        WHERE c.active=1
          AND EXISTS (SELECT 1 FROM sx_ygo_seasons s WHERE s.id=c.season_id AND s.status NOT IN ('completed','archived') AND (s.ends_on IS NULL OR s.ends_on>=CURDATE()))
          AND LOWER(REPLACE(c.status, ' ', '_')) IN ('pending_event','pendiente_evento')
          AND c.starts_on IS NOT NULL
          AND c.starts_on<=CURDATE()
          AND EXISTS (
            SELECT 1 FROM sx_ygo_matches m
            WHERE m.competition_id=c.id AND m.scheduled_on IS NOT NULL
          )
    ");
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) {
        return 0;
    }
    $update = $pdo->prepare("UPDATE sx_ygo_competitions SET status='active',configuration_locked=1,started_at=COALESCE(started_at,NOW()) WHERE id=?");
    $audit = $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type,entity_id,action,payload) VALUES (?,?,?,?)');
    foreach ($ids as $id) {
        $update->execute([$id]);
        $audit->execute(['competition', $id, 'auto_start_scheduled_competition', json_encode(['started_on' => date('Y-m-d')], JSON_UNESCAPED_UNICODE)]);
    }
    return count($ids);
}

function ygo_schedule_blackout_dates(PDO $pdo, int $competitionId): array
{
    $stmt = $pdo->prepare('SELECT blackout_date FROM sx_ygo_schedule_blackouts WHERE competition_id=? AND active=1');
    $stmt->execute([$competitionId]);
    return array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
}

function ygo_clear_pending_qualifier_dates(PDO $pdo, int $excludeCompetitionId = 0): int
{
    $stmt = $pdo->prepare("
        UPDATE sx_ygo_matches m
        JOIN sx_ygo_competitions c ON c.id=m.competition_id
        SET m.scheduled_on=NULL, m.starts_at=NULL
        WHERE m.status <> 'FINALIZADA' AND c.active=1 AND c.type='qualifier' AND c.id<>?
    ");
    $stmt->execute([$excludeCompetitionId]);
    return $stmt->rowCount();
}

function ygo_schedule_occupancy(PDO $pdo, int $excludeCompetitionId): array
{
    $stmt = $pdo->prepare("
        SELECT m.scheduled_on, m.starts_at
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id
        WHERE m.status <> 'FINALIZADA' AND m.scheduled_on IS NOT NULL
          AND (
            m.competition_id IS NULL
            OR (
              m.competition_id<>?
              AND c.active=1
              AND LOWER(REPLACE(c.status, ' ', '_')) NOT IN ('cancelled','completed','archived','finalizado','finalizada')
            )
          )
        ORDER BY m.scheduled_on, m.starts_at, m.id
    ");
    $stmt->execute([$excludeCompetitionId]);
    $occupancy = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $date = (string) ($row['scheduled_on'] ?? '');
        if ($date === '') {
            continue;
        }
        $occupancy[$date]['count'] = (int) ($occupancy[$date]['count'] ?? 0) + 1;
        $time = substr((string) ($row['starts_at'] ?? ''), 0, 5);
        if ($time !== '') {
            $occupancy[$date]['times'][$time] = true;
        }
    }
    return $occupancy;
}

function ygo_next_free_schedule_time(array $usedTimes, int $fallbackIndex = 0): string
{
    foreach (['19:00', '19:45', '20:30'] as $time) {
        if (empty($usedTimes[$time])) {
            return $time . ':00';
        }
    }
    return (new DateTimeImmutable('19:00'))->modify('+' . ($fallbackIndex * 45) . ' minutes')->format('H:i:s');
}

function ygo_reflow_pending_competition(PDO $pdo, int $competitionId, string $startDate, string $pattern, int $eventDailyLimit): array
{
    $competitionStmt = $pdo->prepare('SELECT id,type FROM sx_ygo_competitions WHERE id=? AND active=1 LIMIT 1');
    $competitionStmt->execute([$competitionId]);
    $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$competition) {
        return ['scheduled' => 0, 'start' => null, 'end' => null];
    }

    $matchStmt = $pdo->prepare("
        SELECT id
        FROM sx_ygo_matches
        WHERE competition_id=? AND status <> 'FINALIZADA'
        ORDER BY COALESCE(display_order, 999999), round_number, table_number, id
    ");
    $matchStmt->execute([$competitionId]);
    $matchIds = array_map('intval', $matchStmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$matchIds) {
        return ['scheduled' => 0, 'start' => null, 'end' => null];
    }

    $pdo->prepare("UPDATE sx_ygo_matches SET scheduled_on=NULL, starts_at=NULL WHERE competition_id=? AND status <> 'FINALIZADA'")
        ->execute([$competitionId]);

    $globalLimit = ygo_schedule_capacity_limit($pdo);
    $eventDailyLimit = max(1, min($globalLimit, $eventDailyLimit));
    $occupancy = ygo_schedule_occupancy($pdo, $competitionId);
    $blackouts = ygo_schedule_blackout_dates($pdo, $competitionId);
    $start = new DateTimeImmutable($startDate);
    $update = $pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?, starts_at=? WHERE id=? AND competition_id=? AND status <> "FINALIZADA"');
    $cursor = 0;
    $dayIndex = 0;
    $firstDate = null;
    $lastDate = null;

    while ($cursor < count($matchIds) && $dayIndex < 730) {
        $date = ygo_next_competition_match_date($start, $pattern, $dayIndex, 1);
        $dayIndex++;
        if (isset($blackouts[$date])) {
            continue;
        }
        $existing = (int) ($occupancy[$date]['count'] ?? 0);
        $quota = ygo_schedule_available_quota($globalLimit, $existing, $eventDailyLimit, count($matchIds) - $cursor);
        if ($quota < 1) {
            continue;
        }
        $usedTimes = $occupancy[$date]['times'] ?? [];
        for ($slot = 0; $slot < $quota; $slot++) {
            $time = ygo_next_free_schedule_time($usedTimes, $existing + $slot);
            $usedTimes[substr($time, 0, 5)] = true;
            $update->execute([$date, $time, $matchIds[$cursor], $competitionId]);
            $cursor++;
        }
        $occupancy[$date] = ['count' => $existing + $quota, 'times' => $usedTimes];
        $firstDate ??= $date;
        $lastDate = $date;
    }

    if ($cursor !== count($matchIds)) {
        throw new RuntimeException('No fue posible distribuir todos los combates dentro de la capacidad global.');
    }
    if ($lastDate !== null) {
        $pdo->prepare('UPDATE sx_ygo_competitions SET ends_on=? WHERE id=?')->execute([$lastDate, $competitionId]);
    }
    ygo_rebuild_competition_calendar_events_from_matches($pdo, ygo_current_season_id($pdo), $competitionId, (string) ($competition['type'] ?? 'match'));
    return ['scheduled' => $cursor, 'start' => $firstDate, 'end' => $lastDate];
}

function ygo_assign_explicit_competition_schedule(PDO $pdo, int $competitionId, array $dateQuotas): array
{
    $matchStmt = $pdo->prepare("
        SELECT id FROM sx_ygo_matches
        WHERE competition_id=? AND status <> 'FINALIZADA'
        ORDER BY COALESCE(display_order, 999999), round_number, table_number, id
    ");
    $matchStmt->execute([$competitionId]);
    $matchIds = array_map('intval', $matchStmt->fetchAll(PDO::FETCH_COLUMN));
    $pdo->prepare("UPDATE sx_ygo_matches SET scheduled_on=NULL, starts_at=NULL WHERE competition_id=? AND status <> 'FINALIZADA'")
        ->execute([$competitionId]);
    $occupancy = ygo_schedule_occupancy($pdo, $competitionId);
    $limit = ygo_schedule_capacity_limit($pdo);
    $update = $pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?,starts_at=? WHERE id=? AND competition_id=? AND status <> "FINALIZADA"');
    $cursor = 0;
    $lastDate = null;
    foreach ($dateQuotas as $date => $requested) {
        $existing = (int) ($occupancy[$date]['count'] ?? 0);
        $quota = ygo_schedule_available_quota($limit, $existing, (int) $requested, count($matchIds) - $cursor);
        $usedTimes = $occupancy[$date]['times'] ?? [];
        for ($slot = 0; $slot < $quota; $slot++) {
            $time = ygo_next_free_schedule_time($usedTimes, $existing + $slot);
            $usedTimes[substr($time, 0, 5)] = true;
            $update->execute([$date, $time, $matchIds[$cursor], $competitionId]);
            $cursor++;
        }
        if ($quota > 0) {
            $lastDate = $date;
        }
    }
    if ($cursor !== count($matchIds)) {
        throw new RuntimeException('La programacion explicita no contiene cupos para todos los combates.');
    }
    if ($lastDate !== null) {
        $pdo->prepare('UPDATE sx_ygo_competitions SET ends_on=? WHERE id=?')->execute([$lastDate, $competitionId]);
    }
    $typeStmt = $pdo->prepare('SELECT type FROM sx_ygo_competitions WHERE id=?');
    $typeStmt->execute([$competitionId]);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, ygo_current_season_id($pdo), $competitionId, (string) ($typeStmt->fetchColumn() ?: 'match'));
    return ['scheduled' => $cursor, 'end' => $lastDate];
}

function ygo_reflow_active_qualifiers(PDO $pdo, ?string $minimumStart = null): array
{
    $stmt = $pdo->prepare("
        SELECT id,starts_on,schedule_pattern,qualifier_matches_per_day
        FROM sx_ygo_competitions
        WHERE season_id=? AND active=1 AND type='qualifier' AND status NOT IN ('cancelled','completed','archived')
        ORDER BY id
    ");
    $stmt->execute([ygo_current_season_id($pdo)]);
    $today = new DateTimeImmutable($minimumStart ?: 'today');
    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $competition) {
        $configured = !empty($competition['starts_on']) ? new DateTimeImmutable((string) $competition['starts_on']) : $today;
        $start = $configured > $today ? $configured : $today;
        $results[(int) $competition['id']] = ygo_reflow_pending_competition(
            $pdo,
            (int) $competition['id'],
            $start->format('Y-m-d'),
            (string) (($competition['schedule_pattern'] ?? '') ?: 'consecutive'),
            max(1, (int) ($competition['qualifier_matches_per_day'] ?? 2))
        );
    }
    return $results;
}

function ygo_rebalance_after_competition_schedule(PDO $pdo, int $competitionId): void
{
    $stmt = $pdo->prepare('SELECT id,type,starts_on,schedule_pattern,qualifier_matches_per_day FROM sx_ygo_competitions WHERE id=? LIMIT 1');
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$competition) {
        return;
    }
    $type = (string) ($competition['type'] ?? '');
    $start = (string) (($competition['starts_on'] ?? '') ?: date('Y-m-d'));
    $pattern = (string) (($competition['schedule_pattern'] ?? '') ?: 'consecutive');
    $daily = max(1, (int) ($competition['qualifier_matches_per_day'] ?? 1));
    if ($type !== 'qualifier') {
        ygo_clear_pending_qualifier_dates($pdo, 0);
    }
    ygo_reflow_pending_competition($pdo, $competitionId, $start, $pattern, $daily);
    if ($type !== 'qualifier') {
        ygo_reflow_active_qualifiers($pdo);
    }
}

function ygo_schedule_can_displace_qualifier(PDO $pdo, int $matchId, string $date): bool
{
    $targetStmt = $pdo->prepare('SELECT c.type FROM sx_ygo_matches m LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE m.id=? LIMIT 1');
    $targetStmt->execute([$matchId]);
    if ((string) $targetStmt->fetchColumn() === 'qualifier') {
        return false;
    }
    $qualifierStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sx_ygo_matches m
        JOIN sx_ygo_competitions c ON c.id=m.competition_id
        WHERE m.scheduled_on=? AND m.id<>? AND m.status <> 'FINALIZADA' AND c.type='qualifier'
    ");
    $qualifierStmt->execute([$date, $matchId]);
    return (int) $qualifierStmt->fetchColumn() > 0;
}

function ygo_schedule_roster(PDO $pdo, int $competitionId, array $deckIds): void
{
    $pdo->prepare('UPDATE sx_ygo_competition_decks SET active=0 WHERE competition_id=?')->execute([$competitionId]);
    $insert = $pdo->prepare('INSERT INTO sx_ygo_competition_decks (competition_id,deck_id,sort_order,active) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order),active=1');
    foreach (array_values($deckIds) as $index => $deckId) {
        $insert->execute([$competitionId, (int) $deckId, $index + 1]);
    }
}

function ygo_ensure_october_format_league(PDO $pdo, int $seasonId, string $divisionCode, string $divisionName, string $ruleset, string $deckStyle, string $leagueName, string $startDate, string $pattern, int $dailyLimit, string $accent): array
{
    $divisionInsert = $pdo->prepare("
        INSERT INTO sx_ygo_divisions (season_id,code,name,cup_name,tier_order,deck_count,accent,description,starts_on,active)
        VALUES (?,?,?,?,9,0,?,?,?,1)
        ON DUPLICATE KEY UPDATE name=VALUES(name),accent=VALUES(accent),description=VALUES(description),starts_on=VALUES(starts_on),active=1
    ");
    $divisionInsert->execute([$seasonId, $divisionCode, $divisionName, 'Copa ' . str_replace('Primera Division ', '', $divisionName), $accent, 'Division oficial del formato ' . $ruleset . '.', $startDate]);
    $divisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id=? AND code=? AND active=1 LIMIT 1');
    $divisionStmt->execute([$seasonId, $divisionCode]);
    $divisionId = (int) $divisionStmt->fetchColumn();

    $deckStmt = $pdo->prepare('SELECT id FROM sx_ygo_decks WHERE active=1 AND style=? ORDER BY seed_rank,id');
    $deckStmt->execute([$deckStyle]);
    $deckIds = array_map('intval', $deckStmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($deckIds) < 4) {
        throw new RuntimeException('No hay suficientes decks para ' . $leagueName . '.');
    }
    $placeholders = implode(',', array_fill(0, count($deckIds), '?'));
    $pdo->prepare("UPDATE sx_ygo_decks SET division_id=? WHERE id IN ($placeholders) AND active=1")->execute([$divisionId, ...$deckIds]);
    $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count=? WHERE id=?')->execute([count($deckIds), $divisionId]);

    $leagueStmt = $pdo->prepare('SELECT id FROM sx_ygo_competitions WHERE season_id=? AND active=1 AND type="league" AND (event_ruleset=? OR name=?) ORDER BY id DESC LIMIT 1');
    $leagueStmt->execute([$seasonId, $ruleset, $leagueName]);
    $leagueId = (int) ($leagueStmt->fetchColumn() ?: 0);
    if ($leagueId <= 0) {
        $code = ygo_generate_competition_code($pdo, $seasonId, 'league', $ruleset, $leagueName);
        $pdo->prepare("
            INSERT INTO sx_ygo_competitions
                (season_id,division_id,code,name,type,scope,format,status,starts_on,accent,rules,active,match_format,best_of,participants_per_side,schedule_strategy,schedule_pattern,schedule_interval_days,qualifier_matches_per_day,event_ruleset,deck_pool,event_system,execution_model,participation,construction,environment,level,event_flow,participant_source,draw_policy,ranking_impact,deck_policy,access_type,phase_structure)
            VALUES
                (?,?,?,?,'league','division',?,'pending_event',?,?,?,1,'1v1',5,1,'auto_event',?,?,?,?,'division','Liga por puntos','round_robin','INDIVIDUAL_1V1','CONSTRUCTED','PRESENCIAL','COMPETITIVE','SEASON','DIVISION','ALLOWED','FULL','DECK_LOCK_EVENT','OPEN','LEAGUE')
        ")->execute([$seasonId, $divisionId, $code, $leagueName, $ruleset, $startDate, $accent, 'Liga oficial ' . $ruleset . '.', $pattern, $pattern === 'every_other_day' ? 2 : 1, $dailyLimit, $ruleset]);
        $leagueId = (int) $pdo->lastInsertId();
    }
    $pdo->prepare("
        UPDATE sx_ygo_competitions
        SET division_id=?,name=?,event_ruleset=?,format=?,deck_pool='division',starts_on=?,status='pending_event',match_format='1v1',best_of=5,participants_per_side=1,schedule_strategy='auto_event',schedule_pattern=?,schedule_interval_days=?,qualifier_matches_per_day=?,execution_model='round_robin',active=1
        WHERE id=? AND season_id=?
    ")->execute([$divisionId, $leagueName, $ruleset, $ruleset, $startDate, $pattern, $pattern === 'every_other_day' ? 2 : 1, $dailyLimit, $leagueId, $seasonId]);
    $pdo->prepare('UPDATE sx_ygo_divisions SET league_competition_id=? WHERE id=?')->execute([$leagueId, $divisionId]);
    ygo_schedule_roster($pdo, $leagueId, $deckIds);

    $matchCountStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');
    $matchCountStmt->execute([$leagueId]);
    if ((int) $matchCountStmt->fetchColumn() === 0) {
        $competitionStmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');
        $competitionStmt->execute([$leagueId]);
        $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        ygo_schedule_competition_by_model($pdo, $competition, $deckIds, $startDate, $pattern, '1v1', 1, $dailyLimit);
    }
    return ['division_id' => $divisionId, 'league_id' => $leagueId, 'deck_ids' => $deckIds];
}

function ygo_apply_october_2026_schedule(PDO $pdo): array
{
    $action = 'october_2026_shared_capacity_v2';
    ygo_ensure_schedule_capacity_tables($pdo);
    $seasonId = ygo_current_season_id($pdo);
    $pdo->prepare("UPDATE sx_ygo_divisions d SET d.deck_count=(SELECT COUNT(*) FROM sx_ygo_decks dk WHERE dk.division_id=d.id AND dk.active=1) WHERE d.season_id=? AND d.code IN ('GOAT','RUSH_DUEL')")
        ->execute([$seasonId]);
    $doneStmt = $pdo->prepare('SELECT payload FROM sx_ygo_audit_log WHERE action=? ORDER BY id DESC LIMIT 1');
    $doneStmt->execute([$action]);
    $existing = $doneStmt->fetchColumn();
    if ($existing !== false) {
        return json_decode((string) $existing, true) ?: ['applied' => true];
    }

    $pdo->beginTransaction();
    try {
        ygo_save_setting($pdo, 'max_matches_per_day', '3');
        $qualifierStmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE season_id=? AND active=1 AND type='qualifier' AND name LIKE '%Clasificatorio Previo Liga%' ORDER BY id DESC LIMIT 1");
        $qualifierStmt->execute([$seasonId]);
        $qualifierId = (int) $qualifierStmt->fetchColumn();
        $cupStmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE season_id=? AND active=1 AND type='cup' AND name LIKE '%Leyendas%2026%' ORDER BY id DESC LIMIT 1");
        $cupStmt->execute([$seasonId]);
        $cupId = (int) $cupStmt->fetchColumn();
        if ($qualifierId <= 0 || $cupId <= 0) {
            throw new RuntimeException('No se encontraron el clasificatorio y la Copa Leyendas activos.');
        }

        $blackout = $pdo->prepare('INSERT INTO sx_ygo_schedule_blackouts (season_id,competition_id,blackout_date,reason,active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE reason=VALUES(reason),active=1');
        foreach (['2026-10-02', '2026-10-04', '2026-10-05'] as $date) {
            $blackout->execute([$seasonId, $qualifierId, $date, 'Fecha reservada para Copa Leyendas 2026.']);
        }

        $goat = ygo_ensure_october_format_league($pdo, $seasonId, 'GOAT', 'Primera Division GOAT', 'goat', 'GOAT', 'Liga GOAT', '2026-10-14', 'consecutive', 3, '#36d8ff');
        $rush = ygo_ensure_october_format_league($pdo, $seasonId, 'RUSH_DUEL', 'Primera Division Rush Duel', 'rush_duel', 'Rush Duel', 'Liga Rush Duel', '2026-10-05', 'every_other_day', 1, '#ff7a59');

        ygo_clear_pending_qualifier_dates($pdo, 0);
        $pdo->prepare("UPDATE sx_ygo_matches SET scheduled_on=NULL,starts_at=NULL WHERE competition_id IN (?,?) AND status <> 'FINALIZADA'")
            ->execute([(int) $rush['league_id'], (int) $goat['league_id']]);
        $pdo->prepare("UPDATE sx_ygo_competitions SET starts_on='2026-10-02',schedule_pattern='consecutive',qualifier_matches_per_day=2 WHERE id=?")->execute([$cupId]);
        $cupSchedule = ygo_assign_explicit_competition_schedule($pdo, $cupId, ['2026-10-02' => 2, '2026-10-04' => 2, '2026-10-05' => 2]);
        $rushSchedule = ygo_reflow_pending_competition($pdo, (int) $rush['league_id'], '2026-10-05', 'every_other_day', 1);
        $goatSchedule = ygo_reflow_pending_competition($pdo, (int) $goat['league_id'], '2026-10-14', 'consecutive', 3);
        $pdo->prepare("UPDATE sx_ygo_competitions SET qualifier_matches_per_day=2,schedule_pattern='consecutive' WHERE id=?")->execute([$qualifierId]);
        $qualifierSchedule = ygo_reflow_pending_competition($pdo, $qualifierId, '2026-10-01', 'consecutive', 2);

        $payload = [
            'applied' => true,
            'global_daily_limit' => 3,
            'qualifier_id' => $qualifierId,
            'qualifier' => $qualifierSchedule,
            'cup_id' => $cupId,
            'cup' => $cupSchedule,
            'rush' => array_merge($rush, ['schedule' => $rushSchedule]),
            'goat' => array_merge($goat, ['schedule' => $goatSchedule]),
            'blackouts' => ['2026-10-02', '2026-10-04', '2026-10-05'],
        ];
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type,entity_id,action,payload) VALUES (?,?,?,?)')
            ->execute(['system', 0, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        $pdo->commit();
        return $payload;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
