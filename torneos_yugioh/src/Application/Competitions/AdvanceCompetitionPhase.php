<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Competitions\PhaseAdvancePolicy;

final readonly class AdvanceCompetitionPhase
{
    public function __construct(
        private PhaseAdvanceReader $reader,
        private PhaseAdvanceActions $actions,
        private CompetitionPhaseQueries $queries,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(int $competitionId): int
    {
        return $this->transaction->run(function () use ($competitionId): int {
            $competition = $this->reader->lock($competitionId);
            if (!$competition) {
                return 0;
            }
            if ($this->actions->admission($competition) !== null) {
                $this->actions->requireAdmission($competition, $this->reader->registeredDecks($competitionId));
            }
            if (
                ($this->actions->teamKind($competition) !== '' ||
                    in_array((string) $competition['execution_model'], $this->actions->workflowModels(), true)) &&
                $this->actions->workflow($competition) !== null
            ) {
                return $this->actions->generateWorkflow($competition);
            }
            $this->actions->requireOpen((int) $competition['season_id']);
            PhaseAdvancePolicy::requireOpenStatus($competition);
            $this->actions->requireSupportedModel($competition);
            return $this->locked($competitionId);
        });
    }

    public function locked(int $competitionId): int
    {
        $competition = $this->reader->activeCompetition($competitionId);
        if (!$competition) {
            return 0;
        }
        $type = (string) (($competition['type'] ?? '') ?: 'tournament');
        $execution = (string) (($competition['execution_model'] ?? '') ?: 'manual');
        if(in_array($execution,$this->actions->workflowModels(),true))throw new InvalidArgumentException('Este evento anterior no tiene un reglamento versionado. No se reinterpretaran sus combates.');
        $phaseStructure = strtoupper((string) (($competition['phase_structure'] ?? '') ?: 'NONE'));
        $created = 0;

        if (PhaseAdvancePolicy::blockedQualifier($competition, $type, $execution, $phaseStructure)) {
            $this->actions->audit($competitionId, 'blocked_advance_competition_phase', ['reason' => 'division_qualifier_has_no_next_phase']);
            return 0;
        }

        $advanceKnockout = fn(string $phase): int => $this->advanceKnockout($competition, $competitionId, $phase);

        if (in_array($execution, ['single_elimination', 'double_elimination', 'top_cut', 'playoffs', 'bracket', 'series'], true)) {
            $phase = $this->queries->count($competitionId,'cup')>0 ? 'cup' : ($type === 'world' ? 'world' : ($type === 'final' ? 'final' : 'cup'));
            $created += $advanceKnockout($phase);
        }

        if ($created === 0 && (in_array($execution, ['swiss_top_cut', 'top_cut'], true) || str_contains($phaseStructure, 'TOP_CUT'))) {
            $targetPhase = 'top_cut';
            if ($this->queries->count($competitionId, $targetPhase) > 0) {
                $created += $advanceKnockout($targetPhase);
            } else {
                $basePhase = $this->queries->count($competitionId, 'swiss') > 0 ? 'swiss' : 'qualifier';
                if ($this->queries->finished($competitionId, $basePhase)) {
                    $ranking = $this->reader->standings($competitionId);
                    $topSize = $this->actions->targetSize(count($ranking));
                    $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
                    $created += $this->actions->createRound($competition, $targetPhase, $deckIds, 1, 'Top Cut');
                }
            }
        }

        if ($created === 0 && (in_array($execution, ['groups_knockout'], true) || str_contains($phaseStructure, 'GROUPS'))) {
            $targetPhase = 'playoff';
            if ($this->queries->count($competitionId, $targetPhase) > 0) {
                $created += $advanceKnockout($targetPhase);
            } elseif ($this->queries->finished($competitionId, 'groups')) {
                $ranking = $this->reader->standings($competitionId);
                $topSize = $this->actions->targetSize(count($ranking));
                $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
                $created += $this->actions->createRound($competition, $targetPhase, $deckIds, 1, 'Eliminatoria');
            }
        }

        if ($created === 0 && $type === 'league') {
            $targetPhase = 'playoff';
            if ($this->queries->count($competitionId, $targetPhase) > 0) {
                $created += $advanceKnockout($targetPhase);
            } elseif (in_array($execution, ['season_playoffs', 'playoffs'], true) && $this->queries->finished($competitionId, 'league')) {
                $ranking = $this->reader->standings($competitionId);
                $topSize = $this->actions->targetSize(count($ranking));
                $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
                $created += $this->actions->createRound($competition, $targetPhase, $deckIds, 1, 'Playoff');
            }
        }

        if ($created > 0) {
            $this->actions->audit($competitionId, 'advance_competition_phase', ['created' => $created, 'type' => $type, 'execution_model' => $execution]);
        }
        return $created;
    }

    private function advanceKnockout(array $competition, int $competitionId, string $phase): int
    {
        $round = $this->reader->latestRound($competitionId, $phase);
        if ($round <= 0 || $this->reader->pendingCount($competitionId, $phase, $round) > 0) {
            return 0;
        }
        $winners = $this->queries->winners($competitionId, $phase, $round);
        if (count($winners) === 1) {
            $this->actions->recordHonor($competitionId);
            return 0;
        }
        if (count($winners) < 2) {
            return 0;
        }
        $nextRound = $round + 1;
        $label = count($winners) <= 2 ? 'Final' : 'Ronda ' . $nextRound;
        return $this->actions->createRound($competition, $phase, $winners, $nextRound, $label);
    }
}
