<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\AdvanceCompetitionPhase;
use StationX\Yugioh\Application\Competitions\CompetitionPhaseQueries;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativePhaseAdvanceActions;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoPhaseAdvanceReader;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class CompetitionPhaseModule
{
    public static function queries(PDO $pdo): CompetitionPhaseQueries
    {
        return new CompetitionPhaseQueries(new PdoPhaseAdvanceReader($pdo));
    }

    public static function advance(PDO $pdo): AdvanceCompetitionPhase
    {
        $reader = new PdoPhaseAdvanceReader($pdo);
        return new AdvanceCompetitionPhase(
            $reader,
            new NativePhaseAdvanceActions($pdo),
            new CompetitionPhaseQueries($reader),
            new PdoTransactionBoundary($pdo),
        );
    }
}
