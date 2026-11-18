<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds;

use InvalidArgumentException;
use StationX\Yugioh\Application\DeckWorlds\Ports\EligibleDecks;
use StationX\Yugioh\Application\DeckWorlds\Ports\WorldAssignmentReader;

/** Read projections preserve historical rows while excluding ineligible decks from the directory. */
final readonly class DeckWorldsQueries
{
    public function __construct(
        private WorldAssignmentReader $assignments,
        private EligibleDecks $eligibleDecks,
    ) {}

    public function overview(int $deckId = 0): WorldsOverview
    {
        $eligible = $this->eligibleIds();
        if ($deckId && !in_array($deckId, $eligible, true)) {
            throw new InvalidArgumentException('Mundos solo está disponible para decks TCG.');
        }
        return new WorldsOverview(
            $eligible,
            $this->filter($eligible),
            $deckId ? $this->history($deckId) : [],
        );
    }

    public function eligibleIds(): array
    {
        return $this->eligibleDecks->ids();
    }

    public function records(): array
    {
        return $this->filter($this->eligibleIds());
    }

    public function history(int $deckId): array
    {
        return $this->assignments->history($deckId);
    }

    private function filter(array $eligible): array
    {
        return array_values(
            array_filter(
                $this->assignments->all(),
                static fn($r): bool => in_array($r->deckId, $eligible, true),
            ),
        );
    }
}
