<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decklists;

/** Resolves an exact provider identity, without exposing catalog transport/cache to inventory rules. */
interface DeckCardCatalog
{
    public function lookup(int $cardId): array;
}
