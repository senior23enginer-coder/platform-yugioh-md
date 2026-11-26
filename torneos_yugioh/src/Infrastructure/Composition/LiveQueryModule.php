<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Matches\ReadLiveMatches;
use StationX\Yugioh\Application\Statistics\StatisticsQuery;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoLiveQueryReader;
use StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsPublicationReader;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class LiveQueryModule
{
    public static function statistics(PDO $pdo): StatisticsQuery
    {
        return new StatisticsQuery(
            StatisticsModule::view($pdo),
            new PdoStatisticsPublicationReader($pdo),
            StatisticsModule::replay(),
        );
    }

    public static function matches(PDO $pdo): ReadLiveMatches
    {
        return new ReadLiveMatches(
            new PdoLiveQueryReader($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
