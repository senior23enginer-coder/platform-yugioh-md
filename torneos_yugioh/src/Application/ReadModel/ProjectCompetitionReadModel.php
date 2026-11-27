<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

use Throwable;

final readonly class ProjectCompetitionReadModel
{
    public function __construct(private CompetitionReadModelServices $services) {}

    public function execute(
        array $dbDivisions,
        array $competitionDeckRows,
        array $allDecks,
        array $competitions,
        array $fixtures,
    ): array {
        $leagueDivisionIdsByCompetition = [];
        foreach ($dbDivisions as $division) {
            $leagueCompetitionId = (int) ($division['league_competition_id'] ?? 0);
            if ($leagueCompetitionId > 0) {
                $leagueDivisionIdsByCompetition[$leagueCompetitionId][] = (int) $division['id'];
            }
        }
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
        return [
            'competitions' => $competitions,
            'fixtures' => $fixtures,
            'dependencies' => $competitionDependencies,
        ];
    }
}
