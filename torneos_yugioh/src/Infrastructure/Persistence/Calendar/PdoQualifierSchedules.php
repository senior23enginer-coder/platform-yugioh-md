<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\QualifierScheduleReader;
use StationX\Yugioh\Application\Calendar\QualifierScheduleWriter;

/** Keeps native season/status predicates and the original MySQL displacement statement. */
final readonly class PdoQualifierSchedules implements
    QualifierScheduleReader,
    QualifierScheduleWriter
{
    public function __construct(private PDO $pdo) {}

    public function activeQualifiers(): array
    {
        $query = $this->pdo->prepare(
            "SELECT id,starts_on,schedule_pattern,qualifier_matches_per_day FROM sx_ygo_competitions WHERE season_id=? AND active=1 AND type='qualifier' AND status NOT IN ('cancelled','completed','archived') ORDER BY id",
        );
        $query->execute([\ygo_current_season_id($this->pdo)]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function competitionConfiguration(int $competitionId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT id,type,starts_on,schedule_pattern,qualifier_matches_per_day FROM sx_ygo_competitions WHERE id=? LIMIT 1',
        );
        $query->execute([$competitionId]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function clearPendingDatesExcept(int $excludeCompetitionId): int
    {
        $query = $this->pdo->prepare(
            "UPDATE sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id SET m.scheduled_on=NULL, m.starts_at=NULL WHERE m.status <> 'FINALIZADA' AND c.active=1 AND c.type='qualifier' AND c.id<>?",
        );
        $query->execute([$excludeCompetitionId]);
        return $query->rowCount();
    }
}
