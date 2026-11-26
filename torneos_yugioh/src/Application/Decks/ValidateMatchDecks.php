<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use StationX\Yugioh\Domain\Decks\MatchDeckPolicy;

final readonly class ValidateMatchDecks
{
    public function __construct(
        private ResolveEligibleDecks $eligibility,
        private MatchRosterReader $reader,
    ) {}

    public function execute(
        ?int $competitionId,
        int $manualDivisionId,
        array $deckIds,
        int $participantsPerSide = 1,
    ): array {
        $allowed = $this->eligibility->execute($competitionId, $manualDivisionId);
        $selection = MatchDeckPolicy::selection($allowed, $deckIds);
        if (!$selection['ok']) {
            return $selection;
        }
        $ids = $selection['ids'];
        return MatchDeckPolicy::validateRows(
            $this->reader->activeDeckRows($ids),
            $ids,
            $deckIds,
            $manualDivisionId,
            $participantsPerSide,
        );
    }
}
