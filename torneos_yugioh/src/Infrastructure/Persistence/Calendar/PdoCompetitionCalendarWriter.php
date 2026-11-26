<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\CompetitionCalendarWriter;

final readonly class PdoCompetitionCalendarWriter implements CompetitionCalendarWriter
{
    public function __construct(private PDO $pdo) {}

    public function deactivateType(int $seasonId, int $competitionId, string $eventType): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_events SET active = 0 WHERE season_id = ? AND competition_id = ? AND event_type = ?')
            ->execute([$seasonId, $competitionId, $eventType]);
    }

    public function upsertJourneys(int $seasonId, int $competitionId, iterable $rows): int
    {
        $insert = $this->pdo->prepare('
            INSERT INTO sx_ygo_events (season_id, competition_id, division_id, code, title, event_type, status, event_date, starts_at, venue, notes, active)
            VALUES (?, ?, ?, ?, ?, ?, "scheduled", ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE title=VALUES(title), status=VALUES(status), event_date=VALUES(event_date), starts_at=VALUES(starts_at), venue=VALUES(venue), notes=VALUES(notes), active=1
        ');
        $created = 0;
        foreach ($rows as $row) {
            $insert->execute([$seasonId, $competitionId, ...$row]);
            $created++;
        }
        return $created;
    }

    public function deactivateCode(int $seasonId, int $competitionId, string $code): void
    {
        $this->pdo->prepare("UPDATE sx_ygo_events SET active = 0 WHERE season_id = ? AND competition_id = ? AND code = ?")
            ->execute([$seasonId, $competitionId, $code]);
    }
}
