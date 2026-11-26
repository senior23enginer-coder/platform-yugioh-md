<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionDefinitionStore
{
    public function ruleset(int $seasonId, string $competitionRuleset): array;

    public function existing(int $id, int $seasonId): array;

    public function linkedDivisions(int $seasonId, int $id): array;

    public function activeDivisions(int $seasonId, array $postedDivisionIds): array;

    public function update(int $id, array $values): void;

    public function insert(array $values): int;

    public function linkDivisions(int $seasonId, int $targetCompetitionId, array $postedDivisionIds): void;

    public function setParameters(int $targetCompetitionId, ?string $parameters): void;

    public function clearDependencies(int $targetCompetitionId): void;

    public function validQualifier(int $conditionQualifierId, int $seasonId): int;

    public function setDependency(int $seasonId, int $targetCompetitionId, int $validQualifierId): void;
}
