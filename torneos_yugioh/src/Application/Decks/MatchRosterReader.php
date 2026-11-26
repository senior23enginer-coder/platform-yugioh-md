<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface MatchRosterReader
{
    public function activeDeckRows(array $ids): array;

    public function activeTeamRows(array $ids): array;
}
