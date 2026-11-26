<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface LegacyEventFixtureReader
{
    public function currentSeasonId(): int;

    public function firstDivisionId(): int;

    public function divisions(int $seasonId): array;

    public function leagueDecks(int $divisionId): array;

    public function decks(?int $divisionId = null): array;

    public function leagueMatchCount(): int;
}
