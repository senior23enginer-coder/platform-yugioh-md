<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckTemplateWriter;

final readonly class PdoDeckTemplateWriter implements DeckTemplateWriter
{
    public function __construct(private PDO $pdo) {}

    public function activeNameExists(string $name): bool
    {
        $exists = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_decks WHERE name=? AND active=1 LIMIT 1',
        );
        $exists->execute([$name]);
        return (bool) $exists->fetchColumn();
    }

    public function create(array $data): int
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active) VALUES (NULL, NULL, 999, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            )
            ->execute([
                $data['name'],
                $data['archetype'],
                $data['style'],
                '',
                '',
                '',
                $data['notes'],
                '',
            ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function appendCards(int $id, array $cards): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO sx_ygo_deck_cards (deck_id, card_name, role, quantity, section, notes) VALUES (?, ?, ?, ?, ?, ?)',
        );
        foreach ($cards as $card) {
            $insert->execute([$id, $card['name'], '', $card['quantity'], $card['section'], '']);
        }
    }
}
