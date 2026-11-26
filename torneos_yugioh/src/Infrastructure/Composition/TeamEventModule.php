<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Teams\SaveTeamMatchResult;
use StationX\Yugioh\Application\Teams\ExpandTeamIndividualResults;
use StationX\Yugioh\Infrastructure\Persistence\Teams\NativeTeamResultServices;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoTeamIndividualReader;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoTeamResultStore;
use StationX\Yugioh\Infrastructure\Time\SystemClock;
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

    public static function result(PDO $pdo): SaveTeamMatchResult
    {
        return new SaveTeamMatchResult(
            new PdoTeamResultStore($pdo),
            new NativeTeamResultServices($pdo),
            self::configuration($pdo),
            new PdoTransactionBoundary($pdo),
            new SystemClock(new DateTimeZone('America/Bogota')),
        );
    }

    public static function individual(PDO $pdo): ExpandTeamIndividualResults
    {
        return new ExpandTeamIndividualResults(new PdoTeamIndividualReader($pdo));
    }
}
