<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Statistics\CalculateStatistics;
use StationX\Yugioh\Application\Statistics\ReadStatisticsSource;
use StationX\Yugioh\Application\Statistics\RefreshStatistics;
use StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsSourceReader;
use StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsRevisionStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class StatisticsModule
{
    public static function replay(?Clock $clock = null): CalculateStatistics
    {
        return new CalculateStatistics($clock ?? new SystemClock());
    }

    public static function refresh(PDO $pdo): RefreshStatistics
    {
        return new RefreshStatistics(
            new PdoStatisticsRevisionStore($pdo),
            new ReadStatisticsSource(new PdoStatisticsSourceReader($pdo)),
            self::replay(),
            new PdoTransactionBoundary($pdo, false),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
