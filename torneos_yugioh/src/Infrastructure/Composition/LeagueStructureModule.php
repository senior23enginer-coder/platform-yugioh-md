<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Leagues\ComputeLeagueStandings;
use StationX\Yugioh\Application\Leagues\EnsureGoatLeague;
use StationX\Yugioh\Application\Leagues\SyncFormatCategories;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoFormatCategoryStore;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoGoatLeagueStore;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoLeagueStandingsReader;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class LeagueStructureModule
{
    public static function standings(PDO $pdo): ComputeLeagueStandings
    {
        return new ComputeLeagueStandings(new PdoLeagueStandingsReader($pdo));
    }

    public static function categories(PDO $pdo): SyncFormatCategories
    {
        return new SyncFormatCategories(new PdoFormatCategoryStore($pdo));
    }

    public static function goat(PDO $pdo): EnsureGoatLeague
    {
        return new EnsureGoatLeague(
            new PdoGoatLeagueStore($pdo),
            new PdoFormatCategoryStore($pdo),
            new PdoTransactionBoundary($pdo, false),
        );
    }
}
