<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionDependencyFacts;
use StationX\Yugioh\Application\Competitions\CompetitionGateReader;

final readonly class PdoCompetitionDependencyReader implements CompetitionDependencyFacts, CompetitionGateReader
{
    public function __construct(private PDO $pdo) {}

    public function dependencies(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT dep.*, target.name AS competition_name, target.type AS competition_type,
                   required.name AS depends_on_name, required.type AS depends_on_type,
                   required.status AS depends_on_status
            FROM sx_ygo_competition_dependencies dep
            JOIN sx_ygo_competitions target ON target.id = dep.competition_id
            JOIN sx_ygo_competitions required ON required.id = dep.depends_on_competition_id
            WHERE dep.season_id = ? AND dep.active = 1
            ORDER BY target.name, required.name
        ");
        $stmt->execute([\ygo_current_season_id($this->pdo)]);
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[(int) $row['competition_id']][] = $row;
        }
        return $grouped;
    }

    public function resultCount(int $competitionId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM sx_ygo_matches m
            JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.competition_id = ? AND r.result IS NOT NULL AND r.result <> ''
        ");
        $stmt->execute([$competitionId]);
        return (int) $stmt->fetchColumn();
    }

    public function honorCount(int $competitionId): int
    {
        if (!\ygo_table_exists($this->pdo, 'sx_ygo_honors')) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id = ?');
        $stmt->execute([$competitionId]);
        return (int) $stmt->fetchColumn();
    }

    public function divisionCount(int $competitionId): int
    {
        if (!\ygo_column_exists($this->pdo, 'sx_ygo_divisions', 'source_qualifier_id')) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_divisions WHERE source_qualifier_id = ? AND active = 1');
        $stmt->execute([$competitionId]);
        return (int) $stmt->fetchColumn();
    }

    public function season(int $competitionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.* FROM sx_ygo_seasons s JOIN sx_ygo_competitions c ON c.season_id=s.id WHERE c.id=?',
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function competition(int $competitionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function admission(array $competition): ?array
    {
        if (\ygo_admission_required($competition) || \ygo_admission_load($this->pdo, $competition) !== null) {
            return \ygo_admission_view($this->pdo, $competition);
        }
        return null;
    }

    public function standings(int $competitionId): array
    {
        return \ygo_competition_standings($this->pdo, $competitionId);
    }
}
