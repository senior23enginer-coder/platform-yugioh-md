<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\MatchScheduleServices;

final readonly class NativeMatchScheduleServices implements MatchScheduleServices
{
    public function __construct(private PDO $pdo) {}

    public function validate(int $matchId, ?string $scheduledOn, ?string $startsAt, string $venue): array
    {
        return \ygo_validate_match_schedule($this->pdo, $matchId, $scheduledOn, $startsAt, $venue);
    }

    public function rebuildCalendar(int $competitionId, string $phase): void
    {
        \ygo_rebuild_competition_calendar_events_from_matches($this->pdo, \ygo_current_season_id($this->pdo), $competitionId, $phase);
    }

    public function reflowQualifiers(): void
    {
        \ygo_reflow_active_qualifiers($this->pdo);
    }
}
