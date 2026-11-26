<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\LegacyPhasePlan;

final readonly class AdvanceLegacyPhase
{
    public function __construct(private LegacyPhaseStore $store) {}

    public function execute(string $phase): int
    {
        $defaultDivision = $this->store->firstDivisionId();
        $winnersByDivision = LegacyPhasePlan::winnersByDivision($this->store->finishedResults($phase));
        $created = 0;
        foreach ($winnersByDivision as $divisionId => $winners) {
            $round = $this->store->nextRound($divisionId, $phase);
            $created += $this->store->insert($divisionId ?: $defaultDivision, $phase, $round, LegacyPhasePlan::rows($winners, $round));
        }
        return $created;
    }
}
