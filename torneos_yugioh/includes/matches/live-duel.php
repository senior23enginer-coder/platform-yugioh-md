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
    $writer = new \StationX\Yugioh\Infrastructure\Persistence\Matches\PdoLiveDuelWriteStore($pdo);
    try {
        $writer->requireOpen($matchId, false);
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
        $result = (new \StationX\Yugioh\Application\Matches\ApplyLiveDuelChange(
            new \StationX\Yugioh\Application\Matches\LoadLiveDuel(new \StationX\Yugioh\Infrastructure\Persistence\Matches\PdoLiveDuelReadStore($pdo)),
            $writer
        ))->execute(
            $matchId,
            $expectedRevision,
            $actor,
            $operation,
            $payload,
            static fn(): int => (int) floor(microtime(true) * 1000),
            static fn(): float => microtime(true) * 1000,
            static fn(): string => date(DATE_ATOM)
        );
        if ($result === null) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'conflict' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $pdo->commit();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
