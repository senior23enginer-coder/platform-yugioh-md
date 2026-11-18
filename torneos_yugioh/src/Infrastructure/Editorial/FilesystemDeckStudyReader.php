<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use StationX\Yugioh\Application\DeckMetadata\DeckStudyReader;
use StationX\Yugioh\Application\DeckMetadata\DeckStudySnapshot;
use StationX\Yugioh\Domain\DeckMetadata\DeckStudyProfile;

/** Read-only adapter for the active registry. No research repository or database is opened. */
final readonly class FilesystemDeckStudyReader implements DeckStudyReader
{
    public function __construct(private string $appRoot) {}

    public function read(array $deckIds): DeckStudySnapshot
    {
        $policy = $this->readDocument(
            $this->appRoot . '/assets/editorial/strategy-content-state.json',
        );
        $root = realpath($this->appRoot . '/assets/editorial');
        $profiles = [];
        foreach (array_unique($deckIds) as $id) {
            $entry = $policy['checked_studies'][(string) $id] ?? null;
            if (!$root || !is_string($entry['path'] ?? null)) {
                continue;
            }
            $file = realpath($this->appRoot . '/' . ltrim($entry['path'], './'));
            // Resolve symlinks and traversal first, then require the editorial directory boundary.
            if (
                !$file ||
                !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/')
            ) {
                continue;
            }
            $document = $this->readDocument($file);
            if (
                (int) ($document['deck_id'] ?? 0) !== $id ||
                !is_array($document['profile'] ?? null)
            ) {
                continue;
            }
            $profile = $document['profile'];
            $profiles[$id] = new DeckStudyProfile(
                $profile['Tipo de estrategia'] ?? '',
                $profile['Estilo de juego'] ?? '',
                $profile['Arquetipo'] ?? null,
            );
        }
        return new DeckStudySnapshot(
            $profiles,
            $policy['cleared_deck_ids'] ?? [],
            $policy['cleared_profile_revisions'] ?? [],
        );
    }

    /** Missing/invalid optional metadata cannot provide descriptions for another deck. */
    private function readDocument(string $file): array
    {
        $document = json_decode((string) @file_get_contents($file), true);
        return is_array($document) ? $document : [];
    }
}
