<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Decks\PlatformDeckProjection;
use StationX\Yugioh\Domain\Matches\PlatformFixtureProjection;

final readonly class LoadPlatformReadModel
{
    public function __construct(
        private PlatformReadModelReader $reader,
        private PlatformReadModelServices $services,
        private Clock $clock,
    ) {}

    public function execute(array $fallbackDivisions): array
    {
        $seasonId = $this->services->currentSeasonId();
        $dbDivisions = $this->reader->divisions($seasonId);

        $divisions = PlatformDeckProjection::divisions($dbDivisions, $fallbackDivisions);

        $deckRows = $this->reader->decks();
        ['divisions' => $divisions, 'allDecks' => $allDecks] = PlatformDeckProjection::decks(
            $divisions,
            $deckRows,
        );

        $matchRows = $this->reader->matches();
        [
            'fixtures' => $fixtures,
            'deckMatchCounts' => $deckMatchCounts,
        ] = PlatformFixtureProjection::build($matchRows, $divisions);

        $teams = $this->reader->teams();
        $competitions = $this->reader->competitions();
        $matchTypes = $this->reader->matchTypes();
        $competitionDeckRows = $this->reader->competitionDeckRows();
        [
            'competitions' => $competitions,
            'fixtures' => $fixtures,
            'dependencies' => $competitionDependencies,
        ] = (new ProjectCompetitionReadModel($this->services))->execute(
            $dbDivisions,
            $competitionDeckRows,
            $allDecks,
            $competitions,
            $fixtures,
        );
        $cards = $this->reader->cards();
        $media = $this->reader->media();
        $logoHistory = $this->reader->logoHistory();
        $honors = $this->reader->honors();
        $audit = $this->reader->audit();
        $events = $this->reader->events();
        $rulesets = $this->reader->rulesets();
        $rulesetVersions = $this->reader->rulesetVersions();
        $gameRules = $this->reader->gameRules();
        $gameRuleConditions = $this->reader->gameRuleConditions();
        $gameRules = GameRuleReadModelProjection::attachConditions($gameRules, $gameRuleConditions);
        $statusHistory = $this->reader->statusHistory();
        $this->services->backfill($this->services->currentSeasonId());
        $standingSnapshots = $this->reader->standingSnapshots();
        $seasons = $this->reader->seasons();
        $players = $this->reader->players();
        $playerDecks = $this->reader->playerDecks();
        $matchSnapshots = $this->reader->matchSnapshots();
        $matchupAnalyses = $this->reader->matchupAnalyses();
        $matchupAnalysisHistory = $this->reader->matchupAnalysisHistory();
        $deckAnalysisProfiles = $this->reader->deckAnalysisProfiles();
        $deckAnalysisProfileHistory = $this->reader->deckAnalysisProfileHistory();
        $medalUnlocks = $this->reader->medalUnlocks();
        $checkins = $this->reader->checkins();
        $metaHistory = $this->reader->metaHistory();
        $eloHistory = $this->reader->eloHistory();
        $medalProgress = $this->reader->medalProgress();
        $rankingHistory = $this->reader->rankingHistory();
        $eventSummaries = $this->reader->eventSummaries();

        $allDecks = $this->services->deckMetadata($allDecks, $cards, $deckAnalysisProfiles);
        return [
            'divisions' => $divisions,
            'fixtures' => $fixtures,
            'allDecks' => $allDecks,
            'teams' => $teams,
            'competitions' => $competitions,
            'cards' => $cards,
            'media' => $media,
            'logoHistory' => $logoHistory,
            'honors' => $honors,
            'audit' => $audit,
            'events' => $events,
            'rulesets' => $rulesets,
            'rulesetVersions' => $rulesetVersions,
            'matchTypes' => $matchTypes,
            'gameRules' => $gameRules,
            'gameRuleConditions' => $gameRuleConditions,
            'competitionDependencies' => $competitionDependencies,
            'statusHistory' => $statusHistory,
            'standingSnapshots' => $standingSnapshots,
            'seasons' => $seasons,
            'seasonEvents' => $this->services->seasonEvents(),
            'players' => $players,
            'playerDecks' => $playerDecks,
            'matchSnapshots' => $matchSnapshots,
            'matchupAnalyses' => $matchupAnalyses,
            'matchupAnalysisHistory' => $matchupAnalysisHistory,
            'deckAnalysisProfiles' => $deckAnalysisProfiles,
            'deckAnalysisProfileHistory' => $deckAnalysisProfileHistory,
            'medalUnlocks' => $medalUnlocks,
            'checkins' => $checkins,
            'metaHistory' => $metaHistory,
            'eloHistory' => $eloHistory,
            'medalProgress' => $medalProgress,
            'rankingHistory' => $rankingHistory,
            'eventSummaries' => $eventSummaries,
            'settings' => $this->services->settings(),
            'uiLabels' => $this->services->labels(),
            'serverToday' => $this->clock->now()->format('Y-m-d'),
        ];
    }
}
