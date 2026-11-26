<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Bootstrap\RunHistoricalBackfills;
use StationX\Yugioh\Infrastructure\Persistence\ReadModel\PdoHistoricalBackfills;

final class BootstrapModule
{
    public static function backfills(PDO $pdo): RunHistoricalBackfills
    {
        return new RunHistoricalBackfills(new PdoHistoricalBackfills($pdo));
    }
}
