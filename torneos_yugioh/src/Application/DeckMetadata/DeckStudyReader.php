<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckMetadata;

/** Outgoing read port. Implementations resolve identities before returning study descriptions. */
interface DeckStudyReader
{
    /** @param list<int> $deckIds */
    public function read(array $deckIds): DeckStudySnapshot;
}
