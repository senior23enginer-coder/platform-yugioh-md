<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\ReadModel\PlatformReadModelReader;

/** Compatibility composition of the four independent PDO read adapters. */
final readonly class PdoPlatformReadModelReader implements PlatformReadModelReader
{
    private PdoSportingReadModelReader $sporting;
    private PdoDeckIdentityReadModelReader $identity;
    private PdoHistoricalReadModelReader $history;
    private PdoRulesReadModelReader $rules;

    public function __construct(PDO $pdo)
    {
        $this->sporting = new PdoSportingReadModelReader($pdo);
        $this->identity = new PdoDeckIdentityReadModelReader($pdo);
        $this->history = new PdoHistoricalReadModelReader($pdo);
        $this->rules = new PdoRulesReadModelReader($pdo);
    }

    public function divisions(int $seasonId): array
    {
        return $this->sporting->divisions($seasonId);
    }

    public function decks(): array
    {
        return $this->identity->decks();
    }

    public function matches(): array
    {
        return $this->sporting->matches();
    }

    public function statusHistory(): array
    {
        return $this->history->statusHistory();
    }

    public function standingSnapshots(): array
    {
        return $this->history->standingSnapshots();
    }

    public function players(): array
    {
        return $this->identity->players();
    }

    public function playerDecks(): array
    {
        return $this->identity->playerDecks();
    }

    public function matchSnapshots(): array
    {
        return $this->history->matchSnapshots();
    }

    public function matchupAnalyses(): array
    {
        return $this->identity->matchupAnalyses();
    }

    public function matchupAnalysisHistory(): array
    {
        return $this->identity->matchupAnalysisHistory();
    }

    public function deckAnalysisProfiles(): array
    {
        return $this->identity->deckAnalysisProfiles();
    }

    public function deckAnalysisProfileHistory(): array
    {
        return $this->identity->deckAnalysisProfileHistory();
    }

    public function medalUnlocks(): array
    {
        return $this->history->medalUnlocks();
    }

    public function checkins(): array
    {
        return $this->history->checkins();
    }

    public function metaHistory(): array
    {
        return $this->history->metaHistory();
    }

    public function eloHistory(): array
    {
        return $this->history->eloHistory();
    }

    public function medalProgress(): array
    {
        return $this->history->medalProgress();
    }

    public function rankingHistory(): array
    {
        return $this->history->rankingHistory();
    }

    public function eventSummaries(): array
    {
        return $this->history->eventSummaries();
    }

    public function teams(): array
    {
        return $this->identity->teams();
    }

    public function competitions(): array
    {
        return $this->sporting->competitions();
    }

    public function matchTypes(): array
    {
        return $this->rules->matchTypes();
    }

    public function competitionDeckRows(): array
    {
        return $this->sporting->competitionDeckRows();
    }

    public function cards(): array
    {
        return $this->identity->cards();
    }

    public function media(): array
    {
        return $this->identity->media();
    }

    public function logoHistory(): array
    {
        return $this->identity->logoHistory();
    }

    public function honors(): array
    {
        return $this->history->honors();
    }

    public function audit(): array
    {
        return $this->history->audit();
    }

    public function events(): array
    {
        return $this->sporting->events();
    }

    public function rulesets(): array
    {
        return $this->rules->rulesets();
    }

    public function rulesetVersions(): array
    {
        return $this->rules->rulesetVersions();
    }

    public function gameRules(): array
    {
        return $this->rules->gameRules();
    }

    public function gameRuleConditions(): array
    {
        return $this->rules->gameRuleConditions();
    }

    public function seasons(): array
    {
        return $this->sporting->seasons();
    }
}
