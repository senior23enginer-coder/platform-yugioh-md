<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

use StationX\Yugioh\Domain\Competitions\LeagueStandings;

final readonly class ComputeLeagueStandings
{
    public function __construct(private LeagueStandingsReader $reader) {}

    public function execute(string $phase): array
    {
        $rows = $this->reader->activeDecks();
        $matches = $this->reader->finalizedMatches($phase);
        return LeagueStandings::calculate($rows, $matches);
    }
}
