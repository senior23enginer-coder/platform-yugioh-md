<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckMetadata;

use StationX\Yugioh\Domain\DeckMetadata\DeckStudyProfile;

/** One read of the active study registry and its cleared-profile policy per projection. */
final readonly class DeckStudySnapshot
{
    /**
     * @param array<int, DeckStudyProfile> $profiles
     * @param array<int, mixed> $clearedDeckIds Preserve strict ID membership from the registry.
     * @param array<int, mixed> $clearedProfileRevisions
     */
    public function __construct(
        public array $profiles = [],
        public array $clearedDeckIds = [],
        public array $clearedProfileRevisions = [],
    ) {}

    public function excludesProfile(int $deckId, int $revision): bool
    {
        return in_array($deckId, $this->clearedDeckIds, true) &&
            $revision <= (int) ($this->clearedProfileRevisions[$deckId] ?? 0);
    }
}
