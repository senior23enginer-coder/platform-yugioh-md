<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionConfigurationStore
{
    public function competition(int $seasonId, int $competitionId): array;

    public function matchCount(int $competitionId): int;

    public function hasRuleset(int $seasonId, string $eventRuleset): bool;

    public function resumeCancelled(int $seasonId, int $competitionId, string $nextStatus): void;

    public function audit(int $competitionId, array $payload): void;

    public function roster(int $competitionId): array;

    public function ruleCode(int $ruleId): string;

    public function scheduledQualifierCount(int $competitionId): int;

    public function pendingMatchCount(int $competitionId): int;

    public function replaceRoster(int $competitionId, array $selectedDeckIds): void;

    public function updateConfiguration(array $values): void;

    public function scheduledRows(int $competitionId): \Closure;

    public function clearDependencies(int $competitionId): void;

    public function validQualifier(int $seasonId, int $conditionQualifierId): int;

    public function setQualifierDependency(int $seasonId, int $competitionId, int $validQualifierId): void;
}
