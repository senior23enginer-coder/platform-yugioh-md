<?php
declare(strict_types=1);

function ygo_season_periods(): array
{
    return ['annual' => ['Anual', 12], 'semiannual' => ['Semestral', 6], 'quarterly' => ['Trimestral', 3]];
}

function ygo_season_status_label(string $status): string
{
    return ['draft' => 'Borrador', 'active' => 'Activa', 'completed' => 'Finalizada', 'archived' => 'Archivada'][$status] ?? $status;
}

function ygo_season_end(string $start, string $period): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    if (!$date || $date->format('Y-m-d') !== $start || !isset(ygo_season_periods()[$period])) {
        throw new InvalidArgumentException('Selecciona un ciclo y una fecha de inicio válidos.');
    }
    // Clamp the same day in the target month, including starts on February 29 or month end.
    $target = $date->modify('first day of this month')->modify('+' . ygo_season_periods()[$period][1] . ' months');
    $target = $target->setDate((int) $target->format('Y'), (int) $target->format('m'), min((int) $date->format('d'), (int) $target->format('t')));
    return $target->modify('-1 day')->format('Y-m-d');
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
    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_seasons WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ygo_season_require_open(PDO $pdo, int $id, bool $playing = false, bool $lock = false): array
{
    $season = ygo_season_find($pdo, $id, $lock);
    if (!$season) {
        throw new InvalidArgumentException('El evento debe estar vinculado a una temporada existente.');
    }
    if (in_array($season['status'], ['completed', 'archived'], true) || (!empty($season['ends_on']) && $season['ends_on'] < date('Y-m-d'))) {
        throw new InvalidArgumentException('La temporada está finalizada. Sus eventos están cerrados y solo permiten consulta.');
    }
    if ($playing && !empty($season['starts_on']) && $season['starts_on'] > date('Y-m-d')) {
        throw new InvalidArgumentException('La temporada todavía no ha comenzado.');
    }
    return $season;
}

function ygo_season_validate_event_dates(array $season, ?string $start, ?string $end): void
{
    foreach ([$start, $end] as $value) {
        if (!$value) continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || (!empty($season['starts_on']) && $value < $season['starts_on']) || (!empty($season['ends_on']) && $value > $season['ends_on'])) {
            throw new InvalidArgumentException('Las fechas del evento deben estar dentro del período de su temporada.');
        }
    }
    if ($start && $end && $end < $start) {
        throw new InvalidArgumentException('El fin del evento no puede ser anterior a su inicio.');
    }
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
    $stmt = $pdo->prepare('SELECT s.* FROM sx_ygo_seasons s JOIN sx_ygo_competitions c ON c.season_id=s.id WHERE c.id=?');
    $stmt->execute([$competitionId]);
    $season = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$season) throw new InvalidArgumentException('El evento debe pertenecer a una temporada existente.');
    $stmt = $pdo->prepare('SELECT MIN(scheduled_on) first_date,MAX(scheduled_on) last_date FROM sx_ygo_matches WHERE competition_id=?');
    $stmt->execute([$competitionId]);
    $range = $stmt->fetch(PDO::FETCH_ASSOC);
    ygo_season_validate_event_dates($season, $range['first_date'], $range['last_date']);
}

