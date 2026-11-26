<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Leagues\ApplyDivisionMovements;
use StationX\Yugioh\Application\Leagues\ConfigureDivisionMovements;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoDivisionMovementReader;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoDivisionMovementWriter;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class DivisionMovementModule
{
    public static function configure(PDO $pdo): ConfigureDivisionMovements
    {
        return new ConfigureDivisionMovements(
            new PdoDivisionMovementReader($pdo),
            new PdoDivisionMovementWriter($pdo),
        );
    }

    public static function apply(PDO $pdo): ApplyDivisionMovements
    {
        return new ApplyDivisionMovements(
            new PdoDivisionMovementReader($pdo),
            new PdoDivisionMovementWriter($pdo),
            new PdoTransactionBoundary($pdo),
        );
    }
}
