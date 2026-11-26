<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use RuntimeException;
use StationX\Yugioh\Application\Decks\Packages\DeckPackageArchive;
use StationX\Yugioh\Application\Decks\Packages\DeckPackagePublication;

final readonly class LocalDeckPackagePublication implements DeckPackagePublication
{
    private string $root;

    public function __construct(string $app, private DeckPackageArchive $archive)
    {
        $this->root = $app . '/assets/editorial';
    }

    public function underLock(callable $operation): mixed
    {
        $lock = fopen($this->root . '/.deck-data.lock', 'c');
        if (!$lock) {
            throw new RuntimeException('No se pudo bloquear la publicación.');
        }
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('No se pudo bloquear la publicación.');
        }
        try {
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function registry(): array
    {
        return DeckPackageJsonFile::read($this->root . '/strategy-content-state.json');
    }

    public function version(int $id): string
    {
        return 'deck-data-' . $id . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    public function createVersion(string $version): void
    {
        if (!mkdir($this->root . '/' . $version, 0755, true)) {
            throw new RuntimeException('No se pudo crear la versión del deck.');
        }
    }

    public function writeImage(string $version, string $destination, string $bytes): void
    {
        if (file_put_contents($this->root . '/' . $version . '/' . $destination, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('No se pudo escribir la imagen.');
        }
    }

    public function writeStudy(string $version, int $id, string $json): void
    {
        if (file_put_contents($this->root . '/' . $version . '/' . $id . '.json', $json) !== strlen($json)) {
            throw new RuntimeException('No se pudo escribir el contenido.');
        }
    }

    public function writeOriginal(string $version, array $files): void
    {
        $this->archive->write($this->root . '/' . $version . '/source.zip', $files, true);
    }

    public function writePrevious(string $version, string $json): void
    {
        file_put_contents($this->root . '/' . $version . '/previous-entry.json', $json);
    }

    public function activate(string $json): void
    {
        $registry = $this->root . '/strategy-content-state.json';
        $temp = $registry . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp, $registry)) {
            throw new RuntimeException('No se pudo activar la versión; el contenido anterior sigue disponible.');
        }
    }
}
