<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Seasons\SeasonLifecycle;
use StationX\Yugioh\Infrastructure\Persistence\Seasons\PdoSeasonLifecycleStore;
use StationX\Yugioh\Infrastructure\Time\SystemClock;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class SeasonLifecycleModule
{
    public static function create(PDO $pdo): SeasonLifecycle
    {
        return new SeasonLifecycle(
            new PdoSeasonLifecycleStore($pdo),
            new PdoTransactionBoundary($pdo, false),
            new PdoTransactionBoundary($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
