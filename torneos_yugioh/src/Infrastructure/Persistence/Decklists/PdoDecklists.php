<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decklists;

use PDO;
use InvalidArgumentException;
use StationX\Yugioh\Domain\Decks\DecklistRules;
use StationX\Yugioh\Application\Decklists\{DecklistReader, DecklistWriter};

/** Original row order, FOR UPDATE locks and SQL audit; constructors do not initialize schema. */
final readonly class PdoDecklists implements DecklistReader, DecklistWriter
{
    public function __construct(private PDO $pdo) {}
    public function snapshot(int $id, bool $lock = false): array
    {
        $q = $this->pdo->prepare(
            'SELECT id,name FROM sx_ygo_decks WHERE id=? AND active=1' .
                ($lock ? ' FOR UPDATE' : ''),
        );
        $q->execute([$id]);
        $deck = $q->fetch(PDO::FETCH_ASSOC);
        if (!$deck) {
            throw new InvalidArgumentException('El deck no existe.');
        }
        $q = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY id' .
                ($lock ? ' FOR UPDATE' : ''),
        );
        $q->execute([$id]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        return [
            'deck' => $deck,
            'cards' => $rows,
            'version' => DecklistRules::version($rows),
        ];
    }

    public function replace(int $deckId, array $cards): void
    {
        $this->pdo->prepare('DELETE FROM sx_ygo_deck_cards WHERE deck_id=?')->execute([$deckId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO sx_ygo_deck_cards(deck_id,card_name,role,quantity,section,notes,ygoprodeck_id) VALUES(?,?,?,?,?,?,?)',
        );
        foreach ($cards as $row) {
            $insert->execute([
                $deckId,
                $row['card_name'],
                $row['role'],
                $row['quantity'],
                $row['section'],
                $row['notes'],
                $row['ygoprodeck_id'],
            ]);
        }
    }

    public function audit(int $deckId, array $before, array $after): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )
            ->execute([
                'deck',
                $deckId,
                'save_decklist',
                json_encode(
                    ['before' => $before, 'after' => $after],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            ]);
    }
}
