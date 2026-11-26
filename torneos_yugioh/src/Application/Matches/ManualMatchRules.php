<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface ManualMatchRules
{
    public function teamKind(array $event): string;
    public function gameRule(int $posted, string $phase): ?int;
    public function validateDecks(
        ?int $competitionId,
        int $divisionId,
        array $decks,
        int $participants,
    ): array;
    public function rulesetDecks(string $ruleset): array;
    public function validateSchedule(?string $date, ?string $time, string $venue): array;
    public function workflowModels(): array;
    public function requireSupported(array $event): void;
    public function rebuild(int $id, string $phase): void;
}
