<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

function ygo_season_periods(): array
{
    return \StationX\Yugioh\Domain\Seasons\SeasonDefinition::periods();
}

function ygo_season_status_label(string $status): string
{
    return ['draft' => 'Borrador', 'active' => 'Activa', 'completed' => 'Finalizada', 'archived' => 'Archivada'][$status] ?? $status;
}

function ygo_season_end(string $start, string $period): string
{
    return \StationX\Yugioh\Domain\Seasons\SeasonDefinition::end($start, $period);
}

function ygo_ensure_seasons_schema(PDO $pdo): void
{
    ygo_add_column($pdo, 'sx_ygo_seasons', 'period_type', "VARCHAR(20) NOT NULL DEFAULT 'annual' AFTER status");
    ygo_add_column($pdo, 'sx_ygo_seasons', 'starts_on', 'DATE NULL AFTER period_type');
    ygo_add_column($pdo, 'sx_ygo_seasons', 'ends_on', 'DATE NULL AFTER starts_on');
    ygo_add_column($pdo, 'sx_ygo_seasons', 'closed_at', 'DATETIME NULL AFTER ends_on');
    ygo_add_column($pdo, 'sx_ygo_seasons', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->exec("UPDATE sx_ygo_seasons SET starts_on=COALESCE(starts_on,'2026-01-01'),ends_on=COALESCE(ends_on,'2026-12-31') WHERE slug='temporada-2026'");
    $fk = $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sx_ygo_competitions' AND CONSTRAINT_NAME='fk_ygo_competition_season'");
    if ((int) $fk->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE sx_ygo_competitions ADD CONSTRAINT fk_ygo_competition_season FOREIGN KEY (season_id) REFERENCES sx_ygo_seasons(id) ON DELETE RESTRICT');
    }
}

function ygo_season_find(PDO $pdo, int $id, bool $lock = false): ?array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Seasons\PdoSeasonScheduleReader($pdo))->find($id, $lock);
}

function ygo_season_require_open(PDO $pdo, int $id, bool $playing = false, bool $lock = false): array
{
    return \StationX\Yugioh\Infrastructure\Composition\SeasonScheduleModule::create($pdo)->requireOpen($id, $playing, $lock);
}

function ygo_season_validate_event_dates(array $season, ?string $start, ?string $end): void
{
    \StationX\Yugioh\Domain\Seasons\SeasonSchedulePolicy::validateEventDates($season, $start, $end);
}

function ygo_season_initialize_context(PDO $pdo): void
{
    $id = (int) ($_GET['season_context'] ?? $_GET['public_season'] ?? 0);
    $eventId = (int) ($_POST['competition_id'] ?? $_POST['manual_competition_id'] ?? $_POST['checkin_competition_id'] ?? $_POST['dependency_competition_id'] ?? $_POST['honor_competition_id'] ?? 0);
    if ((int) ($_POST['dependency_id'] ?? 0) > 0) {
        $stmt = $pdo->prepare('SELECT competition_id FROM sx_ygo_competition_dependencies WHERE id=?');
        $stmt->execute([(int) $_POST['dependency_id']]);
        $eventId = (int) ($stmt->fetchColumn() ?: $eventId);
    }
    $matchId = (int) ($_POST['match_id'] ?? $_POST['schedule_match_id'] ?? $_POST['friendly_match_id'] ?? $_POST['checkin_match_id'] ?? 0);
    if ($matchId > 0) {
        $stmt = $pdo->prepare('SELECT competition_id FROM sx_ygo_matches WHERE id=?');
        $stmt->execute([$matchId]);
        $eventId = (int) ($stmt->fetchColumn() ?: $eventId);
    }
    if ($eventId > 0) {
        $stmt = $pdo->prepare('SELECT season_id FROM sx_ygo_competitions WHERE id=?');
        $stmt->execute([$eventId]);
        $id = (int) ($stmt->fetchColumn() ?: $id);
    } elseif (isset($_POST['competition_season_id'])) {
        $id = (int) $_POST['competition_season_id'];
    }
    if (($_POST['action'] ?? '') === 'save_division' && (int) ($_POST['division_id'] ?? 0) > 0) {
        $stmt = $pdo->prepare('SELECT season_id FROM sx_ygo_divisions WHERE id=?');
        $stmt->execute([(int) $_POST['division_id']]);
        $id = (int) ($stmt->fetchColumn() ?: $id);
    }
    if ($id > 0 && ygo_season_find($pdo, $id)) {
        $GLOBALS['ygo_request_season_id'] = $id;
    }
}

function ygo_season_validate_competition_schedule(PDO $pdo, int $competitionId): void
{
    \StationX\Yugioh\Infrastructure\Composition\SeasonScheduleModule::create($pdo)->validateCompetition($competitionId);
}

function ygo_season_save(PDO $pdo, array $input): int
{
    return \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->save($input);
}

