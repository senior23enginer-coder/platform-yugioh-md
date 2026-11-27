<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\LiveDuelWriteStore;

final readonly class PdoLiveDuelWriteStore implements LiveDuelWriteStore
{
    public function __construct(private PDO $pdo) {}

    public function requireOpen(int $matchId, bool $forUpdate): void
    {
        $seasonStmt = $this->pdo->prepare('SELECT c.season_id FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE m.id=?');
        $seasonStmt->execute([$matchId]);
        $seasonId = (int) $seasonStmt->fetchColumn();
        if ($forUpdate) {
            \ygo_season_require_open($this->pdo, $seasonId, true, true);
        } else {
            \ygo_season_require_open($this->pdo, $seasonId, true);
        }
    }

    public function update(int $matchId, array $state, int $revision, string $actor): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_live_duel_state SET duel_number=?, revision=?, state_json=?, updated_by=? WHERE match_id=?')
            ->execute([$state['duel_number'], $revision, json_encode($state, JSON_UNESCAPED_UNICODE), $actor, $matchId]);
    }

    public function recordSurrender(int $matchId, array $state, string $actor): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['match', $matchId, 'duel_surrendered', json_encode(['duel' => (int) $state['duel_number'], 'side' => $actor, 'at' => (string) $state['surrendered_at']], JSON_UNESCAPED_UNICODE)]);
    }
}
