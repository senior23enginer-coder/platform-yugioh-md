<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

interface SeasonScheduleReader
{
    public function find(int $id, bool $lock = false): ?array;

    public function forCompetition(int $competitionId): ?array;

    public function matchDateRange(int $competitionId): array;
}
