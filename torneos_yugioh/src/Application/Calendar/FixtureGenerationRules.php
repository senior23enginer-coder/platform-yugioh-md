<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface FixtureGenerationRules
{
    public function requireExecutionType(string $type, string $execution): void;

    public function requireSupportedModel(array $competition): void;

    public function teamContext(array $competition): array;

    public function assertFixtureMutable(int $competitionId): void;

    public function admit(array $competition, array $ids): array;

    public function admissionLoad(array $competition): ?array;

    public function teamKind(array $competition): string;

    public function workflowModels(): array;

    public function shuffle(array $ids): array;

    public function rosterGroups(array $ids, int $participantsPerSide): array;

    public function leaguePlan(
        int $competitionId,
        array $ids,
        int $participantsPerSide,
        bool $doubleRound,
    ): array;
}
