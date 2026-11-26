<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\CompetitionHonorPolicy;

final readonly class RecordCompetitionHonor
{
    public function __construct(
        private CompetitionHonorReader $reader,
        private CompetitionHonorWriter $writer,
    ) {}

    public function execute(int $competitionId): int
    {
        if ($competitionId <= 0 || $this->reader->exists($competitionId)) {
            return 0;
        }
        $competition = $this->reader->competition($competitionId);
        if (!$competition || ($competition['execution_model'] ?? '') === 'manual') {
            return 0;
        }
        $workflow = $this->reader->workflow($competition);
        if ($workflow !== null) {
            if (!empty($workflow['complete']) && $workflow['champion'] !== null) {
                $this->writer->workflowHonor(
                    $competition,
                    (int) $workflow['champion'],
                    (int) $workflow['runner_up'],
                );
                return 1;
            }
            return 0;
        }
        $final = $this->reader->finalResult($competitionId);
        [$championId, $runnerId] =
            CompetitionHonorPolicy::usesFinal($competition) && $final
                ? CompetitionHonorPolicy::fromFinal($final)
                : CompetitionHonorPolicy::fromRanking($this->reader->standings($competitionId));
        if ($championId <= 0) {
            return 0;
        }
        $championTeamId = $this->reader->teamId($championId);
        $runnerTeamId = $runnerId > 0 ? $this->reader->teamId($runnerId) : 0;
        $this->writer->record(
            $competition,
            $competitionId,
            $championId,
            $championTeamId,
            $runnerId,
            $runnerTeamId,
        );
        return 1;
    }
}
