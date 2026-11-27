<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\LiveDuelReadStore;

final readonly class PdoLiveDuelReadStore implements LiveDuelReadStore
{
    public function __construct(private PDO $pdo) {}

    public function match(int $matchId): array
    {
        $stmt = $this->pdo->prepare("
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

    public function state(int $matchId, bool $forUpdate): array
    {
        $select = $this->pdo->prepare(
            'SELECT duel_number, revision, state_json, updated_by, updated_at FROM sx_ygo_live_duel_state WHERE match_id = ?' .
                ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $select->execute([$matchId]);
        return $select->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertInitial(int $matchId, array $state): void
    {
        $this->pdo
            ->prepare(
                'INSERT IGNORE INTO sx_ygo_live_duel_state (match_id, duel_number, revision, state_json, updated_by) VALUES (?, ?, 1, ?, ?)',
            )
            ->execute([
                $matchId,
                $state['duel_number'],
                json_encode($state, JSON_UNESCAPED_UNICODE),
                'judge',
            ]);
    }
}
