<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final readonly class InitializePlatform
{
    public function __construct(private PlatformInitializationPort $port) {}

    public function execute(array $divisions, array $fixtures): array
    {
        $this->port->ensureCoreSchema();
        $this->port->ensureMatchCompletionColumn();
        $this->port->ensureStatisticsSchema();
        $this->port->ensureFfaSchema();
        $this->port->bootstrapGlobalStatistics();
        $this->port->ensureCompetitionParametersColumn();
        $seedCounts = $this->port->seedDatabase($divisions, $fixtures);
        $this->port->ensureSeasonSchema();
        $this->port->initializeSeasonContext();
        $this->port->closeExpiredSeasons();
        $seasonId = $this->port->currentSeasonId();
        $this->port->ensureCatalogs($seasonId);
        $catalogGroups = $this->port->loadCatalogs($seasonId);
        return compact('seedCounts', 'seasonId', 'catalogGroups');
    }
}
