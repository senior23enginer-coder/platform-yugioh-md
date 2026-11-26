<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckCardWriter
{
    public function append(array $row): void;

    public function clear(int $deckId): void;

    public function appendBatch(array $rows): void;
}
