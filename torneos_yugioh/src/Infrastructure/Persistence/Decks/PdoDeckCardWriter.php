<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckCardWriter;

final readonly class PdoDeckCardWriter implements DeckCardWriter
{
    public function __construct(private PDO $pdo) {}

    public function append(array $row): void
    {
        $this->appendBatch([$row]);
    }

    public function clear(int $deckId): void
    {
        $this->pdo->prepare('DELETE FROM sx_ygo_deck_cards WHERE deck_id=?')->execute([$deckId]);
    }

    public function appendBatch(array $rows): void
    {
        $insert = $this->pdo->prepare('INSERT INTO sx_ygo_deck_cards (deck_id,card_name,role,quantity,section,notes) VALUES (?,?,?,?,?,?)');
        foreach ($rows as $row) {
            $insert->execute([$row['deckId'], $row['name'], $row['role'], $row['quantity'], $row['section'], $row['notes']]);
        }
    }
}
