<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\DeckWorlds;

/** A versioned assignment. Persisted historical metadata is kept without reinterpretation. */
final readonly class WorldAssignment
{
    public function __construct(
        public int $deckId,
        public WorldSelection $selection,
        public int $revision,
        public ?string $updatedAt,
    ) {}
}
