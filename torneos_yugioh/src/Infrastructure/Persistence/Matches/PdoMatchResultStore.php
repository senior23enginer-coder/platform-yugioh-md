<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\MatchResultStore;

final readonly class PdoMatchResultStore implements MatchResultStore
{
    public function __construct(private PDO $pdo) {}

    public function syncedResult(string $requestId): string|false
    {
        $stmt = $this->pdo->prepare('SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1');
        $stmt->execute([$requestId]);
        $result = $stmt->fetchColumn();
        return $result === false ? false : (string) $result;
    }

    public function audit(int $matchId, string $action, array $payload): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['match', $matchId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    public function ensure(int $matchId): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO sx_ygo_match_results (match_id) VALUES (?)')->execute([$matchId]);
    }

    public function lockExisting(int $matchId): array
    {
        $existingStmt = $this->pdo->prepare(
            'SELECT r.d1, r.d2, r.d3, r.d4, r.d5, r.duel_details, m.status AS match_status FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id=r.match_id WHERE r.match_id = ? LIMIT 1 FOR UPDATE',
        );
        $existingStmt->execute([$matchId]);
        return $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function persistedTurns(int $matchId): array
    {
        $persistedTurnsStmt = $this->pdo->prepare('
            SELECT duel_number, turn_number, side, ended_at, elapsed_seconds
            FROM sx_ygo_turn_audit
            WHERE match_id = ?
            ORDER BY duel_number, turn_number
        ');
        $persistedTurnsStmt->execute([$matchId]);
        return $persistedTurnsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function appendTurns(int $matchId, iterable $rows): void
    {
        $turnAuditInsert = $this->pdo->prepare('
            INSERT IGNORE INTO sx_ygo_turn_audit
                (match_id, duel_number, turn_number, side, ended_at, elapsed_seconds)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        foreach ($rows as $row) {
            $turnAuditInsert->execute([$matchId, $row['duel'], $row['turn'], $row['side'], $row['endedAt'], $row['elapsed']]);
        }
    }

    public function save(array $values): void
    {
        $this->pdo->prepare(
            "
            INSERT INTO sx_ygo_match_results (match_id, d1, d2, d3, d4, d5, result, score, validation_status, notes, dice_roll, turn_choice, dice_winner, turn_chosen_by, turn_decision, starts_player, duel_details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                d1 = VALUES(d1), d2 = VALUES(d2), d3 = VALUES(d3), d4 = VALUES(d4), d5 = VALUES(d5),
                result = VALUES(result), score = VALUES(score), validation_status = VALUES(validation_status), notes = VALUES(notes),
                dice_roll = VALUES(dice_roll), turn_choice = VALUES(turn_choice), dice_winner = VALUES(dice_winner),
                turn_chosen_by = VALUES(turn_chosen_by), turn_decision = VALUES(turn_decision), starts_player = VALUES(starts_player),
                duel_details = VALUES(duel_details)
        ",
        )->execute([
            $values['matchId'],
            $values['d1'],
            $values['d2'],
            $values['d3'],
            $values['d4'],
            $values['d5'],
            $values['winner'],
            $values['score'],
            $values['validation'],
            $values['notes'],
            $values['diceRoll'],
            $values['turnChoice'],
            $values['diceWinner'],
            $values['turnChosenBy'],
            $values['turnDecision'],
            $values['startsPlayer'],
            $values['duelDetails'],
        ]);
    }

    public function competitionId(int $matchId): int
    {
        $stmt = $this->pdo->prepare('SELECT competition_id FROM sx_ygo_matches WHERE id = ? LIMIT 1');
        $stmt->execute([$matchId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function saveLiveState(int $matchId, array $state): void
    {
        $this->pdo->prepare(
            "
            INSERT INTO sx_ygo_live_duel_state (match_id, duel_number, revision, state_json, updated_by)
            VALUES (?, ?, 1, ?, 'judge')
            ON DUPLICATE KEY UPDATE duel_number=VALUES(duel_number), revision=revision+1, state_json=VALUES(state_json), updated_by='judge'
        ",
        )->execute([
            $matchId,
            $state['duel_number'],
            json_encode($state, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function recordSync(string $requestId, int $matchId, string $payloadHash): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)')
            ->execute([$requestId, $matchId, $payloadHash, 'resultado']);
    }
}
