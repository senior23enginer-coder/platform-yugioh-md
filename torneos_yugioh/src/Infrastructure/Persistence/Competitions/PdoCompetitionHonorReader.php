<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionHonorReader;

final readonly class PdoCompetitionHonorReader implements CompetitionHonorReader
{
    public function __construct(private PDO $pdo) {}

    public function exists(int $competitionId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id = ?');
        $stmt->execute([$competitionId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function competition(int $competitionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function workflow(array $competition): ?array
    {
        return \ygo_workflow_load($this->pdo, $competition);
    }

    public function finalResult(int $competitionId): array
    {
        $finalStmt = $this->pdo->prepare("
            SELECT m.deck_a_id, m.deck_b_id, r.result
            FROM sx_ygo_matches m
            JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.competition_id = ? AND m.status = 'FINALIZADA' AND r.result IN ('A', 'B')
              AND m.phase IN ('final', 'cup', 'top_cut', 'playoff')
            ORDER BY m.round_number DESC, m.id DESC
            LIMIT 1
        ");
        $finalStmt->execute([$competitionId]);
        return $finalStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function standings(int $competitionId): array
    {
        return \ygo_competition_standings($this->pdo, $competitionId);
    }

    public function teamId(int $deckId): int
    {
        $stmt = $this->pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id = ? LIMIT 1');
        $stmt->execute([$deckId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
