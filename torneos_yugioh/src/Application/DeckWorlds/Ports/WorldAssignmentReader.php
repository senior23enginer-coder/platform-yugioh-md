<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds\Ports;

use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;

interface WorldAssignmentReader
{
    /** @return list<WorldAssignment> Assignments whose deck still exists, ordered by deck ID. */
    public function all(): array;

    /** @return list<WorldAssignment> Newest first, at most the existing 25-revision window. */
    public function history(int $deckId): array;
}
