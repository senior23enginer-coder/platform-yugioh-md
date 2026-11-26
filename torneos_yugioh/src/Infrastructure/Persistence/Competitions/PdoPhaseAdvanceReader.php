<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\PhaseAdvanceReader;

final readonly class PdoPhaseAdvanceReader implements PhaseAdvanceReader
{
    public function __construct(private PDO $pdo) {}

    public function lock(int $competitionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function activeCompetition(int $competitionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function registeredDecks(int $competitionId): array
    {
        $stmt = $this->pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 ORDER BY sort_order,id');
        $stmt->execute([$competitionId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function matchCount(int $competitionId, string $phase): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
        $stmt->execute([$competitionId, $phase]);
        return (int) $stmt->fetchColumn();
    }

    public function phaseCounts(int $competitionId, string $phase): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN m.status = 'FINALIZADA' AND r.result IN ('A', 'B', 'DRAW') THEN 1 ELSE 0 END) AS closed
            FROM sx_ygo_matches m
            LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.competition_id = ? AND m.phase = ?
        ");
        $stmt->execute([$competitionId, $phase]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function roundResults(int $competitionId, string $phase, int $round): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.deck_a_id, m.deck_b_id, r.result
            FROM sx_ygo_matches m
            LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.competition_id = ? AND m.phase = ? AND m.round_number = ? AND m.status = 'FINALIZADA'
            ORDER BY m.table_number, m.id
        ");
        $stmt->execute([$competitionId, $phase, $round]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latestRound(int $competitionId, string $phase): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
        $stmt->execute([$competitionId, $phase]);
        return (int) $stmt->fetchColumn();
    }

    public function pendingCount(int $competitionId, string $phase, int $round): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ? AND round_number = ? AND status <> 'FINALIZADA'");
        $stmt->execute([$competitionId, $phase, $round]);
        return (int) $stmt->fetchColumn();
    }

    public function standings(int $competitionId): array
    {
        return \ygo_competition_standings($this->pdo, $competitionId);
    }

    public function cupByes(int $competitionId, int $round): array
    {
        return json_decode(\ygo_settings($this->pdo)['cup_bye_' . $competitionId . '_' . $round] ?? '[]', true) ?: [];
    }
}
