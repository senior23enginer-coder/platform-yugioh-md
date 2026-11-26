<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\DeleteCompetition;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionDeletionStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class CompetitionDeletionModule
{
    public static function create(PDO $pdo): DeleteCompetition
    {
        return new DeleteCompetition(
            new PdoCompetitionDeletionStore($pdo),
            new PdoTransactionBoundary($pdo, false),
        );
    }
}
