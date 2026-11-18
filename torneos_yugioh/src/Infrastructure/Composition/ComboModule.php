<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use DateTimeZone;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Combos\ComboServices;
use StationX\Yugioh\Application\Combos\PrepareComboDocument;
use StationX\Yugioh\Application\Combos\SaveCreatedCombo;
use StationX\Yugioh\Application\Combos\ComboQueries;
use StationX\Yugioh\Infrastructure\Editorial\NativeComboSources;
use StationX\Yugioh\Infrastructure\Persistence\Combos\PdoComboInventory;
use StationX\Yugioh\Infrastructure\Persistence\Combos\PdoComboRepository;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

/** Wires ports once. Construction opens no transaction and creates no tables. */
final class ComboModule
{
    public static function create(PDO $pdo, ?Clock $clock = null): ComboServices
    {
        $sources = new NativeComboSources();
        $inventory = new PdoComboInventory($pdo, $sources);
        $records = new PdoComboRepository($pdo);
        $documents = new PrepareComboDocument($sources);
        return new ComboServices(
            new SaveCreatedCombo(
                $inventory,
                $records,
                $documents,
                $sources,
                new PdoTransactionBoundary($pdo, false),
                $clock ?? new SystemClock(new DateTimeZone('UTC')),
            ),
            new ComboQueries($inventory, $records),
            $documents,
            $inventory,
        );
    }
}
