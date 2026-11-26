<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionHonorWriter
{
    public function workflowHonor(array $competition, int $championId, int $runnerId): void;

    public function record(
        array $competition,
        int $competitionId,
        int $championId,
        int $championTeamId,
        int $runnerId,
        int $runnerTeamId,
    ): void;
}
