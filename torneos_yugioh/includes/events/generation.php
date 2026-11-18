<?php
declare(strict_types=1);

/**
 * event-generation: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_regenerate_league_matches(PDO $pdo, int $seasonId): void
{
    $divisionRows = $pdo->prepare('SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
    $divisionRows->execute([$seasonId]);
    foreach ($divisionRows->fetchAll(PDO::FETCH_ASSOC) as $division) {
        $divisionId = (int) $division['id'];
        $pdo->prepare("DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.division_id = ? AND m.phase = 'league'")->execute([$divisionId]);
        $pdo->prepare("DELETE FROM sx_ygo_matches WHERE division_id = ? AND phase = 'league'")->execute([$divisionId]);
        $decksStmt = $pdo->prepare('SELECT id, seed_rank, name, reason FROM sx_ygo_decks WHERE division_id = ? AND active = 1 ORDER BY seed_rank, name');
        $decksStmt->execute([$divisionId]);
        $deckRows = $decksStmt->fetchAll(PDO::FETCH_ASSOC);
        $fixture = buildFixtures(array_map(static fn(array $deck): array => [(int) $deck['seed_rank'], (string) $deck['name'], (string) ($deck['reason'] ?? '')], $deckRows));
        $deckIdByName = [];
        foreach ($deckRows as $deck) {
            $deckIdByName[(string) $deck['name']] = (int) $deck['id'];
        }
        foreach ($fixture as $round) {
            foreach ($round['matches'] as $match) {
                if (!isset($deckIdByName[$match['a']], $deckIdByName[$match['b']])) {
                    continue;
                }
                $pdo->prepare("
                    INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                    VALUES (?, 'league', ?, ?, ?, ?, 'PENDIENTE')
                ")->execute([$divisionId, $round['ronda'], $match['mesa'], $deckIdByName[$match['a']], $deckIdByName[$match['b']]]);
            }
        }
    }
}

function ygo_qualifier_finalize_check(PDO $pdo, ?int $competitionId = null): array
{
    $competitionId = $competitionId ?: ygo_selected_qualifier_competition_id($pdo);
    if (!$competitionId) {
        return ['ok' => false, 'reasons' => ['Selecciona un clasificatorio valido.']];
    }
    $competitionStmt = $pdo->prepare("SELECT qualifier_duels_per_deck FROM sx_ygo_competitions WHERE id=? AND type='qualifier' AND active=1 LIMIT 1");
    $competitionStmt->execute([$competitionId]);
    $requiredMatches = max(2, min(5, (int) ($competitionStmt->fetchColumn() ?: 3)));
    $reasons = [];
    $matchesStmt = $pdo->prepare("
        SELECT round_number, status
        FROM sx_ygo_matches
        WHERE phase = 'qualifier' AND competition_id = ?
        ORDER BY round_number, table_number, id
    ");
    $matchesStmt->execute([$competitionId]);
    $matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$matches) {
        return ['ok' => false, 'reasons' => ['No hay combates clasificatorios programados.']];
    }
    $rounds = [];
    foreach ($matches as $match) {
        $round = (int) $match['round_number'];
        $rounds[$round][] = (string) $match['status'];
    }
    if (empty($rounds[1])) {
        $reasons[] = 'No existe una primera ronda programada.';
    } elseif (array_filter($rounds[1], static fn(string $status): bool => $status !== 'FINALIZADA')) {
        $reasons[] = 'No se puede finalizar: toda la primera ronda debe estar jugada.';
    }
    foreach ($rounds as $round => $statuses) {
        $finished = count(array_filter($statuses, static fn(string $status): bool => $status === 'FINALIZADA'));
        if ($finished > 0 && $finished < count($statuses)) {
            $reasons[] = 'No se puede finalizar: la ronda ' . $round . ' esta en curso.';
        }
    }
    $standings = ygo_competition_standings($pdo, $competitionId, 'qualifier');
    if (!$standings || array_filter($standings, static fn(array $row): bool => (int) $row['pj'] < $requiredMatches)) {
        $reasons[] = 'Cada deck debe tener minimo ' . $requiredMatches . ' combates finalizados.';
    }
    return ['ok' => !$reasons, 'reasons' => array_values(array_unique($reasons))];
}

function ygo_finalize_qualifier(PDO $pdo, ?int $competitionId = null): bool
{
    $competitionId = $competitionId ?: ygo_selected_qualifier_competition_id($pdo);
    if (!$competitionId) {
        return false;
    }
    $seasonId = ygo_current_season_id($pdo);
    $divisionRows = $pdo->prepare('SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
    $divisionRows->execute([$seasonId]);
    $official = $divisionRows->fetchAll(PDO::FETCH_ASSOC);
    if (!$official) {
        return false;
    }
    $check = ygo_qualifier_finalize_check($pdo, $competitionId);
    if (!$check['ok']) {
        return false;
    }
    $standings = ygo_competition_standings($pdo, $competitionId, 'qualifier');
    $total = count($standings);
    $base = intdiv($total, count($official));
    $extra = $total % count($official);
    $offset = 0;
    foreach ($official as $index => $division) {
        $size = $base + ($index < $extra ? 1 : 0);
        $slice = array_slice($standings, $offset, $size);
        foreach ($slice as $localRank => $deck) {
            $pdo->prepare('UPDATE sx_ygo_decks SET division_id = ?, seed_rank = ? WHERE id = ?')
                ->execute([(int) $division['id'], $localRank + 1, (int) $deck['id']]);
        }
        $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count = ? WHERE id = ?')->execute([$size, (int) $division['id']]);
        $offset += $size;
    }
    ygo_regenerate_league_matches($pdo, $seasonId);
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['competition', $competitionId, 'finalize_qualifier', json_encode(['total_decks' => $total], JSON_UNESCAPED_UNICODE)]);
    return true;
}

function ygo_first_division_id(PDO $pdo): int
{
    $seasonId = ygo_current_season_id($pdo);
    return (int) $pdo->query("SELECT id FROM sx_ygo_divisions WHERE season_id = $seasonId ORDER BY tier_order, id LIMIT 1")->fetchColumn();
}

function ygo_decks_for_division(PDO $pdo, ?int $divisionId = null): array
{
    if ($divisionId) {
        $stmt = $pdo->prepare('SELECT id, name, seed_rank FROM sx_ygo_decks WHERE active = 1 AND division_id = ? ORDER BY seed_rank, name');
        $stmt->execute([$divisionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $pdo->query('SELECT id, name, seed_rank FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, name')->fetchAll(PDO::FETCH_ASSOC);
}

function ygo_clear_phase(PDO $pdo, int $divisionId, string $phase): void
{
    $pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.division_id = ? AND m.phase = ?')->execute([$divisionId, $phase]);
    $pdo->prepare('DELETE FROM sx_ygo_matches WHERE division_id = ? AND phase = ?')->execute([$divisionId, $phase]);
}

function ygo_generate_knockout(PDO $pdo, int $divisionId, string $phase, array $decks): int
{
    ygo_clear_phase($pdo, $divisionId, $phase);
    $created = 0;
    $count = count($decks);
    if ($count < 2) {
        return 0;
    }
    $pairs = intdiv($count, 2);
    for ($i = 0; $i < $pairs; $i++) {
        $a = $decks[$i];
        $b = $decks[$count - 1 - $i];
        $pdo->prepare("
            INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status)
            VALUES (?, ?, 1, ?, ?, ?, 'main', ?, 1, ?, ?, 'PENDIENTE')
        ")->execute([$divisionId, $phase, $i + 1, 'Ronda 1', $i + 1, $i + 1, (int) $a['id'], (int) $b['id']]);
        $created++;
    }
    return $created;
}

function ygo_generate_friendlies(PDO $pdo): int
{
    $divisionId = ygo_first_division_id($pdo);
    $decks = ygo_decks_for_division($pdo);
    if (!$divisionId || count($decks) < 2) {
        return 0;
    }
    ygo_clear_phase($pdo, $divisionId, 'friendly');
    $createdPairs = [];
    $created = 0;
    $count = count($decks);
    foreach ($decks as $index => $deck) {
        foreach ([1, 5, 11, 17, 23] as $roundIndex => $offset) {
            $rival = $decks[($index + $offset) % $count];
            if ((int) $deck['id'] === (int) $rival['id']) {
                continue;
            }
            $pairKey = implode('-', [min((int) $deck['id'], (int) $rival['id']), max((int) $deck['id'], (int) $rival['id'])]);
            if (isset($createdPairs[$pairKey])) {
                continue;
            }
            $createdPairs[$pairKey] = true;
            $pdo->prepare("
                INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                VALUES (?, 'friendly', ?, ?, ?, ?, 'PENDIENTE')
            ")->execute([$divisionId, $roundIndex + 1, $created + 1, (int) $deck['id'], (int) $rival['id']]);
            $created++;
        }
    }
    return $created;
}

function ygo_generate_cups(PDO $pdo): int
{
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
    $stmt->execute([$seasonId]);
    $created = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $divisionId) {
        $created += ygo_generate_knockout($pdo, (int) $divisionId, 'cup', ygo_decks_for_division($pdo, (int) $divisionId));
    }
    return $created;
}

function ygo_generate_world(PDO $pdo): int
{
    $divisionId = ygo_first_division_id($pdo);
    $decks = array_slice(ygo_decks_for_division($pdo), 0, 24);
    if (!$divisionId || count($decks) < 2) {
        return 0;
    }
    ygo_clear_phase($pdo, $divisionId, 'world');
    $created = 0;
    for ($group = 0; $group < 6; $group++) {
        $groupDecks = array_slice($decks, $group * 4, 4);
        for ($i = 0; $i < count($groupDecks); $i++) {
            for ($j = $i + 1; $j < count($groupDecks); $j++) {
                $pdo->prepare("
                    INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                    VALUES (?, 'world', ?, ?, ?, ?, 'PENDIENTE')
                ")->execute([$divisionId, $group + 1, $created + 1, (int) $groupDecks[$i]['id'], (int) $groupDecks[$j]['id']]);
                $created++;
            }
        }
    }
    return $created;
}

function ygo_generate_leagues(PDO $pdo): int
{
    $seasonId = ygo_current_season_id($pdo);
    ygo_regenerate_league_matches($pdo, $seasonId);
    return (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_matches WHERE phase = 'league'")->fetchColumn();
}

function ygo_selected_qualifier_competition_id(PDO $pdo): ?int
{
    $postedId = (int) ($_POST['qualifier_competition_id'] ?? 0);
    if ($postedId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE id=? AND type='qualifier' AND active=1 LIMIT 1");
        $stmt->execute([$postedId]);
        $found = (int) $stmt->fetchColumn();
        if ($found > 0) {
            return $found;
        }
    }
    $stmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE season_id=? AND type='qualifier' AND active=1 ORDER BY FIELD(status, 'active', 'draft', 'completed'), id LIMIT 1");
    $stmt->execute([ygo_current_season_id($pdo)]);
    $found = (int) $stmt->fetchColumn();
    return $found > 0 ? $found : null;
}

function ygo_competition_phase_match_count(PDO $pdo, int $competitionId, string $phase): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
    $stmt->execute([$competitionId, $phase]);
    return (int) $stmt->fetchColumn();
}

function ygo_competition_phase_finished(PDO $pdo, int $competitionId, string $phase): bool
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN m.status = 'FINALIZADA' AND r.result IN ('A', 'B', 'DRAW') THEN 1 ELSE 0 END) AS closed
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND m.phase = ?
    ");
    $stmt->execute([$competitionId, $phase]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int) ($row['total'] ?? 0);
    return $total > 0 && $total === (int) ($row['closed'] ?? 0);
}

function ygo_competition_round_winners(PDO $pdo, int $competitionId, string $phase, int $round): array
{
    $stmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND m.phase = ? AND m.round_number = ? AND m.status = 'FINALIZADA'
        ORDER BY m.table_number, m.id
    ");
    $stmt->execute([$competitionId, $phase, $round]);
    $winners = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $winner = $row['result'] === 'A' ? (int) $row['deck_a_id'] : ($row['result'] === 'B' ? (int) $row['deck_b_id'] : 0);
        if ($winner<=0) throw new InvalidArgumentException('La llave requiere un ganador valido en todos sus combates; no se avanzara con empate.');
        if ($winner > 0) {
            $winners[] = $winner;
        }
    }
    if($phase==='cup') $winners=array_merge($winners,json_decode(ygo_settings($pdo)['cup_bye_'.$competitionId.'_'.$round] ?? '[]',true) ?: []);
    return array_values(array_unique($winners));
}

function ygo_deck_ids_for_ruleset(PDO $pdo, string $ruleset): array
{
    return ygo_format_deck_ids($pdo, $ruleset);
}

function ygo_create_competition_knockout_round(PDO $pdo, array $competition, string $phase, array $deckIds, int $round, string $label): int
{
    $competitionId = (int) ($competition['id'] ?? 0);
    $divisionId = !empty($competition['division_id']) ? (int) $competition['division_id'] : null;
    $deckIds = array_values(array_unique(array_filter(array_map('intval', $deckIds), static fn(int $id): bool => $id > 0)));
    if ($competitionId <= 0 || count($deckIds) < 2) {
        return 0;
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ? AND round_number = ?');
    $exists->execute([$competitionId, $phase, $round]);
    if ((int) $exists->fetchColumn() > 0) {
        return 0;
    }
    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_matches
            (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status, scheduled_on, starts_at, venue, notes)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "main", ?, 1, ?, ?, "PENDIENTE", ?, "19:00:00", "Station X Tulua", ?)
    ');
    $pairs = intdiv(count($deckIds), 2);
    $created = 0;
    $seasonId = ygo_current_season_id($pdo);
    $scheduledBase = (string) (($competition['starts_on'] ?? '') ?: (new DateTimeImmutable())->format('Y-m-d'));
    for ($i = 0; $i < $pairs; $i++) {
        $deckA = $deckIds[$i];
        $deckB = $deckIds[count($deckIds) - 1 - $i];
        $displayOrder = ($round * 100) + ($i + 1);
        $scheduledOn = (new DateTimeImmutable($scheduledBase))->modify('+' . max(0, $round - 1) . ' days')->format('Y-m-d');
        $insert->execute([
            $divisionId,
            $competitionId,
            (int) ($competition['game_rule_id'] ?? 0) ?: null,
            $phase,
            (string) (($competition['match_format'] ?? '') ?: '1v1'),
            (int) (($competition['best_of'] ?? 0) ?: 3),
            max(1, min(3, (int) (($competition['participants_per_side'] ?? 0) ?: 1))),
            $round,
            $i + 1,
            $label,
            $i + 1,
            $displayOrder,
            $deckA,
            $deckB,
            $scheduledOn,
            'Generado por avance automatico de ' . $phase . '.',
        ]);
        $created++;
    }
    $program=ygo_fixed_program($pdo);
    if($phase==='cup' && count($deckIds)%2===1) {
        $bye=(int)$deckIds[intdiv(count($deckIds),2)];
        ygo_save_setting($pdo,'cup_bye_'.$competitionId.'_'.$round,json_encode([$bye]));
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$competitionId,'cup_round_bye',json_encode(['round'=>$round,'deck_id'=>$bye])]);
    }
    ygo_admission_schedule_stage($pdo,$competition,(string)($competition['starts_on']??date('Y-m-d')),(string)($competition['schedule_pattern']??'consecutive'),max(1,(int)($competition['qualifier_matches_per_day']??1)),$phase,$round);
    ygo_schedule_fixed_cup_round($pdo,$competitionId,$phase,$round);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $phase);
    return $created;
}

function ygo_record_competition_honor(PDO $pdo, int $competitionId): int
{
    if ($competitionId <= 0) {
        return 0;
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id = ?');
    $exists->execute([$competitionId]);
    if ((int) $exists->fetchColumn() > 0) {
        return 0;
    }
    $competitionStmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $competitionStmt->execute([$competitionId]);
    $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$competition) {
        return 0;
    }
    if(($competition['execution_model']??'')==='manual')return 0;
    $workflow=ygo_workflow_load($pdo,$competition);
    if($workflow!==null){if(!empty($workflow['complete']) && $workflow['champion']!==null){ygo_workflow_honor($pdo,$competition,(int)$workflow['champion'],(int)$workflow['runner_up']);return 1;}return 0;}
    $finalStmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND m.status = 'FINALIZADA' AND r.result IN ('A', 'B')
          AND m.phase IN ('final', 'cup', 'top_cut', 'playoff')
        ORDER BY m.round_number DESC, m.id DESC
        LIMIT 1
    ");
    $finalStmt->execute([$competitionId]);
    $final = $finalStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $championId = 0;
    $runnerId = 0;
    $usesDecisiveFinal = in_array((string) ($competition['type'] ?? ''), ['cup', 'final', 'world'], true)
        || in_array((string) ($competition['execution_model'] ?? ''), ['single_elimination', 'double_elimination', 'bracket', 'series'], true);
    if ($usesDecisiveFinal && $final) {
        $championId = $final['result'] === 'A' ? (int) $final['deck_a_id'] : (int) $final['deck_b_id'];
        $runnerId = $final['result'] === 'A' ? (int) $final['deck_b_id'] : (int) $final['deck_a_id'];
    } else {
        $ranking = ygo_competition_standings($pdo, $competitionId);
        $championId = (int) ($ranking[0]['id'] ?? 0);
        $runnerId = (int) ($ranking[1]['id'] ?? 0);
    }
    if ($championId <= 0) {
        return 0;
    }
    $teamStmt = $pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id = ? LIMIT 1');
    $teamStmt->execute([$championId]);
    $championTeamId = (int) ($teamStmt->fetchColumn() ?: 0);
    $runnerTeamId = 0;
    if ($runnerId > 0) {
        $teamStmt->execute([$runnerId]);
        $runnerTeamId = (int) ($teamStmt->fetchColumn() ?: 0);
    }
    $pdo->prepare('INSERT INTO sx_ygo_honors (season_id, competition_id, champion_deck_id, champion_team_id, runner_up_deck_id, runner_up_team_id, title, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            (int) $competition['season_id'],
            $competitionId,
            $championId,
            $championTeamId > 0 ? $championTeamId : null,
            $runnerId > 0 ? $runnerId : null,
            $runnerTeamId > 0 ? $runnerTeamId : null,
            'Campeon - ' . (string) $competition['name'],
            'Registrado automaticamente al cerrar el evento.',
        ]);
    return 1;
}

function ygo_apply_league_division_movements(PDO $pdo, int $competitionId): int
{
    return ygo_apply_configured_league_movements($pdo, $competitionId);
}

function ygo_advance_competition_phase(PDO $pdo, int $competitionId): int
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_advance_competition_phase_serialized($pdo,$competitionId));
}

function ygo_advance_competition_phase_serialized(PDO $pdo, int $competitionId): int
{
    $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
    try {
        $lock=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$lock->execute([$competitionId]);$c=$lock->fetch(PDO::FETCH_ASSOC);
        if(!$c) {if($owns)$pdo->commit();return 0;}
        if(ygo_admission_load($pdo,$c)!==null){$roster=$pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 ORDER BY sort_order,id');$roster->execute([$competitionId]);ygo_admission_require($pdo,$c,array_map('intval',$roster->fetchAll(PDO::FETCH_COLUMN)),false);}
        if((ygo_team_kind($c)!=='' || in_array((string)$c['execution_model'],ygo_workflow_models(),true)) && ygo_workflow_load($pdo,$c)!==null){
            $result=ygo_workflow_generate($pdo,$c);if($owns)$pdo->commit();return $result;
        }
        ygo_season_require_open($pdo,(int)$c['season_id']);
        if(in_array(ygo_normalize_status_key((string)$c['status']),['completed','closed','cancelled','archived'],true)) throw new InvalidArgumentException('El evento esta cerrado y no admite avance.');
        ygo_require_supported_model($c);
        $result=ygo_advance_competition_phase_locked($pdo,$competitionId);
        if($owns)$pdo->commit();return $result;
    } catch(Throwable $e) {if($owns && $pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function ygo_advance_competition_phase_locked(PDO $pdo, int $competitionId): int
{
    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$competition) {
        return 0;
    }
    $type = (string) (($competition['type'] ?? '') ?: 'tournament');
    $execution = (string) (($competition['execution_model'] ?? '') ?: 'manual');
    if(in_array($execution,ygo_workflow_models(),true))throw new InvalidArgumentException('Este evento anterior no tiene un reglamento versionado. No se reinterpretaran sus combates.');
    $phaseStructure = strtoupper((string) (($competition['phase_structure'] ?? '') ?: 'NONE'));
    $created = 0;

    if (
        $type === 'qualifier'
        && $execution === 'swiss'
        && $phaseStructure === 'NONE'
        && strtoupper((string) (($competition['event_flow'] ?? '') ?: '')) === 'QUALIFIES_TO_EVENT'
        && strtoupper((string) (($competition['advancement_rule'] ?? '') ?: '')) === 'RANKING_THRESHOLD'
    ) {
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'blocked_advance_competition_phase', json_encode(['reason' => 'division_qualifier_has_no_next_phase'], JSON_UNESCAPED_UNICODE)]);
        return 0;
    }

    $advanceKnockout = function (string $phase) use ($pdo, $competition, $competitionId): int {
        $roundStmt = $pdo->prepare('SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
        $roundStmt->execute([$competitionId, $phase]);
        $round = (int) $roundStmt->fetchColumn();
        if ($round <= 0) {
            return 0;
        }
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ? AND round_number = ? AND status <> 'FINALIZADA'");
        $pendingStmt->execute([$competitionId, $phase, $round]);
        if ((int) $pendingStmt->fetchColumn() > 0) {
            return 0;
        }
        $winners = ygo_competition_round_winners($pdo, $competitionId, $phase, $round);
        if (count($winners) === 1) {
            ygo_record_competition_honor($pdo, $competitionId);
            return 0;
        }
        if (count($winners) < 2) {
            return 0;
        }
        $nextRound = $round + 1;
        $label = count($winners) <= 2 ? 'Final' : 'Ronda ' . $nextRound;
        return ygo_create_competition_knockout_round($pdo, $competition, $phase, $winners, $nextRound, $label);
    };

    if (in_array($execution, ['single_elimination', 'double_elimination', 'top_cut', 'playoffs', 'bracket', 'series'], true)) {
        $phase = ygo_competition_phase_match_count($pdo,$competitionId,'cup')>0 ? 'cup' : ($type === 'world' ? 'world' : ($type === 'final' ? 'final' : 'cup'));
        $created += $advanceKnockout($phase);
    }

    if ($created === 0 && (in_array($execution, ['swiss_top_cut', 'top_cut'], true) || str_contains($phaseStructure, 'TOP_CUT'))) {
        $targetPhase = 'top_cut';
        if (ygo_competition_phase_match_count($pdo, $competitionId, $targetPhase) > 0) {
            $created += $advanceKnockout($targetPhase);
        } else {
            $basePhase = ygo_competition_phase_match_count($pdo, $competitionId, 'swiss') > 0 ? 'swiss' : 'qualifier';
            if (ygo_competition_phase_finished($pdo, $competitionId, $basePhase)) {
                $ranking = ygo_competition_standings($pdo, $competitionId);
                $topSize = ygo_knockout_target_size(count($ranking));
                $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
                $created += ygo_create_competition_knockout_round($pdo, $competition, $targetPhase, $deckIds, 1, 'Top Cut');
            }
        }
    }

    if ($created === 0 && (in_array($execution, ['groups_knockout'], true) || str_contains($phaseStructure, 'GROUPS'))) {
        $targetPhase = 'playoff';
        if (ygo_competition_phase_match_count($pdo, $competitionId, $targetPhase) > 0) {
            $created += $advanceKnockout($targetPhase);
        } elseif (ygo_competition_phase_finished($pdo, $competitionId, 'groups')) {
            $ranking = ygo_competition_standings($pdo, $competitionId);
            $topSize = ygo_knockout_target_size(count($ranking));
            $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
            $created += ygo_create_competition_knockout_round($pdo, $competition, $targetPhase, $deckIds, 1, 'Eliminatoria');
        }
    }

    if ($created === 0 && $type === 'league') {
        $targetPhase = 'playoff';
        if (ygo_competition_phase_match_count($pdo, $competitionId, $targetPhase) > 0) {
            $created += $advanceKnockout($targetPhase);
        } elseif (in_array($execution, ['season_playoffs', 'playoffs'], true) && ygo_competition_phase_finished($pdo, $competitionId, 'league')) {
            $ranking = ygo_competition_standings($pdo, $competitionId);
            $topSize = ygo_knockout_target_size(count($ranking));
            $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
            $created += ygo_create_competition_knockout_round($pdo, $competition, $targetPhase, $deckIds, 1, 'Playoff');
        }
    }

    if ($created > 0) {
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'advance_competition_phase', json_encode(['created' => $created, 'type' => $type, 'execution_model' => $execution], JSON_UNESCAPED_UNICODE)]);
    }
    return $created;
}

function ygo_advance_phase(PDO $pdo, string $phase, ?int $competitionId = null): int
{
    if ($competitionId !== null && $competitionId > 0) {
        return ygo_advance_competition_phase($pdo, $competitionId);
    }
    $divisionId = ygo_first_division_id($pdo);
    $rows = $pdo->prepare("
        SELECT m.division_id, m.phase, m.round_number, m.table_number, m.deck_a_id, m.deck_b_id, r.result
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.phase = ? AND m.status = 'FINALIZADA'
        ORDER BY m.division_id, m.round_number, m.table_number
    ");
    $rows->execute([$phase]);
    $winnersByDivision = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $winner = $row['result'] === 'A' ? (int) $row['deck_a_id'] : ($row['result'] === 'B' ? (int) $row['deck_b_id'] : 0);
        if ($winner > 0) {
            $winnersByDivision[(int) $row['division_id']][] = $winner;
        }
    }
    $created = 0;
    foreach ($winnersByDivision as $divId => $winners) {
        $nextRound = (int) $pdo->query("SELECT COALESCE(MAX(round_number), 0) + 1 FROM sx_ygo_matches WHERE division_id = $divId AND phase = " . $pdo->quote($phase))->fetchColumn();
        for ($i = 0; $i + 1 < count($winners); $i += 2) {
            $slot = intdiv($i, 2) + 1;
            $pdo->prepare("
                INSERT IGNORE INTO sx_ygo_matches (division_id, phase, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, 'PENDIENTE')
            ")->execute([$divId ?: $divisionId, $phase, $nextRound, $slot, 'Ronda ' . $nextRound, $slot, (($nextRound - 1) * 100) + $slot, $winners[$i], $winners[$i + 1]]);
            $created++;
        }
    }
    return $created;
}

function ygo_auto_schedule_qualifier(PDO $pdo): int
{
    $startDate = (string) ($_POST['qualifier_schedule_start'] ?? (new DateTimeImmutable())->format('Y-m-d'));
    $startTime = (string) ($_POST['qualifier_schedule_time'] ?? '19:00');
    $tablesPerDay = max(1, min(12, (int) ($_POST['qualifier_schedule_tables'] ?? 6)));
    $matchesPerDay = max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? 1)));
    $tablesPerDay = $matchesPerDay;
    $minutesPerRound = max(20, min(180, (int) ($_POST['qualifier_schedule_minutes'] ?? 45)));
    $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime) ?: new DateTimeImmutable();
    $competitionId = ygo_selected_qualifier_competition_id($pdo);

    $stmt = $pdo->prepare("
        SELECT id, round_number, table_number
        FROM sx_ygo_matches
        WHERE phase = 'qualifier'
          AND (? IS NULL OR competition_id = ? OR competition_id IS NULL)
          AND status <> 'FINALIZADA'
        ORDER BY round_number, table_number, id
    ");
    $stmt->execute([$competitionId, $competitionId]);
    $matches = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $updated = 0;
    foreach ($matches as $index => $match) {
        $dayOffset = intdiv($index, $tablesPerDay);
        $slot = $index % $tablesPerDay;
        $dateTime = $base->modify('+' . $dayOffset . ' days')->modify('+' . ($slot * $minutesPerRound) . ' minutes');
        $pdo->prepare('UPDATE sx_ygo_matches SET competition_id=COALESCE(competition_id, ?), scheduled_on=?, starts_at=?, venue=COALESCE(NULLIF(venue, ""), ?), notes=COALESCE(NULLIF(notes, ""), ?) WHERE id=?')
            ->execute([
                $competitionId,
                $dateTime->format('Y-m-d'),
                $dateTime->format('H:i:s'),
                'Station X Tulua',
                'Programado automaticamente desde Gestion.',
                (int) $match['id'],
            ]);
        $updated++;
    }
    if ($competitionId) {
        $pdo->prepare('UPDATE sx_ygo_competitions SET qualifier_matches_per_day = ? WHERE id = ?')
            ->execute([$matchesPerDay, $competitionId]);
    }
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['competition', $competitionId, 'auto_schedule_qualifier', json_encode(['updated' => $updated, 'start' => $startDate, 'time' => $startTime, 'matches_per_day' => $matchesPerDay], JSON_UNESCAPED_UNICODE)]);
    return $updated;
}
