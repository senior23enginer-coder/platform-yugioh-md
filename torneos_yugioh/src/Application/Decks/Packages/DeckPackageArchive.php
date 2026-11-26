<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

interface DeckPackageArchive
{
    public function read(string $path): array;

    public function write(string $path, array $files, bool $create): void;
}
