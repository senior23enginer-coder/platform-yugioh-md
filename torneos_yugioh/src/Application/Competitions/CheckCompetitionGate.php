<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Competitions\CompetitionGatePolicy;

final readonly class CheckCompetitionGate
{
    public function __construct(
        private CompetitionGateReader $reader,
        private CompetitionDependencyFacts $facts,
        private EvaluateCompetitionDependency $evaluator,
        private Clock $clock,
    ) {}

    public function execute(int $competitionId): array
    {
        $season = $this->reader->season($competitionId);
        if (!CompetitionGatePolicy::seasonOpen($season, $this->clock->now()->format('Y-m-d'))) {
            return [
                'can_execute' => false,
                'message' => 'Temporada finalizada: evento cerrado, disponible solo para consulta.',
                'dependencies' => [],
            ];
        }
        $target = $this->reader->competition($competitionId);
        if ($target) {
            $admission = $this->reader->admission($target);
            if ($admission !== null && !$admission['ok']) {
                return ['can_execute' => false, 'message' => $admission['message'], 'dependencies' => []];
            }
        }
        $dependencies = $this->reader->dependencies()[$competitionId] ?? [];
        $resolved = [];
        foreach ($dependencies as $dependency) {
            $completed = CompetitionGatePolicy::completed($dependency);
            $resultCount = $this->facts->resultCount((int) $dependency['depends_on_competition_id']);
            $ok = $this->evaluator->execute($dependency, $completed, $resultCount > 0);
            $resolved[] = CompetitionGatePolicy::item($dependency, $resultCount, $ok);
        }
        return CompetitionGatePolicy::summarize($resolved);
    }
}
