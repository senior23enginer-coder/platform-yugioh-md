<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface LeagueRosterReader
{
    public function competition(int $competitionId): ?array;

    public function assignments(): array;

    /** Locks the given decks in ID order within the caller's transaction. */
    public function lockDecks(array $sortedIds): void;
}
