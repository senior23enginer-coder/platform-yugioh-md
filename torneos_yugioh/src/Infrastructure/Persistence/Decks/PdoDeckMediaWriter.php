<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckMediaWriter;

final readonly class PdoDeckMediaWriter implements DeckMediaWriter
{
    public function __construct(private PDO $pdo) {}

    public function add(int $deckId, string $type, string $url, string $title): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_deck_media (deck_id, media_type, url, title) VALUES (?, ?, ?, ?)',
            )
            ->execute([$deckId, $type, $url, $title]);
    }
}
