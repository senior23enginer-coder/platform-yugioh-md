<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds\Ports;

/** Format eligibility is resolved by the existing catalog adapter, not inferred from card lists. */
interface EligibleDecks
{
    /** @return list<int> */
    public function ids(): array;
}
