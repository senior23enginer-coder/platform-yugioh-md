<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds\Ports;

use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;

/** Write-side operations must run inside the same transaction and serialized deck lock. */
interface WorldAssignmentWriter
{
    public function lockExistingDeck(int $deckId): bool;

    public function findForUpdate(int $deckId): ?WorldAssignment;

    /** Persist both the current assignment and its immutable revision, without committing. */
    public function persistWithHistory(WorldAssignment $assignment, bool $alreadyExists): void;
}
