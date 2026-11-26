<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\CompetitionCalendarReader;

final readonly class PdoCompetitionCalendarReader implements CompetitionCalendarReader
{
    public function __construct(private PDO $pdo) {}

    public function competition(int $seasonId, int $competitionId): ?array
    {
        $competitionStmt = $this->pdo->prepare(
            'SELECT id, division_id, name, type FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1',
        );
        $competitionStmt->execute([$competitionId, $seasonId]);
        return $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function scheduledDays(int $competitionId): array
    {
        $rows = $this->pdo->prepare("
            SELECT scheduled_on, MIN(starts_at) AS starts_at, COUNT(*) AS match_count, MIN(venue) AS venue
            FROM sx_ygo_matches
            WHERE competition_id = ? AND scheduled_on IS NOT NULL
            GROUP BY scheduled_on
            ORDER BY scheduled_on
        ");
        $rows->execute([$competitionId]);
        return $rows->fetchAll(PDO::FETCH_ASSOC);
    }
}
