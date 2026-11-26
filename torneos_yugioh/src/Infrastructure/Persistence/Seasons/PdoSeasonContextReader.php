<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Seasons;

use PDO;
use StationX\Yugioh\Application\Seasons\SeasonContextReader;

final readonly class PdoSeasonContextReader implements SeasonContextReader
{
    public function __construct(private PDO $pdo) {}

    private function scalar(string $sql, int $id): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public function competitionForDependency(int $id): int
    {
        return $this->scalar(
            'SELECT competition_id FROM sx_ygo_competition_dependencies WHERE id=?',
            $id,
        );
    }

    public function competitionForMatch(int $id): int
    {
        return $this->scalar('SELECT competition_id FROM sx_ygo_matches WHERE id=?', $id);
    }

    public function seasonForCompetition(int $id): int
    {
        return $this->scalar('SELECT season_id FROM sx_ygo_competitions WHERE id=?', $id);
    }

    public function seasonForDivision(int $id): int
    {
        return $this->scalar('SELECT season_id FROM sx_ygo_divisions WHERE id=?', $id);
    }

    public function exists(int $id): bool
    {
        return (new PdoSeasonScheduleReader($this->pdo))->find($id) !== null;
    }

    public function defaultSeasonId(): int
    {
        return (int) $this->pdo
            ->query("SELECT id FROM sx_ygo_seasons WHERE slug = 'temporada-2026'")
            ->fetchColumn();
    }

    public function eventRows(): array
    {
        return $this->pdo
            ->query(
                "SELECT c.id,c.season_id,c.code,c.name,c.type,c.event_ruleset,c.status,c.starts_on,c.ends_on,
        CASE WHEN c.status IN ('completed','closed','cancelled','archived') THEN
            (SELECT MAX(h.created_at) FROM sx_ygo_competition_status_history h WHERE h.competition_id=c.id AND h.to_status=c.status)
        ELSE NULL END AS closed_at
        FROM sx_ygo_competitions c WHERE c.active=1 ORDER BY c.season_id,c.name,c.id",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
