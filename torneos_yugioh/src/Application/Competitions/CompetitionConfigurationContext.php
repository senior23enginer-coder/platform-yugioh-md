<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionConfigurationContext
{
    public function currentSeasonId(): int;

    public function auditSnapshot(int $competitionId): array;

    public function requireSupportedModel(array $competition): void;

    public function teamContext(array $competition): array;

    public function assertFixtureMutable(int $competitionId): void;

    public function recordHistory(int $seasonId, int $competitionId, ?string $from, string $to, string $action, array $payload): void;

    public function gate(int $competitionId): array;

    public function validGameRuleId(int $seasonId, int $postedRuleId, ?int $fallbackRuleId, string $competitionType, string $executionModel): ?int;

    public function templateBaseCode(string $code): string;

    public function workflowModels(): array;

    public function expectedQualifierMatches(int $selectedDeckCount, int $participantsPerSide, int $duelsPerDeck): int;
}
