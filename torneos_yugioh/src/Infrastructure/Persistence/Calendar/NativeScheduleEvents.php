<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\ScheduleEvents;

/** Bridges the existing calendar projection writer without changing its audit or transaction scope. */
final readonly class NativeScheduleEvents implements ScheduleEvents
{
    public function __construct(private PDO $pdo) {}

    public function rebuild(int $competitionId, string $eventType): void
    {
        \ygo_rebuild_competition_calendar_events_from_matches(
            $this->pdo,
            \ygo_current_season_id($this->pdo),
            $competitionId,
            $eventType,
        );
    }
}
