<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Teams\TeamEventConfiguration;
use StationX\Yugioh\Infrastructure\Persistence\Teams\NativeTeamEventRules;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoTeamEventConfigurationStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class TeamEventModule
{
    public static function configuration(PDO $pdo): TeamEventConfiguration
    {
        return new TeamEventConfiguration(
            new PdoTeamEventConfigurationStore($pdo),
            new NativeTeamEventRules($pdo),
            new PdoTransactionBoundary($pdo),
        );
    }
}
