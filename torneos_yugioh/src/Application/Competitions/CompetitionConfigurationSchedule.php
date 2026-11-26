<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionConfigurationSchedule
{
    public function scheduleQualifier(array $competition, array $ids, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $duelsPerDeck, int $matchesPerDay): int;

    public function rebuildCalendar(int $seasonId, int $competitionId, string $type): int;

    public function scheduleByModel(array $competition, array $ids, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay): int;

    public function fixedProgram(): bool;

    public function reflow(int $competitionId, string $startDate, string $pattern, int $matchesPerDay): void;

    public function validateSeasonSchedule(int $competitionId): void;

    public function validateMatchSchedule(int $matchId, ?string $date, ?string $time, ?string $venue): array;

    public function rebalance(int $competitionId): void;

    public function persistCalendarEvent(int $seasonId, int $competitionId): void;
}
