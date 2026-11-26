<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Bootstrap\EnrichPlatformData;
use StationX\Yugioh\Infrastructure\Persistence\ReadModel\NativeBootstrapData;
use StationX\Yugioh\Application\Bootstrap\InitializePlatform;
use StationX\Yugioh\Infrastructure\Persistence\ReadModel\NativePlatformInitialization;
use StationX\Yugioh\Application\Bootstrap\MaintainSeason;
use StationX\Yugioh\Infrastructure\Persistence\ReadModel\NativeSeasonMaintenance;
use StationX\Yugioh\Application\Bootstrap\RunHistoricalBackfills;
use StationX\Yugioh\Infrastructure\Persistence\ReadModel\PdoHistoricalBackfills;

final class BootstrapModule
{
    public static function initialize(PDO $pdo): InitializePlatform
    {
        return new InitializePlatform(new NativePlatformInitialization($pdo));
    }

    public static function maintain(PDO $pdo): MaintainSeason
    {
        return new MaintainSeason(new NativeSeasonMaintenance($pdo));
    }

    public static function backfills(PDO $pdo): RunHistoricalBackfills
    {
        return new RunHistoricalBackfills(new PdoHistoricalBackfills($pdo));
    }
    public static function enrich(PDO $pdo): EnrichPlatformData
    {
        return new EnrichPlatformData(new NativeBootstrapData($pdo));
    }
}
