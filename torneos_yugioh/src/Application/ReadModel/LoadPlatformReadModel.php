<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

use Throwable;
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
        $leagueDivisionIdsByCompetition = [];
        foreach ($dbDivisions as $division) {
            $leagueCompetitionId = (int) ($division['league_competition_id'] ?? 0);
            if ($leagueCompetitionId > 0) {
                $leagueDivisionIdsByCompetition[$leagueCompetitionId][] = (int) $division['id'];
            }
        }
        $matchTypes = $this->reader->matchTypes();
        $competitionDeckRows = $this->reader->competitionDeckRows();
        $competitionDecksById = [];
        foreach ($competitionDeckRows as $row) {
            $competitionDecksById[(int) $row['competition_id']][] = (int) $row['deck_id'];
        }
        $deckTeamById = [];
        foreach ($allDecks as $deck) {
            $deckTeamById[(int) ($deck['id'] ?? 0)] =
                $deck['team_id'] !== null ? (int) $deck['team_id'] : null;
        }
        $competitionDependencies = $this->services->dependencies();
        $competitionGateById = [];
        $progressGroups = $this->services->progressGroups();
        foreach ($competitions as &$competition) {
            $competitionId = (int) $competition['id'];
            $competition['progress'] = $this->services->progress(
                $progressGroups[$competitionId] ?? [],
                in_array((string) $competition['type'], ['cup', 'final'], true),
            );
            $associatedDivisionIds = array_values(
                array_unique($leagueDivisionIdsByCompetition[$competitionId] ?? []),
            );
            $primaryDivisionId = (int) ($competition['division_id'] ?? 0);
            if (
                $primaryDivisionId > 0 &&
                !in_array($primaryDivisionId, $associatedDivisionIds, true)
            ) {
                array_unshift($associatedDivisionIds, $primaryDivisionId);
            }
            $competition['division_ids'] = $associatedDivisionIds;
            $competition['division_count'] = count($associatedDivisionIds);
            $gate = $this->services->gate($competitionId);
            $competition['dependencies'] = $gate['dependencies'];
            $competition['can_execute'] = $gate['can_execute'];
            $competition['blocked_reason'] = $gate['can_execute'] ? '' : $gate['message'];
            try {
                $allowedDeckIds = $this->services->allowedDecks(
                    $competitionId,
                    (int) ($competition['division_id'] ?? 0),
                );
                $standings = $this->services->standings($competitionId);
            } catch (Throwable $error) {
                $allowedDeckIds = [];
                $standings = [];
            }
            $competition['selected_deck_ids'] = $competitionDecksById[$competitionId] ?? [];
            $visibleDeckIds = $competition['selected_deck_ids'] ?: $allowedDeckIds;
            $participantTeamIds = [];
            foreach ($visibleDeckIds as $deckId) {
                $teamId = $deckTeamById[(int) $deckId] ?? null;
                if ($teamId !== null && $teamId > 0) {
                    $participantTeamIds[$teamId] = true;
                }
            }
            $competition['deck_pool_count'] = count($allowedDeckIds);
            $competition['participant_deck_count'] = count($visibleDeckIds);
            $competition['participant_team_count'] = count($participantTeamIds);
            $competition['ranking'] = $standings;
            $competition['ranking_ready'] = count($standings) > 0;
            $competition['top_cut'] = array_slice(
                $standings,
                0,
                (string) ($competition['type'] ?? '') === 'world' ? 24 : 16,
            );
            $competitionGateById[$competitionId] = $gate;
        }
        unset($competition);
        foreach ($fixtures as &$rounds) {
            foreach ($rounds as &$round) {
                foreach ($round['matches'] as &$match) {
                    $competitionId = (int) ($match['competition_id'] ?? 0);
                    $gate =
                        $competitionId > 0
                            ? $competitionGateById[$competitionId] ??
                                $this->services->gate($competitionId)
                            : ['can_execute' => true, 'message' => 'Sin competencia asociada.'];
                    $match['competition_can_execute'] = (bool) ($gate['can_execute'] ?? true);
                    $match['competition_blocked_reason'] = (string) ($gate['message'] ?? '' ?: '');
                }
                unset($match);
            }
            unset($round);
        }
        unset($rounds);
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
        $conditionsByRule = [];
        foreach ($gameRuleConditions as $condition) {
            $conditionsByRule[(int) ($condition['game_rule_id'] ?? 0)][] = $condition;
        }
        foreach ($gameRules as &$gameRule) {
            $gameRule['conditions'] = $conditionsByRule[(int) ($gameRule['id'] ?? 0)] ?? [];
            $gameRule['condition_count'] = count(
                array_filter(
                    $gameRule['conditions'],
                    static fn($condition) => !empty($condition['active']),
                ),
            );
        }
        unset($gameRule);
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
