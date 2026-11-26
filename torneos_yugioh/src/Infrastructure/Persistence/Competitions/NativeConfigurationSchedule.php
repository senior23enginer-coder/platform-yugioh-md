<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionConfigurationSchedule;

final readonly class NativeConfigurationSchedule implements CompetitionConfigurationSchedule
{
    public function __construct(private PDO $pdo) {}

    public function scheduleQualifier(array $competition, array $ids, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $duelsPerDeck, int $matchesPerDay): int
    {
        return \ygo_schedule_qualifier_duels($this->pdo, $competition, $ids, $startDate, $pattern, $matchFormat, $participantsPerSide, $duelsPerDeck, $matchesPerDay);
    }

    public function rebuildCalendar(int $seasonId, int $competitionId, string $type): int
    {
        return \ygo_rebuild_competition_calendar_events_from_matches($this->pdo, $seasonId, $competitionId, $type);
    }

    public function scheduleByModel(array $competition, array $ids, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay): int
    {
        return \ygo_schedule_competition_by_model($this->pdo, $competition, $ids, $startDate, $pattern, $matchFormat, $participantsPerSide, $matchesPerDay);
    }

    public function fixedProgram(): bool
    {
        return \ygo_fixed_program($this->pdo);
    }

    public function reflow(int $competitionId, string $startDate, string $pattern, int $matchesPerDay): void
    {
        \ygo_reflow_pending_competition($this->pdo, $competitionId, $startDate, $pattern, $matchesPerDay);
    }

    public function validateSeasonSchedule(int $competitionId): void
    {
        \ygo_season_validate_competition_schedule($this->pdo, $competitionId);
    }

    public function validateMatchSchedule(int $matchId, ?string $date, ?string $time, ?string $venue): array
    {
        return \ygo_validate_match_schedule($this->pdo, $matchId, $date, $time, $venue);
    }

    public function rebalance(int $competitionId): void
    {
        \ygo_rebalance_after_competition_schedule($this->pdo, $competitionId);
    }

    public function persistCalendarEvent(int $seasonId, int $competitionId): void
    {
        \ygo_persist_competition_calendar_event($this->pdo, $seasonId, $competitionId);
    }
}
