<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\CheckCompetitionCompletion;
use StationX\Yugioh\Application\Competitions\ManageCompetitionCodes;
use StationX\Yugioh\Application\Competitions\RecordCompetitionHistory;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionCodeStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionHistoryWriter;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionLifecycleReader;

final class CompetitionLifecycleModule
{
    public static function completion(PDO $pdo): CheckCompetitionCompletion
    {
        return new CheckCompetitionCompletion(new PdoCompetitionLifecycleReader($pdo));
    }

    public static function history(PDO $pdo): RecordCompetitionHistory
    {
        return new RecordCompetitionHistory(new PdoCompetitionHistoryWriter($pdo));
    }

    public static function codes(PDO $pdo): ManageCompetitionCodes
    {
        return new ManageCompetitionCodes(new PdoCompetitionCodeStore($pdo));
    }
}
