<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Seasons;

use PDO;
use StationX\Yugioh\Application\Seasons\SeasonScheduleReader;

final readonly class PdoSeasonScheduleReader implements SeasonScheduleReader
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_seasons WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function forCompetition(int $competitionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.* FROM sx_ygo_seasons s JOIN sx_ygo_competitions c ON c.season_id=s.id WHERE c.id=?',
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function matchDateRange(int $competitionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(scheduled_on) first_date,MAX(scheduled_on) last_date FROM sx_ygo_matches WHERE competition_id=?',
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
