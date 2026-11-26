<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckDeletionReader
{
    public function competition(int $deckId, array $statuses): ?array;

    public function match(int $deckId, array $statuses): ?array;
}
