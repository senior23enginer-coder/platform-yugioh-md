<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\DeckWorlds;

use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;
use StationX\Yugioh\Domain\DeckWorlds\WorldSelection;

/** Keeps database JSON and row names outside the domain. Invalid stored JSON still fails explicitly. */
final class WorldAssignmentCodec
{
    public static function fromRow(array $row): WorldAssignment
    {
        return new WorldAssignment(
            (int) $row['deck_id'],
            WorldSelection::fromStored(
                json_decode($row['attributes_json'], true, 8, JSON_THROW_ON_ERROR),
            ),
            (int) $row['revision'],
            $row['updated_at'] ?? ($row['changed_at'] ?? null),
        );
    }
}
