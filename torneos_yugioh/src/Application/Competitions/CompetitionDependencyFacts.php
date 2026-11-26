<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionDependencyFacts
{
    public function resultCount(int $competitionId): int;

    public function honorCount(int $competitionId): int;

    public function divisionCount(int $competitionId): int;

    public function standings(int $competitionId): array;
}
