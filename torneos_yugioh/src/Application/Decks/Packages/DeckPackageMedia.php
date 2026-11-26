<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

interface DeckPackageMedia
{
    public function exists(string $url): bool;

    /** @return array{sha256:string,extension:string,bytes:string}|null */
    public function read(string $url): ?array;
}
