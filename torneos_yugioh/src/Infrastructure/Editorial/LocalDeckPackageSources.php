<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use StationX\Yugioh\Application\Decks\Packages\DeckPackageArchive;
use StationX\Yugioh\Application\Decks\Packages\DeckPackageSources;
use StationX\Yugioh\Domain\Decks\Packages\PackageFolder;

final readonly class LocalDeckPackageSources implements DeckPackageSources
{
    public function __construct(
        private string $app,
        private LocalDeckPackageMedia $media,
        private DeckPackageArchive $archive,
    ) {}

    public function current(int $id): array
    {
        $state = DeckPackageJsonFile::read(
            $this->app . '/assets/editorial/strategy-content-state.json',
        );
        $entry = $state['checked_studies'][(string) $id] ?? null;
        $path = $entry ? $this->media->resolve($entry['path']) : null;
        return [$entry, $path ? DeckPackageJsonFile::read($path) : null];
    }

    public function sources(int $id, ?array $entry): array
    {
        $path = isset($entry['source_zip']) ? $this->media->resolve($entry['source_zip']) : null;
        if (!$path) {
            $indexPath = $this->app . '/assets/editorial/deck-data-sources/index.json';
            $index = is_file($indexPath) ? DeckPackageJsonFile::read($indexPath) : [];
            $path = isset($index[(string) $id])
                ? $this->media->resolve($index[(string) $id]['zip'])
                : null;
        }
        return $path ? PackageFolder::normalizeFiles($this->archive->read($path)) : [];
    }
}
