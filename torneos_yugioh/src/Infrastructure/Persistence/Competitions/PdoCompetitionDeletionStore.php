<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionDeletionStore;

final readonly class PdoCompetitionDeletionStore implements CompetitionDeletionStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function competition(int $seasonId, int $competitionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, status FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1',
        );
        $stmt->execute([$competitionId, $seasonId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function delete(int $seasonId, int $competitionId, string $status): void
    {
        $this->pdo->prepare('DELETE r FROM sx_ygo_match_results r INNER JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.competition_id = ?')->execute([$competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_matches WHERE competition_id = ?')->execute([$competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_events WHERE season_id = ? AND competition_id = ?')->execute([$seasonId, $competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_competition_decks WHERE competition_id = ?')->execute([$competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_competition_dependencies WHERE competition_id = ? OR depends_on_competition_id = ?')->execute([$competitionId, $competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_competition_status_history WHERE competition_id = ?')->execute([$competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ?')->execute([$competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_honors WHERE competition_id = ?')->execute([$competitionId]);
        if (\ygo_table_exists($this->pdo, 'sx_ygo_event_summaries')) $this->pdo->prepare('DELETE FROM sx_ygo_event_summaries WHERE competition_id = ?')->execute([$competitionId]);
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')->execute(['competition', $competitionId, 'delete_competition', json_encode(['status' => $status], JSON_UNESCAPED_UNICODE)]);
        $this->pdo->prepare('DELETE FROM sx_ygo_competitions WHERE id = ? AND season_id = ?')->execute([$competitionId, $seasonId]);
    }
}
