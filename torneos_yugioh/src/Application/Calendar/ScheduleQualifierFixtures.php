<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Calendar\FixtureGenerationPlan;

final readonly class ScheduleQualifierFixtures
{
    public function __construct(
        private FixtureGenerationReader $reader,
        private FixtureGenerationRules $rules,
        private FixtureGenerationActions $actions,
    ) {}

    public function execute(array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $duelsPerDeck, int $matchesPerDay, string $phase): int
    {
        if(!in_array((string)($competition['execution_model']??'manual'),['swiss','swiss_top_cut'],true)) throw new InvalidArgumentException('El programador suizo no puede ejecutar otro motor.');

        $this->rules->requireExecutionType((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
        $competition = $this->reader->lockCompetition($competition);
        $competition=$this->rules->teamContext($competition);$this->rules->requireSupportedModel($competition);
        $this->rules->assertFixtureMutable((int)($competition['id'] ?? 0));
        $selectedDeckIds=$this->rules->admit($competition,$selectedDeckIds);
        return $this->actions->workflow($competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);
    }
}
