<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

interface DeckPackageTicketStore
{
    public function pending(): array;

    public function save(array $pending): void;

    public function clear(): void;
}
