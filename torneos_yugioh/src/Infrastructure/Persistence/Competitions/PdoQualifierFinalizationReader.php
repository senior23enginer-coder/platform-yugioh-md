<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\QualifierFinalizationReader;

final readonly class PdoQualifierFinalizationReader implements QualifierFinalizationReader
{
    public function __construct(private PDO $pdo) {}

    public function postedQualifier(int $id): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE id=? AND type='qualifier' AND active=1 LIMIT 1");
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public function defaultQualifier(int $seasonId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE season_id=? AND type='qualifier' AND active=1 ORDER BY FIELD(status, 'active', 'draft', 'completed'), id LIMIT 1");
        $stmt->execute([$seasonId]);
        return (int) $stmt->fetchColumn();
    }

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function requiredMatches(int $competitionId): mixed
    {
        $stmt = $this->pdo->prepare("SELECT qualifier_duels_per_deck FROM sx_ygo_competitions WHERE id=? AND type='qualifier' AND active=1 LIMIT 1");
        $stmt->execute([$competitionId]);
        return $stmt->fetchColumn();
    }

    public function matches(int $competitionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT round_number, status
            FROM sx_ygo_matches
            WHERE phase = 'qualifier' AND competition_id = ?
            ORDER BY round_number, table_number, id
        ");
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function divisions(int $seasonId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function standings(int $competitionId): array
    {
        return \ygo_competition_standings($this->pdo, $competitionId, 'qualifier');
    }
}
