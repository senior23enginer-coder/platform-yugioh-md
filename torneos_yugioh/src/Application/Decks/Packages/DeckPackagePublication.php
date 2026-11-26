<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

interface DeckPackagePublication
{
    public function underLock(callable $operation): mixed;

    public function registry(): array;

    public function version(int $id): string;

    public function createVersion(string $version): void;

    public function writeImage(string $version, string $destination, string $bytes): void;

    public function writeStudy(string $version, int $id, string $json): void;

    public function writeOriginal(string $version, array $files): void;

    public function writePrevious(string $version, string $json): void;

    public function activate(string $json): void;
}
