<?php
declare(strict_types=1);

/**
 * live-duel: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_live_duel_match(PDO $pdo, int $matchId): array
{
    $stmt = $pdo->prepare("
        SELECT m.id, m.status, m.best_of, m.round_number, m.table_number, m.scheduled_on, m.starts_at,
               COALESCE(c.name, dv.name, 'Partida') AS competition_name,
               da.name AS deck_a, db.name AS deck_b,
               COALESCE(ta.name, 'Sin equipo') AS team_a, COALESCE(tb.name, 'Sin equipo') AS team_b,
               COALESCE(ta.logo_url, '') AS logo_a, COALESCE(tb.logo_url, '') AS logo_b,
               r.d1, r.d2, r.d3, r.d4, r.d5, r.duel_details
        FROM sx_ygo_matches m
        JOIN sx_ygo_decks da ON da.id = m.deck_a_id
        JOIN sx_ygo_decks db ON db.id = m.deck_b_id
        LEFT JOIN sx_ygo_teams ta ON ta.id = da.team_id
        LEFT JOIN sx_ygo_teams tb ON tb.id = db.team_id
        LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
        LEFT JOIN sx_ygo_divisions dv ON dv.id = m.division_id
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function ygo_live_duel_initial_state(array $match): array
{
    $details = json_decode((string) ($match['duel_details'] ?? ''), true);
    $details = is_array($details) ? $details : [];
    $duelNumber = 1;
    foreach (range(1, 5) as $number) {
        if (!in_array((string) ($match['d' . $number] ?? ''), ['A', 'B', 'E'], true)) {
            $duelNumber = $number;
            break;
        }
        $duelNumber = min(5, $number + 1);
    }
    $detail = [];
    foreach ($details as $row) {
        if (is_array($row) && (int) ($row['duel'] ?? 0) === $duelNumber) {
            $detail = $row;
            break;
        }
    }
    $history = ygo_normalize_turn_history(is_array($detail['turn_history'] ?? null) ? $detail['turn_history'] : []);
    $starts = strtoupper((string) ($detail['starts_player'] ?? ''));
    $starts = in_array($starts, ['A', 'B'], true) ? $starts : '';
    if ($starts === '' && $history && (int) ($history[0]['turn'] ?? 0) === 1) {
        $historyStart = strtoupper((string) ($history[0]['side'] ?? ''));
        $starts = in_array($historyStart, ['A', 'B'], true) ? $historyStart : '';
    }
    $completed = max((int) ($detail['turn_count'] ?? 0), count($history));
    $activeSide = $starts === '' ? '' : ($completed % 2 === 0 ? $starts : ($starts === 'A' ? 'B' : 'A'));
    $state = [
        'duel_number' => $duelNumber,
        'status' => !empty($detail['duel_started']) ? 'running' : 'setup',
        'starts_player' => $starts,
        'active_side' => $activeSide,
        'turn_count' => $completed,
        'turn_history' => $history,
        'lp_a' => max(0, (int) (($detail['lp_a'] ?? '') !== '' ? $detail['lp_a'] : 8000)),
        'lp_b' => max(0, (int) (($detail['lp_b'] ?? '') !== '' ? $detail['lp_b'] : 8000)),
        'lp_start_a' => max(1, (int) ($detail['lp_start_a'] ?? 8000)),
        'lp_start_b' => max(1, (int) ($detail['lp_start_b'] ?? 8000)),
        'lp_history' => is_array($detail['lp_history'] ?? null) ? array_slice($detail['lp_history'], 0, 100) : [],
        'timer_mode' => in_array((string) ($detail['timer_mode'] ?? ''), ['none', 'countdown'], true) ? (string) $detail['timer_mode'] : '',
        'timer_running' => !empty($detail['timer_running']),
        'timer_started_at' => max(0, (int) ($detail['timer_started_at'] ?? 0)),
        'elapsed_seconds' => max(0, min(1200, (int) ($detail['duration_seconds'] ?? 0))),
        'dice_a' => max(0, (int) ($detail['dice_a'] ?? 0)),
        'dice_b' => max(0, (int) ($detail['dice_b'] ?? 0)),
        'dice_winner' => in_array(strtoupper((string) ($detail['dice_winner'] ?? $detail['turn_chosen_by'] ?? '')), ['A', 'B'], true) ? strtoupper((string) ($detail['dice_winner'] ?? $detail['turn_chosen_by'])) : '',
        'turn_decision' => (string) ($detail['turn_decision'] ?? ''),
        'surrendered_by' => in_array(strtoupper((string) ($detail['surrendered_by'] ?? '')), ['A', 'B'], true) ? strtoupper((string) $detail['surrendered_by']) : '',
        'surrendered_at' => mb_substr(trim((string) ($detail['surrendered_at'] ?? '')), 0, 40),
        'updated_at_ms' => (int) floor(microtime(true) * 1000),
        'correction_at_ms' => max(array_merge([0],array_map(static fn($d)=>(int)($d['manual_correction_at_ms']??0),$details))),
    ];
    $previousResult = $duelNumber > 1 ? (string) ($match['d' . ($duelNumber - 1)] ?? '') : '';
    $hasDrawReroll = $state['dice_a'] >= 1 && $state['dice_b'] >= 1 && in_array($state['dice_winner'], ['A', 'B'], true);
    if ($previousResult === 'E' && !$hasDrawReroll) {
        $state['status'] = 'setup';
        $state['starts_player'] = '';
        $state['active_side'] = '';
        $state['timer_running'] = false;
        $state['timer_started_at'] = 0;
    }
    return $state;
}

function ygo_live_duel_normalize_state(array $state): array
{
    $duel = max(1, min(5, (int) ($state['duel_number'] ?? 1)));
    $starts = strtoupper((string) ($state['starts_player'] ?? ''));
    $active = strtoupper((string) ($state['active_side'] ?? ''));
    $history = ygo_normalize_turn_history(is_array($state['turn_history'] ?? null) ? $state['turn_history'] : []);
    if (!in_array($starts, ['A', 'B'], true) && $history && (int) ($history[0]['turn'] ?? 0) === 1) {
        $historyStart = strtoupper((string) ($history[0]['side'] ?? ''));
        $starts = in_array($historyStart, ['A', 'B'], true) ? $historyStart : '';
    }
    if (!in_array($active, ['A', 'B'], true) && in_array($starts, ['A', 'B'], true)) {
        $completedTurns = max((int) ($state['turn_count'] ?? 0), count($history), $history ? (int) end($history)['turn'] : 0);
        $active = $completedTurns % 2 === 0 ? $starts : ($starts === 'A' ? 'B' : 'A');
    }
    $turnCount = max((int) ($state['turn_count'] ?? 0), count($history), $history ? (int) end($history)['turn'] : 0);
    $status = strtolower((string) ($state['status'] ?? 'setup'));
    $status = in_array($status, ['setup', 'ready', 'running', 'paused', 'completed'], true) ? $status : 'setup';
    if (!in_array($starts, ['A', 'B'], true) && in_array($status, ['ready', 'running', 'paused'], true)) {
        $status = 'setup';
        $active = '';
        $state['timer_running'] = false;
        $state['timer_started_at'] = 0;
    }
    return [
        'duel_number' => $duel,
        'status' => $status,
        'starts_player' => in_array($starts, ['A', 'B'], true) ? $starts : '',
        'active_side' => in_array($active, ['A', 'B'], true) ? $active : '',
        'turn_count' => max(0, min(999, $turnCount)),
        'turn_history' => $history,
        'lp_a' => max(0, min(99999, (int) ($state['lp_a'] ?? 8000))),
        'lp_b' => max(0, min(99999, (int) ($state['lp_b'] ?? 8000))),
        'lp_start_a' => max(1, min(99999, (int) ($state['lp_start_a'] ?? 8000))),
        'lp_start_b' => max(1, min(99999, (int) ($state['lp_start_b'] ?? 8000))),
        'lp_history' => array_slice(is_array($state['lp_history'] ?? null) ? $state['lp_history'] : [], 0, 100),
        'timer_mode' => in_array((string) ($state['timer_mode'] ?? ''), ['none', 'countdown'], true) ? (string) $state['timer_mode'] : '',
        'timer_running' => !empty($state['timer_running']),
        'timer_started_at' => max(0, (int) ($state['timer_started_at'] ?? 0)),
        'elapsed_seconds' => max(0, min(1200, (int) ($state['elapsed_seconds'] ?? 0))),
        'dice_a' => max(0, min(6, (int) ($state['dice_a'] ?? 0))),
        'dice_b' => max(0, min(6, (int) ($state['dice_b'] ?? 0))),
        'dice_winner' => in_array(strtoupper((string) ($state['dice_winner'] ?? '')), ['A', 'B'], true) ? strtoupper((string) $state['dice_winner']) : '',
        'turn_decision' => in_array(strtolower((string) ($state['turn_decision'] ?? '')), ['primero', 'segundo'], true) ? strtolower((string) $state['turn_decision']) : '',
        'surrendered_by' => in_array(strtoupper((string) ($state['surrendered_by'] ?? '')), ['A', 'B'], true) ? strtoupper((string) $state['surrendered_by']) : '',
        'surrendered_at' => mb_substr(trim((string) ($state['surrendered_at'] ?? '')), 0, 40),
        'updated_at_ms' => (int) floor(microtime(true) * 1000),
        'correction_at_ms' => max(0,(int)($state['correction_at_ms']??0)),
    ];
}

function ygo_live_duel_get(PDO $pdo, int $matchId, bool $forUpdate = false): array
{
    $match = ygo_live_duel_match($pdo, $matchId);
    if (!$match) return [];
    $select = $pdo->prepare('SELECT duel_number, revision, state_json, updated_by, updated_at FROM sx_ygo_live_duel_state WHERE match_id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
    $select->execute([$matchId]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $state = ygo_live_duel_initial_state($match);
        $pdo->prepare('INSERT IGNORE INTO sx_ygo_live_duel_state (match_id, duel_number, revision, state_json, updated_by) VALUES (?, ?, 1, ?, ?)')
            ->execute([$matchId, $state['duel_number'], json_encode($state, JSON_UNESCAPED_UNICODE), 'judge']);
        $select->execute([$matchId]);
        $row = $select->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $state = json_decode((string) ($row['state_json'] ?? ''), true);
    $normalizedState = ygo_live_duel_normalize_state(is_array($state) ? $state : ygo_live_duel_initial_state($match));
    $authoritativeState = ygo_live_duel_initial_state($match);
    if ((int) $normalizedState['duel_number'] !== (int) $authoritativeState['duel_number'] && strtoupper((string)$match['status'])!=='FINALIZADA') {
        $normalizedState = ygo_live_duel_normalize_state($authoritativeState);
    }
    $liveDuelNumber = (int) $normalizedState['duel_number'];
    $previousResult = $liveDuelNumber > 1 ? (string) ($match['d' . ($liveDuelNumber - 1)] ?? '') : '';
    $hasDrawReroll = (int) $normalizedState['dice_a'] >= 1 && (int) $normalizedState['dice_b'] >= 1 && in_array((string) $normalizedState['dice_winner'], ['A', 'B'], true);
    if ($previousResult === 'E' && !$hasDrawReroll) {
        $normalizedState['status'] = 'setup';
        $normalizedState['starts_player'] = '';
        $normalizedState['active_side'] = '';
        $normalizedState['timer_running'] = false;
        $normalizedState['timer_started_at'] = 0;
    }
    return [
        'match' => $match,
        'state' => $normalizedState,
        'revision' => max(1, (int) ($row['revision'] ?? 1)),
        'updated_by' => (string) ($row['updated_by'] ?? 'judge'),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function ygo_live_duel_api(PDO $pdo): void
{
    if(ygo_team_kind(ygo_competition_for_match($pdo,(int)($_REQUEST['match_id']??0)))!==''){http_response_code(409);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'message'=>'Usa el registro de resultados por equipo en la ficha del evento.']);exit;}
    $matchId = max(0, (int) ($_REQUEST['match_id'] ?? 0));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    if ($matchId <= 0 || !ygo_live_duel_match($pdo, $matchId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Partida no encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $seasonStmt = $pdo->prepare('SELECT c.season_id FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE m.id=?');
        $seasonStmt->execute([$matchId]);
        ygo_season_require_open($pdo, (int) $seasonStmt->fetchColumn(), true);
    } catch (InvalidArgumentException $error) {
        http_response_code(409);
        echo json_encode(['ok'=>false,'read_only'=>true,'message'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $actor = strtoupper((string) ($_POST['actor'] ?? ''));
    $actor = in_array($actor, ['A', 'B'], true) ? $actor : 'judge';
    $operation = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['operation'] ?? '')));
    $expectedRevision = max(0, (int) ($_POST['revision'] ?? 0));
    $payload = json_decode((string) ($_POST['payload'] ?? '{}'), true);
    $payload = is_array($payload) ? $payload : [];
    $pdo->beginTransaction();
    try {
        $seasonStmt = $pdo->prepare('SELECT c.season_id FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE m.id=?');
        $seasonStmt->execute([$matchId]);
        ygo_season_require_open($pdo, (int) $seasonStmt->fetchColumn(), true, true);
        $live = ygo_live_duel_get($pdo, $matchId, true);
        if ($expectedRevision > 0 && $expectedRevision !== (int) $live['revision']) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'conflict' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $state = $live['state'];
        if ($operation === 'sync' && $actor === 'judge') {
            $incoming = ygo_live_duel_normalize_state($payload);
            if ((int)($incoming['correction_at_ms'] ?? 0) !== (int)($state['correction_at_ms'] ?? 0)) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['ok' => false, 'conflict' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $incoming['correction_at_ms']=$state['correction_at_ms']??0;
            $expectedDuel = (int) ygo_live_duel_initial_state($live['match'])['duel_number'];
            if ((int) $incoming['duel_number'] !== $expectedDuel) {
                throw new RuntimeException('Guarda el resultado del duelo actual antes de avanzar al siguiente.');
            }
            if ((int) $incoming['duel_number'] !== (int) $state['duel_number']) {
                $state = $incoming;
            } else {
                $merged = ygo_merge_monotonic_turns(
                    ['turn_count' => $state['turn_count'], 'turn_history' => $state['turn_history']],
                    ['turn_count' => $incoming['turn_count'], 'turn_history' => $incoming['turn_history']]
                );
                $incoming['turn_count'] = $merged['detail']['turn_count'];
                $incoming['turn_history'] = $merged['detail']['turn_history'];
                if (in_array((string) ($state['surrendered_by'] ?? ''), ['A', 'B'], true)) {
                    $incoming['surrendered_by'] = (string) $state['surrendered_by'];
                    $incoming['surrendered_at'] = (string) ($state['surrendered_at'] ?? '');
                    $incoming['status'] = 'completed';
                    $incoming['timer_running'] = false;
                    $incoming['timer_started_at'] = 0;
                }
                $state = $incoming;
            }
        } elseif ($operation === 'end_turn') {
            if (!in_array((string) $state['status'], ['running', 'paused'], true) || !in_array((string) $state['active_side'], ['A', 'B'], true)) {
                throw new RuntimeException('El duelo todavía no está en curso.');
            }
            if ($actor !== 'judge' && $actor !== (string) $state['active_side']) {
                throw new RuntimeException('Todavía no es el turno de este lado.');
            }
            $nextTurn = min(999, max((int) $state['turn_count'], count($state['turn_history'])) + 1);
            $liveElapsed = (int) $state['elapsed_seconds'];
            if (!empty($state['timer_running']) && (int) ($state['timer_started_at'] ?? 0) > 0) {
                $liveElapsed = min(1200, $liveElapsed + max(0, (int) floor((microtime(true) * 1000 - (int) $state['timer_started_at']) / 1000)));
            }
            $state['turn_history'][] = ['turn' => $nextTurn, 'side' => (string) $state['active_side'], 'ended_at' => date(DATE_ATOM), 'elapsed_seconds' => $liveElapsed];
            $state['turn_history'] = ygo_normalize_turn_history($state['turn_history']);
            $state['turn_count'] = $nextTurn;
            $state['active_side'] = $state['active_side'] === 'A' ? 'B' : 'A';
        } elseif ($operation === 'surrender') {
            if (!in_array((string) $state['status'], ['running', 'paused'], true) || !in_array((string) $state['active_side'], ['A', 'B'], true)) {
                throw new RuntimeException('El duelo todavia no esta en curso.');
            }
            if ($actor === 'judge' || $actor !== (string) $state['active_side']) {
                throw new RuntimeException('Solo el jugador que tiene el turno puede rendirse.');
            }
            $state['surrendered_by'] = $actor;
            $state['surrendered_at'] = date(DATE_ATOM);
            $state['status'] = 'completed';
            $state['timer_running'] = false;
            $state['timer_started_at'] = 0;
        } elseif ($operation === 'lp_change') {
            if (!in_array((string) $state['status'], ['running', 'paused'], true)) {
                throw new RuntimeException('Los LP solo pueden cambiar mientras el duelo esta en curso.');
            }
            $side = strtoupper((string) ($payload['side'] ?? $actor));
            if (!in_array($side, ['A', 'B'], true) || ($actor !== 'judge' && $actor !== $side)) throw new RuntimeException('No puedes cambiar los LP del otro lado.');
            $field = 'lp_' . strtolower($side);
            $startField = 'lp_start_' . strtolower($side);
            $before = (int) $state[$field];
            $amount = max(0, min(99999, (int) ($payload['amount'] ?? 0)));
            $kind = (string) ($payload['kind'] ?? 'subtract');
            $after = $before;
            if ($kind === 'subtract') $after = max(0, $before - $amount);
            elseif ($kind === 'add') $after = min(99999, $before + $amount);
            elseif ($kind === 'half') $after = (int) floor($before / 2);
            elseif ($kind === 'reset') $after = (int) $state[$startField];
            elseif ($kind === 'set') $after = $amount;
            else throw new RuntimeException('Operación de LP inválida.');
            $state[$field] = $after;
            array_unshift($state['lp_history'], ['side' => strtolower($side), 'operation' => $kind, 'amount' => $amount, 'before' => $before, 'after' => $after, 'at' => date(DATE_ATOM)]);
            $state['lp_history'] = array_slice($state['lp_history'], 0, 100);
        } else {
            throw new RuntimeException('Acción en vivo no permitida.');
        }
        $state = ygo_live_duel_normalize_state($state);
        $revision = (int) $live['revision'] + 1;
        $pdo->prepare('UPDATE sx_ygo_live_duel_state SET duel_number=?, revision=?, state_json=?, updated_by=? WHERE match_id=?')
            ->execute([$state['duel_number'], $revision, json_encode($state, JSON_UNESCAPED_UNICODE), $actor, $matchId]);
        if ($operation === 'surrender') {
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'duel_surrendered', json_encode(['duel' => (int) $state['duel_number'], 'side' => $actor, 'at' => (string) $state['surrendered_at']], JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'match' => $live['match'], 'state' => $state, 'revision' => $revision, 'updated_by' => $actor], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
