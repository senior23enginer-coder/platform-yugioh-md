<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\PhaseAdvancePolicy;

final readonly class CompetitionPhaseQueries
{
    public function __construct(private PhaseAdvanceReader $reader) {}

    public function count(int $competitionId, string $phase): int
    {
        return $this->reader->matchCount($competitionId, $phase);
    }

    public function finished(int $competitionId, string $phase): bool
    {
        return PhaseAdvancePolicy::finished($this->reader->phaseCounts($competitionId, $phase));
    }

    public function winners(int $competitionId, string $phase, int $round): array
    {
        $winners = PhaseAdvancePolicy::winners(
            $this->reader->roundResults($competitionId, $phase, $round),
        );
        if ($phase === 'cup') {
            $winners = array_merge($winners, $this->reader->cupByes($competitionId, $round));
        }
        return array_values(array_unique($winners));
    }
}
