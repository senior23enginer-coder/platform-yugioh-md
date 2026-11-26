<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

interface SeasonContextReader
{
    public function competitionForDependency(int $id): int;

    public function competitionForMatch(int $id): int;

    public function seasonForCompetition(int $id): int;

    public function seasonForDivision(int $id): int;

    public function exists(int $id): bool;

    public function defaultSeasonId(): int;
}