function ygo_season_save(PDO $pdo, array $input): int
{
    $id = (int) ($input['season_id'] ?? 0);
    $name = trim((string) ($input['season_name'] ?? ''));
    $period = (string) ($input['season_period'] ?? '');
    $start = (string) ($input['season_starts_on'] ?? '');
    $defaultEnd = ygo_season_end($start, $period);
    $end = trim((string) ($input['season_ends_on'] ?? '')) ?: $defaultEnd;
    $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
    if (!$endDate || $endDate->format('Y-m-d') !== $end || $end < $start) {
        throw new InvalidArgumentException('La fecha de cierre debe ser valida y no puede ser anterior al inicio.');
    }
    $status = (string) ($input['season_status'] ?? 'draft');
    if ($name === '' || mb_strlen($name) > 120 || !in_array($status, ['draft', 'active'], true)) {
        throw new InvalidArgumentException('Indica un nombre de hasta 120 caracteres y un estado válido.');
    }
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            ygo_season_require_open($pdo, $id, false, true);
            $outside = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_competitions WHERE season_id=? AND ((starts_on IS NOT NULL AND (starts_on<? OR starts_on>?)) OR (ends_on IS NOT NULL AND (ends_on<? OR ends_on>?)))');
            $outside->execute([$id, $start, $end, $start, $end]);
            $matches = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE c.season_id=? AND m.scheduled_on IS NOT NULL AND (m.scheduled_on<? OR m.scheduled_on>?)');
            $matches->execute([$id, $start, $end]);
            if ((int) $outside->fetchColumn() > 0 || (int) $matches->fetchColumn() > 0) {
                throw new InvalidArgumentException('El período dejaría eventos o partidas fuera de la temporada. Ajusta primero sus fechas.');
            }
            $pdo->prepare('UPDATE sx_ygo_seasons SET name=?,period_type=?,starts_on=?,ends_on=?,status=? WHERE id=?')->execute([$name, $period, $start, $end, $status, $id]);
        } else {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
            $slug = substr($slug ?: 'temporada', 0, 56) . '-' . bin2hex(random_bytes(5));
            $pdo->prepare('INSERT INTO sx_ygo_seasons(name,slug,status,period_type,starts_on,ends_on) VALUES(?,?,?,?,?,?)')->execute([$name, $slug, $status, $period, $start, $end]);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['season', $id, 'save_season', json_encode(['name'=>$name,'period_type'=>$period,'starts_on'=>$start,'ends_on'=>$end,'status'=>$status], JSON_UNESCAPED_UNICODE)]);
        if (!empty($input['season_finalize'])) ygo_season_close($pdo, $id);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function ygo_season_close(PDO $pdo, int $id, string $reason = 'manual'): int
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $season = ygo_season_find($pdo, $id, true);
        if (!$season) throw new InvalidArgumentException('Temporada no encontrada.');
        if (in_array($season['status'], ['completed', 'archived'], true)) {
            if ($ownsTransaction) $pdo->commit();
            return 0;
        }
        $stmt = $pdo->prepare("SELECT id,status FROM sx_ygo_competitions WHERE season_id=? AND status NOT IN ('completed','cancelled','closed','archived') FOR UPDATE");
        $stmt->execute([$id]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($events as $event) {
            $pdo->prepare("UPDATE sx_ygo_competitions SET status='closed',configuration_locked=1 WHERE id=?")->execute([$event['id']]);
            $pdo->prepare('INSERT INTO sx_ygo_competition_status_history(season_id,competition_id,from_status,to_status,action,payload) VALUES(?,?,?,?,?,?)')->execute([$id,$event['id'],$event['status'],'closed','season_closed',json_encode(['reason'=>$reason], JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->prepare("UPDATE sx_ygo_events SET status='closed' WHERE season_id=? AND status NOT IN ('completed','cancelled','closed')")->execute([$id]);
        $pdo->prepare("UPDATE sx_ygo_seasons SET status='completed',closed_at=NOW() WHERE id=?")->execute([$id]);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['season',$id,'close_season',json_encode(['reason'=>$reason,'closed_events'=>count($events)], JSON_UNESCAPED_UNICODE)]);
        if ($ownsTransaction) $pdo->commit();
        return count($events);
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function ygo_season_close_expired(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT id FROM sx_ygo_seasons WHERE ends_on<? AND status NOT IN ('completed','archived')");
    $stmt->execute([date('Y-m-d')]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) ygo_season_close($pdo, (int) $id, 'period_ended');
}

function ygo_season_delete(PDO $pdo, int $id): void
{
    $pdo->beginTransaction();
    try {
        if (!ygo_season_find($pdo, $id, true)) throw new InvalidArgumentException('Temporada no encontrada.');
        foreach (['sx_ygo_competitions', 'sx_ygo_divisions', 'sx_ygo_events'] as $table) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE season_id=?');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) throw new InvalidArgumentException('La temporada tiene eventos o divisiones asociados. Puedes finalizarla y conservar su historial.');
        }
        $pdo->prepare('DELETE gc FROM sx_ygo_game_rule_conditions gc JOIN sx_ygo_game_rules g ON g.id=gc.game_rule_id WHERE g.season_id=?')->execute([$id]);
        foreach (['sx_ygo_game_rules','sx_ygo_ruleset_versions','sx_ygo_rulesets','sx_ygo_settings','sx_ygo_ui_labels','sx_ygo_catalog_items'] as $table) {
            $pdo->prepare('DELETE FROM '.$table.' WHERE season_id=?')->execute([$id]);
        }
        $pdo->prepare('DELETE FROM sx_ygo_seasons WHERE id=?')->execute([$id]);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['season',$id,'delete_season','{}']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
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
