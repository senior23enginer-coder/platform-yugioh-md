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
    return (new \StationX\Yugioh\Infrastructure\Persistence\Calendar\NativeScheduleCapacity(
        $pdo,
    ))->limit();
}

function ygo_schedule_available_quota(
    int $globalLimit,
    int $occupied,
    int $eventLimit,
    int $remaining,
): int {
    return \StationX\Yugioh\Domain\Calendar\ScheduleQuota::availableQuota(
        $globalLimit,
        $occupied,
        $eventLimit,
        $remaining,
    );
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
    $update = $pdo->prepare(
        "UPDATE sx_ygo_competitions SET status='active',configuration_locked=1,started_at=COALESCE(started_at,NOW()) WHERE id=?",
    );
    $audit = $pdo->prepare(
        'INSERT INTO sx_ygo_audit_log (entity_type,entity_id,action,payload) VALUES (?,?,?,?)',
    );
    foreach ($ids as $id) {
        $update->execute([$id]);
        $audit->execute([
            'competition',
            $id,
            'auto_start_scheduled_competition',
            json_encode(['started_on' => date('Y-m-d')], JSON_UNESCAPED_UNICODE),
        ]);
    }
    return count($ids);
}

function ygo_schedule_blackout_dates(PDO $pdo, int $competitionId): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoScheduleReader(
        $pdo,
    ))->blackouts($competitionId);
}

function ygo_clear_pending_qualifier_dates(PDO $pdo, int $excludeCompetitionId = 0): int
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoQualifierSchedules(
        $pdo,
    ))->clearPendingDatesExcept($excludeCompetitionId);
}

function ygo_schedule_occupancy(PDO $pdo, int $excludeCompetitionId): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoScheduleReader(
        $pdo,
    ))->occupancy($excludeCompetitionId);
}

function ygo_next_free_schedule_time(array $usedTimes, int $fallbackIndex = 0): string
{
    return \StationX\Yugioh\Domain\Calendar\ScheduleTimePolicy::next(
        $usedTimes,
        $fallbackIndex,
        (new \StationX\Yugioh\Infrastructure\Time\NativeCalendarTime())->evening(),
    );
}

function ygo_reflow_pending_competition(
    PDO $pdo,
    int $competitionId,
    string $startDate,
    string $pattern,
    int $eventDailyLimit,
): array {
    return \StationX\Yugioh\Infrastructure\Composition\CalendarModule::create(
        $pdo,
    )->reflow->execute($competitionId, $startDate, $pattern, $eventDailyLimit);
}

function ygo_assign_explicit_competition_schedule(
    PDO $pdo,
    int $competitionId,
    array $dateQuotas,
): array {
    return \StationX\Yugioh\Infrastructure\Composition\CalendarModule::create(
        $pdo,
    )->explicit->execute($competitionId, $dateQuotas);
}

function ygo_reflow_active_qualifiers(PDO $pdo, ?string $minimumStart = null): array
{
    return \StationX\Yugioh\Infrastructure\Composition\CalendarModule::create(
        $pdo,
    )->qualifiers->execute($minimumStart);
}

function ygo_rebalance_after_competition_schedule(PDO $pdo, int $competitionId): void
{
    \StationX\Yugioh\Infrastructure\Composition\CalendarModule::create($pdo)->rebalance->execute(
        $competitionId,
    );
}

function ygo_schedule_can_displace_qualifier(PDO $pdo, int $matchId, string $date): bool
{
    $targetStmt = $pdo->prepare(
        'SELECT c.type FROM sx_ygo_matches m LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE m.id=? LIMIT 1',
    );
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
    $pdo->prepare('UPDATE sx_ygo_competition_decks SET active=0 WHERE competition_id=?')->execute([
        $competitionId,
    ]);
    $insert = $pdo->prepare(
        'INSERT INTO sx_ygo_competition_decks (competition_id,deck_id,sort_order,active) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order),active=1',
    );
    foreach (array_values($deckIds) as $index => $deckId) {
        $insert->execute([$competitionId, (int) $deckId, $index + 1]);
    }
}

