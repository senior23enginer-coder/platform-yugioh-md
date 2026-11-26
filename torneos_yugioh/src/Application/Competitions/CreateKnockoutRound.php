<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Competitions\KnockoutRoundPlan;

final readonly class CreateKnockoutRound
{
    public function __construct(
        private KnockoutRoundStore $store,
        private KnockoutRoundServices $services,
        private Clock $clock,
    ) {}

    public function execute(
        array $competition,
        string $phase,
        array $deckIds,
        int $round,
        string $label,
    ): int {
        $competitionId = (int) ($competition['id'] ?? 0);
        $ids = KnockoutRoundPlan::normalize($deckIds);
        if (
            $competitionId <= 0 ||
            count($ids) < 2 ||
            $this->store->exists($competitionId, $phase, $round)
        ) {
            return 0;
        }
        $seasonId = $this->store->currentSeasonId();
        $base = (string) ($competition['starts_on'] ?? '' ?: $this->clock->now()->format('Y-m-d'));
        $created = $this->store->insert(
            KnockoutRoundPlan::rows($competition, $phase, $ids, $round, $label, $base),
        );
        $this->services->fixedProgram();
        $bye = KnockoutRoundPlan::bye($phase, $ids);
        if ($bye !== null) {
            $this->services->saveBye(
                'cup_bye_' . $competitionId . '_' . $round,
                json_encode([$bye]),
            );
            $this->store->auditBye($competitionId, $round, $bye);
        }
        $this->services->scheduleAdmission(
            $competition,
            (string) ($competition['starts_on'] ?? $this->clock->now()->format('Y-m-d')),
            (string) ($competition['schedule_pattern'] ?? 'consecutive'),
            max(1, (int) ($competition['qualifier_matches_per_day'] ?? 1)),
            $phase,
            $round,
        );
        $this->services->scheduleFixedRound($competitionId, $phase, $round);
        $this->services->rebuildCalendar($seasonId, $competitionId, $phase);
        return $created;
    }
}
