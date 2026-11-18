<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\DeckWorlds;

use PDO;
use StationX\Yugioh\Application\DeckWorlds\Ports\EligibleDecks;

/** Adapter to the existing format resolver, including its current roster semantics. */
final readonly class LegacyTcgEligibility implements EligibleDecks
{
    public function __construct(private PDO $pdo) {}

    public function ids(): array
    {
        return array_values(
            array_unique(
                array_merge(
                    \ygo_format_deck_ids($this->pdo, 'tcg_advanced'),
                    \ygo_format_deck_ids($this->pdo, 'tcg_traditional'),
                ),
            ),
        );
    }
}
