<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

interface PlatformInitializationPort
{
    public function ensureCoreSchema(): void;

    public function ensureMatchCompletionColumn(): void;

    public function ensureStatisticsSchema(): void;

    public function ensureFfaSchema(): void;

    public function bootstrapGlobalStatistics(): void;

    public function ensureCompetitionParametersColumn(): void;

    public function seedDatabase(array $divisions, array $fixtures): array;

    public function ensureSeasonSchema(): void;

    public function initializeSeasonContext(): void;

    public function closeExpiredSeasons(): void;

    public function currentSeasonId(): int;

    public function ensureCatalogs(int $seasonId): void;

    public function loadCatalogs(int $seasonId): array;
}
