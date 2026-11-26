<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\StandingSnapshotReader;

final readonly class PdoStandingSnapshotReader implements StandingSnapshotReader
{
    public function __construct(private PDO $pdo) {}

    public function phases(int $competitionId): array
    {
        $phaseStmt = $this->pdo->prepare(
            'SELECT DISTINCT phase FROM sx_ygo_matches WHERE competition_id = ? ORDER BY phase',
        );
        $phaseStmt->execute([$competitionId]);
        return $phaseStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function standings(int $competitionId, ?string $phase): array
    {
        return \ygo_competition_standings($this->pdo, $competitionId, $phase);
    }

    public function maxRound(int $competitionId, ?string $phase): int
    {
        $maxRoundStmt =
            $phase === null
                ? $this->pdo->prepare(
                    'SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ?',
                )
                : $this->pdo->prepare(
                    'SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?',
                );
        $maxRoundStmt->execute($phase === null ? [$competitionId] : [$competitionId, $phase]);
        return (int) $maxRoundStmt->fetchColumn();
    }

    public function missingCompetitionIds(int $seasonId): array
    {
        if (!\ygo_table_exists($this->pdo, 'sx_ygo_standing_snapshots')) {
            return [];
        }
        $stmt = $this->pdo->prepare("
            SELECT c.id
            FROM sx_ygo_competitions c
            WHERE c.season_id = ? AND c.active = 1
              AND EXISTS (
                  SELECT 1
                  FROM sx_ygo_matches m
                  JOIN sx_ygo_match_results r ON r.match_id = m.id
                  WHERE m.competition_id = c.id AND r.result IS NOT NULL AND r.result <> ''
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM sx_ygo_standing_snapshots s
                  WHERE s.competition_id = c.id AND s.snapshot_key = 'current'
              )
            ORDER BY c.id
            LIMIT 12
        ");
        $stmt->execute([$seasonId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
