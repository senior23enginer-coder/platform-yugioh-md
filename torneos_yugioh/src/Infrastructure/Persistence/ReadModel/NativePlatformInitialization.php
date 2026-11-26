<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\Bootstrap\PlatformInitializationPort;

final readonly class NativePlatformInitialization implements PlatformInitializationPort
{
    public function __construct(private PDO $pdo) {}

    public function ensureCoreSchema(): void
    {
        \ensure_ygo_tables($this->pdo);
    }

    public function ensureMatchCompletionColumn(): void
    {
        \ygo_add_column($this->pdo, 'sx_ygo_matches', 'completed_at', 'DATETIME NULL');
    }

    public function ensureStatisticsSchema(): void
    {
        \ygo_stats_schema($this->pdo);
    }

    public function ensureFfaSchema(): void
    {
        \ygo_ffa_schema($this->pdo);
    }

    public function bootstrapGlobalStatistics(): void
    {
        \ygo_stats_bootstrap_global($this->pdo);
    }

    public function ensureCompetitionParametersColumn(): void
    {
        \ygo_add_column($this->pdo, 'sx_ygo_competitions', 'execution_parameters', 'LONGTEXT NULL');
    }

    public function seedDatabase(array $divisions, array $fixtures): array
    {
        return \seed_ygo_database($this->pdo, $divisions, $fixtures);
    }

    public function ensureSeasonSchema(): void
    {
        \ygo_ensure_seasons_schema($this->pdo);
    }

    public function initializeSeasonContext(): void
    {
        \ygo_season_initialize_context($this->pdo);
    }

    public function closeExpiredSeasons(): void
    {
        \ygo_season_close_expired($this->pdo);
    }

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function ensureCatalogs(int $seasonId): void
    {
        \ygo_ensure_default_catalogs($this->pdo, $seasonId);
    }

    public function loadCatalogs(int $seasonId): array
    {
        return \ygo_load_catalog_groups($this->pdo, $seasonId);
    }
}
