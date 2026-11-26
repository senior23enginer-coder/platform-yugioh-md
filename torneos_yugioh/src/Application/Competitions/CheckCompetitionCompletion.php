<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy;

final readonly class CheckCompetitionCompletion
{
    public function __construct(private CompetitionLifecycleReader $reader) {}

    public function execute(int $competitionId): array
    {
        $workflow = $this->reader->workflow($competitionId);
        if ($workflow !== null) {
            $unfinished = CompetitionLifecyclePolicy::unfinishedWorkflow($workflow);
            if ($unfinished !== null) {
                return $unfinished;
            }
        }
        return CompetitionLifecyclePolicy::finishCounts($this->reader->matchCounts($competitionId));
    }
}
