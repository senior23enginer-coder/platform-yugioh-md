<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionLifecycleReader
{
    public function workflow(int $competitionId): ?array;

    public function matchCounts(int $competitionId): array;
}