function ygo_season_close(PDO $pdo, int $id, string $reason = 'manual'): int
{
    return \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->close($id, $reason);
}

function ygo_season_close_expired(PDO $pdo): void
{
    \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->closeExpired();
}

function ygo_season_delete(PDO $pdo, int $id): void
{
    \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->delete($id);
}

function ygo_season_handle_post(PDO $pdo, string $action): ?string
{
    if (!in_array($action, ['save_season','close_season','delete_season','set_season_status'], true)) return null;
    try {
        $id = (int) ($_POST['season_id'] ?? ygo_current_season_id($pdo));
        if ($action === 'save_season') {
            ygo_season_save($pdo, $_POST);
        } elseif ($action === 'delete_season') {
            ygo_season_delete($pdo, $id);
        } elseif ($action === 'close_season' || in_array($_POST['season_status'] ?? '', ['completed','archived'], true)) {
            ygo_season_close($pdo, $id);
        } else {
            $season = ygo_season_find($pdo, $id);
            if (!$season) throw new InvalidArgumentException('Temporada no encontrada.');
            ygo_season_save($pdo, ['season_id'=>$id,'season_name'=>$season['name'],'season_period'=>$season['period_type'],'season_starts_on'=>$season['starts_on'],'season_ends_on'=>$season['ends_on'],'season_status'=>$_POST['season_status'] ?? 'draft']);
        }
        return 'temporada_guardada';
    } catch (InvalidArgumentException $error) {
        $_SESSION['ygo_season_error'] = $error->getMessage();
        return 'temporada_error';
    }
}

function ygo_season_guard_post(PDO $pdo, string $action): ?string
{
    $actions = ['save_competition','configure_competition','save_match_result','save_match_schedule','create_match','delete_manual_friendly','advance_competition','finalize_competition','advance_phase','apply_qualifier_divisions','apply_league_movements','recalculate_competition_standings','archive_competition','delete_competition','set_qualifier_status','auto_schedule_qualifier','rebuild_qualifier_calendar','generate_friendlies','generate_leagues','generate_cups','generate_world','save_division','save_checkin','save_honor','save_competition_dependency','delete_competition_dependency'];
    if (!in_array($action, $actions, true)) return null;
    try {
        $seasonId = ygo_current_season_id($pdo);
        $season = ygo_season_require_open($pdo, $seasonId, $action === 'save_match_result');
        if ($action === 'save_competition' && (!isset($_POST['competition_season_id']) || (int) $_POST['competition_season_id'] !== $seasonId)) {
            throw new InvalidArgumentException('Selecciona la temporada del evento. La temporada de un evento existente se conserva.');
        }
        $start = $_POST['starts_on'] ?? $_POST['competition_start_on'] ?? $_POST['scheduled_on'] ?? $_POST['manual_scheduled_on'] ?? $_POST['qualifier_schedule_start'] ?? null;
        $end = $_POST['ends_on'] ?? null;
        ygo_season_validate_event_dates($season, $start, $end);
        if ($action === 'create_match' && (int) ($_POST['manual_competition_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Selecciona un evento con temporada para crear la partida.');
        }
        foreach (array_unique(array_filter(array_merge([(int) ($_POST['competition_division_id'] ?? 0)], array_map('intval', (array) ($_POST['competition_division_ids'] ?? []))))) as $divisionId) {
            $stmt = $pdo->prepare('SELECT season_id FROM sx_ygo_divisions WHERE id=?');
            $stmt->execute([$divisionId]);
            if ((int) $stmt->fetchColumn() !== $seasonId) throw new InvalidArgumentException('Las divisiones del evento deben pertenecer a su temporada.');
        }
        return null;
    } catch (InvalidArgumentException $error) {
        $_SESSION['ygo_season_error'] = $error->getMessage();
        return 'evento_temporada_bloqueada';
    }
}

function ygo_current_season_id(PDO $pdo): int
{
    if (!empty($GLOBALS['ygo_request_season_id'])) return (int) $GLOBALS['ygo_request_season_id'];
    return (int) $pdo->query("SELECT id FROM sx_ygo_seasons WHERE slug = 'temporada-2026'")->fetchColumn();
}


function ygo_season_event_rows(PDO $pdo): array
{
    return $pdo->query("SELECT c.id,c.season_id,c.code,c.name,c.type,c.event_ruleset,c.status,c.starts_on,c.ends_on,
        CASE WHEN c.status IN ('completed','closed','cancelled','archived') THEN
            (SELECT MAX(h.created_at) FROM sx_ygo_competition_status_history h WHERE h.competition_id=c.id AND h.to_status=c.status)
        ELSE NULL END AS closed_at
        FROM sx_ygo_competitions c WHERE c.active=1 ORDER BY c.season_id,c.name,c.id")->fetchAll(PDO::FETCH_ASSOC);
}
