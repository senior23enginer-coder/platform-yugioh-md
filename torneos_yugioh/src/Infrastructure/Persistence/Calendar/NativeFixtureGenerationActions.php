<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\FixtureGenerationActions;

final readonly class NativeFixtureGenerationActions implements FixtureGenerationActions
{
    public function __construct(private PDO $pdo) {}

    public function workflow(
        array $competition,
        array $ids,
        string $startDate,
        string $pattern,
        int $matchesPerDay,
    ): int {
        return \ygo_workflow_generate(
            $this->pdo,
            $competition,
            $ids,
            $startDate,
            $pattern,
            $matchesPerDay,
        );
    }

    public function cup(
        array $competition,
        array $ids,
        string $startDate,
        string $pattern,
        string $matchFormat,
        int $participantsPerSide,
        int $matchesPerDay,
    ): int {
        return \ygo_schedule_cup_duels(
            $this->pdo,
            $competition,
            $ids,
            $startDate,
            $pattern,
            $matchFormat,
            $participantsPerSide,
            $matchesPerDay,
        );
    }

    public function qualifier(
        array $competition,
        array $ids,
        string $startDate,
        string $pattern,
        string $matchFormat,
        int $participantsPerSide,
        int $duels,
        int $matchesPerDay,
        string $phase,
    ): int {
        return \ygo_schedule_qualifier_duels(
            $this->pdo,
            $competition,
            $ids,
            $startDate,
            $pattern,
            $matchFormat,
            $participantsPerSide,
            $duels,
            $matchesPerDay,
            $phase,
        );
    }

    public function saveBye(string $key, string $value): void
    {
        \ygo_save_setting($this->pdo, $key, $value);
    }

    public function admissionStage(
        array $competition,
        string $startDate,
        string $pattern,
        int $matchesPerDay,
    ): void {
        \ygo_admission_schedule_stage(
            $this->pdo,
            $competition,
            $startDate,
            $pattern,
            $matchesPerDay,
        );
    }

    public function rebuildCalendar(int $seasonId, int $competitionId, string $phase): int
    {
        return \ygo_rebuild_competition_calendar_events_from_matches(
            $this->pdo,
            $seasonId,
            $competitionId,
            $phase,
        );
    }
}
