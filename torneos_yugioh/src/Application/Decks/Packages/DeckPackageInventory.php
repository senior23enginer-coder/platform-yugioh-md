<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

interface DeckPackageInventory
{
    public function inventory(int $id): array;

    public function saved(int $id): array;
}
