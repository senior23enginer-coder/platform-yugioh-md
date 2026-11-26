<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\RecordCompetitionHonor;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionHonorReader;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionHonorWriter;

final class CompetitionHonorModule
{
    public static function create(PDO $pdo): RecordCompetitionHonor
    {
        return new RecordCompetitionHonor(
            new PdoCompetitionHonorReader($pdo),
            new PdoCompetitionHonorWriter($pdo),
        );
    }
}
