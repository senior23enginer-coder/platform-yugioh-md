<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

use StationX\Yugioh\Domain\Statistics\TeamIndividualProjection;

final readonly class ExpandTeamIndividualResults
{
    public function __construct(private TeamIndividualReader $reader) {}

    public function execute(array $rows): array
    {
        $teamIds = $this->reader->teamMatchIds();
        if (!$teamIds) {
            return $rows;
        }
        return TeamIndividualProjection::expand($rows, $teamIds, $this->reader->records(), $this->reader->decks());
    }
}
