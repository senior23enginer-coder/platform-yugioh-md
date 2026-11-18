<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use PDOStatement;
use StationX\Yugioh\Application\Calendar\ScheduleWriter;

/** SQL writes only; caller-owned locks and transaction boundaries remain untouched. */
final class PdoScheduleWriter implements ScheduleWriter
{
    private ?PDOStatement $assignment = null;

    public function __construct(private readonly PDO $pdo) {}

    public function clearPendingDates(int $competitionId): void
    {
        $this->pdo
            ->prepare(
                "UPDATE sx_ygo_matches SET scheduled_on=NULL, starts_at=NULL WHERE competition_id=? AND status <> 'FINALIZADA'",
            )
            ->execute([$competitionId]);
    }

    public function assign(int $competitionId, int $matchId, string|int $date, string $time): void
    {
        $this->assignment ??= $this->pdo->prepare(
            'UPDATE sx_ygo_matches SET scheduled_on=?, starts_at=? WHERE id=? AND competition_id=? AND status <> "FINALIZADA"',
        );
        $this->assignment->execute([$date, $time, $matchId, $competitionId]);
    }

    public function setEndDate(int $competitionId, string|int $date): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_competitions SET ends_on=? WHERE id=?')
            ->execute([$date, $competitionId]);
    }
}
