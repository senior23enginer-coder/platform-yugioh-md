<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface LeagueStandingsReader
{
    public function activeDecks(): array;

    public function finalizedMatches(string $phase): array;
}
