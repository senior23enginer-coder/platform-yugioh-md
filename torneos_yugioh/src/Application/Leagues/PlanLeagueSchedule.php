<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

use StationX\Yugioh\Domain\Competitions\LeagueRosterPolicy;
use StationX\Yugioh\Domain\Competitions\LeagueSchedulePolicy;

final readonly class PlanLeagueSchedule
{
    public function __construct(private LeagueScheduleReader $reader) {}

    public function execute(
        int $competitionId,
        array $selectedDeckIds,
        int $participantsPerSide,
        bool $doubleRound,
    ): array {
        $divisions = $this->reader->divisions($competitionId);
        $owned = array_column($divisions, null, 'id');
        $ids = LeagueRosterPolicy::normalize($selectedDeckIds);
        if (!$ids) {
            return ['groups' => [], 'rounds' => []];
        }
        $deckDivisions = $this->reader->deckDivisions($ids);
        LeagueSchedulePolicy::validateMembership($ids, $owned, $deckDivisions);
        $groupsByDivision = [];
        foreach ($divisions as $division) {
            $localIds = array_values(
                array_filter(
                    $ids,
                    static fn($id) => (int) $deckDivisions[$id] === (int) $division['id'],
                ),
            );
            if (!$localIds) {
                continue;
            }
            $localGroups = $this->reader->rosterGroups($localIds, $participantsPerSide);
            LeagueSchedulePolicy::requireCompleteGroups($localGroups);
            $groupsByDivision[(int) $division['id']] = $localGroups;
        }
        return LeagueSchedulePolicy::plan($groupsByDivision, $doubleRound);
    }
}
