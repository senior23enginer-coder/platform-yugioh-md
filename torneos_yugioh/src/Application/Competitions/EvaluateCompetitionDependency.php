<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

final readonly class EvaluateCompetitionDependency
{
    public function __construct(private CompetitionDependencyFacts $facts) {}

    public function execute(array $dependency, bool $completed, bool $hasResults): bool
    {
        $condition = (string) ($dependency['condition_type'] ?: 'completed_with_results');
        $requiredId = (int) $dependency['depends_on_competition_id'];
        if ($condition === 'completed') {
            return $completed;
        }
        if ($condition === 'has_results') {
            return $hasResults;
        }
        if ($condition === 'top_n') {
            $take = max(1, (int) ($dependency['take_count'] ?? 0) ?: 4);
            return $completed && count($this->facts->standings($requiredId)) >= $take;
        }
        if ($condition === 'winner_to_final') {
            return $completed &&
                ($this->facts->honorCount($requiredId) > 0 ||
                    count($this->facts->standings($requiredId)) > 0);
        }
        if ($condition === 'defending_champion') {
            return $this->facts->honorCount($requiredId) > 0;
        }
        if ($condition === 'creates_divisions') {
            return $completed && $this->facts->divisionCount($requiredId) > 0;
        }
        return $completed && $hasResults;
    }
}
