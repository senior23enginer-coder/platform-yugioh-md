<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface DeckIdentityReadModelReader
{
    public function decks(): array;

    public function teams(): array;

    public function players(): array;

    public function playerDecks(): array;

    public function cards(): array;

    public function media(): array;

    public function logoHistory(): array;

    public function matchupAnalyses(): array;

    public function matchupAnalysisHistory(): array;

    public function deckAnalysisProfiles(): array;

    public function deckAnalysisProfileHistory(): array;
}
