<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\DeckWorlds\AssignDeckWorlds;
use StationX\Yugioh\Application\DeckWorlds\DeckWorldsQueries;
use StationX\Yugioh\Application\DeckWorlds\DeckWorldsServices;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Infrastructure\Persistence\DeckWorlds\LegacyTcgEligibility;
use StationX\Yugioh\Infrastructure\Persistence\DeckWorlds\PdoWorldAssignmentRepository;
use StationX\Yugioh\Infrastructure\Time\SystemClock;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

/** Composition only: receiving a connection does not query it or initialize its schema. */
final class DeckWorldsModule
{
    public static function create(PDO $pdo, ?Clock $clock = null): DeckWorldsServices
    {
        $repository = new PdoWorldAssignmentRepository($pdo);
        $eligible = new LegacyTcgEligibility($pdo);
        return new DeckWorldsServices(
            new AssignDeckWorlds(
                $repository,
                $eligible,
                new PdoTransactionBoundary($pdo),
                $clock ?? new SystemClock(),
            ),
            new DeckWorldsQueries($repository, $eligible),
        );
    }
}
