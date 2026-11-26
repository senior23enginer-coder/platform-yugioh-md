<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Calendar\FixtureGenerationPlan;

final readonly class ScheduleModelFixtures
{
    public function __construct(
        private FixtureGenerationReader $reader,
        private FixtureGenerationWriter $writer,
        private FixtureGenerationRules $rules,
        private FixtureGenerationActions $actions,
    ) {}

    public function execute(array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay): int
    {
        $this->rules->requireExecutionType((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
        $competition = $this->reader->lockCompetition($competition);
        $competition=$this->rules->teamContext($competition);$this->rules->requireSupportedModel($competition);
        $this->rules->assertFixtureMutable((int)($competition['id'] ?? 0));
        $selectedDeckIds=$this->rules->admit($competition,$selectedDeckIds);
        if($this->rules->teamKind($competition)!=='' || in_array((string)$competition['execution_model'],$this->rules->workflowModels(),true))return $this->actions->workflow($competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);

        $competitionId = (int) ($competition['id'] ?? 0);
        $seasonId = $this->reader->currentSeasonId();
        $divisionId = (int) (($competition['division_id'] ?? 0) ?: $this->reader->firstDivisionId());
        $participantsPerSide = max(1, min(3, $participantsPerSide));
        $matchesPerDay = max(1, min(5, $matchesPerDay));
        $selectedDeckIds = $this->rules->admissionLoad($competition)!==null ? $selectedDeckIds : $this->rules->shuffle($selectedDeckIds);
        $groups = $this->rules->rosterGroups($selectedDeckIds, $participantsPerSide);
        if ($competitionId <= 0 || $divisionId <= 0 || count($groups) < 2) {
            return 0;
        }
        $type = (string) (($competition['type'] ?? '') ?: 'tournament');
        $execution = (string) (($competition['execution_model'] ?? '') ?: 'manual');
        if(in_array($execution,$this->rules->workflowModels(),true))return $this->actions->workflow($competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);
        if($execution==='free_for_all')throw new InvalidArgumentException('Crea las mesas de3 o4 jugadores desde Battle Royale en la ficha.');
        if ($execution==='manual') throw new InvalidArgumentException('Modo manual: crea sus combates expresamente; no se generara un round robin.');
        $phase = in_array($type, ['league'], true) ? 'league' : ($type === 'world' ? 'world' : ($type === 'final' ? 'final' : 'tournament'));
        if (in_array($execution, ['single_elimination', 'double_elimination', 'top_cut', 'playoffs', 'bracket', 'series'], true)) {
            return $this->actions->cup($competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $matchesPerDay);
        }
        if (in_array($execution, ['swiss', 'swiss_top_cut'], true) || $type === 'qualifier') {
            $duels = max(2, min(5, count($groups) - 1));
            $swissPhase = $type === 'qualifier' ? 'qualifier' : 'swiss';
            return $this->actions->qualifier($competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $duels, $matchesPerDay, $swissPhase);
        }

        $leaguePlan=null;
        if ($type==='league') {
            if ($this->reader->hasFinalized($competitionId)) {
                throw new InvalidArgumentException('No se puede regenerar una liga que ya tiene combates finalizados.');
            }
            $leaguePlan=$this->rules->leaguePlan($competitionId,$selectedDeckIds,$participantsPerSide,$execution==='double_round_robin');
        }

        $this->writer->clearPending($competitionId);
        $plan = FixtureGenerationPlan::rounds($groups, $leaguePlan, $execution, $phase);
        $groups = $plan['groups'];
        $rounds = $plan['rounds'];
        $phase = $plan['phase'];
        $generation = $this->reader->generation($competitionId, false);
        $created = $this->writer->insertModel(FixtureGenerationPlan::modelRows(
            $groups, $rounds, $divisionId, $competitionId, $competition, $startDate, $pattern,
            $matchFormat, $participantsPerSide, $matchesPerDay, $generation, $phase, $execution,
        ));
        $this->actions->admissionStage($competition,$startDate,$pattern,$matchesPerDay);
        $this->actions->rebuildCalendar($seasonId, $competitionId, $phase);
        return $created;
    }
}
