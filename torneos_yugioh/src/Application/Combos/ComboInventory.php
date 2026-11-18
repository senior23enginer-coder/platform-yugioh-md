<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

/** Read port. A saving operation requests a locked deck and inventory on its transaction. */
interface ComboInventory
{
    public function load(int $deckId, bool $lock = false, bool $requireMain = true): array;
}
