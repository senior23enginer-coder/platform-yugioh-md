<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\ReadModel\LoadPlatformReadModel;
use StationX\Yugioh\Infrastructure\Persistence\ReadModel\NativePlatformReadModelServices;
use StationX\Yugioh\Infrastructure\Persistence\ReadModel\PdoPlatformReadModelReader;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class PlatformReadModelModule
{
    public static function create(PDO $pdo): LoadPlatformReadModel
    {
        return new LoadPlatformReadModel(
            new PdoPlatformReadModelReader($pdo),
            new NativePlatformReadModelServices($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
