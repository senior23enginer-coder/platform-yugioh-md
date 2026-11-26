<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckMediaWriter
{
    public function add(int $deckId, string $type, string $url, string $title): void;
}
