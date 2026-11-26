<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionStandingsReader;

final readonly class PdoCompetitionStandingsReader implements CompetitionStandingsReader
{
    public function __construct(private PDO $pdo) {}

    public function competition(int $competitionId): array
    {
        $competitionStmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
        $competitionStmt->execute([$competitionId]);
        return $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function workflowRanking(array $competition, ?string $phaseFilter): array
    {
        return \ygo_workflow_live_ranking($this->pdo, $competition, $phaseFilter);
    }

    public function decks(int $competitionId): array
    {
        $selectedStmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1');
        $selectedStmt->execute([$competitionId]);
        $hasSelectedDecks = (int) $selectedStmt->fetchColumn() > 0;
        if ($hasSelectedDecks) {
            $deckStmt = $this->pdo->prepare("
                SELECT
                    d.id,
                    d.name,
                    d.seed_rank,
                    COALESCE(t.name, 'Sin equipo') AS team,
                    COALESCE(t.short_code, '') AS team_code,
                    COALESCE(t.primary_color, '#36d8ff') AS team_primary_color,
                    COALESCE(t.secondary_color, '#ffd25f') AS team_secondary_color
                FROM sx_ygo_competition_decks cd
                JOIN sx_ygo_decks d ON d.id = cd.deck_id
                LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
                WHERE cd.competition_id = ? AND cd.active = 1 AND d.active = 1
                ORDER BY cd.sort_order, d.seed_rank, d.name
            ");
            $deckStmt->execute([$competitionId]);
            $deckRows = $deckStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $deckRows = $this->pdo->query("
            SELECT
                d.id,
                d.name,
                d.seed_rank,
                COALESCE(t.name, 'Sin equipo') AS team,
                COALESCE(t.short_code, '') AS team_code,
                COALESCE(t.primary_color, '#36d8ff') AS team_primary_color,
                COALESCE(t.secondary_color, '#ffd25f') AS team_secondary_color
            FROM sx_ygo_decks d
            LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
            WHERE d.active = 1
            ORDER BY d.seed_rank, d.name
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
        return $deckRows;
    }

    public function matches(int $competitionId, ?string $phaseFilter): array
    {
        $phaseSql = $phaseFilter !== null && $phaseFilter !== '' ? ' AND m.phase = ?' : '';
        $matchStmt = $this->pdo->prepare("
            SELECT m.deck_a_id, m.deck_b_id, r.result, r.score
            FROM sx_ygo_matches m
            JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.competition_id = ? AND r.result IS NOT NULL AND r.result <> ''$phaseSql
        ");
        $matchStmt->execute($phaseFilter !== null && $phaseFilter !== '' ? [$competitionId, $phaseFilter] : [$competitionId]);
        return $matchStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function teamConfiguration(array $competition): ?array
    {
        return \ygo_team_load($this->pdo, $competition);
    }
}
