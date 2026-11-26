<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

interface DeckPackageSources
{
    public function current(int $id): array;

    public function sources(int $id, ?array $entry): array;
}
