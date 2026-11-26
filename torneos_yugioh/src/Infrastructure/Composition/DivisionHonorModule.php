<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\SaveDivision;
use StationX\Yugioh\Application\Competitions\SaveManualHonor;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoDivisionWriteStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoManualHonorStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class DivisionHonorModule
{
    public static function division(PDO $pdo): SaveDivision
    {
        return new SaveDivision(new PdoDivisionWriteStore($pdo));
    }

    public static function honor(PDO $pdo): SaveManualHonor
    {
        return new SaveManualHonor(new PdoManualHonorStore($pdo), new PdoTransactionBoundary($pdo));
    }
}
