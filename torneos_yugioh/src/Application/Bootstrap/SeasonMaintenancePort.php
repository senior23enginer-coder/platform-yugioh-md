<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

interface SeasonMaintenancePort
{
    public function ensureSettings(): void;

    public function ensureLabels(): void;

    public function removeSeededDivisions(int $seasonId, array $definitions): int;

    public function ensureRulesets(int $seasonId, array $options): void;

    public function ensureRulesetVersions(int $seasonId): void;

    public function ensureMatchTypes(): void;

    public function ensureGameRules(int $seasonId): void;

    public function ensureGoatStructure(int $seasonId): array;

    public function applyHistoricalSchedule(): array;

    public function activateDueCompetitions(): int;

    public function ensureCompetitionCodes(int $seasonId): void;

    public function syncDeckTeams(): int;

    public function normalizeDeckFormats(): int;

    public function seedDeckAnalysis(): int;
}
