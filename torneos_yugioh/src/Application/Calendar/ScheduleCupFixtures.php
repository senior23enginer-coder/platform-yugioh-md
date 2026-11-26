<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Calendar\FixtureGenerationPlan;

final readonly class ScheduleCupFixtures
{
    public function __construct(
        private FixtureGenerationReader $reader,
        private FixtureGenerationWriter $writer,
        private FixtureGenerationRules $rules,
        private FixtureGenerationActions $actions,
    ) {}

    public function execute(array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay): int
    {
        if(!in_array((string)($competition['execution_model']??'manual'),['single_elimination','bracket','top_cut','playoffs'],true)) throw new InvalidArgumentException('El programador de llave no puede ejecutar una serie, modo manual u otro motor.');

        $this->rules->requireExecutionType((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
        $this->rules->requireSupportedModel($competition);
        $competition = $this->reader->lockCompetition($competition);
        $this->rules->assertFixtureMutable((int)($competition['id'] ?? 0));
        $selectedDeckIds=$this->rules->admit($competition,$selectedDeckIds);

        $matchesPerDay = max(1, min(5, $matchesPerDay));
        $competitionId = (int) ($competition['id'] ?? 0);
        $seasonId = $this->reader->currentSeasonId();
        $divisionId = !empty($competition['division_id']) ? (int) $competition['division_id'] : null;
        $participantsPerSide = max(1, min(3, $participantsPerSide));
        $minimumDecks = $participantsPerSide * 2;
        $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
        if ($competitionId <= 0 || count($selectedDeckIds) < $minimumDecks) {
            return 0;
        }
        $generation = $this->reader->generation($competitionId, true);
        $selectedDeckIds = $this->rules->admissionLoad($competition)!==null ? $selectedDeckIds : $this->rules->shuffle($selectedDeckIds);
        $groups = $this->rules->rosterGroups($selectedDeckIds, $participantsPerSide);
        $groupCount = count($groups);
        if ($groupCount < 2) {
            return 0;
        }

        $this->writer->clearPending($competitionId);
        $created = $this->writer->insertCup(FixtureGenerationPlan::cupRows(
            $groups, $divisionId, $competitionId, $competition, $startDate, $pattern,
            $matchFormat, $participantsPerSide, $matchesPerDay, $generation,
        ));
        if($groupCount%2===1) $this->actions->saveBye('cup_bye_'.$competitionId.'_1',json_encode([(int)$groups[intdiv($groupCount,2)][0]]));
        $this->actions->admissionStage($competition,$startDate,$pattern,$matchesPerDay);
        $this->actions->rebuildCalendar($seasonId, $competitionId, 'cup');
        return $created;
    }
}
