<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use StationX\Yugioh\Domain\Decks\DeckDeletionPolicy;

final readonly class FindDeckDeletionBlocker
{
    public function __construct(private DeckDeletionReader $reader) {}

    public function execute(int $deckId): ?array
    {
        if ($deckId <= 0) {
            return null;
        }
        $statuses = DeckDeletionPolicy::blockedStatuses();
        return $this->reader->competition($deckId, $statuses) ?? $this->reader->match($deckId, $statuses);
    }
}
