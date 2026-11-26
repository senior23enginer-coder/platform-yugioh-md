<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\KnockoutRoundServices;

final readonly class NativeKnockoutRoundServices implements KnockoutRoundServices
{
    public function __construct(private PDO $pdo) {}

    public function fixedProgram(): array
    {
        return \ygo_fixed_program($this->pdo);
    }

    public function saveBye(string $key, string $value): void
    {
        \ygo_save_setting($this->pdo, $key, $value);
    }

    public function scheduleAdmission(
        array $competition,
        string $start,
        string $pattern,
        int $limit,
        string $phase,
        int $round,
    ): void {
        \ygo_admission_schedule_stage(
            $this->pdo,
            $competition,
            $start,
            $pattern,
            $limit,
            $phase,
            $round,
        );
    }

    public function scheduleFixedRound(int $competitionId, string $phase, int $round): void
    {
        \ygo_schedule_fixed_cup_round($this->pdo, $competitionId, $phase, $round);
    }

    public function rebuildCalendar(int $seasonId, int $competitionId, string $phase): void
    {
        \ygo_rebuild_competition_calendar_events_from_matches(
            $this->pdo,
            $seasonId,
            $competitionId,
            $phase,
        );
    }
}
