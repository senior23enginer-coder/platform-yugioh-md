<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface DeckTeamStore
{
    public function activeDecks(): array;

    public function assign(int $deckId, int $teamId): void;

    public function archiveUnusedLegacyTeams(): void;
}
