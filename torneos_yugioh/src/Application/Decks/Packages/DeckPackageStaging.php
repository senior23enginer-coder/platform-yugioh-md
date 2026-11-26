<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

interface DeckPackageStaging
{
    public function stage(string $upload): string;

    public function discard(string $path): void;

    public function ticket(): string;
}
