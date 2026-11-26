<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface LeagueScheduleReader
{
    public function divisions(int $competitionId): array;

    public function deckDivisions(array $ids): array;

    public function rosterGroups(array $ids, int $participantsPerSide): array;
}
