<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface PlatformReadModelReader
{
    public function divisions(int $seasonId): array;

    public function decks(): array;

    public function matches(): array;

    public function statusHistory(): array;

    public function standingSnapshots(): array;

    public function players(): array;

    public function playerDecks(): array;

    public function matchSnapshots(): array;

    public function matchupAnalyses(): array;

    public function matchupAnalysisHistory(): array;

    public function deckAnalysisProfiles(): array;

    public function deckAnalysisProfileHistory(): array;

    public function medalUnlocks(): array;

    public function checkins(): array;

    public function metaHistory(): array;

    public function eloHistory(): array;

    public function medalProgress(): array;

    public function rankingHistory(): array;

    public function eventSummaries(): array;

    public function teams(): array;

    public function competitions(): array;

    public function matchTypes(): array;

    public function competitionDeckRows(): array;

    public function cards(): array;

    public function media(): array;

    public function logoHistory(): array;

    public function honors(): array;

    public function audit(): array;

    public function events(): array;

    public function rulesets(): array;

    public function rulesetVersions(): array;

    public function gameRules(): array;

    public function gameRuleConditions(): array;

    public function seasons(): array;
}
