<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Decks\SaveDeckAnalysisProfile;
use StationX\Yugioh\Application\Decks\SaveMatchupAnalysis;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckAnalysisStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class DeckAnalysisModule
{
    public static function profile(PDO $pdo): SaveDeckAnalysisProfile
    {
        return new SaveDeckAnalysisProfile(new PdoDeckAnalysisStore($pdo), new PdoTransactionBoundary($pdo));
    }

    public static function matchup(PDO $pdo): SaveMatchupAnalysis
    {
        return new SaveMatchupAnalysis(new PdoDeckAnalysisStore($pdo), new PdoTransactionBoundary($pdo));
    }
}
