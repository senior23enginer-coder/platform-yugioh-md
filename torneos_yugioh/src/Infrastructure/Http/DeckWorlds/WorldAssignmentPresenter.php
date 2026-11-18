<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\DeckWorlds;

use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;

/** Preserves the public JSON shape while keeping transport keys out of domain entities. */
final class WorldAssignmentPresenter
{
    public static function one(WorldAssignment $assignment): array
    {
        return [
            'deck_id' => $assignment->deckId,
            'attributes' => $assignment->selection->codes,
            'primary' => $assignment->selection->primary(),
            'revision' => $assignment->revision,
            'updated_at' => $assignment->updatedAt,
        ];
    }

    /** @param list<WorldAssignment> $assignments */
    public static function many(array $assignments): array
    {
        return array_map(self::one(...), $assignments);
    }
}
