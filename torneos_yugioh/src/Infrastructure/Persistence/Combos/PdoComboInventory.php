<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Combos;

use PDO;
use InvalidArgumentException;
use StationX\Yugioh\Application\Combos\ComboInventory;
use StationX\Yugioh\Application\Combos\ComboSources;

/** Locks the deck and its saved cards before a write; editorial context is provided separately. */
final readonly class PdoComboInventory implements ComboInventory
{
    public function __construct(private PDO $pdo, private ComboSources $sources) {}
    public function load(int $deckId, bool $lock = false, bool $requireMain = true): array
    {
        $suffix =
            $lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
        $q = $this->pdo->prepare('SELECT id,name FROM sx_ygo_decks WHERE id=?' . $suffix);
        $q->execute([$deckId]);
        $deck = $q->fetch(PDO::FETCH_ASSOC);
        if (!$deck) {
            throw new InvalidArgumentException('El deck no existe.');
        }
        $q = $this->pdo->prepare(
            'SELECT id,deck_id,card_name,quantity,section,ygoprodeck_id FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY id' .
                $suffix,
        );
        $q->execute([$deckId]);
        $cards = $q->fetchAll(PDO::FETCH_ASSOC);
        $loaded = (bool) array_filter(
            $cards,
            static fn($c) => $c['section'] === 'main' && (int) $c['quantity'] > 0,
        );
        if ($requireMain && !$loaded) {
            throw new InvalidArgumentException('Carga primero el Main Deck.');
        }
        foreach ($cards as &$c) {
            $c['id'] = (int) $c['id'];
            $c['deck_id'] = (int) $c['deck_id'];
            $c['quantity'] = (int) $c['quantity'];
            $c['ygoprodeck_id'] = $c['ygoprodeck_id'] ? (int) $c['ygoprodeck_id'] : null;
        }
        unset($c);
        return [
            'deck' => $deck,
            'cards' => $cards,
            'cards_loaded' => $loaded,
            'inventory_sha256' => hash(
                'sha256',
                json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ),
        ] + $this->sources->context($deckId);
    }
}
