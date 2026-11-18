<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds;

use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;

/** Read result without session, transport or persistence details. */
final readonly class WorldsOverview
{
    /**
     * @param list<int> $eligibleIds
     * @param list<WorldAssignment> $records
     * @param list<WorldAssignment> $history
     */
    public function __construct(
        public array $eligibleIds,
        public array $records,
        public array $history,
    ) {}
}
