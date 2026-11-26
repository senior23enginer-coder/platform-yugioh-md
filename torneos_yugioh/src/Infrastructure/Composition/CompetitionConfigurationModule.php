<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Competitions\ConfigureCompetition;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativeConfigurationContext;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativeConfigurationMedia;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativeConfigurationRoster;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativeConfigurationSchedule;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionConfigurationStore;
use StationX\Yugioh\Infrastructure\Time\SystemClock;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class CompetitionConfigurationModule
{
    public static function create(PDO $pdo): ConfigureCompetition
    {
        return new ConfigureCompetition(
            new PdoCompetitionConfigurationStore($pdo),
            new NativeConfigurationContext($pdo),
            new NativeConfigurationRoster($pdo),
            new NativeConfigurationMedia($pdo),
            new NativeConfigurationSchedule($pdo),
            new PdoTransactionBoundary($pdo, false),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
