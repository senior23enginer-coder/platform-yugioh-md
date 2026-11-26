<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\MaintainCompetitions;
use StationX\Yugioh\Application\Matches\DeleteManualFriendly;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativeCompetitionMaintenanceServices;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionMaintenanceStore;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoManualFriendlyStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class CompetitionMaintenanceModule
{
    public static function competitions(PDO $pdo): MaintainCompetitions
    {
        return new MaintainCompetitions(
            new PdoCompetitionMaintenanceStore($pdo),
            new NativeCompetitionMaintenanceServices($pdo),
        );
    }

    public static function friendly(PDO $pdo): DeleteManualFriendly
    {
        return new DeleteManualFriendly(
            new PdoManualFriendlyStore($pdo),
            new PdoTransactionBoundary($pdo, false),
        );
    }
}