function ygo_ensure_october_format_league(
    PDO $pdo,
    int $seasonId,
    string $divisionCode,
    string $divisionName,
    string $ruleset,
    string $deckStyle,
    string $leagueName,
    string $startDate,
    string $pattern,
    int $dailyLimit,
    string $accent,
): array {
    $divisionInsert = $pdo->prepare("
        INSERT INTO sx_ygo_divisions (season_id,code,name,cup_name,tier_order,deck_count,accent,description,starts_on,active)
        VALUES (?,?,?,?,9,0,?,?,?,1)
        ON DUPLICATE KEY UPDATE name=VALUES(name),accent=VALUES(accent),description=VALUES(description),starts_on=VALUES(starts_on),active=1
    ");
    $divisionInsert->execute([
        $seasonId,
        $divisionCode,
        $divisionName,
        'Copa ' . str_replace('Primera Division ', '', $divisionName),
        $accent,
        'Division oficial del formato ' . $ruleset . '.',
        $startDate,
    ]);
    $divisionStmt = $pdo->prepare(
        'SELECT id FROM sx_ygo_divisions WHERE season_id=? AND code=? AND active=1 LIMIT 1',
    );
    $divisionStmt->execute([$seasonId, $divisionCode]);
    $divisionId = (int) $divisionStmt->fetchColumn();

    $deckStmt = $pdo->prepare(
        'SELECT id FROM sx_ygo_decks WHERE active=1 AND style=? ORDER BY seed_rank,id',
    );
    $deckStmt->execute([$deckStyle]);
    $deckIds = array_map('intval', $deckStmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($deckIds) < 4) {
        throw new RuntimeException('No hay suficientes decks para ' . $leagueName . '.');
    }
    $placeholders = implode(',', array_fill(0, count($deckIds), '?'));
    $pdo->prepare(
        "UPDATE sx_ygo_decks SET division_id=? WHERE id IN ($placeholders) AND active=1",
    )->execute([$divisionId, ...$deckIds]);
    $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count=? WHERE id=?')->execute([
        count($deckIds),
        $divisionId,
    ]);

    $leagueStmt = $pdo->prepare(
        'SELECT id FROM sx_ygo_competitions WHERE season_id=? AND active=1 AND type="league" AND (event_ruleset=? OR name=?) ORDER BY id DESC LIMIT 1',
    );
    $leagueStmt->execute([$seasonId, $ruleset, $leagueName]);
    $leagueId = (int) ($leagueStmt->fetchColumn() ?: 0);
    if ($leagueId <= 0) {
        $code = ygo_generate_competition_code($pdo, $seasonId, 'league', $ruleset, $leagueName);
        $pdo->prepare(
            "
            INSERT INTO sx_ygo_competitions
                (season_id,division_id,code,name,type,scope,format,status,starts_on,accent,rules,active,match_format,best_of,participants_per_side,schedule_strategy,schedule_pattern,schedule_interval_days,qualifier_matches_per_day,event_ruleset,deck_pool,event_system,execution_model,participation,construction,environment,level,event_flow,participant_source,draw_policy,ranking_impact,deck_policy,access_type,phase_structure)
            VALUES
                (?,?,?,?,'league','division',?,'pending_event',?,?,?,1,'1v1',5,1,'auto_event',?,?,?,?,'division','Liga por puntos','round_robin','INDIVIDUAL_1V1','CONSTRUCTED','PRESENCIAL','COMPETITIVE','SEASON','DIVISION','ALLOWED','FULL','DECK_LOCK_EVENT','OPEN','LEAGUE')
        ",
        )->execute([
            $seasonId,
            $divisionId,
            $code,
            $leagueName,
            $ruleset,
            $startDate,
            $accent,
            'Liga oficial ' . $ruleset . '.',
            $pattern,
            $pattern === 'every_other_day' ? 2 : 1,
            $dailyLimit,
            $ruleset,
        ]);
        $leagueId = (int) $pdo->lastInsertId();
    }
    $pdo->prepare(
        "
        UPDATE sx_ygo_competitions
        SET division_id=?,name=?,event_ruleset=?,format=?,deck_pool='division',starts_on=?,status='pending_event',match_format='1v1',best_of=5,participants_per_side=1,schedule_strategy='auto_event',schedule_pattern=?,schedule_interval_days=?,qualifier_matches_per_day=?,execution_model='round_robin',active=1
        WHERE id=? AND season_id=?
    ",
    )->execute([
        $divisionId,
        $leagueName,
        $ruleset,
        $ruleset,
        $startDate,
        $pattern,
        $pattern === 'every_other_day' ? 2 : 1,
        $dailyLimit,
        $leagueId,
        $seasonId,
    ]);
    $pdo->prepare('UPDATE sx_ygo_divisions SET league_competition_id=? WHERE id=?')->execute([
        $leagueId,
        $divisionId,
    ]);
    ygo_schedule_roster($pdo, $leagueId, $deckIds);

    $matchCountStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');
    $matchCountStmt->execute([$leagueId]);
    if ((int) $matchCountStmt->fetchColumn() === 0) {
        $competitionStmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');
        $competitionStmt->execute([$leagueId]);
        $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        ygo_schedule_competition_by_model(
            $pdo,
            $competition,
            $deckIds,
            $startDate,
            $pattern,
            '1v1',
            1,
            $dailyLimit,
        );
    }
    return ['division_id' => $divisionId, 'league_id' => $leagueId, 'deck_ids' => $deckIds];
}

