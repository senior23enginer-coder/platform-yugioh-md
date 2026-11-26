<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\ComputeCompetitionStandings;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionStandingsReader;

final class CompetitionStandingsModule
{
    public static function create(PDO $pdo): ComputeCompetitionStandings
    {
        return new ComputeCompetitionStandings(new PdoCompetitionStandingsReader($pdo));
    }
}
