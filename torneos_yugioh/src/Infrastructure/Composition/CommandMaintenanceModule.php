<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Commands\ArchiveQaData;
use StationX\Yugioh\Application\Commands\SynchronizeOfflineForm;
use StationX\Yugioh\Application\Matches\SaveFfaAction;
use StationX\Yugioh\Infrastructure\Http\FormActions\NativeOfflineFormExecutor;
use StationX\Yugioh\Infrastructure\Http\FormActions\PdoOfflineFormStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoQaArchiveStore;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoFfaActionGateway;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class CommandMaintenanceModule
{
    public static function offline(PDO $pdo): SynchronizeOfflineForm
    {
        return new SynchronizeOfflineForm(
            new PdoOfflineFormStore($pdo),
            new NativeOfflineFormExecutor($pdo),
        );
    }

    public static function ffa(PDO $pdo): SaveFfaAction
    {
        return new SaveFfaAction(new PdoFfaActionGateway($pdo));
    }

    public static function qa(PDO $pdo): ArchiveQaData
    {
        return new ArchiveQaData(
            new PdoQaArchiveStore($pdo),
            new PdoTransactionBoundary($pdo, false),
        );
    }
}
