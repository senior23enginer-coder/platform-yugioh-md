<?php
declare(strict_types=1);

/**
 * live-duel: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_live_duel_match(PDO $pdo, int $matchId): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Matches\PdoLiveDuelReadStore($pdo))->match($matchId);
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
    return (new \StationX\Yugioh\Application\Matches\LoadLiveDuel(
        new \StationX\Yugioh\Infrastructure\Persistence\Matches\PdoLiveDuelReadStore($pdo)
    ))->execute($matchId, $forUpdate, static fn(): int => (int) floor(microtime(true) * 1000));
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