function ygo_apply_october_2026_schedule(PDO $pdo): array
{
    $action = 'october_2026_shared_capacity_v2';
    ygo_ensure_schedule_capacity_tables($pdo);
    $seasonId = ygo_current_season_id($pdo);
    $pdo->prepare(
        "UPDATE sx_ygo_divisions d SET d.deck_count=(SELECT COUNT(*) FROM sx_ygo_decks dk WHERE dk.division_id=d.id AND dk.active=1) WHERE d.season_id=? AND d.code IN ('GOAT','RUSH_DUEL')",
    )->execute([$seasonId]);
    $doneStmt = $pdo->prepare(
        'SELECT payload FROM sx_ygo_audit_log WHERE action=? ORDER BY id DESC LIMIT 1',
    );
    $doneStmt->execute([$action]);
    $existing = $doneStmt->fetchColumn();
    if ($existing !== false) {
        return json_decode((string) $existing, true) ?: ['applied' => true];
    }

    $pdo->beginTransaction();
    try {
        ygo_save_setting($pdo, 'max_matches_per_day', '3');
        $qualifierStmt = $pdo->prepare(
            "SELECT id FROM sx_ygo_competitions WHERE season_id=? AND active=1 AND type='qualifier' AND name LIKE '%Clasificatorio Previo Liga%' ORDER BY id DESC LIMIT 1",
        );
        $qualifierStmt->execute([$seasonId]);
        $qualifierId = (int) $qualifierStmt->fetchColumn();
        $cupStmt = $pdo->prepare(
            "SELECT id FROM sx_ygo_competitions WHERE season_id=? AND active=1 AND type='cup' AND name LIKE '%Leyendas%2026%' ORDER BY id DESC LIMIT 1",
        );
        $cupStmt->execute([$seasonId]);
        $cupId = (int) $cupStmt->fetchColumn();
        if ($qualifierId <= 0 || $cupId <= 0) {
            throw new RuntimeException(
                'No se encontraron el clasificatorio y la Copa Leyendas activos.',
            );
        }

        $blackout = $pdo->prepare(
            'INSERT INTO sx_ygo_schedule_blackouts (season_id,competition_id,blackout_date,reason,active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE reason=VALUES(reason),active=1',
        );
        foreach (['2026-10-02', '2026-10-04', '2026-10-05'] as $date) {
            $blackout->execute([
                $seasonId,
                $qualifierId,
                $date,
                'Fecha reservada para Copa Leyendas 2026.',
            ]);
        }

        $goat = ygo_ensure_october_format_league(
            $pdo,
            $seasonId,
            'GOAT',
            'Primera Division GOAT',
            'goat',
            'GOAT',
            'Liga GOAT',
            '2026-10-14',
            'consecutive',
            3,
            '#36d8ff',
        );
        $rush = ygo_ensure_october_format_league(
            $pdo,
            $seasonId,
            'RUSH_DUEL',
            'Primera Division Rush Duel',
            'rush_duel',
            'Rush Duel',
            'Liga Rush Duel',
            '2026-10-05',
            'every_other_day',
            1,
            '#ff7a59',
        );

        ygo_clear_pending_qualifier_dates($pdo, 0);
        $pdo->prepare(
            "UPDATE sx_ygo_matches SET scheduled_on=NULL,starts_at=NULL WHERE competition_id IN (?,?) AND status <> 'FINALIZADA'",
        )->execute([(int) $rush['league_id'], (int) $goat['league_id']]);
        $pdo->prepare(
            "UPDATE sx_ygo_competitions SET starts_on='2026-10-02',schedule_pattern='consecutive',qualifier_matches_per_day=2 WHERE id=?",
        )->execute([$cupId]);
        $cupSchedule = ygo_assign_explicit_competition_schedule($pdo, $cupId, [
            '2026-10-02' => 2,
            '2026-10-04' => 2,
            '2026-10-05' => 2,
        ]);
        $rushSchedule = ygo_reflow_pending_competition(
            $pdo,
            (int) $rush['league_id'],
            '2026-10-05',
            'every_other_day',
            1,
        );
        $goatSchedule = ygo_reflow_pending_competition(
            $pdo,
            (int) $goat['league_id'],
            '2026-10-14',
            'consecutive',
            3,
        );
        $pdo->prepare(
            "UPDATE sx_ygo_competitions SET qualifier_matches_per_day=2,schedule_pattern='consecutive' WHERE id=?",
        )->execute([$qualifierId]);
        $qualifierSchedule = ygo_reflow_pending_competition(
            $pdo,
            $qualifierId,
            '2026-10-01',
            'consecutive',
            2,
        );

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
        $pdo->prepare(
            'INSERT INTO sx_ygo_audit_log (entity_type,entity_id,action,payload) VALUES (?,?,?,?)',
        )->execute(['system', 0, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        $pdo->commit();
        return $payload;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
