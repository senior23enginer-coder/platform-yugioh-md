<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use StationX\Yugioh\Domain\Decks\RosterGroupingPolicy;

final readonly class GroupMatchRoster
{
    public function __construct(private MatchRosterReader $reader) {}

    public function execute(array $selectedDeckIds, int $participantsPerSide): array
    {
        $participantsPerSide = max(1, min(3, $participantsPerSide));
        $ids = RosterGroupingPolicy::normalize($selectedDeckIds);
        if (!$ids) {
            return [];
        }
        if ($participantsPerSide === 1) {
            return array_map(static fn(int $id): array => [$id], $ids);
        }
        return RosterGroupingPolicy::group($ids, $participantsPerSide, $this->reader->activeTeamRows($ids));
    }
}
