<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\DeckMetadata;

/** Display-only metadata. Building it never changes the deck or its inventory. */
final readonly class DeckMetadata
{
    public function __construct(
        public string $style,
        public string $archetype,
        public int $cardCount,
    ) {}

    public function hasCards(): bool
    {
        return $this->cardCount > 0;
    }
}
