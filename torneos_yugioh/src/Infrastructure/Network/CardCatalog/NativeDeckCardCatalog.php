<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Network\CardCatalog;

use StationX\Yugioh\Application\Decklists\DeckCardCatalog;
use StationX\Yugioh\Application\CardCatalog\LookupCard;

/** Reuses the catalog use case, retaining identity validation and shared provider/cache behavior. */
final readonly class NativeDeckCardCatalog implements DeckCardCatalog
{
    public function __construct(private LookupCard $cards) {}
    public function lookup(int $cardId): array
    {
        return $this->cards->execute($cardId);
    }
}
