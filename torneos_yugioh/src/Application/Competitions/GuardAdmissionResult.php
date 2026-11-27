<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\AdmissionConfigurationPolicy;

final readonly class GuardAdmissionResult
{
    public function __construct(private AdmissionResultGuardStore $store) {}

    public function execute(int $matchId): void
    {
        $source = $this->store->competitionForMatch($matchId);
        if (!$source) {
            return;
        }
        $this->store->lockCompetition($source);
        AdmissionConfigurationPolicy::assertResultEditable($source, $this->store->lockedConfigurations());
    }
}
