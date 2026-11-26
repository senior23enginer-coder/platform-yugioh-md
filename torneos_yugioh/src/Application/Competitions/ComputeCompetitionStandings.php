<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\CompetitionStandingsPolicy;

final readonly class ComputeCompetitionStandings
{
    public function __construct(private CompetitionStandingsReader $reader) {}

    public function execute(int $competitionId, ?string $phaseFilter = null): array
    {
        $competition = $this->reader->competition($competitionId);
        $workflow = $competition ? $this->reader->workflowRanking($competition, $phaseFilter) : [];
        $phase = CompetitionStandingsPolicy::phase($competition, $workflow, $phaseFilter);
        if (CompetitionStandingsPolicy::excluded($competition, $workflow)) {
            return [];
        }
        $decks = $this->reader->decks($competitionId);
        $matches = $this->reader->matches($competitionId, $phase);
        $rows = CompetitionStandingsPolicy::calculate(
            $decks,
            $matches,
            CompetitionStandingsPolicy::rankingImpact($competition),
            $workflow,
        );
        $teams = $competition ? $this->reader->teamConfiguration($competition) : null;
        return $teams === null ? $rows : CompetitionStandingsPolicy::teams($rows, $teams);
    }
}
