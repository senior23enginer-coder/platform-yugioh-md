<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Decklists\SaveDecklist;
use StationX\Yugioh\Infrastructure\Persistence\Decklists\PdoDecklists;
use StationX\Yugioh\Infrastructure\Network\CardCatalog\NativeDeckCardCatalog;
use StationX\Yugioh\Infrastructure\Transactions\{NativeEventWriteLock, PdoTransactionBoundary};

/** Native persistence shares the same PDO, event lock and transaction owner across the entire save. */
final class DecklistModule
{
    public static function reader(PDO $pdo): PdoDecklists
    {
        return new PdoDecklists($pdo);
    }
    public static function save(PDO $pdo): SaveDecklist
    {
        $store = self::reader($pdo);
        return new SaveDecklist(
            $store,
            $store,
            new NativeDeckCardCatalog(CardCatalogModule::create()->lookup),
            new NativeEventWriteLock($pdo),
            new PdoTransactionBoundary($pdo),
        );
    }
}
