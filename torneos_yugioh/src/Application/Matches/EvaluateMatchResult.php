<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use StationX\Yugioh\Domain\Matches\DuelSequence;
use StationX\Yugioh\Domain\Matches\EventResultPolicy;
use StationX\Yugioh\Domain\Matches\MatchRulePolicy;

final readonly class EvaluateMatchResult
{
    public function __construct(
        private MatchRuleReader $rules,
        private MatchEventReader $events,
        private ResolveMatchRule $resolver,
    ) {}

    public function evaluate(int $matchId, array $values): array
    {
        $bestOf = MatchRulePolicy::bestOf($this->rules->bestOf($matchId));
        $ruleId = $this->resolver->execute($matchId);
        $conditions = $ruleId > 0 ? $this->rules->conditions($ruleId) : [];
        return DuelSequence::evaluateSequence($values, $bestOf, $conditions);
    }

    public function applyEvent(int $matchId, array $evaluation, array $currentValues = []): array
    {
        $competition = $this->events->competition($matchId);
        if (!$competition) {
            return $evaluation + ['model_effects' => []];
        }
        $phase = $this->events->phase($matchId);
        $workflow = $this->events->workflow($competition);
        return EventResultPolicy::apply(
            $competition,
            $phase,
            $workflow,
            $evaluation,
            $currentValues,
        );
    }
}
