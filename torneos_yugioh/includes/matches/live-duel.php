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
    return \StationX\Yugioh\Domain\Matches\LiveDuelState::initial($match, static fn(): int => (int) floor(microtime(true) * 1000));
}

function ygo_live_duel_normalize_state(array $state): array
{
    return \StationX\Yugioh\Domain\Matches\LiveDuelState::normalize($state, static fn(): int => (int) floor(microtime(true) * 1000));
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
        $state = \StationX\Yugioh\Domain\Matches\LiveDuelState::transition(
            $live['state'],
            $live['match'],
            $actor,
            $operation,
            $payload,
            static fn(): int => (int) floor(microtime(true) * 1000),
            static fn(): float => microtime(true) * 1000,
            static fn(): string => date(DATE_ATOM)
        );
        if ($state === null) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'conflict' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
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
